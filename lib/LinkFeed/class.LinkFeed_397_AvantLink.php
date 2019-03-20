<?php

require_once 'text_parse_helper.php';

class LinkFeed_397_AvantLink
{
	function __construct($aff_id,$oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->getStatus = false;
		$this->file = "programlog_{$aff_id}_".date("Ymd_His").".csv";
	}	

	function Login(){
		$islogined = false;
		$this->oLinkFeed->clearHttpInfos($this->info["AffId"]);
		$strUrl = "https://www.avantlink.com/signin";
		$request = array(
			"AccountSiteID" => $this->info["AccountSiteID"],
			"method" => "get",
			"postdata" => "",
		);
		$r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
		$result = $r["content"];
		$_token = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="_token"', 'value="'), '"'));		

		$this->info["LoginPostString"] .= "&_token=".$_token;
		
		$request = array(
			"AccountSiteID" => $this->info["AccountSiteID"],
			"method" => $this->info["LoginMethod"],
			"postdata" => $this->info["LoginPostString"]			
		);
		$r = $this->oLinkFeed->GetHttpResult($this->info["LoginUrl"], $request);		//print_r($r);exit;
		if($r["code"] == 200){
			if(stripos($r["content"], $this->info["LoginVerifyString"]) !== false)
			{
				echo "verify succ: " . $this->info["LoginVerifyString"] . "\n";
				$islogined = true;
			}else{
				echo "verify login failed(".$this->info["LoginVerifyString"].") <br>\n";
			}
		}
		
		if(!$islogined){
			mydie("die: login failed for aff({$this->info["AccountSiteID"]}) <br>\n");
		}
		//从US跳转到AU
		$time = explode (" ", microtime () );
		$time = $time [1] . ($time [0] * 1000);
		$time2 = explode ( ".", $time );
		$time = $time2 [0];//取毫秒级的时间戳
		
		$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => 'post',
				"postdata" => "xjxfun=ajaxChangeLogin&xjxr={$time}&xjxargs[]=AU_180167&xjxargs[]=%3C!%5BCDATA%5B%2Faffiliate%2F%5D%5D%3E"
		);
		//echo $time;exit;
		$skip_url = "https://classic.avantlink.com/affiliate/index.php";
		$skip_r = $this->oLinkFeed->GetHttpResult($skip_url, $request);
		
		if($skip_r["code"] == 200){
			$check_url = $this->oLinkFeed->ParseStringBy2Tag($skip_r['content'], "window.location.href = '", "';]]></cmd>");
			//print_r($check_url);exit;
			$request = array("AffId" => $this->info["AccountSiteID"],"method" => "get","postdata" => "",);
			$r = $this->oLinkFeed->GetHttpResult($check_url,$request);
			if($r["code"] == 200){
				if(stripos($r["content"], $this->info["LoginVerifyString"]) !== false)
				{
					echo "verify succ: " . $this->info["LoginVerifyString"] . "change site succ"."\n";
				}else{
					echo "verify change site failed(".$this->info["LoginVerifyString"].") <br>\n";
				}
			}
		}
	}
	
    function GetStatus(){
        $this->getStatus = true;
        $this->GetProgramFromAff();
    }

	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
		$this->GetProgramByApi();
		echo "Craw Program end @ ".date("Y-m-d H:i:s")."\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
	}
	
	function GetProgramByApi ()
	{
		echo "\tGet Program by api start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$program_num = 0;
		$base_program_num = 0;
		$request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);
	
		$url = "https://classic.avantlink.com/api.php?affiliate_id={$this->info['APIKey1']}&auth_key={$this->info['APIKey4']}&module=AssociationFeed&output=xml";
		$r = $this->oLinkFeed->GetHttpResult($url, $request);
		$r = simplexml_load_string($r['content']);
		$data = json_decode(json_encode($r), true);
//		print_r($data);exit;

		if(!empty($data)){
			foreach ($data['Table1'] as $v) {
				$IdInAff = intval($v['Merchant_Id']);
				if (!$IdInAff) {
				    continue;
                }

                switch ($v["Association_Status"]) {
                    case 'active':
                        $Partnership = 'Active';
                        break;
                    case 'pending':
                        $Partnership = 'Pending';
                        break;
                    case 'denied':
                        $Partnership = 'Declined';
                        break;
                    case 'none':
                        $Partnership = 'NoPartnership';
                        break;
                    default:
                        mydie("New Association_Status appeared: {$v["Association_Status"]} ");
                        break;
                }
                
                $AffDefaultUrl = (!empty($v['Default_Tracking_URL']))?$v['Default_Tracking_URL']:'';

                $arr_prgm[$IdInAff] = array(
                		'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                		'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                		'IdInAff' => $IdInAff,
                		'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                		"AffDefaultUrl" => addslashes($AffDefaultUrl),
                );
                
                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId']))
                {
                
					if (!empty($v['Commission_Rate'])) {
	                    $CommissionExt = $v['Commission_Rate'];
	                } else {
	                    $CommissionExt = $v['Default_Program_Commission_Rate'];
	                }
	
					$JoinData = (!empty($v['Date_Joined']))?$v['Date_Joined']:'';

					if (is_array($v['Merchant_Category_Name'])){
						$CategoryExt = implode(EX_CATEGORY,$v['Merchant_Category_Name']);
					}else {
						$CategoryExt = $v['Merchant_Category_Name'];
					}

					$arr_prgm[$IdInAff] += array(
							'CrawlJobId' => $this->info['crawlJobId'],
		                    "Name" => addslashes(trim($v['Merchant_Name'])),
		                    "Homepage" => addslashes($v['Merchant_URL']),
		                    "CreateDate" => date('Y-m-d H:i:s', strtotime($JoinData)),
		                    "StatusInAff" => 'Active',                              //'Active','TempOffline','Offline'
		                    //"MobileFriendly" => 'UNKNOWN',
		                    //"SupportDeepUrl" => 'UNKNOWN',
		                    "CommissionExt" => addslashes($CommissionExt),
		                    "CategoryExt" => addslashes($CategoryExt),
		                    "LogoUrl" => addslashes(@$v['Merchant_Logo']),
		                    //"SupportDeepUrl" => "YES",
		                    "CookieTime" => @intval($v['Referral_Days'])
					);
					$base_program_num++;
                }
                $program_num++;
                if (count($arr_prgm) >= 100) {
                	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                	$arr_prgm = array();
                }
			}
			if (count($arr_prgm)) {
				$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
				$arr_prgm = array();
			}
		}
		echo "\tGet Program by api end\r\n";
		if ($program_num < 10) {
			mydie("die: program count < 10, please check program.\n");
		}
		echo "\tUpdate ({$base_program_num}) base program.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
	}

}

