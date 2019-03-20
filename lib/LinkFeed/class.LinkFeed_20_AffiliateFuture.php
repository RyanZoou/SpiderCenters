<?php

class LinkFeed_20_AffiliateFuture
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
        $this->oLinkFeed->clearHttpInfos($this->info["AccountSiteID"]);
        $this->isFull = $this->info['isFull'];
        $this->GetProgramByPage();
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function LoginIntoAffService()
    {
        //get para __VIEWSTATE and then process default login
        if (!isset($this->info["LoginPostStringOrig"])) $this->info["LoginPostStringOrig"] = $this->info["LoginPostString"];
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => "",);
        if (isset($this->info["loginUrl"])) {
            $this->info["LoginUrl"] = $this->info["loginUrl"];
        }
        $strUrl = $this->info["LoginUrl"];

        echo "login url:" . $strUrl . "\r\n";

        $r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
        $result = $r["content"];

        $this->info["referer"] = $strUrl;

        if (isset($this->info["loginUrl"])) {
            if (!preg_match('@id="__VIEWSTATE" value="(.*?)".*?id="__VIEWSTATEGENERATOR" value="(.*?)"@ms', $result, $g))
                mydie("die: login for LinkFeed_20_AFFF_US failed, param not found\n");
            $this->info["LoginPostString"] = sprintf('__VIEWSTATE=%s&__VIEWSTATEGENERATOR=%s&%s', urlencode($g[1]), urlencode($g[2]), $this->info["LoginPostStringOrig"]);
        } else {
            if (!preg_match('@id="__VIEWSTATE" value="(.*?)".*?id="__VIEWSTATEGENERATOR" value="(.*?)".*?id="__EVENTVALIDATION" value="(.*?)"@ms', $result, $g))
                mydie("die: login for LinkFeed_20_AFFF_US failed, param not found\n");
            $this->info["LoginPostString"] = sprintf('__VIEWSTATE=%s&__VIEWSTATEGENERATOR=%s&__EVENTVALIDATION=%s&%s', urlencode($g[1]), urlencode($g[2]), urlencode($g[3]), $this->info["LoginPostStringOrig"]);
        }

        if (preg_match('@id="__EVENTTARGET" value="(.*?)"@ms', $result, $g))
            $this->info['LoginPostString'] .= '&__EVENTTARGET=' . urlencode($g[1]);
        if (preg_match('@id="__EVENTARGUMENT" value="(.*?)"@ms', $result, $g))
            $this->info['LoginPostString'] .= '&__EVENTARGUMENT=' . urlencode($g[1]);
        if (preg_match('@id="topinclude$txtUsername" value="(.*?)"@ms', $result, $g))
            $this->info['LoginPostString'] .= '&topinclude$txtUsername=' . urlencode($g[1]);
        if (preg_match('@id="topinclude$txtPassword" value="(.*?)"@ms', $result, $g))
            $this->info['LoginPostString'] .= '&topinclude$txtPassword=' . urlencode($g[1]);

        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info, 6, true, true, false);
        return "stophere";
    }

    function GetProgramByPage()
    {
        echo "\tGet Program by page start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = $base_program_num = 0;

        $prgm_default_url = $strMerID_arr = array();

        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => "",);

        //login af.affiliates
        $tmp_info = $this->info;
        $this->info["loginUrl"] = "http://af.affiliates.affiliatefuture.com/login.aspx";
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info, 1, false);
        $r = $this->oLinkFeed->GetHttpResult("http://af.affiliates.affiliatefuture.com/programmes/MerchantsJoined.aspx", $request);
        $result = $r["content"];
        preg_match_all("/http:\/\/scripts\.affiliatefuture\.com.*merchantID=(\d+).+programmeID=(\d+).+&url=/i", $result, $m);
        if (count($m)) {
            foreach ($m[2] as $k => $v) {
                if (strlen($m[0][$k])) {
                    $prgm_default_url[$v] = $m[0][$k];
                }
                if (strlen($m[1][$k])) {
                    $strMerID_arr[$v] = $m[1][$k];
                }
            }
        }

        //login
        $this->oLinkFeed->clearHttpInfos($this->info["AccountSiteID"]);
        $this->info = $tmp_info;
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info, 1, false);

        // for get new program, ignore isset program
        $old_prgm = array();

        $r = $this->oLinkFeed->GetHttpResult("http://affiliates.affiliatefuture.com/myprogrammes/default.aspx", $request);
        $result = $r["content"];
        $strLineStart = '<td>Merchant</td><td>Programme Name</td>';
        $nLineStart = stripos($result, $strLineStart, 0);

        $strLineStart = '<tr style="color:Black;';
        while ($nLineStart >= 0) {
            $nLineStart = stripos($result, $strLineStart, $nLineStart);
            if ($nLineStart === false) {
                break;
            }

            $StatusInAffRemark = trim($this->oLinkFeed->ParseStringBy2Tag($result, 'background-color:', ';', $nLineStart));
            if ($StatusInAffRemark == "White") {
                $StatusInAff = "Active";
            } elseif ($StatusInAffRemark == "Red") {
                $StatusInAff = "Offline";
            } else {
                break;
            }

            $strMerName = trim($this->oLinkFeed->ParseStringBy2Tag($result, 'NAME="Hyperlink1">', "</span>", $nLineStart));
            if ($strMerName === false) {
                continue;
            }

            $prgm_id = intval($this->oLinkFeed->ParseStringBy2Tag($result, array('NAME="Hyperlink2" href="MerchantProgramme.aspx?id='), "\"", $nLineStart));
            if ($prgm_id === false) {
                continue;
            }

            $AffDefaultUrl = '';
            if (count($prgm_default_url)) {
                $AffDefaultUrl = isset($prgm_default_url[$prgm_id]) ? addslashes($prgm_default_url[$prgm_id]) : "";
            }

            $arr_prgm[$prgm_id] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $prgm_id,
                'Partnership' => "Active",
                'AffDefaultUrl' => addslashes($AffDefaultUrl)
            );
            
            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $prgm_id, $this->info['crawlJobId'])) {
                $LogoUrl = '';
                $TermAndCondition = '';
                $strMerID = '';
                if (isset($strMerID_arr[$prgm_id])) {
                    $strMerID = $strMerID_arr[$prgm_id];
                    $mer_url = "http://affiliates.affiliatefuture.com/merchants/AddProgramme.aspx?cat=&id=$strMerID";
                    $mer_arr = $this->oLinkFeed->GetHttpResult($mer_url, $request);
                    $mer_detail = $mer_arr["content"];
                    $TermAndCondition = trim($this->oLinkFeed->ParseStringBy2Tag($mer_detail, array('<span id="Description">', '<b>'), '</span>'));
                    $LogoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($mer_detail, '<img id="Image1" src="', '"'));
                }

                $prgm_url = "http://affiliates.affiliatefuture.com/myprogrammes/MerchantProgramme.aspx?id=$prgm_id";
                $prgm_arr = $this->oLinkFeed->GetHttpResult($prgm_url, $request);
                $prgm_detail = $prgm_arr["content"];

                $Homepage = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Hyperlink1', 'href="'), '">'));
                $desc_PPC = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<span id="MerchantDescription">', '</span>'));
                if (strpos($desc_PPC, '<b>PPC Policy:</b>')){
                	$desc_PPC = explode('<b>PPC Policy:</b>', $desc_PPC);
                	$desc = trim($desc_PPC[0]);
                	$SEMPolicyExt = trim($desc_PPC[1]);
                }else {
                	$desc = $desc_PPC;
                	$SEMPolicyExt = '';
                }
                $CommissionExt = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<span id="Offer">', '</span>'));

                $arr_prgm[$prgm_id] += array(
                    "CrawlJobId" => $this->info['crawlJobId'],
                    "Name" => addslashes($strMerName),
                    "Homepage" => addslashes($Homepage),
                    "Description" => addslashes($desc),
                    "CommissionExt" => addslashes($CommissionExt),
                    "StatusInAffRemark" => addslashes($StatusInAffRemark),
                    "StatusInAff" => $StatusInAff,
                    "TermAndCondition" => addslashes($TermAndCondition),
                    "LogoUrl" => addslashes($LogoUrl),
                	"SEMPolicyExt" => addslashes($SEMPolicyExt)
                );

                if (!empty($strMerID)) {
                    $old_prgm[$strMerID] = 1;
                }
                $base_program_num ++;
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


        $r = $this->oLinkFeed->GetHttpResult("http://affiliates.affiliatefuture.com/merchants/categoryListing.aspx", $request);
        $result = $r["content"];
        $strLineStart = 'Merchant</span>:</b>';

        $nLineStart = 0;
        while ($nLineStart >= 0) {
            $nLineStart = stripos($result, $strLineStart, $nLineStart);
            if ($nLineStart === false) {
                break;
            }

            $strMerName = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('<span id="datalist', 'Name2">'), "</span>", $nLineStart));
            if ($strMerName === false) {
                break;
            }

            $Homepage = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('Site</span>:</b>', '<a', '>'), '</a>', $nLineStart));
            $desc = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('Merchant Description</span></b>', 'Description2">'), '</span>', $nLineStart));

            $tmp_Partnership = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('lnkSubscribe"', '">'), '</a>', $nLineStart));
            $Partnership = "NoPartnership";
            if ($tmp_Partnership == "SUBSCRIBED") {
                $Partnership = "Active";
            } elseif ($tmp_Partnership == "JOIN PROGRAMME") {
                $Partnership = "NoPartnership";
            }

            //only get no partnership merchant
            if ($tmp_Partnership != "JOIN PROGRAMME") {
                continue;
            }

            $strMerID = intval($this->oLinkFeed->ParseStringBy2Tag($result, array('Hyperlink4"', 'id='), "\"", $nLineStart));
            if ($strMerID === false) {
                break;
            }

            // for get new program, ignore isset program
            if (isset($old_prgm[$strMerID])) {
                continue;
            }

            $mer_url = "http://affiliates.affiliatefuture.com/merchants/AddProgramme.aspx?cat=&id=$strMerID";
            $mer_arr = $this->oLinkFeed->GetHttpResult($mer_url, $request);
            $mer_detail = $mer_arr["content"];
            $prgm_id = intval($this->oLinkFeed->ParseStringBy2Tag($mer_detail, 'href="Creatives.aspx?id=', '"'));
            if (!$prgm_id) {
                continue;
            }

            $AffDefaultUrl = '';
            if (count($prgm_default_url)) {
                $AffDefaultUrl = isset($prgm_default_url[$prgm_id]) ? addslashes($prgm_default_url[$prgm_id]) : "";
            }

            $arr_prgm[$prgm_id] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $prgm_id,
                'Partnership' => $Partnership,
                'AffDefaultUrl' => addslashes($AffDefaultUrl)
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $prgm_id, $this->info['crawlJobId'])) {
                $TermAndCondition = trim($this->oLinkFeed->ParseStringBy2Tag($mer_detail, array('<span id="Description">', '<b>'), '</span>'));
                $LogoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($mer_detail, '<img id="Image1" src="', '"'));
                $CommissionExt = trim($this->oLinkFeed->ParseStringBy2Tag($mer_detail, '<span id="datalist1_ctl00_OfferDetails">', '</span>'));

                $arr_prgm[$prgm_id] += array(
                    "CrawlJobId" => $this->info['crawlJobId'],
                    "Name" => addslashes($strMerName),
                    "Homepage" => addslashes($Homepage),
                    "Description" => addslashes($desc),
                    "StatusInAff" => 'Active',                            //'Active','TempOffline','Offline'
                    "CommissionExt" => addslashes($CommissionExt),
                    "TermAndCondition" => addslashes($TermAndCondition),
                    "LogoUrl" => addslashes($LogoUrl)
                );
                $base_program_num ++;
            }

            $program_num++;
            if (count($arr_prgm) >= 100) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }
        }

        if (count($arr_prgm)) {
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }

        echo "\tGet Program by page end\r\n";
        if ($program_num < 1) {
            mydie("die: program count < 1, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }

}