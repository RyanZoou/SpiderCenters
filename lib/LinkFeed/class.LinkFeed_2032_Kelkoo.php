<?php
require_once 'text_parse_helper.php';
class LinkFeed_2032_Kelkoo
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;

        $this->country_arr = json_decode($this->info['APIKey1'], true);
        $this->config_key_arr = json_decode($this->info['APIKey5'], true);
    }

    function UrlSigner($urlDomain, $urlPath, $country)
    {
        if(!$country) mydie('UrlSigner no country. ');
        settype($urlDomain, 'String');
        settype($urlPath, 'String');
        settype($this->config_key_arr[$country]['TrackingId'], 'String');
        settype($this->config_key_arr[$country]['AffiliateKey'], 'String');

        $URL_sig = "hash";
        $URL_ts = "timestamp";
        $URL_partner = "aid";
        $time = time();
        $urlPath = str_replace(" ", "+", $urlPath);
        $URLtmp = $urlPath . "&" . $URL_partner . "=" . $this->config_key_arr[$country]['TrackingId'] . "&" . $URL_ts . "=" . $time;
        $s = $urlPath . "&" . $URL_partner . "=" . $this->config_key_arr[$country]['TrackingId'] . "&" . $URL_ts . "=" . $time . $this->config_key_arr[$country]['AffiliateKey'];
        $tokken = base64_encode(pack('H*', md5($s)));
        $tokken = str_replace(array("+", "/", "="), array(".", "_", "-"), $tokken);
        $URLreturn = $urlDomain . $URLtmp . "&" . $URL_sig . "=" . $tokken;
        return $URLreturn;
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
    
    function getOfferFeedMID($country){
    	//连接ftp服务器 并到offer目录下
    	$ftp_url = "ftpkelkoo.kelkoo.net";
    	$connect = ftp_connect($ftp_url,21) or mydie("ftp connect error");
    	ftp_login($connect,'BrandRewardHongKongLimited','DeugMocAm8') or mydie("ftp login error");
    	//php ftp_nlist() ftp_rawlist()无法返回目录中的文件列表，是因为没有开启被动模式(passive mode).
		//如果在主动模式（active mode）连接FTP服务器情况下无法返回目录中的文件，可以使用ftp_pasv($ftp, true)来开启被动模式。
		ftp_pasv($connect, true);
    	ftp_chdir($connect,'offer');
    	if (ftp_chdir($connect,$country)){
    		$offers = ftp_rawlist($connect, ".");
    		foreach ($offers as $offer){
    			if(preg_match("/Offerfeed_(\d+)_{$country}.full.xml.gz/", $offer,$match )){
    				$merchants[] = $match[1];
    			}
    		}
    		ftp_close($connect);
    		return $merchants;
    	}else{
    		ftp_close($connect);
    		return array();
    	}
    }

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        $tmp_prgm = array();
        foreach($this->country_arr as $country){
            $arr_prgm = array();
            $base_program_num = 0;
            $merchants = array();
            $merchants = $this->getOfferFeedMID($country);
            $url = $this->UrlSigner('http://'.$country.'.shoppingapis.kelkoo.com', '/V2/categorySearch?format=Tree&shortcuts=false&features=None', $country);
            echo $url;
            $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "category_$country" . ".dat","cache_merchant");//返回.cache文件的路径
            if(!$this->oLinkFeed->fileCacheIsCached($cache_file))
            {
                $request["method"] = "get";
                $r = $this->oLinkFeed->GetHttpResult($url,$request);
                $result = $r["content"];
                $this->oLinkFeed->fileCachePut($cache_file,$result);
            }
            if(!file_exists($cache_file)) mydie("die: category $country file does not exist. \n");

            $categoryResult = simplexml_load_file($cache_file);
            $category = $categoryResult -> Category;
            $category_arr = array();
            $category_rel = array();
            foreach($category->Category as $lv1){

                $category_arr[(int)$lv1['id']]['name'] = (string)$lv1['name'];
                $category_arr[(int)$lv1['id']]['deadend'] = 0;
                $category_rel[(int)$lv1['id']] = array();

                foreach($lv1 as $lv2){
                    $category_arr[(int)$lv2['id']]['name'] = (string)$lv2['name'];

                    if($lv2->Category){
                        $category_arr[(int)$lv2['id']]['deadend'] = 0;
                        $category_rel[(int)$lv1['id']][(int)$lv2['id']] = array();
                        foreach($lv2 as $lv3){
                            $category_arr[(int)$lv3['id']]['name'] = (string)$lv3['name'];
                            $category_arr[(int)$lv3['id']]['deadend'] = 1;
                            $category_arr[(int)$lv3['id']]['name_rel'] = (string)$lv1['name'] . ' >> ' . (string)$lv2['name'] . ' >> ' . (string)$lv3['name'];
                            $category_rel[(int)$lv1['id']][(int)$lv2['id']][(int)$lv3['id']] = (string)$lv1['name'] . ' >> ' . (string)$lv2['name'] . ' >> ' . (string)$lv3['name'];
                        }
                    }else{
                        $category_arr[(int)$lv2['id']]['deadend'] = 1;
                        $category_arr[(int)$lv2['id']]['name_rel'] = (string)$lv1['name'] . ' >> ' . (string)$lv2['name'];
                        $category_rel[(int)$lv1['id']][(int)$lv2['id']] = (string)$lv1['name'] . ' >> ' . (string)$lv2['name'];
                    }
                }
            }
            foreach($category_arr as $catid => $cat)
            {
                if($cat['deadend'] !== 1) {
                    continue;
                }

                $page = 1;
                $return_limit = 100;
                $total_page = 0;
                $total = 0;
                $cnt = 0;
                $hasNextPage = 1;
                while($hasNextPage){
                    $url = $this->UrlSigner('http://'.$country.'.shoppingapis.kelkoo.com', '/V2/merchantSearch?category=' . $catid . '&enable=store_details,store_profile,store_ratings,store_payment&start=' . ((($page - 1) * $return_limit) + 1) . '&results=' . $return_limit, $country);

                    $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], date('Y-m-d') ."p_{$country}_{$catid}_{$page}" . ".dat","cache_merchant");//返回.cache文件的路径
                    if(!$this->oLinkFeed->fileCacheIsCached($cache_file))
                    {
                        $request["method"] = "get";
                        $r = $this->oLinkFeed->GetHttpResult($url,$request);
                        $result = $r["content"];
                        $this->oLinkFeed->fileCachePut($cache_file,$result);
                    }
                    if(!file_exists($cache_file)) mydie("die: category file does not exist. \n");

                    $result = simplexml_load_file($cache_file);

                    if($total_page == 0){
                        $total = $result['totalResultsAvailable'];
                        $total_page = ceil((int)$result['totalResultsAvailable'] / $return_limit);
                    }

                    if($page >= $total_page || $cnt > $total){
                        $hasNextPage = 0;
                    }
                    $page++;
                    foreach($result as $v){
                        $IdInAff = (int)$v['id'];
                        if(!$IdInAff) {
                            continue;
                        }

                        if(!isset($tmp_prgm[$IdInAff])){
                            $tmp_prgm[$IdInAff] = $country;
                        }elseif($tmp_prgm[$IdInAff] != $country){
                            mydie("has repeat program. $IdInAff | {$tmp_prgm[$IdInAff]} : $country");
                        }

                        if (!isset($arr_prgm[$IdInAff])) {
                            $arr_prgm[$IdInAff] = array(
                                'AccountSiteID' => $this->info["AccountSiteID"],
                                'BatchID' => $this->info['batchID'],
                                'IdInAff' => $IdInAff,
                                'Partnership' => 'Active',
                            );
                        }

                        if ($this->isFull) {
                            /* $CategoryExt = $cat['name_rel'];
                            if (isset($arr_prgm[$IdInAff]['CategoryExt'])) {
                                $CategoryExt = $arr_prgm[$IdInAff]['CategoryExt'] . EX_CATEGORY . $CategoryExt;
								$arr_prgm[$IdInAff]['CategoryExt'] = addslashes($CategoryExt);
                            } */
                        	$CategoryExt = $catid;
                        	$ss = 0;
                        	if (isset($arr_prgm[$IdInAff]['CategoryExt'])) {
                        		$arr_prgm[$IdInAff]['CategoryExt'] .= ",".addslashes($CategoryExt);
                        	}else{
                        		$arr_prgm[$IdInAff]['CategoryExt'] = addslashes($CategoryExt);
                        	}

                            $arr_prgm[$IdInAff] += array(
                                "Name" => addslashes(trim($v->Name)),
                                "Homepage" => addslashes($v->MerchantUrl),
                                "LogoUrl" => addslashes($v->Profile->Logo->Url),
                                "TargetCountryExt" => $country,
//                               	"CategoryExt" => addslashes($CategoryExt),
                                'StatusInAff' => 'Active'
                            );
                        }
                        $program_num++;
                        $cnt++;
                    }
                }
            }
            if (!empty($merchants)){
	            $idinaffs = array_keys($arr_prgm);
	            $diff = array_diff($idinaffs, $merchants);
	            foreach ($diff as $mer){
	            	if (isset($arr_prgm[$mer])){
	            		$arr_prgm[$mer]['Partnership'] = "NoPartnership";
	            	}
	            }
            }
            echo count($arr_prgm)."/{$program_num}\r\n";
            
            //get AffDefaultUrl
            foreach($arr_prgm as $idinaff => $v){
            	if ($this->isFull){
            		$cate_string = array();
            		$cate_id_arr = explode(',', $v['CategoryExt']);
            		foreach ($cate_id_arr as $value){
            			$cate_string[] = $category_arr[$value]['name_rel'];
            		}
            		$arr_prgm[$idinaff]['CategoryExt'] = addslashes(implode(EX_CATEGORY, $cate_string));
            	}
            	
                $AffDefaultUrl = '';
                $url = $this->UrlSigner('http://'.$country.'.shoppingapis.kelkoo.com', '/V3/productSearch?merchantId=' . $idinaff . '&sort=default_ranking&logicalType=and&show_products=1&show_subcategories=0&show_refinements=0&custom1=x&start=1&results=1', $country);
                $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], date('m_d_H') ."program_{$idinaff}" . ".dat","product");//返回.cache文件的路径
                if(!$this->oLinkFeed->fileCacheIsCached($cache_file))
                    {
                        $request["method"] = "get";
                        $r = $this->oLinkFeed->GetHttpResult($url,$request);
                        $result = $r["content"];
                        $this->oLinkFeed->fileCachePut($cache_file,$result);
                    }
                if(!file_exists($cache_file)) mydie("die: AffDefaultUrl does not exist. \n");
                $result = simplexml_load_file($cache_file);
                $result = $result -> Products -> Product;

                if(is_object($result->Offer->Url) && strlen($result->Offer->Url)){
                    $AffDefaultUrl = (string)$result->Offer->Url;
                    $AffDefaultUrl = str_replace('custom1=x', 'custom1=[SUBTRACKING]', $AffDefaultUrl);
                }
                $arr_prgm[$idinaff]['AffDefaultUrl'] = addslashes($AffDefaultUrl);
                $base_program_num ++;
            }
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);

            echo "\tGet Program by api end\r\n";

            if(count($arr_prgm) < 10){
                mydie("die: program count < 10, please check program.\n");
            }

            echo "\tUpdate $country ({$base_program_num}) base programs.\r\n";
            echo "\tUpdate $country (".count($arr_prgm).") program.\r\n";
            unset($arr_prgm);
        }
    }

    function getTransactionFromAff($start_date, $end_date)
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

        $objTransaction = New TransactionDb();
        $arr_transaction = $arr_find_Repeated_transactionId = array();
        $tras_num = 0;
        $begin_dt = $start_date;
        $end_dt = $end_date;

        $api_url = "https://partner.kelkoo.com/statsSelectionService.xml?pageType=custom&from=[fromDate]&to=[toDate]&currency=EUR&countries=All&split=daily&username=[Account]&password=[Password]";
        $request = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "get");

        $i = 0;
        while ($begin_dt < $end_dt) {
            $tmp_dt = date('Y-m-d', strtotime('+2 day', strtotime($begin_dt)));
            $tmp_dt = $tmp_dt > $end_dt ? $end_dt : $tmp_dt;
            $i ++;

            $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "data_" . date("YmdH") . "_Transaction_{$tmp_dt}.tmp", 'Transaction', true);
            if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
                $url = str_replace(array('[fromDate]', '[toDate]', '[Account]', '[Password]'), array($begin_dt, $tmp_dt, $this->info['UserName'], $this->info['Password']), $api_url);
                echo "req => {$url} \n";

                $fw = fopen($cache_file, 'w');
                if (!$fw) {
                    mydie("File open failed {$cache_file}");
                }
                $request['file'] = $fw;

                $result = $this->oLinkFeed->GetHttpResult($url, $request);
                if ($result['code'] != 200){
                    mydie("Download tmp file failed.");
                }
                fclose($fw);
            }
            if (isset($result)) {
                unset($result);
            }
            $result = json_decode(json_encode(simplexml_load_file($cache_file)), true);

            foreach ($result['tracking'] as $v)
            {
                $country = trim($v['country']);
                $Custom1 = @trim($v['Custom1']);
                $day = date('ymd', strtotime($v['day']));
                $revenue = trim($v['revenue']);
                $TransactionId = "{$Custom1}_{$day}_{$country}_$revenue";
                if (!$TransactionId) {
                    continue;
                }

//                if (isset($arr_find_Repeated_transactionId[$TransactionId])) {
//                    mydie("The transactionId={$TransactionId} have early exists!");
//                } else {
//                    $arr_find_Repeated_transactionId[$TransactionId] = '';
//                }

                $arr_transaction[$TransactionId] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'TransactionId' => $TransactionId,                      //must be unique
                    'country' => addslashes($v['country']),
                    'day' => addslashes($v['day']),
                    'Custom1' => @addslashes($v['Custom1']),
                    'Custom2' => @addslashes(json_encode($v['Custom2'])),
                    'Custom3' => @addslashes(json_encode($v['Custom3'])),
                    'numberOfLeads' => addslashes($v['numberOfLeads']),
                    'revenue' => addslashes($v['revenue']),
                    'currency' => addslashes($v['currency']),
                    'deviceType' => addslashes($v['deviceType']),
                );
                $tras_num ++;

                if (count($arr_transaction) >= 100) {
                    $objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
                    $arr_transaction = array();
                }
            }
            $begin_dt = date('Y-m-d', strtotime('+1 day', strtotime($tmp_dt)));
            if ($i > 50) {
                break;
            }
        }

        if (count($arr_transaction) > 0) {
            $objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
            unset($arr_transaction);
        }
//        unset($arr_find_Repeated_transactionId);

        echo "\tUpdate ({$tras_num}) Transactions.\r\n";
        echo "Craw Transaction end @ " . date("Y-m-d H:i:s") . "\r\n";

        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }
}
