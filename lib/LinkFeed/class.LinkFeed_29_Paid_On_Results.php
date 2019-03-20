<?php

require_once 'text_parse_helper.php';
/*
if(SID == 'bdg02'){
    define('API_KEY_29', 'EPPPSWWEKKPUHKYLQQWZ');
    define('AFFID_INAFF_29', 47119);
}
else{
    //define('API_KEY_29', 'HTHJVBUTZOPAKSOROYXS');
    //define('AFFID_INAFF_29', 19933);
	//Jimmy
	define('API_KEY_29', 'HETHJOHUGHTNEUQSYLPN');
	define('AFFID_INAFF_29', 47133);
}
*/
class LinkFeed_29_Paid_On_Results
{
	var $info = array(
		"ID" => "29",
		"Name" => "Paid On Results",
		"IsActive" => "YES",
		"ClassName" => "LinkFeed_29_Paid_On_Results",
		"LastCheckDate" => "1970-01-01",
	);

	function __construct($aff_id,$oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		
		$this->file = "programlog_{$aff_id}_".date("Ymd_His").".csv";
	}

	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
		$this->GetProgramByApi();
		echo "Craw Program end @ ".date("Y-m-d H:i:s")."\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
	}

	function GetProgramByApi()
	{
		echo "\tGet Program by api start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$program_num = 0;
		$base_program_num = 0;

		//login
		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);
		
		//get paymentDays
		$page_url = "https://affiliate.paidonresults.com/cgi-bin/merchant-dir.pl";
		$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "post",
				"postdata" => "type=0",
		);
		$re = $this->oLinkFeed->GetHttpResult($page_url, $request);
		//print_r($re);exit;
		$re = $re['content'];
		$re = trim($this->oLinkFeed->ParseStringBy2Tag($re, '<img src="/images/inv-5x5px.gif" width="1" height="3"></td></tr>', '<Tr><td bgcolor="#077dbb"><img src="/images/inv-5x5px.gif" width="3" height="2"></td>'));
		$re = explode('<tr bgcolor', $re);
		unset($re[0]);
		//print_r($re);exit;
		$arr_prgmByPage = array();
		foreach ($re as $v)
		{
			$LogoUrl = "https://affiliate.paidonresults.com/logos/".trim($this->oLinkFeed->ParseStringBy2Tag($v, 'img src="/logos/', '"'));
			$strMerID = trim($this->oLinkFeed->ParseStringBy2Tag($v, 'img src="/logos/', '.'));
			if (!$strMerID)
				continue;
			$PaymentDays = trim($this->oLinkFeed->ParseStringBy2Tag($v, 'text-decoration:none;">', '</a>'));
			if ($PaymentDays == 'New<br>Merchant' || $PaymentDays == 'Average<br>Coming Soon'){
				$PaymentDays = 0;
			}elseif ($PaymentDays == 'Less than<br>24 Hours'){
				$PaymentDays = 1;
			}else{
				$PaymentDays = str_replace(' Days', '', $PaymentDays);
				$PaymentDays = str_replace(' Day', '', $PaymentDays);
				$PaymentDays = intval($PaymentDays);
			}
			$arr_prgmByPage[$strMerID] = array(
					"PaymentDays" => $PaymentDays,
					"LogoUrl" => $LogoUrl,
			);
		}
		//print_r($arr_prgmByPage);exit;
		$apiurl = sprintf("http://affiliate.paidonresults.com/api/merchant-directory?apikey=%s&Format=XML&AffiliateID=%s&MerchantCategories=ALL&Fields=MerchantID,MerchantCaption,MerchantCategory,AccountManager,MerchantName,MerchantStatus,DateLaunched,AffiliateStatus,ApprovalRate,DeepLinks,AccountManagerEmail,MerchantURL,CookieLength,AffiliateURL,SampleCommissionRates,AverageCommission&JoinedMerchants=YES&MerchantsNotJoined=YES", $this->info['APIKey1'], $this->info['APIKey2']);
		$request = array("method" => "get");
		$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"],"merchant_xml_".date("YmdH").".dat", "cache_feed");
		if(!$this->oLinkFeed->fileCacheIsCached($cache_file)){
			$r = $this->oLinkFeed->GetHttpResult($apiurl, $request);
			$result = $r["content"];
			$this->oLinkFeed->fileCachePut($cache_file,$result);
		}
		$xml = new DOMDocument();
		$xml->load($cache_file);
		
		
		//parse XML
		$advertiser_list = $xml->getElementsByTagName("Merchants");
		foreach($advertiser_list as $advertiser)
		{
			$advertiser_info = array();
			$childnodes = $advertiser->getElementsByTagName("*");
			foreach($childnodes as $node){
				$advertiser_info[$node->nodeName] = trim($node->nodeValue);
			}
			
			$strMerID = $advertiser_info['MerchantID'];
			$AffiliateStatus = $advertiser_info['AffiliateStatus'];
			if($AffiliateStatus == "JOINED"){
				$Partnership = "Active";
			}else{
				$Partnership = "NoPartnership";
			}
			
			$arr_prgm[$strMerID] = array(
					'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
					'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
					'IdInAff' => $strMerID,
					'Partnership' => $Partnership                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
			);
			
			if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId']))
			{
				$desc = $advertiser_info['MerchantCaption'];
				$CategoryExt = $advertiser_info['MerchantCategory'];
				$JoinDate = $advertiser_info['DateLaunched'];
				$JoinDate = date("Y-m-d H:i:s", strtotime($JoinDate));
				$Name = $advertiser_info['MerchantName'];
				$CookieTime = $advertiser_info['CookieLength'];
				$Homepage = $advertiser_info['MerchantURL'];
				$prgm_url = $advertiser_info['AffiliateURL'];
				$CommissionExt = empty($advertiser_info['SampleCommissionRates']) ? $advertiser_info['AverageCommission'] : $advertiser_info['SampleCommissionRates'];
				$Contacts = $advertiser_info['AccountManager']." Email:".$advertiser_info['AccountManagerEmail'];
				$LogoUrl = isset($arr_prgmByPage[$strMerID]['LogoUrl']) ? $arr_prgmByPage[$strMerID]['LogoUrl'] : '';
				$PaymentDays = isset($arr_prgmByPage[$strMerID]['PaymentDays']) ? $arr_prgmByPage[$strMerID]['PaymentDays'] : '';
				
				$StatusInAffRemark = $advertiser_info['MerchantStatus'];
				if($StatusInAffRemark == "LIVE"){
					$StatusInAff = "Active";
				}else{
					$StatusInAff = "Offline";
				}
	
				$SupportDeepurl = $advertiser_info['DeepLinks'];
				if(stripos($SupportDeepurl, "yes") !== false){
					$SupportDeepurl = "YES";
				}else{
					$SupportDeepurl = "NO";
				}
				
				//SEMPolicyExt
				$SEMPolicyExt = '';
				$sem_url = "https://affiliate.paidonresults.com/cgi-bin/view-merchant.pl?site_id={$strMerID}";
				$sem_res = $this->oLinkFeed->GetHttpResult($sem_url,$request);
				$sem_res = $sem_res['content'];
				$needle = 'PPC Restrictions';
				if (strpos($sem_res, $needle)){
					$SEMPolicyExt = trim($this->oLinkFeed->ParseStringBy2Tag($sem_res,'<td align="left" bgcolor="#FFF0F0" style="border:2px solid red;padding:10px;">',"</td>"));
					$SEMPolicyExt =trim($this->oLinkFeed->ParseStringBy2Tag($SEMPolicyExt , '<div align="justify">' , '</div>'));
				}else {
					$SEMPolicyExt = '';
				}
				$arr_prgm[$strMerID] += array(
					'CrawlJobId' => $this->info['crawlJobId'],
					"Name" => addslashes($Name),
					"Homepage" => addslashes($Homepage),
					"Description" => addslashes($desc),
					"CategoryExt" => addslashes($CategoryExt),
					"CookieTime" => addslashes($CookieTime),
					"CommissionExt" => addslashes($CommissionExt),
					"Contacts" => addslashes($Contacts),
					"JoinDate" => $JoinDate,
					"StatusInAffRemark" => addslashes($StatusInAffRemark),
					"StatusInAff" => $StatusInAff,						//'Active','TempOffline','Offline'
					"SupportDeepUrl" => $SupportDeepurl,
					"TargetCountryExt" => '',
					"LogoUrl" => addslashes($LogoUrl),
					"PaymentDays" => $PaymentDays,
					"SEMPolicyExt" => addslashes($SEMPolicyExt)
                );
				
				$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"],"detail_".date("Ym")."_{$strMerID}.dat", "program", true);
				if(!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
                    $prgm_url = "http://affiliate.paidonresults.com/cgi-bin/view-merchant.pl?site_id={$strMerID}";
                    $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);
                    $prgm_arr = $this->oLinkFeed->GetHttpResult($prgm_url, $request);
                    if ($prgm_arr['code'] == 200) {

                        $results = $prgm_arr['content'];
                        $this->oLinkFeed->fileCachePut($cache_file, $results);
                        //print_r($results);exit;
                    }
                }
                $cache_file = file_get_contents($cache_file);
                //print_r($cache_file);exit;
                $AllowNonaffPromo = 'UNKNOWN';
                $AllowNonaffCoupon = 'UNKNOWN';
                $TermAndCondition = '';
                if($cache_file){
                    if(stripos($cache_file,'Affiliates must not feature any other coupon/promotion codes such as those found in, but not limited to') !== false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file, 'Advertiser reserves the right to reconcile or adjust the value on any transaction that is attributed to another marketing channel') !== false){
                        $AllowNonaffCoupon = 'NO';
                        $AllowNonaffPromo = 'NO';
                        //通用条件
                    }else if(stripos($cache_file,'affiliates can only use the voucher codes supplied') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Voucher sites must only promote codes that have been designated for affiliate use') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Affiliates shouldn’t post, use or feature any discount\/voucher codes from offline media sources.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'publishers on the (.)+affiliate program should only use and monetise voucher codes (.)+ This includes user generated content, this cannot be monetised without the relevant permissions.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'It is not allowed to promote vouchers that have not been communicated via the affiliate channel') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'affiliates may only promote voucher codes') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Affiliates are not to promote any voucher codes that have not been provided') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Affiliates should not display voucher\/discount codes that have been provided for use by other marketing channels.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Affiliates found to be promoting unauthorised discount codes or those issued through other marketing channels') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Affiliates are ONLY allowed to use voucher codes issued to') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Affiliates are requested not to use voucher codes') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Voucher code sites may not list false voucher codes or voucher codes not associated with the affiliate program') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Any sites found to be running voucher codes not specifically authorised') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Publishers may only use coupons and promotional codes that are provided exclusively through the affiliate program.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Affiliates may not use misleading text on affiliate links	 buttons or images to imply that anything besides currently authorized affiliate deals or savings are available.') !==false){
                        $AllowNonaffCoupon = 'NO';
                        $AllowNonaffPromo = 'NO';
                    }else if(stripos($cache_file,'Any discount promotion of our products by affiliates should be authorized') !==false){
                        $AllowNonaffCoupon = 'NO';
                        $AllowNonaffPromo = 'NO';
                    }else if(stripos($cache_file,'The only coupons authorized for use are those that we make directly available to you.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'All coupons must be publicly distributed coupons that are given to the affiliate.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Coupon sites may only post distributed coupons; that is coupons that are given to them or posted within the affiliate interface.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'They need to promote the coupon which we will provide them.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'Publishers may only use coupons and promotional codes that are provided through communication specifically intended for publishers in the affiliate program.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'These are the ONLY promotion codes affiliates are authorized to use in their marketing efforts') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }else if(stripos($cache_file,'will review each coupon offering before allowing an affiliate to use.') !==false){
                        $AllowNonaffCoupon = 'NO';
                    }

                    //$TermAndCondition
                    if(preg_match('@<td align="left" bgcolor="#FFF0F0" style="border:2px solid red;padding:10px;">(.*?)</td>@ms', $cache_file,$matches))
                        $TermAndCondition = $matches[1];
                }
                $arr_prgm[$strMerID]['AllowNonaffPromo'] = $AllowNonaffPromo;
                $arr_prgm[$strMerID]['AllowNonaffCoupon'] = $AllowNonaffCoupon;
                $arr_prgm[$strMerID]['TermAndCondition'] = addslashes($TermAndCondition);
				$base_program_num++;
			}
//			print_r($arr_prgm);exit;
			$program_num++;
			if(count($arr_prgm) >= 100){
				$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
				//$objProgram->updateProgram($this->info["AffId"], $arr_prgm);
				$arr_prgm = array();
			}
		}

		if(count($arr_prgm)){
			$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
			//$objProgram->updateProgram($this->info["AffId"], $arr_prgm);
			unset($arr_prgm);
		}
		echo "\tGet Program by api end\r\n";
		if($program_num < 10){
			mydie("die: program count < 10, please check program.\n");
		}
		echo "\tUpdate ({$base_program_num}) base program.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
	}

}

