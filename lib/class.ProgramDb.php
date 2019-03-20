<?php
class ProgramDb
{
    var $basicProgramIdinaffList = array();

    function __construct()
    {
        if (!isset($this->objMysql)) {
            $this->objMysql = new MysqlExt(PROD_DB_NAME, PROD_DB_HOST, PROD_DB_USER, PROD_DB_PASS);
        }
	    $this->oLinkFeed = new LinkFeed();
    }

    function InsertProgramBatch($networkId, $arr, $cacheBaseInfoIdInAff=false)
    {
        if (empty($arr)) {
            return false;
        }
        $site_table_name = "batch_program_account_site_$networkId";
        $base_table_name = "batch_program_$networkId";

        $base_fields = $this->oLinkFeed->getTableFields($base_table_name);
        unset($base_fields['AddTime']);
        unset($base_fields['BatchID']);
        unset($base_fields['IdInAff']);
        $base_fields = array_keys($base_fields);

        $base_arr = array();
        foreach ($arr as $key => $val) {
            $commKeys = array_intersect($base_fields, array_keys($val));
            if (!empty($commKeys)) {
                $base_arr[$key] = $val;
            }
        }

        $this->updateProgramBatch($site_table_name, $arr);

        if (!empty($base_arr)) {
            $this->updateProgramBatch($base_table_name, $base_arr, $cacheBaseInfoIdInAff);
        }
    }

    function updateProgramBatch($table_name, $arr, $cacheBaseInfoIdInAff = false)
    {
        if (empty($arr)) {
            return false;
        }
        $arr_update = array();
        $firstArr = current($arr);
        if ($cacheBaseInfoIdInAff && !isset($firstArr['CrawlJobId'])) {
            mydie("Params error when insert site data to batch table!");
        }

        $idInAff = array_keys($arr);
        if (stripos($table_name, 'account_site') !== false) {
            if (!isset($firstArr['BatchID']) || !isset($firstArr['AccountSiteID']) || empty($firstArr['BatchID']) || empty($firstArr['AccountSiteID'])){
                mydie("Params error when insert site data to batch table!");
            }
            $sql = "SELECT IdInAff FROM $table_name WHERE BatchID = ".intval($firstArr['BatchID'])." AND AccountSiteID =" .intval($firstArr['AccountSiteID']). " AND IdInAff IN ('".implode("','",$idInAff)."')";
        } else {
            if (!isset($firstArr['BatchID']) || empty($firstArr['BatchID'])){
                mydie("Params error when insert base data to batch table!");
            }
            $sql = "SELECT IdInAff FROM $table_name WHERE BatchID = ".intval($firstArr['BatchID'])." AND IdInAff IN ('".implode("','",$idInAff)."')";
        }
        $return_arr = $this->objMysql->getRows($sql,"IdInAff");
        foreach($return_arr as $k => $v) {
            if(isset($arr[$k]) === true) {
                $arr_update[$k] = $arr[$k];
                unset($arr[$k]);
            }
        }
        unset($return_arr);

        if(count($arr)){
            $this->doInsertProgram($table_name, $arr);
        }
        if(count($arr_update)){
            $this->doUpdateProgram($table_name, $arr_update);
        }

        //insert base info cache!
        if ($cacheBaseInfoIdInAff) {
            $sql = "SELECT IdInAff FROM crawl_job_idinaff_cache WHERE CrawlJobID=" . intval($firstArr['CrawlJobId']) . " AND IdInAff IN ('" . join("','", $idInAff) . "') AND HaveCrawlBaseInfo='YES'";
            $return_arr = $this->objMysql->getRows($sql,"IdInAff");

            $exists_list = array_keys($return_arr);
            $update_list = array_intersect($idInAff, $exists_list);
            $insert_list = array_diff($idInAff, $exists_list);

            if (!empty($insert_list)) {
                $inner_arr = array();
                foreach ($insert_list as $val) {
                    $inner_arr[] = '(' . intval($firstArr['CrawlJobId']) . ",'" . $val . "','YES')";
                }
                $sql = "INSERT INTO crawl_job_idinaff_cache(CrawlJobID, IdInAff, HaveCrawlBaseInfo) VALUES " . join(',', $inner_arr);
                $this->objMysql->query($sql);
            }
            if (!empty($update_list)) {
                $sql = "UPDATE crawl_job_idinaff_cache SET HaveCrawlBaseInfo='YES' WHERE CrawlJobID=" . intval($firstArr['CrawlJobId']) . " AND IdInAff IN ('" . join("','", $update_list) . "')";
                $this->objMysql->query($sql);
            }
        }
    }

