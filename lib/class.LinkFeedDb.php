<?php
class LinkFeedDb
{
    public $ProgramDbObj, $objPendingLinks, $objBdg_go_base;
    private $tableFields = array();
    private $compareFields = array();

    function __construct()
    {
        if (!isset($this->objMysql)) {
            $this->objMysql = new MysqlExt();
        }
    }

    function getAffById($siteId)
    {
        if (isset($this->affiliates[$siteId])) return $this->affiliates[$siteId];                  //affiliate是动态成员函数，所谓动态成员函数，就是在函数中定义，而不是在类的一开始定义。与成员函数无区别。
        $tmpString = addslashes($siteId);
        $sql = "SELECT d.name as DepartmentName,n.NetworkID, n.`Name` AS NetworkName, asite.AccountSiteID,asite. AccountSiteName, a.AccountID, a.UserName, a.`Password`, IF(asite.LoginUrl != '', asite.LoginUrl, a.LoginUrl) AS LoginUrl, asite.LoginMethod, asite.LoginPostString, asite.LoginVerifyString, asite.LoginSuccUrl, IF(asite.TimeZone != '', asite.TimeZone, n.TimeZone) AS TimeZone, IF(asite.Charset != '', asite.Charset, n.Charset) AS Charset, asite.APIKey1, asite.APIKey2, asite.APIKey3, asite.APIKey4, asite.APIKey5, asite.APIKey6
                FROM account a
                INNER JOIN account_site asite ON a.AccountID = asite.AccountID
                INNER JOIN network n ON a.NetworkID = n.NetworkID
                INNER JOIN department d ON d.DepartmentID = a.DepartmentID
                WHERE asite.AccountSiteID = '{$tmpString}';";
        $arr = $this->objMysql->getFirstRow($sql);
        if (empty($arr)) mydie("die: getAffById failed, siteid = '{$siteId}' not found\n");

        //change the way how to get the password, the first choice from br01, if br01 don't find then from crawl_center.
        if (!$this->objPendingLinks) {
	        $this->objPendingLinks = new MysqlExt(PENDINGLINKS_DB_NAME,PENDINGLINKS_DB_HOST,PENDINGLINKS_DB_USER,PENDINGLINKS_DB_PASS,PENDINGLINKS_DB_SOCKET);
        }
	    $sql = "SELECT AffId FROM aff_crawl_config WHERE AccountSiteId='$siteId' LIMIT 1";
        $affId =  $this->objPendingLinks->getFirstRowColumn($sql);
        if ($affId) {
	        if (!$this->objBdg_go_base) {
		        $this->objBdg_go_base = new MysqlExt(BRO1_DB_NAME,BRO1_DB_HOST,BRO1_DB_USER,BRO1_DB_PASS,BRO1_DB_SOCKET);
	        }
	        $sql = "SELECT Password FROM wf_aff WHERE ID='$affId' LIMIT 1";
	        $arr['Password'] =  $this->objBdg_go_base->getFirstRowColumn($sql);
        }

        $arr['LoginPostString'] = str_replace("XXXXXX", urlencode($arr['UserName']), $arr['LoginPostString']);
        $arr['LoginPostString'] = str_replace("YYYYYY", urlencode($arr['Password']), $arr['LoginPostString']);
        $this->affiliates[$siteId] = $arr;
        return $arr;
    }

    function getBatchInfoByCrawlJobId($crawlJobId)
    {
        $crawlJobId = addslashes($crawlJobId);
        $sql = "SELECT BatchID,NetworkID,AccountID,AccountSiteID FROM batch WHERE CrawlJobId='$crawlJobId'";
        $arr = $this->objMysql->getRows($sql);

        return $arr;
    }

    function getBatchInfoByBatchId($batchId)
    {
        $batchId = addslashes($batchId);
        $sql = "SELECT BatchID,NetworkID,AccountID,AccountSiteID FROM batch WHERE BatchID='$batchId'";
        $arr = $this->objMysql->getRows($sql);

        return $arr;
    }

