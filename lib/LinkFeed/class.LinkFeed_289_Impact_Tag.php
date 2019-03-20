<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/5
 * Time: 15:09
 */
class LinkFeed_289_Impact_Tag
{
	function __construct($aff_id, $oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
	}

	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
		$this->GetProgramByPage();
		echo "Craw Program end @ ".date("Y-m-d H:i:s")."\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'],'Done');
	}

	function GetProgramByPage()
	{
		echo "\tGet Program by page start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$program_num = 0;
		$base_program_num = 0;

		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info);

		$request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);
		$strUrl = "http://partners.impacttag.net/programme/index/";
		$r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
		if ($r['code'] == 200){
			$content = $r['content'];
//			var_dump($content);exit("lalala");
		}else{
			var_dump($r);
			mydie("get partnership program error");
		}

//		$havaNextPro = true;
		$startP = 0;
		while (1){
			$program = '';
			$program = $this->oLinkFeed->ParseStringBy2Tag($content,"<div class=\"highlight\">","</div>",$startP);
			if ($program === false) break;

			$detailUrl = $this->oLinkFeed->ParseStringBy2Tag($program,'<a href="','">More Details');
			$r_arr = explode("/",$detailUrl);
			$strMerID = $r_arr[count($r_arr)-1];
			if (!$strMerID) mydie('get idinaff error');
			echo $strMerID."\t";

			if (strpos($program,"<strong>Pending</strong>") !== false){
				$Partnership = "Pending";
			}else{
				$Partnership = "Active";
			}

			//affdefaulturl
			$affDefaultUrl = '';
			if ($Partnership == "Active") {
				$detailUrl = "http://partners.impacttag.net" . $detailUrl;
				$detailRes = $this->oLinkFeed->GetHttpResult($detailUrl, $request);
				if ($detailRes['code'] != 200) {
					var_dump($detailRes);
					mydie("get program detail error");
				}
				$trakUrl = $this->oLinkFeed->ParseStringBy2Tag($detailRes['content'], '<a href="/Programme/TrackingLink/', '">View Links</a>');
				$trakUrl = "http://partners.impacttag.net/Programme/TrackingLink/" . $trakUrl;
				$trakRes = $this->oLinkFeed->GetHttpResult($trakUrl, $request);
				if ($trakRes['code'] != 200) {
					var_dump($trakRes);
					mydie("get program detail error");
				}
				$listingrow = $this->oLinkFeed->ParseStringBy2Tag($trakRes['content'],'<tr class="listingrow">','</tr>');
				$affDefaultUrl = trim(strip_tags(explode('</td>',$listingrow)[1]));
			}

			$arr_prgm[$strMerID] = array(
				'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
				'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
				'IdInAff' => $strMerID,
				'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
				"AffDefaultUrl" => addslashes($affDefaultUrl),
			);

			if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
				$strMerName = trim($this->oLinkFeed->ParseStringBy2Tag($program, '<h3>', '</h3>'));
				$labelP = strpos($program, "</label>");
				$p = trim($this->oLinkFeed->ParseStringBy2Tag($program, '<p>', '</td>', $labelP));
				$p = str_replace('</p>', '', $p);
				$br_arr = explode("<br/>", $p);
				$desc = $br_arr[0];
				$commission = '';
				foreach ($br_arr as $br) {
					if (empty($br)) continue;
					if (stripos($br, "%") || stripos($br, "$") || stripos($br, "£")) {
						$commission .= $br."\n";
					}
				}
				$Homepage = trim($this->oLinkFeed->ParseStringBy2Tag($program,'<a target="_blank" href="','"'));

				$arr_prgm[$strMerID] += array(
					"CrawlJobId" => $this->info['crawlJobId'],
					"Name" => addslashes(html_entity_decode($strMerName)),
					"StatusInAff" => 'Active',                        //'Active','TempOffline','Offline'
					"TargetCountryExt" => 'Global',
					"Description" => addslashes($desc),
					"Homepage" => addslashes($Homepage),
					"CommissionExt" => addslashes(trim($commission)),
				);
				$base_program_num++;
			}
			$program_num++;

			if (count($arr_prgm) >= 100) {
				$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
				$arr_prgm = array();
			}
		}
		if (count($arr_prgm)) {
			$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
			unset($arr_prgm);
		}
		echo "\tGet Program by page end\r\n";

		if($program_num < 10){
			mydie("die: program count < 10, please check program.\n");
		}

		echo "\tUpdate ({$base_program_num}) base programs.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
	}
}