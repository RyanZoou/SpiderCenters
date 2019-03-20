<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';

class LinkFeed_64_Effiliation
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
		$this->GetProgramByPage();
		echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
	}

	function GetProgramByPage()
	{
		echo "\tGet Program by page start\r\n";
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get");
		$objProgram = new ProgramDb();
		$arr_prgm = $supportUrl_arr = array();
		$program_num = $base_program_num = 0;

		if ($this->isFull) {
            $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);
            $result = $this->oLinkFeed->GetHttpResult('https://publisher.effiliation.com/affiliev2/secure/factory.html?tab=deeplink',$request);
            $result = $this->oLinkFeed->ParseStringBy2Tag($result['content'], array('input-with-feedback no-right-padding not-zero', 'input-with-feedback no-right-padding not-zero'), 'input-with-feedback no-right-padding not-zero');
            preg_match_all('#value=\"(\d*)\"#', $result,$matches);
            unset($matches[1][0]);
            foreach($matches[1] as $v){
                $requestLoad = $request;
                $requestLoad['method'] = 'post';
                $requestLoad['postdata'] = 'id_program='.$v;
                $loadArr = $this->oLinkFeed->GetHttpResult('https://publisher.effiliation.com/affiliev2/secure/ajaxloaddeeplinks.html',$requestLoad);
                preg_match_all('#value=\"([1-9]\d*)\"#', $loadArr['content'],$matches);
                $deeplink = $matches[1][0];
                $requestGetDeeplink = $request;
                $requestGetDeeplink['method'] = 'post';
                $requestGetDeeplink['postdata'] = 'program='.$v.'&deeplink='.$deeplink.'&url=&code=&urlencoded=1';
                $supportUrl_arr[$v] = $this->oLinkFeed->GetHttpResult('https://publisher.effiliation.com/affiliev2/secure/ajaxgetcodedeeplink.html',$requestGetDeeplink)['content'];
            }
        }

		$status = array('active', 'inactive', 'pending', 'unregistered', 'closed', 'refused', 'recommendation');
		foreach ($status as $v) {
			$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], $v . ".dat", "cache_merchant");
			if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
				$request["method"] = "get";
				$strUrlAllMerchant = "http://apiv2.effiliation.com/apiv2/programs.json?key={$this->info['APIKey1']}&filter=" . $v . "&lg=en";
				$r = $this->oLinkFeed->GetHttpResult($strUrlAllMerchant, $request);
				$result = $r["content"];
				$this->oLinkFeed->fileCachePut($cache_file, $result);
			}
			if (!file_exists($cache_file)) mydie("die: merchant csv file does not exist. \n");

			$apiResult = json_decode(file_get_contents($cache_file), true);
			if (!isset($apiResult['programs'])) {
				print_r($apiResult);
				mydie("Call api function failed!");
			}

			foreach ($apiResult['programs'] as $row) {
				$strMerID = intval($row['id_programme']);
				if (!$strMerID) {
					continue;
				}

				$AffDefaultUrl = $this->oLinkFeed->ParseStringBy2Tag($row['url_tracke'], 'href="', '"');
				$arr_prgm[$strMerID] = array(
					'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
					'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
					'IdInAff' => $strMerID,
					'Partnership' => addslashes($row['etat'] . '~' . $v),
					'AffDefaultUrl' => addslashes($AffDefaultUrl)
				);
				$program_num++;

				if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
					if (isset($row['mobile']) && isset($row['applimobile']) && $row['mobile'] == 'No' && $row['applimobile'] == 'No') {
						$MobileFriendly = 'No';
					} else {
						$MobileFriendly = 'Yes';
					}
					$row['categories'] = str_replace(',', EX_CATEGORY, $row['categories']);

					$arr_prgm[$strMerID] += array(
						'CrawlJobId' => $this->info['crawlJobId'],
						'Name' => addslashes($row['nom']),
						'TargetCountryExt' => addslashes($row['pays']),
						'EPCDefault' => @addslashes($row['epc']),
						'CategoryExt' => addslashes($row['categories']),
						'Homepage' => addslashes($row['url']),
						'CommissionExt' => addslashes($row['remuneration']),
						'Description' => @addslashes($row['description']),
						'CookieTime' => addslashes($row['dureecookies']),
						'Contacts' => @addslashes($row['responsable']),
						'SecondIdInAff' => addslashes($row['id_affilieur']),
						'MobileFriendly' => $MobileFriendly,
						'SupportCouponSite'=> addslashes($row['bonsdereduc']),
                        'SupportDeepUrl' => isset($supportUrl_arr[$strMerID]) ? 'YES' : 'NO',
                        'DeepUrlTpl' => @addslashes($supportUrl_arr[$strMerID])
					);

					$base_program_num++;
				}

				if (count($arr_prgm) >= 100) {
					$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
					$arr_prgm = array();
				}
			}

			if (count($arr_prgm)) {
				$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
				$arr_prgm = array();
			}
		}
		echo "\tGet Program by page end\r\n";
		if ($program_num < 10) {
			mydie("die: program count < 10, please check program.\n");
		}

		echo "\tUpdate ({$base_program_num}) base programs." . PHP_EOL;
		echo "\tUpdate ({$program_num}) program.\r\n";

	}


}
