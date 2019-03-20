<?php
class TransactionDb
{
	function __construct()
	{
		if (!isset($this->objMysql)) {
			$this->objMysql = new MysqlExt(PROD_DB_NAME, PROD_DB_HOST, PROD_DB_USER, PROD_DB_PASS);
		}
		$this->oLinkFeed = new LinkFeed();
	}

	function InsertTransactionToBatch($networkId, $arr)
	{
		if (!$networkId) {
			mydie("NetworkId can not be empty!");
		} else {
			$table_name = "batch_transaction_$networkId";
		}
		if (empty($arr)) {
			return false;
		}
		$arr_update = array();

		$transaction_list = array_keys($arr);
		$firstArr = current($arr);
		if (!isset($firstArr['BatchID']) || !isset($firstArr['AccountSiteID']) || empty($firstArr['BatchID']) || empty($firstArr['AccountSiteID'])){
			mydie("Params error when insert site data to batch table!");
		}
		$sql = "SELECT TransactionId FROM $table_name WHERE BatchID = ".intval($firstArr['BatchID'])." AND AccountSiteID =" .intval($firstArr['AccountSiteID']). " AND TransactionId IN ('".implode("','",$transaction_list)."')";

		$return_arr = $this->objMysql->getRows($sql,"TransactionId");
		foreach($return_arr as $k => $v) {
			if(isset($arr[$k]) === true) {
				$arr_update[$k] = $arr[$k];
				unset($arr[$k]);
			}
		}
		unset($return_arr);

		if(count($arr)){
			$this->doInsertTransactionToBatch($table_name, $arr);
		}
		if(count($arr_update)){
			$this->doUpdateTransactionToBatch($table_name, $arr_update);
		}
	}

	function doInsertTransactionToBatch($table_name, $arr)
	{
		$fields = $this->oLinkFeed->getTableFields($table_name);

		$fields_values = array();
		$value_list = array();
		foreach ($arr as $key => $val) {
			foreach ($val as $vk => $vv) {
				if (isset($fields[$vk])) {
					$fields_values[$key][$vk] = $vv;
				}
			}
			$value_list[] = "('".join("','", array_values($fields_values[$key]))."')";
		}

		$sql = "INSERT INTO $table_name (`" . join("`,`", array_keys(current($fields_values))) . "`) VALUES " . join(',', $value_list);
		try {
			$this->objMysql->query($sql);
		} catch (Exception $e) {
			echo $e->getMessage()."\n";
			return false;
		}

		return true;
	}

	function doUpdateTransactionToBatch($table_name, $arr)
	{
		$firstArr = current($arr);
		if (!isset($firstArr['BatchID']) || !isset($firstArr['AccountSiteID']) || empty($firstArr['BatchID']) || empty($firstArr['AccountSiteID'])){
			mydie("Params error when update site data to batch table!");
		}
		$where = ' BatchID =' .intval($firstArr['BatchID']) . ' AND AccountSiteID =' . intval($firstArr['AccountSiteID']) . ' ';

		$fields = $this->oLinkFeed->getTableFields($table_name);

		foreach($arr as $key => $val){

			$field_update = array();
			foreach($val as $k => $v){
				$v = trim($v);
				if (in_array($k, array('BatchID', 'TransactionId', 'AccountSiteID'))) {
					continue;
				}
				if(isset($fields[$k]) && !empty($v)) {
					$field_update[] = "$k = '$v'";
				}
			}

			if(count($field_update)){
				$sql = "UPDATE $table_name SET ".implode(",", $field_update)." WHERE $where AND TransactionId = '".$val["TransactionId"]."'";
				try {
					$this->objMysql->query($sql);
				} catch (Exception $e) {
					echo $e->getMessage()."\n";
				}
			}
		}
	}

	function checkTransactionBatchData($NetworkID, $BatchID, $AccountSiteID)
	{
		if (!$NetworkID || !$BatchID || !$AccountSiteID) {
			mydie("Params error when check transaction!");
		}
		$return_arr = array();
		$change_transactionId_list = array();
		$allTransactionNum = 0;

		$pos = 0;
		$limit = 1;
		$warning = 5000000;
		while(1){
			$sql = "SELECT * FROM batch_transaction_{$NetworkID} WHERE BatchID='".trim($BatchID)."' AND AccountSiteID='".trim($AccountSiteID)."' limit $pos, $limit";
			$fieldsVal = $this->objMysql->getRows($sql);
			if(count($fieldsVal)){
				foreach ($fieldsVal as $val) {
					$allTransactionNum ++;
					$cResult = $this->insertTransactionChangeLog($val, $NetworkID, $AccountSiteID);

					if (!empty($cResult)) {
						$change_transactionId_list[$cResult['TransactionId']] = $cResult['Fields'];
					}
				}
				$pos += $limit;
			} else {
				break;
			}

			if($pos > $warning){
				mydie('The num of compare transaction Batch Data Change > '. $warning);
			}
		}
		$return_arr['total_num'] = $allTransactionNum;
		$return_arr['change_detail'] = $change_transactionId_list;

		return $return_arr;
	}

