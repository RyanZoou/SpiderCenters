<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_52_TradeTracker
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

        $client  = new SoapClient("http://ws.tradetracker.com/soap/affiliate?wsdl", array('trace'=> true));
        $client->authenticate($this->info['APIKey2'], $this->info['APIKey3']);

        foreach ($client->getCampaigns($this->info['APIKey1'], '') as $prgm) {
            $strMerID = $prgm->ID;
            if(!$strMerID) {
                continue;
            }

            $Partnership = "NoPartnership";
            $StatusInAffRemark = $prgm->info->assignmentStatus;
            if($StatusInAffRemark == 'accepted'){
                $Partnership = 'Active';
            }elseif($StatusInAffRemark == 'rejected'){
                $Partnership = 'Declined';
            }elseif($StatusInAffRemark == 'pending'){
                $Partnership = 'Pending';
            }
            $AffDefaultUrl = $prgm->info->trackingURL;

            $arr_prgm[$strMerID] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                'IdInAff' => $strMerID,
                'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                "AffDefaultUrl" => addslashes($AffDefaultUrl),
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {

                $CategoryExt = "";
                if(isset($prgm->info->category)) {
                    $CategoryExt = $prgm->info->category->name;
                }
                $SupportDeepurl = $prgm->info->deeplinkingSupported;
                if ($SupportDeepurl == 1) {
                    $SupportDeepurl = "YES";
                } else {
                    $SupportDeepurl = "NO";
                }

                $CommissionExt = 'Lead:' . $prgm->info->commission->leadCommission . ',Sales:' . $prgm->info->commission->saleCommissionFixed . ',Sales(%):' . $prgm->info->commission->saleCommissionVariable;
                $LogoUrl = $prgm->info->imageURL;

                $arr_prgm[$strMerID] += array(
                    'CrawlJobId' => $this->info['crawlJobId'],
                    "Name" => addslashes($prgm->name),
                    "Homepage" => addslashes($prgm->URL),
                    "CategoryExt" => addslashes($CategoryExt),
                    "Description" => addslashes($prgm->info->campaignDescription),
                    "CreateDate" => $prgm->info->startDate,
                    "StatusInAffRemark" => addslashes($StatusInAffRemark),
                    "SupportDeepUrl" => $SupportDeepurl,
                    "CommissionExt" => addslashes($CommissionExt),
                    "TermAndCondition" => addslashes($prgm->info->characteristics),
                    "LogoUrl" => addslashes($LogoUrl),
                    'PublisherPolicy' => addslashes($prgm->info->policyDiscountCodeStatus),
                	"SEMPolicyExt" => addslashes($prgm->info->policySearchEngineMarketingStatus)
                );
                $base_program_num ++;
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

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }


}