    function getSitesAccoutName($siteIds)
    {
        $siteIds = (string)$siteIds;
        if (empty($siteIds)) {
            return false;
        }
        $site_arr = explode(',', $siteIds);
        $site_arr = array_map(function ($c) {
            $a = intval($c);
            if ($a) {
                return $a;
            }
        }, $site_arr);
        $sql = "SELECT s.AccountSiteID,a.name FROM account a inner join account_site s on a.AccountID=s.AccountID WHERE s.AccountSiteID IN (" . join(',', $site_arr) . ")";
        $arr = $this->objMysql->getRows($sql, 'AccountSiteID');
        $result = array();
        foreach ($arr as $key => $val) {
            $result[$key] = $val['name'];
        }
        return $result;
    }

    /************************************ table batch **********************************/

    function startNewCrawlBatch($siteInfo, $crawlType, $crawlJobId)
    {
        $licesen = microtime(true) . '_' . $crawlJobId . '_' . $siteInfo['AccountSiteID'];

        $sql = "INSERT INTO batch(NetworkID, AccountID, AccountSiteID, CrawlType, CrawlStartTime, CrawlJobId, License) 
            VALUE ({$siteInfo['NetworkID']}, {$siteInfo['AccountID']}, {$siteInfo['AccountSiteID']}, '{$crawlType}', '" . date('Y-m-d H:i:s') . "', '$crawlJobId', '$licesen')";
        try {
            $this->objMysql->query($sql);
        } catch (Exception $e) {
            mydie('Start a new batch failed, Exception :' . $e->getMessage());
        }

        $sql = "SELECT batchId FROM batch WHERE License = '$licesen'";
        $batchId = $this->objMysql->getFirstRowColumn($sql);
        if (!$batchId) {
            mydie("Start a new batch failed！");
        }

        return $batchId;
    }

    function getBatchCrawlStatus($batchId)
    {
        $sql = "SELECT CrawlStatus FROM batch WHERE BatchID=$batchId";
        $result = $this->objMysql->getFirstRowColumn($sql);
        return $result;
    }

    function getBatchCheckStatus($batchId)
    {
        $sql = "SELECT CheckStatus FROM batch WHERE BatchID=$batchId";
        $result = $this->objMysql->getFirstRowColumn($sql);
        return $result;
    }

    function getBatchSyncStatus($batchId)
    {
        $sql = "SELECT SyncStatus FROM batch WHERE BatchID=$batchId";
        $result = $this->objMysql->getFirstRowColumn($sql);
        return $result;
    }

    function setBatchCrawlStatus($batchId, $status)
    {
        $sql = "UPDATE batch SET CrawlStatus='$status',CrawlEndTime='" . date('Y-m-d H:i:s') . "' WHERE batchID=$batchId";
        $this->objMysql->query($sql);
    }

    function setBatchCheckStatus($batchId, $status)
    {
        $sql = "UPDATE batch SET CheckStatus='$status' WHERE batchID=$batchId";
        $this->objMysql->query($sql);
    }

    function setBatchSyncStatus($batchId, $status)
    {
        $sql = "UPDATE batch SET SyncStatus='$status' WHERE batchID=$batchId";
        $this->objMysql->query($sql);
    }

    function checkTableUnlock($table_name, $retry = 10)
    {
        if ($retry < 0) {
            return false;
        }
        $sql = "SHOW OPEN TABLES LIKE '$table_name'";
        $open_table_info = $this->objMysql->getFirstRow($sql);
        if (empty($open_table_info) || !isset($open_table_info['In_use'])) {
            mydie("Table $table_name have something wrong!");
        }

        if ($open_table_info['In_use'] > 0) {           //The value of In_use mean the connecting num, In_use=0 mean no connect use this table;
            echo "$table_name is locking, will wait 1 secend and retry." . PHP_EOL;
            sleep(1);
            return $this->checkTableUnlock($table_name, --$retry);
        } else {
            return true;
        }
    }

