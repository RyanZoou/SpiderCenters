<?php
class LinkFeed_553_Smart4ads
{

    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;

        $this->file = "programlog_{$aff_id}_" . date("Ymd_His") . ".csv";
        $this->islogined = false;
        $this->maxPage = 500;
        $this->session = '';
        $this->unSupportLinkTypeArray = [
            'H' => 'H',
            'V' => 'V',
            'R' => 'R',
            'S' => 'S',
            'Z' => 'Z',
            'Y' => 'Y',
            'N' => 'N',
        ];
        $this->countryArray = [
            'belgium' => 35,
            'brazil' => 6,
            'chile' => 4,
            'colombia' => 25,
            'Czech Republic' => 40,
            'Ecuador' => 31,
            'france' => 7,
            'germany' => 8,
            'greece' => 24,
            'hungary' => 39,
            'international' => 26,
            'italy' => 15,
            'latam' => 34,
            'morocco' => 36,
            'paraguay' => 32,
            'portugal' => 2,
            'poland' => 11,
            'Puerto Rico' => 33,
            'qatar' => 37,
            'spain' => 1,
            'netherlands' => 23,
            'uruguay' => 16,
            'usa' => 10,
            'uk' => 14,
            'venezuela' => 27,
        ];
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

    function login($try = 6)
    {
        if ($this->islogined) {
            echo "verify succ: " . $this->info["LoginVerifyString"] . "\n";
            return true;
        }

        $this->oLinkFeed->clearHttpInfos($this->info['AccountSiteID']);//删除缓存文件，删除httpinfos[$aff_id]变量

        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => 'get',
            "no_ssl_verifyhost" => true,
        );
        $loginUrl = $this->info['LoginUrl'];
        $loginUrl = str_replace("XXXXXX",urlencode($this->info['UserName']),$loginUrl);
        $loginUrl = str_replace("YYYYYY",urlencode($this->info['Password']),$loginUrl);
        $arr = $this->oLinkFeed->GetHttpResult($loginUrl, $request);
        if ($arr["code"] == 0) {
            if (preg_match("/^SSL: certificate subject name .*? does not match target host name/i", $arr["error_msg"])) {
                $request["no_ssl_verifyhost"] = 1;
                $arr = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
            }
        }

