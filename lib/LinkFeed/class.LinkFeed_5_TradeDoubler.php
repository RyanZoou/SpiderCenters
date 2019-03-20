<?php
class LinkFeed_5_TradeDoubler
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

        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);
        $site_id = $this->info['APIKey1'];
        $contry_code = $this->info['APIKey2'];

        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = $base_program_num = 0;

        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => "",);

        if ($this->isFull) {
            //get commission info
            $commission_info = array();
            $strUrl = "https://reports.tradedoubler.com/pan/aReport3Key.action?metric1.summaryType=NONE&metric1.lastOperator=/&metric1.columnName2=programId&metric1.operator1=/&metric1.columnName1=programId&metric1.midOperator=/&customKeyMetricCount=0&columns=programTariffPercentage&columns=programTariffCurrency&columns=programTariffAmount&columns=programId&columns=programName&sortBy=orderDefault&includeWarningColumn=true&affiliateId=$site_id&latestDayToExecute=0&setColumns=true&reportTitleTextKey=REPORT3_SERVICE_REPORTS_AAFFILIATEMYPROGRAMSREPORT_TITLE&interval=MONTHS&reportName=aAffiliateMyProgramsReport&key=731a61f9409131c6a22f415c179853ea";
            $result = $this->oLinkFeed->GetHttpResult($strUrl, $request);
            if (empty($result['content'])) {
                mydie("Can't get data from api.");
            }
            $result = preg_replace('@>\s+<@', '><', $result['content']);
            $listStr = $this->oLinkFeed->ParseStringBy2Tag($result, array('Sub total:Brandreward', '<tbody', '>'), '</tbody>');
            $listArr = explode('href="/pan/aProgramInfoApplyRead.action?programId', $listStr);
            $programArr = array();
            foreach ($listArr as $pStr) {
                $programId = intval($this->oLinkFeed->ParseStringBy2Tag($pStr, '=', '&'));
                if (!$programId) {
                    continue;
                }
                $programArr[$programId] = explode('</tr><tr', $pStr);
                $count = count($programArr[$programId]);
                unset($programArr[$programId][$count - 1]);
            }
            if ('HK' != $contry_code) {
				foreach ($programArr as $programId => $val) {
					$commission = '';
					foreach ($val as $key => $cV) {
						preg_match('@<td class=".+">(\d+\.\d+)</td><td>([A-Z]{3})</td><td class=".+">((?:\d+\.\d+%)|(?:&nbsp;))</td>@', $cV, $m);
						if (!isset($m[2]) || empty($m[2]) || (($m[1] == 0.00 && ($m[3] == '0.00%' || $m[3] == '&nbsp;')))) {
							continue;
						}
						if ($m[3] != '100.00%'&&$m[3] != '0.00%') {
							$commission .= $m[3] . ',';
						} elseif ($m[1] != 0.00) {
							$commission .= $m[2] . ' ' . $m[1] . ',';
						}
					}
					$commission_info[$programId] = rtrim($commission, ',');
				}
			}
        }

        //get progrm info
        $nNumPerPage = 100;
        $nPageNo = 1;
        while (1) {
            if ($nPageNo == 1) {
                $strUrl = "https://publisher.tradedoubler.com/pan/aProgramList.action";
                $request["method"] = "post";
                $request["postdata"] = "programGEListParameterTransport.currentPage=" . $nPageNo . "&searchPerformed=true&searchType=prog&programGEListParameterTransport.programIdOrName=&programGEListParameterTransport.deepLinking=&programGEListParameterTransport.tariffStructure=&programGEListParameterTransport.siteId=" . $site_id . "&programGEListParameterTransport.orderBy=statusId&programAdvancedListParameterTransport.websiteStatusId=&programGEListParameterTransport.pageSize=" . $nNumPerPage . "&programAdvancedListParameterTransport.directAutoApprove=&programAdvancedListParameterTransport.mobile=&programGEListParameterTransport.graphicalElementTypeId=&programGEListParameterTransport.graphicalElementSize=&programGEListParameterTransport.width=&programGEListParameterTransport.height=&programGEListParameterTransport.lastUpdated=&programGEListParameterTransport.graphicalElementNameOrId=&programGEListParameterTransport.showGeGraphics=true&programAdvancedListParameterTransport.pfAdToolUnitName=&programAdvancedListParameterTransport.pfAdToolProductPerCell=&programAdvancedListParameterTransport.pfAdToolDescription=&programAdvancedListParameterTransport.pfTemplateTableRows=&programAdvancedListParameterTransport.pfTemplateTableColumns=&programAdvancedListParameterTransport.pfTemplateTableWidth=&programAdvancedListParameterTransport.pfTemplateTableHeight=&programAdvancedListParameterTransport.pfAdToolContentUnitRule=";
                $this->oLinkFeed->GetHttpResult($strUrl, $request);
            }
            $strUrl = "https://publisher.tradedoubler.com/pan/aProgramList.action?categoryChoosen=false&programGEListParameterTransport.currentPage=" . $nPageNo . "&programGEListParameterTransport.pageSize=" . $nNumPerPage . "&programGEListParameterTransport.pageStreamValue=true";
            $request["postdata"] = "";
            $request["method"] = "get";
            $r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
            $result = $r["content"];

            //parse HTML
            $strLineStart = 'showPopBox(event, getProgramCodeAffiliate';
            $nLineStart = 0;
            $bStart = 1;
            while (1) {
                $nLineStart = stripos($result, $strLineStart, $nLineStart);
                if ($nLineStart === false && $bStart == 1) {
                    break 2;
                }
                if ($nLineStart === false) {
                    break;
                }
                $bStart = 0;

                $strMerID = $this->oLinkFeed->ParseStringBy2Tag($result, 'getProgramCodeAffiliate(', ',', $nLineStart);
                if ($strMerID === false) {
                    break;
                }
                $strMerID = trim($strMerID);
                if (empty($strMerID)) {
                    continue;
                }

                echo "$strMerID\t";

                //name
                $strMerName = $this->oLinkFeed->ParseStringBy2Tag($result, ">", "</a>", $nLineStart);
                if ($strMerName === false) {
                    break;
                }
                $strMerName = html_entity_decode(trim($strMerName));

                $CategoryExt = $this->oLinkFeed->ParseStringBy2Tag($result, '<td>', '</td>', $nLineStart);
                $CategoryExt = trim(str_replace(",&nbsp;", EX_CATEGORY, $CategoryExt));
                $arr_pattern = array();
                for ($i = 0; $i < 8; $i++) {
                    $arr_pattern[] = "<td>";
                }
                $EPC90d = $this->oLinkFeed->ParseStringBy2Tag($result, $arr_pattern, '</td>', $nLineStart);
                if ($EPC90d === false) {
                    break;
                }
                $EPC90d = trim(html_entity_decode(strip_tags($EPC90d)));

                $EPCDefault = $this->oLinkFeed->ParseStringBy2Tag($result, '<td>', '</td>', $nLineStart);
                if ($EPCDefault === false) {
                    break;
                }
                $EPCDefault = trim(html_entity_decode(strip_tags($EPCDefault)));

                $MobileFriendly = trim(strtoupper($this->oLinkFeed->ParseStringBy2Tag($result, array('<td>', '<td>'), '</td>', $nLineStart)));
                if ($MobileFriendly != 'YES' && $MobileFriendly != 'NO') {
                    $MobileFriendly = 'UNKNOWN';
                }

                $strStatus = $this->oLinkFeed->ParseStringBy2Tag($result, '<td>', '</td>', $nLineStart);
                if ($strStatus === false) {
                    break;
                }
                $strStatus = trim(strip_tags($strStatus));
                if (0 && $contry_code == "DE") {
                    //warning: im not very sure for those de status ...
                    if (stripos($strStatus, 'Akzeptiert') !== false) {
                        $strStatus = 'approval';
                    } elseif (stripos($strStatus, 'Unter Beobachtung') !== false) {
                        $strStatus = 'pending';
                    } elseif (stripos($strStatus, 'Beendet') !== false) {
                        $strStatus = 'declined';
                    } elseif (stripos($strStatus, 'In Bearbeitung') !== false) {
                        $strStatus = 'pending';
                    } elseif (stripos($strStatus, 'Programmbewerbung') !== false) {
                        $strStatus = 'not apply';
                    } elseif (stripos($strStatus, 'Abgelehnt') !== false) {
                        $strStatus = 'declined';
                    } else {
                        mydie("die: Unknown Status: $strStatus <br>\n");
                    }
                } else {
                    if (stripos($strStatus, 'Accepted') !== false) {
                        $strStatus = 'approval';
                    } elseif (stripos($strStatus, 'Under Consideration') !== false) {
                        $strStatus = 'pending';
                    } elseif (stripos($strStatus, 'Denied') !== false) {
                        $strStatus = 'declined';
                    } elseif (stripos($strStatus, 'On Hold') !== false) {
                        $strStatus = 'not apply';
                    } elseif (stripos($strStatus, 'Apply') !== false) {
                        $strStatus = 'not apply';
                    } elseif (stripos($strStatus, 'Ended') !== false) {
                        $strStatus = 'declined';
                    } else {
                        mydie("die: Unknown Status: $strStatus <br>\n");
                    }
                }

                if (stripos($strMerName, 'Closed') !== false) {
                    $strStatus = 'siteclosed';
                }elseif (stripos($strMerName, 'closing') !== false || stripos($strMerName, 'pausing') !== false) {
                    if (preg_match('@\d\d\.\d\d\.\d\d\d\d@', $strMerName, $g) && isset($g[0]) && strtotime($g[0]) < time()) {
                        $strStatus = 'siteclosed';
                    }
                    if (preg_match('@(\d+)\/(\d+)\/(\d\d)@', $strMerName, $g) && strtotime(sprintf("20%s-%s-%s", $g[3], $g[2], $g[1])) < time()) {
                        $strStatus = 'siteclosed';
                    }
                    if (preg_match('@(\d+)\/(\d+)\/(\d\d\d\d)@', $strMerName, $g) && strtotime(sprintf("%s-%s-%s", $g[3], $g[2], $g[1])) < time()) {
                        $strStatus = 'siteclosed';
                    }
                    echo $strMerName . "---" . $strStatus . "\n";
                } elseif (stripos($strMerName, 'paused') !== false) {
                    $strStatus = 'siteclosed';
                } elseif (stripos($strMerName, 'set to pause') !== false) {
                    $strStatus = 'siteclosed';
                }if ($strStatus == 'approval') {
                    $Partnership = "Active";
                    $StatusInAff = "Active";
                } elseif ($strStatus == 'pending') {
                    $Partnership = "Pending";
                    $StatusInAff = "Active";
                } elseif ($strStatus == 'declined') {
                    $Partnership = "Declined";
                    $StatusInAff = "Active";
                } elseif ($strStatus == 'not apply') {
                    $Partnership = "NoPartnership";
                    $StatusInAff = "Active";
                } else {
                    $Partnership = "NoPartnership";
                    $StatusInAff = "Offline";
                }

                $AffDefaultUrl = '';
                $links_url = "https://publisher.tradedoubler.com/pan/aProgramInfoLinksRead.action?programId={$strMerID}&affiliateId={$site_id}";
                $links_arr = $this->oLinkFeed->GetHttpResult($links_url, $request);
                if ($links_arr['code'] == 200) {
                    $links_detail = $links_arr["content"];
                    $g_id = intval($this->oLinkFeed->ParseStringBy2Tag($links_detail, array('/pan/aInfoCenterLinkInfo.action', 'geId='), '&'));
                    if ($g_id > 0) {
                        $AffDefaultUrl = "http://clkuk.tradedoubler.com/click?p({$strMerID})a({$site_id})g({$g_id})";
                    }
                }

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $strMerID,
                    'Partnership' => $Partnership,
                    'AffDefaultUrl' => addslashes($AffDefaultUrl)
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    $desc = '';
                    $request["method"] = "get";
                    $request["postdata"] = "";
                    $prgm_url = "https://publisher.tradedoubler.com/pan/aProgramTextRead.action?programId={$strMerID}&affiliateId={$site_id}";
                    $prgm_arr = $this->oLinkFeed->GetHttpResult($prgm_url, $request);
                    if ($prgm_arr['code'] == 200) {
                        $prgm_detail = $prgm_arr["content"];
                        $desc = "<div>" . trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<div id="publisher-body">', '<div id="publisher-footer">'));
                        $desc = preg_replace("/[\\r|\\n|\\r\\n|\\t]/is", '', $desc);
                        $desc = preg_replace('/<([a-z]+?)\s+?.*?>/i', '<$1>', $desc);
                        preg_match_all('/<([a-z]+?)>/i', $desc, $res_s);
                        preg_match_all('/<\/([a-z]+?)>/i', $desc, $res_e);
                        $tags_arr = array();
                        foreach ($res_s[1] as $v) {
                            if (strtolower($v) != "br") {
                                if (isset($tags_arr[$v])) {
                                    $tags_arr[$v] += 1;
                                } else {
                                    $tags_arr[$v] = 1;
                                }
                            }
                        }
                        foreach ($res_e[1] as $v) {
                            if (strtolower($v) != "br" && isset($tags_arr[$v])) {
                                $tags_arr[$v] -= 1;
                            }
                        }
                        foreach ($tags_arr as $k => $v) {
                            for ($i = 0; $i < $v; $i++) {
                                $desc .= "</$k>";
                            }
                        }
                    }

                    $SupportDeepUrl = $LogoUrl = $CookieTime = $PaymentDays = $Homepage = '';
                    $overview_url = "https://publisher.tradedoubler.com/pan/aProgramInfoApplyRead.action?programId={$strMerID}&affiliateId={$site_id}";
                    $overview_arr = $this->oLinkFeed->GetHttpResult($overview_url, $request);
                    if ($prgm_arr['code'] == 200) {
                        $overview_detail = $overview_arr["content"];
                        $Homepage = trim($this->oLinkFeed->ParseStringBy2Tag($overview_detail, array('Visit the site', '<a href="'), '"'));
                        $SupportDeepUrl = strtoupper(trim($this->oLinkFeed->ParseStringBy2Tag($overview_detail, array('Deep linking', '<td nowrap="nowrap">'), '</td>')));
                        $LogoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($overview_detail, array('<table border="0">', '<td><img src="'), '"></td>'));
                        $CookieTime = intval(trim($this->oLinkFeed->ParseStringBy2Tag($overview_detail, array('Cookie time', '<td nowrap="nowrap">'), 'day')));
                        $PaymentDays = intval(trim(html_entity_decode($this->oLinkFeed->ParseStringBy2Tag($overview_detail, array('Time to auto accept', '<td>', '<td>'), 'Day'))));
						if (!isset($commission_info[$strMerID]) || empty($commission_info[$strMerID])) {
							$table = $this->oLinkFeed->ParseStringBy2Tag($overview_detail, '<table class="listTable">', ' </table>');
							$table = preg_replace('@>\s+<@', '><', $table);
							$comm_type = 0;
							if(strpos($table, 'more ></a>')){
								$comm_type = 1;
								echo "get comm detail page";
								$more_comm_url = "https://publisher.tradedoubler.com/pan/aProgramInfoCommissionPLCRead.action?programId={$strMerID}&affiliateId={$site_id}";
								$more_comm_res = $this->oLinkFeed->GetHttpResult($more_comm_url, $request);
								if ($more_comm_res['code'] != 200) mydie('get commission detail page error');
								$more_comm_res = $more_comm_res["content"];
								$arr_table = $this->oLinkFeed->ParseStringBy2Tag($more_comm_res,array('<table class="tablebox tableBorder"','>'),'</table>');
								$arr_table = preg_replace("@<!--.*?-->@",'',$arr_table);
								$arr_table = preg_replace('@>\s+<@', '><',$arr_table);
								$arr_table = explode('</tr><tr>', $arr_table);
								unset($arr_table[0]);
							}else{
								$arr_table = explode('</tr><tr>', $table);
								unset($arr_table[0]);
							}
							
							$commission = '';
							foreach ($arr_table as $value){
								$arr_comm=explode('</td><td>',$value);
								if ($comm_type){
									$arr_comm[0] = str_replace('<td>', '', $arr_comm[0]);
									$commission .= $arr_comm[0]."\t$".$arr_comm[1]."\t".$arr_comm[2]."%\n";
								}else {
									if((float)$arr_comm[2]==0.00)
										continue;
									if (trim($arr_comm[1])=='USD'){
										$commission .='$'.trim((float)$arr_comm[2]).',';
									}elseif (trim($arr_comm[1])=='') {
										$commission .=trim((float)$arr_comm[2]).'%'.',';
									}elseif (trim($arr_comm[1])=='GBP'){
										$commission .='£'.trim((float)$arr_comm[2]).',';
									}elseif (trim($arr_comm[1])=='EUR'){
										$commission .='€'.trim((float)$arr_comm[2]).',';
									}else
									{
										mydie("new currency {$arr_comm[1]} in {$strMerID},please check {$overview_url}");
									}
								}
							}
							$commission_info[$strMerID] = rtrim($commission, ',');
						}
                        if ($tmp_url = $this->oLinkFeed->findFinalUrl($Homepage)) {
                            $Homepage = $tmp_url;
                        }
                    }

                    $arr_prgm[$strMerID] += array(
                        "CrawlJobId" => $this->info['crawlJobId'],
                        "Name" => addslashes($strMerName),
                        "TargetCountryExt" => $contry_code,
                        "StatusInAffRemark" => $strStatus,
                        "StatusInAff" => $StatusInAff,
                        "EPCDefault" => preg_replace("/[^0-9.]/", "", $EPCDefault),
                        "EPC90d" => preg_replace("/[^0-9.]/", "", $EPC90d),
                        "MobileFriendly" => addslashes($MobileFriendly),
                        "CategoryExt" => addslashes($CategoryExt),
                        'Description' => addslashes($desc),
                        'Homepage' => addslashes($Homepage),
                        'SupportDeepUrl' => addslashes($SupportDeepUrl),
                        'LogoUrl' => addslashes($LogoUrl),
                        'CookieTime' => $CookieTime,
                        'PaymentDays' => $PaymentDays,
                        'CommissionExt' => isset($commission_info[$strMerID]) ? addslashes($commission_info[$strMerID]) : ''
                    );
                    $base_program_num ++;
                }

                $program_num++;
                if (count($arr_prgm) >= 100) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }
            }
            $nPageNo++;
            if (count($arr_prgm)) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }
        }

        echo "\tGet Program by page end\r\n";
        if ($program_num < 1) {
            mydie("die: program count < 1, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }

}