    function doInsertProgram($table_name, $arr)
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
            $fields_values[$key]['AddTime'] = date('Y-m-d H:i:s');
            $value_list[] = "('".join("','", array_values($fields_values[$key]))."')";
        }

        $sql = "INSERT INTO $table_name (" . join(",", array_keys(current($fields_values))) . ") VALUES " . join(',', $value_list);
        try {
            $this->objMysql->query($sql);
        } catch (Exception $e) {
            echo $e->getMessage()."\n";
            return false;
        }

        return true;
    }

    function doUpdateProgram($table_name, $arr)
    {
        $firstArr = current($arr);
        if (stripos($table_name, 'account_site') !== false) {
            if (!isset($firstArr['BatchID']) || !isset($firstArr['AccountSiteID']) || empty($firstArr['BatchID']) || empty($firstArr['AccountSiteID'])){
                mydie("Params error when update site data to batch table!");
            }
            $where = ' BatchID =' .intval($firstArr['BatchID']) . ' AND AccountSiteID =' . intval($firstArr['AccountSiteID']) . ' ';
        } else {
            if (!isset($firstArr['BatchID']) || empty($firstArr['BatchID'])){
                mydie("Params error when update base data to batch table!");
            }
            $where = ' BatchID =' .intval($firstArr['BatchID']) . ' ';
        }

        $fields = $this->oLinkFeed->getTableFields($table_name);

        foreach($arr as $key => $val){
            if (isset($val['Name'])) {
                $val['Name'] = html_entity_decode($val['Name']);
            }

            $field_update = array();
            foreach($val as $k => $v){
                $v = trim($v);
                if (in_array($k, array('BatchID', 'IdInAff', 'AccountSiteID'))) {
                    continue;
                }
                if(isset($fields[$k]) && !empty($v)){
                    $field_update[] = "$k = '".$v."'";
                }
            }

            if(count($field_update)){
                $sql = "UPDATE $table_name SET ".implode(",", $field_update)." WHERE $where AND IdInAff = '".$val["IdInAff"]."'";
                try {
                    $this->objMysql->query($sql);
                } catch (Exception $e) {
                    echo $e->getMessage()."\n";
                }
            }
        }
    }

    function checkSiteBatchDataChange($networkId, $siteId, $batchId)
    {
        $return_arr = array();
        $change_idinaff_list = array();
        $allProgramNum = 0;

        $pos = 0;
        $limit = 1;
        $warning = 100000;
        while(1){
            $sql = "SELECT * FROM batch_program_account_site_{$networkId} WHERE BatchID='".trim($batchId)."' AND AccountSiteID='".trim($siteId)."' limit $pos, $limit";
            $fieldsVal = $this->objMysql->getRows($sql);
            if(count($fieldsVal)){
                foreach ($fieldsVal as $val) {
                    $allProgramNum ++;
                    $cResult = $this->insertProgramChangeLog($val, $networkId, $siteId);

                    if (!empty($cResult)) {
                        $change_idinaff_list[$cResult['IdInAff']] = $cResult['Fields'];
                    }

                }
                $pos += $limit;
            } else {
                break;
            }

            if($pos > $warning){
                mydie('The num of compareProgramBatchDataChange > '. $warning);
            }
        }
        $return_arr['total_num'] = $allProgramNum;
        $return_arr['change_detail'] = $change_idinaff_list;

        return $return_arr;
    }

    function checkBaseBatchDataChange($networkId, $crawlJobId)
    {
        $return_arr = array();
        $change_idinaff_list = array();
        $allBaseProgramNum = 0;

        $pos = 0;
        $limit = 1;
        $warning = 100000;
        while(1){
            $sql = "SELECT * FROM batch_program_{$networkId} WHERE batchID IN (SELECT BatchID FROM batch WHERE CrawlJobId='$crawlJobId') limit $pos, $limit";
            $fieldsVal = $this->objMysql->getRows($sql);
            if(count($fieldsVal)){
                foreach ($fieldsVal as $val) {
                    $allBaseProgramNum ++;
                    $cResult = $this->insertProgramChangeLog($val, $networkId);
                    if (!empty($cResult)) {
                        $change_idinaff_list[$cResult['IdInAff']] = $cResult['Fields'];
                    }
                }
                $pos += $limit;
            } else {
                break;
            }

            if($pos > $warning){
                mydie('The num of compareProgramBatchDataChange > '. $warning);
            }
        }
        $return_arr['total_num'] = $allBaseProgramNum;
        $return_arr['change_detail'] = $change_idinaff_list;

        return $return_arr;
    }

    function insertProgramChangeLog($row, $networkId, $siteId='')
    {
        if (empty($row) || !($row["IdInAff"]) || !$networkId) {
            return false;
        }

        $change_arr = array();

        $programInfo = $this->getProgramByIdInAff($networkId, $row["IdInAff"], $siteId);
//        echo 'new:';print_r($row);
//        echo 'old:';print_r($programInfo);

        $allChangeData = $this->compareFieldValue($networkId, $programInfo, $row);

        if (!empty($allChangeData['rule'])) { //规则内变化的记录log
            $insertConstantData = array(
                'BatchID' => $row['BatchID'],
                'OldBatchID' => $programInfo['BatchID'],
                'NetworkID' => $networkId,
                'IdInAff' => $row["IdInAff"],
                'AccountSiteID' => $siteId,
                'ChangeTime'   => date("Y-m-d H:i:s"),
            );

            $change_arr["IdInAff"] = $row["IdInAff"];

            foreach ($allChangeData['rule'] as $key => $val) {
                if($key == "LastUpdateTime" || $key == "BatchID") {
                    continue;
                }

                $change_arr["Fields"][] = $key;

                $insertData = $insertConstantData;
                $insertData['FieldName'] = $key;

                $sql = "insert ignore into `batch_program_changelog` ";
                $fields = $values = '';

                foreach ($insertData as $k => $v) {
                    $fields .= "`" . $k . "`, ";
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

    function compareFieldValue($networkId, $from = array(), $to = array())
    {
        $data['normal'] = $data['rule'] = array();
	    $field_arr = $this->oLinkFeed->getCompareField($networkId, 'Program');

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

    function getProgramByIdInAff($networkId, $idInAff, $siteId='')
    {
        $data = array();
        if (empty($networkId) || empty($idInAff)) {
            return $data;
        }

        if ($siteId) {
            $sql = "SELECT * FROM program_account_site_{$networkId} WHERE AccountSiteID = '".addslashes($siteId)."' AND IdInAff='".addslashes($idInAff)."'";
        } else {
            $sql = "SELECT * FROM program_{$networkId} WHERE IdInAff='".addslashes($idInAff)."'";
        }

        if ($query = $this->objMysql->query($sql)) {
            $data = $this->objMysql->getRow($query);
        }
        return $data;
    }

    function syncSiteProgramBatchData($batchId, $networkId, $siteId)
    {
        try {
            $site_field_list = array_keys($this->oLinkFeed->getTableFields("program_account_site_{$networkId}"));

            $pos = $i = 0;
            $limit = 10;
            $warning = 100000;
            while (1) {
                $rSql = "SELECT * FROM batch_program_account_site_{$networkId} WHERE BatchID ='" . trim($batchId) . "' AND AccountSiteID='" . trim($siteId) . "' limit $pos, $limit";
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
                    $sql = "REPLACE INTO program_account_site_{$networkId} (" . implode(",", $site_field_list) . ") VALUES" . implode(",", $rp_value_list);
                    $this->objMysql->query($sql);
                } else {
                    break;
                }

                $pos += $limit;
                $i++;

                if ($i * $limit > $warning) {
                    echo "\tThis batch get too many programs, please check the reason.";
                    return false;
                }
            }
            echo "\tIn this batch, $pos programs haven crawled site info data, and they were synced!.\n";

        }catch (MegaException $e){
            echo $e->getMessage();
            return false;
        }

        return true;
    }

    function syncBaseProgramBatchData($networkId, $crawlJobId)
    {
        $crawlJobId = addslashes($crawlJobId);
        try {
            $field_list = array_keys($this->oLinkFeed->getTableFields("program_{$networkId}"));

            $pos = $i = 0;
            $limit = 1;
            $warning = 100000;
            while (1) {
                $sql = "SELECT * FROM batch_program_{$networkId} WHERE batchID IN (SELECT BatchID FROM batch WHERE CrawlJobId='$crawlJobId') limit $pos, $limit";
                $program_arr = $this->objMysql->getRows($sql);

                if (!empty($program_arr)) {
                    $p_value_list = array();
                    foreach ($program_arr as $rv) {
                        $val_row = array();
                        foreach ($field_list as $index => $fv) {
                            if ($fv != 'LastUpdateTime'){
                                $val_row[$index] = addslashes($rv[$fv]);
                            } else {
                                $val_row[$index] = date('Y-m-d H:i:s');
                            }
                        }
                        $p_value_list[] = "('" . implode("','", $val_row) . "')";
                    }
                    if ($p_value_list) {
                        $sql = "REPLACE INTO program_{$networkId} (" . implode(",", $field_list) . ") VALUES" . implode(",", $p_value_list);
                        $this->objMysql->query($sql);
                    }
                } else {
                    break;
                }

                $pos += $limit;
                $i++;

                if ($i * $limit > $warning) {
                    echo "\tThis batch get too many programs, please check the reason.";
                    return false;
                }
            }
            echo "\tIn this job, $pos programs haven crawled base info data, and they were synced!.\n";

        }catch (MegaException $e){
            echo $e->getMessage();
            return false;
        }

        return true;
    }

    function checkBaseProgramInfoExistsByIdinaff($networkId, $idinaff, $crawlJobId)
    {
        if (!isset($this->basicProgramIdinaffList[$networkId]) || empty($this->basicProgramIdinaffList[$networkId])) {
            if (!$networkId || !$crawlJobId) {
                mydie("params error!");
            }
            $crawlJobId = intval($crawlJobId);

            $idinaff_list = array();

            $pos = 0;
            $limit = 1000;
            while(1){
                $sql = "SELECT IdInAff FROM crawl_job_idinaff_cache WHERE CrawlJobID = {$crawlJobId} limit $pos, $limit";
                $fieldsVal = $this->objMysql->getRows($sql);
                if(count($fieldsVal)){
                    foreach ($fieldsVal as $val) {
                        $idinaff_list[] = $val['IdInAff'];
                    }
                    $pos += $limit;
                } else {
                    break;
                }
            }

            $this->basicProgramIdinaffList[$networkId] = $idinaff_list;
        }
//        print_r($this->basicProgramIdinaffList);exit;

        if (!in_array($idinaff, $this->basicProgramIdinaffList[$networkId])) {
            $this->basicProgramIdinaffList[$networkId][] = $idinaff;
            return false;
        }

        return true;
    }

    function getCountryCode()
    {
        $sql = 'SELECT CountryCode,CountryName FROM country_codes LIMIT 1000';
        $result = $this->objMysql->getRows($sql, "CountryCode");
        $data = array_map(function ($counArr) {return $counArr['CountryName'];}, $result);
        return $data;
    }

    function getBatchProgramIdInAffList($networkId, $siteId, $batchId)
    {
        if (!$networkId || !$siteId || !$batchId) {
            mydie('Get batch program idinaff list failed, params wrong!');
        }
        $sql = "SELECT IdInAff FROM batch_program_account_site_$networkId WHERE BatchID=$batchId AND AccountSiteID=$siteId LIMIT 100000";
        try{
            $result = $this->objMysql->getRows($sql, "IdInAff");
        } catch (Exception $e) {
            mydie("Get batch program idinaff list failed : " . $e->getMessage());
        }
        return array_keys($result);
    }

    function setProgramOffLine($networkId, $crawlJobId)
    {
        $check_date = date('Y-m-d H:i:s', time() - 86400);
        $sql = "SELECT IdInAff FROM program_$networkId WHERE BatchID NOT IN (SELECT BatchID FROM batch WHERE CrawlJobId=$crawlJobId) AND LastUpdateTime>'$check_date'";
        echo $sql;
        $result = $this->objMysql->getRows($sql, "IdInAff");
        if (count($result) > 30) {
            mydie('Die: more than 30 programs offline!');
        }

        $id_list = join("','", array_keys($result));
        $sql = "UPDATE program_$networkId SET StatusInAff='Offline' WHERE IdInAff in ('$id_list')";
        $this->objMysql->query($sql);
    }

    function checkProgramOffline($networkId,$batchId, $siteId)
    {
	    $sql = "select IdInAff from batch_program_account_site_$networkId where AccountSiteID=$siteId and BatchId=$batchId limit 200000";
	    $result = $this->objMysql->getRows($sql, "IdInAff");
	    $idList = array_keys($result);
	    $sql = "select count(1) from program_account_site_$networkId where AccountSiteID=$siteId and LastUpdateTime>'" . date('Y-m-d H:i:s', time() - 86400) . "' and IdInAff not in ('". join("','", $idList) ."')";
	    $offlineNum = $this->objMysql->getFirstRowColumn($sql);
	    $sql = "select count(1) from program_account_site_$networkId where AccountSiteID=$siteId and LastUpdateTime>'" . date('Y-m-d H:i:s', time() - 86400) . "'";
	    $total_num = $this->objMysql->getFirstRowColumn($sql);

	    if ($total_num <= 30 || $networkId == 2030) {
	    	return 0;
	    }else {
		    return $offlineNum / $total_num;
	    }

    }
}

?>
