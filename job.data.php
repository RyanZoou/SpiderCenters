<?php
include_once(__DIR__ . "/etc/const.php");

define("ALERT_EMAIL", "stanguan@meikaitech.com,ryanzou@brandreward.com");
$longopts = array("siteid:", "method:", 'crawljobid', "merid", "ignorecheck::", "daemon::", "logfile", "silent::", "alert", "startdate", "enddate", "full::", 'batchid');
$options = GetOptions::get_long_options($longopts);
$paras = array();

if($options["ignorecheck"])
	$paras["ignorecheck"] = 1;
$oLinkFeed = new LinkFeed($paras);

$crawlJobId = false;
if (isset($options['crawljobid']) && intval($options['crawljobid'])) {
	$crawlJobId = intval($options["crawljobid"]);
} else {
	if ($options['method'] == 'getprogram') {
		$crawlJobId = $oLinkFeed->startNewCrawlJob($options['siteid'], 'Program', $options["logfile"], 'Crawling', $options["full"]);
	} else if ($options['method'] == 'gettransaction') {
        //when full=true mean that we will crawl half yeas transaction!
	    $crawlJobId = $oLinkFeed->startNewCrawlJob($options['siteid'], 'Transaction', $options["logfile"], 'Crawling', $options["full"]);
	} else {
		$crawlJobId = $oLinkFeed->getMaxCrawlJobID($options['siteid'], $options['method']);
	}
	$options["crawljobid"] = $crawlJobId;
}

if($options["daemon"])
{
	$options["alert"] = 1;
	if(!$options["logfile"])
	{
		$options["logfile"] = INCLUDE_ROOT . "logs/" . basename(__FILE__);
		$arr_log_para = array();
		$arr_log_para[] = $options["method"];
		$arr_log_para[] = $options["crawljobid"];

		if ($options['method'] == 'getprogram' || $options['method'] == 'gettransaction') {
			$arr_log_para[] = date("His") . "_" . date("Ymd");
		}

		$arr_log_para[] = "log";
		$options["logfile"] .= "." . implode(".",$arr_log_para);
	}
	$cmd = "nohup php " . __FILE__ . " " . GetOptions::get_option_str($longopts,$options,array("daemon","silent")) . " > " . $options["logfile"] . " 2>&1 &";
	echo "start daemon mode ...\n";
	echo "log file is: " . $options["logfile"] . "\n";
    echo $cmd . PHP_EOL;
	if ($options['method'] == 'getprogram' || $options['method'] == 'gettransaction') {
		$oLinkFeed->setJobCrawlLogPath($crawlJobId, $options["logfile"]);
	}

	system($cmd);
	if(!$options["silent"])
	{
		sleep(3);
		system("tail -f " . $options["logfile"]);
	}
	exit;
}

