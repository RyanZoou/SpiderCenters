<?php

class LinkFeed extends LinkFeedDb
{
    //var $CookiePath = "/tmp/likefeed/";
    var $affiliates = array();
    var $instances = array();
    var $workingdirs = array();
    var $httpinfos = array();
    var $debug = false;

    function __construct($paras = array())
    {
        if (!isset($this->objMysql))
            $this->objMysql = new MysqlExt();
        $this->ignorecheck = isset($paras["ignorecheck"]) ? 1 : 0;
        $this->nocache = isset($paras["nocache"]) ? 1 : 0;
        $this->oHttpCrawler = new HttpCrawler();
    }
	
	/**
	 * 根据siteId获取联盟的实例
	 * @param $siteId
	 * @return mixed
	 */
    function getInstance($siteId)
    {
        if (isset($this->instances[$siteId])) return $this->instances[$siteId];
        if (!isset($this->affiliates[$siteId])) $this->getAffById($siteId);            //getAffById函数将查询的记录，存入affiliates数组
        if (!isset($this->affiliates[$siteId])) mydie("siteid({$siteId}) not found.");
        $class_name = $this->getClassNameBySiteID($siteId);                            //getClassNameBySiteID返回值return "LinkFeed_" . network . "_" . $class_name

        $class_file = $this->getClassFilePath($class_name);                              //getClassFilePath返回联盟类文件路径
        if (!is_file($class_file)) $this->createDefaultClassFile($siteId);

        include_once($this->getClassFilePath($class_name));
        $obj = new $class_name($siteId, $this);//去看113的构造函数，就知道这里为什么要俩形参								  //php中，如果变量值是一个类名，可以直接new这个变量，即相当于new这个类
        if (!is_object($obj)) mydie("get Instance of $class_name failed");
        $this->instances[$siteId] = $obj;
        return $obj;
    }
	
	/**
	 * 创建默认位置的功能文件
	 * @param $siteId
	 * @return bool
	 */
    function createDefaultClassFile($siteId)
    {
        $class_template_file = INCLUDE_ROOT . "classtemplate.txt";
        $template_text = file_get_contents($class_template_file);                     //file_get_contents函数将文件内容读入一个字符串
        $class_name = $this->getClassNameBySiteID($siteId);
        $class_file = $this->getClassFilePath($class_name);
        if (file_exists($class_file)) {
            echo basename($class_file) . " is existing , skip it ...\n";               //basename()返回路径的文件名部分
            return false;
        }

        $arr_from = array("{class_name}");
        $arr_to = array($class_name);
        foreach ($this->affiliates[$siteId] as $k => $v) {
            $arr_from[] = "{" . $k . "}";
            $arr_to[] = addslashes($v);
        }

        file_put_contents($class_file, str_replace($arr_from, $arr_to, $template_text));
        chmod($class_file, 0666);                                                       //chmod函数改变文件的读写权限
    }
	
	/**
	 * 根据类名生成并返回类文件的path
	 * @param $class_name
	 * @return string
	 */
    function getClassFilePath($class_name)
    {
        $class_file = "class." . $class_name . ".php";
        return INCLUDE_ROOT . "lib/LinkFeed/" . $class_file;             //INCLUDE_ROOT是当前文件的路径
    }
	
	/**
	 * 根据给定的类名按照类名生成规则返回类文件名称
	 * @param $networkId
	 * @param $display_name
	 * @return string
	 */
    function getClassNameByDisplayName($networkId, $display_name)
    {
        $class_name = trim($display_name);
        if (($pos = strpos($class_name, "(")) !== false) $class_name = trim(substr($class_name, 0, $pos));
        $class_name = str_replace(array(" ", ".", "-"), "_", $class_name);
        $class_name = ucfirst($class_name);
        if (!$class_name) mydie("something wrong here");
        return "LinkFeed_" . $networkId . "_" . $class_name;
    }
	
	/**
	 * 根据siteid返回对应的类名
	 * @param $siteId
	 * @return string
	 */
    function getClassNameBySiteID($siteId)
    {
        if (!isset($this->affiliates[$siteId])) $this->getAffById($siteId);
        if (!isset($this->affiliates[$siteId])) mydie("siteid($siteId) not found.");
        $display_name = $this->affiliates[$siteId]["NetworkName"];
        return $this->getClassNameByDisplayName($this->affiliates[$siteId]['NetworkID'], $display_name);
    }

    //===============================================================================
	
	/**
	 * 根据指定的Site，返回缓存文件的路径
	 * @param $aff_id
	 * @param $file_name
	 * @param $group_name
	 * @param bool $use_true_file_name = false
	 * @return string
	 */
    function fileCacheGetFilePath($site_id, $file_name, $group_name, $use_true_file_name = false)
    {
    	if(!$use_true_file_name) $file_name .= "." . date("YmdH") . ".cache";
        $working_dir = $this->getWorkingDirBySiteID($site_id, $group_name);
        return $working_dir . $file_name;
    }
	
	/**
	 * 判断给定的路径是否是一个已经存在的缓存文件
	 * @param $cache_file
	 * @return bool
	 */
    function fileCacheIsCached($cache_file)
    {
        if ($this->nocache) return false;
        return file_exists($cache_file);
    }
	
	/**
	 * 获取指定路径的缓存文件中的内容
	 * @param $cache_file
	 * @return bool|string
	 */
    function fileCacheGet($cache_file)
    {
        if ($this->fileCacheIsCached($cache_file)) return file_get_contents($cache_file);
        return false;
    }
	
	/**
	 * 向指定缓存文件的路径中插入缓存内容
	 * @param $cache_file
	 * @param $content
	 * @return bool|int
	 */
    function fileCachePut($cache_file, &$content)//向$cache_file文件中写入$content内容
    {
        $cache_file_temp = $cache_file . "." . time();
        $r = file_put_contents($cache_file_temp, $content);
        if ($r === false) {
            @unlink($cache_file_temp);
            return false;
        }
        @chmod($cache_file_temp, 0666);
        @rename($cache_file_temp, $cache_file);
        return $r;
    }
	
