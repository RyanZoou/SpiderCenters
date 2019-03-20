<?php
class LinkFeed_2046_DoubleTrade_RU
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->DataSource = array("feed" => 52, "website" => 27);
        $this->getStatus = false;

        $this->file = "programlog_{$aff_id}_" . date("Ymd_His") . ".csv";
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
        echo "\tGet Program by Api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = 0;
        $base_program_num = 0;
        $request = array('AccountSiteID'=>$this->info['AccountSiteID'],'method'=>'get');
        $apiUrl = 'http://api.doubletrade.ru/offers/?web_id='.$this->info['APIKey1'].'&report_key=' . $this->info['APIKey5'];
        $cacheName="data_" . date("YmdH") . "_program_list.dat";
        $result = $this->oLinkFeed->GetHttpResultAndCache($apiUrl,$request,$cacheName,'affiliate');
        $result = json_decode(json_encode(simplexml_load_string($result)), true);
        if (empty($result['matrix']['rows']['row'])){
            mydie("Can't get data from api");
        }

        foreach ($result['matrix']['rows']['row'] as $val){
            $programId = intval($val['programId']);
            if (!$programId){
                continue;
            }

            if (!isset($arr_prgm[$programId])) {
            	$affDefaultUrl = trim($val['trackingURL']);
            	$strStatus = $val['status'];
            	if (is_array($strStatus) && empty($strStatus)) {
		            $strStatus = '';
		            $partnership = 'Active';
	            } else {
		            switch ($strStatus) {
			            case 'Accepted' :
				            $partnership = 'Active';
				            break;
			            case 'Not Applied' :
				            $partnership = 'NoPartnership';
				            break;
			            case 'On Hold' :
				            $partnership = 'NoPartnership';
				            break;
			            case 'Under Consideration' :
				            $partnership = 'Pending';
				            break;
			            case 'Denied' :
				            $partnership = 'Declined';
				            break;
			            case 'Ended' :
				            $partnership = 'Expired';
				            break;
			            default :
				            mydie("Find new partnership inaff ({$val['status']})");
				            break;
		            }
	            }
            	
                $arr_prgm[$programId] = array(
                		'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                		'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                		'IdInAff' => $programId,
                		'Partnership' => $partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                		"AffDefaultUrl" => addslashes($affDefaultUrl),
                );
                
                
                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $programId, $this->info['crawlJobId']))
                {
                
	                $programName = trim($val['programName']);
	                $homepage = trim($val['AdvertiserWebsite']);
	                $logo = trim($val['logo']);
	                
	                $cookieTime = intval($val['cookieLifetime']);
	                $terms = '';
	                if (!empty($val['trafficSources'])) {
	                    foreach ($val['trafficSources'] as $tk => $tv) {
	                        $terms .= $tk . ': ' . $tv . ",\n";
	                    }
	                }
	
	                
	
	                $arr_prgm[$programId] += array(
	                		'CrawlJobId' => $this->info['crawlJobId'],
		                    "Name" => addslashes($programName),
		                    //"TargetCountryExt" => 'RU',
		                    "StatusInAffRemark" => addslashes($strStatus),
		                    "StatusInAff" => 'Active',                        //'Active','TempOffline','Offline'
		                    "Homepage" => addslashes($homepage),
		                    "TermAndCondition" => addslashes($terms),
		                    "CookieTime" => $cookieTime,
		                    "LogoUrl" => addslashes($logo)
	                );
                }
            }

            if ($this->isFull){
	            if ($val['isPercentage'] == 'yes'){
	                $commission = $val['programTariffAmount'] . '%';
	            }elseif (empty($val['programTariffCurrency']) || empty($val['programTariffAmount'])){
	            	$commission = '';
	            }else {
	            	$commission = $val['programTariffCurrency'] . ' ' . $val['programTariffAmount'];
	            }
	
	            if (!isset($arr_prgm[$programId]['CommissionExt']) || empty($arr_prgm[$programId]['CommissionExt'])){
	                $arr_prgm[$programId]['CommissionExt'] = $commission;
	            }else{
	                $arr_prgm[$programId]['CommissionExt'] .= ', ' . $commission;
	            }
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
        if ($program_num < 1) {
            mydie("die: program count < 1, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }

}


?>
