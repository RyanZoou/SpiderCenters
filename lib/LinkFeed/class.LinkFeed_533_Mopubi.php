<?php
class LinkFeed_533_Mopubi
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
        $this->GetProgramFromPage();
        echo "Craw Program end @ ".date("Y-m-d H:i:s")."\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function GetProgramFromPage()
    {
        echo "\tGet Program by Page start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = $base_program_num = 0;

        //step 1,login
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);

        //program management adv
        echo "get program \r\n";
        $strUrl = "http://console.mopubi.com/affiliates/Extjs.ashx?s=contracts";
        $hasNextPage = true;
        $page = 1;
        while($hasNextPage){
            echo "\t page $page.\n";
            $postdata = array(
                'groupBy' => '',
                'groupDir' => 'ASC',
                'cu' => 0,
                'c' => '',
                'cat' => 0,
                'sv' => '',
                'cn' => '',
                'pf' => '',
                'st' => 0,
                'm' => '',
                'ct' => '',
                'pmin' => '',
                'pmax' => '',
                'mycurr' => false,
                't' => '',
                'p' => ($page - 1) * 100,
                'n' => 100,
            );
            $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => http_build_query($postdata),);

            $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
            $res = json_decode($r["content"],true);
            //var_dump($res);exit;
            if(($res['total'] - ($page - 1) * 100) < 100)
                $hasNextPage = false;
            $result = $res['rows'];
            foreach($result as $item)
            {
                $strMerID = $item['campaign_id'];
                if (!$strMerID)
                    break;

                $strMerName = trim($item['name']);
                if ($strMerName === false) {
                    break;
                }

                $StatusInAffRemark = trim($item['status']);
                $AffDefaultUrl = $Partnership = '';
                if($StatusInAffRemark == 'Active') {
                    $Partnership = 'Active';
                    $contid = $item['contract_id'];
                    $detailDefaulUrl = "http://console.mopubi.com/affiliates/Extjs.ashx?s=creatives&cont_id=$contid";
                    $request = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "post", "postdata" => "s=creatives&cont_id=$contid", );
                    $detailDefaulUrlFull = $this->oLinkFeed->GetHttpResult($detailDefaulUrl,$request);
                    $detailDefaul = json_decode($detailDefaulUrlFull['content'],true)['rows'];
                    $AffDefaultUrl = $detailDefaul[0]['unique_link'];
                }elseif($StatusInAffRemark == 'Pending'){
                    $Partnership = 'Pending';
                }elseif($StatusInAffRemark == 'Apply To Run' || $StatusInAffRemark == 'Inactive' || $StatusInAffRemark == 'Public'){
                    $Partnership = 'NoPartnership';
                }else{
                    mydie ("die: unknown $strMerName partnership: $StatusInAffRemark.\n");
                }

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $strMerID,
                    'Partnership' => $Partnership,
                    "AffDefaultUrl" => addslashes($AffDefaultUrl),
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    $Homepage = '';
                    if ($Partnership == 'Active') {
                        $contid = $item['contract_id'];
                        $detailUrl = "http://console.mopubi.com/affiliates/Extjs.ashx?s=contract_info&cont_id=$contid";
                        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);
                        $detailFull = $this->oLinkFeed->GetHttpResult($detailUrl, $request);
                        $detail = json_decode($detailFull['content'], true)['rows'];
                        $Homepage = $detail[0]['preview_link'];
                    }

                    $CommissionExt = '';
                    switch ($item['price_format_id']) {
                        case 5 :
                            $CommissionExt = addslashes(trim($item['price_converted']) . "%");
                            break;
                        case 1 :
						case 4 :
                            $CommissionExt = addslashes("$" . trim($item['price_converted']));
                            break;
                        case 2 :
                            $CommissionExt = addslashes("€" . trim($item['price_converted']));
                            break;
                        default :
                        	echo $strMerName."\t".$item['price_format_id']."\t".$item['price_converted']."\n";
                            mydie("There find new currency!");
                    }

                    $arr_prgm[$strMerID] += array(
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Name" => addslashes(html_entity_decode(trim($strMerName))),
                        "StatusInAff" => 'Active',                        //'Active','TempOffline','Offline'
                        "StatusInAffRemark" => $StatusInAffRemark,
                        "CommissionExt" => $CommissionExt,
                        "Homepage" => addslashes($Homepage),
                        "CategoryExt" => addslashes($item['vertical_name']),
                        'PublisherPolicy' => addslashes($item['restrictions']),
                    	'SupportType' => "Content".EX_CATEGORY."Coupon"
                    );
                    $base_program_num ++;
                }

                $program_num++;
                if(count($arr_prgm) >= 100){
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }
            }
            $page++;
            if($page > 300){
                mydie("die: Page overload.\n");
            }
        }
        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }

        echo "\tGet Program by page end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }

        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

}