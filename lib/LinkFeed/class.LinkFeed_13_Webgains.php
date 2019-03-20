<?php
class LinkFeed_13_Webgains
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
//        $this->GetProgramByApi();
//        if ($this->isFull) {
        $this->GetProgramByPage();
//        }
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = $arr_prgm_name = array();
        $program_num = $base_program_num = 0;

        $request_arr = array(
            'username' => $this->info["UserName"],
            'password' => $this->info["Password"],
            'campaignid' => $this->info['APIKey1']
        );

        $client = new SoapClient(INCLUDE_ROOT . "wsdl/webgains.wsdl", array('trace' => true));
        $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "programs_list" , "program");
        if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
            $retry = 1;
            while ($retry < 5) {
                $results = $client->__soapCall("getProgramsWithMembershipStatus", $request_arr);
                if (!empty($results)) {
                    break;
                }
            }

            $results = json_encode($results);
            $this->oLinkFeed->fileCachePut($cache_file, $results);
        }
        $cache_file = file_get_contents($cache_file);
        $cache_file = json_decode($cache_file);
        if (count($cache_file)) {
            foreach ($cache_file as $v) {
                $strMerID = intval($v->programID);
                if (!$strMerID) {
                    continue;
                }

                $strStatus = $v->programMembershipStatusName;
//                if (stripos($strStatus, 'Live') !== false || $strStatus == 'Joined') {
//                    $Partnership = 'Active';
//                    $StatusInAff = "Active";
//                } elseif (stripos($strStatus, 'Not joined') !== false) {
//                    $Partnership = 'NoPartnership';
//                    $StatusInAff = "Active";
//                } elseif (stripos($strStatus, 'Pending approval') !== false) {
//                    $Partnership = 'Pending';
//                    $StatusInAff = "Active";
//                } elseif (stripos($strStatus, 'Suspended') !== false) {
//                    $Partnership = 'Expired';
//                    $StatusInAff = "Active";
//                } elseif (stripos($strStatus, 'Rejected') !== false) {
//                    $Partnership = 'Declined';
//                    $StatusInAff = "Active";
//                } elseif (stripos($strStatus, 'siteclosed') !== false) {
//                    $Partnership = 'Active';
//                    $StatusInAff = "Offline";
//                } elseif (stripos($strStatus, 'Under review') !== false) {
//                    $Partnership = 'Pending';
//                    $StatusInAff = "Active";
//                } else {
//                    mydie('Find new partnership symbel (' . $strStatus . ') idinaff=' . $strMerID);
//                }

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                    'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                    'IdInAff' => $strMerID,
                    'Partnership' => addslashes($strStatus),                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    $TargetCountryExt = trim(str_ireplace("Webgains", "", $v->programNetworkName));
                    $strMerName = trim($v->programName);
                    $desc = $v->programDescription;

//                    if (stripos($strMerName, "closed") !== false) {
//                        $strStatus = "closed";
//                        $StatusInAff = "Offline";
//                    }

                    $arr_prgm[$strMerID] += array(
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Name" => addslashes(html_entity_decode($strMerName)),
                        "Homepage" => addslashes($v->programURL),
                        "TargetCountryExt" => $TargetCountryExt,
//                        "StatusInAffRemark" => addslashes($strStatus),
//                        "StatusInAff" => $StatusInAff,
                        "Description" => addslashes($desc),
                    );

                    $base_program_num++;
                }
                $program_num++;
                if (count($arr_prgm) >= 100) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
                    $arr_prgm = array();
                }
            }
        }

        if (count($arr_prgm)) {
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
            unset($arr_prgm);
        }

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

    function GetProgramByPage()
    {
        echo "\tGet program by page start\r\n";

        $objProgram = new ProgramDb();
        $program_num = $base_program_num = 0;
        $arr_prgm = array();

        //step 1,login
        $this->info["AffLoginUrl"] = "http://www.webgains.com/loginform.html?action=login";
        $this->info["AffLoginSuccUrl"] = "http://www.webgains.com/publisher/{$this->info['APIKey1']}";
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info, 6, true, false, false);
        $this->SwitchWebgainsToSelectWebSiteNew();
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        //step 2, get program list
        $page = 1;
        $hasNextPage = true;
        while($hasNextPage)
        {
            echo "page$page\t";
            $url = "http://www.webgains.com/publisher/{$this->info['APIKey1']}/program/list/get-data/joined/all/order/name/sort/asc/keyword//country//category//status/?columns%5B%5D=name&columns%5B%5D=status&columns%5B%5D=voucher_code_enabled&columns%5B%5D=categories&columns%5B%5D=keywords&columns%5B%5D=direct_ppc&columns%5B%5D=vouchers&columns%5B%5D=own_ppc&columns%5B%5D=seo&columns%5B%5D=action&subcategory=&page=$page";
            $retry = 1;
            while (true) {
                $re = $this->oLinkFeed->GetHttpResult($url, $request);
                $re = json_decode($re['content'],true);
                if (isset($re['data']) && !empty($re['data'])) {
                    break;
                }
                if ($retry > 3) {
                    echo $url;
                    mydie("data crawl is empty, please check the API");
                }
                sleep(3);
                $retry ++;
            }

            if ($re['pagesNumber'] == ($page + 1)) {
                $hasNextPage = false;
            }

            foreach ($re['data'] as $v) {
                $strMerID = $v['id'];
                $strStatus = $v['membershipStatus'];
                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $strMerID,
                    'Partnership' => addslashes($strStatus),
                );
                $program_num ++;

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {

                    if (!empty($v['categories']['long']))
                        $CategoryExt = $v['categories']['long'];
                    elseif (!empty($v['categories']['short']))
                        $CategoryExt = $v['categories']['short'];
                    else
                        $CategoryExt = '';
                    $CookieTime = intval($v['cookieLength']);

                    $arr_prgm[$strMerID] += array(
                        "Name" => addslashes(trim($v['name'])),
                        "TargetCountryExt" => addslashes(trim($v['networkName'])),
                        "Description" => addslashes(trim($v['description'])),
                        "CategoryExt" => addslashes($CategoryExt),
                        "CookieTime" => $CookieTime,
                        'CrawlJobId' => $this->info['crawlJobId'],
                    );

                    $base_program_num++;
                    if (count($arr_prgm) >= 100) {
                        $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
                        $arr_prgm = array();
                    }
                }
            }
            $page++;
            if ($page > 100) {
                mydie("die: Page overload.\n");
            }
        }
        if (count($arr_prgm)) {
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
            $arr_prgm = array();
        }
        echo "\n\tGet Program by page end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";


        //step 3, get program detail
        if ($this->isFull) {
            $objProgram = new ProgramDb();
            $arr_prgm_off = array();
            $program_num = 0;

            $sql = "SELECT IdInAff,Partnership FROM batch_program_account_site_13 WHERE BatchID = " . intval($this->info["batchID"]) . " AND AccountSiteID = " . intval($this->info['AccountSiteID']);
            $prgm = $objProgram->objMysql->getRows($sql);

            $active_prgm_list = array();
            if (!empty($prgm)) {
                foreach ($prgm as $val) {
                    //Only choose the programs whose have partnership with us!
                    if (stripos($val['Partnership'], 'Live') !== false || $val['Partnership'] == 'Joined' || stripos($val['Partnership'], 'siteclosed') !== false) {
                        $active_prgm_list[] = $val['IdInAff'];
                    }
                }
            }
            echo "\tget " . count($active_prgm_list) . " p\r\n";

            foreach ($active_prgm_list as $strMerID) {
                if (!$strMerID || $objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    continue;
                }

                $prgm_detail = '';
                $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "detail_{$strMerID}", "program");
                if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
                    $prgm_url = "http://www.webgains.com/publisher/{$this->info['APIKey1']}/program/view?programID=$strMerID";
                    $retry = 4;
                    while ($retry) {
                        $prgm_arr = $this->oLinkFeed->GetHttpResult($prgm_url, $request);
                        if ($prgm_arr["code"] == 200 && !empty($prgm_arr["content"]) && $prgm_arr["final_url"] == $prgm_url && stripos($prgm_arr["content"], "$strMerID") !== false) {
                            $prgm_detail = $prgm_arr["content"];
                            $this->oLinkFeed->fileCachePut($cache_file, $prgm_detail);
                            break;
                        }
                        $retry--;
                    }
                } else {
                    $prgm_detail = file_get_contents($cache_file);
                }

                if (!empty($prgm_detail)) {

                    $arr_prgm[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                        'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                        'IdInAff' => $strMerID,
                        'Homepage' => '',
                        'Description' => '',
                        'Contacts' => '',
                        'SEMPolicyExt' => '',
                        'CommissionExt' => '',
                        'LogoUrl' => '',
                        'SupportDeepUrl' => '',
                        'SupportType' => '',
                        'CrawlJobId' => $this->info['crawlJobId'],
                    );

                    $Homepage = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('class="homepageUrl', 'href="'), '"')));
                    $arr_prgm[$strMerID]["Homepage"] = addslashes(trim($Homepage));

                    $Description = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('id="desc-full"', '>'), '&nbsp;<a id="desc-view-less')));
                    $arr_prgm[$strMerID]["Description"] = addslashes(trim($Description));

                    $Contacts = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Contact details', 'Account manager:'), '</h2>')));
                    $tmp_email = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Account manager', 'mailto:'), '"'));
                    if (!empty($tmp_email)) $Contacts .= ", Email: " . $tmp_email;
                    $arr_prgm[$strMerID]["Contacts"] = addslashes($Contacts);

                    $SEMPolicyExt = "PPC Policy Overview:" . trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, 'PPC Policy Overview:', '<br/>'));
                    $SEMPolicyExt .= ", Keyword policy details:" . trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('id="keywordPolicyBox"', '<div class="modal-body">'), '</div>')));
                    $arr_prgm[$strMerID]["SEMPolicyExt"] = addslashes($SEMPolicyExt);

                    $CommissionExt = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Commission details', '<h2>'), '<span class=')));
                    $arr_prgm[$strMerID]["CommissionExt"] = addslashes($CommissionExt);

                    $LogoUrl = 'http://www.webgains.com' . trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('<div class="wrapper">', '<img src="'), '"'));
                    $arr_prgm[$strMerID]['LogoUrl'] = addslashes($LogoUrl);

                    //check support deep_links_
                    $deep_arr = $this->oLinkFeed->GetHttpResult("http://www.webgains.com/front/publisher/program/get-tools/programid/$strMerID", $request);
                    $tmp_obj = @json_decode($deep_arr["content"]);
                    if (isset($tmp_obj->deep_links)) {
                        if ($tmp_obj->deep_links == "Allowed") {
                            $arr_prgm[$strMerID]["SupportDeepUrl"] = "YES";
                        } else {
                            $arr_prgm[$strMerID]["SupportDeepUrl"] = "NO";
                        }
                    }

                    $SupportType = '';
                    $list = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Marketing channels', 'id="widget_info">'), '</table>'));
                    $list = explode("</tr><tr>", preg_replace("/>\s*?</", "><", $list));
                    foreach ($list as $channel) {
                        if (strpos($channel, "Content Rewards") && strpos($channel, 'tick_sml.png')) {
                            $SupportType .= "Content" . EX_CATEGORY;
                        } elseif (strpos($channel, "Discount Voucher Sites") && strpos($channel, 'tick_sml.png')) {
                            $SupportType .= "Coupon" . EX_CATEGORY;
                        }
                    }
                    $arr_prgm[$strMerID]['SupportType'] = rtrim($SupportType, EX_CATEGORY);

                    if (count($arr_prgm) >= 100) {
                        $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                        $arr_prgm = array();
                    }

                } elseif ($prgm_arr["code"] == 200 && $prgm_arr["final_url"] != $prgm_url) {
                    echo $prgm_url . "\t";
                    $arr_prgm_off[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],
                        'BatchID' => $this->info['batchID'],
                        "IdInAff" => $strMerID,
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Partnership" => 'Offline'
                    );
                }
                $program_num++;
            }

            if (count($arr_prgm)) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                unset($arr_prgm);
            }

            if (count($arr_prgm_off) > 30) {
                mydie("Can't open programs detail page normally!");
            } else {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm_off);
                unset($arr_prgm_off);
            }

            echo "\tUpdate detail ({$program_num}) program.\r\n";
        }

    }

    function SwitchWebgainsToSelectWebSiteNew($checkonly=false, $times = 3, $siteid = 0)
    {
        $strUKSiteID = $this->info['APIKey1'];
        if(isset($this->oLinkFeed->WebgainsCurrentSite) && $this->oLinkFeed->WebgainsCurrentSite == $strUKSiteID) {
            return true;
        }

        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        if($times < 3){
            $request["method"] = "post";
            $request["postdata"] = "globalaction=switchcampaign&campaignswitchid=$strUKSiteID";
        }

        if($siteid == 0){
            $siteid = $strUKSiteID;
        }

        $strUrl = "http://www.webgains.com/publisher/$siteid";
        $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
        $result = $r["content"];

        $strSiteSelected = trim($this->oLinkFeed->ParseStringBy2Tag($result, 'currentCampaign:', ','));
        if (intval($strSiteSelected) == $strUKSiteID) {
            //is UK Site now, do nothing
            if($checkonly) {
                echo "double check site switch result: ok! \n";
            }
            else echo "is $strUKSiteID Site now, do nothing <br> \n";

            $this->oLinkFeed->WebgainsCurrentSite = $strUKSiteID;
            return true;
        } elseif($checkonly) {
            mydie("die: SwitchWebgainsToSelectWebSiteNew failed.\n");
        } else{
            if($times > 0){
                $times--;
                echo "[$strSiteSelected] is NOT $strUKSiteID site now. do switch has $times chances.<br> \n";
                return $this->SwitchWebgainsToSelectWebSiteNew(false, $times, $strSiteSelected);
            }else{
                mydie("die: SwitchWebgainsToSelectWebSiteNew failed, try 3 times.\n");
            }
        }
    }

	function getTransactionFromAff($start_date, $end_date)
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

		define('AWS_LOCATION', 'http://ws.webgains.com/aws.php');
		define('AWS_URI', 'http://ws.webgains.com/aws.php');
		define('AWS_ACTION', 'getFullEarningsWithCurrency');
		$objTransaction = New TransactionDb();
		$arr_transaction = $arr_find_Repeated_transactionId = array();
		$tras_num = 0;


		$client = new SoapClient(null, array('location' => AWS_LOCATION, 'uri' => AWS_URI, 'trace' => true));
		if ($this->info['AccountSiteID'] == 19) {
			$SITE_CAMPAIGNS = array(
				'uk' => '192821',
			);
		} else {
			$SITE_CAMPAIGNS = array(
				'csus' => '207237',
				'csuk' => '207235',
				'csie' => '207241',
				'csde' => '206803',
				'csca' => '207239',
				'csau' => '207233'
			);
		}
		$d = new DateTime($start_date);

		$i = 0;
		while($d->format('Y-m-d') <= $end_date) {
			$start_dt = $d->format('Y-m-d') . 'T00:00:00';
			$d->modify('+15 day');
			if ($d->format('Y-m-d') > $end_date) {
				$end_dt = $end_date . 'T23:59:59';
			} else {
				$end_dt = $d->format('Y-m-d') . 'T23:59:59';
			}
			$d->modify('+1 day');

			echo "Doing page: ST:{$start_dt} ET:{$end_dt} \n";
			if ($i > 50) {
				break;
			} else {
				$i++;
			}
			foreach ($SITE_CAMPAIGNS as $site => $campaignid) {
				$st = new SoapVar($start_dt, XSD_DATETIME, 'startdate', 'xsd:dateTime');
				$et = new SoapVar($end_dt, XSD_DATETIME, 'enddate', 'xsd:dateTime');
				$us = new SoapVar($this->info['UserName'], XSD_STRING, 'username', 'xsd:string');
				$pa = new SoapVar($this->info['Password'], XSD_STRING, 'password', 'xsd:string');
				$cp = new SoapVar($campaignid, XSD_INT, 'campaignid', 'xsd:int');

				$retry = 0;
				$r = array();
				do {
					try {
						$r = $client->__soapCall(
							AWS_ACTION,
							array($st, $et, $cp, $us, $pa),
							array(
								'location' => AWS_LOCATION,
								'uri' => AWS_URI,
								'soapaction' => 'http://ws.webgains.com/aws.php#' . AWS_ACTION
							)
						);
						$pass = false;
					} catch (Exception $e) {
						if (++$retry <= 5) {
							$pass = true;
							sleep(30);
						} else {
							$pass = false;
							continue 2;
						}
					}
				} while ($pass);

				if (!is_array($r) || count($r) == 0)
					continue;


				foreach ($r as $o) {

					$TransactionId = trim($o->transactionID);
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
						'affiliateID' => addslashes($o->affiliateID),
						'campaignName' => addslashes($o->campaignName),
						'campaignID' => addslashes($o->campaignID),
						'date' => addslashes($o->date),
						'validationDate' => addslashes($o->validationDate),
						'delayedUntilDate' => addslashes($o->delayedUntilDate),
						'programName' => addslashes($o->programName),
						'programID' => addslashes($o->programID),
						'linkID' => addslashes($o->linkID),
						'eventID' => addslashes($o->eventID),
						'commission' => addslashes($o->commission),
						'saleValue' => addslashes($o->saleValue),
						'status' => addslashes($o->status),
						'paymentStatus' => addslashes($o->paymentStatus),
						'changeReason' => addslashes($o->changeReason),
						'clickRef' => addslashes($o->clickRef),
						'clickthroughTime' => addslashes($o->clickthroughTime),
						'landingPage' => addslashes($o->landingPage),
						'country' => addslashes($o->country),
						'referrer' => addslashes($o->referrer),
						'currency' => addslashes($o->currency),
					);
					$tras_num++;

					if (count($arr_transaction) >= 100) {
						$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
						$arr_transaction = array();
					}
				}
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
