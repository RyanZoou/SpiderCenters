<?php
class LinkFeed_679_Admitad_com
{
	function __construct($aff_id, $oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->getStatus = false;
		$this->file = "programlog_{$aff_id}_".date("Ymd_His").".csv";
		
		$this->partnership_priority_map = array(
            'Removed' => 1,
            'Expired' => 2,
            'Declined' => 3,
            'NoPartnership' => 4,
            'Pending' => 5,
            'Active' => 6,
        );
		$this->islogined = false;
	}
	
    function login($try = 1)
	{
		if ($this->islogined) {
			echo "verify succ: " . $this->info["AffLoginVerifyString"] . "\n";
			return true;
		}
		
		$this->oLinkFeed->clearHttpInfos($this->info['AffId']);//删除缓存文件，删除httpinfos[$aff_id]变量
		$request = array(
			"AffId" => $this->info["AffId"],
			"method" => 'get',
		);
		$loginHtml = $this->oLinkFeed->GetHttpResult("https://www.admitad.com/en/sign_in/?next=".urlencode('https://help.admitad.com/en/'),$request);
		preg_match('/<input type=\'hidden\' name=\'csrfmiddlewaretoken\' value=\'(.+)\' \/>/', $loginHtml['content'],$matches);
	    $loginToken = $matches[1];
	    $this->info['AffLoginPostString'] .= "&csrfmiddlewaretoken=".urlencode($loginToken)."&next=";
	    
		$request = array(
			"AffId" => $this->info["AffId"],
			"method" => $this->info["AffLoginMethod"],
			"postdata" => $this->info["AffLoginPostString"],
		    "addheader"=> array('referer:https://www.admitad.com/en/sign_in/?next=https%3A//www.admitad.com/en/webmaster/'),
		    "no_ssl_verifyhost" => true,
		);
		$arr = $this->oLinkFeed->GetHttpResult($this->info['AffLoginUrl'], $request);
				
		if ($arr["code"] == 200) {
			if (stripos($arr["content"], $this->info["AffLoginVerifyString"]) !== false) {
				echo "verify succ: " . $this->info["AffLoginVerifyString"] . "\n";
				$this->islogined = true;
				return true;
			}
		}
		
		if (!$this->islogined) {
			if ($try < 0) {
				mydie("Failed to login!");
			} else {
				echo "login failed ... retry $try...\n";
				sleep(30);
				$this->login(--$try);
			}
		}
	}
	
	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
		$this->GetProgramByApi();
		echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
		
	}
	
	function GetProgramByApi()
	{
		echo "\tGet Program by api start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$program_num = $base_program_num = 0;
		
		$alloffset = 0;
		$arr_prgmID_active = array();				//状态和合作关系都是active的program
		$arr_prgmID_apply = array();				//申请过的program
		$partnership_arr = array();

		//step 1 , get my program
		//Client authorization
		$data_b64_encoded = base64_encode($this->info['APIKey1'] . ':' . $this->info['APIKey2']);
		$query = array(
				'client_id' => $this->info['APIKey1'],
				'scope' => 'advcampaigns_for_website',
				'grant_type' => 'client_credentials'
		);
		$ch = curl_init('https://api.admitad.com/token/');
		$curl_opts = array(
				CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:6.0.2) Gecko/20100101 Firefox/6.0.2',
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => array('Authorization: Basic ' . $data_b64_encoded),
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query($query)
		);
		curl_setopt_array($ch, $curl_opts);
		$reponseToken = curl_exec($ch);
		curl_close($ch);
		$tokenArr = json_decode($reponseToken,true);
		$access_token = $tokenArr['access_token'];
		$Websites = json_decode($this->info['APIKey3'],true);
		foreach ($Websites as $Website => $WebsiteID){
			echo "\tGet Program for website was called $Website start\r\n";
            $myoffset = 0;
			while(1){
				echo "\tStart get Program for website was called ".$Website." ".$myoffset."th\r\n";
				//$ch = curl_init('https://api.admitad.com/advcampaigns/');
				$ch = curl_init("https://api.admitad.com/advcampaigns/website/{$WebsiteID}/?offset={$myoffset}&language=en&limit=100");
				$curl_opts = array(
						CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:6.0.2) Gecko/20100101 Firefox/6.0.2',
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_SSL_VERIFYHOST => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $access_token),
				);
				curl_setopt_array($ch, $curl_opts);
				$reponseprograms = curl_exec($ch);
				curl_close($ch);
				$affiliate_programs = json_decode($reponseprograms,true);
				$lastNum = count($affiliate_programs['results']);
		
				foreach ($affiliate_programs['results'] as $v){
						
					$strMerID = $v['id'];
					$arr_prgmID_apply[$strMerID] = 1;
					if(isset($arr_prgmID_active[$strMerID]))
						continue;
					
					$StatusInAffRemark = $v['connection_status'];
					$Partnership = "NoPartnership";
					if($StatusInAffRemark == 'active')
						$Partnership = 'Active';
					elseif($StatusInAffRemark == 'pending')
						$Partnership = 'Pending';
					elseif($StatusInAffRemark == 'declined')
						$Partnership = 'Declined';
					
                    if($v['status'] == 'active')
                        $StatusInAff = 'Active';
                    elseif($v['status'] == 'disabled')
                        $StatusInAff = 'Offline';

                    if($Partnership == 'Active' && $StatusInAff == 'Active')
                        $arr_prgmID_active[$strMerID] = 1;

                    //删选所有网站里的最佳program合作关系
                    if (isset($partnership_arr[$strMerID]['Partnership']) && !empty($partnership_arr[$strMerID]['Partnership'])) {
                        $old_partnership_priority = $this->partnership_priority_map[$partnership_arr[$strMerID]['Partnership']];
                        $new_partnership_priority = $this->partnership_priority_map[$Partnership];
                        if ($new_partnership_priority > $old_partnership_priority) {
                            $partnership_arr[$strMerID]['Partnership'] = $Partnership;
                        }
                    } else {
                        $partnership_arr[$strMerID] = array(
                            'AccountSiteID' => $this->info["AccountSiteID"],
                            "IdInAff" => $strMerID,
                            'BatchID' => $this->info['batchID'],
                            'Partnership' => $Partnership,
                        );
                    }

					$arr_prgm[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                        'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                        'IdInAff' => $strMerID,
                        'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                        "AffDefaultUrl" => addslashes($v['gotolink'])
					);

					if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId']))
					{
						$desc = $v['description'];
						$CountryExt = array();
						foreach($v['regions'] as $Countrys){
							$CountryExt[] = $Countrys['region'];
						}
						$TargetCountryExt = implode("|", $CountryExt);
						if($TargetCountryExt == '00') $TargetCountryExt = 'GLOBAL';
							
						$ReturnDays = $v['goto_cookie_lifetime'];
						if($v['allow_deeplink']){
							$SupportDeepUrl = 'YES';
						}else{
							$SupportDeepUrl = 'NO';
						}

						$Commission = array();
						foreach ($v['actions'] as $action){
							$Commission[] = $action['type'] . ':' . $action['payment_size'];
						}
						$CommissionExt = implode(';', $Commission);
							
						$CategoryExt = array();
						foreach ($v['categories'] as $categories){
							if(isset($categories['parent']['name'])){
								$CategoryExt[] =  $categories['parent']['name'] . '-' . $categories['name'];
							}
						}
						$CategoryExt = implode(EX_CATEGORY, $CategoryExt);

						$arr_prgm[$strMerID] += array(
                                "CrawlJobId" => $this->info['crawlJobId'],
								"Name" => addslashes($v['name']),
								"TargetCountryExt" => $TargetCountryExt,
								"JoinDate" => $v['activation_date'],
								"StatusInAffRemark" => $StatusInAffRemark,
								"StatusInAff" => $StatusInAff,                        //'Active','TempOffline','Offline'
								"Description" => addslashes($desc),
								"Homepage" => $v['site_url'],
								"CookieTime" => addslashes($ReturnDays),
								"RankInAff" => $v['rating'],
								"SupportDeepUrl" => $SupportDeepUrl,
								"LastUpdateTime" => date("Y-m-d H:i:s"),
								"DetailPage" => "https://www.admitad.com/en/webmaster/websites/{$WebsiteID}/offers/{$strMerID}/#information",
								"CommissionExt" => addslashes($CommissionExt),
								"CategoryExt" => addslashes($CategoryExt),
								"LogoUrl" => addslashes($v['image']),
								"PaymentDays" => $v['max_hold_time'],
						);
						$base_program_num++;
					}
					$program_num++;
					if(count($arr_prgm) >= 100){
						$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
						$arr_prgm = array();
					}
				}
				if(count($arr_prgm)){
					$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
					$arr_prgm = array();
				}
				echo "\tFinish get Program for website was called ".$Website." ".$myoffset."th\r\n";
				$myoffset += 100;
				if($lastNum < 100) break;
			}
			echo "\tGet Program for website was called $Website end\r\n";
		}

		//step 2, set programs partnership!
        if (count($partnership_arr)) {
            echo "\tStart to set programs partnership!\r\n";
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $partnership_arr);
            echo "\tSet (" . count($partnership_arr) . ") programs partnership success!\r\n";
            unset($partnership_arr);
        }
		
		//step 3 , get all program
		$data_b64_encoded = base64_encode($this->info['APIKey1'] . ':' . $this->info['APIKey2']);
		$query = array(
				'client_id' => $this->info['APIKey1'],
				'scope' => 'advcampaigns arecords banners websites',
				'grant_type' => 'client_credentials'
		);
		$ch = curl_init('https://api.admitad.com/token/');
		$curl_opts = array(
				CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:6.0.2) Gecko/20100101 Firefox/6.0.2',
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => array('Authorization: Basic ' . $data_b64_encoded),
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query($query)
		);
		curl_setopt_array($ch, $curl_opts);
		$reponseToken = curl_exec($ch);
		curl_close($ch);
		$tokenArr = json_decode($reponseToken,true);
		//print_r($tokenArr);exit;
		$access_token = $tokenArr['access_token'];
		
		echo "\tGet all Program by api start\r\n";
		while(1){
			$ch = curl_init("https://api.admitad.com/advcampaigns/?offset={$alloffset}&language=en&limit=100");
			//$ch = curl_init("https://api.admitad.com/advcampaigns/website/?offset={$alloffset}&limit=100");
			$curl_opts = array(
					CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:6.0.2) Gecko/20100101 Firefox/6.0.2',
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => false,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $access_token),
			);
			curl_setopt_array($ch, $curl_opts);
			$reponseprograms = curl_exec($ch);
			curl_close($ch);
			$affiliate_programs = json_decode($reponseprograms,true);
			$lastNum = count($affiliate_programs['results']);
		
			foreach ($affiliate_programs['results'] as $v){
						
				$strMerID = $v['id'];
				if(isset($arr_prgmID_apply[$strMerID]))
					continue;
				$Partnership = "NoPartnership";
				
				$arr_prgm[$strMerID] = array(
						'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
						'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
						'IdInAff' => $strMerID,
						'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
				);

				if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId']))
				{
					if($v['status'] == 'active')
					$StatusInAff = 'Active';
					elseif($v['status'] == 'disabled')
					$StatusInAff = 'Offline';
						
					$desc = $v['description'];
							
					$CountryExt = array();
					foreach($v['regions'] as $Countrys){
						$CountryExt[] = $Countrys['region'];
					}
					$TargetCountryExt = implode("|", $CountryExt);
					if($TargetCountryExt == '00') $TargetCountryExt = 'GLOBAL';
					
					$ReturnDays = $v['goto_cookie_lifetime'];
					if($v['allow_deeplink'])
						$SupportDeepUrl = 'YES';
					else
						$SupportDeepUrl = 'NO';
					
					$Commission = array();
					foreach ($v['actions'] as $action){
						$Commission[] = $action['type'] . ':' . $action['payment_size'];
					}
					$CommissionExt = implode(';', $Commission);
						
					$CategoryExt = array();
					foreach ($v['categories'] as $categories){
						if(isset($categories['parent']['name'])){
							$CategoryExt[] =  $categories['parent']['name'] . '-' . $categories['name'];
						}
					}
					$CategoryExt = implode(" & ", $CategoryExt);
						
					$arr_prgm[$strMerID] += array(
                        "CrawlJobId" => $this->info['crawlJobId'],
                        "Name" => addslashes($v['name']),
                        "TargetCountryExt" => $TargetCountryExt,
                        "JoinDate" => $v['activation_date'],
                        "StatusInAff" => $StatusInAff,                        //'Active','TempOffline','Offline'
                        "Description" => addslashes($desc),
                        "Homepage" => $v['site_url'],
                        "CookieTime" => addslashes($ReturnDays),
                        "RankInAff" => $v['rating'],
                        "SupportDeepUrl" => $SupportDeepUrl,
                        "LastUpdateTime" => date("Y-m-d H:i:s"),
                        "CommissionExt" => addslashes($CommissionExt),
                        "CategoryExt" => addslashes($CategoryExt),
					);
					$base_program_num++;
				}
				$program_num++;
				if(count($arr_prgm) >= 100){
					$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
					$arr_prgm = array();
				}
			}
			if(count($arr_prgm)){
				$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
				$arr_prgm = array();
			}
			$alloffset += 100;
			if($lastNum < 100) break;
		}
		echo "\tGet all Program by api end\r\n";

		echo "\tGet Program by api end\r\n";
		if($program_num < 10){
			mydie("die: program count < 10, please check program.\n");
		}
		echo "\tUpdate ({$base_program_num}) program.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
	}
	
}

?>