    /************************************ table crawl_job_batch **********************************/
    function getMaxCrawlJobID($siteIDs, $method = false)
    {
        if ($siteIDs) {
            $siteIDs = addslashes($siteIDs);
            $crawlType = ucfirst(preg_replace('@(get)|(check)|(sync)@', '', $method));
            preg_match("@(get|check|sync)" . strtolower($crawlType) . "@", $method, $m);
            $optionType = $m[1];

            $sql = "SELECT CrawlJobID,BaseDataCrawlStatus,BaseDataCheckStatus,BaseDataSyncStatus FROM crawl_job_batch WHERE AccountSiteIDs='$siteIDs'";
            if ($crawlType) {
                $sql .= " AND CrawlType='$crawlType' ";
            }
            if ($crawlType == 'Program') {
                $sql .= " AND IsFullCrawl='YES' ";
            }
            $sql .= " ORDER BY CrawlJobID DESC limit 1";

            $result = $this->objMysql->getFirstRow($sql);
            if ($optionType == 'check' && isset($result['BaseDataCheckStatus']) && $result['BaseDataCheckStatus'] == 'Uncheck' && $result['BaseDataCrawlStatus'] == 'Done') {
                return $result['CrawlJobID'];
            }
            if ($optionType == 'sync' && isset($result['BaseDataSyncStatus']) && $result['BaseDataSyncStatus'] == 'Unsync' && $result['BaseDataCheckStatus'] == 'Done') {
                return $result['CrawlJobID'];
            }

            $sql = "SELECT CrawlJobID FROM crawl_job_batch WHERE AccountSiteIDs='$siteIDs' AND CrawlType='$crawlType' ORDER BY CrawlJobID DESC limit 1";
        } else {
            $sql = 'SELECT CrawlJobID FROM crawl_job_batch ORDER BY CrawlJobID DESC limit 1';
        }
        return $this->objMysql->getFirstRowColumn($sql);
    }

    function startNewCrawlJob($siteID, $crawlType, $logFile, $baseDataCrawlStatus, $isFull = 'YES')
    {
        $licesen = microtime(true) . '_' . $siteID . '_' . rand(1000000, 9999999);

        $isFull = !$isFull ? 'NO' : 'YES';

        $siteId_first = current(explode(',', trim($siteID)));
        $sql = "select a.NetworkID from account a left join account_site b on a.AccountID=b.AccountID where b.AccountSiteID='$siteId_first'";
        $NetworkID = $this->objMysql->getFirstRowColumn($sql);

        $siteID = addslashes($siteID);
        $logFile = addslashes($logFile);
        $crawlType = addslashes($crawlType);
        $sql = "INSERT INTO crawl_job_batch(NetworkID, AccountSiteIDs, CrawlType, BaseDataCrawlStartTime, IsFullCrawl, BaseDataCrawlStatus, LogFilePath, License) 
            VALUE ('$NetworkID', '$siteID', '$crawlType', '" . date('Y-m-d H:i:s') . "', '$isFull', '$baseDataCrawlStatus', '$logFile', '$licesen')";
        try {
            $this->objMysql->query($sql);
        } catch (Exception $e) {
            mydie('Start a new crwalJob failed, Exception :' . $e->getMessage());
        }

        $sql = "SELECT CrawlJobId FROM crawl_job_batch WHERE License = '$licesen'";
        $crawlJobId = $this->objMysql->getFirstRowColumn($sql);
        if (!$crawlJobId) {
            mydie("Start a new crwalJob failed！");
        }

        return $crawlJobId;
    }

    /**
     * @param int|string $batchId
     * @return mixed|string
     */
    function getCrawlJobIdByBatchId($batchId)
    {
        $sql = "SELECT CrawlJobId FROM batch WHERE BatchId=$batchId";
        return $result = $this->objMysql->getFirstRowColumn($sql);
    }

    function getJobCrawlStatus($crawlJobId)
    {
        $sql = "SELECT BaseDataCrawlStatus FROM crawl_job_batch WHERE CrawlJobID=$crawlJobId";
        $result = $this->objMysql->getFirstRowColumn($sql);
        return $result;
    }

    function getJobCheckStatus($crawlJobId)
    {
        $sql = "SELECT BaseDataCheckStatus FROM crawl_job_batch WHERE CrawlJobID=$crawlJobId";
        $result = $this->objMysql->getFirstRowColumn($sql);
        return $result;
    }

    function getJobSyncStatus($crawlJobId)
    {
        $sql = "SELECT BaseDataSyncStatus FROM crawl_job_batch WHERE CrawlJobID=$crawlJobId";
        $result = $this->objMysql->getFirstRowColumn($sql);
        return $result;
    }

    function setJobCrawlStatus($crawlJobId, $status)
    {
        $sql = "UPDATE crawl_job_batch SET BaseDataCrawlStatus='$status',BaseDataCrawlEndTime='" . date('Y-m-d H:i:s') . "' WHERE CrawlJobID=$crawlJobId";
        $this->objMysql->query($sql);
    }

