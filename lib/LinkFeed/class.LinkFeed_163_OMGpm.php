<?php
require_once 'text_parse_helper.php';
class LinkFeed_163_OMGpm
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->affParams = json_decode($this->info['APIKey1'], true);
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
        $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"],"{$this->info["AccountSiteID"]}_".date("YW").".dat", "program", true);
        $cache = array();
        if($this->oLinkFeed->fileCacheIsCached($cache_file)){
            $cache = file_get_contents($cache_file);
            $cache = json_decode($cache,true);
        }

        //login step 1;
        if ($this->info['APIKey2'] == 'bdg02') {
            $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info);
        }

        $Agency = $this->affParams['Agency'];
        $Affiliate = $this->affParams['Affiliate'];

        //get Details url start
        date_default_timezone_set("UTC");
        $t = microtime(true);
        $micro = sprintf("%03d",($t - floor($t)) * 1000);
        $utc = gmdate('Y-m-d H:i:s.', $t).$micro;
        $sig_data= $utc;
        $API_Key = $this->affParams['API_Key'];
        $private_key = $this->affParams['private_key'];

        $concateData = $private_key.$sig_data;
        $sig = md5($concateData);
        $progm_url = "https://api.omgpm.com/network/OMGNetworkApi.svc/v1.2/GetProgrammes?AID=$Affiliate&AgencyID=$Agency&CountryCode=&Key=$API_Key&Sig=$sig&SigData=".urlencode($sig_data);
        date_default_timezone_set("America/Los_Angeles");

        // get program from csv.
        $str_header = "Product Feed Available";
        $cache_filecsv = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"],"program.csv","cache_merchant");
        if(!$this->oLinkFeed->fileCacheIsCached($cache_filecsv))
        {
            $r = $this->oLinkFeed->GetHttpResult($progm_url,$request);
            $result = $r["content"];
            if(stripos($result,$str_header) === false) mydie("die: wrong csv file: $cache_filecsv, url: $progm_url");
            $this->oLinkFeed->fileCachePut($cache_filecsv,$result);
        }

        $fhandle = file_get_contents($cache_filecsv, 'r');
        $res = json_decode($fhandle,true);
        $res = $res['GetPublisherProgrammesResult'];

        foreach($res as $k) {

            $IdInAff = intval($k['PID']);

            $StatusInAffRemark = trim($k['Programme Status']);
            if($StatusInAffRemark == "Not Applied"){
                $Partnership = "NoPartnership";
            }elseif($StatusInAffRemark == "Rejected"){
                $Partnership = "Declined";
            }elseif($StatusInAffRemark == "Live"){
                $Partnership = "Active";
            }elseif($StatusInAffRemark == "Cancelled"){
                $Partnership = "Expired";
            }elseif($StatusInAffRemark == "Waiting"){
                $Partnership = "Pending";
            }else{
                $Partnership = "NoPartnership";
            }
            $AffDefaultUrl = $k['Tracking URL'];

            $arr_prgm[$IdInAff] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $IdInAff,
                'Partnership' => $Partnership,
                'AffDefaultUrl' => $AffDefaultUrl,
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
                $MerchantName = $k['Merchant Name'];
                $ProductName = $k['Product Name'];
                $desc = $k['Product Description'];
                $CategoryExt = $k['Sector'];
                $TargetCountryExt = trim($k['Country Code']);
                $PayoutType = $k['Payout Type'];
                $ReturnDays = $k['Cookie Duration'];
                $SupportDeepurl = $k['Deep Link Enabled'];
                $Homepage = $k['Website URL'];
                $CommissionExt = $k['Commission'];
                $LogoUrl = $k['Merchant Logo URL'];
                $prgm_name = $MerchantName . "-" . $ProductName;
                $CommissionExt .= "PayoutType: " . $PayoutType;

                if (stripos($prgm_name, "closed") !== false) {
                    $StatusInAff = 'Offline';
                } else {
                    $StatusInAff = 'Active';
                }

                $ContactWebsiteID = $k['Contact WebsiteID'];
                $prgm_url = "https://admin.optimisemedia.com/v2/programmes/affiliate/viewprogramme.aspx?ProductID=$IdInAff&ContactWebsiteID=$ContactWebsiteID";

                $arr_prgm[$IdInAff] += array(
                    'CrawlJobId' => $this->info['crawlJobId'],
                    "Name" => addslashes($prgm_name),
                    "Homepage" => addslashes($Homepage),
                    "StatusInAffRemark" => addslashes($StatusInAffRemark),
                    "StatusInAff" => $StatusInAff,
                    "CategoryExt" => addslashes($CategoryExt),
                    "TargetCountryExt" => $TargetCountryExt,
                    "Description" => addslashes($desc),
                    "CommissionExt" => addslashes($CommissionExt),
                    "CookieTime" => addslashes($ReturnDays),
                    "SupportDeepUrl" => strtoupper($SupportDeepurl),
                    "LogoUrl" => addslashes($LogoUrl),
                	"SupportType" => "Content".EX_CATEGORY."Coupon"
                );

                if ($this->info['APIKey2'] == 'bdg02') {
                    if (!isset($cache[$IdInAff]['Homepage'])) {
                        $prgm_r = $this->oLinkFeed->GetHttpResult($prgm_url, $request);
                        if ($prgm_r['code'] == 200) {
                            $prgm_r = $prgm_r['content'];
                            $Homepage = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_r, 'ContentPlaceHolder1_lbPreview" href="', '"'));
                            $arr_prgm[$IdInAff]['Homepage'] = addslashes($Homepage);
                            $cache[$IdInAff]['Homepage'] = $Homepage;
                        }
                    } else {
                        $arr_prgm[$IdInAff]['Homepage'] = addslashes($cache[$IdInAff]['Homepage']);
                    }
                }
                $base_program_num++;
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
        $cache = json_encode($cache);
        $this->oLinkFeed->fileCachePut($cache_file, $cache);

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

}
