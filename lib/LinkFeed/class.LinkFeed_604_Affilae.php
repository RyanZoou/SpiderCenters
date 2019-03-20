<?php
class LinkFeed_604_Affilae
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->file = "programlog_{$aff_id}_" . date("Ymd_His") . ".csv";
    }

    function LoginIntoAffService()
    {
        $url = $this->info["LoginUrl"];
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "post",
            "postdata" => $this->info["LoginPostString"],
        );
        $arr = $this->oLinkFeed->GetHttpResult($url, $request);
        if ($this->info["LoginVerifyString"] && stripos($arr["content"], $this->info["LoginVerifyString"]) !== false) {
            echo "verify succ: " . $this->info["LoginVerifyString"] . "\n";
            return true;
        } else {
            print_r($arr);
            mydie("verify failed: " . $this->info["LoginVerifyString"] . "\n");
        }
        return false;

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
        $arr_prgm = array();
        $program_num = $base_program_num = 0;

        //1.login
        echo "Login...\r\n";
        $this->LoginIntoAffService();

        //2.get my program affDefaultUrl
        echo "get my program's AffDefaultUrl\r\n";

        $default_url = "https://app.affilae.com/en/publisher/{$this->info['APIKey1']}/partnerships";
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "get",
            "postdata" => ""
        );
        $default_r = $this->oLinkFeed->GetHttpResult($default_url, $request);
        $default_r = $default_r['content'];
        //print_r($default_r);exit;
        $LineStart = 0;
        $default_arr = array();
        $default_r = $this->oLinkFeed->ParseStringBy2Tag($default_r, '<tbody>', '</tbody>');
        while (1) {
            $per_program = $this->oLinkFeed->ParseStringBy2Tag($default_r, '<tr>', '</tr>', $LineStart);
            if (!$per_program)
                break;
            $affdefaulturl = $this->oLinkFeed->ParseStringBy2Tag($per_program, '<br><i>', '</i>');
            if (!$affdefaulturl)
                continue;
            $programID = $this->oLinkFeed->ParseStringBy2Tag($per_program, 'messages/contact/', '"');
            $default_arr[$programID] = html_entity_decode($affdefaulturl);
        }

        //3.get partnership
        echo "get Partnership\r\n";
        $partner_request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "get",
            "postdata" => "",
            "addheader" => array($this->info['APIKey4']),
        );
        $partner_url = "https://v3.affilae.com/publisher/partnerships.list?affiliateProfile={$this->info['APIKey1']}";
        $re = $this->oLinkFeed->GetHttpResult($partner_url, $partner_request);
        $result = json_decode($re['content'], true);
        //var_dump($result);exit;

        if ($result['statusCode'] != 200) {
            mydie("die: program partnership cann't crawled, please check the page");
        }

        $status = array();
        foreach ($result['partnerships']['data'] as $data) {
            if (!isset($status[$data['program']['id']]))
                $status[$data['program']['id']] = array(
                    'id' => $data['program']['id'],
                    'name' => $data['program']['name'],
                    'createdAt' => $data['createdAt'],
                    'status' => $data['status']
                );
            elseif ($status[$data['program']['id']]['createdAt'] < $data['createdAt'])
                $status[$data['program']['id']] = array(
                    'id' => $data['program']['id'],
                    'name' => $data['program']['name'],
                    'createdAt' => $data['createdAt'],
                    'status' => $data['status']
                );
            else
                continue;
        }

        //4.get program form marketplace
        echo "get program form marketplace\r\n";
        $hasNextPage = true;
        $offset = 0;
        $limit = 100;
        while ($hasNextPage) {
            $url = "https://v3.affilae.com/marketplace/programs.list?offset=$offset&limit=$limit";
            $re = $this->oLinkFeed->GetHttpResult($url);
            $re = json_decode($re['content'], true);
            //var_dump($re);exit;
            if ($re['statusCode'] != 200)
                mydie("Httpcode " . $re['statusCode'] . " error! Please check it.");

            $total = $re['count'];
            if ($total <= ($offset + 100))
                $hasNextPage = false;

            foreach ($re['programs']['data'] as $v) {
                $strMerID = $v['id'];
                $strMerName = $v['name'];

                //Partnership
                if (isset($status[$strMerID])) {
                    if ($status[$strMerID]['name'] != $strMerName) {
                        print_r($status[$strMerID]['name'] . ":" . $strMerName . "\r\n");
                        echo "Warning: programName Different from the json, IdInAff is $strMerID!\r\n";
                    }

                    $StatusInAffRemark = $status[$strMerID]['status'];
                    if ($StatusInAffRemark == 'pending') {
                        $Partnership = 'Pending';
                    } elseif ($StatusInAffRemark == 'active') {
                        $Partnership = 'Active';
                    } elseif ($StatusInAffRemark == 'refused by advertiser') {
                        $Partnership = 'Declined';
                    } elseif ($StatusInAffRemark == 'cancelled by advertiser') {
                        $Partnership = 'NoPartnership';
                    } elseif ($StatusInAffRemark == 'cancelled by publisher') {
                        $Partnership = 'NoPartnership';
                    } else {
                        mydie("New status appeared: $StatusInAffRemark");
                    }
                } else {
                    $Partnership = 'NoPartnership';
                }

                //AffDefaultUrl
                if (isset($default_arr[$strMerID])) {
                    $AffDefaultUrl = $default_arr[$strMerID];
                    preg_match('/[\d\D]*[#|?](ae={0,1}\d+)/', $AffDefaultUrl, $m);
                    $SecondIdInAff = @$m[1];
                } else {
                    $AffDefaultUrl = '';
                }

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                    'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                    'IdInAff' => $strMerID,
                    'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                    "AffDefaultUrl" => addslashes($AffDefaultUrl),
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {

                    //CategoryExt
                    $categories_arr = array();
                    foreach ($v['categories'] as $Category) {
                        $categories_arr[] = $Category['title_en'];
                    }
                    $CategoryExt = implode(EX_CATEGORY, $categories_arr);

                    //CommissionExt
                    $Commissions_arr = array();
                    foreach ($v['stats'] as $type => $commission) {
                        if ($commission['kind'] == 'percent') {
                            $Commissions_arr[] = $type . ':' . ($commission['value'] / 100) . '%';
                        } elseif ($commission['kind'] == 'fixed') {
                            $Commissions_arr[] = $type . ':' . ($commission['value'] / 100) . $commission['currency'];
                        } else {
                            if (!empty($commission['kind']))
                                $Commissions_arr[] = $type . ':' . ($commission['value'] / 100) . $commission['currency'];
                        }
                    }
                    $CommissionExt = implode('|', $Commissions_arr);

                    if (isset($v['createdAt'])) {
                        $CreateDate = date('Y-m-d H:i:s', $v['createdAt']);
                    } else {
                        $CreateDate = '';
                    }

                    //TargetCountryExt
                    if (isset($v['countries']) && !empty($v['countries'])) {
                        $TargetCountryExt = implode(',', $v['countries']);
                    } else {
                        $TargetCountryExt = '';
                    }

                    if (isset($v['description'])) {
                        $desc = trim($v['description']);
                    } else {
                        $desc = '';
                    }

                    if (isset($v['isActivated']) && $v['isActivated']) {
                        $StatusInAff = 'Active';
                    } else {
                        $StatusInAff = 'Offline';
                    }
                    
                    //SEMPolicyExt
                    if (isset($v['authorizeBuyKeywords'])){
                    	switch ($v['authorizeBuyKeywords']){
                    		case 'yes':
                    			$SEMPolicyExt = 'yes';
                    			break;
                    		case 'no':
                    			$SEMPolicyExt = 'no';
                    			break;
                    		case 'ask':
                    			$SEMPolicyExt = 'Agreement required';
                    			break;
                    		case '2':
                    			$SEMPolicyExt = 'Agreement required';
                    			break;
                    		case '1':
                    			$SEMPolicyExt = 'yes';
                    			break;
                    		case '0':
                    			$SEMPolicyExt = 'no';
                    			break;
                    		default:
                    			print_r($v);
                    			mydie('find new authorizeBuyKeywords');
                    	}
                    }else{
                    	$SEMPolicyExt = '';
                    }

                    $arr_prgm[$strMerID] += array(
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Name" => addslashes($strMerName),
                        "TargetCountryExt" => addslashes($TargetCountryExt),
                        "SecondIdInAff" => isset($SecondIdInAff) ? $SecondIdInAff : '',
                        "JoinDate" => $CreateDate,
                        "RankInAff" => isset($v['advertiserWeight']) ? $v['advertiserWeight'] : '',
                        "StatusInAff" => $StatusInAff,                        //'Active','TempOffline','Offline'
                        "Description" => addslashes($desc),
                        "Homepage" => isset($v['url']) ? addslashes($v['url']) : '',
                        "TermAndCondition" => isset($v['terms']) ? addslashes($v['terms']) : '',
                        "CommissionExt" => addslashes($CommissionExt),
                        "CategoryExt" => addslashes(trim($CategoryExt)),
                        "LogoUrl" => isset($v['logo']) ? addslashes($v['logo']) : '',
                    	"SEMPolicyExt" => addslashes($SEMPolicyExt)
                    );
                    $base_program_num ++;
                }

                $program_num++;
                if (count($arr_prgm) >= 100) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }
            }
            $offset += 100;
        }
        if (count($arr_prgm)) {
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
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