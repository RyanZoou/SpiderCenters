<?php
class HttpCrawler
{
	function __construct()
	{
		$this->cookie_fields = array("domain","access","path","secure","expire","name","value");
	}

	function SetCookie($cookiefile,$cookieinfo)
	{
		if(!isset($cookieinfo["name"])) return false;
		$name = $cookieinfo["name"];
		$all_cookie = $this->ReadCookie($cookiefile);
		$all_cookie[$name] = $cookieinfo;
		return $this->SaveCookieFile($cookiefile,$all_cookie);
	}
	
	function GetCookieLineFromArr($_arr)
	{
		//resort and remove fields
		$arr = array();
		if(!isset($_arr["access"])) $_arr["access"] = "TRUE";
		if(!isset($_arr["secure"])) $_arr["secure"] = "FALSE";
		if(!isset($_arr["expire"])) $_arr["expire"] = time() + 24 * 3600 * 30;
		if(!isset($_arr["path"])) $_arr["path"] = "/";
		foreach($this->cookie_fields as $k => $v)
		{
			$arr[$v] = $_arr[$v];
		}
		return implode("\t",$arr);
	}
	
	function SaveCookieFile($cookiefile,$all_cookie)
	{
		$content = "";
		foreach($all_cookie as $name => $arr)
		{
			$line = $this->GetCookieLineFromArr($arr) . "\n";
			$content .= $line;
		}
		return file_put_contents($cookiefile,$content);
	}
	
	function RemoveCookie($cookiefile,$cookiename)
	{
		$all_cookie = $this->ReadCookie($cookiefile);
		if(isset($all_cookie[$cookiename]))
		{
			unset($all_cookie[$cookiename]);
			return $this->SaveCookieFile($cookiefile,$all_cookie);
		}
	}
	
	function ReadCookie($cookiefile,$cookiename="")
	{
		$result = array();
		if(!file_exists($cookiefile)) return $result;
		$lines = file($cookiefile);
		foreach($lines as $line)
		{
			$line = trim($line);
			if($line == "" || substr($line,0,1) == "#") continue;
			list($domain,$access,$path,$secure,$expire,$name,$value) = $arr_temp = explode("\t",$line);
			if(!$name) continue;
			
			foreach($this->cookie_fields as $k => $v)
			{
				$result[$name][$v] = $arr_temp[$k];
			}
		}
		
		if($cookiename)
		{
			if(isset($result[$cookiename])) return $result[$cookiename];
			else return array();
		}
		
		return $result;
	}
	
