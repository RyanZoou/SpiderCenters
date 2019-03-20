<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_7_ShareASale
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

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = $arr_prgm_name = array();
        $program_num = $base_program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        $myTimeStamp = gmdate(DATE_RFC1123);

        $APIVersion = 1.2;
        $actionVerb = "merchantStatus";
        $sig = $this->info['APIKey2'].':'.$myTimeStamp.':'.$actionVerb.':'.$this->info['APIKey3'];
        $sigHash = hash("sha256",$sig);
        $myHeaders = array("x-ShareASale-Date: $myTimeStamp","x-ShareASale-Authentication: $sigHash");
        $request['addheader'] = $myHeaders;

        $url = "https://shareasale.com/x.cfm?action=$actionVerb&affiliateId=".$this->info['APIKey1']."&token=".$this->info['APIKey2']."&version=$APIVersion";
        $result = $this->oLinkFeed->GetHttpResultAndCache($url, $request, "programs_list_" . date('YmdH') . '.cache', '|Merchant|');
//        print_r($result);exit;

        if (!$result || stripos($result,"Error Code ") !== false) {
            trigger_error($result,E_USER_ERROR);
            mydie("die: get info by Api failed.\n");
        }

        $arr_feed = explode("\n", $result);
        $line_number = 0;
        $line_one = "Merchant Id|Merchant|WWW|Program Status|Program Category|Sale Comm|Lead Comm|Hit Comm|Approved|Link Url";
        foreach($arr_feed as $line) {
            $line_number++;
            if ($line_number == 1) {
                if (trim($line) != $line_one) {
                    echo "$line", "\n";
                    mydie("die: wrong API format: at line $line\n");
                }
                continue;
            }

            $row = explode("|", $line);

            if (!count($row) || !isset($row[0])) {
                continue;
            }

            $strMerID = intval($row[0]);
            if (!$strMerID) {
                continue;
            }

            $Partnership = "NoPartnership";
            if (isset($row[8])) {
                if ($row[8] == "Yes") {
                    $Partnership = "Active";
                } elseif ($row[8] == "Pending") {
                    $Partnership = "Pending";
                } elseif ($row[8] == "Declined") {
                    $Partnership = "Declined";
                } else {
                    $Partnership = "NoPartnership";
                }
            }
            $AffDefaultUrl = isset($row[9]) ? trim($row[9]) : "";

            $arr_prgm[$strMerID] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $strMerID,
                'Partnership' => $Partnership,
                'AffDefaultUrl' => addslashes($AffDefaultUrl)
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                $name = isset($row[1]) ? trim($row[1]) : "";
                $StatusInAffRemark = "";
                $StatusInAff = "Offline";
                if (isset($row[3])) {
                    $StatusInAffRemark = $row[3];
                    if ($row[3] == "Closed") {
                        $StatusInAff = "Offline";
                    } elseif ($row[3] == "LowFunds") {
                        $StatusInAff = "Active";
                    } elseif ($row[3] == "Online") {
                        $StatusInAff = "Active";
                    } elseif ($row[3] == "TemporarilyOffline") {
                        $StatusInAff = "TempOffline";
                    } else {
                        $StatusInAff = "Offline";
                    }
                }

                $Homepage = isset($row[2]) ? trim($row[2]) : "";

                $CommissionExt = "Sale Comm:" . trim($row[5]) . "|";
                $CommissionExt .= "Lead Comm:" . trim($row[6]) . "|";
                $CommissionExt .= "Hit Comm:" . trim($row[7]);

                $CategoryExt = isset($row[4]) ? addslashes($row[4]) : "";
                $CategoryExt = str_replace('acc', 'Accessories', $CategoryExt);
                $CategoryExt = str_replace('art', 'Art/Music/Photography', $CategoryExt);
                $CategoryExt = str_replace('auction', 'Auction Services', $CategoryExt);
                $CategoryExt = str_replace('bus', 'Business', $CategoryExt);
                $CategoryExt = str_replace('car', 'Automotive', $CategoryExt);
                $CategoryExt = str_replace('clo', 'Clothing', $CategoryExt);
                $CategoryExt = str_replace('com', 'Commerce/Classifieds', $CategoryExt);
                $CategoryExt = str_replace('cpu', 'Computers/Electronics', $CategoryExt);
                $CategoryExt = str_replace('dating', 'Online Dating Services', $CategoryExt);
                $CategoryExt = str_replace('domain', 'Domain Names', $CategoryExt);
                $CategoryExt = str_replace('edu', 'Education', $CategoryExt);
                $CategoryExt = str_replace('fam', 'Family', $CategoryExt);
                $CategoryExt = str_replace('fin', 'Financial', $CategoryExt);
                $CategoryExt = str_replace('free', 'Freebies, Free Stuff, Rewards Programs', $CategoryExt);
                $CategoryExt = str_replace('fud', 'Food/Drink', $CategoryExt);
                $CategoryExt = str_replace('gif', 'Gifts', $CategoryExt);
                $CategoryExt = str_replace('gourmet', 'Gourmet', $CategoryExt);
                $CategoryExt = str_replace('green', 'Green', $CategoryExt);
                $CategoryExt = str_replace('hea', 'Health', $CategoryExt);
                $CategoryExt = str_replace('hom', 'Home & Garden', $CategoryExt);
                $CategoryExt = str_replace('hosting', 'Web Hosting', $CategoryExt);
                $CategoryExt = str_replace('ins', 'Insurance', $CategoryExt);
                $CategoryExt = str_replace('job', 'Career/Jobs/Employment', $CategoryExt);
                $CategoryExt = str_replace('legal', 'Legal', $CategoryExt);
                $CategoryExt = str_replace('lotto', 'Gaming and Lotto', $CategoryExt);
                $CategoryExt = str_replace('mal', 'Shopping Malls', $CategoryExt);
                $CategoryExt = str_replace('mar', 'Marketing', $CategoryExt);
                $CategoryExt = str_replace('med', 'Books/Media', $CategoryExt);
                $CategoryExt = str_replace('military', 'Military', $CategoryExt);
                $CategoryExt = str_replace('mov', 'Moving/Moving Supplies', $CategoryExt);
                $CategoryExt = str_replace('rec', 'Recreation', $CategoryExt);
                $CategoryExt = str_replace('res', 'Real Estate', $CategoryExt);
                $CategoryExt = str_replace('search', 'Search Engine Submission', $CategoryExt);
                $CategoryExt = str_replace('spf', 'Sports/Fitness', $CategoryExt);
                $CategoryExt = str_replace('toy', 'Games/Toys', $CategoryExt);
                $CategoryExt = str_replace('tvl', 'Travel', $CategoryExt);
                $CategoryExt = str_replace('web', 'General Web Services', $CategoryExt);
                $CategoryExt = str_replace('webmaster', 'Webmaster Tools', $CategoryExt);
                $CategoryExt = str_replace('weddings', 'Weddings', $CategoryExt);
                $CategoryExt = str_replace(',',EX_CATEGORY,$CategoryExt);


                $arr_prgm[$strMerID] += array(
                    "Name" => addslashes($name),
                    "Homepage" => addslashes($Homepage),
                    "CategoryExt" => $CategoryExt,
                    "CommissionExt" => addslashes($CommissionExt),
                    "StatusInAff" => $StatusInAff,                        //'Active','TempOffline','Offline'
                    "StatusInAffRemark" => addslashes($StatusInAffRemark),
                    'CrawlJobId' => $this->info['crawlJobId'],
                );
                $base_program_num ++;
            }

            $program_num++;
            if(count($arr_prgm) >= 100){
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }
        }

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

	function getTransactionFromAff($start_date, $end_date)
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

		$objTransaction = New TransactionDb();
		$begin_dt = $start_date;
		$end_dt = $end_date;
		$arr_transaction = $arr_find_Repeated_transactionId = array();
		$tras_num = 0;

		$timestamp = gmdate(DATE_RFC1123);
		$sig = $this->info['APIKey2'] . ':' . $timestamp . ':activity:' . $this->info['APIKey3'];
		$sig = hash("sha256", $sig);
		$headers = array("x-ShareASale-Date: {$timestamp}", "x-ShareASale-Authentication: {$sig}");

		$api_url = "https://shareasale.com/x.cfm?action=activity&affiliateId={$this->info['APIKey1']}&token={$this->info['APIKey2']}&dateStart={BEGIN_DATE}&dateEnd={END_DATE}&version=1.7 ";
		$request = array(
			"AccountSiteID" => $this->info["AccountSiteID"],
			"method" => "get",
			"addheader" => $headers,
		);

		$i = 0;
		while ($begin_dt < $end_dt) {
			$tmp_dt = date('Y-m-d', strtotime('+30 day', strtotime($begin_dt)));
			$tmp_dt = $tmp_dt > $end_dt ? $end_dt : $tmp_dt;
			$i ++;

			$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "data_" . date("YmdH") . "_Transaction_{$tmp_dt}.csv", 'Transaction', true);
			if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
				$url = str_replace(array('{BEGIN_DATE}', '{END_DATE}'), array(date('m/d/Y', strtotime($begin_dt)), date('m/d/Y', strtotime($tmp_dt))), $api_url);
				echo "req => {$url} \n";

				$fw = fopen($cache_file, 'w');
				if (!$fw) {
					mydie("File open failed {$cache_file}");
				}
				$request['file'] = $fw;

				$result = $this->oLinkFeed->GetHttpResult($url, $request);
				if ($result['code'] != 200){
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
				$lr = fgetcsv($fp, 0, '|', '"');

				if (++$k == 1) {
					continue;
				}
				if ($lr[0] == "" || count($lr) < 22)
					continue;
				if (empty($lr)){
					break;
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
					'TransID' => addslashes($lr[0]),
					'UserID' => addslashes($lr[1]),
					'MerchantID' => addslashes($lr[2]),
					'TransDate' => addslashes($lr[3]),
					'TransAmount' => addslashes($lr[4]),
					'Commission' => addslashes($lr[5]),
					'Comment' => addslashes($lr[6]),
					'Voided' => addslashes($lr[7]),
					'PendingDate' => addslashes($lr[8]),
					'Locked' => addslashes($lr[9]),
					'AffComment' => addslashes($lr[10]),
					'BannerPage' => addslashes($lr[11]),
					'ReversalDate' => addslashes($lr[12]),
					'ClickDate' => addslashes($lr[13]),
					'ClickTime' => addslashes($lr[14]),
					'BannerId' => addslashes($lr[15]),
					'SKUList' => addslashes($lr[16]),
					'QuantityList' => addslashes($lr[17]),
					'LockDate' => addslashes($lr[18]),
					'PaidDate' => addslashes($lr[19]),
					'MerchantOrganization' => addslashes($lr[20]),
					'MerchantWebsite' => addslashes($lr[21]),
				);
				$tras_num ++;

				if (count($arr_transaction) >= 100) {
					$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
					$arr_transaction = array();
				}
			}
			fclose($fp);

			$begin_dt = date('Y-m-d', strtotime('+1 day', strtotime($tmp_dt)));
			if ($i > 50) {
				break;
			}
		}
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
