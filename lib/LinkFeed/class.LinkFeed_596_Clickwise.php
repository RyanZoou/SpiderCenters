<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_596_Clickwise
{
	private $S;
	function __construct($aff_id, $oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->isFull = true;
		$this->username = urlencode($this->info["UserName"]);
		$this->password = urlencode($this->info["Password"]);
	}

	function Login()
	{
		$url = 'http://my.pampanetwork.com/scripts/track.php?url=H_my.pampanetwork.com%2Faffiliates%2Flogin.php&referrer=H_my.pampanetwork.com%2Faffiliates%2Findex.php&getParams=&anchor=login&isInIframe=false&cookies=';
		$re = $this->oLinkFeed->GetHttpResult($url);
		$re = $re['content'];
		$PAPVisitorId = $this->oLinkFeed->ParseStringBy2Tag($re, "('", "')");
		$strUrl = "http://my.pampanetwork.com/affiliates/login.php#login";
		$request = array(
			"AccountSiteID" => $this->info["AccountSiteID"],
			"method" => "get",
			"postdata" => "",
			"addcookie" => array(
				'PAPVisitorId' => array(
					'domain' => 'my.pampanetwork.com',
					'name' => 'PAPVisitorId',
					'value' => $PAPVisitorId,
				),
			),
		);
		$r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
		$result = $r["content"];
		$this->S = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, '[\"S\",\"', '\"'));
	
		$this->info["LoginPostString"] = str_ireplace('{S}', $this->S, $this->info["LoginPostString"]);
	
		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info,3,true,false,true);
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
		$arr_prgm = $arr_prgm_name = array();
		$program_num = $base_program_num = 0;
		
		//1.login
		$this->Login();
		
		//2.get homepage from link
		if ($this->isFull) {
			$homepage_arr = $arr_AffDefaultUrl = array();
			$page = 1;
			$Hoffset = 0;
			$limit = 100;
			$hasNextPage = true;
			$url = 'http://my.pampanetwork.com/scripts/server.php';
			while ($hasNextPage) {
				$request = array(
					"AccountSiteID" => $this->info["AccountSiteID"],
					"method" => "post",
					"postdata" => "D=%7B%22C%22%3A%22Gpf_Rpc_Server%22%2C+%22M%22%3A%22run%22%2C+%22requests%22%3A%5B%7B%22C%22%3A%22Pap_Affiliates_Promo_BannersGrid%22%2C+%22M%22%3A%22getRows%22%2C+%22offset%22%3A{$Hoffset}%2C+%22limit%22%3A{$limit}%2C+%22filters%22%3A%5B%5B%22type%22%2C%22IN%22%2C%22A%2CE%2CI%2CT%22%5D%5D%2C+%22columns%22%3A%5B%5B%22id%22%5D%2C%5B%22id%22%5D%2C%5B%22destinationurl%22%5D%2C%5B%22name%22%5D%2C%5B%22campaignid%22%5D%2C%5B%22campaignname%22%5D%2C%5B%22bannercode%22%5D%2C%5B%22bannerdirectlinkcode%22%5D%2C%5B%22bannerpreview%22%5D%2C%5B%22rtype%22%5D%2C%5B%22displaystats%22%5D%2C%5B%22channelcode%22%5D%2C%5B%22campaigndetails%22%5D%5D%7D%5D%2C+%22S%22%3A%22{$this->S}%22%7D",
				);

				$r = $this->oLinkFeed->GetHttpResult($url, $request);
				$result = json_decode($r["content"], true);

				$count = $result[0]['count'];
				foreach ($result[0]['rows'] as $v) {
					if ($Hoffset + 100 >= $count)
						$hasNextPage = false;
					if ($v[0] == 'id')                //第一元素是字段展示
						continue;
					if ($v[6] != 'A')                //active
						continue;
					if (isset($homepage_arr[$v[3]]))
						continue;
					$strMerID = $v[3];
					if (strpos($v[25], 'href')) {
						$tempAffDefultUrl = $this->oLinkFeed->ParseStringBy2Tag($v[25], '<a href="', "\"");
					} else {
						$tempAffDefultUrl = $v[25];
					}
					$arr_AffDefaultUrl [$strMerID] = str_replace('&amp;', '&', $tempAffDefultUrl);
					$OriginalUrl = $this->oLinkFeed->findFinalUrl($v[9]);
					$url_arr = parse_url($OriginalUrl);
					if (isset($url_arr['scheme']) && isset($url_arr['host']))
						$homepage = $url_arr['scheme'] . '://' . $url_arr['host'];
					else
						continue;
					if (stripos($homepage, 'track') !== false)
						continue;
					$homepage_arr[$strMerID] = $homepage;
				}
				$Hoffset += 100;
				$page++;
			}
		}

		//3.get program
		$offset = 0;
		$limit = 100;
		$hasNextPage = true;
		$url = 'http://my.pampanetwork.com/scripts/server.php';
		$status = array(
			'A' => 'approved',
			'D' => 'declined',
			'P' => 'waiting for approval',
		);

		while ($hasNextPage) {
			$request = array(
					"AccountSiteID" => $this->info["AccountSiteID"],
					"method" => "post",
					"postdata" => "D=%7B%22C%22%3A%22Gpf_Rpc_Server%22%2C+%22M%22%3A%22run%22%2C+%22requests%22%3A%5B%7B%22C%22%3A%22Pap_Affiliates_Promo_CampaignsGrid%22%2C+%22M%22%3A%22getRows%22%2C+%22offset%22%3A{$offset}%2C+%22limit%22%3A{$limit}%2C+%22columns%22%3A%5B%5B%22id%22%5D%2C%5B%22id%22%5D%2C%5B%22name%22%5D%2C%5B%22description%22%5D%2C%5B%22logourl%22%5D%2C%5B%22banners%22%5D%2C%5B%22commissionsdetails%22%5D%2C%5B%22rstatus%22%5D%2C%5B%22commissionsexist%22%5D%2C%5B%22affstatus%22%5D%2C%5B%22dateinserted%22%5D%2C%5B%22overwritecookie%22%5D%2C%5B%22avarageConversion%22%5D%2C%5B%22actions%22%5D%5D%7D%5D%2C+%22S%22%3A%22{$this->S}%22%7D",
			);
			$r = $this->oLinkFeed->GetHttpResult($url,$request);

			$result = json_decode($r["content"],true);
			/* array (
				[0] =>  'id',
				[1] =>  'campaignid',
				[2] =>  'rstatus',						'A':Active,'W':TempOffline
				[3] =>  'name',
				[4] =>  'description',
				[5] =>  'dateinserted',
				[6] =>  'logourl',
				[7] =>  'overwritecookie',
				[8] =>  'banners',
				[9] =>  'avarageConversion',
				[10] => 'affstatus',					'A','D','P'
				[11] => 'commissionsexist',				'Y','N'
				[12] => 'commissionsdetails',
			) */
			$count = $result[0]['count'];
			if ($offset+100 >= $count)
				$hasNextPage = false;

			foreach ($result[0]['rows'] as $v)
			{
				if ($v[0] == 'id')
					continue;
				$strMerID = $v[0];

				if (empty($v[10]))
					$StatusInAffRemark = 'NoPartnership';
				else 
					$StatusInAffRemark = $status[$v[10]];
				
				if ($StatusInAffRemark == 'approved') {
					$Partnership = 'Active';
				}elseif ($StatusInAffRemark == 'declined') {
					$Partnership = 'Declined';
				}elseif ($StatusInAffRemark == 'waiting for approval') {
					$Partnership = 'Pending';
				}elseif ($StatusInAffRemark == 'NoPartnership') {
					$Partnership = 'NoPartnership';
				}else {
					print_r($v);
					mydie("die: New status is $v[10], add it please");
				}

				$arr_prgm[$strMerID] = array(
					'AccountSiteID' => $this->info["AccountSiteID"],
					'BatchID' => $this->info['batchID'],
					'IdInAff' => $strMerID,
					'Partnership' => $Partnership,
					"AffDefaultUrl" => addslashes((isset($arr_AffDefaultUrl [$strMerID]))?$arr_AffDefaultUrl[$strMerID]:''),
				);

				if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
					if ($v[2] == 'A')
						$StatusInAff = 'Active';
					elseif ($v[2] == 'W')
						$StatusInAff = 'TempOffline';
					else
						mydie("die: there is new rstatus named $v[2], add it please");

					$strMerName = trim($v[3]);
					$LogoUrl = trim($v[6]);
					$CreateDate = trim($v[5]);

					$search = array(
						'/<!--[\/!]*?[^<>]*?-->/isu', // 去掉 注释标记
						'/<script[^>]*?>.*?<\/script>/isu', // 去掉 javascript
						'/<style[^>]*?>.*?<\/style>/isu', // 去掉 css
					);
					$desc = preg_replace($search, '', $v[4]);
					$CommissionExt = trim(html_entity_decode($v[12]));
					$lineStart = 0;

					while (1) {
						$CategoryExt = trim(strip_tags(html_entity_decode($this->oLinkFeed->ParseStringBy2Tag($desc, '>', '<', $lineStart))));
						$lastStr = substr($desc, $lineStart + 1);
						if (!empty($CategoryExt) || (strpos($lastStr, '<') === false))
							break;
					}
					$country_str = "Armenia,Netherlands Antilles,Angola,Antarctica,Argentina,America,Austria,Australia,Aruba,Azerbaijan,Bosnia and Herzegovina,Barbados,Bangladesh,Belgium,Burkina Faso,Bulgaria,Bahrain,Burundi,Benin,Bermuda,Brunei Darussalam,Bolivia,Brazil,Bahamas,Bhutan,Bouvet Island,Botswana,Belarus,Belize,Canada,Cocos (Keeling) Islands,Congo, The Democratic Republic of the,Central African Republic,Congo,Switzerland,Cote D'Ivoire,Cook Islands,Chile,Cameroon,China,Colombia,Costa Rica,Cuba,Cape Verde,Christmas Island,Cyprus,Czech Republic,Germany,Djibouti,Denmark,Dominica,Dominican Republic,Algeria,Ecuador,Estonia,Egypt,Western Sahara,Eritrea,Spain,Ethiopia,Finland,Fiji,Falkland Islands (Malvinas),Micronesia, Federated States of,Faroe Islands,France,France, Metropolitan,Gabon,United Kingdom,Grenada,Georgia,French Guiana,Ghana,Gibraltar,Greenland,Gambia,Guinea,Guadeloupe,Equatorial Guinea,Greece,South Georgia and the South Sandwich Islands,Guatemala,Guam,Guinea-Bissau,Guyana,Hong Kong,Heard Island and McDonald Islands,Honduras,Croatia,Haiti,Hungary,Indonesia,Ireland,Israel,India,British Indian Ocean Territory,Iraq,Iran, Islamic Republic of,Iceland,Italy,Jamaica,Jordan,Japan,Kenya,Kyrgyzstan,Cambodia,Kiribati,Comoros,Saint Kitts and Nevis,Korea, Democratic People's Republic of,Korea, Republic of,Kuwait,Cayman Islands,Kazakstan,Lao People's Democratic Republic,Lebanon,Saint Lucia,Liechtenstein,SriLanka,Liberia,Lesotho,Lithuania,Luxembourg,Latvia,Libyan,Morocco,Monaco,Moldova, Republic of,Madagascar,Marshall Islands,Macedonia,Mali,Myanmar,Mongolia,Macau,Northern Mariana Islands,Martinique,Mauritania,Montserrat,Malta,Mauritius,Maldives,Malawi,Mexico,Malaysia,Mozambique,Namibia,New Caledonia,Niger,Norfolk Island,Nigeria,Nicaragua,Netherlands,Norway,Nepal,Nauru,Niue,New Zealand,Oman,Panama,Peru,French Polynesia,Papua New Guinea,Philippines,Pakistan,Poland,Saint Pierre and Miquelon,Pitcairn Islands,Puerto Rico,Palestinian Territory,Portugal,Palau,Paraguay,Qatar,Reunion,Romania,Russia,Rwanda,Saudi,Arab,Solomon Islands,Seychelles,Sudan,Sweden,Singapore,Saint Helena,Slovenia,Svalbardand Jan Mayen,Slovakia,Sierra Leone,San Marino,Senegal,Somalia,Suriname,Sao Tome and Principe,El Salvador,Syrian,Swaziland,Turks and Caicos Islands,Chad,French Southern Territories,Togo,Thailand,Tajikistan,Tokelau,Turkmenistan,Tunisia,Tonga,Timor-Leste,Turkey,Trinidad and Tobago,Tuvalu,Taiwan,Tanzania, United Republic of,Ukraine,Uganda,United States Minor Outlying Islands,United States,Uruguay,Uzbekistan,Holy See (Vatican City State),Saint Vincent and the Grenadines,Venezuela,Virgin Islands, British,Virgin Islands, U.S.,Vietnam,Vanuatu,Wallis and Futuna,Samoa,Yemen,Mayotte,Serbia,South Africa,Zambia,Montenegro,Zimbabwe,Anonymous Proxy,Satellite Provider,Other,Aland Islands,Guernsey,Isle of Man,Jersey,world,uk,usa,LATAM,BRASIL,MÉXICO,KENIA,ESPAÑA,Europe,PERÚ,IVORY COAST,UAE,UAE (United Arab Emirates),KSA,UNITED STATES (USA)";
					$country_arr = explode(",", $country_str);

					$arr_desc = explode("<br", $desc);
					$arr_targetCountry = array();
					$s = array(' ', ' ');
					foreach ($arr_desc as $dv) {
						$lastStr = str_replace('"><b>', '">', $dv);
						while (1) {
							$TargetCountry = trim(strip_tags(html_entity_decode($this->oLinkFeed->ParseStringBy2Tag($lastStr, '">', '<'))));
							$lastStr = substr($lastStr, $lineStart + 1);
							foreach ($country_arr as $c) {
								if (stripos($TargetCountry, $c) !== false) {
									$arr_targetCountry[] = str_replace($s, '', $TargetCountry);
									break 2;
								}
							}
							if (strpos($lastStr, '<') === false)
								break;
						}
					}
					$TargetCountryExt = implode(',', $arr_targetCountry);
					$TermAndCondition = trim(strip_tags(html_entity_decode(substr($desc, $lineStart))));
					$Homepage = isset($homepage_arr[$strMerID]) ? $homepage_arr[$strMerID] : '';
					$desc = trim(strip_tags(html_entity_decode($desc)));
					$arr_prgm[$strMerID] += array(
						'CrawlJobId' => $this->info['crawlJobId'],
						"Name" => addslashes($strMerName),
						"TargetCountryExt" => addslashes($TargetCountryExt),
						"JoinDate" => $CreateDate,
						"StatusInAffRemark" => $StatusInAffRemark,
						"StatusInAff" => $StatusInAff,                        //'Active','TempOffline','Offline'
						"Description" => addslashes($desc),
						"Homepage" => addslashes($Homepage),
						"TermAndCondition" => addslashes($TermAndCondition),
						"CommissionExt" => addslashes($CommissionExt),
						"CategoryExt" => addslashes(trim($CategoryExt)),
						"LogoUrl" => addslashes($LogoUrl),
					);
					$base_program_num++;
				}

				$program_num++;
				if(count($arr_prgm) >= 100){
					$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
					$arr_prgm = array();
				}
			}
			$offset += 100;
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

	function getTransactionFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Transaction start @ {$check_date}\r\n";

		$objTransaction = New TransactionDb();
		$arr_transaction = $arr_find_Repeated_transactionId = array();
		$tras_num = 0;

		$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "data_" . date("YmdH") . "_Transaction.tmp", 'Transaction', true);
		if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
            $this->Login();
			//get the must param fileId
			$api_url = 'http://my.pampanetwork.com/scripts/server.php';
			$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "post",
				"postdata" => "D=%7B%22C%22%3A%22Gpf_Rpc_Server%22%2C+%22M%22%3A%22run%22%2C+%22requests%22%3A%5B%7B%22C%22%3A%22Pap_Affiliates_Reports_TransactionsGrid%22%2C+%22M%22%3A%22makeCSVFile%22%2C+%22csv_export_timezone%22%3A%22S%22%2C+%22delimiter%22%3A%22c%22%2C+%22fields%22%3A%5B%5B%22name%22%2C%22value%22%5D%2C%5B%22filters%22%2C%22%5B%5D%22%5D%2C%5B%22sort_col%22%2C%22dateinserted%22%5D%2C%5B%22columns%22%2C%22%5B%5B%5C%22id%5C%22%5D%2C%5B%5C%22id%5C%22%5D%2C%5B%5C%22commission%5C%22%5D%2C%5B%5C%22totalcost%5C%22%5D%2C%5B%5C%22t_orderid%5C%22%5D%2C%5B%5C%22productid%5C%22%5D%2C%5B%5C%22countrycode%5C%22%5D%2C%5B%5C%22dateinserted%5C%22%5D%2C%5B%5C%22name%5C%22%5D%2C%5B%5C%22campaignid%5C%22%5D%2C%5B%5C%22rtype%5C%22%5D%2C%5B%5C%22tier%5C%22%5D%2C%5B%5C%22commissionTypeName%5C%22%5D%2C%5B%5C%22rstatus%5C%22%5D%2C%5B%5C%22payoutstatus%5C%22%5D%2C%5B%5C%22payouthistoryid%5C%22%5D%2C%5B%5C%22lastclickdata1%5C%22%5D%2C%5B%5C%22original_currency_code%5C%22%5D%2C%5B%5C%22originalcurrencyvalue%5C%22%5D%2C%5B%5C%22payoutdate%5C%22%5D%5D%22%5D%2C%5B%22sort_asc%22%2C%22true%22%5D%2C%5B%22csv_export_timezone%22%2C%22S%22%5D%2C%5B%22delimiter%22%2C%22c%22%5D%5D%7D%5D%2C+%22S%22%3A%22{$this->S}%22%7D",
			);
			$result = $this->oLinkFeed->GetHttpResult($api_url, $request);

			$re = json_decode($result['content'], true);
			//print_r($re);exit;

			if (!isset($re[0]['success']) || $re[0]['success'] != 'Y')
				mydie('export to csv field, please check it');
			if (isset($re[0]['fileds'][8][0]) && $re[0]['fileds'][8][0] == 'fileId')
				$fileId = $re[0]['fileds'][8][1];
			else {
				foreach($re[0]['fields'] as $v) {
					if ($v[0] == 'fileId') {
						$fileId = $v[1];
						break;
					}
				}
			}
			if (empty($fileId))
				mydie("fileId is empty when export to csv, please check it");


			$fw = fopen($cache_file, 'w');
			if (!$fw) {
				throw new Exception("File open failed {$cache_file}");
			}
			$strUrl = "http://my.pampanetwork.com/scripts/server.php?C=Pap_Affiliates_Reports_TransactionsGrid&M=getCSVFile&S={$this->S}&FormRequest=Y&FormResponse=Y&fileId=$fileId";
			$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "get",
				"file" => $fw
			);
			$result = $this->oLinkFeed->GetHttpResult($strUrl, $request);
			if ($result['code'] != 200){
				mydie("Download XML file failed.");
			}
			fclose($fw);
		}


		$fp = fopen ($cache_file, 'r');
		$k = 0;
		while (!feof($fp)) {
			$lr = explode(",",trim(fgets($fp)));
			if (++$k == 1 || empty($lr[1])) {
				continue;
			}

			$TransactionId = trim($lr[0],'"');
			if (!$TransactionId) {
				continue;
			}
			if (isset($arr_find_Repeated_transactionId[$TransactionId])) {
				mydie("The transactionId={$TransactionId} have earldy exists!");
			} else {
				$arr_find_Repeated_transactionId[$TransactionId] = '';
			}

			$arr_transaction[$TransactionId] = array(
				'AccountSiteID' => $this->info["AccountSiteID"],
				'BatchID' => $this->info['batchID'],
				'TransactionId' => $TransactionId,                      //must be unique
                'ID' => addslashes(trim($lr[0],'"')),
                'Commission' => addslashes(trim($lr[1],'"')),
                'TotalCost' => addslashes(trim($lr[2],'"')),
                'OrderID' => addslashes(trim($lr[3],'"')),
                'ProductID' => addslashes(trim($lr[4],'"')),
                'CountryCode' => addslashes(trim($lr[5],'"')),
                'Created' => addslashes(trim($lr[6],'"')),
                'CampaignName' => addslashes(trim($lr[7],'"')),
                'CampaignID' => addslashes(trim($lr[8],'"')),
                'Type' => addslashes(trim($lr[9],'"')),
                'Tier' => addslashes(trim($lr[10],'"')),
                'commissionTypeName' => addslashes(trim($lr[11],'"')),
                'Status' => addslashes(trim($lr[12],'"')),
                'Paid' => addslashes(trim($lr[13],'"')),
                'PayoutHistoryID' => addslashes(trim($lr[14],'"')),
                'LastClickData1' => addslashes(trim($lr[15],'"')),
                'OriginalCurrency' => addslashes(trim($lr[16],'"')),
                'OriginalCurrencyValue' => addslashes(trim($lr[17],'"')),
                'PayoutDate' => addslashes(trim($lr[18],'"')),
                'original_currency_symbol' => addslashes(trim($lr[19],'"')),
                'original_currency_precision' => addslashes(trim($lr[20],'"')),
                'original_currency_wheredisplay' => addslashes(trim($lr[21],'"')),
                'allowlastclickdata' => addslashes(trim($lr[22],'"'))
			);
			$tras_num ++;

			if (count($arr_transaction) >= 100) {
				$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
				$arr_transaction = array();
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
?>