if($options["alert"] == 1)
{
	$cmd = "php " . __FILE__ . " " . GetOptions::get_option_str($longopts,$options,array("daemon","logfile","silent","alert"));
    system($cmd,$retval);
	
	if($retval > 0)
	{
	    $alert_remark = $full = '';
	    switch ($options['method']) {
            case 'getprogram':
                $alert_remark = 'Crawl program warning: ';
                $full = $options["full"] ? ' : Full :' : ' : Only site :';
                $oLinkFeed->setJobCrawlStatus($crawlJobId, 'Error');
                break;
            case 'checkprogram':
                $alert_remark = 'Check program warning: ';
                $log_content = '';
	            if($options["logfile"] && file_exists($options["logfile"])){
		            $log_content = strip_tags(shell_exec("cat " . $options["logfile"]));
	            }
	            $oLinkFeed->setJobCheckStatus($crawlJobId, 'Error', $log_content);
	            $oLinkFeed->analyzeCheckResult($crawlJobId, $log_content);
                break;
            case 'syncprogram':
                $alert_remark = 'Sync program warning: ';
                $oLinkFeed->setJobSyncStatus($crawlJobId, 'Error');
                break;


		    case 'gettransaction':
			    $alert_remark = 'Crawl transaction warning: ';
			    $oLinkFeed->setJobCrawlStatus($crawlJobId, 'Error');
			    break;
		    case 'checktransaction':
			    $alert_remark = 'Check transaction warning: ';
			    $log_content = '';
			    if($options["logfile"] && file_exists($options["logfile"])){
				    $log_content = strip_tags(shell_exec("cat " . $options["logfile"]));
			    }
			    $oLinkFeed->setJobCheckStatus($crawlJobId, 'Error', $log_content);
//			    $oLinkFeed->analyzeCheckResult($crawlJobId, $log_content);
			    break;
		    case 'synctransaction':
			    $alert_remark = 'Sync transaction warning: ';
			    $oLinkFeed->setJobSyncStatus($crawlJobId, 'Error');
			    break;

            default :
                break;
        }

		//send alert
        $site_arr = $oLinkFeed->getSitesAccoutName($options["siteid"]);
	    $site1_id = current(explode(',', $options["siteid"]));
        $site1_info = $oLinkFeed->getAffById($site1_id);

        $alert_subject = $alert_remark . $options["method"] . "$full {$site1_info['NetworkID']} : {$site1_info['NetworkName']} failed @ " . date("Y-m-d H:i:s") . " [{$options["siteid"]}(" . join(',', array_values($site_arr)) . ")]";
		$alert_body = "$cmd" . "\n";
		$alert_body .= "log file: " . $options["logfile"] . "\n";
		$alert_body .= "\n\n";
		if($options["logfile"] && file_exists($options["logfile"])){
			$alert_body .= strip_tags(shell_exec("tail -n 50 " . $options["logfile"]));
		}
		$to = "stanguan@meikaitech.com,ryanzou@brandreward.com,lucky@brandreward.com,nolan@brandreward.com";
		AlertEmail::SendAlert($alert_subject,nl2br($alert_body), $to);
		mydie("die: job failed, alert email was sent ... \n");
	}

	exit;
}

$siteidArray = explode(",",$options["siteid"]);
$batchId = $isFull = false;
if (!empty($options["batchid"])){
    $batchId = intval($options["batchid"]);
}
if (!empty($options["full"])){
    $isFull = true;
}

//when full=true mean that we will crawl half yeas transaction!
if ($options['method'] == 'gettransaction' && $isFull){
    $start_date = date("Y-m-d", strtotime("-180 days"));
    $end_date = date("Y-m-d");
} else {
    $start_date = isset($options["startdate"]) ? $options["startdate"] : '';
    $end_date = isset($options["enddate"]) ? $options["enddate"] : '';
}
if ($isFull && !$crawlJobId) {
	mydie("Full crawl crawlJobId can not be empty!");
}

//var_export($options);
foreach($siteidArray as $siteId)
{
	$siteId = trim($siteId);
	if(!is_numeric($siteId))
		continue;
	switch($options["method"])
	{
		/********************************* Program part ********************************/
		case "getprogram":
			$oLinkFeed->GetAllProgram($siteId, $crawlJobId, $isFull, $batchId);
            break;
		case "checkSitebatch":
            $oLinkFeed->CheckSiteProgram($batchId, $crawlJobId);
            break 2;
		case "syncSitebatch":
            $oLinkFeed->SyncSiteProgram($batchId, $crawlJobId);
            break 2;
        case "checkjob":
            $oLinkFeed->CheckJobProgram($crawlJobId);
            break 2;
        case "syncjob":
            $oLinkFeed->SyncJobProgram($crawlJobId);
            break 2;
        case "checkprogram":
            $oLinkFeed->CheckSiteProgram(false, $crawlJobId);
            $oLinkFeed->CheckJobProgram($crawlJobId);
            break 2;
        case "syncprogram":
            $oLinkFeed->SyncSiteProgram(false, $crawlJobId);
            $oLinkFeed->SyncJobProgram($crawlJobId);
            break 2;
		/************************************ end *************************************/

		/****************************** transaction part ******************************/
		case "gettransaction":
			$oLinkFeed->GetAllTransaction($siteId, $crawlJobId, $start_date, $end_date, $batchId);
			break;
		case "checktransaction":
			$oLinkFeed->CheckJobTransaction($crawlJobId);
			break 2;
		case "synctransaction":
			$oLinkFeed->SyncJobTransaction($crawlJobId);
			break 2;
		/************************************ end *************************************/

		default:
			mydie("die: wrong method: " . $options["method"] . "\n");
	}
}

if ($options["method"] == "getprogram" || $options['method'] == 'gettransaction') {
    $oLinkFeed->setJobCrawlStatus($crawlJobId, 'Done');
}

print "<< Succ >>\n\n";
exit;









?>