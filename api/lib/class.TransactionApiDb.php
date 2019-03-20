<?php
class TransactionApiDb extends MysqlPdo
{
	private $networkId;
	private $siteId;

	public function getAccountSiteInfo($siteId, $networkId, $key)
	{
		$return_arr = array('code' => 0, 'error_msg' => '');

		$siteId = addslashes($siteId);
		$networkId = addslashes($networkId);
		$key = addslashes($key);
		if (!$siteId || !$networkId) {
			$return_arr['error_msg'] = 'siteId or networkId can not be empty !';
			return $return_arr;
		}

		$sql = "SELECT s.AccountSiteID
            FROM account_site s
            INNER JOIN account a ON s.AccountID = a.AccountID
            INNER JOIN network n ON a.NetworkID = n.NetworkID
            INNER JOIN department d ON a.DepartmentID = d.DepartmentID
            WHERE s.AccountSiteID = '{$siteId}' AND n.NetworkID = '{$networkId}' AND d.`ApiKey` = '{$key}'";
		$result = $this->getFirstRow($sql);
		if (!$result) {
			$return_arr['error_msg'] = 'authentication failure!';
			return $return_arr;
		}

		$this->networkId = $networkId;
		$this->siteId = $siteId;

		$return_arr['code'] = 1;
		return $return_arr;
	}

	public function getAllTransactionInfo($page, $pageSize, $orderByField = false)
	{
		$page = intval($page);
		$pageSize = intval($pageSize);
		if($page < 1) {
			$page = 1;
		}

		if($pageSize < 1 || $pageSize > PAGESIZE) {
			$pageSize = PAGESIZE;
		}
		$startIndex = ($page - 1) * $pageSize;

		$return_arr = array('code' => 1, 'page' => $page, 'pagesize' => $pageSize);

		$sql = "SELECT COUNT(1) FROM transaction_{$this->networkId} WHERE AccountSiteID = '{$this->siteId}' AND LastUpdateTime > now() - interval 1 day ";
		$cnt = intval($this->getFirstRowColumn($sql));
		if (!$cnt) {
			$return_arr['data'] = array();
			$return_arr['total'] = 0;
			return $return_arr;
		}
		$return_arr['total'] = $cnt;

		if($startIndex > $cnt) {
			$return_arr['data'] = array();
			return $return_arr;
		}

		$sql = "SELECT * FROM transaction_{$this->networkId} WHERE AccountSiteID = '{$this->siteId}' AND LastUpdateTime > now() - interval 1 day ";
		if ($orderByField) {
			$sql .= " ORDER BY `$orderByField` ASC ";
		}
		$sql .= " LIMIT $startIndex,$pageSize ";

		try {
			$data = $this->getRows($sql, 'TransactionId');
		} catch (PDOException $e) {
			$return_arr['code'] = 0;
			$return_arr['error_msg'] = $e->getMessage();
			return $return_arr;
		}

		$return_arr['data'] = array_map(function ($c){if (isset($c['BatchID'])){unset($c['BatchID']);return $c;}},$data);

		return $return_arr;
	}

}

?>