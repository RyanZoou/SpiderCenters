<?php
class LinkFeed_2059_LinkTech
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;

        $this->file = "programlog_{$aff_id}_" . date("Ymd_His") . ".csv";
        $this->islogined = false;
        $this->linkFlag = '广告内容';
        $this->linkNextPageFlag = '尾页';
        $this->maxPage = 500;
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

    /**
     * 解析抓取到的列表页面，获取到program基本信息
     *
     * @param $baiscArray
     * @param $content
     */
    function parsePageList(&$baiscArray, $content) {
        $content = $this->oLinkFeed->ParseStringBy2Tag($content, array('class="portlet-body"><table class=', '<tbody','>'), '</tbody>');
        $programePartern = '/<\/\s*tr\s*><\s*tr\s*>/i';
        $originalprogramArray = preg_split($programePartern, $content, -1, PREG_SPLIT_NO_EMPTY);
        $nowDate = date('Y-m-d H:i:s');
        foreach ($originalprogramArray as $v) {
            $itemPartern = '/<\s*\/\s*td\s*>/i';
            $itemArray = preg_split($itemPartern, $v);
            if (count($itemArray) == 13) {
                $idinaff = $this->oLinkFeed->ParseStringBy2Tag($itemArray[2], array('<td', '>'));
                $name = $this->oLinkFeed->ParseStringBy2Tag($itemArray[1], array('<br>'));
                $logUrl = $this->oLinkFeed->ParseStringBy2Tag($itemArray[1], array('<img src=\''), '\'');
                $commissionext = $this->oLinkFeed->ParseStringBy2Tag($itemArray[6], array('>'));
                $targetcountryext = '';
                $categoryext = $this->oLinkFeed->ParseStringBy2Tag($itemArray[5], array('>'));
                
                //get partnership
                $partnership = '';
                $tmp =$this->oLinkFeed->ParseStringBy2Tag($itemArray[10], array('<td', '>'));
                
                if (stripos($tmp, '已通过') !== false) {
                    $partnership = 'Active';
                } elseif (stripos($tmp, '未申请') !== false) {
                    $partnership = 'NoPartnership';
                } elseif (stripos($tmp, '待审核') !== false) {
                    $partnership = 'Pending';
                } elseif (stripos($tmp, ' 拒绝') !== false) {
                    $partnership = 'Declined';
                } elseif (stripos($tmp, '拒绝') !== false) {
                    $partnership = 'Declined';
                } else {
                    mydie("partnership is invalied,please check the network{$this->info['NetworkID']}!");
                }
                
                $tmp = $itemArray[11];
                $supportDeepUrl = 'UNKNOWN';
                if (stripos($tmp, '自定义链接') !== false) {
                    $supportDeepUrl = 'YES';
                } elseif (stripos($tmp, '一般链接') !== false) {
                    $supportDeepUrl = 'NO';
                } else {
                    $supportDeepUrl = 'UNKNOWN';
                }
                $statusinaff = 'Active';
                if (isset($baiscArray[$idinaff])) {
                    continue;
                } else {
                	$baiscArray[$idinaff] = array(
                			'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                			'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                			'IdInAff' => $idinaff,
                			'Partnership' => $partnership                    //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                	);
                	if ($this->isFull){
                		//get SEM from detail page
                		$SEMPolicyExt = '';
                		$request = array(
                				'AccountSiteID'=>$this->info['AccountSiteID'],
                				'method'=>'get'
                		);
                		$detailurl = "http://www.linktech.cn/AC_NEW/merchant/merchant_view.php?merchant_id={$idinaff}&cpc_yn=N";
                		$detailres = $this->oLinkFeed->GetHttpResult($detailurl,$request);
                		$SEMPolicyExt = trim($this->oLinkFeed->ParseStringBy2Tag($detailres['content'], array('禁止关键词', '<td>'),'</td>'));
                		if (empty($SEMPolicyExt)) $SEMPolicyExt = "UNKNOW";
                		
	                    $baiscArray[$idinaff] += array(
	                    	'CrawlJobId' => $this->info['crawlJobId'],
	                        'Name' => addslashes($name),
	                        'Homepage' => '',
	                        'Description' => '',
	                        'StatusInAff' => $statusinaff,
	                        'CommissionExt' => addslashes($commissionext),
	                        'TargetCountryExt' => addslashes($targetcountryext),
	                        'CategoryExt' => addslashes($categoryext),
	                        'LogoUrl' => addslashes($logUrl),
	                        'SupportDeepUrl' => addslashes($supportDeepUrl),
	                    	'SEMPolicyExt' => addslashes($SEMPolicyExt)
	                    );
                	}
                }
                
            }
        }
    }

    function GetProgramByApi()
    {
        echo "\tGet Program by Api start\r\n";

        $this->login();
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $base_program_num = 0;
        $program_num = 0;
        $request = array(
            'AccountSiteID'=>$this->info['AccountSiteID'],
            'method'=>'get'
        );

        //first get the data from api (The data from api just part of all programs data);
        $api_program_info = array();
        $baseUrl = $this->info['LoginSuccUrl'];
        $perPage = 500;
        $page = 1;
        while (true) {
            $tmpListUrl = $baseUrl . "&page={$page}&per_page={$perPage}";
            $cacheName = "data_" . date("YmdH") . "_program_list{$page}.dat";
            $result = $this->oLinkFeed->GetHttpResultAndCache($tmpListUrl,$request,$cacheName,'广告主列表');
            $result = preg_replace('@>\s+<@', '><', $result);
            $this->parsePageList($api_program_info, $result);
            if (stripos($result, '尾页') === false) {
                break;
            } else {
                $page++;
            }
            if ($page >= $this->maxPage) {
                mydie("get the page of program list of '{$this->info['AffId']} exceed max limit {$this->maxPage}', please check the network!\r\n");
            }
        }

        $innerBaseUrl = 'http://www.linktech.cn/AC_NEW/merchant/merchant_link.php?cpc_yn=N&merchant_id=';
        $tmpCount = 0;
        foreach ($api_program_info as $programInfo) { // obtain homepage and description
            $idinaff = isset($programInfo['IdInAff']) ? $programInfo['IdInAff'] : '';
            if (empty($idinaff)) {
                continue;
            } else {
            	if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idinaff, $this->info['crawlJobId']))
            	{
	                $innerUrl = $innerBaseUrl . $idinaff;
	                $cacheName = "data_" . date("YmdH") . "_program_inner{$idinaff}.dat";
	                $result = $this->oLinkFeed->GetHttpResultAndCache($innerUrl,$request,$cacheName,'获取链接');
	                $result = preg_replace('@>\s+<@', '><', $result);
	                $homePage = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('网址:', '<a','>'), '</a'));
	                if (empty($homePage)) {
	                    continue;
	                } else {
	                    $programInfo['Homepage'] = $homePage;
	                }
	                $description = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('简介:'), '</div'));
	                $programInfo['Description'] = $description;
	                $base_program_num++;
            	}
	            $arr_prgm[$idinaff] = $programInfo;
            	$tmpCount++;
            	$program_num++;
            	if ($tmpCount >= 100) {
            		$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            		$tmpCount = 0;
            		$arr_prgm = array();
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
