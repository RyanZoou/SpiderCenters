<?php

/**
 * User: rzou
 * Date: 2017/7/24
 * Time: 17:26
 */
class LinkFeed_2033_Payoom
{
	function __construct($aff_id, $oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->islogined = false;
	}
	
	function login($try = 6)
	{
		if ($this->islogined) {
			echo "verify succ: " . $this->info["LoginVerifyString"] . "\n";
			return true;
		}
		
		$this->oLinkFeed->clearHttpInfos($this->info['AccountSiteID']);//删除缓存文件，删除httpinfos[$aff_id]变量
		$request = array(
			"AccountSiteID" => $this->info["AccountSiteID"],
			"method" => 'get'
		);
		$r = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
		$token = $this->oLinkFeed->ParseStringBy2Tag($r['content'], 'input type="hidden" name="_token" value="', '"');
		$this->info['LoginPostString'] = urldecode('_token=' . $token . '&') . $this->info['LoginPostString'];
		$this->info["referer"] = true;
		$request = array(
			"AccountSiteID" => $this->info["AccountSiteID"],
			"method" => $this->info["LoginMethod"],
			"postdata" => $this->info["LoginPostString"],
			"no_ssl_verifyhost" => true,
			"header" => 1,
		);
		
		$arr = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
		if ($arr["code"] == 0) {
			if (preg_match("/^SSL: certificate subject name .*? does not match target host name/i", $arr["error_msg"])) {
				$request["no_ssl_verifyhost"] = 1;
				$arr = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
			}
		}
		
		if ($arr["code"] == 200) {
			if (stripos($arr["content"], $this->info["LoginVerifyString"]) !== false) {
				echo "verify succ: " . $this->info["LoginVerifyString"] . "\n";
				$this->islogined = true;
				return true;
			}
		}
		
		if (!$this->islogined) {
			if ($try < 0) {
				mydie("Failed to login!");
			} else {
				echo "login failed ... retry $try...\n";
				sleep(30);
				$this->login(--$try);
			}
		}
	}
	
	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
		$this->GetProgramByApi();
		echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
	}
	
	function GetProgramByApi()
	{
		echo "\tGet Program by api start\r\n";
		$this->login();
		
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$program_num = 0;
		$base_program_num = 0;
		$request = array(
			"AccountSiteID" => $this->info["AccountSiteID"],
			"method" => 'get'
		);
		
		$hasNextPage = true;
		$page = 1;
		
		while ($hasNextPage) {
			$strUrl = "https://{$this->info['APIKey2']}.yeahpixel.com/api/v1/?api_token={$this->info['APIKey1']}&method=getOffers&limit=100&page=$page";
			
			$re_try = 1;
			while ($re_try) {
				$r = $this->oLinkFeed->GetHttpResult($strUrl);
				$apiResponse = @json_decode($r['content'], true);
				
				if (isset($apiResponse['data']) && !empty($apiResponse['data'])) {
					break;
				}
				if ($re_try > 3) {
					mydie("Api is empty!");
				}
				$re_try++;
			}
			
			$total = $apiResponse['total'];
			if ($total < $page * 100) {
				$hasNextPage = false;
			}
			
			foreach ($apiResponse['data'] as $prgm_info) {
				$IdInAff = $prgm_info['offer_id'];
				if (!$IdInAff)
					continue;
				
				//get partnership
				if ($prgm_info['offer_require_approval'] == 'no') {
					$Partnership = 'Active';
				} elseif ($prgm_info['offer_require_approval'] == 'yes') {
					$Partnership = 'NoPartnership';
				} else {
					mydie('Find new status of partnership :' . $prgm_info['offer_require_approval']);
				}
				$AffDefaultUrl = "https://eploop.go2pixel.org/tracking/track/?offer_id=$IdInAff&aff_id={$this->info['APIKey3']}";
				
				$arr_prgm[$IdInAff] = array(
						'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
						'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
						'IdInAff' => $IdInAff,
						'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
						"AffDefaultUrl" => addslashes($AffDefaultUrl),
				);
				
				if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId']))
				{
				
					$SupportDeepUrl = 'UNKNOWN';
					$sup_deep = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_info['offer_description'], array('Deep-linking', '>'), '<'));
					if (strpos($sup_deep, 'Available') !== false) {
						$SupportDeepUrl = 'YES';
					}elseif(strpos($sup_deep, 'NotAvailable') !== false) {
						$SupportDeepUrl = 'NO';
					}
					
					$TermAndCondition = '';
					if (isset($prgm_info['offer_description']) && !empty($prgm_info['offer_description'])) {
						$TermAndCondition = addslashes(strip_tags(html_entity_decode($prgm_info['offer_description'])));
					}
				
					$DetailPage = "http://platform.postback.in/affiliate/offers/$IdInAff";
					$r = $this->oLinkFeed->GetHttpResult($DetailPage,$request);
					
					$strPosition = 0;
					$LogoUrl = $this->oLinkFeed->ParseStringBy2Tag($r['content'], array('class="user-bg text-center', 'src="'), '"', $strPosition);
					$CommissionExt = $this->oLinkFeed->ParseStringBy2Tag($r['content'], array('<strong>Payout</strong>', '<p>'), '</p>', $strPosition);
					$CommissionExt = preg_replace("/\s*/",'',$CommissionExt);
					$CommissionExt = str_replace(array('\r\n','\r','\n'),'',$CommissionExt);
					$TargetCountryExt = $this->oLinkFeed->ParseStringBy2Tag($r['content'], array('<strong>Countries</strong>', '<p>'), '</p>', $strPosition);
					
					//SEMPolicyExt
					$SEMPolicyExt = '';
					if (strpos($r['content'], '<strong>SEM')){
						$start = 0;
						for ($i=0;$i<3;$i++){
							$value = $this->oLinkFeed->ParseStringBy2Tag($r['content'], '<strong>SEM' , '</p>', $start);
							if (empty($value)){
								break;
							}else {
								$SEMPolicyExt .= "\nSEM".$value;
							}
						}
					}
					empty($SEMPolicyExt) ? $SEMPolicyExt='UNKNOW' : $SEMPolicyExt = trim($SEMPolicyExt);
					
					
					$arr_prgm[$IdInAff] += array(
							'CrawlJobId' => $this->info['crawlJobId'],
							"Name" => addslashes((trim($prgm_info['offer_name']))),
							"Homepage" => addslashes($prgm_info['offer_preview_url']),
							"StatusInAff" => 'Active',                        //'Active','TempOffline','Offline'
							"CommissionExt" => addslashes(trim($CommissionExt)),
							"TermAndCondition" => $TermAndCondition,
							'TargetCountryExt' => addslashes(trim($TargetCountryExt)),
							'CategoryExt' => addslashes(trim(str_replace(',', EX_CATEGORY, $prgm_info['offer_categories']))),
							'LogoUrl' => addslashes($LogoUrl),
							"SupportDeepUrl" => $SupportDeepUrl,
							"SEMPolicyExt" => addslashes($SEMPolicyExt)
					);
					$base_program_num ++;
				}
				$program_num++;
				if (count($arr_prgm) >= 100) {
					$objProgram->InsertProgramBatch($this->info['NetworkID'], $arr_prgm);
					$arr_prgm = array();
				}
			}
			$page++;
		}
		
		if (count($arr_prgm)) {
			$objProgram->InsertProgramBatch($this->info['NetworkID'],$arr_prgm);
			unset($arr_prgm);
		}
		echo "\tGet Program by api end\r\n";
		
		if ($program_num < 10) {
			mydie("die: program count < 10, please check program.\n");
		}
		echo "\tUpdate ({$base_program_num}) base program.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
		
	}
	
}