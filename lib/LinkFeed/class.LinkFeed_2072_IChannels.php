<?php
class LinkFeed_2072_IChannels
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;

        $this->accountid = $this->info['APIKey1'];
        $this->key = $this->info['APIKey2'];
        $this->maxPage = $this->info['APIKey3'];
        $this->programCategoryArray = array(
            27 => '3C家電',
            28 => '金融理財',
            29 => '服飾精品',
            30 => '購物商城',
            31 => '家居生活',
            32 => '媽咪寶貝',
            33 => '旅遊訂房',
            34 => '美容保養',
            35 => '美食特產',
            36 => '命理星座',
            37 => '書籍雜誌',
            38 => '網路服務',
            39 => '醫護保健',
            40 => '休閒影音',
            41 => '線上遊戲',
            42 => '教育學習',
            43 => '其他類別',
            45 => '美食情報',
            47 => '寵物水族',
        );
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
        echo "\tGet Program by Page start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = $category_prgm_arr = array();
        $program_num = $base_program_num = 0;
        $request_program = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", 'postdata' => '');

        //login
        $this->oLinkFeed->LoginIntoAffService($this->info['AccountSiteID'],$this->info);

        $partnership_arr = array('2' => 'Active', '3' => 'Pending', '4' => 'Declined', '' => 'NoPartnership');
        foreach($partnership_arr as $tag => $partnership){
            $perPage = 100;
            $page = 1;
            $hasNextPage = true;
            $tmp_num = 0;
            while ($hasNextPage) {
                $Partnership = $partnership;
                if ($page >= $this->maxPage) {
                    mydie("get the page of program list of ($partnership) exceed max limit {$this->maxPage}', please check the network! basic\r\n");
                }
                $tmpListUrl = "https://www.ichannels.com.tw/sitemember_new/manufacturer-index.php?tag={$tag}&page={$page}&pageSize={$perPage}";
                $cache_name = "program_list{$tag}_{$page}_" . date('YmdH') . '.cache';
                $result = $this->oLinkFeed->GetHttpResultAndCache($tmpListUrl, $request_program, $cache_name, $this->accountid);
                $result = preg_replace('@>\s+<@', '><', $result);
                if (stripos($result, '沒有相關數據') !== false) {
                    break;
                }
                $result = $this->oLinkFeed->ParseStringBy2Tag($result, array('<tbody>'), '</tbody');
                $originalprogramArray = explode('</tr><tr>', $result);
                if (count($originalprogramArray) != $perPage) {
                    $hasNextPage = false;
                }
                foreach ($originalprogramArray as $v) {
                	$v = str_replace('</th>', '</td>', $v);
                    $itemArray = explode('</td><td', $v);
                    if (count($itemArray) == 6) {
                        $idinaff = $this->oLinkFeed->ParseStringBy2Tag($itemArray[0], array('?id='), '"');

                        echo "$idinaff\t";

                        if($Partnership == 'NoPartnership'){
                            //auto apply partnership
                            if(stripos($itemArray[4], '不需申請') !== false){
                                $apply_url = "https://www.ichannels.com.tw/sitemember_new/manufacturer-ajaxcheckcppermission.php";
                                $tmp_request = array('AccountSiteID' => $this->info['AccountSiteID'], 'method' => 'post', 'postdata' => 'mid=' . $idinaff,);
                                $tmpResult = $this->oLinkFeed->GetHttpResult($apply_url, $tmp_request);
                                if($tmpResult['code'] == '200' && $tmpResult['content'] == '{"rs":1}'){
                                    $apply_url = "https://www.ichannels.com.tw/sitemember_new/manufacturer-ajaxadduser.php";
                                    $tmpResult = $this->oLinkFeed->GetHttpResult($apply_url, $tmp_request);
                                    if($tmpResult['code'] == '200' && $tmpResult['content'] == '{"error":0}'){
                                        $Partnership = 'Active';
                                    }
                                }
                            }
                        }

                        //get program detail page !
                        $homePage = $AffDefaultUrl = '';
                        $request = array('AccountSiteID'=>$this->info['AccountSiteID'], 'method'=>'get');
                        $detailUrl = 'https://www.ichannels.com.tw/sitemember_new/manufacturer-detail.php?id=' . urlencode($idinaff);
                        $cache_name = "programDetail_{$idinaff}_" . date('YmdH') . '.cache';
                        $detailResult = $this->oLinkFeed->GetHttpResultAndCache($detailUrl, $request, $cache_name, $this->accountid);
                        $detailResult = preg_replace('@>\s+<@', '><', $detailResult);
                        if (stripos($detailResult, '本廠商暫不支援成效標籤') !== false) {
                            $Partnership = 'NoPartnership';
                        }

                        if ($Partnership == 'Active') {
                            $affResult = $this->oLinkFeed->ParseStringBy2Tag($detailResult, '<!-- 推廣連結與素材-->', '<!-- 商品列表-->');
                            if (stripos($affResult, '首頁') !== false) {
                                $destUrl = $this->oLinkFeed->ParseStringBy2Tag($detailResult, array('請選擇推廣頁面', '<i', 'url="'), '"');
                                if (!empty($destUrl)) {
                                    $homePage = $destUrl;
                                    $destUrl = urlencode($destUrl);
                                    $apiUrl = "http://api.ichannels.com.tw/sitemember/main-url.php?key={$this->key}&member_code={$this->accountid}&url={$destUrl}";
                                    $tmpResult = $this->oLinkFeed->GetHttpResult($apiUrl, $request);
                                    $tmpResult = @json_decode($tmpResult['content'], true);
                                    if (isset($tmpResult['gen_url']) && !empty($tmpResult['gen_url'])) {
                                        $AffDefaultUrl = $tmpResult['gen_url'];
                                    }
                                }
                            }
                        }

                        $arr_prgm[$idinaff] = array(
                            'AccountSiteID' => $this->info["AccountSiteID"],
                            'BatchID' => $this->info['batchID'],
                            'IdInAff' => $idinaff,
                            'Partnership' => $Partnership,
                            "AffDefaultUrl" => addslashes($AffDefaultUrl),
                        );

                        if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idinaff, $this->info['crawlJobId'])) {
                            $category_prgm_arr[$idinaff] = array(
                                'AccountSiteID' => $this->info["AccountSiteID"],
                                'BatchID' => $this->info['batchID'],
                                'IdInAff' => $idinaff,
                                'CategoryExt' => '',
                            );

                            $logUrl = $this->oLinkFeed->ParseStringBy2Tag($itemArray[0], array('<img', 'src="'), '"');
                            $name = $this->oLinkFeed->ParseStringBy2Tag($itemArray[0], array('<img', '<a', '>'), '</a');
                            $commissionext = $this->oLinkFeed->ParseStringBy2Tag($itemArray[1], array('>'));
                            $description = $this->oLinkFeed->ParseStringBy2Tag($detailResult, array('廣告主介紹', '</h2>'), '<h2');
                            if (empty($homePage)) {
                                $homePage = $this->oLinkFeed->ParseStringBy2Tag($detailResult, array('通路王會員中心', $name, '</span><a', 'href="'), '"');
                            }

                            $supportDeepUrl = 'UNKNOWN';
                            if (stripos($detailResult, '自訂推廣頁') !== false) {
                                if (stripos($detailResult, '本廠商暫不支援自訂推廣頁') !== false) {
                                    $supportDeepUrl = 'NO';
                                } else {
                                    $supportDeepUrl = 'YES';
                                }
                            }
                            
                            //SEM Policy
                            $SEMPolicyExt = '';
                            $policy_list = $this->oLinkFeed->ParseStringBy2Tag($detailResult,array("廣告廠商推廣限制",'<table width="100%" border="0" cellspacing="0" cellpadding="0" class="tab3">'),'</table>');
                            if (!empty($policy_list)){
                            	$policy_list = explode("</tr>",$policy_list);
                            	foreach ($policy_list as $oneline){
                            		if(stripos($oneline, '關鍵字廣告購買') !== false){
                            			$SEMPolicyExt .= strip_tags($oneline)."\n";
                            		}
                            	}
                            }
//                             echo $SEMPolicyExt;exit;

                            $arr_prgm[$idinaff] += array(
                                'CrawlJobId' => $this->info['crawlJobId'],
                                'Name' => addslashes($name),
                                'StatusInAff' => 'Active',
                                'CommissionExt' => addslashes(trim($commissionext,'\r\n\s')),
                                'LogoUrl' => addslashes($logUrl),
                                'Homepage' => addslashes($homePage),
                                'Description' => addslashes($description),
                                'SupportDeepUrl' => $supportDeepUrl,
                            	"SEMPolicyExt" => addslashes($SEMPolicyExt)
                            );
                            $base_program_num ++;
                        }
                        $program_num ++;

                        $tmp_num++;

                        if(count($arr_prgm) >= 100){
                            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                            $arr_prgm = array();
                        }
                    }
                }

                $page++;
            }
            if(count($arr_prgm)){
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }

            echo "($partnership $tmp_num)" . PHP_EOL;
        }
        unset($arr_prgm);

        echo "Get category info start" . PHP_EOL;

        $request = array(
            'AccountSiteID' => $this->info['AccountSiteID'],
            'method' => 'post',
        );
        foreach ($this->programCategoryArray as $categoryId => $categoryName) {
            $request['postdata'] = "keywords=&category_id%5B%5D={$categoryId}&commision_type%5B%5D=0&commision_count%5B1%5D%5Ba%5D=%E4%B8%8D%E9%99%90&commision_count%5B1%5D%5Bz%5D=%E4%B8%8D%E9%99%90&commision_count%5B2%5D%5Ba%5D=%E4%B8%8D%E9%99%90&commision_count%5B2%5D%5Bz%5D=%E4%B8%8D%E9%99%90&deal_date=0&rd=0&is_make_data%5B%5D=-1&if_acl%5B%5D=0&sortBy=manufacturer_id&sortOrder=DESC&tag=1&stag=";
            $perPage = 100;
            $page = 1;
            $hasNextPage = true;
            while ($hasNextPage) {
                if ($page >= $this->maxPage) {
                    mydie("get the page of program list of '{$this->info['AccountSiteID']} exceed max limit {$this->maxPage}', please check the network! category{$categoryId} categoryName {$categoryName}\r\n");
                }
                $tmpListUrl = "https://www.ichannels.com.tw/sitemember_new/manufacturer-index.php?page={$page}&pageSize={$perPage}";
                $cache_name = "program_category_{$categoryId}_list{$page}_" . date('YmdH') . '.cache';
                $result = $this->oLinkFeed->GetHttpResultAndCache($tmpListUrl, $request, $cache_name, $this->accountid);
                $result = preg_replace('@>\s+<@', '><', $result);
                if (stripos($result, '沒有相關數據') !== false) {
                    break;
                }
                $result = $this->oLinkFeed->ParseStringBy2Tag($result, '<tbody>', '</tbody');
                $originalprogramArray = explode('</tr><tr>', $result);
                if (count($originalprogramArray) != $perPage) {
                    $hasNextPage = false;
                }
                foreach ($originalprogramArray as $v) {
                    $itemArray = explode('</td><td', $v);
                    if (count($itemArray) == 5) {
                        $idinaff = $this->oLinkFeed->ParseStringBy2Tag($itemArray[0], '?id=', '"');
                        if (isset($category_prgm_arr[$idinaff])) {
                            $category_prgm_arr[$idinaff]['CategoryExt'] .= EX_CATEGORY.$categoryName;
                            $category_prgm_arr[$idinaff]['CategoryExt'] = trim(ltrim($category_prgm_arr[$idinaff]['CategoryExt'],EX_CATEGORY),'\r\n\s');
                        }
                    }
                }
                $page++;
            }
        }

        $objProgram->InsertProgramBatch($this->info["NetworkID"], $category_prgm_arr);
        echo "Get category info end" . PHP_EOL;

        echo "\n\tGet Program by Page end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

}
