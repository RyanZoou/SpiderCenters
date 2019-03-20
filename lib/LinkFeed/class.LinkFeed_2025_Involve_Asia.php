<?php
class LinkFeed_2025_Involve_Asia
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
    }

    function LoginIntoAffService(&$request)
    {
        echo "login to affservice\n\t";

        $loginUrl = "https://app.involve.asia/";
        $this->oLinkFeed->clearHttpInfos($this->info["AccountSiteID"]);
        $r = $this->oLinkFeed->GetHttpResult($loginUrl, $request);

        if ($r["code"] == 0) {
            if (preg_match("/^SSL: certificate subject name .*? does not match target host name/i", $r["error_msg"])) {
                $request["no_ssl_verifyhost"] = 1;
                $r = $this->GetHttpResult($loginUrl, $request);
            }
        }
        if (!strpos($r['content'], 'type="hidden"')) {
            mydie("die: login failed for site({$this->info['AccountSiteID']}) when load to loginPage!<br>\n");
        }

        $token_key = $this->oLinkFeed->ParseStringBy2Tag($r["content"], 'type="hidden" name="', '"');
        $token_val = $this->oLinkFeed->ParseStringBy2Tag($r["content"], array('type="hidden"', 'value="'), '"');
        $request['postdata'] = $token_key . '=' . $token_val;

        $this->info["LoginPostString"] .= '&' . $token_key . '=' . $token_val;

        $request['method'] = 'post';
        $request['postdata'] = $this->info["LoginPostString"];

        $r = $this->oLinkFeed->GetHttpResult($loginUrl, $request);

        if (stripos($r["content"], $this->info['LoginVerifyString']) === false) {
            mydie("die: login failed for site({$this->info['AccountSiteID']}) when login in!");
        }

        echo "verify succ: " . $this->info["LoginVerifyString"] . "\n";
        return 'stop here !';
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
        $arr_prgm = $program_id_list = array();
        $program_num = $base_program_num = 0;

        //step 1, get basic info from api
        echo "\tGet Program by api start\r\n";
        $apiKey = urlencode($this->info['APIKey1']);
        $apiSecret = urlencode($this->info['APIKey2']);

        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "post",
            "postdata" => "secret={$apiSecret}&key={$apiKey}"
        );

        $r = $this->oLinkFeed->GetHttpResult('https://api.involve.asia/api/authenticate', $request);
        $rToken = json_decode($r['content'], true);
        if (strpos($rToken['status'], 'success') === false) mydie('Failed get token !');
        $token = $rToken['data']['token'];

        $hasNextPage = true;
        $page = 1;
        $limit = 100;
        $program_list_url = 'https://api.involve.asia/api/offers/all';
        $request['addheader'] = array(
            'Content-Type: application/x-www-form-urlencoded',
            "Authorization: Bearer $token"
        );

        while ($hasNextPage) {
            $request['postdata'] = http_build_query(array('page' => $page, 'limit' => $limit));
            $r = $this->oLinkFeed->GetHttpResult($program_list_url, $request);
            $result = json_decode($r['content'], true);
            if ($result['status'] != 'success' || empty($result['data'])) {
                mydie("Failed to get data from api.");
            }
            if ($result['data']['count'] <= $page * $limit) {
                $hasNextPage = false;
            } else {
                echo "$page\t";
                $page++;
            }
            foreach ($result['data']['data'] as $pv) {
                $IdInAff = intval($pv['offer_id']);
                if (!$IdInAff) {
                    continue;
                }
                echo "$IdInAff\t";

                $program_id_list[$IdInAff] = '';

                /*
                $statusInRemark = trim($pv['status']);
                switch ($statusInRemark) {
                    case 'approved':
                        $partnership = 'Active';
                        break;
                    default:
                        mydie("die: new partnership [$statusInRemark].\n");
                        break;
                }
                */

                $arr_prgm[$IdInAff] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $IdInAff,
                    'Partnership' => 'Active',
                    'AffDefaultUrl' => addslashes($pv['tracking_link']),
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
                    $arr_prgm[$IdInAff] += array(
                        "Name" => addslashes($pv['offer_name']),
                        "Homepage" => addslashes($pv['preview_url']),
//                        "StatusInAffRemark" => addslashes($statusInRemark),
                        "StatusInAff" => 'Active',
                        "LogoUrl" => addslashes($pv['logo']),
                    );
                }
                $program_num ++;
                if (count($arr_prgm) >= 100) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
                    $arr_prgm = array();
                }
            }
        }

        if (count($arr_prgm)) {
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
            $arr_prgm = array();
        }
        echo "\n\tGet Program by api end\r\n";
        echo "\tUpdate ({$program_num}) program from api.\r\n";


        //step 2, get more info from page.
        echo "\tGet Program by page start\r\n";
        $program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "");
        $this->LoginIntoAffService($request);
        $request['method'] = 'get';

        $list_url = 'https://app.involve.asia/publisher/search?merchant_name=&sort_by=relevance&require_approval=&categories=&countries=';
        $r = $this->oLinkFeed->GetHttpResult($list_url, $request);
        $result = @json_decode($r['content'], true);
        $pageContent = preg_replace("/>\\s+</i", "><", $result['data']['contents']);
        $p_arr = explode('<div class="col-sm-12 col-md-6 col-lg-4">', $pageContent);

        foreach ($p_arr as $program) {
            if (empty($program)) continue;
            preg_match('@src="https:\/\/img\.involve\.asia\/ia_logo\/(.+)" alt="Offer Logo@', $program, $m);

            if (!empty($m[1])) {
                $IdInAff = intval($m[1]);
            } else {
                continue;
            }

            if (!$IdInAff || isset($program_id_list[$IdInAff])) {
                continue;
            } else {
                $program_id_list[$IdInAff] = '';
            }

            echo $IdInAff . "\t";

            $strPosition = 0;
            $homePage = $AffDefualtUrl = '';
            $name = $this->oLinkFeed->ParseStringBy2Tag($program, 'title="', '"', $strPosition);
            $logo_url = $this->oLinkFeed->ParseStringBy2Tag($program, array('<img', 'src='), '"', $strPosition);
            if (stripos($program, 'data-merchant_id="') !== false) {
                $homePage = trim($this->oLinkFeed->ParseStringBy2Tag($program, 'data-preview_url="', '"', $strPosition));
                $statusInRemark = trim($this->oLinkFeed->ParseStringBy2Tag($program, array('class="btn btn', '>'), '</a>', $strPosition));
                $AffDefualtUrl = html_entity_decode($this->oLinkFeed->ParseStringBy2Tag($program, 'a href="', '"', $strPosition));
            } elseif (stripos($program, 'name="btnofferid_') !== false) {
                $statusInRemark = trim($this->oLinkFeed->ParseStringBy2Tag($program, array('class="btn btn', '>'), '</a>', $strPosition));
                $homePage = trim($this->oLinkFeed->ParseStringBy2Tag($program, 'a href="', '"', $strPosition));
            }

            switch ($statusInRemark) {
                case 'Get Link':
                    $partnership = 'Active';
                    break;
                case 'Pending':
                    $partnership = 'Pending';
                    break;
                case 'Apply':
                case 'Learn more':
                    $partnership = 'NoPartnership';
                    break;
                case 'Rejected':
                    $partnership = 'Declined';
                    break;
                default:
                    mydie("die: new partnership [$statusInRemark].\n");
                    break;
            }
            $arr_prgm[$IdInAff] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $IdInAff,
                'Partnership' => $partnership,
                'AffDefaultUrl' => addslashes($AffDefualtUrl),
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
                $arr_prgm[$IdInAff] += array(
                    "Name" => addslashes($name),
                    "Homepage" => addslashes($homePage),
                    "StatusInAffRemark" => addslashes($statusInRemark),
                    "StatusInAff" => 'Active',                    //'Active','TempOffline','Offline'
                    "LogoUrl" => addslashes($logo_url),
                );
            }
            $program_num ++;
            if (count($arr_prgm) >= 100) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
                $arr_prgm = array();
            }
        }
        if (count($arr_prgm)) {
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
            $arr_prgm = array();
        }
        echo "\n\tGet Program by page end\r\n";
        echo "\tUpdate ({$program_num}) program from page.\r\n";


        //step 3 get detail info from detail page.
        if ($this->isFull) {
            echo "\tGet Program by page detail start\r\n";
            $objProgram = new ProgramDb();
            $program_num = 0;
            foreach ($program_id_list as $IdInAff => $val) {
                echo "$IdInAff\t";

                if ($objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
                	continue;
                }

                $DetailPage = "https://app.involve.asia/publisher/browse/$IdInAff";
                $r = $this->oLinkFeed->GetHttpResult($DetailPage, $request);
                $DPresult = preg_replace("/>\\s+</i", "><", $r['content']);
                $description = $this->oLinkFeed->ParseStringBy2Tag($DPresult, array('> Description</h4><div', '>'), '<h4');
                $termAndCondition = $this->oLinkFeed->ParseStringBy2Tag($DPresult, array('Terms and Conditions</span>', '</p>'), '</div>');
                $categoryExt = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($DPresult, array('Merchant Category', '>'), '</ul')));
                $countryExt = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($DPresult, 'Available Countries', '</ul')));
                $AvailableTools = $this->oLinkFeed->ParseStringBy2Tag($DPresult, 'Available Tools', '</ul');
                $SEMPolicyExt = '';
                $att_arr = explode('</p>', $description);
                foreach ($att_arr as $key => $p){
                	if (stripos($p, 'Search Campaigns')){
                		$SEMPolicyExt .= $p;
                	}
                }

                $SupportDeepUrl = 0;
                $tools_arr = explode('fw-500', $AvailableTools);
                foreach ($tools_arr as $value) {
                    $isAvalibe = stripos($value, 'list-orange');
                    $isDeeplink = stripos($value, 'Deeplink');
                    if ($isAvalibe && $isDeeplink) {
                        $SupportDeepUrl = 1;
                    }
                }

                $commissionExt = array();
                $commStr = $this->oLinkFeed->ParseStringBy2Tag($DPresult, array('Commission Structure', '<ul', '>'), '</ul');
                $commArr = explode('</li>', $commStr);
                foreach ($commArr as $commV) {
                    $expStr = trim($commV);
                    if ($expStr) {
                        list($a, $b) = explode("\n", trim(strip_tags($commV), "\n"));
                        if ($IdInAff == 1222) {
                            $commissionExt[] = trim($b);
                        } else {
                            $commissionExt[] = trim($a);
                        }
                    }
                }

                $arr_prgm[$IdInAff] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    "IdInAff" => $IdInAff,
                    "Description" => addslashes($description),
                    "TermAndCondition" => addslashes($termAndCondition),
                    "SupportDeepUrl" => $SupportDeepUrl ? 'YES' : 'NO',
                    "TargetCountryExt" => addslashes($countryExt),
                    "CategoryExt" => addslashes($categoryExt),
                    "CommissionExt" => addslashes(join(',', $commissionExt)),
                	"SEMPolicyExt" => addslashes($SEMPolicyExt),
                    'CrawlJobId' => $this->info['crawlJobId'],
                );

                $program_num++;
                $base_program_num ++;
                if (count($arr_prgm) >= 100) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }
            }
            if (count($arr_prgm)) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                unset($arr_prgm);
            }
            echo "\tGet Program by page detail end\r\n";
        }

        echo "\tGet Program by page end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
        echo "\tSet program country int.\r\n";
    }

}