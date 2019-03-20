<?php
require_once 'text_parse_helper.php';
class LinkFeed_6_PepperjamNetwork
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
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

	function login($try = 3){
    	$logouturl = "https://ascend.pepperjam.com/authentication/logout";
		$request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "header" => '1');
		$result = $this->oLinkFeed->GetHttpResult($logouturl,$request);
		$localtion = $this->oLinkFeed->ParseStringBy2Tag($result['content'],"Location: ","email+openid");
		$localtion .= 'email+openid';
		$result = $this->oLinkFeed->GetHttpResult($localtion,$request);
		$localtion = $this->oLinkFeed->ParseStringBy2Tag($result['content'],"Location: ","email+openid");
		$localtion .= 'email+openid';
		$result = $this->oLinkFeed->GetHttpResult($localtion,$request);
		$csrf = $this->oLinkFeed->ParseStringBy2Tag($result['content'],array('name="_csrf"','value="'),'"');

		$request['method'] = "post";
		$request['postdata'] = "_csrf={$csrf}&username=".urlencode($this->info['UserName'])."&password=".urlencode($this->info['Password'])."&cognitoAsfData=eyJwYXlsb2FkIjoie1wiY29udGV4dERhdGFcIjp7XCJVc2VyQWdlbnRcIjpcIk1vemlsbGEvNS4wIChXaW5kb3dzIE5UIDEwLjA7IFdPVzY0KSBBcHBsZVdlYktpdC81MzcuMzYgKEtIVE1MLCBsaWtlIEdlY2tvKSBDaHJvbWUvNjMuMC4zMjM5LjEzMiBTYWZhcmkvNTM3LjM2XCIsXCJEZXZpY2VJZFwiOlwiZ3N6Y2xseHJiYjhmcHY2Yjd4eWw6MTU1MDgxNzE0OTIxOFwiLFwiRGV2aWNlTGFuZ3VhZ2VcIjpcInpoLUNOXCIsXCJEZXZpY2VGaW5nZXJwcmludFwiOlwiTW96aWxsYS81LjAgKFdpbmRvd3MgTlQgMTAuMDsgV09XNjQpIEFwcGxlV2ViS2l0LzUzNy4zNiAoS0hUTUwsIGxpa2UgR2Vja28pIENocm9tZS82My4wLjMyMzkuMTMyIFNhZmFyaS81MzcuMzZBbGlTU09Mb2dpbiBwbHVnaW46QWxpV2FuZ1dhbmcgUGx1Zy1JbiBGb3IgRmlyZWZveCBhbmQgTmV0c2NhcGU6QWxpcGF5IFNlY3VyaXR5IENvbnRyb2wgMzpBbGlwYXkgc2VjdXJpdHkgY29udHJvbDpCYWlkdVl1bkd1YW5qaWEgQXBwbGljYXRpb246Q2hyb21lIFJlbW90ZSBEZXNrdG9wIFZpZXdlcjpDaHJvbWl1bSBQREYgUGx1Z2luOkNocm9taXVtIFBERiBWaWV3ZXI6Rm94aXQgUmVhZGVyIFBsdWdpbiBmb3IgTW96aWxsYTpHb29nbGUgVXBkYXRlOk1pY3Jvc29mdMKuIFdpbmRvd3MgTWVkaWEgUGxheWVyIEZpcmVmb3ggUGx1Z2luOlNob2Nrd2F2ZSBGbGFzaDpTaG9ja3dhdmUgRmxhc2g6U2hvY2t3YXZlIEZsYXNoOlRlbmNlbnQgUVE6VGVuY2VudCBTU08gUGxhdGZvcm06WHVuTGVpIFBsdWdpbjppVHJ1c0NoaW5hIGlUcnVzUFRBLFhFbnJvbGwsaUVucm9sbCxod1BUQSxVS2V5SW5zdGFsbHMgRmlyZWZveCBQbHVnaW46bnBhbGljZG8gcGx1Z2luOnpoLUNOXCIsXCJEZXZpY2VQbGF0Zm9ybVwiOlwiV2luMzJcIixcIkNsaWVudFRpbWV6b25lXCI6XCIwODowMFwifSxcInVzZXJuYW1lXCI6XCJwai5wYXJ0bmVyc0BicmFuZHJld2FyZC5jb21cIixcInVzZXJQb29sSWRcIjpcIlwiLFwidGltZXN0YW1wXCI6XCIxNTUwODE3MTQ5MjE4XCJ9Iiwic2lnbmF0dXJlIjoiVVZVMXY5QTRHbS9vNTNhUjRDUGNrMGdESEVGR0tBdERNdFpEMnUxbFp2Zz0iLCJ2ZXJzaW9uIjoiSlMyMDE3MTExNSJ9&signInSubmitButton=Sign+in";
		$login_result = $this->oLinkFeed->GetHttpResult($localtion,$request);
