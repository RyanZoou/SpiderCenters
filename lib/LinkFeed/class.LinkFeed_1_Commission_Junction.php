<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_1_Commission_Junction
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
        $arr_prgm = array();
        $program_num = 0;
        $base_program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "addheader" => array("authorization: {$this->info['APIKey1']}"),);
        $request_more = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        //step 1,login
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info);

        $link = "https://advertiser-lookup.api.cj.com/v3/advertiser-lookup?";
        $order_arr = array("joined", "notjoined");

        //status only
        echo "\tget Support Deep Url\r\n";
        $arr_deepdomain = $this->getSupportDeepUrl();
        $xml2arr = new XML2Array();

        foreach ($order_arr as $vvv) {
            $crawl_base_idinaff_list = array();
            list($nPageNo, $nNumPerPage, $bHasNextPage, $nPageTotal) = array(1, 100, true, 1);
            while ($bHasNextPage) {
                echo "page$nPageNo\t";

                $param = array(
                    "advertiser-ids=$vvv",    //CIDs,joined,notjoined
                    "advertiser-name=",
                    "keywords=",
                    "page-number={$nPageNo}",
                    "records-per-page={$nNumPerPage}",
                );
                $postdata = implode("&", $param);
                $strUrl = $link . $postdata;
                $cacheName = $vvv.'_program_list_page_' . $nPageNo . '_' . date('YmdH') . '.cache';
                $result = $this->oLinkFeed->GetHttpResultAndCache($strUrl, $request, $cacheName, 'cj-api');
                if (empty($result))
                    continue;

                $re = $xml2arr->createArray($result);

                $re = $re['cj-api']['advertisers'];
                $total_matched = $re['@attributes']["total-matched"];
                $records_returned = $re['@attributes']["records-returned"];
                $page_number = $re['@attributes']["page-number"];

                $nPageTotal = ceil($total_matched / $nNumPerPage);
                $bHasNextPage = $page_number;
                if ($nPageNo >= $nPageTotal || $records_returned < 100) {
                    $bHasNextPage = false;
                } else {
                    $nPageNo++;
                }

                foreach ($re['advertiser'] as $advertiser) {
                    $IdInAff = trim($advertiser["advertiser-id"]);
                    $Name = trim($advertiser["advertiser-name"]);
                    if (empty($IdInAff) || empty($Name)) {
                        continue;
                    }

                    $Partnership = $advertiser["relationship-status"];
                    $arr_prgm[$IdInAff] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                        'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                        'IdInAff' => $IdInAff,
                        'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                    );

                    if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
                        $crawl_base_idinaff_list[] = $IdInAff;
                        $StatusInAff = $advertiser["account-status"];
                        $CategoryExt = $advertiser['primary-category']['parent'] . '-' . $advertiser['primary-category']['child'];
                        if (!$advertiser['primary-category']['parent']) {
                            $CategoryExt = $advertiser['primary-category']['child'];
                        }
                        $EPCDefault = $advertiser["seven-day-epc"];
                        $EPC90d = $advertiser["three-month-epc"];
                        $Homepage = strtolower($advertiser["program-url"]);
                        $RankInAff = intval($advertiser["network-rank"]);
                        $Commission = array();

                        $c = $advertiser['actions'];
                        if (isset($c['action']['name'])) {
                            //action 只有一个
                            if (isset($c['action']['commission']['itemlist'])) {
                                if (is_array($c['action']['commission']['default'])) {
                                    $Commission[] = $c['action']['name'] . ":" . $c['action']['commission']['default']['@attributes']['type'] . ":" . $c['action']['commission']['default']['@value'];

                                } else {
                                    $Commission[] = $c['action']['name'] . ":" . $c['action']['type'] . ":" . $c['action']['commission']['default'];
                                }
                                if (isset($c['action']['commission']['itemlist'][0])) {
                                    foreach ($c['action']['commission']['itemlist'] as $item) {
                                        $Commission[] = $item["@attributes"]['name'] . ":sub:" . $item["@value"];
                                    }
                                } else {
                                    $Commission[] = $c['action']['commission']['itemlist']['@attributes']['name'] . ":" . $c['action']['type'] . ":" . $c['action']['commission']['itemlist']['@value'];
                                }
                            } else {
                                if (is_array($c['action']['commission']['default'])) {
                                    $Commission[] = $c['action']['name'] . ":" . $c['action']['commission']['default']['@attributes']['type'] . ":" . $c['action']['commission']['default']['@value'];
                                } else
                                    $Commission[] = $c['action']['name'] . ":" . $c['action']['type'] . ":" . $c['action']['commission']['default'];
                            }
                        } elseif (isset($c['action'][0])) {
                            //action有多个
                            foreach ($c['action'] as $v) {
                                if (isset($v['commission']['itemlist'])) {
                                    if (is_array($v['commission']['default'])) {
                                        $Commission[] = $v['name'] . ":" . $v['commission']['default']['@attributes']['type'] . ":" . $v['commission']['default']['@value'];
                                    } else {
                                        $Commission[] = $v['name'] . ":" . $v['type'] . ":" . $v['commission']['default'];
                                    }
                                    if (isset($v['commission']['itemlist'][0])) {
                                        foreach ($v['commission']['itemlist'] as $item) {
                                            $Commission[] = $item["@attributes"]['name'] . ":sub:" . $item["@value"];
                                        }
                                    } else {
                                        $Commission[] = $v['commission']['itemlist']['@attributes']['name'] . ":" . $v['type'] . ":" . $v['commission']['itemlist']['@value'];
                                    }
                                } else {
                                    if (is_array($v['commission']['default'])) {
                                        $Commission[] = $v['name'] . ":" . $v['commission']['default']['@attributes']['type'] . ":" . $v['commission']['default']['@value'];
                                    } else
                                        $Commission[] = $v['name'] . ":" . $v['type'] . ":" . $v['commission']['default'];
                                }
                            }
                        }
                        $CommissionExt = implode('|', $Commission);

                        $arr_prgm[$IdInAff] += array(
                            'CrawlJobId' => $this->info['crawlJobId'],
                            'Name' => addslashes(trim($Name)),
                            'RankInAff' => $RankInAff,
                            'StatusInAff' => $StatusInAff,
                            'Homepage' => addslashes($Homepage),
                            'EPCDefault' => addslashes(trim(preg_replace("/[^0-9.-]/", "", $EPCDefault))),
                            'EPC90d' => addslashes(trim(preg_replace("/[^0-9.-]/", "", $EPC90d))),
                            'MobileFriendly' => 'UNKNOWN',
                            'SupportDeepUrl' => 'UNKNOWN',
                            'CommissionExt' => addslashes($CommissionExt),
                            'CategoryExt' => addslashes($CategoryExt),
                            'Description' => '',
                            'Contacts' => '',
                            'TermAndCondition' => '',
                            'TargetCountryExt' => '',
                            'PoliciesList' => '',
                        );

                        if ($advertiser['mobile-tracking-certified'] == 'true') {
                            $arr_prgm[$IdInAff]['MobileFriendly'] = 'YES';
                        }else if ($advertiser['mobile-tracking-certified'] == 'false') {
                            $arr_prgm[$IdInAff]['MobileFriendly'] = 'NO';
                        }

                        if (count($arr_deepdomain)) {
                            $Homepage = preg_replace("/^https?:\\/\\/(.*?)\\/?/i", "\$1", $Homepage);

                            if (isset($arr_deepdomain[$Homepage])) {
                                $arr_prgm[$IdInAff]['SupportDeepUrl'] = "YES";
                            } else {
                                $Homepage = preg_replace("/^ww.{0,2}\./i", "", $Homepage);
                                if (isset($arr_deepdomain[$Homepage])) {
                                    $arr_prgm[$IdInAff]['SupportDeepUrl'] = "YES";
                                }
                            }
                        }

                        /*
                         * detail
                         */
                        $prgm_url = "https://members.cj.com/member/advertiser/$IdInAff/detail.json";
                        $cacheName = 'program_detail_' . $IdInAff . '_' . date('YmdH') . '.cache';
                        $detailPage = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request_more, $cacheName);
                        if ($detailPage) {
                            $cache_file = json_decode($detailPage);
                            $desc = $cache_file->advertiser->description;
                            $arr_prgm[$IdInAff]['Description'] = addslashes($desc);
                        }

                        /*
                         * contact
                         */
                        $prgm_url = "https://members.cj.com/member/advertiser/$IdInAff/contact/" . $this->info['APIKey3'] . ".json";
                        $cacheName = 'program_contact_' . $IdInAff . '_' . date('YmdH') . '.cache';
                        $contactPage = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request_more, $cacheName);
                        if ($contactPage) {
                            $cache_file = json_decode($contactPage);
                            $contact = "Contact: " . $cache_file->contact->name . "; Email: " . $cache_file->contact->email;
                            $arr_prgm[$IdInAff]['Contacts'] = addslashes($contact);
                        }

                        /*
                         * terms
                         */
                        $prgm_url = "https://members.cj.com/member/publisher/" . $this->info['APIKey3'] . "/advertiser/$IdInAff/activeProgramTerms.json";
                        $cacheName = 'program_terms_' . $IdInAff . '_' . date('YmdH') . '.cache';
                        $termsPage = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request_more, $cacheName);
                        if ($termsPage) {
                            $cache_file = json_decode($termsPage);
                            $TermAndCondition = '';
                            if (isset($cache_file->activeProgramTerms->policies->policiesList)) {
                                $arr_prgm[$IdInAff]['PoliciesList'] = addslashes(json_encode($cache_file->activeProgramTerms->policies->policiesList));
                                foreach ($cache_file->activeProgramTerms->policies->policiesList as $tmp_policy) {
                                    $TermAndCondition .= '<b>' . $tmp_policy->policyTitle . '</b><br />&nbsp;&nbsp;&nbsp;&nbsp;' . $tmp_policy->policyText . '<br /><br />';
                                }
                                $arr_prgm[$IdInAff]['TermAndCondition'] = addslashes($TermAndCondition);
                            }
                        }

                        /*
                         * TargetCountryExt
                         */
                        if (count($arr_prgm) >= 100) {
                            $this->getTargetCountryByIdList($arr_prgm, $crawl_base_idinaff_list);
                            $crawl_base_idinaff_list = array();
                        }
                        $base_program_num ++;
                    }
                    $program_num++;

                    if (count($arr_prgm) >= 100) {
                        $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                        $arr_prgm = array();
                    }
                }
            }

            if (count($arr_prgm)) {
                $this->getTargetCountryByIdList($arr_prgm, $crawl_base_idinaff_list);
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                unset($arr_prgm);
            }
        }

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

    function getSupportDeepUrl()
    {
        //http://[CJDOMAINS]/links/[SITEIDINAFF]/type/dlg/sid/[SUBTRACKING]/[PURE_DEEPURL]
        $domains_arr = array();
        $url = "http://www.yceml.net/am_gen/".$this->info['APIKey2']."/include/allCj/am.js";
        $tmp_arr = $this->oLinkFeed->GetHttpResult($url, array("method" => "get"));
        if ($tmp_arr["code"] == 200) {
            $domains = trim($this->oLinkFeed->ParseStringBy2Tag($tmp_arr["content"], 'domains=[', ']'));
            $domains_arr = array_flip(explode("','", trim($domains, "'")));
        }
        return $domains_arr;
    }

    function getTargetCountryByIdList(&$arr_prgm, $crawl_base_idinaff_list)
    {
        if (empty($crawl_base_idinaff_list)) {
            return true;
        }
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);
        $id_list = implode(",", $crawl_base_idinaff_list);
        $prgm_url = "https://members.cj.com/member/publisher/" . $this->info['APIKey3'] . "/advertiserSearch.json?pageNumber=1&publisherId=" . $this->info['APIKey3'] . "&pageSize=100&advertiserIds=$id_list&geographicSource=&sortColumn=advertiserName&sortDescending=false";
        $return_arr = $this->oLinkFeed->GetHttpResult($prgm_url, $request);
        $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "country_" . date('Ymd'), 'TargetCountry', true);
        $cacheStr = $this->oLinkFeed->fileCacheGet($cache_file);
        if ($return_arr["code"] == 200) {
            $cacheStr += $return_arr["content"];
            $prgm_json = json_decode($return_arr["content"]);
            foreach ($prgm_json->advertisers as $v_j) {
                if (isset($arr_prgm[$v_j->advertiserId]) && is_array($v_j->serviceableAreas) && count($v_j->serviceableAreas)) {
                    $arr_prgm[$v_j->advertiserId]["TargetCountryExt"] = addslashes(implode(",", $v_j->serviceableAreas));
                }
            }
        }
        $this->oLinkFeed->fileCachePut($cache_file, $cacheStr);
        return true;
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

		$api_url = 'https://commission-detail.api.cj.com/v3/commissions?date-type=event&start-date={BEGIN_DATE}&end-date={END_DATE}';
		$request = array(
			"AccountSiteID" => $this->info["AccountSiteID"],
			"method" => "get",
			"addheader" => array(sprintf('authorization: %s', $this->info['APIKey1'])),
		);

		$i = 0;
		while ($begin_dt < $end_dt) {
			$td = date('Y-m-d', strtotime('+5 days', strtotime($begin_dt)));
			$i ++;

			$url = str_replace(array('{BEGIN_DATE}', '{END_DATE}'), array($begin_dt, $end_dt < $td ? $end_dt : $td), $api_url);
			$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "data_" . date("YmdH") . "_Transaction_{$td}.csv", 'Transaction', true);
			if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
				$fw = fopen($cache_file, 'w');
				if (!$fw) {
					throw new Exception("File open failed {$cache_file}");
				}
				echo "req => {$url} \n";
				$request['file'] = $fw;

				$retry = 5;
				while (true) {
                    $result = $this->oLinkFeed->GetHttpResult($url, $request);
                    if ($result['code'] == 200) {
                        break;
                    } else {
                        if ($retry <= 0) {
                            print_r($result);
                            mydie("Download XML file failed.");
                        }
                    }
                    sleep(2*60);
                    $retry --;
                }
				fclose($fw);
			}
			$result = json_decode(json_encode(simplexml_load_file($cache_file)),true);

			if (isset($result['commissions']) && isset($result['commissions']['commission']) && count($result['commissions']['commission']) > 0) {

				foreach ($result['commissions']['commission'] as $d) {
					$TransactionId = trim($d['commission-id']);
					if (!$TransactionId) {
						continue;
					}
					if (isset($arr_find_Repeated_transactionId[$TransactionId])) {
						mydie("The transactionId={$TransactionId} have earldy exists!");
					} else {
						$arr_find_Repeated_transactionId[$TransactionId] = '';
					}

					$arr_transaction[$TransactionId] = array(
						'AccountSiteID' => $this->info["AccountSiteID"],
						'BatchID' => $this->info['batchID'],
						'TransactionId' => $TransactionId,                      //must be unique
						'action-status' => addslashes($d['action-status']),
						'action-type' => addslashes($d['action-type']),
						'aid' => addslashes(empty($d['aid']) ? '' : $d['aid']),
						'commission-id' => addslashes($d['commission-id']),
						'country' => addslashes(empty($d['country']) ? '' : $d['country']),
						'event-date' => addslashes($d['event-date']),
						'locking-date' => addslashes($d['locking-date']),
						'order-id' => addslashes($d['order-id']),
						'original' => addslashes($d['original']),
						'original-action-id' => addslashes($d['original-action-id']),
						'posting-date' => addslashes($d['posting-date']),
						'website-id' => addslashes(empty($d['website-id']) ? '' : $d['website-id']),
						'action-tracker-id' => addslashes($d['action-tracker-id']),
						'action-tracker-name' => addslashes($d['action-tracker-name']),
						'cid' => addslashes($d['cid']),
						'advertiser-name' => addslashes($d['advertiser-name']),
						'commission-amount' => addslashes($d['commission-amount']),
						'order-discount' => addslashes(empty($d['order-discount']) ? '' : $d['order-discount']),
						'sid' => addslashes(empty($d['sid']) ? '' : $d['sid']),
						'sale-amount' => addslashes(empty($d['sale-amount']) ? '' : $d['sale-amount']),
						'is-cross-device' => addslashes($d['is-cross-device'])
					);
					$tras_num ++;

					if (count($arr_transaction) >= 100) {
						$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
						$arr_transaction = array();
					}
				}
			}
			$begin_dt = $td;
			if ($i > 50) {
				break;
			}
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
