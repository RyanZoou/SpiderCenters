<?php
class LinkFeed_2081_Access_Trade_VN
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;

        $this->islogined = false;
        $this->countryUrls = [
            'vietnam' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=VN%3A&category=&status=-1',
            'thailand' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=TH%3A&category=&status=-1',
            'indonesia' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=ID%3A&category=&status=-1',
            'japan' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=JP%3A&category=&status=-1',
            'belgium' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=BE%3A&category=&status=-1',
            'china' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=CN%3A&category=&status=-1',
            'croatia' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=HR%3A&category=&status=-1',
            'finland' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=FI%3A&category=&status=-1',
            'greece' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=GR%3A&category=&status=-1',
            'norway' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=NO%3A&category=&status=-1',
            'portugal' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=PT%3A&category=&status=-1',
            'russia' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=RU%3A&category=&status=-1',
            'sweden' => 'https://pub.accesstrade.vn/get_list_campaigns?keyword=&country=SE%3A&category=&status=-1',
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
            "method" => $this->info["LoginMethod"],
            "postdata" => $this->info["LoginPostString"],
            "no_ssl_verifyhost" => true,
        );

        $arr = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
        if ($arr["code"] == 0) {
            if (preg_match("/^SSL: certificate subject name .*? does not match target host name/i", $arr["error_msg"])) {
                $request["no_ssl_verifyhost"] = 1;
                $arr = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
            }
        }

        if ($arr["code"] == 200) {
//            file_put_contents('d:/tmp.txt', $arr["content"]);die;
            if (stripos($arr["content"], $this->info["LoginVerifyString"]) !== false) {
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

        $api_program_info = array();
        $objProgram = new ProgramDb();
        $request = array(
            'AccountSiteID'=>$this->info['AccountSiteID'],
            'method'=>'get',
        );
        $apiUrl = 'https://api.accesstrade.vn/v1/campaigns';
        $request['addheader'] = [
            'Authorization:Token bgDthRlSnyfWUSD3YF_jOj_0bS4hM-Ly',
            'Content-Type:application/json',
        ];
        $cacheName = "data_" . date("YmdH") . "_api.dat";
        $result = $this->oLinkFeed->GetHttpResultAndCache($apiUrl,$request,$cacheName,'total');
        $result = json_decode($result, true);
        $nowDate = date('Y-m-d H:i:s');
        if (isset($result['data'])) {
            foreach ($result['data'] as $item) {
                $idinaff = $item['id'];
                $name = addslashes($item['merchant']);
                $tmp = $item['approval'];
                if ($tmp == 'successful') {
                    $partnership = 'Active';
                } elseif ($tmp == 'pending') {
                    $partnership = 'Pending';
                } elseif ($tmp == 'unregistered') {
                    $partnership = 'NoPartnership';
                } else {
                    mydie("unknown partnership {$tmp} of '{$this->info['AffId']}', please check \r\n");
                }
                
                $api_program_info[$idinaff] = array(
                		'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                		'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                		'IdInAff' => $idinaff,
                		'Partnership' => $partnership                         //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                );
                
                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idinaff, $this->info['crawlJobId']))
                {
                
	                $description = addslashes($item['description']);
	                $homePage = addslashes($item['url']);
	                $categoryString = addslashes("{$item['category']}-{$item['sub_category']}");
	                $api_program_info[$idinaff] += [
	                	'CrawlJobId' => $this->info['crawlJobId'],
	                    'Name' => $name,
	                    'Homepage' => $homePage,
	                    'Description' => $description,
	                    'StatusInAff' => 'Active',
	                    'CommissionExt' => '',
	                    'TargetCountryExt' => '',
	                    'CategoryExt' => $categoryString,
	                    'LogoUrl' => '',
	                    //'SupportDeepUrl' => 'YES'
	                ];
                }
            }
        } else {
            mydie("get the program info of '{$this->info['AffId']} through api fail', please check \r\n");
        }
        
        if($this->isFull){
        	$this->login();
        	$request = array(
        			'AccountSiteID'=>$this->info['AccountSiteID'],
        			'method'=>'get',
        	);
        	
        	// logo, commission, SEM
        	foreach ($api_program_info as $programId => &$item) {
        		$detailPageUrl = "https://pub.accesstrade.vn/campaigns/{$programId}";
        		$cacheName="data_" . date("YmdH") . "_detail{$programId}.dat";
        		$result = $this->oLinkFeed->GetHttpResultAndCache($detailPageUrl,$request,$cacheName,'brandreward');
        		$result = preg_replace('@>\s+<@', '><', $result);
        		$logUrl = addslashes($this->oLinkFeed->ParseStringBy2Tag($result, array('class="widget-user-image"', 'src="'), '"'));
        		$commissionext = $this->oLinkFeed->ParseStringBy2Tag($result, array('Hoa hồng:', '<b>'), '</b');
        		$item['LogoUrl'] = $logUrl;
        		$item['CommissionExt'] = $commissionext;
        		$SEMPolicyExt = '';
        		$policy = $this->oLinkFeed->ParseStringBy2Tag($result, array('Quy định về cách chạy quảng cáo', '<div class="timeline-body">'), '</div>');
        		if (stripos($policy, 'sem') || stripos($policy, 'Google Adwords')){
        			if (stripos($policy, '</p>')){
        				$policy_list = explode("</p>",$policy);
        				foreach ($policy_list as $p){
        					if (stripos($p, 'sem') || stripos($p, 'Google Adwords')) $SEMPolicyExt.= $p;
        				}
        			}elseif (stripos($policy, '</li>')){
        				$policy_list = explode("</li>",$policy);
        				foreach ($policy_list as $p){
        					if (stripos($p, 'sem') || stripos($p, 'Google Adwords')) $SEMPolicyExt.= $p;
        				}
        			}
        		}else {
        			$SEMPolicyExt = 'UNKNOW';
        		}
        		$item['SEMPolicyExt'] = addslashes(strip_tags($SEMPolicyExt));
        	}
        	unset($item);
        	
        	//country
        	$request = array(
        			'AccountSiteID'=>$this->info['AccountSiteID'],
        			'method'=>'get',
        	);
            $countryUrl = "https://pub.accesstrade.vn/get_list_countries";
            $r = $this->oLinkFeed->GetHttpResult($countryUrl,$request);
            $CountriesList = json_decode($r['content'], true)['list_countries_all'];

            foreach ($CountriesList as $item) {
                $pageUrl = "https://pub.accesstrade.vn/get_list_campaigns?keyword=&country={$item['code']}&category=&status=-1";
                $result = $this->oLinkFeed->GetHttpResult($pageUrl, $request);
                $result = json_decode($result['content'], true);

                foreach ($result['campaigns'] as $v) {
                    $info = json_decode($v, true);
                    if (!empty($info)) {
                        $programId = $info['_id'];
                        $commissionext = addslashes($info['max_commission']);
                        if (isset($api_program_info[$programId])) {
                            $api_program_info[$programId]['TargetCountryExt'] .= ",{$item['name']}";
                            $api_program_info[$programId]['CommissionExt'] = addslashes($commissionext);
                        }
                    }
                }
            }

        	foreach ($api_program_info as &$item) {
        		$item['TargetCountryExt'] = addslashes(trim($item['TargetCountryExt'], ','));
        	}
        	unset($item);
        	$base_program_num = count($api_program_info);
        }
        
        $program_num = count($api_program_info);
        if (!empty($api_program_info)) {
        	$objProgram->InsertProgramBatch($this->info["NetworkID"], $api_program_info, true);
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
