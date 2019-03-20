<?php
class LinkFeed_2051_Pay4Results_EU
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
        $strUrl = "http://my.pay4results.eu/affiliates/Extjs.ashx?s=contracts";
        $hasNextPage = true;
        $page = 1;
        $arr_prgm = array();
        while($hasNextPage){
            echo "page $page\t";
            $postdata = array(
                'groupBy' => '',
                'groupDir' => 'ASC',
                'cu' => 1,
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
                'mycurr' => true,
                't' => '',
                'p' => ($page - 1) * 100,
                'n' => 100,
            );
            $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => http_build_query($postdata));

            $cache_name = "program_list_page{$page}_" . date('YmdH') . '.cache';
            $r = $this->oLinkFeed->GetHttpResultAndCache($strUrl, $request, $cache_name, 'total');
            $res = json_decode($r,true);
            if(($res['total'] - ($page - 1) * 100) < 100)
                $hasNextPage = false;
            $result = $res['rows'];
            foreach($result as $item)
            {
                $strMerID = $item['campaign_id'];
                if (!$strMerID) {
                    continue;
                }

                $strMerName = trim($item['name']);
                if (!$strMerName) {
                    continue;
                }

                $country = '';
                if (!empty($item['countries']) && $item['countries'][0] != '-1'){
                    foreach ($item['countries'] as $c){
                        $country .= $c . ',';
                    }
                }
                $country = rtrim($country, ',');

                $StatusInAffRemark = trim($item['status']);
                $AffDefaultUrl = $Partnership = '';
                if($StatusInAffRemark == 'Active') {
                    $Partnership = 'Active';
                    $contid = $item['contract_id'];
                    $detailDefaulUrl = "http://my.pay4results.eu/affiliates/Extjs.ashx?s=creatives&cont_id=$contid";
                    $request = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "post", "postdata" => "s=creatives&cont_id=$contid", );
                    $cache_name = "defaultUrl_{$strMerID}_" . date('YmdH') . '.cache';
                    $detailDefaulUrlFull = $this->oLinkFeed->GetHttpResultAndCache($detailDefaulUrl,$request,$cache_name,'unique_link');
                    $detailDefaul = json_decode($detailDefaulUrlFull,true)['rows'];
                    $AffDefaultUrl = $detailDefaul[0]['unique_link'];
                }elseif($StatusInAffRemark == 'Pending'){
                    $Partnership = 'Pending';
                }elseif($StatusInAffRemark == 'Apply To Run' || $StatusInAffRemark == 'Inactive' || $StatusInAffRemark == 'Public'){
                    $Partnership = 'NoPartnership';
                }else{
                    mydie ("die: unknown $strMerName partnership: $StatusInAffRemark.\n");
                }
                if ($Partnership == 'Active' && stripos($strMerName, 'paused') !== false) {
                    $Partnership = 'NoPartnership';
                }

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $strMerID,
                    'Partnership' => $Partnership,
                    "AffDefaultUrl" => addslashes($AffDefaultUrl),
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    $Homepage = trim($item['preview_link']);
                    $CommissionExt = '';
                    switch ($item['price_format_id']) {
                        case 5 :
                            $CommissionExt = addslashes(trim($item['price_converted']) . "%");
                            break;
                        case 1 :
                            $CommissionExt = addslashes("€" . trim($item['price_converted']));
                            break;
                        default :
                            mydie("There find new currency! id={$item['currency_id']}");
                    }
                    
                    //SEMPolicyExt
                    $SEMPolicyExt = '';
                    if (empty($item['media_types'])){
                    	$SEMPolicyExt = 'allowed';
                    }else {
	                    foreach ($item['media_types'] as $type){
	                    	if(stripos($type['name'], 'PPC') !== 0){
	                    		$SEMPolicyExt = 'allowed';
	                    		break;
	                    	}
	                    }
                    }

                    $arr_prgm[$strMerID] += array(
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Name" => addslashes(html_entity_decode(trim($strMerName))),
                        "StatusInAff" => 'Active',
                        "StatusInAffRemark" => $StatusInAffRemark,
                        "CommissionExt" => $CommissionExt,
                        "Homepage" => addslashes($Homepage),
                        "CategoryExt" => addslashes($item['vertical_name']),
                        "TargetCountryExt" => addslashes($country),
                        "Description" => addslashes($item['description']),
                    	"SEMPolicyExt" => $SEMPolicyExt
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