//		var_dump($login_result);
		if ($login_result['code'] == 200 && strpos($login_result['content'],"BRAND REWARD") !== false){
			echo "login succ : BRAND REWARD \n";
			return true;
		}else{
			if ($try > 0){
				$try--;
				$this->login($try);
			}else{
				var_dump($login_result);
				mydie("login failed");
			}
		}
	}

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = $arr_prgm_name = $programInfo = array();
        $program_num = $base_program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        $this->login();

        echo "\tGet SupportDeepurl" . PHP_EOL;
        $hasSupportDeepurl = false;
        $SupportDeepurl_arr = $this->getSupportDUT();
        if(count($SupportDeepurl_arr) > 100) {
            $hasSupportDeepurl = true;
        }

        if ($this->isFull) {
            $programInfo = $this->GetProgramInfo();
        }

        $page = 1;
        $hasNextPage = true;
        while($hasNextPage) {
            $apiurl = sprintf("http://api.pepperjamnetwork.com/20120402/publisher/advertiser?apiKey=%s&format=json&page=%s", $this->info['APIKey1'], $page);
            $cacheName = "api_advertiser_{$page}_" . date('YmdH') . '.cache';
            $result = $this->oLinkFeed->GetHttpResultAndCache($apiurl, $request, $cacheName, 'status');
            $result = json_decode($result);
            if(isset($result->meta->status->code) && $result->meta->status->code==429) {
                mydie($result->meta->status->message);
            }
            $total_pages = $result->meta->pagination->total_pages;
            if($page >= $total_pages) {
                $hasNextPage = false;
            }
            $page++;

            $advertiser_list = $result->data;
            foreach($advertiser_list as $advertiser) {
                $strMerID = $advertiser->id;
                $StatusInAffRemark = $advertiser->status;
                if ($StatusInAffRemark == "joined") {
                    $Partnership = "Active";
                } elseif ($StatusInAffRemark == "revoked_advertiser") {
                    $Partnership = "Expired";
                } elseif ($StatusInAffRemark == "applied") {
                    $Partnership = "Pending";
                } elseif ($StatusInAffRemark == "declined_advertiser") {
                    $Partnership = "Declined";
                } elseif ($StatusInAffRemark == "invited") {
                    $Partnership = "Pending";
                } elseif ($StatusInAffRemark == "revoked_publisher") {
                    $Partnership = "Removed";
                } elseif ($StatusInAffRemark == "declined_publisher") {
                    $Partnership = "Removed";
                } else {
                    $Partnership = "NoPartnership";
                }
                $AffDefaultUrl = '';
                if($hasSupportDeepurl && isset($SupportDeepurl_arr[$strMerID])) {
                    $AffDefaultUrl = $SupportDeepurl_arr[$strMerID]['AffDefaultUrl'];
                }

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $strMerID,
                    'Partnership' => $Partnership,
                    "AffDefaultUrl" => $AffDefaultUrl
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    $strMerName = $advertiser->name;
                    $desc = $advertiser->description;
                    if (preg_match('/(Terms & Conditions|terms and conditions)(.*?)$/is', $desc, $matches)) {
                        $TermAndCondition = $matches[0];
                    } else {
                        $TermAndCondition = '';
                    }
                    $desc = trim(strip_tags($desc));

                    //program_detail
                    $prgm_url = "http://www.pepperjamnetwork.com/affiliate/program/details?programId=$strMerID";
                    $program_detail_cache = "detail_{$strMerID}_" . date('YmdH') . '.cache';
                    $prgm_detail = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request, $program_detail_cache);
                    $TargetCountryExt = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('<ul id="program-popup-countries">', '<li>'), '</ul>');
                    $TargetCountryExt = trim(strip_tags(str_replace('</li><li>', ',', $TargetCountryExt)));
                    $CategoryExt = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<strong>Categories:</strong>', '</div>'));
                    $CategoryExt = str_replace(",", EX_CATEGORY, $CategoryExt);
                    $Contacts = "Manager: ".trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<strong>Manager:</strong>', '</div>')));
                    $Contacts .= ", Email: ".@$programInfo[$strMerID][1];
                    $Contacts .= ", Phone: ".@$programInfo[$strMerID][2];
                    $Contacts .= ", Address: ".trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('<strong>Address:</strong>', '<div>'), '</div>')));
                    $Contacts = @mb_convert_encoding($Contacts, "UTF-8", mb_detect_encoding($Contacts));

                    $SEMPolicyExt = "Suggested Keywords:".trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<h3>Suggested Keywords:</h3>', '<h3>')));
                    $SEMPolicyExt .= ", \nRestricted Keywords:".trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<h3>Restricted Keywords:</h3>', '</div>')));
                    $CommissionExt = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, 'Default Terms', '* Incentives for a monthly period')));
                    if(empty($CommissionExt)) {
                        $CommissionExt = @$programInfo[$strMerID][5];
                    }
                    $SupportDeepurl = @$programInfo[$strMerID][0];
                    if($hasSupportDeepurl && isset($SupportDeepurl_arr[$strMerID])) {
                        $SupportDeepurl = $SupportDeepurl_arr[$strMerID]['SupportDeepurl'];
                    }
                    $SupportType = "";
                    if (!is_string($programInfo[$strMerID][8])){
                    	echo $strMerID.PHP_EOL;
                    	var_dump($programInfo[$strMerID][8]);
                    }
                    if (strpos($programInfo[$strMerID][8], 'Content') !== false){
                    	$SupportType .= 'Content'.EX_CATEGORY;
                    }
                    if (strpos($programInfo[$strMerID][8], 'Coupon') !== false){
                    	$SupportType .= "Coupon".EX_CATEGORY;
                    }
                    $SupportType = rtrim($SupportType, EX_CATEGORY);
                    

                    $arr_prgm[$strMerID] += array(
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Name" => addslashes(html_entity_decode(trim($strMerName))),
                        "StatusInAff" => "Active",
                        "StatusInAffRemark" => addslashes($StatusInAffRemark),
                        "Description" => addslashes($desc),
                        "TermAndCondition" => addslashes($TermAndCondition),
                        "MobileFriendly" => 'UNKNOWN',
                        "LogoUrl" => addslashes($advertiser->logo),
                        "TargetCountryExt" => addslashes($TargetCountryExt),
                        "CategoryExt" => addslashes($CategoryExt),
                        "Contacts" => addslashes($Contacts),
                        "IdInAff" => $strMerID,
                        "CreateDate" => @$programInfo[$strMerID][7],
                        "Homepage" => addslashes(@$programInfo[$strMerID][3]),
                        "CommissionExt" => addslashes($CommissionExt),
                        "CookieTime" => @$programInfo[$strMerID][4],
                        "SEMPolicyExt" => addslashes($SEMPolicyExt),
                        "SubAffPolicyExt" => addslashes(@$programInfo[$strMerID][6]),
                        "SupportDeepUrl" => $SupportDeepurl,
                    	"SupportType" => $SupportType
                    );
                    if ($advertiser->mobile_tracking == 'Enabled') {
                        $arr_prgm[$strMerID]['MobileFriendly'] = 'YES';
                    }
                    $base_program_num ++;
                }

                $program_num++;
                if (count($arr_prgm) >= 100) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }
            }
        }
        unset($programInfo);

        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

    function GetProgramInfo()
    {
        $programInfo = array();
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        //Get all merchants info
        $str_header = 'Program ID,Program Name,Deep Linking,Product Feed,Email,Phone,Promotional Methods,Prohibited States,Countries,Generic Link,Website URL,Logo,Locking Period,Cookie Duration,Commission,Join Date,Affidavit Required';
        $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"],"merchant_csv_".date("YmdH").".dat", "cache_merchant");
        if(!$this->oLinkFeed->fileCacheIsCached($cache_file))
        {
            $strUrl = "https://www.pepperjamnetwork.com/affiliate/program/manage?&csv=1";
            $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
            $result = $r["content"];
            print "Get Merchant CSV.\n";
            if(stripos($result,$str_header) === false){
                mydie("die: wrong csv file: $cache_file");
            }
            $this->oLinkFeed->fileCachePut($cache_file,$result);
        }

        $fhandle = fopen($cache_file, 'r');
        if(!$fhandle) {
            mydie("open $cache_file failed.\n");
        }

        while ($line = fgetcsv ($fhandle, 50000, ',')) {
            //Program ID,Program Name,Deep Linking,Product Feed,Email,Phone,Allowed Promotional Methods,Prohibited States,Generic Link,Website URL,Logo,Locking Period,Cookie Duration,Commission,Join Date,Affidavit Required
            $strMerID = intval($line[0]);
            if ($strMerID < 1) {
                continue;
            }

            $SupportDeepurl = strtoupper(trim($line[2]));
            $tmp_email = $line[4];
            $tmp_phone = $line[5];
            $SupportType = $line[6];
            $tmp_prohibited_states = $line[7];
            $Homepage = $line[10];
            $ReturnDays = $line[13];
            $CommissionExt_bk = $line[14];
            $JoinDate = $line[15];
            $SubAffPolicyExt = "";
            if($tmp_prohibited_states) {
                $SubAffPolicyExt = "Prohibited States: " . $tmp_prohibited_states;
            }
            if($JoinDate) {
                $JoinDate = date("Y-m-d H:i:s", strtotime($JoinDate));
            }
            $programInfo[$strMerID] = array($SupportDeepurl, $tmp_email, $tmp_phone, $Homepage, $ReturnDays, $CommissionExt_bk, $SubAffPolicyExt, $JoinDate, $SupportType);
        }
        return $programInfo;
    }

    function getSupportDUT()
    {
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);
        $str_url = "http://www.pepperjamnetwork.com/affiliate/creative/generic?website=&sid=&deep_link=&encrypted=0&rows_per_page=2000";
        $tmp_arr = $this->oLinkFeed->GetHttpResult($str_url, $request);
        $result = $tmp_arr["content"];
        $SupportDeepurl_arr = array();

        //parse HTML
        $strLineStart = '<td class="creative">';
        $nLineStart = 0;
        while ($nLineStart >= 0){
            $nLineStart = stripos($result, $strLineStart, $nLineStart);
            if ($nLineStart === false) break;
            $AffDefaultUrl = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($result, array('tracking-link', 'value="'), '"', $nLineStart)));
            $SupportDeepurl = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($result, array('Deep linking', '<span>'), '</span>', $nLineStart)));
            $strMerID = $this->oLinkFeed->ParseStringBy2Tag($result, 'data-id="', '"', $nLineStart);
            $strMerID = intval($strMerID);
            if($SupportDeepurl == "allowed"){
                $SupportDeepurl_arr[$strMerID]["SupportDeepurl"] = "YES";
            }else{
                $SupportDeepurl_arr[$strMerID]["SupportDeepurl"] = "NO";
            }
            $SupportDeepurl_arr[$strMerID]["AffDefaultUrl"] = $AffDefaultUrl;
        }

        return $SupportDeepurl_arr;
    }

	function getTransactionFromAff($start_date, $end_date)
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

		$objTransaction = New TransactionDb();
		$begin_dt = $start_date;
		$end_dt = date('Y-m-d', strtotime($end_date) - 86400);
		$arr_transaction = $arr_find_Repeated_transactionId = array();
		$tras_num = 0;

		$url = 'http://api.pepperjamnetwork.com/20120402/publisher/report/transaction-details?apiKey={TOKEN}&startDate={BEGIN_DATE}&endDate={END_DATE}&format=csv';
		$url = str_replace(array('{BEGIN_DATE}', '{END_DATE}', '{TOKEN}'), array($begin_dt, $end_dt, $this->info['APIKey1']), $url);
		$request = array(
			"AccountSiteID" => $this->info["AccountSiteID"],
			"method" => "get",
		);

		$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "data_" . date("YmdH") . "_Transaction_{$end_date}.csv", 'Transaction', true);
		if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
			echo "req => {$url} \n";

			$fw = fopen($cache_file, 'w');
			if (!$fw) {
				mydie("File open failed {$cache_file}");
			}
			$request['file'] = $fw;

			$result = $this->oLinkFeed->GetHttpResult($url, $request);
			if ($result['code'] != 200){
			    print_r($result);
				mydie("Download csv file failed.");
			}
			fclose($fw);
		}

		$fp = fopen($cache_file, 'r');
		if (!$fp) {
			mydie("File open failed {$cache_file}");
		}

		$k = 0;
		while (!feof($fp)) {
			if (++$k == 1) {
				$lr = trim(fgets($fp));
				if ($lr != "transaction_id,order_id,sid,creative_type,commission,sale_amount,type,date,status,program_name,program_id,sub_type") {
					mydie("Report Format changed!");
				}
				continue;
			}

			$lr = fgetcsv($fp);
			if ($lr[0] == "No Results Found" || !$lr) {
				continue;
			}

			$TransactionId = trim($lr[0]);
			if (!$TransactionId) {
				continue;
			}

			if (isset($arr_find_Repeated_transactionId[$TransactionId])) {
				mydie("The transactionId={$TransactionId} have early exists!");
			} else {
				$arr_find_Repeated_transactionId[$TransactionId] = '';
			}

			$arr_transaction[$TransactionId] = array(
				'AccountSiteID' => $this->info["AccountSiteID"],
				'BatchID' => $this->info['batchID'],
				'TransactionId' => $TransactionId,                      //must be unique
				'transaction_id' => addslashes($lr[0]),
				'order_id' => addslashes($lr[1]),
				'sid' => addslashes($lr[2]),
				'creative_type' => addslashes($lr[3]),
				'commission' => addslashes($lr[4]),
				'sale_amount' => addslashes($lr[5]),
				'type' => addslashes($lr[6]),
				'date' => addslashes($lr[7]),
				'status' => addslashes($lr[8]),
				'program_name' => addslashes($lr[9]),
				'program_id' => addslashes($lr[10]),
				'sub_type' => addslashes($lr[11])
			);
			$tras_num ++;

			if (count($arr_transaction) >= 100) {
				$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
				$arr_transaction = array();
			}
		}
		fclose($fp);

		if (count($arr_transaction) > 0) {
			$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
			unset($arr_transaction);
		}
		unset($arr_find_Repeated_transactionId);

		echo "\tUpdate ({$tras_num}) Transactions.\r\n";
		echo "Craw Transaction end @ " . date("Y-m-d H:i:s") . "\r\n";

		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
	}

}