	function GetHttpResult($_url,$_para=array(),$ch="")
	{	
		/*if(defined("DEBUG_MODE") && DEBUG_MODE)//defined()检查常量是否存在
		{
			if(isset($_para["method"]) && $_para["method"] == "post") echo "posting: $_url <br>\n";
			else echo "getting: $_url <br>\n";
		}*/ 
		$cookiejar = "";
		
		if(!$ch) $ch = curl_init();//curl_init初始化一个curl会话，返回curl句柄
		
		curl_setopt($ch, CURLOPT_URL,$_url);//设置要获取的url
		
		if(isset($_para["header"]) && is_numeric($_para["header"]))
		{
			curl_setopt($ch, CURLOPT_HEADER, true);
		}
		else
		{
			curl_setopt($ch, CURLOPT_HEADER, false);//启用时会将头文件的信息作为数据流输出,关闭则不输出头信息
		}
		
		if(isset($_para["nobody"]) && is_numeric($_para["nobody"]))
		{
			curl_setopt($ch, CURLOPT_NOBODY, true);
		}
		else
		{
			curl_setopt($ch, CURLOPT_NOBODY, false);//启用时将不对HTML中的BODY部分进行输出。
		}				
		
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);//在发起连接前等待的时间，如果设置为0，则无限等待。
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//将curl_exec()获取的结果以文件流的形式返回，而不是直接输出。
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);//启用时会将服务器服务器返回的"Location: "放在header中递归的返回给服务器，使用CURLOPT_MAXREDIRS可以限定递归返回的数量。
													   //头信息中location的含义：表示客户应当到哪里去提取文档，用于将接收端定位到资源的位置（URL）上
		if(isset($_para["timeout"]) && is_numeric($_para["timeout"]))
		{
			curl_setopt($ch, CURLOPT_TIMEOUT, $_para["timeout"]);
		}
		else
		{
			curl_setopt($ch, CURLOPT_TIMEOUT, 600);//by ike 20111122              //设置cURL允许执行的最长秒数。
		}
		
		if(isset($_para["maxredirs"]) && is_numeric($_para["maxredirs"]))
		{
			curl_setopt($ch, CURLOPT_MAXREDIRS, $_para["maxredirs"]);
		}
		else
		{
			curl_setopt($ch, CURLOPT_MAXREDIRS, 10);//指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的。键line 132
		}
		
		if(isset($_para["port"]) && is_numeric($_para["port"]))
		{
			curl_setopt($ch, CURLOPT_PORT, $_para["port"]);
		}
		
		
		if(isset($_para["autoreferer"]) && $_para["autoreferer"] == false)
		{
			curl_setopt($ch, CURLOPT_AUTOREFERER, false);
		}
		elseif(isset($_para["referer"]) && $_para["referer"])
		{
			curl_setopt($ch, CURLOPT_AUTOREFERER, false);
		}
		else
		{
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);//当根据Location:重定向时，自动设置header中的Referer:信息。
		}
		
		if(isset($_para["headerfunction"]) && $_para["headerfunction"])
		{
			curl_setopt($ch, CURLOPT_HEADERFUNCTION, $_para["headerfunction"]);
		}
		
		if(isset($_para["readfunction"]) && $_para["readfunction"])
		{
			curl_setopt($ch, CURLOPT_READFUNCTION, $_para["readfunction"]);
		}
		
		if(isset($_para["writefunction"]) && $_para["writefunction"])
		{
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, $_para["writefunction"]);
		}
		
		if(isset($_para["referer"])) curl_setopt($ch, CURLOPT_REFERER, $_para["referer"]);
		if(isset($_para["verbose"])) curl_setopt($ch, CURLOPT_VERBOSE, true);
		
		if(!$cookiejar && isset($_para["cookiejar"]))
		{
			$cookiejar = $_para["cookiejar"];//$cookiejar就是aff_10.cookie这个文件的路径

		}
		//if(!$cookiejar && isset($_para["ID"])) $cookiejar = $this->getCookieJarByAffId($_para["ID"]);
		
		if($cookiejar && isset($_para["addcookie"]) && is_array($_para["addcookie"]))
		{
			foreach($_para["addcookie"] as $cookiename => $cookieinfo)
			{
				$this->SetCookie($cookiejar,$cookieinfo);
			}
		}
		
		if($cookiejar && isset($_para["remvoecookie"]) && is_array($_para["remvoecookie"]))
		{
			foreach($_para["remvoecookie"] as $cookiename)
			{
				$this->RemoveCookie($cookiejar,$cookiename);
			}
		}
		
		if($cookiejar)
		{
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);//把返回来的cookie信息保存在$cookie_jar文件中
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);// file name to read cookies from,在访问其他页面时拿着这个cookie文件去访问
		}
		
		if(isset($_para["cookie"]))
		{
			curl_setopt($ch,CURLOPT_COOKIE,$_para["cookie"]);//设定HTTP请求中"Cookie: "部分的内容。多个cookie用分号分隔，分号后带一个空格(例如， "fruit=apple; colour=red")。
		}

		if(isset($_para["addheader"]) && is_array($_para["addheader"]))
		{
			//like:array('Content-type: text/plain', 'Content-length: 100')
			curl_setopt($ch,CURLOPT_HTTPHEADER,$_para["addheader"]); 
		}
		
		if(isset($_para["stderr_temp"])){
			$verbose = fopen(INCLUDE_ROOT."temp.txt", 'w+');
			curl_setopt($ch, CURLOPT_STDERR, $verbose);
		}
		
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36");
		//在HTTP请求中包含一个"User-Agent: "头的字符串。
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//禁用后cURL将终止从服务端进行验证。
		//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		if(isset($_para["no_ssl_verifyhost"]))
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);// 如果CURLOPT_SSL_VERIFYPEER(默认值为2)被启用，CURLOPT_SSL_VERIFYHOST需要被设置成TRUE否则设置为FALSE。
		}else{
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		}

        if (isset($_para['userpwd']) && $_para['userpwd'])
        {
            curl_setopt($ch, CURLOPT_USERPWD, $_para['userpass']);
        }
        if (isset($_para['file']) && $_para['file'])    //$_para['file'] =>$fw  ( $fw = fopen($file_path, 'w'); 文件句柄！)
        {
            curl_setopt($ch, CURLOPT_FILE, $_para['file']);
        }

		if(!isset($_para["no_encoding"]))
		{
			curl_setopt($ch, CURLOPT_ENCODING, "gzip, deflate");
		}

		if(isset($_para["method"]) && $_para["method"] == "post")
		{
			curl_setopt($ch, CURLOPT_POST, true);
			
			if(isset($_para["postdata"]))
			{
				//$postdata = $_para["postdata"];
				//if(is_array($postdata)) $postdata = http_build_query($postdata));
				curl_setopt($ch, CURLOPT_POSTFIELDS,$_para["postdata"]);           //这一步完成登录
			}
	
			//if(defined("DEBUG_MODE") && DEBUG_MODE && $_para["postdata"]) echo "postdata: ". print_r($_para["postdata"],true)." <br>\n";
		}
		
		/*
		 * One of CURL_SSLVERSION_DEFAULT (0), 
		 * CURL_SSLVERSION_TLSv1 (1), 
		 * CURL_SSLVERSION_SSLv2 (2), 
		 * CURL_SSLVERSION_SSLv3 (3), 
		 * CURL_SSLVERSION_TLSv1_0 (4), 
		 * CURL_SSLVERSION_TLSv1_1 (5) or 
		 * CURL_SSLVERSION_TLSv1_2 (6).
		 */
		if(isset($_para["SSLV"]) && is_numeric($_para["SSLV"]))
		{
			curl_setopt($ch, CURLOPT_SSLVERSION, $_para["SSLV"]);
		}
		
		$pagecontent = curl_exec($ch);//执行给定的cURL会话。这个函数应该在初始化一个cURL会话并且全部的选项都被设置后被调用。
		$error_msg = curl_error($ch);//返回一条最近一次cURL操作明确的文本的错误信息。		
		$curl_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);//获取最后一次传输的相关信息。CURLINFO_HTTP_CODE表示获取的信息是最后一个收到的HTTP代码

        //获取最后一次跳转链接
        if(isset($_para['FinalUrl']) && $_para['FinalUrl']) {
            $pagecontent = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        }
        
        $FinalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        curl_close($ch);
		if(defined("DEBUG_MODE") && DEBUG_MODE && $curl_code != 200){//状态码为200，说明连接成功
			echo "warning: curl_code = $curl_code <br>\n";
			echo "Curl error: $error_msg\n";
		}
		
		if(isset($_para["stderr_temp"])){
			rewind($verbose);
			$verboseLog = stream_get_contents($verbose);
			//echo "\r\nVerbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
			fclose($verbose);
		}
		
		return array(
			"code" => $curl_code,
			"content" => $pagecontent,
			"error_msg" => $error_msg,
			"verbose" => isset($verboseLog) ? $verboseLog : "",
			"final_url" => $FinalUrl,
		);
	}

}//end class
?>