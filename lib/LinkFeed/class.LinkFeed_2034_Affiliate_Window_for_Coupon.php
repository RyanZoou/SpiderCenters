<?php
require_once 'text_parse_helper.php';
class LinkFeed_2034_Affiliate_Window_for_Coupon
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
        $this->GetProgramByApi();
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = $arr_prgm_name = array();
        $program_num = $base_program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        //first step get partnership programs programId list
        echo "\r\nGet active Program start\r\n";
        $program_list_url = "https://api.awin.com/publishers/{$this->info['APIKey2']}/programmes?relationship=joined&accessToken={$this->info['APIKey4']}";
        $ptsp_pgrm_r = $this->oLinkFeed->GetHttpResult($program_list_url, $request);
        $ptsp_pgrm_r = json_decode($ptsp_pgrm_r['content'], true);
        $ptsp_pgrm_list = array();
        foreach ($ptsp_pgrm_r as $pv) {
            $ptsp_pgrm_list[$pv['id']] = '';

            $idInAff = intval($pv['id']);
            if (!$idInAff) {
                continue;
            }
            echo "$idInAff\t";
            $affDefaultUrl = trim($pv['clickThroughUrl']);

            $arr_prgm[$idInAff] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $idInAff,
                'Partnership' => 'Active',
                'AffDefaultUrl' => addslashes($affDefaultUrl)
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idInAff, $this->info['crawlJobId'])) {
                $name = trim($pv['name']);
                $homepage = trim($pv['displayUrl']);
                $targetCountry = trim($pv['primaryRegion']['countryCode']);

                //get commission info
                $commission = $commission_r = '';
                $commission_url = "https://api.awin.com/publishers/{$this->info['APIKey2']}/commissiongroups?advertiserId={$idInAff}&accessToken={$this->info['APIKey4']}";
                $retry = 3;
                while ($retry) {
                    $r = $this->oLinkFeed->GetHttpResult($commission_url, $request);
                    if (stripos($r['content'], 'commissionGroups') !== false) {
                        $commission_r = $r['content'];
                        break;
                    } else {
                        sleep('3');
                        $retry --;
                    }
                }

                if(!empty($commission_r)){
                    $commission_r = json_decode($commission_r, true);
                    if (!empty($commission_r['commissionGroups'])) {
                        foreach ($commission_r['commissionGroups'] as $cVal) {
                            if ($cVal['type'] == 'fix') {
                                $commission .= $cVal['currency'] . ' ' . $cVal['amount'] . '|';
                            } elseif ($cVal['type'] == 'percentage') {
                                $commission .= $cVal['percentage'] . '%|';
                            } else {
                                mydie("Find new currency type {$cVal['type']}.");
                            }
                        }
                        $commission = rtrim($commission, '|');
                    }
                }

                $arr_prgm[$idInAff] += array(
                    "CrawlJobId" => $this->info['crawlJobId'],
                    "Name" => addslashes(html_entity_decode($name)),
                    "Homepage" => addslashes($homepage),
                    "TargetCountryExt" => addslashes($targetCountry),
                    "StatusInAff" => 'Active',                        //'Active','TempOffline','Offline'
                    "CommissionExt" => addslashes($commission),
                );

                $arr_prgm[$idInAff] += $this->getProgramDetail($idInAff);
                $base_program_num++;
            }

            $program_num++;
            if (count($arr_prgm) >= 100) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }
        }
        if (count($arr_prgm)) {
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            $arr_prgm = array();
        }
        echo "\r\nGet active Program end\r\n";

        //step 2 get nopartnership program information
        echo "\r\nGet nopartnership Program start\r\n";
        $program_list_url = "https://api.awin.com/publishers/{$this->info['APIKey2']}/programmes?accessToken={$this->info['APIKey4']}";
        $pgrm_r = $this->oLinkFeed->GetHttpResult($program_list_url, $request);
        $pgrm_r = json_decode($pgrm_r['content'], true);

        foreach ($pgrm_r as $val) {
            $idInAff = intval($val['id']);
            if (!$idInAff || isset($ptsp_pgrm_list[$idInAff])) {
                continue;
            }
            echo "$idInAff\t";

            $affDefaultUrl = trim($val['clickThroughUrl']);
            $arr_prgm[$idInAff] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $idInAff,
                'Partnership' => 'NoPartnership',
                'AffDefaultUrl' => addslashes($affDefaultUrl)
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idInAff, $this->info['crawlJobId'])) {
                $name = trim($val['name']);
                $homepage = trim($val['displayUrl']);

                $targetCountry = trim($val['primaryRegion']['countryCode']);

                $arr_prgm[$idInAff] += array(
                    "CrawlJobId" => $this->info['crawlJobId'],
                    "Name" => addslashes(html_entity_decode($name)),
                    "Homepage" => addslashes($homepage),
                    "TargetCountryExt" => addslashes($targetCountry),
                    "StatusInAff" => 'Active',                        //'Active','TempOffline','Offline'
                );
                $arr_prgm[$idInAff] += $this->getProgramDetail($idInAff);

                $base_program_num++;
            }

            $program_num++;
            if (count($arr_prgm) >= 100) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }
        }

        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

    function getProgramDetail($programId)
    {
        if (!$programId){
            return false;
        }
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);

        $descPageUrl = "https://ui.awin.com/merchant-profile/$programId?setLocale=en_GB";
        $cacheName = "program_description_{$programId}_" . date('YmdH') . '.cache';
        $pgrm_r = $this->oLinkFeed->GetHttpResultAndCache($descPageUrl, $request, $cacheName);
        $logo = $this->oLinkFeed->ParseStringBy2Tag($pgrm_r, array('id="viewProfilePicture"', 'img src="'), '"');
        $desc = $this->oLinkFeed->ParseStringBy2Tag($pgrm_r, array('div id="descriptionLongContent"', '>'), '</div');
        preg_match_all('#href="mailto:([_a-zA-Z0-9-]+(?:\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*(?:\.[a-zA-Z]{2,}))"#', $pgrm_r, $m);
        $contactEmail = '';
        if (isset($m[1]) && !empty($m[1])) {
            $contactEmail = join(', ', $m[1]);
        }

        $term_url = "https://ui.awin.com/merchant-profile-terms/$programId";
        $cacheName = "program_term_{$programId}_" . date('YmdH') . '.cache';
        $term_r = $this->oLinkFeed->GetHttpResultAndCache($term_url, $request, $cacheName);
        $term = $this->oLinkFeed->ParseStringBy2Tag($term_r, array('div id="termsFreeTextContent"', '>'), '</div');

        $ppc_url = "https://ui.awin.com/merchant-profile-terms/$programId/affiliate";
        $cacheName = "program_ppc_{$programId}_" . date('YmdH') . '.cache';
        $ppc_r = $this->oLinkFeed->GetHttpResultAndCache($ppc_url, $request, $cacheName);
        $ppc = $this->oLinkFeed->ParseStringBy2Tag($ppc_r, '<table class="table table-striped table-hover">', '</table>');

        $return_arr = array(
            'LogoUrl' => addslashes($logo),
            'Description' => addslashes($desc),
            'TermAndCondition' => addslashes($term),
            'PublisherPolicy' => addslashes($ppc),
            'Contacts' => addslashes($contactEmail)
        );

        return $return_arr;
    }

	function getTransactionFromAff($start_date, $end_date)
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

		$objTransaction = New TransactionDb();
		$begin_dt = $start_date;
		$end_dt = $end_date;
		$arr_transaction = $arr_find_Repeated_transactionId = array();
		$tras_num = 0;

		$api_url = "https://api.awin.com/publishers/{$this->info['APIKey2']}/transactions/?startDate={BEGIN_DATE}T00%3A00%3A00&endDate={END_DATE}T23%3A59%3A59&timezone=America/Chicago&accessToken={$this->info['APIKey4']}";
		$request = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "get");

		$i = 0;
		while ($begin_dt < $end_dt) {
			$td = date('Y-m-d', strtotime('+1 days', strtotime($begin_dt)));
			$i ++;

			$url = str_replace(array('{BEGIN_DATE}', '{END_DATE}'), array($begin_dt, $begin_dt), $api_url);
			$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "data_" . date("YmdH") . "_Transaction_{$td}.csv", 'Transaction', true);
			if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
				echo "req => {$url} \n";
				$result = $this->oLinkFeed->GetHttpResult($url, $request);
				if ($result['code'] != 200){
					mydie("Download json file failed.");
				}
				$result = $result['content'];
				$this->oLinkFeed->fileCachePut($cache_file, $result);
			} else {
				$result = $this->oLinkFeed->fileCacheGet($cache_file);
			}
			$result = json_decode($result, true);

			foreach ($result as $v)
			{
				$TransactionId = trim($v['id']);
				if (!$TransactionId) {
					continue;
				}

				if (isset($arr_find_Repeated_transactionId[$TransactionId])) {
					mydie("The transactionId={$TransactionId} have early exists!");
				} else {
					$arr_find_Repeated_transactionId[$TransactionId] = '';
				}

				$arr_transaction[$TransactionId] = array(
					'AccountSiteID' => $this->info["AccountSiteID"],
					'BatchID' => $this->info['batchID'],
					'TransactionId' => $TransactionId,                      //must be unique
					'id' => addslashes($v['id']),
					'url' => addslashes($v['url']),
					'advertiserId' => addslashes($v['advertiserId']),
					'publisherId' => addslashes($v['publisherId']),
					'commissionSharingPublisherId' => addslashes($v['commissionSharingPublisherId']),
					'siteName' => addslashes($v['siteName']),
					'commissionStatus' => addslashes($v['commissionStatus']),
					'commissionAmount' => addslashes(json_encode($v['commissionAmount'])),
					'saleAmount' => addslashes(json_encode($v['saleAmount'])),
					'ipHash' => addslashes($v['ipHash']),
					'customerCountry' => addslashes($v['customerCountry']),
					'clickRefs' => addslashes(json_encode($v['clickRefs'])),
					'clickDate' => addslashes($v['clickDate']),
					'transactionDate' => addslashes($v['transactionDate']),
					'validationDate' => addslashes($v['validationDate']),
					'type' => addslashes($v['type']),
					'declineReason' => addslashes($v['declineReason']),
					'voucherCodeUsed' => addslashes($v['voucherCodeUsed']),
					'voucherCode' => addslashes($v['voucherCode']),
					'lapseTime' => addslashes($v['lapseTime']),
					'amended' => addslashes($v['amended']),
					'amendReason' => addslashes($v['amendReason']),
					'oldSaleAmount' => addslashes(json_encode($v['oldSaleAmount'])),
					'oldCommissionAmount' => addslashes(json_encode($v['oldCommissionAmount'])),
					'clickDevice' => addslashes($v['clickDevice']),
					'transactionDevice' => addslashes($v['transactionDevice']),
					'publisherUrl' => addslashes($v['publisherUrl']),
					'advertiserCountry' => addslashes($v['advertiserCountry']),
					'orderRef' => addslashes($v['orderRef']),
					'customParameters' => addslashes(json_encode($v['customParameters'])),
					'transactionParts' => addslashes(json_encode($v['transactionParts'])),
					'paidToPublisher' => addslashes($v['paidToPublisher']),
					'paymentId' => addslashes($v['paymentId']),
					'transactionQueryId' => addslashes($v['transactionQueryId']),
					'originalSaleAmount' => addslashes($v['originalSaleAmount'])
				);
				$tras_num ++;

				if (count($arr_transaction) >= 100) {
					$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
					$arr_transaction = array();
				}
			}

			$begin_dt = $td;
			if ($i > 500) {
				break;
			}
			sleep(2);
		}
		if (count($arr_transaction) > 0) {
			$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
			unset($arr_transaction);
		}
		unset($arr_find_Repeated_transactionId);

		echo "\tUpdate ({$tras_num}) Transactions.\r\n";
		echo "Craw Transaction end @ " . date("Y-m-d H:i:s") . "\r\n";

		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
	}

}
