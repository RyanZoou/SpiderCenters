<?php
include_once "XML2Array.php";
class LinkFeed_2058_Chanet
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
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

        $XML = new XML2Array();
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = 0;
        $base_program_num = 0;


        $request = array('AccountSiteID'=>$this->info['AccountSiteID'],'method'=>'get',);
        $url = "http://file.chanet.com.cn/rest/as/get_pm_list.cgi?username={$this->info['UserName']}&password={$this->info['Password']}&token={$this->info['APIKey1']}&as_id={$this->info['APIKey2']}&cost_type=all&certification=all";
        $cacheName="data_" . date("YmdH") . "_program_list_api.dat";
//         $result = $this->oLinkFeed->GetHttpResultAndCache($url,$request,$cacheName,'promotion');
        $result = $this->chanet_getcache($url,$request,$cacheName,'promotion',3,$this->oLinkFeed);
        $result = @$XML->createArray($result);

        if (!isset($result['soap:Envelope']['soap:Body']['get_promotion_listResponse']['promotion_list']['promotion']) || empty($result['soap:Envelope']['soap:Body']['get_promotion_listResponse']['promotion_list']['promotion'])){
            mydie("Failed to get program list from api.");
        }
        $result = $result['soap:Envelope']['soap:Body']['get_promotion_listResponse']['promotion_list']['promotion'];

        foreach ($result as $val){
            $programId = intval($val['id']['@value']);
            if (!$programId){
                continue;
            }
            echo $programId . "\t";

            //get partnership
            $statusInRemark = trim($val['certification']['@value']);
            if (strcmp($statusInRemark, '已批准') == 0){
            	$partnership = 'Active';
            }elseif(strcmp($statusInRemark, '待审批') == 0 || strcmp($statusInRemark, '申请中') == 0){
            	$partnership = 'Pending';
            }elseif (strcmp($statusInRemark, '已拒绝') == 0){
            	$partnership = 'Declined';
            }elseif (strcmp($statusInRemark, '未申请') == 0){
            	$partnership = 'NoPartnership';
            }else{
            	mydie("Find new partnership symble ({$statusInRemark})");
            }
            
            $supportDeepUrl = strcmp($val['support_deeplink']['@value'] , '是') == 0 ? 'YES' : 'NO';
            if($supportDeepUrl == 'NO'){
            	$affDefaultUrl = $val['default_url']['@value'];
            }else{
            	$affDefaultUrl = 'http' . $this->oLinkFeed->ParseStringBy2Tag($val['default_url']['@value'], 'http', '&u') . '&u=[SUBTRACKING]&url=[DEEPURL]';
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
            
	            $programName = trim($val['promotion_name']['@value']);
	            $logoUrl = trim($val['logo_url']['@value']);
	            $desc = $val['introduction']['@value'];
	            $category = trim($val['category']['@value']);
	            $homepage = trim($val['site_url']['@value']);
	            $country = trim($val['country']['@value']);            
	           
	            //get commission
	            $commission = '';
	            if (isset($val['thanks_list']['thanks']) && !empty($val['thanks_list']['thanks'])){
	                if (!isset($val['thanks_list']['thanks']['price'])){
	                    foreach ($val['thanks_list']['thanks'] as $cv){
	                        echo $cv['price']['@value'] . "\t";
	                        if (isset($cv['price']['@value']) && $cv['price']['@value'] != '-') {
	                            if (stripos($cv['price']['@value'], '%') !== false){
	                                $commission .= $cv['price']['@value'] . '|';
	                            }else {
	                                $commission .= 'CNY ' . $cv['price']['@value'] . '|';
	                            }
	                        }
	                    }
	                    $commission = rtrim($commission, '|');
	                }elseif ($val['thanks_list']['thanks']['price']['@value'] != '-'){
	                    if (stripos($val['thanks_list']['thanks']['price']['@value'], '%') !== false){
	                        $commission = $val['thanks_list']['thanks']['price']['@value'];
	                    }else {
	                        $commission = 'CNY ' . $val['thanks_list']['thanks']['price']['@value'];
	                    }
	                }
	            }
	
	            $arr_prgm[$programId] += array(
	            	'CrawlJobId' => $this->info['crawlJobId'],
	                "Name" => addslashes($programName),
	                "Homepage" => addslashes($homepage),
	                "CommissionExt" => addslashes($commission),
	                "CategoryExt" => addslashes($category),
	                "TargetCountryExt" => addslashes($country),
	                "Description" => addslashes($desc),
	                "LogoUrl" => addslashes($logoUrl),
	                "SupportDeepUrl" => $supportDeepUrl
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
        if ($program_num < 1) {
            mydie("die: program count < 1, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
        echo "\tGet Program by Api end\r\n";
    }
    
    function chanet_getcache($url, $request, $cacheFileName, $valStr='', $retry=3,$oLinkFeed){
    	if (!isset($request['AccountSiteID'])) {
    		mydie("AccountSiteID can not be empty!");
    	}
    	if (empty($cacheFileName)) {
    		mydie("CacheFileName can not be empty!");
    	}
    	
    	$results = '';
    	$cache_file = $oLinkFeed->fileCacheGetFilePath($request['AccountSiteID'], $cacheFileName, 'data', true);
    	if (!$oLinkFeed->fileCacheIsCached($cache_file)) {
    		while ($retry) {
    			$time = 4 - $retry;
    			echo "try times : {$time}".PHP_EOL;
    			$r = $oLinkFeed->GetHttpResult($url, $request);
    			if ($valStr) {
    				if (strpos($r['content'], $valStr) !== false) {
    					$results = $r['content'];
    					break;
    				}
    			} elseif (!empty($r['content'])) {
    				$results = $r['content'];
    				break;
    			}
    			print_r($r);
    			sleep(300);
    			$retry--;
    		}
    	
    		if (!$results) {
    			print_r($r);
    			mydie("Can't get the content of '{$url}', please check the val string !\r\n");
    		}
    		//            $results = mb_convert_encoding($results, "UTF-8", mb_detect_encoding($results));
    		$oLinkFeed->fileCachePut($cache_file, $results);
    	
    		return $results;
    	}
    	$result = file_get_contents($cache_file);
    	
    	return $result;
    }

}
?>