	/**
	 * 创建LinkFeed_$affId_$groupName对应的工作空间(搭建数据存放目录)，并返回其路径
	 * @param $affId
	 * @param string $groupName
	 * @return string
	 */
    function getWorkingDirBySiteID($site_id, $group_name = "")
    {
        if (isset($this->workingdirs[$site_id][$group_name])) {
            return $this->workingdirs[$site_id][$group_name];
        }

        $is_mkdir = false;

        $dir = INCLUDE_ROOT . "data/";                                                        //创建data文件夹
        if (!is_dir($dir)) {
            $is_mkdir = true;
            mkdir($dir);
            chmod($dir, 0777);
        }
        $dir .= $this->getClassNameBySiteID($site_id) . "/";                                    //在data文件夹下，创建LinkFeed_10_AW等文件夹
        if (!is_dir($dir)) {
            $is_mkdir = true;
            mkdir($dir);
            chmod($dir, 0777);
        }
        $dir .= "site_{$site_id}/";                                    //在data文件夹下，创建LinkFeed_10_AW等文件夹
        if (!is_dir($dir)) {
            $is_mkdir = true;
            mkdir($dir);
            chmod($dir, 0777);
        }

        if ($group_name) {
            $dir .= $group_name . "/";
            if (!is_dir($dir)) {
                $is_mkdir = true;
                mkdir($dir);
                chmod($dir, 0777);
            }
        }
        if ($is_mkdir && !is_dir($dir)) mydie("make Working Dir failed: $dir\n");

        $this->workingdirs[$site_id][$group_name] = $dir;
        return $dir;
    }

	/**
	 * 调用功能类库中的getMerAffIDByURL($strURL)方法，获取所有的商家和link
	 * @param $aff_id
	 */
    function GetAllMerchantAndLink($aff_id)
    {
        $arr_return = array(
            "AffectedCount" => 0,
            "UpdatedCount" => 0,
        );

        $arr_log = array(
            "JobName" => __METHOD__,
            "AffId" => $aff_id,
            "MerchantId" => "",
            "SiteId" => 0,
            "AffectedCount" => 0,
            "UpdatedCount" => 0,
            "Detail" => "",
        );
        $this->addJob($arr_log);
        $this->GetMerchantListFromAff($aff_id);
        $this->GetAllLinksFromAff($aff_id);
        $this->endJob($arr_log);
    }

	/**
	 * 调用功能类库中的getMerAffIDByURL($strURL)方法，获取多有的商家列表
	 * @param $aff_id
	 * @return array
	 */
    function GetMerchantListFromAff($aff_id)
    {
        $arr_return = array(
            "AffectedCount" => 0,
            "UpdatedCount" => 0,
            "DefaultErrorMsg" => "Method " . __METHOD__ . " in LinkFeed Object(AffId=$aff_id) not found",
        );

        return $arr_return;

        $_obj = $this->getInstance($aff_id);
        if (method_exists($_obj, 'GetMerchantListFromAff')) {
            //add log
            $arr_log = array(
                "JobName" => __METHOD__,
                "AffId" => $aff_id,
                "MerchantId" => "",
                "SiteId" => 0,
                "AffectedCount" => 0,
                "UpdatedCount" => 0,
                "Detail" => "",
            );
            $this->addJob($arr_log);

            $arr_return = $_obj->GetMerchantListFromAff();

            $arr_log["AffectedCount"] = $arr_return["AffectedCount"];
            $arr_log["UpdatedCount"] = $arr_return["UpdatedCount"];
            $this->endJob($arr_log);
        }

        if ($this->debug && !isset($arr_return["DefaultErrorMsg"])) {
            print "GetMerchantListFromAff for aff $aff_id is finished. <br>\n";
            print "here is the result: " . print_r($arr_return, true) . "<br>\n";
        }
        return $arr_return;
    }

	/**
	 * 清除http访问记录（cookie文件），并清除保存的this->info信息
	 * @param $site_id
	 */
    function clearHttpInfos($site_id)
    {
        $cookiejar = $this->getCookieJarBySiteId($site_id);
        if (file_exists($cookiejar)) @unlink($cookiejar);//unlink() 函数删除文件。若成功，则返回 true，失败则返回 false。
        if (isset($this->httpinfos[$site_id])) unset($this->httpinfos[$site_id]);
    }

	/**
	 * 返回对应affid的文件的路径（类似data/LinkFeed_10_AW/aff_10.cookie）
	 * @param $aff_id
	 * @return mixed
	 */
    function getCookieJarBySiteId($site_id)
    {
        if (!isset($this->httpinfos[$site_id]["cookiejar"])) {
            $this->httpinfos[$site_id]["cookiejar"] = $this->getWorkingDirBySiteID($site_id) . "accountSite_" . $site_id . ".cookie";
        }
        return $this->httpinfos[$site_id]["cookiejar"];
    }

	/**
	 * 按照$_para数组中指定的参数定义的规则获取URL的资源
	 *（该方法自动获取该affid对应的cookie文件位置并设置进$_para["cookiejar"]参数内）
	 * @param $_url
	 * @param array $_para
	 * @param string $ch
	 * @return array
	 */
    function GetHttpResult($_url, $_para = array(), $ch = "")
    {
        if (isset($_para["AccountSiteID"]) && !isset($_para["cookiejar"])) {
            $_para["cookiejar"] = $this->getCookieJarBySiteId($_para["AccountSiteID"]);
        }
        return $this->oHttpCrawler->GetHttpResult($_url, $_para, $ch);//HttpCrawler类中的函数，返回爬取的页面各项信息的数组
        //return HttpCrawler::GetHttpResult($_url,$_para);
    }

