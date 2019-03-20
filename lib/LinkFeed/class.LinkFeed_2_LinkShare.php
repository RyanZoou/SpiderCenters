<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_2_LinkShare
{
    private $maxPage;
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

        //$this->isFull = $this->info['isFull']; #Becasue of statusInAff can't update in every batch

        $this->maxPage = $this->info['APIKey5'];
        $this->GetProgramFromByPage();
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function getStatusByStr($str)
    {
        if(stripos($str,'No Relationship') !== false) return 'not apply';
        elseif(stripos($str,'Pending') !== false) return 'pending';
        elseif(stripos($str,'Approved') !== false) return 'approval';
        elseif(stripos($str,'Discontinued') !== false) return 'siteclosed';
        elseif(stripos($str,'Declined') !== false) return 'declined';
        elseif(stripos($str,'Removed') !== false) return 'expired';
        elseif(stripos($str,'Extended') !== false) return 'approval';
        return false;
    }

    function GetProgramFromByPage()
    {
        echo "\tGet Program by page start" . PHP_EOL;
        $objProgram = new ProgramDb();
        $program_num = $base_program_num = $statisticCsvCount = 0;
        $arr_prgm = $country_arr = $exist_prgm = array();

        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);

        //step 1, login
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info);

        //step 2, get program from csv.
        echo "start get program from csv" . PHP_EOL;
        $str_header = '"Advertiser Name","Advertiser URL","MID","Advertiser Description","Link to T&C","Link to Program History","Link to Home Page","Status","Contact Name","Contact Title","State","City","Address","Zip","Country","Phone","Email Address","Commission Terms","Offer","Offer Type","Year Joined","Expiration Date","Return Days","Transaction Update Window","TrueLock","Premium Status","Baseline Commission Terms","Baseline Offer","Baseline Offer Type","Baseline Expiration Date","Baseline Return Days","Baseline Transaction Update Window","Baseline TrueLock"';
        $cache_filecsv = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "caReport.csv", "cache_merchant");
        if(!$this->oLinkFeed->fileCacheIsCached($cache_filecsv))
        {
            $strUrl = "http://cli.linksynergy.com/cli/publisher/programs/consolidatedAdvertiserReport.php";
            $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
            $result = $r["content"];
            $file_id = intval($this->oLinkFeed->ParseStringBy2Tag($result, "http://cli.linksynergy.com/cli/publisher/programs/carDownload.php?id=", "'"));

            $strUrl = "http://cli.linksynergy.com/cli/publisher/programs/carDownload.php?id=$file_id";
            $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
            $result = $r["content"];
            if(stripos($result,$str_header) === false) {
                mydie("die: wrong csv file: $cache_filecsv");
            }
            $this->oLinkFeed->fileCachePut($cache_filecsv,$result);
        }

        echo "csv file has download." . PHP_EOL;

        $handle = fopen($cache_filecsv, 'r');
        while($line = fgetcsv ($handle, 5000)) {
            foreach($line as $k => $v) $line[$k] = trim($v);
            if ($line[0] == '' || $line[0] == 'Advertiser Name') {
                continue;
            }
            if(!isset($line[2])) {
                continue;
            }
            if(!isset($line[5])) {
                continue;
            }

            $Offer_url = $line[5];
            $AffDefaultUrl = $line[6];
            $strTmpMerID = $line[2];
            $StatusInAffRemark = $line[7];
            preg_match("/nid=(\d+)/i", $Offer_url, $matches);
            $nid = $matches[1];
            preg_match("/offerid=(\d+)/i", $AffDefaultUrl, $matches);
            $strTmpOfferID = $matches[1];
            $strMerID = $strTmpMerID."_".$nid;

            if($StatusInAffRemark == "Active"){
                $StatusInAff = "Active";
                $Partnership = "Active";
            }elseif($StatusInAffRemark == "Declined"){
                $StatusInAff = "Active";
                $Partnership = "Declined";
            }elseif($StatusInAffRemark == "Hold"){
                $StatusInAff = "Offline";
                $Partnership = "Active";
            }else{
                $StatusInAff = "Offline";
                $Partnership = "NoPartnership";
            }

            $arr_prgm[$strMerID] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $strMerID,
                'Partnership' => $Partnership,
                "AffDefaultUrl" => $AffDefaultUrl,
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                $strMerName = $line[0];
                $Homepage = $line[1];
                $desc = $line[3];
                $Contact_Name = $line[8];
                $Contact_Title = $line[9];
                $Contact_State = $line[10];
                $Contact_City = $line[11];
                $Contact_Address = $line[12];
                $Contact_Phone = $line[15];
                $Contact_Email = $line[16];
                $CommissionExt = $line[17];
                $JoinDate = $line[20];
                $ReturnDays = $line[22];
                $Contact_Zip = $line[23];
                $Contact_Country = $line[24];
                $Premium_Status = $line[25];
                $Contacts = "$Contact_Name($Contact_Title), Email: $Contact_Email, Phone: $Contact_Phone, Zip: $Contact_Zip, Address: $Contact_State $Contact_City $Contact_Address  $Contact_Country.";
                $JoinDate = $JoinDate . "-01-01 00:00:00";
                $RankInAff = 3;
                if ($Premium_Status == "Premium") {
                    $RankInAff = 5;
                }

                //program
                $arr_prgm[$strMerID] += array(
                    'CrawlJobId' => $this->info['crawlJobId'],
                    "Name" => addslashes(trim($this->coversionCode($strMerName))),
                    "Homepage" => addslashes($Homepage),
                    "Contacts" => addslashes($this->coversionCode($Contacts)),
                    "RankInAff" => $RankInAff,
                    "JoinDate" => $JoinDate,
                    "StatusInAffRemark" => addslashes($StatusInAffRemark),
                    "StatusInAff" => $StatusInAff,
                    "Description" => addslashes($this->coversionCode($desc)),
                    "CommissionExt" => addslashes($CommissionExt),
                    "CookieTime" => $ReturnDays,
                    "SecondIdInAff" => $strTmpOfferID,
                    "TargetCountryExt" => '',
                    "TermAndCondition" => '',
                    "SupportDeepUrl" => 'UNKNOWN',
                    'CategoryExt' => "",
                    'MobileFriendly' => 'UNKNOWN'
                );

                if (!isset($country_arr[$strTmpMerID])) {
                    $url = "http://cli.linksynergy.com/cli/publisher/programs/shipping_availability.php?mid=$strTmpMerID";
                    $cacheName = 'country_' . $strTmpMerID . '_' . date('YmdH') . '.cache';
                    $Country_result = $this->oLinkFeed->GetHttpResultAndCache($url, $request, $cacheName);
                    preg_match_all('/<td>(.*?)<\/td>/', $Country_result, $matches);
                    foreach ($matches[1] as $ke => $m) {
                        if (empty($m))
                            unset($matches[1][$ke]);
                    }
                    if (count($matches[1])) {
                        $TargetCountryExt = implode(',', $matches[1]);
                        $arr_prgm[$strMerID]['TargetCountryExt'] = $TargetCountryExt;
                        $country_arr[$strTmpMerID] = $TargetCountryExt;
                    }
                } else {
                    $arr_prgm[$strMerID]['TargetCountryExt'] = $country_arr[$strTmpMerID];
                }

                $more_info = $this->getSupportDUT($strTmpMerID, $strTmpOfferID, $request, true);
                if (isset($more_info['CategoryExt'])) {
                    $arr_prgm[$strMerID]['CategoryExt'] = addslashes($more_info['CategoryExt']);
                }
                if (isset($more_info['TermAndCondition'])) {
                    $arr_prgm[$strMerID]['TermAndCondition'] = addslashes($more_info['TermAndCondition']);
                }
                $arr_prgm[$strMerID]['SupportDeepUrl'] = $more_info['SupportDeepUrl'];

                $MobileFriendly = $this->getMobileFriendly($strTmpMerID);
                if (!empty($MobileFriendly)) {
                    $arr_prgm[$strMerID] = array_merge($MobileFriendly, $arr_prgm[$strMerID]);
                }
                $base_program_num ++;
            }

            $exist_prgm[$strMerID] = 1;
            $program_num++;
            $statisticCsvCount++;

            if(count($arr_prgm) >= 100){
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }
        }
        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }
        fclose($handle);
        echo "Finish get program from csv" . PHP_EOL;


        $request_category = array(
            'New' => array(
                'strUrl' => 'http://cli.linksynergy.com/cli/publisher/programs/advertisers.php',
                'postData' => '__csrf_magic=%s&analyticchannel=&analyticpage=&singleApply=&update=&remove_mid=&remove_oid=&remove_nid=&filter_open=&cat=&advertiserSearchBox=&category=-1&filter_status=all&filter_networks=all&filter_type=all&filter_banner_size=-1&orderby=&direction=&currec=%s&pagesize=%s',
                'statisticCount' => 0
            ),
            'My' => array(
                'strUrl' => 'http://cli.linksynergy.com/cli/publisher/programs/advertisers.php?my_programs=1',
                'postData' => '__csrf_magic=%s&analyticchannel=Programs&analyticpage=My+Advertisers&singleApply=&update=&remove_mid=&remove_oid=&remove_nid=&filter_open=&cat=&advertiserSerachBox_old=&advertiserSerachBox=&category=-1&filter_networks=all&filter_promotions=-1&filter_type=all&filter_banner_size=+--+All+Sizes+--&my_programs=1&filter_status_program=all&orderby=&direction=&currec=%s&pagesize=%s',
                'statisticCount' => 0
            ),
            'Premium' => array(
                'strUrl' => 'http://cli.linksynergy.com/cli/publisher/programs/advertisers.php?advertisers=1',
                'postData' => '__csrf_magic=%s&analyticchannel=Programs&analyticpage=Premium+Advertisers&singleApply=&update=&remove_mid=&remove_oid=&remove_nid=&filter_open=&cat=&advertiserSerachBox_old=&advertiserSerachBox=&category=-1&filter_status=all&filter_networks=all&filter_promotions=-1&filter_type=all&filter_banner_size=+--+All+Sizes+--&orderby=&direction=&currec=%s&pagesize=%s',
                'statisticCount' => 0
            )
        );

        $__csrf_magic = '';
        foreach ($request_category as $key => &$item) {
            echo "Get all $key merchants" . PHP_EOL;

            $arr_prgm = array();
            $request["method"] = "get";
            $nPageNo = 1;
            $nNumPerPage = 100;
            $bHasNextPage = true;
            while($bHasNextPage){
                if ($nPageNo >= $this->maxPage) {
                    mydie("get the page of all $key merchants of '{$this->info['AccountSiteID']} exceed max limit {$this->maxPage}', please check the network!\r\n");
                }

                echo "Page.$nPageNo\t";

                if($nPageNo != 1){
                    $request["method"] = "post";
                    $request["postdata"] = sprintf($item['postData'], urlencode($__csrf_magic), ($nNumPerPage * ($nPageNo - 1) + 1), $nNumPerPage);
                }
                $cacheName = "{$key}_merchants_page_{$nPageNo}_" . date('YmdH') . '.cache';
                $result = $this->oLinkFeed->GetHttpResultAndCache($item['strUrl'], $request, $cacheName);
                $__csrf_magic = $this->oLinkFeed->ParseStringBy2Tag($result, array("name='__csrf_magic'", 'value="'), '"');

                //parse HTML
                $strLineStart = '<td class="td_left_edge">';
                $nLineStart = 0;
                while ($nLineStart >= 0){
                    $nLineStart = stripos($result, $strLineStart, $nLineStart);
                    if ($nLineStart === false) {
                        break;
                    }

                    $strMerID = $this->oLinkFeed->ParseStringBy2Tag($result, array('select_mid[]', 'value="'), '"', $nLineStart);
                    list($strTmpMerID, $strTmpOfferID, $strTmpNetworkID) = explode('~', $strMerID);
                    $strMerID = $strTmpMerID.'_'.$strTmpNetworkID;
                    if(isset($exist_prgm[$strMerID])) {
                        continue;
                    }

                    $LogoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('<img ', 'src="'), '"', $nLineStart));
                    $strMerName = $this->oLinkFeed->ParseStringBy2Tag($result, 'helpMessage(this);">', '</a>', $nLineStart);
                    $strMerFlag = strtoupper($this->oLinkFeed->ParseStringBy2Tag($result, 'images/common/flag_', ".", $nLineStart));
                    $strMerName = trim($strMerName) . ' ('.$strMerFlag.') ';
                    $desc = strip_tags($this->oLinkFeed->ParseStringBy2Tag($result, "</span>", '<img src', $nLineStart));
                    $JoinDate = $this->oLinkFeed->ParseStringBy2Tag($result, array('<td class="td_date_joined">'), '</td>', $nLineStart);
                    $JoinDate = $JoinDate. "-01-01 00:00:00";
                    $CommissionExt = $this->oLinkFeed->ParseStringBy2Tag($result, array('<td class="td_commission">'), '</td>', $nLineStart);
                    $ReturnDays = $this->oLinkFeed->ParseStringBy2Tag($result, array('<td class="td_return">'), '</td>', $nLineStart);

                    $strStatusShow = trim($this->oLinkFeed->ParseStringBy2Tag($result, '<td class="td_status', "<br>", $nLineStart));
                    $strStatus = $this->getStatusByStr($strStatusShow);
                    if($strStatus === false) {
                        print_r($result);
                        mydie("Unknown Status : $strStatusShow <br>\n");
                    }
                    $StatusInAffRemark = strip_tags(str_ireplace(array('">','_temp">'),"",$strStatusShow));
                    if (stripos($strStatusShow,'Approved') !== false) {
                        $Partnership = "Active";
                        $StatusInAff = "Active";
                    } elseif (stripos($strStatusShow,'Pending') !== false) {
                        $Partnership = "Pending";
                        $StatusInAff = "Active";
                    } elseif (stripos($strStatusShow,'No Relationship') !== false) {
                        $Partnership = "NoPartnership";
                        $StatusInAff = "Active";
                    } elseif (stripos($strStatusShow, "Declined") !== false) {
                        $Partnership = "Declined";
                        $StatusInAff = "Active";
                    } elseif (stripos($strStatusShow, "Removed") !== false) {
                        $Partnership = "Removed";
                        $StatusInAff = "Active";
                    } elseif (stripos($strStatusShow, "Discontinued") !== false) {
                        $Partnership = "NoPartnership";
                        $StatusInAff = "TempOffline";
                    } elseif (stripos($strStatusShow, "Extended") !== false) {
                        $Partnership = "Active";
                        $StatusInAff = "Active";
                    } else {
                        $Partnership = "NoPartnership";
                        $StatusInAff = "Active";
                    }

                    $arr_prgm[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],
                        'BatchID' => $this->info['batchID'],
                        'IdInAff' => $strMerID,
                        'Partnership' => $Partnership
                    );

                    if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                        $TargetCountryExt = '';
                        if (!isset($country_arr[$strTmpMerID])) {
                            $url = "http://cli.linksynergy.com/cli/publisher/programs/shipping_availability.php?mid=$strTmpMerID";
                            $cacheName = "country_{$strTmpMerID}_" . date('YmdH') . '.cache';
                            $Country_result = $this->oLinkFeed->GetHttpResultAndCache($url, $request, $cacheName);
                            preg_match_all('/<td>(.*?)<\/td>/', $Country_result, $matches);
                            foreach ($matches[1] as $ke => $m) {
                                if (empty($m)) {
                                    unset($matches[1][$ke]);
                                }
                            }
                            if (count($matches[1])) {
                                $TargetCountryExt = implode(',', $matches[1]);
                                $country_arr[$strTmpMerID] = $TargetCountryExt;
                            }
                        } else {
                            $TargetCountryExt = $country_arr[$strTmpMerID];
                        }

	                    $url = "http://cli.linksynergy.com/cli/publisher/programs/Policies/paid_search.php?&mid=$strTmpMerID";
	                    $cacheName = "paid_search_{$strTmpMerID}_" . date('YmdH') . '.cache';
	                    $SEMPolicyExt = $this->oLinkFeed->GetHttpResultAndCache($url, $request, $cacheName);

                        //program
                        $arr_prgm[$strMerID] += array(
                            'CrawlJobId' => $this->info['crawlJobId'],
                            "Name" => addslashes(trim($strMerName)),
                            "Homepage" => '',
                            "CategoryExt" => "",
                            "Contacts" => '',
                            "TargetCountryExt" => $TargetCountryExt,
                            "RankInAff" => $key == 'Premium' ? 5 : 3,
                            "JoinDate" => $JoinDate,
                            "CreateDate" => "0000-00-00 00:00:00",
                            "DropDate" => "0000-00-00 00:00:00",
                            "TermAndCondition" => '',
                            "StatusInAffRemark" => addslashes($StatusInAffRemark),
                            "StatusInAff" => $StatusInAff,
                            "Description" => addslashes($desc),
                            "CommissionExt" => addslashes($CommissionExt),
                            "CookieTime" => $ReturnDays,
                            "LastUpdateTime" => date("Y-m-d H:i:s"),
                            "SecondIdInAff" => $strTmpOfferID,
                            'MobileFriendly' => 'UNKNOWN',
                            'LogoUrl' => $LogoUrl,
	                        'SEMPolicyExt' => addslashes($SEMPolicyExt),
                        );

                        //program_detail
                        $more_info = $this->getSupportDUT($strTmpMerID, $strTmpOfferID, $request, true);
                        $arr_prgm[$strMerID]['SupportDeepUrl'] = $more_info['SupportDeepUrl'];
                        if (isset($more_info['CategoryExt'])) {
                            $arr_prgm[$strMerID]['CategoryExt'] = addslashes($more_info['CategoryExt']);
                        }
                        if (isset($more_info['TermAndCondition'])) {
                            $arr_prgm[$strMerID]['TermAndCondition'] = addslashes($more_info['TermAndCondition']);
                        }
                        if (isset($more_info['Homepage'])) {
                            $arr_prgm[$strMerID]['Homepage'] = addslashes($more_info['Homepage']);
                        }
                        if (isset($more_info['Contacts'])) {
                            $arr_prgm[$strMerID]['Contacts'] = addslashes($more_info['Contacts']);
                        }
                        $MobileFriendly = $this->getMobileFriendly($strTmpMerID);
                        if (!empty($MobileFriendly)) {
                            $arr_prgm[$strMerID] = array_merge($MobileFriendly, $arr_prgm[$strMerID]);
                        }
                        $base_program_num ++;
                    }

                    $program_num++;
                    $item['statisticCount'] ++;

                    if(count($arr_prgm) >= 100){
                        $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                        $arr_prgm = array();
                    }
                }
                if(count($arr_prgm)){
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    unset($arr_prgm);
                }

                if (false === $this->oLinkFeed->ParseStringBy2Tag($result, "return false;'>Next", '</a></div></div>', $nLineStart)) {
                    $bHasNextPage = false;
                } else {
                    $nPageNo++;
                }
            }
        }

        echo "\tGet Program by page end" . PHP_EOL;
        echo "<hr>" . PHP_EOL;
        echo count($exist_prgm)."/".$program_num . PHP_EOL;
        echo "csv {$statisticCsvCount} / new {$request_category['New']['statisticCount']} / my {$request_category['My']['statisticCount']} / premium {$request_category['Premium']['statisticCount']}" . PHP_EOL;
        echo "<hr>" . PHP_EOL;
        if($program_num < 10){
            mydie("die: program count < 10, please check program.");
        }
        echo "\tUpdate ({$base_program_num}) base programs." . PHP_EOL;
        echo "\tUpdate ({$program_num}) site programs." . PHP_EOL;
    }

    function getSupportDUT($mid, $oid, $request, $needmoreinfo = false)
    {
        $mid = intval($mid);
        $oid = intval($oid);
        $SupportDeepUrl = "UNKNOWN";
        $return_arr = array('SupportDeepUrl' => $SupportDeepUrl);
        if($mid && $oid){
            $prgm_url = "http://cli.linksynergy.com/cli/publisher/programs/adv_info.php?mid=$mid&oid=$oid";
            $cacheName = "detail_page_{$mid}_{$oid}_" . date('YmdH') . '.cache';
            $prgm_detail = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request, $cacheName);

            $SupportDeepUrl = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Deep Linking Enabled', '<td>'), '</td>')));
            if(stripos($SupportDeepUrl, "yes") !== false){
                $SupportDeepUrl = "YES";
            }else{
                $SupportDeepUrl = "NO";
            }
            $return_arr = array('SupportDeepUrl' => $SupportDeepUrl);

            if($needmoreinfo){
                $CategoryExt = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Categories:', '<td>'), "</td>");
                $CategoryExt = trim(strip_tags(str_replace("<br>", EX_CATEGORY, $CategoryExt)), ",");
                $return_arr['CategoryExt'] = $CategoryExt;
                $Homepage = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Website:', '<td>'), "</td>")));
                $return_arr['Homepage'] = $Homepage;

                $Contact_Name = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Contact Name:', '<td>'), "</td>");
                $Contact_Title = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Contact Title:', '<td>'), "</td>");
                $Contact_Phone = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Phone Number:', '<td>'), "</td>");
                $Contact_Email = strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Email Address:', '<td>'), "</td>"));
                $Contact_Address = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Company Address:', '<td>'), "</td>");
                $Contact_Address = trim(strip_tags(str_replace("<br>", ", ", $Contact_Address)));
                $Contacts = "$Contact_Name($Contact_Title), Email: $Contact_Email, Phone: $Contact_Phone, Address: $Contact_Address.";
                $return_arr['Contacts'] = $Contacts;

                $term_url = $this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('Terms & Conditions:', 'href="'), '"');
                $cacheName = "term_{$mid}_{$oid}_" . date('YmdH') . '.cache';
                $TermAndCondition = $this->oLinkFeed->GetHttpResultAndCache($term_url, $request, $cacheName);
                $return_arr['TermAndCondition'] = @mb_convert_encoding($TermAndCondition, "UTF-8", mb_detect_encoding($TermAndCondition));
            }
        }
        return $return_arr;
    }

    function getMobileFriendly($mid)
    {
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get");
        $url = "http://cli.linksynergy.com/cli/publisher/programs/Tracking/mobile_tracking_detail.php?mid=$mid";
        $cacheName = "mobile_tracking_detail_{$mid}_" . date("YmdH") . '.cache';
        $content = $this->oLinkFeed->GetHttpResultAndCache($url, $request, $cacheName);
        if (preg_match('@green_check\.png"@', $content)) {
            return array('MobileFriendly' => 'YES');
        }
        if (preg_match('@red_x\.png"@', $content)) {
            return array('MobileFriendly' => 'NO');
        }
        if (!preg_match('@Pending\s+</td>@', $content)) {
            echo "error page format\n";
            echo "$url\n";
        }
        return array('MobileFriendly' => 'UNKNOWN');
    }

    function coversionCode($str)
    {
        $outStr = iconv($this->info['Charset'],'utf-8//IGNORE',$str);
        return $outStr;
    }

	function GetTransactionFromAffOld($start_date, $end_date)
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

		$objTransaction = New TransactionDb();
		$arr_transaction = $arr_find_Repeated_transactionId = array();
		$tras_num = 0;

		$url_api = 'https://ran-reporting.rakutenmarketing.com/en/reports/signature-orders-report-2/filters?start_date={BDATE}&end_date={EDATE}&include_summary=N&network={NID}&tz=GMT&date_type=process&token={TOKEN}';
		$request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => 'get');
		$NIDS = array(
			'1'=>array('country'=>'US','currency'=>'USD'),
			'3'=>array('country'=>'UK','currency'=>'GBP'),
			'5'=>array('country'=>'CA','currency'=>'CAD'),
			'7'=>array('country'=>'FR','currency'=>'EUR'),
			'9'=>array('country'=>'GE','currency'=>'EUR'),
			'41'=>array('country'=>'AU','currency'=>'AUD'),
		);

		foreach ($NIDS as $nid => $network) {
			$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "data_" . date("YmdH") . "_Transaction_{$network['country']}.csv", 'Transaction', true);
			if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
				$fw = fopen($cache_file, 'w');
				if (!$fw) {
					mydie("File open failed {$cache_file}");
				}
				$url = str_replace(array('{TOKEN}', '{BDATE}', '{EDATE}', '{NID}'), array($this->info['APIKey6'], $start_date, $end_date, $nid), $url_api);
				echo "req => {$url} \n";
				$request['file'] = $fw;
				$result = $this->oLinkFeed->GetHttpResult($url, $request);
				if ($result['code'] != 200){
					mydie("Download {$network['country']} csv file failed.");
				}
				fclose($fw);
			}

			$fp = fopen($cache_file, 'r');
			if (!$fp) {
				mydie("File open failed {$cache_file}");
			}

			$curr_code = isset($network['currency']) ? $network['currency'] : 'USD';
			$k = 0;
			while (!feof($fp)) {
				$lr = fgetcsv($fp, 50000, ',', '"');

				if (++$k == 1) {
					continue;
				}
				if ($lr[0] == "No Results Found") {
					continue;
				}
				if (empty($lr)){
					break;
				}

				$TransactionId = trim($lr[12]);
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
					'TransactionId' => $TransactionId,
					'MemberID_UI' => addslashes($lr[0]),
					'MID' => addslashes($lr[1]),
					'AdvertiserName' => addslashes($lr[2]),
					'OrderID' => addslashes($lr[3]),
					'TransactionDate' => addslashes($lr[4]),
					'TransactionTime' => addslashes($lr[5]),
					'SKU' => addslashes($lr[6]),
					'Sales' => addslashes($lr[7]),
					'Items' => addslashes($lr[8]),
					'TotalCommission' => addslashes($lr[9]),
					'ProcessDate' => addslashes($lr[10]),
					'ProcessTime' => addslashes($lr[11]),
					'Country' => $network['country'],
					'Currency' => $curr_code,
					'ReferrerURL' => addslashes($lr[13])
				);
				$tras_num ++;

				if (count($arr_transaction) >= 100) {
					$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
					$arr_transaction = array();
				}
			}
			fclose($fp);
			sleep(12);//api allows 5 req per min.
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

    function GetTransactionFromAff($start_date, $end_date)
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

        $api_key = '6595f21ae99802fe16f112a7d96bf2';

        $objTransaction = New TransactionDb();
        $arr_transaction = $arr_find_Repeated_transactionId = array();
        $tras_num = 0;
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => 'get',
            "addheader" => array(
                "Authorization: Bearer $api_key",
                'Accept: text/json'
            )
        );

        $page = 1;
        $hasNextPage = true;
        while ($hasNextPage) {
            $url_api = "https://api.rakutenmarketing.com/events/1.0/transactions?process_date_start=$start_date+00%3A00%3A00&process_date_end=$end_date+00%3A00%3A00&transaction_date_start=$start_date+00%3A00%3A00&transaction_date_end=$end_date+00%3A00%3A00&limit=3000&page=$page&currency=USD&type=realtime";
            $apiCacheName = "transaction_{$page}" . date('YmdH') . '.cache';
            $result = $this->oLinkFeed->GetHttpResultAndCache($url_api, $request, $apiCacheName);
            $data = json_decode($result, true);
            $count = count($data);

            echo "\tGet $count record" . PHP_EOL;

            if ($count < 3000) {
                $hasNextPage = false;
            } else {
                $page ++;
            }

            foreach ($data as $val) {
                $TransactionId = trim($val['etransaction_id']);
                if (!$TransactionId || $val['is_event'] == 'Y') {
                    continue;
                }
                if (isset($arr_find_Repeated_transactionId[$TransactionId])) {
                    mydie("The transactionId={$TransactionId} have earldy exists!");
                } else {
                    $arr_find_Repeated_transactionId[$TransactionId] = '';
                }

                $ProcessDate = date('Y-m-d H:i:s', strtotime($val['process_date']));
                $TransactionDate = date('Y-m-d H:i:s', strtotime($val['transaction_date']));

                $arr_transaction[$TransactionId] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'TransactionId' => $TransactionId,
                    'MemberID_UI' => addslashes(trim($val['u1'])),
                    'MID' => addslashes(trim($val['advertiser_id'])),
                    //'AdvertiserName' => addslashes(trim($val['u1'])),
                    'OrderID' => addslashes(trim($val['order_id'])),
                    'TransactionDate' => $TransactionDate,
                    'TransactionTime' => addslashes(trim($val['transaction_date'])),
                    'SKU' => addslashes(trim($val['sku_number'])),
                    'Sales' => addslashes(trim($val['sale_amount'])),
                    'Items' => addslashes(trim($val['quantity'])),
                    'TotalCommission' => addslashes(trim($val['commissions'])),
                    'ProcessDate' => $ProcessDate,
                    'ProcessTime' => addslashes(trim($val['process_date'])),
                    'Currency' => addslashes(trim($val['currency'])),
                    //'ReferrerURL' => addslashes(trim($val['u1']))
                );
                $tras_num ++;

                if (count($arr_transaction) >= 100) {
                    $objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
                    $arr_transaction = array();
                }
            }
            sleep(60);
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