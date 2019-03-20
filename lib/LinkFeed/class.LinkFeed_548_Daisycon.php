<?php
require_once 'text_parse_helper.php';

class LinkFeed_548_Daisycon{

	function __construct($aff_id,$oLinkFeed){
        $this->oLinkFeed = $oLinkFeed;
//        $oLinkFeed = new LinkFeed();
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->file = "programlog_{$aff_id}_".date("Ymd_His").".csv";
        $this->getStatus = false;
        /*
        if (SID == 'bdg01'){
        	$this->PublisherID = '381734';
        	$this->MediaID = '267822';
        }else{
        	$this->PublisherID = '387850';
        	$this->MediaID = '279798';
        }
        */
        $this->headers = array( 'Authorization: Basic ' . base64_encode( $this->info['UserName'] . ':' . $this->info['Password'] ) );
        $this->para = array('addheader' => $this->headers);

    }

	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
		$this->GetProgramByApi();
		echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'],'Done');
	}
    
	function GetProgramByApi()
	{
		echo "\tGet Program by api start\r\n";
		$objProgram = new ProgramDb();
		$program_num = 0;
		$base_program_num = 0;
		$arr_prgm = array();
		
		$page = 1;
		$per_page = 100;
		$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "get",
				"postdata" => '',
				"addheader" => $this->headers,
		);
		//get categories arr
		$category_url = "https://services.daisycon.com/categories?page=1&per_page=100";
		$category_r = $this->oLinkFeed->GetHttpResult($category_url, $request);
		$category_r = json_decode($category_r['content'], true);
		//var_dump($category_r);exit;
		foreach ($category_r as $cate)
			$Category_arr[$cate['id']] = $cate['name'];
		
		//get countries arr
		$country_url = "https://services.daisycon.com/locales?page=1&per_page=100";
		$country_r = $this->oLinkFeed->GetHttpResult($country_url, $request);
		$country_r = json_decode($country_r['content'], true);
		//var_dump($country_r);exit;
		foreach ($country_r as $coun)
			$country_arr[$coun['id']] = substr($coun['code'], -2);
		while (1)
		{
			$page_url = "https://services.daisycon.com/publishers/".$this->info['APIKey1']."/programs?page=$page&per_page=$per_page";
			$re = $this->oLinkFeed->GetHttpResult($page_url, $request);
			$re = json_decode($re['content'], true);
			if (!$re)
				break;
			foreach ($re as $v)
			{
				$strMerID = $v['id'];
				if (!$strMerID)
					continue;
				//print_r($v);exit;
				//Partnership
				$Partnership = 'NoPartnership';
				$sub_url = "https://services.daisycon.com/publishers/".$this->info['APIKey1']."/programs/{$strMerID}/subscriptions";
				$sub_r = $this->oLinkFeed->GetHttpResult($sub_url, $request);
				$sub_r = json_decode($sub_r['content'], true);
				if (!$sub_r)
					continue;
				foreach ($sub_r as $M)
				{
					if ($M['status'] == 'approved' && $this->info['APIKey2'] == $M['media_id'])
					{
						$Partnership = 'Active';
						break;
					}
				}
				//AffDefaultUrl
				$AffDefaultUrl = str_replace('#MEDIA_ID#', $this->info['APIKey2'], trim($v['url']));
				$AffDefaultUrl = str_replace('&ws=#SUB_ID#', '', $AffDefaultUrl);
				if (substr($AffDefaultUrl, 0, 4) != 'http')
					$AffDefaultUrl = 'https:'.$AffDefaultUrl;
				
				$arr_prgm[$strMerID] = array(
						'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
						'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
						'IdInAff' => $strMerID,
						'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
						"AffDefaultUrl" => addslashes($AffDefaultUrl),
				);
				
				if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId']))
				{
					//StatusInAff
					$StatusInAffRemark = $v['status'];
					if ($StatusInAffRemark == 'active')
						$StatusInAff = 'Active';
					else 
						mydie("\r\n New status: $StatusInAffRemark");
					//Description
					$desc = @trim($v['descriptions'][0]['description']);
					//SupportDeepUrl
					if ($v['deeplink'] == 'true')
						$SupportDeepUrl = 'YES';
					else 
						$SupportDeepUrl = 'NO';
					//LogoUrl
					$LogoUrl = addslashes(trim($v['logo']));
					if (substr($LogoUrl, 0, 4) != 'http')
						$LogoUrl = 'https:'.$LogoUrl;
					//CommissionExt
					if ($v['commission']['min_ratio'] && $v['commission']['max_ratio'])
					{
						$CommissionExt = 'Percent:'.$v['commission']['min_ratio'].'%-'.$v['commission']['max_ratio'].'%';
						if ($v['commission']['min_fixed'] && $v['commission']['max_fixed'])
							$CommissionExt .= '|Amount:'.$v['commission']['min_fixed'].'€-'.$v['commission']['max_fixed'].'€';
					}else if ($v['commission']['min_fixed'] && $v['commission']['max_fixed'])
						$CommissionExt = 'Amount:'.$v['commission']['min_fixed'].'€-'.$v['commission']['max_fixed'].'€';
					$CommissionExt .= '|cpc:'.$v['commission']['min_cpc'].'€-'.$v['commission']['max_cpc'].'€';
					//Category
					$Category = array();
					foreach ($v['category_ids'] as $ca)
					{
						if (!isset($Category_arr[$ca]))
							mydie("die: new categoryID is $ca");
						else
							$Category[] = $Category_arr[$ca];
					}
					$CategoryExt = implode(EX_CATEGORY, $Category);
					//country
					$country = array();
					foreach ($v['supply_locale_ids'] as $co)
					{
						if (!isset($country_arr[$co]))
							mydie("die: new CountryID is $co");
						else
							$country[] = $country_arr[$co];
					}
					$TargetCountryExt = implode(',', $country);
					//SEM
					$SEMPolicyExt = '';
					$v['keywordmarketing'] == "true" ? $SEMPolicyExt = "is allowed" : $SEMPolicyExt = 'is NOT allowed';
					
					$arr_prgm[$strMerID] += array(
							'CrawlJobId' => $this->info['crawlJobId'],
							"Name" => addslashes($v['name']),
							"JoinDate" => parse_time_str($v['startdate']),
							"StatusInAffRemark" => $StatusInAffRemark,
							"StatusInAff" => $StatusInAff,                        //'Active','TempOffline','Offline'
							"Description" => addslashes($desc),
							"Homepage" => addslashes(trim($v['display_url'])),
							"CookieTime" => $v['tracking_duration'],
							"TargetCountryExt" => $TargetCountryExt,
							//"TermAndCondition" => addslashes($TermAndCondition),
							"SupportDeepUrl" => $SupportDeepUrl,
							"CommissionExt" => addslashes($CommissionExt),
							"CategoryExt" => addslashes($CategoryExt),
							"LogoUrl" => $LogoUrl,
							"SEMPolicyExt" => $SEMPolicyExt.' (Brandbidding is never allowed!)'
					);
					$base_program_num++;
				}
				$program_num++;
				if(count($arr_prgm) >= 100)
				{
					$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
					$arr_prgm = array();
				}
			}
			if(count($arr_prgm))
			{
				$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
				$arr_prgm = array();
			}
			$page++;
		}
		
		
		echo "\tGet Program by api end\r\n";
		if($program_num < 10){
			mydie("die: program count < 10, please check program.\n");
		}
		echo "\tUpdate ({$base_program_num}) base program.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
	}
	
}