    /*********************************************************************************************
     * Pay attention! If you don't understand this function please use function 'GetHttpResult'!
     * @param $url
     * @param $request
     * @param $cacheFileName    $cacheFileName is a string and must be provided! Please jion the date parameter.
     * @param string $valStr
     * @param int $retry
     * @return bool|mixed|string
     ********************************************************************************************/
    function GetHttpResultAndCache($url, $request, $cacheFileName, $valStr='', $retry=3)
    {
        if (!isset($request['AccountSiteID'])) {
            mydie("AccountSiteID can not be empty!");
        }
        if (empty($cacheFileName)) {
            mydie("CacheFileName can not be empty!");
        }

        $results = '';
        $cache_file = $this->fileCacheGetFilePath($request['AccountSiteID'], $cacheFileName, 'data', true);
        if (!$this->fileCacheIsCached($cache_file)) {
            while ($retry) {
                $r = $this->GetHttpResult($url, $request);
                if ($valStr) {
                    if (strpos($r['content'], $valStr) !== false) {
                        $results = $r['content'];
                        break;
                    }
                } elseif (!empty($r['content'])) {
                    $results = $r['content'];
                    break;
                }
                sleep(5);
                $retry--;
            }

            if (!$results) {
                mydie("Can't get the content of '{$url}', please check the val string !\r\n");
            }
//            $results = mb_convert_encoding($results, "UTF-8", mb_detect_encoding($results));
            $this->fileCachePut($cache_file, $results);

            return $results;
        }
        $result = file_get_contents($cache_file);

        return $result;
    }

	/**
	 * 模拟一个常规登录方式联盟的登录
	 * @param $aff_id
	 * @param $info
	 * @param int $retry
	 * @param bool $processverify
	 * @param bool $forcedefaultlogin
	 * @param bool $checkpreviousseesion
	 * @return bool
	 */
    function LoginIntoAffService($site_id, $info, $retry = 3, $processverify = true, $forcedefaultlogin = false, $checkpreviousseesion = true)//模拟登陆
    {
        if (isset($this->httpinfos[$site_id]["islogined"])) return $this->httpinfos[$site_id]["islogined"];
        if ($checkpreviousseesion && isset($info["LoginSuccUrl"]) && isset($info["LoginVerifyString"]) && $info["LoginSuccUrl"] && $info["LoginVerifyString"]) {
            //try to use previous seesion,看cookie文件中是否有postdata的信息，如果有，就能模拟登陆了，如果没有，跳出此if，向下执行
            $request = array("AccountSiteID" => $info["AccountSiteID"], "no_ssl_verifyhost" => true,);
            $arr = $this->GetHttpResult($info["LoginSuccUrl"], $request);//返回爬取的AffLoginSuccUrl页面各项信息的数组
            if (stripos($arr["content"], $info["LoginVerifyString"]) !== false) {
                echo "very good, previous session found, VerifyString is '" . $info["LoginVerifyString"] . "' <br>\n";
                $this->httpinfos[$site_id]["islogined"] = true;
                return true;
            }
        }
        if (!isset($info['LoginUrl'])) return false;
        $this->clearHttpInfos($site_id);//删除缓存文件，删除httpinfos[$aff_id]变量
        $islogined = false;
        $_obj = $this->getInstance($site_id);
        if (!$forcedefaultlogin && method_exists($_obj, 'LoginIntoAffService'))//method_exists()检查类的方法是否存在
        {
            echo "processing self LoginIntoAffService ...\n";
            $islogined = $_obj->LoginIntoAffService();
            if ($islogined === "stophere") return false;
        } else {
            //default login method,第一次登陆都要走这里
            $request = array(
                "AccountSiteID" => $info["AccountSiteID"],
                "method" => $info["LoginMethod"],
                "postdata" => $info["LoginPostString"],
                "no_ssl_verifyhost" => true,
            );
            if (isset($info["referer"])) $request["referer"] = $info["referer"];
            $arr = $this->GetHttpResult($info['LoginUrl'], $request);//返回爬取的AffLoginSuccUrl页面各项信息的数组
// 			echo "<pre>";
// 			$arr['content'] = str_ireplace("<", "#;", $arr['content']);
// 			print_r($arr);
// 			exit;

            //if code = 0, set ssl verifyhost false
            if ($arr["code"] == 0) {
                if (preg_match("/^SSL: certificate subject name .*? does not match target host name/i", $arr["error_msg"])) {
                    $request["no_ssl_verifyhost"] = 1;
                    $arr = $this->GetHttpResult($info['LoginUrl'], $request);
                }
            }

            if ($arr["code"] == 200) {
                if ($processverify && isset($info["LoginVerifyString"]) && $info["LoginVerifyString"]) {
                    //checking login page result
                    if (stripos($arr["content"], $info["LoginVerifyString"]) !== false) {
                        echo "verify succ: " . $info["LoginVerifyString"] . "\n";
                        $islogined = true;
                    }
                    //handle redir by meta tag
                    if (!$islogined && stripos($arr["content"], "REFRESH") !== false && isset($info["LoginSuccUrl"]) && $info["LoginSuccUrl"]) {
                        $url_path = @parse_url($info["LoginSuccUrl"], PHP_URL_PATH);//parse_url用于解析url，返回一个关联数组。parse_url("xxx", PHP_URL_PATH)返回数组的path值
                        if ($url_path && stripos($arr["content"], $url_path) !== false) {
                            echo "good, verify succ (redir by meta tag) <br>\n";
                            $islogined = true;
                        }

                    }
                    if (!$islogined) echo "verify login failed(" . $info["LoginVerifyString"] . ") <br>\n";
                } elseif (isset($info["LoginSuccUrl"]) && isset($info["LoginVerifyString"]) && $info["LoginSuccUrl"] && $info["LoginVerifyString"]) {
                    //checking AffLoginSuccUrl
                    //try to use previous seesion
                    $request = array("AccountSiteID" => $info["AccountSiteID"],);
                    $arr = $this->GetHttpResult($info["LoginSuccUrl"], $request);
                    if (stripos($arr["content"], $info["LoginVerifyString"]) === false) {
                        print_r($arr);
                        mydie("die: login failed for site($site_id) by double checking AffLoginSuccUrl <br>\n");
                    }
                } else {
                    $islogined = true;
                }
            }

        }
        if (!$islogined) {
            if ($retry > 1) {
                if ($retry > 10) $retry = 10;
                if ($retry < 2) $retry = 2;

                $sec = 300 - $retry * 60;
                if ($sec < 60) $sec = 60;
                echo "login failed ... wait $sec and retry ...\n";
                sleep($sec);
                return $this->LoginIntoAffService($site_id, $info, --$retry, $processverify, $forcedefaultlogin, $checkpreviousseesion);
            }
            print_r($arr);
            mydie("die: login failed for aff($site_id) <br>\n");
        }

        if ($this->debug) print "good, site($site_id) is Logined! <br>\n";

        $this->httpinfos[$site_id]["islogined"] = $islogined;
        return $islogined;
    }

