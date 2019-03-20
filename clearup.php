<?php
include_once(dirname(__FILE__) . "/etc/const.php");

function clearup_file($rel_path,$name_pattern="",$exp_day=7,$dir_pattern="")
{
	//find /mezi/sem/semdata/ -path */temp/* -mtime +5 -delete
	//find /mezi/sem/semdata/ -name 'hourlyrevenue_*' -mtime +5 -delete
	if(!defined("INCLUDE_ROOT")) mydie("die: INCLUDE_ROOT not defined.");
	$rel_path = ltrim($rel_path,"/");
	$path = INCLUDE_ROOT . $rel_path;
	if(!is_dir($path)) return false;
	$cmd = "find $path";
	if($dir_pattern) $cmd .= " -path '$dir_pattern'";
	if($name_pattern) $cmd .= " -name '$name_pattern'";
	$cmd .= " -mtime +" . $exp_day . " -delete";
	echo $cmd . "\n";
	return system($cmd);
}

//1. for cache file
//clearup_file("data/LinkFeed_1_Commission_Junction/","*.cache",7,"*/site_*/*");
clearup_file("data","*.cache",7);
clearup_file("data","*.dat",7);
clearup_file("data","*.csv",7);
clearup_file("logs","*",7);
clearup_file("temp","*",7);

$checkStartDay = date('Y-m-d 00:00:00', strtotime('-91 days'));
$checkEndDay = date('Y-m-d 00:00:00', strtotime('-90 days'));

$objMysql = new MysqlExt();
$sql = "SELECT CrawlJobID,CrawlType,NetworkID FROM crawl_job_batch WHERE BaseDataCrawlStartTime between '$checkStartDay' and '$checkEndDay'";


$crawlJob_arr = $objMysql->getRows($sql,'CrawlJobID');

foreach ($crawlJob_arr as $CrawlJobID => $val) {
    echo "**********CrawlJobID=$CrawlJobID******networkId={$val['NetworkID']}**********" . PHP_EOL;

    $CrawlType = $val['CrawlType'];
    switch ($CrawlType) {
        case 'Program':
            delProgramBatch($CrawlJobID);
            break;
        case 'Transaction':
            delTransactionBatch($CrawlJobID);
            break;
        default:
            break;
    }
}

print "<< Succ >>\n\n";

function delProgramBatch($crawlJobId)
{
    $objMysql = new MysqlExt();
    $sql = "select BatchID,NetworkID From batch WHERE CrawlJobId='$crawlJobId'";
    $arr = $objMysql->getRows($sql,'BatchID');

    foreach ($arr as $batchId => $vv) {
        $networkId = $vv['NetworkID'];
        $sql = "SELECT IdInAff FROM batch_program_changelog WHERE NetworkID='$networkId' AND (BatchID='$batchId' OR OldBatchId='$batchId')";
        $idinaff_arr = array_keys($objMysql->getRows($sql, 'IdInAff'));
        if (empty($idinaff_arr)) { //如果未找到change记录直接删除该batch的所有数据
            $sql = "delete from batch_program_$networkId where BatchID='$batchId'";
            $objMysql->query($sql);
            $sql = "delete from batch_program_account_site_$networkId where BatchID='$batchId'";
            $objMysql->query($sql);

            echo "ALL program DELETE $batchId\n";
        } else { //如果找到了change记录，只删除未改变商家的数据
            $sql = "delete from batch_program_$networkId where BatchID='$batchId' AND IdInAff NOT IN ('" . join("','", $idinaff_arr) . "')";
            $objMysql->query($sql);
            $sql = "delete from batch_program_account_site_$networkId where BatchID='$batchId' AND IdInAff NOT IN ('". join("','", $idinaff_arr) ."')";
            $objMysql->query($sql);

            echo "Reserved program " . count($idinaff_arr) . " $batchId\n";
        }
    }
}
function delTransactionBatch($crawlJobId)
{
    $objMysql = new MysqlExt();
    $sql = "select BatchID,NetworkID From batch WHERE CrawlJobId='$crawlJobId'";
    $arr = $objMysql->getRows($sql,'BatchID');
    foreach ($arr as $batchId => $vv) {
        $networkId = $vv['NetworkID'];

        $sql = "SELECT TransactionId FROM batch_transaction_changelog WHERE NetworkID='$networkId' AND (BatchID='$batchId' OR OldBatchId='$batchId')";
        $TransactionId_arr = array_keys($objMysql->getRows($sql, 'TransactionId'));
        if (empty($TransactionId_arr)) {//如果未找到change记录直接删除该batch的所有数据
            $sql = "delete from batch_transaction_$networkId where BatchID='$batchId'";
            $objMysql->query($sql);

            echo "ALL transaction DELETE $batchId\n";
        } else {//如果找到了change记录，只删除未改变商家的数据
            $sql = "delete from batch_transaction_$networkId where BatchID='$batchId' AND TransactionId NOT IN ('" . join("','", $TransactionId_arr) . "')";
            $objMysql->query($sql);

            echo "Reserved transaction " . count($TransactionId_arr) . " $batchId\n";
        }
    }
}

?>