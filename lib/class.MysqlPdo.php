<?php
class MysqlPdo {
	private $host     	= "";
	private $database 	= "";
	private $user     	= "";
	private $password 	= "";
	private $sth  	= null;
	private $pdo  		= null;
	private $queryArr 	= array();
	public $recordQueryMode = false;
	public $raiseErrorMode = DEBUG_MODE;
	
	public function __construct($database = PROD_DB_NAME, $host = PROD_DB_HOST, $user = PROD_DB_USER, $password = PROD_DB_PASS, $socket = PROD_DB_SOCKET)
    {
		$this->host     = $host;
		$this->database = $database;
		$this->user     = $user;
		$this->password = $password;
		$this->socket	= $socket;
		$this->connect();
	}
	
	public function connect()
	{
	    if (!$this->pdo || !$this->pdo_ping($this->pdo)) {
            if ($this->socket && ($this->host == "localhost" || $this->host == "127.0.0.1")) {
                $pdo = "mysql:unix_socket=" . $this->socket . ";host=" . $this->host . ";dbname=" . $this->database;
            } else {
                $pdo = "mysql:host=" . $this->host . ";dbname=" . $this->database;
            }

            if (defined('MYSQL_SET_NAMES')) {
                $pdo .= ";charset=" . MYSQL_SET_NAMES;
            }

            try {
                $this->pdo = new PDO($pdo, $this->user, $this->password, array(PDO::ATTR_PERSISTENT=>true));
                if ($this->recordQueryMode) {
                    $this->setLog("connected to " . $this->host . " " . $this->user . " " . $this->database);
                }
            } catch (PDOException $e) {
                self::raiseError("Connect failed, " . $this->host . ", " . $e->getMessage(), __FILE__, __LINE__);
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (defined('TIME_ZONE')) {
                $this->pdo->exec("SET time_zone = '" . TIME_ZONE . "'");
            }
            if (defined('MYSQL_SET_NAMES')) {
                $this->pdo->exec("SET names '" . MYSQL_SET_NAMES . "'");
            }
        }
	}

    function pdo_ping($dbconn)
    {
        try{
            @$dbconn->getAttribute(PDO::ATTR_SERVER_INFO);
        } catch (PDOException $e) {
            if(strpos($e->getMessage(), 'MySQL server has gone away')!==false){
                return false;
            }
        }
        return true;
    }

	public function query($sql)
    {
        $this->connect();
		if($this->switchToWriteDBBySql($sql) == "writetofile") return null;
		if($this->recordQueryMode) {
			$result = array();
			$result["sql"] = $sql;
			$result["start"] = microtime(true);
			$this->sth = $this->pdo->query($sql);
			$result["end"] = microtime(true);
			$result["time"] = number_format($result["end"] - $result["start"], 3, '.', ' ');
			$result["backtrace"] = self::getDebugBacktrace("\t");
			$this->setLog($result);
		} else {
			try {
				$this->sth = $this->pdo->query($sql);
			} catch (PDOException $e) {
				self::raiseError("Query failed, " . $sql . ", " . $e->getMessage(), __FILE__, __LINE__);
			}
		}
		return $this->sth;
	}
	
	public function getRow(&$sth, $fetchType = PDO::FETCH_ASSOC)
    {
		return $sth->fetch($fetchType);
	}
	
	public function getLastInsertId()
    {
		return $this->pdo->lastInsertId();
	}
	
	public function getFirstRow($sql)
    {
		$sth = $this->query($sql);
		$row = $sth->fetch(PDO::FETCH_ASSOC);
		if(is_array($row) && count($row) > 0) return $row;
		return array();
	}
	
	public function getFirstRowColumn($sql, $column_number = 0)
    {
		$sth = $this->query($sql);
		return $sth->fetchColumn($column_number);
	}
	
	function switchToWriteDB()
	{
		$sql = "USE " . $this->database;
		$this->switchToWriteDBBySql($sql);
	}
	
	function close()
	{
		$this->pdo = null;
	}

	function switchToWriteDBBySql(&$sql)
	{
		$this->switch_to_write_db = true;
		if(defined("SHORT_SERVER_NAME"))
		{
			if(SHORT_SERVER_NAME == "admin01" || SHORT_SERVER_NAME == "dev01") return "skip";
		}
		
		if(!defined("PROD_WRITE_DB_HOST")) return "skip";
		if($this->host == PROD_WRITE_DB_HOST) return "skip";
		
		if(!defined("PROD_DB_NAME")) return "skip";
		if($this->database != PROD_DB_NAME) return "skip";
		
		//is start with select or set
		list($sql_cmd) = preg_split('/[^a-z]/',strtolower(substr(ltrim($sql),0,10)));
		if($sql_cmd == "select" || $sql_cmd == "set") return "skip";
		
		if(defined("DATA_ROOT")) $write_log_dir = DATA_ROOT . "mysql_write/";
		else $write_log_dir = dirname(__FILE__) . "/mysql_write/";
		$write_log_file = $write_log_dir . PROD_DB_NAME . "_" . gmdate("YmdH");
		if(file_exists($write_log_file))
		{
			$line = date("Y-m-d H:i:s") . "\t" . PROD_DB_NAME . "\t" . str_replace(array("\r","\n")," ",$sql) . ";\n";
			error_log($line, 3, $write_log_file);
			return "writetofile";
		}
		else
		{
			$this->host = PROD_WRITE_DB_HOST;
			$this->close();
			if($this->connect(false) === false)
			{
				//switch it back
				$this->host = PROD_DB_HOST;
				$this->connect();

				if(!is_dir($write_log_dir))
				{
					mkdir($write_log_dir,0777,true);
					chmod($write_log_dir,0777);
				}
				$line = date("Y-m-d H:i:s") . "\t" . PROD_DB_NAME . "\t" . str_replace(array("\r","\n")," ",$sql) . ";\n";
				error_log($line, 3, $write_log_file);
				chmod($write_log_file,0775);
				return "writetofile";
			}
			else
			{
				$this->switch_to_write_db_result = true;
				return "switched";
			}
		}
		return "keepon";
	}

	function query_relay($_sql)
	{
		if(!defined("PROD_WRITE_DB_HOST")) return $this->query($_sql);
		if(!defined("PROD_DB_HOST")) return $this->query($_sql);
		if(PROD_DB_HOST == PROD_WRITE_DB_HOST) return $this->query($_sql);
		if(strtolower(substr(ltrim($_sql),0,6)) != "insert") return $this->query($_sql);
		
		$id = microtime(true) . "." . md5($_sql);
		$host = "web22";
		$dbname = $this->database;
		$timezone = defined('TIME_ZONE') ? TIME_ZONE : "America/Los_Angeles";
		$charset = defined('MYSQL_ENCODING') ? MYSQL_ENCODING : "latin1";//utf8
		$now = date("Y-m-d H:i:s");
		$arr_source = array();
		$arr_source[] = SID_PREFIX;
		$arr_source[] = defined("D_PAGE_NAME") ? D_PAGE_NAME : "";
		$arr_source[] = defined("PAGE_VALUE") ? PAGE_VALUE : "";
		$server_id = defined("STATS_CURR_SERVER_ID") ? STATS_CURR_SERVER_ID : "";
		$sql = "insert into `relay_sql` (`Id`, `Host`, `DBName`, `TimeZone`, `Charset`, `CreateTime`, `Sql`, `Status`, `DoneTime`, `AffectedRows`, `InsertId`, `Source`, `CurrServerID`) values ('$id', '$host', '$dbname', '$timezone', '$charset', '$now', '" . addslashes($_sql) . "', 'NEW', null, '0', '0', '" . implode(":",$arr_source) . "', '" . addslashes($server_id) . "')";
		return $this->query($sql);
	}

	public function getRows(&$sql, $keyname="", $foundrows=false)
    {
		if($foundrows && strpos(substr($sql, 0, 30), "SQL_CALC_FOUND_ROWS") === false) {
			if(stripos($sql, "select") === 0) {
				$sql = "select SQL_CALC_FOUND_ROWS" . substr($sql, 6);
			}
		}
		$sth = $this->query($sql);	
		$arr_return = array();
		if($keyname) {
			$keys = explode(",", $keyname);
		}

		while($row = $sth->fetch(PDO::FETCH_ASSOC))	{
			if($keyname) {
				$arr_temp = array();
				foreach($keys as $key) {
					$arr_temp[] = $row[$key];
				}
				$key_value = implode("\t", $arr_temp);
				$arr_return[$key_value] = $row;
			} else {
				$arr_return[] = $row;
			}
		}
		if($foundrows) {
			$this->getFoundRows();
		}
		$sth->closeCursor();
		return $arr_return;
	}
	
	public function freeResult(&$sth)
    {
		$sth->closeCursor();
	}
	
	public function getFoundRows()
    {
		$this->FOUND_ROWS = $this->getFirstRowColumn("SELECT FOUND_ROWS()");
		if(!is_numeric($this->FOUND_ROWS)) $this->FOUND_ROWS = 0;
		return $this->FOUND_ROWS;
	}
	
	function setLog($result)
	{
		if(!defined("LOG_LOCATION") || !defined("SQLDUMP_LOG_FILE")) return;
		$log_file = LOG_LOCATION . SQLDUMP_LOG_FILE;

		$status = "good";
		$time = 0;
		if(is_array($result))
		{
			$time = $result["time"];
			if($time > 0.1) $status = "normal";
			else if($time > 1) $status = "slow";
			else if($time > 2) $status = "very slow";

			$sql = $result["sql"];
			$sql = str_replace("\n"," ",$sql);

			$backtrace = "";
			if(isset($result["backtrace"][0]))
			{
				$backtrace = end(explode("/",$result["backtrace"][0]));
			}
			$logline = date("Y-m-d H:i:s") . "\t$status\t$time\t$sql\t$backtrace\n";
		}
		else
		{
			$logline = date("Y-m-d H:i:s") . "\t$status\t$time\t$result\n";
		}
		
		@error_log($logline, 3, $log_file);
	}

	public function outputQuerys()
    {
		print_r($this->queryArr);
	}
	
	public function raiseError($errorMsg = "", $scripts=__FILE__, $line = __LINE__)
    {
		$this->errorMsg = $errorMsg;
		$this->time = date("Y-m-d H:i:s");
		$this->scriptName = $scripts;
		$this->line = $line;
		self::setErrorLog();
		
		if($this->raiseErrorMode) {
			echo "ErrorMsg: {$this->errorMsg}\n";
			echo "Time: {$this->time}\n";
			echo "ScriptName: {$this->scriptName}\n";
			echo "BackTrace: \n" . implode("\n", self::getDebugBacktrace("\t"));
			
			exit;
		} else {
			echo "<b>500 Internal Error</b>";
			exit;
		}
	}

	public function setErrorLog()
    {
		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$req = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

		$logString = "{$this->time}\t{$this->scriptName}\t{$this->line}\t$ip\t$req\t$ref\t{$this->errorMsg}\n";
		if(defined("LOG_LOCATION") && defined("ERROR_LOG_FILE")) {
			@error_log($logString, 3, LOG_LOCATION . ERROR_LOG_FILE);
		}
	}
	
	public function getDebugBacktrace($prefix = "")
    {
		$debug_backtrace = debug_backtrace();
		krsort($debug_backtrace);
		foreach ($debug_backtrace as $k => $v) {
			if($v["function"] != __FUNCTION__) {
				$result[] = $prefix . $v["file"]." => ". $v["class"] . " => ".$v["function"]." => ".$v["line"];
			}
		}
		return $result;
	}
}
?>