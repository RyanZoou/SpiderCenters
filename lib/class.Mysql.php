<?php
class Mysql
{
	var $host     = "";
	var $database = "";
	var $user     = "";
	var $password = "";
	var $record   = array();
	var $isPConnect = FALSE;
	var $linkID   = NULL;
	var $queryID  = NULL;

	function Mysql($database = PROD_DB_NAME, $host = PROD_DB_HOST, $user = PROD_DB_USER, $password = PROD_DB_PASS)
	{
		$this->host     = $host;
		$this->database = $database;
		$this->user     = $user;
		$this->password = $password;
		$this->connect();				
	}

	function connect()
	 {
		if (is_null($this->linkID) || !is_resource($this->linkID) || strcasecmp(get_resource_type($this->linkID), "mysql link") <> 0)
		{
			if (!$this->isPConnect)
			{
				$this->linkID = @mysql_connect($this->host, $this->user, $this->password, true);
			}
			else
			{
				$this->linkID = @mysql_pconnect($this->host, $this->user, $this->password);
			}
			if(defined('TIME_ZONE'))
			{
				$sql = "SET time_zone = '".TIME_ZONE."'";
				$this->query($sql);
			}
			if(defined('MYSQL_SET_NAMES'))
			{
				$sql = "SET NAMES ".MYSQL_SET_NAMES;
				$this->query($sql);
			}
		}
		if (!is_resource($this->linkID) || strcasecmp(get_resource_type($this->linkID), "mysql link") <> 0)
		{
			throw new MegaException("can not connect to ".$this->host.", ".mysql_error($this->linkID).", ".mysql_errno($this->linkID));
		}
	}
	
	function reconnect()
	{
		$this->close();
		$this->connect();
	}
	
	function query($sql)
	{
		$result = null;
		
		if($sql == "") throw new MegaException("query string was empty");
		if($this->queryID) $this->queryID = NULL;
		if(!mysql_ping($this->linkID)) $this->reconnect();
		
		if (!mysql_select_db($this->database, $this->linkID))
		{
			//very strang here: sometimes changing DB is failed ...
			$this->reconnect();
			if (!mysql_select_db($this->database, $this->linkID))
			{
				throw new MegaException("can not use the database ".$this->database.", ".mysql_error($this->linkID).", ".mysql_errno($this->linkID));
			}
		}
		
		$this->queryID = @mysql_query($sql, $this->linkID);
		if(!$this->queryID) throw new MegaException("query failed: $sql, ".mysql_error($this->linkID).", ".mysql_errno($this->linkID));
		return $this->queryID;
	}

	function getRow($queryID = "", $fetchType = MYSQL_ASSOC)
	{
		$result = array();
		if(!$queryID) $queryID = $this->queryID;
		if(!is_resource($queryID))
		{
			throw new MegaException("invalid query id, can not get the result from DB result");
		}
		$this->record = @mysql_fetch_array($queryID, $fetchType);
		if(is_array($this->record)) $result = $this->record;
		return $result;
	}

	function getNumRows($qryId = "")
	{
		if(is_resource($qryId)) return @mysql_num_rows($qryId);
		return @mysql_num_rows($this->queryID);
	}

	function getAffectedRows()
	{	
		return @mysql_affected_rows($this->linkID);
	}
	
	function getLastInsertId()
	{
		return @mysql_insert_id($this->linkID);
	}
	
	function freeResult($queryID = "")
	{
		if(!is_resource($queryID)) return @mysql_free_result($this->queryID);	
		return @mysql_free_result($queryID);
	}
	
	function close()
	{
		if($this->linkID) @mysql_close($this->linkID);
	}
	
	function getFirstRow(&$sql)
	{
		$rows = $this->getRows($sql);
		if(is_array($rows) && sizeof($rows) > 0) return current($rows);
		return array();
	}
	
	function getFirstRowColumn(&$sql,$keyname="")
	{
		$first_row = $this->getFirstRow($sql);
		if(sizeof($first_row) == 0) return "";
		if($keyname == "") return current($first_row);
		if(isset($first_row[$keyname])) return $first_row[$keyname];
		return "";
	}
	
	function getRows(&$sql,$keyname="",$foundrows=false)
	{
		$arr_return = array();
		if($foundrows && strpos(substr($sql,0,30),"SQL_CALC_FOUND_ROWS") === false)
		{
			if(stripos($sql,"select") === 0) $sql = "select SQL_CALC_FOUND_ROWS" . substr($sql,6);
		}
		$qryId = $this->query($sql);
		if(!$qryId) {
			return $arr_return;
		}

		if($keyname) $keys = explode(",",$keyname);
		else $i = 0;

		while($row = mysql_fetch_array($qryId,MYSQL_ASSOC))
		{	
			if($keyname)
			{	
				$arr_temp = array();
				foreach($keys as $key) $arr_temp[] = $row[$key];
				$key_value = implode("\t",$arr_temp);
			}
			else
			{
				$key_value = $i++;
			}
			$arr_return[$key_value] = $row;
		}
		if($foundrows) $this->getFoundRows();
		$this->freeResult($qryId);
		return $arr_return;
	}
	
	function getFoundRows()
	{
		$sql = "SELECT FOUND_ROWS()";
		$this->FOUND_ROWS = $this->getFirstRowColumn($sql);
		if(!is_numeric($this->FOUND_ROWS)) $this->FOUND_ROWS = 0;
		return $this->FOUND_ROWS;
	}
}
?>