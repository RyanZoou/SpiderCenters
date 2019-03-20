<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_503_PublicIdeas
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->site_arr = json_decode($this->info['APIKey1'], true);
        $this->desc = array();
    }

    function GetProgramFromAff()
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Program start @ {$check_date}\r\n";
        $this->isFull = $this->info['isFull'];
        $this->GetProgramByPage();
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function GetProgramByPage()
    {
        echo "\tGet Program by page start\r\n";
        $objProgram = new ProgramDb();
        $program_num = $base_program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => "",);

        //-------------------第一次登陆，获取h值，h值是第二次登陆的postdata的一部分-----------------------------------
        $request_firstLogin = $request;
        $request_firstLogin['postdata'] = "loginAff=".urlencode($this->info["UserName"])."&passAff=".urlencode($this->info["Password"])."&site=pi&userType=aff";
        $arr = $this->oLinkFeed->GetHttpResult("https://performance.timeonegroup.com/logmein.php",$request_firstLogin);
        //print_r($arr);exit;
        $h = json_decode($arr['content'])->h;
        //-------------------第二次登陆，登录前，先将info数组中的AffLoginPostString值，加上&h=----------------------------------
        $this->info['LoginPostString'] = $this->info['LoginPostString'].'&h='.urlencode($h);
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);

        $site_arr = $this->site_arr;
        global $active_program;
        $active_program = array();

        foreach($site_arr as $siteid => $s_key){
            echo "\tprocess site:$siteid myprograms\r\n";

            if ($this->isFull) {
                $url = "http://publisher.publicideas.com/xmlProgAff.php?partid={$siteid}&key={$s_key}&noDownload=yes";
                $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"],"$siteid" . ".dat","cache_merchant");
                if(!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
                    $request["method"] = "get";
                    $r = $this->oLinkFeed->GetHttpResult($url,$request);
                    $result = mb_convert_encoding($r["content"],'UTF-8','CP1252');
                    $this->oLinkFeed->fileCachePut($cache_file,$result);
                }
                if(!file_exists($cache_file)) {
                    mydie("die: merchant csv file does not exist. \n");
                }
                $xml = simplexml_load_file($cache_file);
                for ($k = 0; $k < count($xml->program); $k ++) {
                    $program = $xml->program[$k];
                    $IdInAff = (array)$program['id'];
                    $strMerId = $IdInAff[0];

                    $Description = (array)$program->program_description;
                    $str = (STRING)$Description[0];
                    $this->desc[$strMerId]['Description'] = $str;
                }
            }

            $program_num_myprograms = $this->GetProgramBySubPage($objProgram, $request, $siteid, 'myprograms', '', '100');

            echo "\tprocess site:$siteid catprog\r\n";
            $program_num_catprog = $this->GetProgramBySubPage($objProgram, $request, $siteid, 'catprog', 'search', '10');
            $program_num_arr = array_merge($program_num_myprograms['site'], $program_num_catprog['site']);
            $base_program_num_arr = array_merge($program_num_myprograms['base'], $program_num_catprog['base']);

            $program_num += count($program_num_arr);
            $base_program_num += count($base_program_num_arr);
        }

        echo "\tGet Program by page end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }

        echo "\tUpdate ({$base_program_num}) base programs." . PHP_EOL;
        echo "\tUpdate ({$program_num}) program.\r\n";

    }

    function GetProgramBySubPage($objProgram,$request,$site,$action,$type,$nb_page)
    {//$site是我们自己的站点在联盟中的代号，如46631;后面三个形参是url中的参数
        echo "\tget $site $action \r\n";
        global $active_program;
        $program_num = array(
            'site' => array(),
            'base' => array()
        );

        //模拟选取国家的过程
        $request_chooseCountry = $request;
        $request_chooseCountry['postdata'] = 'country%5B%5D=DE&country%5B%5D=FR&country%5B%5D=GB';
        $arr_chooseCountry = $this->oLinkFeed->GetHttpResult("http://publisher.publicideas.com/index.php?action=country",$request_chooseCountry);
        if($arr_chooseCountry['code'] != 200){
            mydie("choose country failed！");
        }
        echo "\t\tchoose country finished \r\n";

        //获取program的页数
        $str_url = 'http://publisher.publicideas.com/index.php?action='.$action.'&categorie_id=0&index=0&nb_page='.$nb_page.'&keyword=&type='.$type;
        $result = $this->oLinkFeed->GetHttpResult($str_url,$request);
        $matches = array();
        preg_match_all('/index=(\d*)/', $result['content'], $matches);
        $page = $matches[1];

        $page = array_unique($page);
        sort($page);
        echo "\t\tget page".count($page)." finished \t";
        //---------------------------------------------循环$page数组即是循环每一个分页页面，爬取每一个页面的数据--------------------------------------------
        if(!count($page)) $page = array(1 => 0);
        foreach ($page as $k=>$p){//分页循环
            echo "p:.$p.\t";
            if($action != 'myprograms_encours' && $action != 'myprograms_rejete'){
                $prgmArr = array();
                $nOffset = 0;
                if($p == 0){
                    $page_content = $result['content'];
                }else{
                    $page_url = 'http://publisher.publicideas.com/index.php?action='.$action.'&categorie_id=0&nb_page='.$nb_page.'&keyword=&type='.$type.'&index='.$p;
                    $page_content = $this->oLinkFeed->GetHttpResult($page_url,$request)['content'];
                }
                $page_content = mb_convert_encoding($page_content,'UTF-8','CP1252');//西欧编码转utf8
                $page_content = str_replace(array("\r","\n","\t"), "", $page_content);
                preg_match_all('#www.publicidees.com/logo/programs/logo#', $page_content, $matches);//有几个logo，就有几个program
                $programNum = count($matches[0]);

                for($k = 0; $k < $programNum; $k ++) {
                    $name = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, 'class="progTitreF">', ' &laquo;</td>', $nOffset));
                    $namePos = $nOffset;
                    $idInAff = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, array('<span class="progImg">', 'logo_'), '_', $namePos));
                    $namePos = $nOffset;//ParseStringBy2Tag函数在运行的过程中，$onOffset变量在不断地增加，$onOffset是strpos函数中的第三个变量，意思是从哪里开始搜索自字符串

                    if ($action == 'catprog') {
                        $partnerShip = 'NoPartnership';
                    } elseif ($action == 'myprograms') {
                        $partnerShip = 'Active';
                    }
                    if (isset($active_program[$idInAff])) {
                        $partnerShip = 'Active';
                    }
                    if ($partnerShip == 'Active') {
                        $active_program[$idInAff] = 1;
                    }
                    $prgmArr[$idInAff] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],
                        'BatchID' => $this->info['batchID'],
                        'IdInAff' => $idInAff,
                        'Partnership' => $partnerShip,
                    );

                    if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idInAff, $this->info['crawlJobId'])) {
                        $mobile = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, '<li><strong>Mobiles : </strong>', '</li>', $namePos));
                        $namePos = $nOffset;
                        $homePage = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, array('width="120" height="60"', '<a href="'), '" target="_blank">', $namePos));
                        $namePos = $nOffset;
                        $country = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, 'flags/16/', '.png', $namePos));
                        $namePos = $nOffset;
                        $commission = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, '<div style="padding:20px">', '</div>', $namePos));
                        $namePos = $nOffset;
                        $SEMPolicyExt = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, "<li><strong>Mini-shops, search engines, white label, iframe, etc. : </strong>", '</li>', $namePos));
                        $namePos = $nOffset;
                        $publisherPolicy = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, "<li><strong>Reduction vouchers : </strong>", '</li>', $namePos));
                        $namePos = $nOffset;
                        $contact = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, 'height="9" />&nbsp;<a href="mailto:', '?subject=', $namePos));
                        $namePos = $nOffset;
                        $homePage = $this->oLinkFeed->findFinalUrl(urldecode($homePage));
                        if (stripos($publisherPolicy, 'Yes') !== false){
                        	$SupportType = "Content".EX_CATEGORY."Coupon";
                        }elseif (stripos($publisherPolicy, 'No') !== false){
                        	$SupportType = "Content";
                        }else{
                        	echo "idinaff : {$idInAff}, name : {$name},find new SupportType : ";
                        	mydie($publisherPolicy);
                        }

                        $prgmArr[$idInAff] += array(
                            'Name' => addslashes($name),
                            'StatusInAff' => 'Active',
                            'MobileFriendly' => ($mobile == 'Yes') ? 'Yes' : 'No',
                            'Homepage' => addslashes($homePage),
                            'TargetCountryExt' => addslashes($country),
                            'CommissionExt' => addslashes($commission),
                            'Contacts' => 'Email: ' . addslashes($contact),
                            'PublisherPolicy' => 'Reduction vouchers : ' . addslashes($publisherPolicy),
                            'Description' => isset($this->desc[$idInAff]['Description']) ? addslashes($this->desc[$idInAff]['Description']) : '',
                        	'SEMPolicyExt' => 'Mini-shops, search engines, white label, iframe, etc. : '.addslashes($SEMPolicyExt),
                            'CrawlJobId' => $this->info['crawlJobId'],
                        	'SupportType' => $SupportType
                        );
                        $program_num['base'][] = $idInAff;
                    }
                    $program_num['site'][] = $idInAff;
                }

                $objProgram->InsertProgramBatch($this->info["NetworkID"], $prgmArr, true);
            }
        }
        echo "\r\n";
        return $program_num;
    }

}
