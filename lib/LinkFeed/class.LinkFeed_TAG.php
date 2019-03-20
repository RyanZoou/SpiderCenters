<?php
require_once 'text_parse_helper.php';
class LinkFeed_TAG
{
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
        echo "\tGet Program by Page start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = $base_program_num = 0;
        $record_arr = array();
        $ppc_needle = "The following conditions apply to affiliates who wish to engage in PPC activity:";

        //step 1,login
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);
        $request_detail = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "post",
            "postdata" => "categoryId=-1&programName=&merchantName=&records=-1&p=&time=1&changePage=&oldColumn=programmeId&sortField=programmeId&order=down",
        );
        $r = $this->oLinkFeed->GetHttpResult("https://{$this->domain}/affiliate_directory.html",$request);
        $result = $r["content"];

        $title = 'PIDProgramNameMIDMerchantNameCategoryCommissionRateCookieDurationCoolingPeriodAverageApprovalStatus';
        preg_match("/Affiliate Programs Directory.*?(<th.*?)<\/tr/is", $result, $m);
        $tmp_arr = explode("</th>",$m[1]);
        $tmp_title = '';
        foreach($tmp_arr as $v){
            $v = preg_replace("/\s/", '', strip_tags($v));
            if($v){
                $tmp_title .= $v;
            }
        }
        if($title != $tmp_title){
            mydie("die: Title Wrong $title | $tmp_title .\n");
        }

        $strLineStart = '<th>Cookie Duration</th>';

        $nLineStart = 0;
        while ($nLineStart >= 0){
            $nLineStart = stripos($result, $strLineStart, $nLineStart);
            if ($nLineStart === false) {
                break;
            }

            $strLineStart = "<tr";
            $strMerID = trim($this->oLinkFeed->ParseStringBy2Tag($result, "<td>", "</td>", $nLineStart));
            if ($strMerID === false) {
                break;
            }

            echo $strMerID . "\t";
            if (isset($record_arr[$strMerID])) {
                continue;
            }

            $strMerName = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($result, '<td>' , "</td>", $nLineStart)));
            if ($strMerName === false) {
                break;
            }

            $tmp = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($result, '<td>' , "</td>", $nLineStart)));
            if ($tmp === false) {
                break;
            }

            $tmpName = trim($this->oLinkFeed->ParseStringBy2Tag($result, '<td>' , "</td>", $nLineStart));
            if ($tmpName === false) {
                break;
            }

            $program_name = $strMerName." - ".$tmpName;

            $CategoryExt = trim($this->oLinkFeed->ParseStringBy2Tag($result, '<td>' , "</td>", $nLineStart));
            $CommissionExt  = trim($this->oLinkFeed->ParseStringBy2Tag($result, '<td>' , "</td>", $nLineStart));
            $CookieTime  = trim($this->oLinkFeed->ParseStringBy2Tag($result, '<td>' , "</td>", $nLineStart));
            $CookieTime2  = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('<td>', '<td>') , "</td>", $nLineStart));

            $StatusInAffRemark = trim($this->oLinkFeed->ParseStringBy2Tag($result, '<td>' , "</td>", $nLineStart));
            if($StatusInAffRemark == "Approved"){
                $Partnership = "Active";
            }elseif($StatusInAffRemark == "Pending"){
                $Partnership = "Pending";
            }elseif($StatusInAffRemark == "Declined"){
                $Partnership = "Declined";
            }else{
                $Partnership = "NoPartnership";
            }

            $prgm_url = "https://{$this->domain}/affiliate_program_detail.html?pId=$strMerID";
            $cache_name = "program_detail_{$strMerID}_" . date('Ymd');
            $prgm_detail = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request_detail, $cache_name);
            $AffDefaultUrl = trim(htmlspecialchars_decode($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('id="trackingString"', '>'), "</")));

            $arr_prgm[$strMerID] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $strMerID,
                'Partnership' => $Partnership,
                "AffDefaultUrl" => addslashes($AffDefaultUrl),
            );
            $record_arr[$strMerID] = 1;

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                $desc = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Program Description', '<div class="value w70 htmlDescription">'), "</div>"));
                $Homepage = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Program Landing URL', 'opennw(\''), "'")));
                $TermAndCondition = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Policy / Terms', '<div class="value w70 htmlDescription">'), "</div>"));
                $SEMPolicyExt = "";
                if (strpos($TermAndCondition, $ppc_needle)){
                	$SEMPolicyExt = trim(substr($TermAndCondition,strpos($TermAndCondition, $ppc_needle)));
                }
                $LogoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('<div class="sideLogo" >', '<img src="'), '"'));
                $Homepage = str_ireplace("?sourcecode=TAG", "", $Homepage);

                $arr_prgm[$strMerID] += array(
                    'CrawlJobId' => $this->info['crawlJobId'],
                    "Name" => addslashes($program_name),
                    "Homepage" => addslashes($Homepage),
                    "CategoryExt" => addslashes($CategoryExt),
                    "CommissionExt" => addslashes($CommissionExt),
                    "StatusInAffRemark" => addslashes($StatusInAffRemark),
                    "Description" => addslashes($desc),
                    "TermAndCondition" => addslashes($TermAndCondition),
                    "LogoUrl" => addslashes($LogoUrl),
                    "CookieTime" => addslashes($CookieTime),
                	"SEMPolicyExt" => addslashes($SEMPolicyExt)
                );
                $base_program_num++;
            }

            $program_num++;
            if(count($arr_prgm) >= 100){
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }
        }
        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }

        echo "\n\tGet Program by Page end\r\n";
        if ($program_num < 1) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }


}
