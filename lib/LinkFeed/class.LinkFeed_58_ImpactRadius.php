<?php
class LinkFeed_58_ImpactRadius
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->siteArr = json_decode($this->info['APIKey1'], true);
    }

    function GetProgramFromAff()
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Program start @ {$check_date}\r\n";
        $this->isFull = $this->info['isFull'];
        $this->GetProgramByPage();
        $this->GetProgramByApi();
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function GetProgramByPage()
    {
        echo "\tGet Program by page start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = $program_info = array();
        $program_num = $base_program_num = 0;
        $concat_arr = array();

        //step 1,login
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info);

        foreach ($this->siteArr as $siteType => $params) {
            echo "\tGet $siteType site Program by page start\r\n";

            $new_user_id = $params['NEW_USER_ID'];
            echo "$new_user_id\r\n";

            $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);
            $time = time() . "123";

            //switch site
            $url = "https://app.impact.com/secure/member/set-current-usership-flow.ihtml?newUsershipId=$new_user_id";
            $this->oLinkFeed->GetHttpResult($url, $request);
            $url = "https://app.impact.com/secure/member/set-current-usership-flow.ihtml?execution=e1s1";
            $this->oLinkFeed->GetHttpResult($url, $request);

            //通过csv仅获取contacts
            if ($this->isFull) {
                $str_header = "First Name,Last Name,Email,Campaign,Campaign Id";
                $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "{$siteType}_myCampaignContacts.csv", "cache_contact");
                if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
                    $strUrl = "https://app.impact.com/secure/account/emaillist/myCampaignContacts.csv";
                    $request["postdata"] = "";

                    $r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
                    $result = $r["content"];
                    print "Get Contacts <br>\n";
                    if (stripos($result, $str_header) === false) {
                        mydie("die: wrong header: " . strstr($result, 0, stripos($result, "\n")));
                    }
                    $this->oLinkFeed->fileCachePut($cache_file, $result);
                }

                $fhandle = fopen($cache_file, 'r');
                while ($line = fgetcsv($fhandle, 5000)) {
                    foreach ($line as $k => $v) $line[$k] = trim($v);

                    if ($line[0] == '' || $line[0] == 'First Name') {
                        continue;
                    }
                    if (!isset($line[4])) {
                        continue;
                    }
                    if (!isset($line[2])) {
                        continue;
                    }
                    $concat_arr[$line[4]]["Contacts"] = addslashes($line[0] . " " . $line[1] . ", Email:" . $line[2]);
                }
            }

            //get pending and not applied programs
            $status_arr = array(
                'Pending' => 'PENDING_MP_APPROVAL%2CPENDING_CAMPAIGN_APPROVAL',
                'NoPartnership' => 'NOT_APPLIED'
            );
            foreach ($status_arr as $partnership => $param) {
                echo "\r\nget $partnership programs\r\n";
                $startIndex = 0;
                $size = 100;
                $hasNextPage = true;
                while ($hasNextPage) {
                    $strUrl = "https://app.impact.com/secure/nositemesh/market/campaign/all.ihtml?_dc=$time&categories=&servicearea=&actions=&rstatus=$param&ads=&rating=&additional=&dealtype=&countries=&q=&tab=all&sortBy=name&sortOrder=ASC&page=1&startIndex=$startIndex&pageSize=$size";
                    $cacheName = "{$partnership}_{$new_user_id}_program_index_{$startIndex}_" . date('YmdH') . '.cache';
                    $result = $this->oLinkFeed->GetHttpResultAndCache($strUrl, $request, $cacheName, 'results');
                    $result = json_decode($result, true);

                    if (empty($result)) {
                        mydie("Get data failed from page.");
                    }
                    if ($result['numRecords'] > $size + $startIndex) {
                        $startIndex += $size;
                    } else {
                        $hasNextPage = false;
                    }

                    foreach ($result['results'] as $pv) {
                        $strMerID = intval($pv['id']);
                        if (!$strMerID) {
                            continue;
                        }
                        echo "$strMerID\t";

                        $PartnershipStr = $partnership;
                        if (isset($program_info[$strMerID]['Partnership']) &&  $program_info[$strMerID]['Partnership'] == 'Active'){
                            $PartnershipStr = 'Active';
                        }

                        $arr_prgm[$strMerID] = array(
                            'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                            'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                            'IdInAff' => $strMerID,
                            'Partnership' => $PartnershipStr,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                        );

                        if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                            $strMerName = trim($pv['name']);
                            $CategoryExt = trim($pv['subTitle']);
                            $CategoryExt = str_replace(',', EX_CATEGORY, $CategoryExt);
                            $LogoUrl = trim($pv['logoSrc']);
                            $Homepage = trim($pv['landingPage']);
                            $CommissionExt = '';
                            if (!empty($pv['slides'])) {
                                foreach ($pv['slides'] as $val) {
                                    $CommissionExt .= $val['value'] . '|';
                                }
                                $CommissionExt = rtrim($CommissionExt, '|');
                            }

                            $prgm_url = "https://app.impact.com/secure/directory/campaign.ihtml?d=lightbox&n=footwear+etc&c=$strMerID";
                            $detailCacheName = "{$new_user_id}_program_detail_{$strMerID}_" . date('YmdH') . '.cache';
                            $prgm_detail = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request, $detailCacheName, 'Impact');

                            preg_match('/id="serviceAreas".*?>(.*?)<\/div>/', $prgm_detail, $TargetCountryExt);
                            $TargetCountryExt = isset($TargetCountryExt[1]) ? $TargetCountryExt[1] : "";
                            $JoinDate = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, 'Active Since', '<'));
                            if ($JoinDate) {
                                $JoinDate = date("Y-m-d H:i:s", strtotime(trim($JoinDate)));
                            }
                            $desc = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('id="dirPubDesc"', '>'), '<');

                            $supportDeepLink = 'UNKNOWN';
                            $attrStr = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('class="campaignAttributesList"', '>'), '</ul');
                            $attrStr = preg_replace('@>\s+<@', '><', $attrStr);
                            $attrArr = explode('</li><li', $attrStr);
                            if (isset($attrArr[7])) {
                                preg_match('@span class="uitkCheck([a-zA-Z]+)"@', $attrArr[7], $deep);
                                $supportDeepLink = $deep[1] == 'True' ? 'YES' : 'NO';
                            }

                            $arr_prgm[$strMerID] += array(
                                'CrawlJobId' => $this->info['crawlJobId'],
                                "Name" => addslashes(html_entity_decode($strMerName)),
                                "CategoryExt" => addslashes($CategoryExt),
                                "JoinDate" => $JoinDate,
                                "Description" => addslashes($desc),
                                "CommissionExt" => addslashes($CommissionExt),
                                "Homepage" => addslashes($Homepage),
                                "TargetCountryExt" => addslashes($TargetCountryExt),
                                "LogoUrl" => addslashes($LogoUrl),
                                "SupportDeepUrl" => $supportDeepLink,
                                "Contacts" => isset($concat_arr[$strMerID]["Contacts"]) ? $concat_arr[$strMerID]["Contacts"] : ''
                            );
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
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }
            }

            //get active program
            echo "\r\nget active programs\r\n";
            $strUrl = "https://app.impact.com/secure/mediapartner/campaigns/mp-manage-active-ios-flow.ihtml?execution=e31s1";
            $this->oLinkFeed->GetHttpResult($strUrl, $request);

            $page = 1;
            $hasNextPage = true;
            while ($hasNextPage) {
                $start = ($page - 1) * 100;
                $strUrl = "https://app.impact.com/secure/nositemesh/mediapartner/mpCampaignsJSON.ihtml?_dc=$time&startIndex=$start&pageSize=100&tableId=myCampaignsTable&page=$page";
                $cacheName = "{$new_user_id}_active_program_page_{$page}_" . date('YmdH') . '.cache';
                $result = $this->oLinkFeed->GetHttpResultAndCache($strUrl, $request, $cacheName, 'records');
                $result = json_decode($result);
                $total = intval($result->totalCount);
                if ($total < ($page * 100)) {
                    $hasNextPage = false;
                }
                $page++;
                $data = $result->records;
                foreach ($data as $v) {
                    $strMerID = intval($v->id->crv);
                    if (empty($strMerID)) {
                        continue;
                    }
                    echo "$strMerID\t";

                    $strMerName = trim($this->oLinkFeed->ParseStringBy2Tag($v->name->dv, '">', "</a>"));
                    if ($strMerName === false) {
                        break;
                    }

                    $program_info[$strMerID]["Partnership"] = 'Active';

                    $arr_prgm[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                        'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                        'IdInAff' => $strMerID,
                        'Partnership' => 'Active',                              //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                    );

                    if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                        $desc = trim($this->oLinkFeed->ParseStringBy2Tag($v->name->dv, 'uitkHiddenInGridView\">', "</p>"));
                        $LogoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($v->id->dv, '<img src="', '"'));
                        $CreateDate = trim($v->launchDate->dv);
                        if ($CreateDate) {
                            $CreateDate = date("Y-m-d H:i:s", strtotime(str_replace(",", "", $CreateDate)));
                        }

                        $RankInAff = intval($v->irrating->crv);

                        $prgm_url = "https://app.impact.com/secure/directory/campaign.ihtml?d=lightbox&n=footwear+etc&c=$strMerID";
                        $detailCacheName = "{$new_user_id}_program_detail_{$strMerID}_" . date('YmdH') . '.cache';
                        $prgm_detail = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request, $detailCacheName, 'Impact');

                        $CommissionExt = "";
                        preg_match_all('/notificationItem">(.*?)<\/li>/', $prgm_detail, $CommissionExt);
                        $size = sizeof(isset($CommissionExt[1]) ? $CommissionExt[1] : array());
                        if ($size > 0) {
                            unset($CommissionExt[1][$size - 1]);
                            $CommissionExt = strip_tags(implode('|', $CommissionExt[1]));
                        } else {
                            $CommissionExt = '';
                        }

                        preg_match('/id="serviceAreas".*?>(.*?)<\/div>/', $prgm_detail, $TargetCountryExt);
                        $TargetCountryExt = isset($TargetCountryExt[1]) ? $TargetCountryExt[1] : "";
                        $CategoryExt = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('<span class="uitkDisplayTooltip uitkImageTooltip normalText">', 'onclick="parent.Ext.WindowMgr.getActive().hide()', '">'), "<"));
                        $CategoryExt = str_replace(',', EX_CATEGORY, $CategoryExt);
                        $JoinDate = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, 'Active Since', '<'));
                        if ($JoinDate) {
                            $JoinDate = date("Y-m-d H:i:s", strtotime(trim($JoinDate)));
                        }

                        preg_match('/id="dirPubDesc".*?>(.*?)<\/p>/', $prgm_detail, $desc);
                        $desc = isset($desc[1]) ? $desc[1] : '';
                        $Homepage = "";
                        preg_match("/<a href=(\"|')([^\"']*)\\1.*?>Company Home Page/i", $prgm_detail, $m);
                        if (count($m) && strlen($m[2])) {
                            $Homepage = trim($m[2]);
                        }

                        $attrStr = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('class="campaignAttributesList"', '>'), '</ul');
                        $attrStr = preg_replace('@>\s+<@', '><', $attrStr);
                        $attrArr = explode('</li><li', $attrStr);
                        preg_match('@span class="uitkCheck([a-zA-Z]+)"@', $attrArr[7], $deep);
                        $supportDeepLink = $deep[1] == 'True' ? 'YES' : 'NO';

                        if (isset($program_info[$strMerID]['supportType']) && !empty($program_info[$strMerID]['supportType'])) {
                            if (stripos($program_info[$strMerID]['supportType'], $siteType) === false) {
                                $supportType = $program_info[$strMerID]['supportType'] . ',' . $siteType;
                            } else {
                                $supportType = $program_info[$strMerID]['supportType'];
                            }
                        } else {
                            $supportType = $siteType;
                        }

                        $program_info[$strMerID]["supportType"] = $supportType;

                        $arr_prgm[$strMerID] += array(
                            'CrawlJobId' => $this->info['crawlJobId'],
                            "Name" => addslashes(html_entity_decode($strMerName)),
                            "Homepage" => addslashes($Homepage),
                            "CategoryExt" => addslashes($CategoryExt),
                            "CreateDate" => $CreateDate,
                            "JoinDate" => $JoinDate,
                            "CommissionExt" => $CommissionExt,
                            "Description" => addslashes($desc),
                            "LogoUrl" => addslashes($LogoUrl),
                            "TargetCountryExt" => addslashes($TargetCountryExt),
                            "RankInAff" => $RankInAff,
                            "SupportDeepUrl" => $supportDeepLink,
                            "SupportType" => $supportType,
                            "Contacts" => isset($concat_arr[$strMerID]["Contacts"]) ? $concat_arr[$strMerID]["Contacts"] : ''
                        );
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
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }

            echo "\tGet $siteType site Program by page end\r\n";
        }

        echo "\tGet Program by page end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 1, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = $program_id_list = array();
        $program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        $sql = "SELECT idinaff FROM batch_program_account_site_58 WHERE BatchID = ".intval($this->info["batchID"])." AND AccountSiteID = " . intval($this->info['AccountSiteID']) . " AND Partnership = 'Active'";
        $prgm = $objProgram->objMysql->getRows($sql, 'idinaff');
        $arr_merchant_id_list = array_keys($prgm);
        $arr_merchant_id_list = array_flip($arr_merchant_id_list);

        foreach ($this->siteArr as $val) {
            $AccountSid = $val['API_SID_58'];
            $AccountToken = $val['API_TOKEN_58'];

            $hasNextPage = true;
            $perPage = 100;
            $page = 1;
            $this->oLinkFeed->clearHttpInfos($this->info["AccountSiteID"]);
            while ($hasNextPage) {
                $strUrl = "https://{$AccountSid}:{$AccountToken}@api.impactradius.com/2010-09-01/Mediapartners/{$AccountSid}/Campaigns.json?PageSize={$perPage}&Page=$page";
                $apiCacheName = "{$AccountSid}_api_list_{$page}" . date('YmdH') . '.cache';
                $result = $this->oLinkFeed->GetHttpResultAndCache($strUrl, $request, $apiCacheName);
                $result = json_decode($result, true);
                $page++;

                $numReturned = intval($result['@numpages']);
                if (!$numReturned) break;
                if ($page > $numReturned) {
                    $hasNextPage = false;
                }

                if (isset($result['Campaigns'])) {
                    $mer_list = $result['Campaigns'];
                } elseif (isset($result['Campaign'])) {
                    $mer_list = $result['Campaign'];
                } else {
                    print_r($result);
                    mydie("Get program from api failed!");
                }

                foreach ($mer_list as $v) {
                    $strMerID = intval($v['CampaignId']);
                    if (!$strMerID || isset($program_id_list[$strMerID]) || !isset($arr_merchant_id_list[$strMerID])) {
                        continue;
                    }

                    $program_id_list[$strMerID] = '';

                    $arr_prgm[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],
                        'BatchID' => $this->info['batchID'],
                        'IdInAff' => $strMerID,
                        "AffDefaultUrl" => addslashes($v['TrackingLink'])
                    );
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
        }

        echo "tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

}