	function setJobCrawlLogPath($crawlJobId, $log_path)
	{
		$sql = "UPDATE crawl_job_batch SET LogFilePath='$log_path' WHERE CrawlJobID=$crawlJobId";
		$this->objMysql->query($sql);
	}

    function setJobCheckStatus($crawlJobId, $status, $log_content = '')
    {
    	$log_update_sql = '';
    	if ($log_content) {
		    $log_update_sql = ",CheckResult='" . addslashes($log_content) . "'";
	    }
        $sql = "UPDATE crawl_job_batch SET BaseDataCheckStatus='$status' $log_update_sql WHERE CrawlJobID=$crawlJobId";
        $this->objMysql->query($sql);
    }

    function setJobSyncStatus($crawlJobId, $status)
    {
        $sql = "UPDATE crawl_job_batch SET BaseDataSyncStatus='$status' WHERE CrawlJobID=$crawlJobId";
        $this->objMysql->query($sql);
    }

	function setJobCheckExpired($crawlJobId, $crawlType)
	{
		$sql = "select AccountSiteIDs from crawl_job_batch where CrawlJobID = '$crawlJobId'";
		$siteIds = $this->objMysql->getFirstRowColumn($sql);
		if (!$siteIds) {
			return false;
		}
		$sql = "update crawl_job_batch a left join batch b on a.crawlJobId=b.crawlJobId set a.BaseDataCheckStatus='Expired',b.CheckStatus='Expired' where a.AccountSiteIDs='$siteIds' and a.BaseDataCheckStatus='Uncheck' AND a.CrawlJobID<'$crawlJobId' and a.BaseDataCrawlStartTime>'" . date('Y-m-d H:i:s', time() - 2*24*60*60) . "'";
		if ($crawlType) {
			$sql .= " and a.CrawlType='$crawlType'";
		}

		$this->objMysql->query($sql);
	}

	function analyzeCheckResult($crawlJobId, $log_content)
	{
		if (!$crawlJobId || !$log_content) {
			return false;
		}
		$change_arr = $batchId_arr = array();
		$sql = "select NetworkID,AccountSiteIDs from crawl_job_batch where CrawlJobID='$crawlJobId'";
		$result = $this->objMysql->getFirstRow($sql);
		$networkId = $result['NetworkID'];

		//first of all check the change for every batch site data;
		$log_arr = explode('Check site data end @ ', $log_content);
		foreach ($log_arr as $val) {
			preg_match('@Check site\(siteId=([0-9]{1,5}) and batchId=([0-9]{1,10})\) data start@', $val, $m);

			if (empty($m)) {
				continue;
			}

			$siteId = $m[1];
			$batchId = $m[2];
			$batchId_arr[] = $batchId;

			preg_match('@The change fields and num detail like that\:([\s\S]*)This batch has \([0-9]{1,10}\) programs@',$val, $check_result);

			if (!empty($check_result[1])) {  //that's mean this site data in this batch have no different with the old usable site data;
				//find out the change fields and idinaff list
				preg_match_all('@\s*([a-zA-Z]{1,30})\s=>\s\d+\s*@', $check_result[1], $batch_err_arr);
				$batch_err_arr = $batch_err_arr[1];
				foreach ($batch_err_arr as $fieldName) {
					$sql = "SELECT bpac.IdInAff FROM batch_program_account_site_$networkId bpac
						INNER JOIN program_account_site_$networkId pac ON bpac.IdInAff=pac.IdInAff AND bpac.AccountSiteID=pac.AccountSiteID
						WHERE bpac.BatchID=$batchId AND bpac.$fieldName <> pac.$fieldName";
					$result = $this->objMysql->getRows($sql, "IdInAff");
					$change_arr['batch'][$batchId]['change_IdInAff_list'][$fieldName] = array_keys($result);
				}
			}

			if (stripos($val,"This batch(batchid=$batchId) data have more than 5% programs offline!") !== false) {
				//find out the offline program list
				$sql = "select IdInAff from batch_program_account_site_$networkId where AccountSiteID=$siteId and BatchId=$batchId limit 200000";
				$result = $this->objMysql->getRows($sql, "IdInAff");
				$idList = array_keys($result);
				$sql = "select IdInAff from program_account_site_$networkId where AccountSiteID=$siteId and LastUpdateTime>'" . date('Y-m-d H:i:s', time() - 86400) . "' and IdInAff not in ('". join("','", $idList) ."')";
				$offlineIdInAffList = $this->objMysql->getRows($sql, 'IdInAff');
				$offlineIdInAffList = array_keys($offlineIdInAffList);
				$change_arr['batch'][$batchId]['offline_IdInAff_list'] = $offlineIdInAffList;
			}
		}

		if (stripos($log_content, 'Check job (crawlJobId=') !== false) {
			$job_arr = explode('Check job (crawlJobId=', $log_content);
			$jobChangeList = end($job_arr);
			preg_match('@The change fields and num detail like that\:([\s\S]*)This job has \([0-9]{1,10}\) programs@',$jobChangeList, $CheckJobResult);
			if (!empty($CheckJobResult[1])) {
				preg_match_all('@\s*([a-zA-Z]{1,30})\s=>\s\d+\s*@', $CheckJobResult[1], $job_err_arr);
				$job_err_arr = $job_err_arr[1];
				foreach ($job_err_arr as $fieldName) {
					$sql = "SELECT p.IdInAff FROM program_$networkId p 
							INNER JOIN batch_program_$networkId bp ON p.`IdInAff`=bp.`IdInAff` 
							WHERE bp.`BatchID` IN ('" . join("','", $batchId_arr) . "') AND p.$fieldName <> bp.$fieldName";
					$result = $this->objMysql->getRows($sql, "IdInAff");
					$change_arr['basic']['change_IdInAff_list'][$fieldName] = array_keys($result);
				}
			}
		}

		if (!empty($change_arr)) {
			$sql = "update crawl_job_batch set CheckAnalyzeResult='". addslashes(json_encode($change_arr)) ."' where CrawlJobID='$crawlJobId'";
			$this->objMysql->query($sql);
		}
		return true;
	}

