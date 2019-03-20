<?php
include_once(__DIR__ . "/etc/const.php");

$networkIdArray = array();
$siteIdArray = array(); 				// todo site维度
$accountIdArray = array(); 				// todo account维度
$is_debug = $isFull = false;
$method = $startdate = $enddate = "";
if(isset($_SERVER["argc"]) && $_SERVER["argc"] > 1)
{
	foreach($_SERVER["argv"] as $v){
		$tmp = explode("=", $v);		
		if($tmp[0] == "--networkid"){
			$networkIdArray = array_flip(explode(",", $tmp[1]));
		}elseif($tmp[0] == "--siteid") {
            $siteIdArray = array_flip(explode(",", $tmp[1]));
        }elseif($tmp[0] == "--accountid") {
            $accountIdArray = array_flip(explode(",", $tmp[1]));
        }elseif ($tmp[0] == "--startdate" && preg_match('@^2\d{3}-\d{2}-\d{2}@', trim($tmp[1]))){
			$startdate = trim($tmp[1]);
        }elseif ($tmp[0] == "--enddate" && preg_match('@^2\d{3}-\d{2}-\d{2}@', trim($tmp[1]))){
			$enddate = trim($tmp[1]);
		}elseif ($tmp[0] == "--debug"){
            $is_debug = true;
        }elseif ($tmp[0] == "--full"){
            $isFull = true;
        }elseif($tmp[0] == "--method"){
			$method = trim($tmp[1])."Status";
		}
	}			
}

echo "<< Start @ ".date("Y-m-d H:i:s")." >>\r\n";

$date = date("Y-m-d H:i:s");
$objProgram = New ProgramDb();
$oLinkFeed = new LinkFeed();
$process = __DIR__ . "/job.data.php";

//killProcess($process);
$sql = "select * from aff_crawl_config where status = 'active'";
$crawl_config = $objProgram->objMysql->getRows($sql, "NetworkID");

foreach ($crawl_config as $networkId => &$val) {
	$val['ProgramCheckStatus'] = $val['ProgramCrawlStatus'];
	$val['ProgramSyncStatus'] = $val['ProgramCrawlStatus'];
	$val['StatsCheckStatus'] = $val['StatsCrawlStatus'];
	$val['StatsSyncStatus'] = $val['StatsCrawlStatus'];
}

$method_config = array(
//    "LinkCrawlStatus" => "getallpagelinks",
    "ProgramCrawlStatus" => "getprogram",
    "ProgramCheckStatus" => "checkprogram",
    "ProgramSyncStatus" => "syncprogram",
    "StatsCrawlStatus" => "gettransaction",
	"StatsCheckStatus" => "checktransaction",
	"StatsSyncStatus" => "synctransaction",
//    "InvaildLinkCrawlStatus" => "getinvalidlinks",
//    "FeedCrawlStatus" => "getallfeeds",
//    "MessageCrawlStatus" => "getmessages",
//    "ProductCrawlStatus" => "getproduct",
);

foreach($crawl_config as $networkId => $aff_v){
	if((count($networkIdArray) && !isset($networkIdArray[$networkId])) || !isset($method_config[$method]) || $crawl_config[$networkId][$method] != "Yes"){
        continue;
	}

	//check network is active or not.
    $tmpString = addslashes($networkId);
	$affSql = "SELECT asite.AccountSiteID as siteId
				FROM account a
				INNER JOIN account_site asite ON a.AccountID = asite.AccountID
				WHERE a.`Status` = 'Active' AND asite.`Status` = 'Active' AND a.NetworkID = '{$tmpString}'";
	$affArr = $objProgram->objMysql->getRows($affSql);
	if(count($affArr) <= 0) {
	    continue;
	}

    $siteId_list = join(',', array_map(function($c){if (isset($c['siteId'])){return $c['siteId'];}}, $affArr));
	if ($method_config[$method] == 'checkprogram' || $method_config[$method] == 'checktransaction') {
		$crawlJobId = $oLinkFeed->getMaxCrawlJobID($siteId_list, $method_config[$method]);
		if (!$crawlJobId) {
			continue;
		}

		$sql = "select BaseDataCrawlStatus, BaseDataCheckStatus from crawl_job_batch where CrawlJobID='$crawlJobId'";
		$result = $objProgram->objMysql->getFirstRow($sql);

		if (!isset($result['BaseDataCrawlStatus']) || $result['BaseDataCrawlStatus'] != 'Done' || !isset($result['BaseDataCheckStatus']) || $result['BaseDataCheckStatus'] != 'Uncheck') {
			continue;
		}
	}

	if ($method_config[$method] == 'syncprogram' || $method_config[$method] == 'synctransaction') {
		$crawlJobId = $oLinkFeed->getMaxCrawlJobID($siteId_list, $method_config[$method]);
		if (!$crawlJobId) {
			continue;
		}

		$sql = "select BaseDataCheckStatus, BaseDataSyncStatus from crawl_job_batch where CrawlJobID='$crawlJobId'";
		$result = $objProgram->objMysql->getFirstRow($sql);

		if (!isset($result['BaseDataCheckStatus']) || $result['BaseDataCheckStatus'] != 'Done' || !isset($result['BaseDataSyncStatus']) || $result['BaseDataSyncStatus'] != 'Unsync') {
			continue;
		}
	}

	if ($isFull){
        $cmd = "php " . $process . " --siteid={$siteId_list} --method={$method_config[$method]} --full --daemon --silent &";
	} else {
        $cmd = "php " . $process . " --siteid={$siteId_list} --method={$method_config[$method]} --daemon --silent &";
    }

    if ($startdate && $enddate) {
		$cmd = rtrim('&', $cmd) . " --startdate=$startdate --enddate=$enddate &";
    }

	if ($is_debug) {
        echo $cmd . PHP_EOL;
	}

    $sleep = 10;
	while(true){
		if(checkProcess("$process | grep $method_config[$method] ", 20)){
			if(checkProcess("'$process --siteid={$siteId_list} ' | grep $method_config[$method] ", 0)){
				system($cmd);
				echo $cmd." | start @ ".date("y-m-d H:i:s")."\r\n";
			}else{
				echo $cmd." | not finished @ ".date("y-m-d H:i:s")."\r\n";
			}
			break;
		}else{
            if ($is_debug) {
                echo "sleep..." . PHP_EOL;
            }
			sleep($sleep);
			if($sleep >= 60){
				$sleep = 60;
			}else{
				$sleep += 10;
			}
		}
	}

	sleep(3);
}

echo "<< End @ ".date("Y-m-d H:i:s")." >>\r\n";

exit;

function checkProcess($process, $cnt){
	if($cnt < 0 || $cnt > 30) $cnt = 30;
	$cmd = "ps aux | grep grep -v | grep " . $process . " -c";
	exec($cmd, $xx);
	//print_r($xx);
	//echo $xx;exit;
	if($xx[0] > $cnt){
		return false;
	}else{
		return true;
	}
}


function killProcess($process){
	$xx = `ps ax | grep ` . $process ;
	$xx = ''.$xx.'';
	
	$xxx = explode("\n", $xx);
	
	
	foreach($xxx as $v){
		$yy = explode(" ", trim($v));
		//print_r($yy);
		$id = $yy[0];
		
		if($id){
			echo $id."\r\n";
			echo system("kill ".$id);
		}
	}
}









?>