        if ($arr["code"] == 200) {
            if (stripos($arr["content"], $this->info["LoginVerifyString"]) !== false) {
                $tmpObj = new HttpCrawler();
                $cookiejarPath = $this->oLinkFeed->getCookieJarBySiteId($this->info["AccountSiteID"]);
                $tmpArray = $tmpObj->ReadCookie($cookiejarPath, 'A_pap_sid');
                if (!empty($tmpArray) && isset($tmpArray['value'])) {
                    $this->session = $tmpArray['value'];
                } else {
                    mydie("obtain session of '{$this->info['AccountSiteID']} fail', please check the network!\r\n");
                }
                echo "verify succ: " . $this->info["LoginVerifyString"] . " login successfully\n";
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

    function GetProgramByApi()
    {
        echo "\tGet Program by Api start\r\n";
        $existsProgramList = array();
        $this->login();

        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = 0;
        $base_program_num = 0;
        $request = array(
            'AccountSiteID'=>$this->info['AccountSiteID'],
            'method'=>'post',
        );

        $tmpUrl = 'http://www.smart4ads.com/smart4ads/scripts/server.php';

        $page = 1;
        $limit = 100;
        $tmpCount = 0;
        while(true) {
            if ($page >= $this->maxPage) {
                mydie("get the page of program list of '{$this->info['AffId']} exceed max limit {$this->maxPage}', please check the network!\r\n");
            }
            $offset = ($page - 1) * $limit;
            $programListPostString = <<<EOF
    {"C":"Gpf_Rpc_Server", "M":"run", "requests":[{"C":"Pap_Affiliates_Promo_CampaignsGrid", "M":"getRows", "offset":{$offset}, "limit":{$limit}, "columns":[["id"],["id"],["name"],["description"],["logourl"],["banners"],["rstatus"],["affstatus"],["commissionsexist"],["actions"]]}], "S":"{$this->session}"}
EOF;
            $request['postdata'] = 'D=' . urlencode($programListPostString);
            $cacheName="data_" . date("YmdH") . "_programlist{$page}.dat";
            $result = $this->oLinkFeed->GetHttpResultAndCache($tmpUrl,$request,$cacheName,'rows');
            $page++;
            $result = json_decode($result, true);
            if (empty($result)) {
                mydie('get data error!');
            }
            $result = reset($result);
            if (isset($result['rows']) && count($result['rows']) > 1) {
            	$programList = &$arr_prgm;
            	$existsProgramList = &$existsProgramList;
            	$r = $result['rows'];
            	
                $columnIndexArray = [
                'campaignid' => -1,
                'name' => -1,
                'description' => -1,
                'logourl' => -1,
                'commissionsdetails' => -1,
                'rstatus' => -1,
                'affstatus' => -1,
                
                ];
                $tmpArray = array_shift($r);
                $tmpArray = array_flip($tmpArray);
                foreach ($columnIndexArray as $k => &$v) {
                	if (isset($tmpArray[$k])) {
                		$v = $tmpArray[$k];
                	} else {
                		mydie("key $k missing!");
                	}
                }
                unset($v);
                $nowDate = date('Y-m-d H:i:s');
                foreach ($r as $item) {
                	$idinaff = addslashes($item[$columnIndexArray['campaignid']]);
                	$name = addslashes($item[$columnIndexArray['name']]);
                	$tmp = trim(strtolower($item[$columnIndexArray['name']]));
                	$existsProgramList[$tmp] = $idinaff;
                	$description = addslashes($item[$columnIndexArray['description']]);
                	$logUrl = addslashes($item[$columnIndexArray['logourl']]);
                	$commission = addslashes($item[$columnIndexArray['commissionsdetails']]);
                	$tmpString = $item[$columnIndexArray['rstatus']];
                	if ($tmpString == 'A') {
                		$statusInAff = 'Active';
                	} else {
                		$statusInAff = 'Offline';
                	}
                	$tmpString = $item[$columnIndexArray['affstatus']];
                	if ($tmpString == 'A') {
                		$partnership = 'Active';
                	} elseif ($tmpString == 'P') {
                		$partnership = 'Pending';
                	} elseif ($tmpString == 'D') {
                		$partnership = 'Declined';
                	} else {
                		$partnership = 'NoPartnership';
                	}
                	
                	$programList[$idinaff] = array(
                			'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                			'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                			'IdInAff' => $idinaff,
                			'Partnership' => $partnership                         //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                	);
                	
                	if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idinaff, $this->info['crawlJobId']))
                	{
                		$programDetailPostString = <<<EOF
    {"C":"Gpf_Rpc_Server", "M":"run", "requests":[{"C":"Pap_Affiliates_Promo_CampaignsGrid", "M":"getRows", "offset":0, "limit":30, "filters":[["search","L","{$idinaff}"]], "columns":[["id"],["id"],["name"],["description"],["logourl"],["banners"],["rstatus"],["affstatus"],["commissionsexist"],["actions"]]}], "S":"{$this->session}"}
EOF;
                		$request['postdata'] = 'D=' . urlencode($programDetailPostString);
                		$detail_res = $this->oLinkFeed->GetHttpResult($tmpUrl,$request);
                		$detail_res = json_decode($detail_res['content'],true);
                		if (count($detail_res[0]['rows']) != 2){
                			print_r($detail_res);
                			mydie('get program detail wrong!');
                		}
                		if (strpos($detail_res[0]['rows'][1][5], 'tica de Keywords')){
                			$SEMPolicyExt = trim($this->oLinkFeed->ParseStringBy2Tag($detail_res[0]['rows'][1][5],'Keywords:</font><font class="Apple-style-span" color="#444444">','/'));
                		}else {
                			$SEMPolicyExt = '';
                		}
                		
                		
	                	$programList[$idinaff] += [
		                	'CrawlJobId' => $this->info['crawlJobId'],
		                	'Name' => $name,
		                	'Homepage' => '',
		                	'Description' => $description,
		                	'StatusInAff' => $statusInAff,
		                	'CommissionExt' => $commission,
		                	'TargetCountryExt' => '',
		                	'CategoryExt' => '',
		                	'LogoUrl' => $logUrl,
		                	'SEMPolicyExt' => addslashes($SEMPolicyExt),
		                	//'SupportDeepUrl' => 'NO',
	                	];
	                	$base_program_num++;
                	}
                	$program_num++;
                }
                
            } else {
                break;
            }
        }

        if ($this->isFull)
        {
//         obtain country & category & homepage
	        $request = array(
	            "AccountSiteID" => $this->info["AccountSiteID"],
	            "method" => "get",
	        );
	        $tmpUrl = 'http://smart4ads.com/smart4ads/client/CampaignList/datasource/Campaigns.xml?r=2980111&89789';
	        $cacheName="data_" . date("YmdH") . "_statisticpage.dat";
	        $result = $this->oLinkFeed->GetHttpResultAndCache($tmpUrl,$request,$cacheName,'row');
	        $result = preg_replace('@>\s+<@', '><', $result);
	        $partern = '/<!\[CDATA\[(.*?)\]\]>/';
	        $result = preg_replace($partern, '$1', $result);
	        $result = $this->oLinkFeed->ParseStringBy2Tag($result, array('<data>'), '</data');
	        $itemArray = explode('</row><row', $result);
	        foreach ($itemArray as $item) {
	            $tmpArray = explode('</column><column', $item);
	            $name = $this->oLinkFeed->ParseStringBy2Tag($tmpArray[1], array('>'));
	            $name = trim(strtolower($name));
	            if (isset($existsProgramList[$name])) {
	                $programId = $existsProgramList[$name];
	                if (isset($arr_prgm[$programId])) {
	                    $homePage = $this->oLinkFeed->ParseStringBy2Tag($tmpArray[0], array('column', '>'));
	                    $tmp = explode('+', $homePage);
	                    if (count($tmp) == 2) {
	                        $homePage = $tmp[1];
	                    } else {
	                        $homePage = '';
	                    }
	                    $country = $this->oLinkFeed->ParseStringBy2Tag($tmpArray[5], array('>'));
	                    $category = $this->oLinkFeed->ParseStringBy2Tag($tmpArray[6], array('>'), '</column');
	                    $arr_prgm[$programId]['Homepage'] = addslashes($homePage);
	                    $arr_prgm[$programId]['TargetCountryExt'] = addslashes($country);
	                    $arr_prgm[$programId]['CategoryExt'] = addslashes($category);
	                }
	            }
	        }
        }

        if (!empty($arr_prgm)) {
        	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
        }
        if ($program_num < 1) {
            mydie("die: program count < 1, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
        echo "\tGet Program by Api end\r\n";
    }

}


?>
