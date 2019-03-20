<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/14
 * Time: 11:11
 */
class LinkFeed_2085_Dao_of_Leads
{
	function __construct($aff_id, $oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->isFull = true;
	}

	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
		$this->GetProgramFromByPage();
		echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
	}

	function GetProgramFromByPage()
	{
		echo "\tGet Program by page start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$program_num  = $base_program_num = 0;
		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info);
		$strUrl = "https://member.daoofleads.com/affiliates/Extjs.ashx?s=contracts";
		$hasNextPage = true;
		$page = 1;
		$arr_prgm = array();
		while ($hasNextPage) {
			echo "page $page\t";
			$postdata = array(
				'groupBy' => '',
				'groupDir' => 'ASC',
				'cu' => 1,
				'c' => '',
				'cat' => 0,
				'sv' => '',
				'cn' => '',
				'pf' => '',
				'st' => 0,
				'm' => '',
				'ct' => '',
				'pmin' => '',
				'pmax' => '',
				'mycurr' => true,
				't' => '',
				'p' => ($page - 1) * 100,
				'n' => 100,
			);

			$StatusInAff = 'Active';
			$request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => http_build_query($postdata));
            $cache_name = "program_list_page_{$page}_" . date('ymdh') . '.cache';
			$r = $this->oLinkFeed->GetHttpResultAndCache($strUrl,$request,$cache_name,'total');
			$res = json_decode($r,true);
			if(($res['total'] - ($page - 1) * 100) < 100)
				$hasNextPage = false;
			$result = $res['rows'];
			foreach($result as $item)
			{
				$IdInAff = $item['campaign_id'];
				if (!$IdInAff)
					continue;
				$strMerName = trim($item['name']);
				if (!$strMerName)
					continue;
				$p_status = trim($item['status']);
				if ('Active' == $p_status){
					$Partnership = 'Active';
					$contid = $item['contract_id'];
					$detailDefaulUrl = "https://member.daoofleads.com/affiliates/Extjs.ashx?s=creatives&cont_id=$contid";
					$request = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "get");
					$DU_cache_name = "defaultUrl_{$IdInAff}_" . date('ymdh') . '.cache';
					$detailDefaulUrlFull = $this->oLinkFeed->GetHttpResultAndCache($detailDefaulUrl,$request,$DU_cache_name,'unique_link');
					$detailDefaul = json_decode($detailDefaulUrlFull,true)['rows'];
					$AffDefaultUrl = $detailDefaul[0]['unique_link'];
				}elseif($p_status == 'Pending'){
					$Partnership = 'Pending';
				}elseif($p_status == 'Apply To Run' || $p_status == 'Inactive' || $p_status == 'Public'){
					$Partnership = 'NoPartnership';
					$AffDefaultUrl = '';
				}else{
					mydie ("die: unknown $strMerName partnership: $p_status.\n");
				}
				$country = '';
				if (!empty($item['countries']) && $item['countries'][0] != '-1'){
					foreach ($item['countries'] as $c){
						$country .= $c . ',';
					}
				}
				$country = rtrim($country, ',');

				$arr_prgm[$IdInAff] = array(
					"AccountSiteID" => $this->info["AccountSiteID"],      //attention there is ID not Id
					"BatchID" => $this->info['batchID'],                  //attention there is ID not Id
					"AffDefaultUrl" => addslashes($AffDefaultUrl),
					'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
					"IdInAff" => $IdInAff,
				);

				if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId']))
				{
					$CommissionExt = '';
					switch ($item['price_format_id']) {
						case 5 :
							$CommissionExt =addslashes(trim($item['price_converted'])."%");
							break;
						case 1 :
							$CommissionExt =addslashes("$".trim($item['price_converted']));
							break;
						default :
							mydie("There find new currency! id={$item['currency_id']}");
					}
					//if ('Active' == $p_status){

						$detailUrl = "https://member.daoofleads.com/affiliates/Extjs.ashx?s=contract_info&cont_id=$contid";
						$tmp_detail = $this->oLinkFeed->GetHttpResult($detailUrl,$request);
						$tmp_detail = json_decode($tmp_detail['content'],true);
					//}

					if (isset($tmp_detail['rows'][0]['preview_link'])) {
						if ($Partnership=='Active')
							$Homepage = trim($tmp_detail['rows'][0]['preview_link']);
						else
							$Homepage = '';
					}
					$arr_prgm[$IdInAff]['SupportDeepUrl']='UNKNOWN';
					if ($Homepage != '') {
						$arr_prgm[$IdInAff]['SupportDeepUrl'] = "YES";
					}
					
					//SEMPolicyExt
					$SEMPolicyExt = '';
					if (empty($item['media_types'])){
						$SEMPolicyExt = 'allowed';
					}else {
						foreach ($item['media_types'] as $type){
							if(stripos($type['name'], 'PPC') !== 0){
								$SEMPolicyExt = 'allowed';
								break;
							}
						}
					}
					
					$arr_prgm[$IdInAff] += array(
						"CommissionExt" => $CommissionExt,
						"Name" => $strMerName,
						'CrawlJobId' => $this->info['crawlJobId'],
						"Homepage" => addslashes($Homepage),
						"StatusInAff" => $StatusInAff,
						"TargetCountryExt" => addslashes($country),
						"CategoryExt" => addslashes($item['vertical_name']),
						"Description" => addslashes($item['description']),
						"SEMPolicyExt" => $SEMPolicyExt
					);
					$base_program_num ++;
				}

				$program_num++;
				if (count($arr_prgm) >= 100) {
					$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
					$arr_prgm = array();
				}
			}
			$page++;
			if($page > 10){
				mydie("die: Page overload.\n");
			}
		}
		if (count($arr_prgm)) {
			$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
			unset($arr_prgm);
		}
		echo "\n\tGet Program by page end\r\n";
		if ($program_num < 30) {
			mydie("die: program count < 30, please check program.\n");
		}
		echo "\tUpdate ({$base_program_num}) base programs.\r\n";
		echo "\tUpdate ({$program_num}) site programs.\r\n";
	}

}