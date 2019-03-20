<?php

include_once(dirname(__FILE__) . "/etc/const.php");

$result = array('code' => 0, 'error_msg' => '');
$params = array();

if (count($_GET)) {
    $params = paramsFilter($_GET);
} elseif(count($_POST)) {
    $params = paramsFilter($_POST);
}

if (!$params) {
    $result['error_msg'] = 'The request parameter can not be empty !';
    echoJson($result);
}

$param_arr = array('siteId', 'networkId', 'key');

foreach ($param_arr as $val) {
    if (!isset($params[$val])) {
        $result['error_msg'] = "The $val not be provided !";
        echoJson($result);
    } elseif (empty($params[$val])) {
        $result['error_msg'] = "The $val can not be empty !";
        echoJson($result);
    }
}

if (!isset($params['page']) || empty($params['page']) || !intval($params['page'])) {
    $params['page'] = 1;
}

if (!isset($params['pagesize'])) {
    $params['pagesize'] = PAGESIZE;
}

$pApi = new ProgramApiDb();
$result = $pApi->getAccountSiteInfo($params['siteId'], $params['networkId'], $params['key']);

if (!$result['code']) {
    echoJson($result);
}

$result = $pApi->getAllProgramInfo($params['page'], $params['pagesize']);
echoJson($result);
