<?php
/**
 * Created by PhpStorm.
 * User: peteryan
 * Date: 2018/4/28
 * Time: 11:44
 */
include_once(dirname(dirname(__DIR__)) . "/etc/const.php");

$key = isset($_REQUEST['key']) ? $_REQUEST['key'] : '';
$siteId = isset($_REQUEST['siteId']) ? intval($_REQUEST['siteId']) : 0;
$networkId = isset($_REQUEST['networkId']) ? intval($_REQUEST['networkId']) : 0;
$resultArray = array(
    'status' => 'success',
    'message' => '',
    'data' => array(),
);
if (empty($key) || empty($siteId) || empty($networkId)) {
    $resultArray['status'] = 'fail';
    $resultArray['message'] = 'key or siteId or networkId is empty';
} else {
    $objProgram = New ProgramDb();
    $tmpKey = addslashes($key);
    $tmpSiteId = addslashes($siteId);
    $tmpNetworkId = addslashes($networkId);
    $sql = "SELECT s.AccountSiteID
            FROM account_site s
            INNER JOIN account a ON s.AccountID = a.AccountID
            INNER JOIN network n ON a.NetworkID = n.NetworkID
            INNER JOIN department d ON a.DepartmentID = d.DepartmentID
            WHERE s.AccountSiteID = '{$tmpSiteId}' AND n.NetworkID = '{$tmpNetworkId}' AND d.`Key` = '{$tmpKey}';";
    $result = $objProgram->objMysql->getFirstRow($sql);
    if (empty($result)) {
        $resultArray['status'] = 'fail';
        $resultArray['message'] = 'authentication failure';
    } else {
        $sql = "SELECT *
                FROM aff_crawl_config
                WHERE AffId = '{$tmpNetworkId}' AND ProgramCrawlStatus = 'Yes' AND `Status` = 'Active';";
        $result = $objProgram->objMysql->getFirstRow($sql);
        if (empty($result)) {
            $resultArray['status'] = 'success';
            $resultArray['message'] = "the program of network {$networkId} has yet to crawl";
        } else {
            $sql = "SELECT p.*, s.*
                    FROM program_account_site_{$networkId} s
                    INNER JOIN program_{$networkId} p ON s.IdInAff = p.IdInAff
                    WHERE s.AccountSiteID = '{$tmpSiteId}'";
            $result = $objProgram->objMysql->getRows($sql);
            $resultArray['status'] = 'success';
            $resultArray['message'] = '';
            $resultArray['data'] = $result;
        }
    }
}
echo json_encode($resultArray);