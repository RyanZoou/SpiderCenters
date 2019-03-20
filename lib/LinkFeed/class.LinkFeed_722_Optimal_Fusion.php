<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_722_Optimal_Fusion
{
	function __construct($aff_id, $oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->isFull = true;
		$this->DataSource = 434;
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
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$program_num = $base_program_num = 0;
		
		//step 1,login
		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);
		
		$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "get",
				"postdata" => ''
		);
		$offerDeed_url = "http://login.optimalfusion.com/affiliates/api/4/offers.asmx/OfferFeed?api_key={$this->info['APIKey2']}&affiliate_id={$this->info['APIKey1']}&campaign_name=&media_type_category_id=0&vertical_category_id=0&vertical_id=0&offer_status_id=0&tag_id=0&start_at_row=1&row_limit=0";
		
		$r = $this->oLinkFeed->GetHttpResult($offerDeed_url, $request);
		$xml = simplexml_load_string($r['content']);
		//var_dump($xml);exit;
		foreach ($xml->offers->offer as $v)
		{
			$strMerID = $v->offer_id . '_' . $v->offer_contract_id;
			if(!$strMerID) 
				continue;
			$Homepage = '';
			$Partnership = '';
			$AffDefaultUrl = '';
			$SupportDeepUrl = 'UNKNOWN';
			if ($v->hidden == 'false')
			{
				$StatusInAffRemark = $v->offer_status->status_name;
				$StatusInAff = 'Active';
				if($StatusInAffRemark == 'Apply To Run')
					$Partnership = 'NoPartnership';
				elseif($StatusInAffRemark == 'Public')
					$Partnership = 'NoPartnership';
				elseif($StatusInAffRemark == 'Pending')
					$Partnership = 'Pending';
				elseif($StatusInAffRemark == 'Active'){
					$Partnership = 'Active';
					$detailDefaulUrl = "http://login.optimalfusion.com/affiliates/Extjs.ashx?s=creatives&cont_id={$v->campaign_id}";
					$detailDefaulUrlFull = $this->oLinkFeed->GetHttpResult($detailDefaulUrl,$request);
					$detailDefaul = json_decode($detailDefaulUrlFull['content'],true)['rows'];
					foreach ($detailDefaul as $de)
					{
						if($de['type'] == 'Link' && $de['show_destination_url'] == true){
							$SupportDeepUrl = 'YES';
							$AffDefaultUrl = $de['unique_link'];
							break;
						}
					}
					if($SupportDeepUrl == 'UNKNOWN')
					{
						$SupportDeepUrl = 'NO';
						$AffDefaultUrl = $detailDefaul[0]['unique_link'];
					}
					if (!empty($AffDefaultUrl))
					{
						$OriginalUrl = $this->oLinkFeed->findFinalUrl($AffDefaultUrl);
						$ParseUrl = parse_url($OriginalUrl);
						$Homepage = $ParseUrl['scheme'] .'://'. $ParseUrl['host'];
					}
					//var_dump($detailDefaul);exit;
				}else
					mydie("die: there is a new StatusInAffRemark, $StatusInAffRemark, please add it");
			}else{
				$StatusInAffRemark = 'Hidden';
				$StatusInAff = 'TempOffline';
				$Partnership = 'NoPartnership';
			}
			
			$arr_prgm[$strMerID] = array(
					'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
					'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
					'IdInAff' => $strMerID,
					'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
					"AffDefaultUrl" => addslashes($AffDefaultUrl),
			);
			
			if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {

				$arr_prgm[$strMerID] += array(
						'CrawlJobId' => $this->info['crawlJobId'],
						"Name" => addslashes($v->offer_name),
						"CategoryExt" => addslashes($v->vertical_name),
						"Description" => addslashes($v->description),
						"TargetCountryExt" => '',
						"StatusInAffRemark" => addslashes($StatusInAffRemark),
						"StatusInAff" => $StatusInAff,							//'Active','TempOffline','Offline'
						"CommissionExt" => addslashes($v->payout),
						"TermAndCondition" => addslashes($v->restrictions),
						"SupportDeepUrl" => $SupportDeepUrl
				);
				if (isset($v->allowed_countries))
				{
					$allowed_countries = json_decode(json_encode($v->allowed_countries), true);
					if (isset($allowed_countries['country']['country_code']))
						$arr_prgm[$strMerID]['TargetCountryExt'] = addslashes($allowed_countries['country']['country_code']);
					else if (isset($allowed_countries['country']))
					{
						foreach ($allowed_countries['country'] as $c)
							$country_arr[] = $c['country_code'];
						$arr_prgm[$strMerID]['TargetCountryExt'] = implode(',', $country_arr);
					}
				}else 
				{
					preg_match('/(?i)(.*)only/', $v->restrictions, $m);
					if (isset($m[1]))
						$arr_prgm[$strMerID]['TargetCountryExt'] = addslashes(trim($m[1]));
						
				}
				$base_program_num++;
				if(count($arr_prgm) >= 100){
					$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
					$arr_prgm = array();
				}
			}
			$program_num++;
		}
		if(count($arr_prgm)){
			$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
			unset($arr_prgm);
		}
		echo "\tGet Program by api end\r\n";
		if($program_num < 1){
			mydie("die: program count < 10, please check program.\n");
		}
		echo "\tUpdate ({$base_program_num}) base programs.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";	
		
	}
}