	function insertTransactionChangeLog($row, $networkId, $siteId)
	{
		if (empty($row) || !isset($row["TransactionId"]) || !$row["TransactionId"] || !$networkId || !$siteId) {
			return false;
		}

		$change_arr = array();

		$transactionInfo = $this->getTransactionById($networkId, $row["TransactionId"], $siteId);

		$allChangeData = $this->compareFieldValue($networkId, $transactionInfo, $row);

		if (!empty($allChangeData['rule'])) { //规则内变化的记录log
			$insertConstantData = array(
				'BatchID' => $row['BatchID'],
				'OldBatchID' => $transactionInfo['BatchID'],
				'NetworkID' => $networkId,
				'TransactionId' => $row["TransactionId"],
				'AccountSiteID' => $siteId,
				'ChangeTime'   => date("Y-m-d H:i:s"),
			);

			$change_arr["TransactionId"] = $row["TransactionId"];

			foreach ($allChangeData['rule'] as $key => $val) {
				if($key == "LastUpdateTime" || $key == "BatchID") {
					continue;
				}

				$change_arr["Fields"][] = $key;

				$insertData = $insertConstantData;
				$insertData['FieldName'] = $key;

				$sql = "insert ignore into `batch_transaction_changelog` ";
				$fields = $values = '';

				foreach ($insertData as $k => $v) {
					$fields .= "`$k`, ";
					$values .= "'" . addslashes($v) . "', ";
				}
				unset($insertData);

				$fields = preg_replace("|, $|i", '', $fields);
				$values = preg_replace("|, $|i", '', $values);
				$sqlQuery = $sql . '(' . $fields . ') values (' . $values . ');';

				if (!$this->objMysql->query($sqlQuery)) {
					return false;
				}
			}
		}
		return $change_arr;
	}

	function getTransactionById($networkId, $transactionId, $siteId)
	{
		$data = array();
		if (!$networkId || !$transactionId || !$siteId) {
			return $data;
		}

		$sql = "SELECT * FROM transaction_{$networkId} WHERE AccountSiteID = '".addslashes($siteId)."' AND TransactionId='".addslashes($transactionId)."'";
		if ($query = $this->objMysql->query($sql)) {
			$data = $this->objMysql->getRow($query);
		}
		return $data;
	}

	function compareFieldValue($networkId, $from = array(), $to = array())
	{
		$data['normal'] = $data['rule'] = array();
		$field_arr = $this->oLinkFeed->getCompareField($networkId, 'Transaction');

		if (empty($from)) {
			return $data;
		}

		if (empty($to)) {
			foreach ($from as $k => $v) {
				if (!isset($field_arr[$k]['NeedCheckChange']) || $field_arr[$k]['NeedCheckChange'] != 'YES'){
					$data['normal'][$k]['old'] = trim(stripslashes($v));
					$data['normal'][$k]['new'] = '';
				}else{
					$data['rule'][$k]['old'] = trim(stripslashes($v));
					$data['rule'][$k]['new'] = '';
				}
			}
			return $data;
		}
		foreach ($from as $k => $v) {
			if ($k == 'LastUpdateTime') {
				continue;
			}
			if (strcmp($v, $to[$k]) == 0) {
				continue;
			}
			if (!isset($field_arr[$k]['NeedCheckChange']) || $field_arr[$k]['NeedCheckChange'] != 'YES'){
				$data['normal'][$k]['old'] = trim(stripslashes($v));
				$data['normal'][$k]['new'] = $to[$k];
			}else{
				$data['rule'][$k]['old'] = trim(stripslashes($v));
				$data['rule'][$k]['new'] = $to[$k];
			}
		}

		return $data;
	}

	function syncTransactionBatchData($networkId, $batchId, $siteId)
	{
		try {
			$site_field_list = array_keys($this->oLinkFeed->getTableFields("transaction_{$networkId}"));

			$pos = $i = 0;
			$limit = 10;
			$warning = 1000000;
			while (1) {
				$rSql = "SELECT * FROM batch_transaction_{$networkId} WHERE BatchID ='" . trim($batchId) . "' AND AccountSiteID='" . trim($siteId) . "' limit $pos, $limit";
				$program_site_arr = $this->objMysql->getRows($rSql);
				$rp_value_list = array();

				if (!empty($program_site_arr)) {
					foreach ($program_site_arr as $rv) {
						$val_row = array();
						foreach ($site_field_list as $index => $fv) {
							if ($fv != 'LastUpdateTime') {
								$val_row[$index] = addslashes($rv[$fv]);
							} else {
								$val_row[$index] = date('Y-m-d H:i:s');
							}
						}
						$rp_value_list[] = "('" . implode("','", $val_row) . "')";
					}
					$sql = "REPLACE INTO transaction_{$networkId} (`" . implode("`,`", $site_field_list) . "`) VALUES" . implode(",", $rp_value_list);
					$this->objMysql->query($sql);
				} else {
					break;
				}

				$pos += $limit;
				$i++;

				if ($i * $limit > $warning) {
					echo "\tThis batch get too many transactions, please check the reason.";
					return false;
				}
			}
			echo "\tIn this batch, $pos transactions haven crawled, and they were synced!.\n";

		}catch (Exception $e){
			echo $e->getMessage();
			return false;
		}

		return true;
	}

}
