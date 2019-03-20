<?php
require_once 'text_parse_helper.php';

class LinkFeed_12_Link_Connector
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->active_programs = array();
        $this->isFull = true;
    }

    function LoginIntoAffService()
    {
        //get para __VIEWSTATE and then process default login
        if (!isset($this->info["LoginPostStringOrig"])) $this->info["LoginPostStringOrig"] = $this->info["LoginPostString"];
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "post",
            "postdata" => "",
        );

        $strUrl = $this->info["LoginUrl"];
        $r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
        $result = $r["content"];
        $arr_hidden_name = array(
            "curdate" => "",
            "loginkey" => "",
        );
        $pattern = "/<input type=\\\"hidden\\\" name=\\\"(.*?)\\\" value=\\\"(.*?)\\\">/iu";
        if (!preg_match_all($pattern, $result, $matches)) mydie("die: LoginIntoAffService failed curdate not found\n");

        foreach ($matches[1] as $i => $name) {
            if (isset($arr_hidden_name[$name])) $arr_hidden_name[$name] = $matches[2][$i];
        }
        foreach ($arr_hidden_name as $name => $value) {
            if (empty($value)) mydie("die: LoginIntoAffService failed $name not found\n");
        }

        $this->getLoginCheckCode($arr_hidden_name);

        $arr_replace_from = array();
        $arr_replace_to = array();
        foreach ($arr_hidden_name as $name => $value) {
            $arr_replace_from[] = "{" . $name . "}";
            $arr_replace_to[] = $value;
        }

        $this->info["LoginPostString"] = str_replace($arr_replace_from, $arr_replace_to, $this->info["LoginPostStringOrig"]);
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info, 2, true, true, false);
        return "stophere";
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
        echo "\tGet Program by page start" . PHP_EOL;
        $objProgram = new ProgramDb();
        $program_num = $base_program_num = 0;
        $arr_prgm = array();

        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => "",);
        $request_detail = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);

        //step 1, login
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info, 1, false);

        $arrStatus4List = array("Sum", "Pending");
        foreach ($arrStatus4List as $status) {
            echo "get $status merchants for LC\n";

            $nNumPerPage = 100;
            $bHasNextPage = true;
            $nPageNo = 1;

            $strUrl = "https://www.linkconnector.com/member/amerchants.htm?Type=" . $status;
            while ($bHasNextPage) {

                $request["postdata"] = "refreshvariable=true&Page=" . $nPageNo . "&s_sort=&s_order=&ddMerchants=&ddCampaignStatus=Active&ddDisplay=" . $nNumPerPage . "&ddDisplay=" . $nNumPerPage;
                $r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
                $result = $r["content"];
                $result = @mb_convert_encoding($result, "UTF-8", mb_detect_encoding($result));

                print "Get $status Merchant List : Page: $nPageNo  <br>\n";

                //parse HTML
                $nLineStart = 0;
                $nTotalPage = $this->oLinkFeed->ParseStringBy2Tag($result, array('per page | Page:', '&nbsp;&nbsp;of '), '</td>', $nLineStart);
                if ($nTotalPage === false) {
                    mydie("die: nTotalPage not found\n");
                }
                $nTotalPage = intval($nTotalPage);
                if ($nTotalPage < $nPageNo) {
                    break;
                }

                $nLineStart = 0;
                $nTmpNoFound = stripos($result, 'No Records Found', $nLineStart);
                if ($nTmpNoFound !== false) {
                    break;
                }

                $strLineStart = '<tr class="lcTable lcTableReport tblRow';
                $nLineStart = 0;
                while ($nLineStart >= 0) {
                    $nLineStart = stripos($result, $strLineStart, $nLineStart);
                    if ($nLineStart === false) {
                        break;
                    }

                    $strMerName = $this->oLinkFeed->ParseStringBy2Tag($result, '<td style="text-align:center;" class="lcTable lcTableReport tblCellFirst">', '</td>', $nLineStart);
                    if ($strMerName === false) {
                        break;
                    }
                    $strMerName = html_entity_decode(trim($strMerName));

                    //category
                    $CategoryExt = isset($mer_cat[$strMerName]) ? $mer_cat[$strMerName] : "";

                    //ID
                    $strCampID = $this->oLinkFeed->ParseStringBy2Tag($result, 'campaigns.htm?cid=', '&mid=', $nLineStart);
                    if ($strCampID === false) {
                        break;
                    }
                    $strCampID = trim($strCampID);

                    $strMerID = $this->oLinkFeed->ParseStringBy2Tag($result, '&mid=', "',", $nLineStart);
                    if ($strMerID === false) {
                        break;
                    }
                    $strMerID = trim($strMerID);
                    if ($strMerID == "") {
                        echo "warning: strMerID not found\n";
                        continue;
                    }

                    $mer_detail_url = "https://www.linkconnector.com/member/campaigns.htm?cid=$strCampID&mid=$strMerID";

                    $strMerID = $strMerID . '_' . $strCampID;

                    if ($strMerID == '151970_6272') {
                        $strMerName = str_replace('?', ' ', $strMerName);
                    }

                    if ($status == "Sum") {
                        $this->active_programs[] = $strMerID;
                    }

                    if ($status == "Pending") {
                        if (!empty($this->active_programs) && in_array($strMerID, $this->active_programs)) {
                            echo sprintf("program id: %s, name: %s is in active program list and ignore.\n", $strMerID, $strMerName);
                            continue;
                        }
                        $Partnership = 'Pending';
                        $StatusInAff = "Active";
                    } elseif ($status == "Declined") {
                        $Partnership = 'Declined';
                        $StatusInAff = "Active";
                    } elseif ($status == "Dropped") {
                        $Partnership = 'NoPartnership';
                        $StatusInAff = "Offline";
                    } elseif ($status == "Sum") {
                        $Partnership = 'Active';
                        $StatusInAff = "Active";
                    } else {
                        mydie("die: wrong status($status)");
                    }

                    $arr_prgm[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],
                        'BatchID' => $this->info['batchID'],
                        'IdInAff' => $strMerID,
                        'Partnership' => $Partnership
                    );

                    if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                        $strCampName = $this->oLinkFeed->ParseStringBy2Tag($result, array('OnMouseOut', '">'), '</a>', $nLineStart);
                        if ($strCampName === false) {
                            break;
                        }
                        if ($strMerName == "") {
                            echo "warning: strMerName not found\n";
                            continue;
                        }

                        $strCampName = html_entity_decode(trim($strCampName));
                        $strMerName = $strMerName . ' - ' . $strCampName;

                        $strEPC = $strEPC90d = -1;
                        $strEvents = "";

                        if ($status == "Sum") {
                            $tofind = '<td style="text-align:center;white-space:nowrap" class="lcTable lcTableReport">';
                            $strEvents = $this->oLinkFeed->ParseStringBy2Tag($result, $tofind, '</td>', $nLineStart);
                            if ($strEvents === false) {
                                echo "warning: strEvents not found\n";
                                continue;
                            }
                            $strEPC = $this->oLinkFeed->ParseStringBy2Tag($result, $tofind, '</td>', $nLineStart);
                            if ($strEPC === false) {
                                echo "warning: strEPC not found\n";
                                continue;
                            }

                            $strEPC90d = $this->oLinkFeed->ParseStringBy2Tag($result, $tofind, '</td>', $nLineStart);
                            if ($strEPC90d === false) {
                                echo "warning: strEPC30d not found\n";
                                continue;
                            }

                            $strEPC = trim($strEPC);
                            $strEPC90d = trim($strEPC90d);
                        }

                        $CommissionExt = trim($strEvents);
                        $EPCDefault = $strEPC;
                        $EPC90d = $strEPC90d;

                        $cache_name = 'detail_page_' . $strMerID . '_' . date('Ymd') . '.cache';
                        $prgm_detail = $this->oLinkFeed->GetHttpResultAndCache($mer_detail_url, $request, $cache_name);

                        $prgm_line = 0;
                        $prgm_campname = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Campaign:', '<td style="font-weight:bold;text-align:left" class="lcTable lcTableForm tblCellLast">'), '</td>', $prgm_line);
                        $prgm_camptype = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Campaign Type:', '<td style="font-weight:bold;text-align:left" class="lcTable lcTableForm tblCellLast">'), '</td>', $prgm_line);
                        $Homepage = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Website:', '<td style="font-weight:bold;text-align:left" class="lcTable lcTableForm tblCellLast">'), '</td>', $prgm_line);
                        $JoinDate = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Start Date:', '<td style="font-weight:bold;text-align:left" class="lcTable lcTableForm tblCellLast">'), '</td>', $prgm_line));
                        if ($JoinDate) {
                            $JoinDate_tmp = $JoinDate;
                            $JoinDate = substr($JoinDate_tmp, 6, 4) . "-" . substr($JoinDate_tmp, 0, 2) . "-" . substr($JoinDate_tmp, 3, 2) . " " . "00:00:00";
                        }

                        $prgm_status = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Status:', '<td style="font-weight:bold;text-align:left" class="lcTable lcTableForm tblCellLast">'), '</td>', $prgm_line);
                        $desc = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, "<span style='font-weight:bold'>Description: </span>", '</td>', $prgm_line));
                        $TermAndCondition = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array("<span style='font-weight:bold'>Campaign Terms and Conditions: </span>", '<table style="margin:8px 0px">'), '</table>', $prgm_line));
                        $ReturnDays = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Expire Tracking', "<div style='border:none;'>"), '</div>', $prgm_line);

                        $SEMPolicyExt = "";
                        $sem_tmp = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Search Engine Marketing Allowed:', '<td style="vertical-align:top;">'), '</td>');
                        if ($sem_tmp) {
                            $SEMPolicyExt = "Search Engine Marketing Allowed:" . $sem_tmp;
                        }
                        $sem_tmp = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Search Engine Marketing Restrictions:', '<td style="vertical-align:top;">'), '</td>');
                        if ($sem_tmp) {
                            $SEMPolicyExt .= "Search Engine Marketing Restrictions:" . $sem_tmp;
                        }
                        if (stripos($prgm_detail, 'International Traffic Allowed') !== false || stripos($prgm_detail, 'International Traffic Welcome') !== false)
                            $TargetCountryExt = 'Global';
                        else
                            $TargetCountryExt = '';

                        $arr_prgm[$strMerID] += array(
                            'CrawlJobId' => $this->info['crawlJobId'],
                            "Name" => addslashes(html_entity_decode(trim($strMerName))),
                            "StatusInAff" => $StatusInAff,                        //'Active','TempOffline','Offline'
                            "StatusInAffRemark" => addslashes($status),
                            "JoinDate" => $JoinDate,
                            "CategoryExt" => $CategoryExt,
                            "SEMPolicyExt" => addslashes($SEMPolicyExt),
                            "Description" => addslashes($desc),
                            "Homepage" => addslashes($Homepage),
                            "CommissionExt" => addslashes($CommissionExt),
                            "EPCDefault" => addslashes(preg_replace("/[^0-9.]/", "", $EPCDefault)),
                            "EPC90d" => addslashes(preg_replace("/[^0-9.]/", "", $EPC90d)),
                            "CookieTime" => $ReturnDays,
                            "TermAndCondition" => addslashes($TermAndCondition),
                            "TargetCountryExt" => $TargetCountryExt,
                        );
                        $base_program_num ++;
                    }

                    $program_num ++;
                    if (count($arr_prgm) >= 200) {
                        $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                        $arr_prgm = array();
                    }
                }
                if (count($arr_prgm)) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }

                $nPageNo++;
                if ($nTotalPage < $nPageNo) {
                    break;
                }
            }
        }

        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.");
        }
        echo "\tUpdate ({$base_program_num}) base programs." . PHP_EOL;
        echo "\tUpdate ({$program_num}) site programs." . PHP_EOL;
    }

    function getLoginCheckCode(&$arr)
    {
        $t2 = strrev("123" . $arr["loginkey"] . $arr["curdate"]);
        $t = "";
        for($i=0;$i<strlen($t2);$i+=3)  $t .= $t2[$i];
        for($i=0;$i<strlen($t2);$i+=2)  $t .= $t2[$i];
        $arr["dest"] = substr($t,0,32);
    }

}

?>