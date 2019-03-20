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

$pApi = new TransactionApiDb();

$orderByField = false;
if (isset($params['orderByField']) && trim($params['orderByField'])) {
	$firstRow = $pApi->getFirstRow("SELECT * FROM transaction_{$params['networkId']} LIMIT 1");
	$fields = array_keys($firstRow);
	$orderByField = trim($params['orderByField']);

	if (!in_array($orderByField, $fields)) {
		$result['error_msg'] = "The field=$orderByField not find in our system!";
		echoJson($result);
	}
}

$result = $pApi->getAccountSiteInfo($params['siteId'], $params['networkId'], $params['key']);

if (!$result['code']) {
	echoJson($result);
}

$result = $pApi->getAllTransactionInfo($params['page'], $params['pagesize'], $orderByField);
echoJson($result);
