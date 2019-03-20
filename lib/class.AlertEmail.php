<?php
class AlertEmail
{
	static function SendAlert($subject, $body="", $to="", $alter_subject = true)
	{
		if(!$to) $to = defined("ALERT_EMAIL") ? ALERT_EMAIL : "ikezhao@megainformationtech.com";
		if ($alter_subject)
			$subject = trim($subject);
		
		$headers = array();
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = "Content-Transfer-Encoding: base64";
		
		$str_header = implode("\r\n",$headers);
		$body = chunk_split(base64_encode($body));
		echo "sending alert: <$to>, $subject\n";
		return mail($to,$subject,$body,$str_header);
	}
	
	function send_mail_via_morecouponcodes($to,$subject,&$content="",$from="")
	{
		if(!$from) $from = "ikezhao@megainformationtech.com";
		/* To send HTML mail, you can set the Content-type header. */
		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
		
		/* additional headers */
		$headers .= "From: $from\r\n";
		
		$mailSender = "http://www.morecouponcodes.com/front/sendmail.php";
		$auth = "fromcouponsnapshot";
		$handle = curl_init($mailSender);
		curl_setopt($handle, CURLOPT_HEADER, 0);
		curl_setopt($handle, CURLOPT_NOBODY, 1);
		curl_setopt($handle, CURLOPT_POST, 1);
		curl_setopt($handle, CURLOPT_POSTFIELDS,"auth=".$auth."" .
				"&to=".urlencode($to)."&subject=".urlencode($subject)."" .
						"&content=".urlencode($content)."&header=".urlencode($headers));
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($handle, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($handle, CURLOPT_USERAGENT, 'couponsnapshotmailsender');
		$result = curl_exec($handle);
		curl_close($handle); 
	}

	static function log_alert_email($action,$arr_info=null){
		if(empty($arr_info))
			return ;

		$logpath = '';
		$content = '';
		switch ($action) {
			case 'aff_has_new_program':
				$logdir = '/home/pendinglinks/website/logs/newprogramlog/';
				$logfile = 'alert_email_log_'.date('Y-m-d').'.log';
				$logpath = $logdir.$logfile;

				$IdInAff = array();
				foreach($arr_info as $k=>$v){
					$AffId = $v['AffId'];
					$IdInAff[] = $v['IdInAff'];
				}

				$sql = 'SELECT `Name` FROM wf_aff WHERE ID = '.intval($AffId);
				$objMysql = new MysqlExt(TASK_DB_NAME, TASK_DB_HOST, TASK_DB_USER, TASK_DB_PASS);
				$return_arr = $objMysql->getRows($sql);
				$name = $return_arr[0]['Name'];

				$content = date('Y-m-d H:i:s')."\t".$AffId."\t".$name."\t".join(',',$IdInAff)."\n";
			break;
		
			default:
				break;
		}

		if(!empty($logpath) && !empty($content)){
			error_log(print_r($content,1),3,$logpath);
			echo "log aff has new program \r\n";
		}
	}

	static function send_alert_email($action){
		$to = "";
		$body = '';
		$subject = '';

		switch ($action) {
			case 'aff_has_new_program':
					$to = "gordonpan@megainformationtech.com,elsahou@megainformationtech.com,sunnychen@megainformationtech.com";
					
					$sql = 'SELECT AffId FROM program WHERE `AddTime` >= "'.date('Y-m-d H:i:s',strtotime('-1 day')).'" GROUP BY AffId ';
					$objMysql = new MysqlExt(TASK_DB_NAME, TASK_DB_HOST, TASK_DB_USER, TASK_DB_PASS);
					$AffId_arr = $objMysql->getRows($sql);
					if(!empty($AffId_arr)){
						$ids = array();
						foreach ($AffId_arr as $key => $value) {
							$ids[] = intval($value['AffId']);
						}

						$sql = 'SELECT `Name` FROM wf_aff WHERE ID IN ('.join(',',$ids).') ';
						$AffName_arr = $objMysql->getRows($sql);

						$names = array();
						foreach ($AffName_arr as $key => $value) {
							$names[] = $value['Name'];
						}

						if(!empty($names))
							$body = join('<br>',$names);
					}

					$subject = 'Affilate has new program';
				break;
			
			default:
				break;
		}

		if($body)
			AlertEmail::SendAlert($subject, $body, $to);
	}
}//end class
?>