	/**
	 * ?
	 * @param $_file
	 * @param $_arr
	 */
    function logarray($_file, &$_arr)
    {
        foreach ($_arr as $k => $v) $_arr[$k] = $this->trimfortsv(trim($v));
        $msg = implode("\t", $_arr) . "\t" . date("Y-m-d H:i:s") . "\n";
        error_log($msg, 3, $_file);//error_log方法，将信息写入文件
    }

	/**
	 * 按指定规则的正则匹配指定的字符串
	 * @param $str
	 * @param $pattern
	 * @param int $offset
	 * @param string &$result
	 * @return bool
	 */
    function str_seek(&$str, $pattern, $offset = 0, &$result = "")
    {
        $result = array();
        if (preg_match('|^/.*/[imseADSUXu]*$|', $pattern)) {
            if (preg_match($pattern, $str, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                $result = array(
                    "pos" => $matches[0][1],
                    "string" => $matches[0][0],
                    "length" => strlen($matches[0][0]),
                );
            } else return false;
        } else {
            $pos = stripos($str, $pattern, $offset);
            if ($pos === false) return false;
            $result = array(
                "pos" => $pos,
                "string" => $pattern,
                "length" => strlen($pattern),
            );
        }
        return true;
    }

	/**
	 * 获取$strSource字符串中，介于$patternBefore和$patternAfter之间的内容
	 * @param $strSource
	 * @param $patternBefore
	 * @param string $patternAfter
	 * @param int $nOffset
	 * @param string $result
	 * @return bool|string
	 */
    function ParseStringBy2Tag(&$strSource, $patternBefore, $patternAfter = "", &$nOffset = 0, &$result = "")
    {
        $result = array();
        $last_matched_pos = $nOffset;
        $last_matched_str_length = 0;
        if (!empty($patternBefore)) {

            if (is_string($patternBefore)) {
                $patternBefore = array($patternBefore);
            }
            for ($i = 0; $i < sizeof($patternBefore); $i++) {
                $pattern = $patternBefore[$i];
                if ($this->str_seek($strSource, $pattern, $last_matched_pos + $last_matched_str_length, $seek_result))//str_seek函数判断$patter是不是$strSource的子字符串
                {   //$strSource是爬取页面返回的content
                    $result[] = $seek_result;
                    $last_matched_pos = $seek_result["pos"];
                    $last_matched_str_length = $seek_result["length"];
                } else return false;
            }
        }
// 		echo "<pre>";
// 		print_r($result);
// 		exit;
        if ($patternAfter == '') {
            $nOffset = $end_pos = strlen($strSource);
        } else if ($this->str_seek($strSource, $patternAfter, $last_matched_pos + $last_matched_str_length, $seek_result)) {
            $result[] = $seek_result;
            $end_pos = $seek_result["pos"];
            //$nOffset = $end_pos + $seek_result["length"];
            $nOffset = $end_pos;
        } else return false;//end pattern not found

        $strResult = substr($strSource, $last_matched_pos + $last_matched_str_length, $end_pos - $last_matched_pos - $last_matched_str_length);//取<select>和</select>中间的content

        if ($this->debug) print "ParseStringBy2Tag($last_matched_pos,$nOffset) result: $strResult  <br>\n";
        return $strResult;
    }

	/**
	 * 获取$strSource字符串中，介于$patternBefore数组最后一个元素和$patternAfter之间的内容
	 * （$patternBefore数组的非末尾元素都是用来指路匹配的。）
	 * @param $strSource
	 * @param $patternBefore
	 * @param $patternAfter
	 * @param int $nOffset
	 * @return array
	 */
    function ParseStringBy2TagToArray(&$strSource, $patternBefore, $patternAfter, &$nOffset = 0)
    {
        $arr_return = array();
        while ($str = $this->ParseStringBy2Tag($strSource, $patternBefore, $patternAfter, $nOffset)) {
            $arr_return[] = $str;
        }
        return $arr_return;
    }

    function findFinalUrl($url, $request_arr = array())
    {
        $return_url = "";
        if ($url) {
            $default_request = array("header" => 1, "nobody" => 1, "no_ssl_verifyhost" => 1, "maxredirs" => 15, "timeout" => 60);
            $default_request = array("FinalUrl" => 1);
            foreach ($request_arr as $k => $v) {
                if ($v == "unset") {
                    unset($default_request[$k]);
                } else {
                    $default_request[$k] = $v;
                }
            }
            $r = $this->GetHttpResult($url, $default_request);
            $header = $r["content"];
            return empty($header) ? $url : $header;
            // print_r($header);
            if (strlen($header < 1000)) {
                //find JS
                preg_match("/window\.location\.replace\((['|\"])([^\1\)]*)\1\)/si", $header, $matches);
                if (isset($matches[2]) && strlen($matches[2]) > 10) {
                    echo "\r\nfind JS redirect: {$matches[2]}\r\n";
                    $return_url = $matches[2];
                    /*$default_request["nobody"] = 1;
                    $r = $this->GetHttpResult($matches[2], $default_request);
                    $header = $r["content"];*/
                }
            }

            if (empty($return_url)) {
                preg_match_all("/Location:(.*)\r\n/i", $header, $matches);
                if (count($matches[1])) {
                    $i = 0;
                    while (count($matches[1])) {
                        $loc = array_pop($matches[1]);
                        if ($return_url = stristr($loc, "http")) {
                            $return_url = preg_replace("/[\"']+/i", "", $return_url);
                            break;
                        }
                        if ($i > 10) {
                            break;
                        }
                        $i++;
                    }
                }
            }

            if (empty($return_url)) {
                $return_url = $url;
            }
        }
        return $return_url;
    }

    //fixEnocding()方法用于转换linkfeed表某些字段的编码
    function fixEnocding(&$aff_id_or_info, &$arr, $forwhat)//$aff_id_or_info是affiliate表内所有字段的数组，$arr是$link数组
    {
        if (is_string($aff_id_or_info)) $arr_info = $this->getAffById($aff_id_or_info);
        else $arr_info = $aff_id_or_info;

        if ($forwhat == "merchant") $encoding_field = "AffMerchantEncoding";
        elseif ($forwhat == "feed") $encoding_field = "AffFeedEncoding";
        elseif ($forwhat == "link") $encoding_field = "AffLinkEncoding";
        else mydie("die: wrong fixEnocding para $forwhat \n");

        $to_encoding = "UTF-8";
        $from_encoding = "";
        if (isset($arr_info[$encoding_field]) && $arr_info[$encoding_field]) $from_encoding = $arr_info[$encoding_field];
        else return;

        $from_encoding = strtoupper($from_encoding);
        if ($from_encoding == $to_encoding) return;

        if ($forwhat == "merchant") $arrColNeedFix = array("MerchantName", "MerchantRemark");
        elseif ($forwhat == "feed" || $forwhat == "link") $arrColNeedFix = array("LinkName", "LinkDesc", "LinkHtmlCode");

        foreach ($arrColNeedFix as $col) {
            if (!isset($arr[$col])) continue;
            if ($arr[$col] == "") continue;
            $iconvres = @iconv($from_encoding, $to_encoding, $arr[$col]);
            if ($iconvres === false) {
                echo "warning: iconv failed for string: " . $arr[$col] . "\n";
                continue;
            }
            $arr[$col] = $iconvres;
        }
    }

	/****************************************** Program part ******************************************/

    function GetAllProgram($siteId, $crawlJobId, $isFull, $batchId = false)
    {
        $_obj = $this->getInstance($siteId);

        echo "\nJob start ...\n";
        echo "DepartmentName: " . $_obj->info['DepartmentName'] . "\n";
        echo "NetworkName: " . $_obj->info['NetworkName'] . "\n";
        echo "AccountSiteName: " . $_obj->info['AccountSiteName'] . "\n";

        //Create a new crawl batch record
        if (!$batchId) {
            $batchId = $this->startNewCrawlBatch($_obj->info, 'Program', $crawlJobId);
            echo "Start a new batch id= " . $batchId . "\n\n";
        }
        $_obj->info['batchID'] = $batchId;
        $_obj->info['isFull'] = $isFull;
        $_obj->info['crawlJobId'] = $crawlJobId;

        //crawl program.
        $_obj->GetProgramFromAff();

    }

    function CheckSiteProgram($batchId, $crawlJobId)
    {
        if (!$batchId && !$crawlJobId){
            mydie("Params are wrong when check batch data.\r\n");
        }

        if ($crawlJobId) {
            $siteArr = $this->getBatchInfoByCrawlJobId($crawlJobId);
        } else {
            $siteArr = $this->getBatchInfoByBatchId($batchId);
        }

        //check crawl batch
        foreach ($siteArr as $val) {
            $this->checkProgramSiteDataCrawlBatch($val['BatchID'], $val['NetworkID'], $val['AccountSiteID']);
        }
    }

    function SyncSiteProgram($batchId, $crawlJobId)
    {
        if (!$batchId && !$crawlJobId){
            mydie("Params are wrong when sync batch data.\r\n");
        }

        if ($crawlJobId) {
            $siteArr = $this->getBatchInfoByCrawlJobId($crawlJobId);
        } else {
            $siteArr = $this->getBatchInfoByBatchId($batchId);
        }

        //sync crawl batch
        foreach ($siteArr as $val) {
            $this->syncSiteProgramBatch($val['BatchID'], $val['NetworkID'], $val['AccountSiteID']);
        }
    }

    function CheckJobProgram($crawlJobId)
    {
        if (!$crawlJobId){
            mydie("Params are wrong when check job data.\r\n");
        }
        $siteArr = $this->getBatchInfoByCrawlJobId($crawlJobId);

        $haveCheck = $this->checkProgramBaseDataCrawlJob($crawlJobId, $siteArr[0]['NetworkID']);

        if ($haveCheck) {
            echo "Check end!";
        }
    }

    function SyncJobProgram($crawlJobId)
    {
        if (!$crawlJobId){
            mydie("Params are wrong when sync job data.\r\n");
        }
        $siteArr = $this->getBatchInfoByCrawlJobId($crawlJobId);

        $this->syncBaseProgramBatch($crawlJobId, $siteArr[0]['NetworkID']);
    }

    /****************************** check and sync help function **************************/

    function checkProgramSiteDataCrawlBatch($batchId, $networkId, $siteId)
    {
        if(!count($networkId) || !count($batchId) || !count($siteId)) {
            mydie("Params are wrong when check batch data.\r\n");
        }

        $batch_check_status = $this->getBatchCheckStatus($batchId);
        if (!$batch_check_status) {
            mydie("This batch( $batchId ) crawl don't exists!");
        }
        if ($batch_check_status != 'Uncheck'){
            return false;
        }

        $batch_crawl_status = $this->getBatchCrawlStatus($batchId);
        if ($batch_crawl_status != 'Done') {
            die("This batch( $batchId ) crawl haven't done.");
        }

        echo "Check site(siteId=$siteId and batchId=$batchId) data start @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->setBatchCheckStatus($batchId, 'Checking');

        $ProgramObj = $this->getProgramDbObj();
        $field_change_num = array();

        $check_site_result = $ProgramObj->checkSiteBatchDataChange($networkId, $siteId, $batchId);
        $allProgramsNum = $check_site_result['total_num'];

        if ($allProgramsNum > 0) {
            foreach ($check_site_result['change_detail'] as $key => $val) {
                foreach ($val as $fv) {
                    if (!isset($field_change_num[$fv])) {
                        $field_change_num[$fv] = array();
                    }
                    $field_change_num[$fv][] = $key;
                }
            }

            echo "\tThe change fields and num detail like that:\n";
            foreach ($field_change_num as $key => $val) {
                echo "\t\t$key => " . count($val) . "\n";
            }
            $programChangeNum = count($check_site_result['change_detail']);
            echo "\tThis batch has ($allProgramsNum) programs, and ($programChangeNum) programs have changed!\r\n";

            if ($programChangeNum / $allProgramsNum > 0.2) {
                $this->setBatchCheckStatus($batchId, 'Error');
                mydie("\n\tThis batch(batchid=$batchId) data have a big difference with old usable data!\r\n");
            } else {
            	$offlinePercent = $ProgramObj->checkProgramOffline($networkId, $batchId, $siteId);

            	if ($offlinePercent > 0.05) {
		            $this->setBatchCheckStatus($batchId, 'Error');
		            mydie("\n\tThis batch(batchid=$batchId) data have more than 5% programs offline!\r\n");
	            }
                $this->setBatchCheckStatus($batchId, 'Done');
            }
        } else {
	        $this->setBatchCheckStatus($batchId, 'Error');
	        mydie("\tThis batch has (0) programs\r\n");
        }
        echo "Check site data end @ " . date("Y-m-d H:i:s") . "\r\n";

        return true;
    }

    function checkProgramBaseDataCrawlJob($crawlJobId, $networkId)
    {
        if(!count($crawlJobId) || !count($networkId)) {
            mydie("Params are wrong when check job data.\r\n");
        }

        $job_check_status = $this->getJobCheckStatus($crawlJobId);
        if (!$job_check_status) {
            mydie("This crawlJobId( $crawlJobId ) crawl don't exists!");
        }
        if ($job_check_status != 'Uncheck') {
            return false;
        }

        $job_crawl_status = $this->getJobCrawlStatus($crawlJobId);
        if ($job_crawl_status != 'Done') {
            die("This job ( $crawlJobId ) crawl haven't done.");
        }

        echo "Check job (crawlJobId=$crawlJobId) data start @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->setJobCheckStatus($crawlJobId, 'Checking');

        $ProgramObj = $this->getProgramDbObj();
        $field_change_num = array();

        $check_site_result = $ProgramObj->checkBaseBatchDataChange($networkId, $crawlJobId);
        $allProgramsNum = $check_site_result['total_num'];

        if ($allProgramsNum > 0) {
            foreach ($check_site_result['change_detail'] as $key => $val) {
                foreach ($val as $fv) {
                    if (!isset($field_change_num[$fv])) {
                        $field_change_num[$fv] = array();
                    }
                    $field_change_num[$fv][] = $key;
                }
            }

            echo "\tThe change fields and num detail like that:\n";
            foreach ($field_change_num as $key => $val) {
                echo "\t\t$key => " . count($val) . "\n";
            }
            $programChangeNum = count($check_site_result['change_detail']);
            echo "\tThis job has ($allProgramsNum) programs, and ($programChangeNum) programs have changed!\r\n";

            if ($programChangeNum / $allProgramsNum > 0.2) {
                $this->setJobCheckStatus($crawlJobId, 'Error');
                mydie("\n\tThis job (crawlJobId=$crawlJobId) data have a big difference with old usable data!\r\n");
            } else {
                $this->setJobCheckStatus($crawlJobId, 'Done');
	            $this->setJobCheckExpired($crawlJobId, 'Program');
            }
        } else {
            echo "\tThis job has (0) programs\r\n";
	        $this->setJobCheckStatus($crawlJobId, 'Done');
        }
        echo "Check job data end @ " . date("Y-m-d H:i:s") . "\r\n";

        return true;
    }

    function syncSiteProgramBatch($batchId, $networkId, $siteId)
    {
        if(!count($networkId) || !count($batchId) || !count($siteId)) {
            mydie("Params are wrong when sync batch data.\r\n");
        }

        $batch_sync_status = $this->getBatchSyncStatus($batchId);
        if (!$batch_sync_status) {
            mydie("This batch( $batchId ) crawl don't exists!");
        }
        if ($batch_sync_status != 'Unsync') {
            return false;
        }

        $batch_check_status = $this->getBatchCheckStatus($batchId);
        if ($batch_check_status == 'Uncheck' || $batch_check_status == 'Checking'){
            die("This batch( $batchId ) data haven't been checked!");
        }
        if ($batch_check_status != 'Done') {
//            $this->setBatchSyncStatus($batchId, 'Error');
	        die("This batch( $batchId ) data are useless.");
        }

        echo "Sync batch data start @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->setBatchSyncStatus($batchId, 'Syncing');

        $ProgramObj = $this->getProgramDbObj();
        $result = $ProgramObj->syncSiteProgramBatchData($batchId, $networkId, $siteId);

        if ($result) {
            $this->setBatchSyncStatus($batchId, 'Done');
        } else {
            $this->setBatchSyncStatus($batchId, 'Error');
        }
        echo "Sync batch data end @ " . date("Y-m-d H:i:s") . "\r\n";
    }

    function syncBaseProgramBatch($crawlJobId, $networkId)
    {
        if(!count($networkId) || !count($crawlJobId)) {
            mydie("Params are wrong when sync job data.\r\n");
        }

        $job_sync_status = $this->getJobSyncStatus($crawlJobId);
        if (!$job_sync_status) {
            mydie("This job ( $crawlJobId ) crawl don't exists!");
        }
        if ($job_sync_status != 'Unsync') {
            return false;
        }

        $job_check_status = $this->getJobCheckStatus($crawlJobId);
        if ($job_check_status == 'Uncheck' || $job_check_status == 'Checking'){
            die("This job ( $crawlJobId ) data haven't been checked!");
        }
        if ($job_check_status != 'Done') {
//            $this->setJobSyncStatus($crawlJobId, 'Error');
	        die("This job ( $crawlJobId ) data are useless.");
        }

        echo "Sync job data start @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->setJobSyncStatus($crawlJobId, 'Syncing');

        $ProgramObj = $this->getProgramDbObj();
        $result = $ProgramObj->syncBaseProgramBatchData($networkId, $crawlJobId);

        if ($result) {
            $this->setJobSyncStatus($crawlJobId, 'Done');
        } else {
            $this->setJobSyncStatus($crawlJobId, 'Error');
        }
        echo "Sync job data end @ " . date("Y-m-d H:i:s") . "\r\n";
    }

	/********************************************** end **********************************************/


	/**************************************** transaction part ***************************************/

	function GetAllTransaction($siteId, $crawlJobId, $start_dt, $end_dt, $batchId = false)
	{
		$_obj = $this->getInstance($siteId);

		echo "\nJob start ...\n";
		echo "DepartmentName: " . $_obj->info['DepartmentName'] . "\n";
		echo "NetworkName: " . $_obj->info['NetworkName'] . "\n";
		echo "AccountSiteName: " . $_obj->info['AccountSiteName'] . "\n";

		//Create a new crawl batch record
		if (!$batchId) {
			$batchId = $this->startNewCrawlBatch($_obj->info, 'Transaction', $crawlJobId);
			echo "Start a new batch id= " . $batchId . "\n\n";
		}

		preg_match('/^[\d]{4}-[\d]{2}-[\d]{2}$/', $start_dt, $start_time);
		preg_match('/^[\d]{4}-[\d]{2}-[\d]{2}$/', $end_dt, $end_time);
		$start_time = isset($start_time[0]) ? $start_time[0] : '';
		$end_time = isset($end_time[0]) ? $end_time[0] : '';
		$tmpStartTime = strtotime($start_time);
		$tmpEndTime = strtotime($end_time);

		if (!$start_time || !$end_time){
			$end_date   = date('Y-m-d', strtotime('+1 day'));
			$start_date = date('Y-m-d', strtotime('-90 days'));
		}elseif ($tmpStartTime > $tmpEndTime){
			mydie("Please input correct date! (start_date : $start_time > end_date : $end_time).");
		}else{
			$end_date   = $end_time;
			$start_date = $start_time;
		}

		$_obj->info['batchID'] = $batchId;
		$_obj->info['crawlJobId'] = $crawlJobId;
		$_obj->info['startDate'] = $start_date;
		$_obj->info['endDate'] = $end_date;

		//crawl Transaction.
		$_obj->getTransactionFromAff($start_date, $end_date);
	}

	function CheckJobTransaction($crawlJobId)
	{
		if (!$crawlJobId){
			mydie("Params are wrong when check job data.\r\n");
		}

		$job_check_status = $this->getJobCheckStatus($crawlJobId);
		if (!$job_check_status) {
			mydie("This crawlJobId( $crawlJobId ) crawl don't exists!");
		}
		if ($job_check_status != 'Uncheck') {
			return false;
		}

		$job_crawl_status = $this->getJobCrawlStatus($crawlJobId);
		if ($job_crawl_status != 'Done') {
			die("This job ( $crawlJobId ) crawl haven't done.");
		}

		echo "Check job (crawlJobId=$crawlJobId) data start @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->setJobCheckStatus($crawlJobId, 'Checking');

		$siteArr = $this->getBatchInfoByCrawlJobId($crawlJobId);

		$check_success = true;
		if (!empty($siteArr)) {
			foreach ($siteArr as $val) {
				$batch_status = $this->checkTransactionBatchData($val['NetworkID'], $val['BatchID'], $val['AccountSiteID']);
				if (!$batch_status) {
					$check_success = false;
					break;
				}
			}
		}
		echo "Check job data end @ " . date("Y-m-d H:i:s") . "\r\n";

		if (!$check_success) {
			$this->setJobCheckStatus($crawlJobId, 'Error');
			mydie("Batch transaction data have something wrong!");
		} else {
			$this->setJobCheckStatus($crawlJobId, 'Done');
		}
	}

	function checkTransactionBatchData($networkId, $batchId, $siteId)
	{
		if(!count($networkId) || !count($batchId) || !count($siteId)) {
			mydie("Params are wrong when check batch data.\r\n");
		}

		$batch_check_status = $this->getBatchCheckStatus($batchId);
		if (!$batch_check_status) {
			mydie("This batch( $batchId ) crawl don't exists!");
		}
        if ($batch_check_status == 'Done'){
            return true;
        }
		if ($batch_check_status != 'Uncheck'){
			return false;
		}

		$batch_crawl_status = $this->getBatchCrawlStatus($batchId);
		if ($batch_crawl_status != 'Done') {
			die("This batch( $batchId ) crawl haven't done.");
		}

		echo "Check site(siteId=$siteId and batchId=$batchId) data start @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->setBatchCheckStatus($batchId, 'Checking');

		$TransactionObj = new TransactionDb();
		$field_change_num = array();

		$check_result = $TransactionObj->checkTransactionBatchData($networkId, $batchId, $siteId);
		$allTransactionNum = $check_result['total_num'];

		if ($allTransactionNum > 0) {
			foreach ($check_result['change_detail'] as $key => $val) {
				foreach ($val as $fv) {
					if (!isset($field_change_num[$fv])) {
						$field_change_num[$fv] = array();
					}
					$field_change_num[$fv][] = $key;
				}
			}

			echo "\tThe change fields and num detail like that:\n";
			foreach ($field_change_num as $key => $val) {
				echo "\t\t$key => " . count($val) . "\n";
			}
			$transactionChangeNum = count($check_result['change_detail']);
			echo "\tThis batch has ($allTransactionNum) transactions, and ($transactionChangeNum) transactions have changed!\r\n";

			if ($transactionChangeNum / $allTransactionNum > 0.2) {
				$this->setBatchCheckStatus($batchId, 'Error');
				mydie("\n\tThis batch(batchid=$batchId) data have a big difference with old usable data!\r\n");
			} else {

			    //Check this batch lost sid number.
			    $lostTidArr = $this->getTransactionSidLost($networkId, $batchId, $siteId);
			    $saveArr = array(
			        'withoutSidTransactionIds'=> $lostTidArr
                );
			    $saveStr = json_encode($saveArr);
			    $crawlJobId = $this->getCrawlJobIdByBatchId($batchId);
			    $this->setSidLostRecords($crawlJobId, $saveStr);

                $lostNum = count($lostTidArr);
                if ($lostNum / $allTransactionNum > 0.05 || $lostNum > 100) {
                    $this->setBatchCheckStatus($batchId, 'Error');
                    mydie("\n\tThis batch(batchid=$batchId) transaction data have $lostNum records without sid, lost too many sid!\r\n");
                }
			}

            $this->setBatchCheckStatus($batchId, 'Done');
            echo "Check batch data end @ " . date("Y-m-d H:i:s") . "\r\n";
            return true;

		} else {
			$this->setBatchCheckStatus($batchId, 'Error');
			mydie("\tThis batch has (0) transactions\r\n");
			echo "Check batch data end @ " . date("Y-m-d H:i:s") . "\r\n";
			return false;
		}

	}

	function SyncJobTransaction($crawlJobId)
	{
		if(!count($crawlJobId)) {
			mydie("Params are wrong when sync job data.\r\n");
		}

		$job_sync_status = $this->getJobSyncStatus($crawlJobId);
		if (!$job_sync_status) {
			mydie("This job ( $crawlJobId ) crawl don't exists!");
		}
		if ($job_sync_status != 'Unsync') {
			return false;
		}

		$job_check_status = $this->getJobCheckStatus($crawlJobId);
		if ($job_check_status == 'Uncheck' || $job_check_status == 'Checking'){
			die("This job ( $crawlJobId ) data haven't been checked!");
		}
		if ($job_check_status != 'Done') {
//            $this->setJobSyncStatus($crawlJobId, 'Error');
			die("This job ( $crawlJobId ) data are useless.");
		}

		echo "Sync job data start @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->setJobSyncStatus($crawlJobId, 'Syncing');

		$siteArr = $this->getBatchInfoByCrawlJobId($crawlJobId);

		$sync_success = true;
		if (!empty($siteArr)) {
			foreach ($siteArr as $val) {
				$batch_status = $this->syncTransactionBatchData($val['NetworkID'], $val['BatchID'], $val['AccountSiteID']);
				if (!$batch_status) {
					$sync_success = false;
					break;
				}
			}
		}
		if (!$sync_success) {
			$this->setJobSyncStatus($crawlJobId, 'Error');
		} else {
			$this->setJobSyncStatus($crawlJobId, 'Done');
		}

	}

	function syncTransactionBatchData($networkId, $batchId, $siteId)
	{
		if(!count($networkId) || !count($batchId) || !count($siteId)) {
			mydie("Params are wrong when sync batch data.\r\n");
		}

		$batch_sync_status = $this->getBatchSyncStatus($batchId);
		if (!$batch_sync_status) {
			mydie("This batch( $batchId ) crawl don't exists!");
		}
		if ($batch_sync_status != 'Unsync') {
			return false;
		}

		$batch_check_status = $this->getBatchCheckStatus($batchId);
		if ($batch_check_status == 'Uncheck' || $batch_check_status == 'Checking'){
			die("This batch( $batchId ) data haven't been checked!");
		}
		if ($batch_check_status != 'Done') {
//            $this->setBatchSyncStatus($batchId, 'Error');
			die("This batch( $batchId ) data are useless.");
		}

		echo "Sync batch data start @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->setBatchSyncStatus($batchId, 'Syncing');

		$TransactionObj = new TransactionDb();
		$check_result = $TransactionObj->syncTransactionBatchData($networkId, $batchId, $siteId);
		echo "Sync batch data end @ " . date("Y-m-d H:i:s") . "\r\n";

		if ($check_result) {
			$this->setBatchSyncStatus($batchId, 'Done');
			return true;
		} else {
			$this->setBatchSyncStatus($batchId, 'Error');
			return false;
		}

	}

	/********************************************** end **********************************************/

    function getProgramDbObj()
    {
        if ($this->ProgramDbObj instanceof ProgramDb) {
            return $this->ProgramDbObj;
        }
        $this->ProgramDbObj = new ProgramDb();

        return $this->ProgramDbObj;
    }


}//end class