	/***************************************** helpful function ***********************************/

	function getCompareField($networkId, $crawlType)
	{
		if(isset($this->compareFields[$networkId][$crawlType]) && !empty($this->compareFields[$networkId][$crawlType])){
			return $this->compareFields[$networkId][$crawlType];
		}

		$sql = "SELECT FieldName,NeedCheckChange FROM network_crawl_fields WHERE NetworkID='" . trim($networkId) . "' AND CrawlType='" . trim($crawlType) . "'";
		$data = $this->objMysql->getRows($sql, 'FieldName');
		$this->compareFields[$networkId][$crawlType] = $data;

		return $data;
	}

	function getTableFields($table_name)
	{
		if (isset($this->tableFields[$table_name])) {
			return $this->tableFields[$table_name];
		}

		if (!$this->objMysql->isTableExisting($table_name)){
			mydie("table $table_name not exits.");
		}

		$getFieldSql = "select COLUMN_NAME from information_schema.COLUMNS where table_name = '$table_name'";
		$fields = $this->objMysql->getRows($getFieldSql);
		$fields = array_map(function ($f) {return $f['COLUMN_NAME'];}, $fields);
		$fields = array_flip($fields);
		$this->tableFields[$table_name] = $fields;

		return $this->tableFields[$table_name];
	}

/******************************* for transaction check ********************************
    /**
     * get the transactionIds whose the transaction crawl without sid lost number.
     *
     * @param int $networkId
     * @param int $batchId
     * @param int $siteId
     * @return array $lostTransactionIds
     */
    function getTransactionSidLost($networkId, $batchId, $siteId)
    {
        $sql = 'select TransactionSidField from network where networkid=' . $networkId;
        $sidFieldName = $this->objMysql->getFirstRowColumn($sql);
        $sql = "select transactionid from batch_transaction_$networkId where batchid=$batchId and accountSiteId=$siteId and $sidFieldName=''";
        $lostTransactionIds = $this->objMysql->getRows($sql, 'transactionid');
        return array_keys($lostTransactionIds);
    }

    /**
     * Save the transactionIds who
     *
     *
     * ··se without sid transaction crawl records by json.
     *
     * @param int|string $crawlJobId
     * @param string $records
     * @return void
     * @throws MegaException
     */
    function setSidLostRecords($crawlJobId, $records)
    {
        $sql = "update crawl_job_batch set checkAnalyzeResult='" . addslashes($records) . "' where crawljobid=$crawlJobId";
        $this->objMysql->query($sql);
    }
/***************************************** end *************************************/

}


















?>