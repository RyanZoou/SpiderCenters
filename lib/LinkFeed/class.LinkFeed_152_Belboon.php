<?php
require_once 'text_parse_helper.php';
class LinkFeed_152_Belboon
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;

        $this->platformid = $this->info['APIKey1'];
        $this->config = array(
            'login' => $this->info['APIKey2'],
            'password' => $this->info['APIKey3'],
            'trace' => true
        );
    }

    function GetProgramFromAff()
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Program start @ {$check_date}\r\n";
        $this->isFull = $this->info['isFull'];
        $this->GetProgramByApi('','', $check_date);
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function RetryGetProgramFromAff($retry, $rePage, $check_date)
    {
        echo "retry craw Program start @ {$check_date}\r\n";
        $this->isFull = $this->info['isFull'];
        $this->GetProgramByApi($retry, $rePage, $check_date);
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function GetProgramByApi($retry = 5, $rePage = 0, $check_date)
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = $base_program_num = 0;

        $active_program = array();
        $activeInAff_program = array();

        $partnershipStatus = null;
        $client  = new SoapClient("http://api.belboon.com/?wsdl", $this->config);
        $page = $rePage;
        $hasNextPage = true;
        while($hasNextPage){
            $limit = 100;
            $start = $limit * $page;

            try{
                $result = $client->getPrograms(
                    $this->platformid,              // adPlatformId
                    null,                           // programLanguage
                    $partnershipStatus,             // partnershipStatus
                    null,                           // query
                    array('programid' => 'ASC'),    // orderBy
                    $limit,                         // limit
                    $start                          // offset
                );
            } catch( Exception $e ) {
                $retry-=1;
                if ($retry == 0)
                    mydie("die: Api error . $e\n");
                echo ("die: Api error . $e\n retry request the api {5-$retry}");
                $this->RetryGetProgramFromAff($retry, $page, $check_date);
                exit;
            }
            if(!count($result->handler->programs)){
                $hasNextPage = false;
                break;
            }
            if($page > 100){
                mydie("die: page max > 100.\n");
            }

            echo "page$page\t";

            //print_r($result);exit;
            foreach($result->handler->programs as $prgm){
                $strMerID = $prgm['programid'];

                if(!$strMerID || isset($active_program[$strMerID])) {
                    continue;
                }

                $Partnership = "NoPartnership";
                $StatusInAffRemark = $prgm['partnershipstatus'];
                if($StatusInAffRemark == 'PARTNERSHIP'){
                    $Partnership = 'Active';
                }elseif($StatusInAffRemark == 'REJECTED'){
                    $Partnership = 'Declined';
                }elseif($StatusInAffRemark == 'PENDING'){
                    $Partnership = 'Pending';
                }elseif($StatusInAffRemark == 'PAUSED'){
                    $Partnership = 'Expired';
                }elseif($StatusInAffRemark == 'AVAILABLE'){
                    $Partnership = 'NoPartnership';
                }

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $strMerID,
                    'Partnership' => $Partnership
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    if (!isset($activeInAff_program[$strMerID])) {
                        $activeInAff_program[$strMerID] = array(
                            'AccountSiteID' => $this->info["AccountSiteID"],
                            'BatchID' => $this->info['batchID'],
                            'IdInAff' => $strMerID,
                            "CategoryExt" => ''
                        );
                    }
                    if($Partnership == 'Active'){
                        $active_program[$strMerID] = $prgm['partnershipid'];
                    }

                        $CommissionExt = '
                                        commissionsaleminpercent: ' . $prgm['commissionsaleminpercent'] . ',
                                        commissionsalemaxpercent: ' . $prgm['commissionsalemaxpercent'] . ',
                                        commissionsaleminfix: ' . $prgm['commissionsaleminfix'] . ',
                                        commissionsalemaxfix: ' . $prgm['commissionsalemaxfix'] . ',
                                        commissionleadmin: ' . $prgm['commissionleadmin'] . ',
                                        commissionleadmax: ' . $prgm['commissionleadmax'] . ',
                                        commissionclickmin: ' . $prgm['commissionclickmin'] . ',
                                        commissionclickmax: ' . $prgm['commissionclickmax'] . ',
                                        commissionviewmin: ' . $prgm['commissionviewmin'] . ',
                                        commissionviewmax: ' . $prgm['commissionviewmax'];

                    $arr_prgm[$strMerID] += array(
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Name" => addslashes($prgm['programname']),
                        "Homepage" => addslashes($prgm['advertiserurl']),
                        "StatusInAffRemark" => addslashes($StatusInAffRemark),
                        "StatusInAff" => 'Active',
                        "Description" => addslashes($prgm['programdescription']),
                        "TermAndCondition" => addslashes($prgm['programterms']),
                        "DetailPage" => $prgm['programregisterurl'],
                        "CommissionExt" => addslashes($CommissionExt),
                        "LogoUrl" => addslashes($prgm['programlogo']),
                    );
                    $base_program_num ++;
                }

                $program_num++;
                if(count($arr_prgm) >= 100){
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }
            }
            $page++;
        }
        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }

        if ($this->isFull) {
            echo "\tget program country and SEMPolicy ext.\r\n";
            $this->getProgramCountryAndSEMByPage($active_program);

            echo "\tget program category ext.\r\n";
            $this->getProgramCategoryByPage($activeInAff_program, $objProgram);
        }

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";

    }

    function getProgramCountryAndSEMByPage($active_program)
    {
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "", );
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info,6);

        $objProgram = new ProgramDb();
        $arr_prgm = array();

        foreach($active_program as $strMerID => $partnershipid){
            $url = "https://ui.belboon.com/ShowPartnershipOverview,mid.43/DoHandlePartnership,id.$partnershipid,partnershipid.$partnershipid,programid.$strMerID.en.html";
            $r = $this->oLinkFeed->GetHttpResult($url, $request);
            $content = $r['content'];

            $SEMPolicyExt = trim($this->oLinkFeed->ParseStringBy2Tag($content,'<span style="font-style:italic;font-weight:bold;">','<div class="right" style="width: 282px;">'));
            $strLineStart = '<div id="content_tecdata" class="tabContentArea">';
            $nLineStart = stripos($content, $strLineStart);
            $CookieTime = trim($this->oLinkFeed->ParseStringBy2Tag($content, array('Cookie Lifetime (days):','valign="top">'), '</td>', $nLineStart));
            $TargetCountryExt = strip_tags($this->oLinkFeed->ParseStringBy2Tag($content, array('Trading area:','</strong>'), '<strong>', $nLineStart));
            $TargetCountryExt = preg_replace("/\s/", "", $TargetCountryExt);
            $PaymentDays = trim($this->oLinkFeed->ParseStringBy2Tag($content, array('Average processing time:</strong>','</strong>'), 'Day', $nLineStart));
            $arr_prgm[$strMerID] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $strMerID,
                "TargetCountryExt" => addslashes($TargetCountryExt),
                "CookieTime" => addslashes($CookieTime),
                "PaymentDays" => addslashes($PaymentDays),
            	"SEMPolicyExt" => addslashes($SEMPolicyExt)
            );
            if(count($arr_prgm) >= 100){
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
                $arr_prgm = array();
            }
        }
        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
            unset($arr_prgm);
        }
    }

    function getProgramCategoryByPage($activeInAff_program,$objProgram)
    {
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "", );
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info,6);

        $url = 'https://ui.belboon.com/ShowProgramListAffiliate,Mode.ProgramCatalog,platformid.605929,mid.40/DoHandleProgramListAffiliate.en.html';
        $r = $this->oLinkFeed->GetHttpResult($url, $request);
        $content = preg_replace("/>\\s+</i", "><", $r['content']);

        $strPosition = 0;
        $outside_program = array();

        while ($strPosition >= 0) {
            $strPosition = stripos($content, 'style="width:220px;padding:5px 3px;', $strPosition);
            if ($strPosition === false) break;

            $suffix_url = trim($this->oLinkFeed->ParseStringBy2Tag($content, array('style="width:220px;padding:5px 3px;','a href="'), '"', $strPosition));
            if (!$suffix_url) continue;

            $ctgr_url = "https://ui.belboon.com$suffix_url";

            preg_match("/catid\.(\d+),platformid/",$suffix_url,$m);

            $ctgr_name = trim($this->oLinkFeed->ParseStringBy2Tag($content, 'target="_self">', '</a>', $strPosition));

            if (!$ctgr_name) mydie("die: can't get catrgory name !");

            $ctgr_son = $this->oLinkFeed->GetHttpResult($ctgr_url, $request);
            $ctgr_son_page = preg_replace("/>\\s+</i", "><", $ctgr_son['content']);

            $sonStrPosition = 0;
            $more_ctgr = false;

            while ($sonStrPosition >= 0) {
                $suffix_url_son = trim($this->oLinkFeed->ParseStringBy2Tag($ctgr_son_page, array('style="width:220px;padding:5px 3px;','a href="'), '"', $sonStrPosition));

                if ($suffix_url_son) {
                    $ctgr_son_url = "https://ui.belboon.com$suffix_url_son";

                    preg_match("/catid\.(\d+),platformid/",$ctgr_son_url,$m_son);

                    $ctgr_son_name = trim($this->oLinkFeed->ParseStringBy2Tag($ctgr_son_page, 'target="_self">', '</a>', $sonStrPosition));

                    if (!$ctgr_son_name) mydie("die: can't get son catrgory name !");

                    $ctgr_ext_name = $ctgr_name . ' > ' . $ctgr_son_name;

                    while ($ctgr_son_url){
                        $m_url = array();
                        $ctgr_grandson = $this->oLinkFeed->GetHttpResult($ctgr_son_url, $request);
                        $ctgr_grandson_page = preg_replace("/>\\s+</i", "><", $ctgr_grandson['content']);

                        $ctgr_more_again = trim($this->oLinkFeed->ParseStringBy2Tag($ctgr_grandson_page, array('style="width:220px;padding:5px 3px;','a href="'), '"'));
                        if ($ctgr_more_again) mydie("There has program of category 4 classification !");

                        if (stripos($ctgr_grandson_page,'img src="https://ui.belboon.com/images/arrow_right.png') !== false){
                            preg_match('/\/a><a\s+href=\"(.+)\"><img\s+src=\"https:\/\/ui\.belboon\.com\/images\/arrow_right\.png/i',$ctgr_grandson_page,$m_url);
                            $ctgr_son_url = "https://ui.belboon.com{$m_url[1]}";
                        } else
                            $ctgr_son_url = false;

                        $match_start_pos1 = strpos($ctgr_grandson_page,'Select category');
                        preg_match_all('/https:\/\/ui\.belboon\.com\/images\/logos\/100\/logo_(\d+)\.gif"/U',$ctgr_grandson_page,$logo1_prId,PREG_PATTERN_ORDER,$match_start_pos1);
                        preg_match_all('/programid\.(\d+)[,\.]/U',$ctgr_grandson_page,$m1_prId,PREG_PATTERN_ORDER,$match_start_pos1);

                        $prgm1_id_list = array_unique(array_merge($logo1_prId[1],$m1_prId[1]));

                        foreach ($prgm1_id_list as $val){
                            if (isset($activeInAff_program[$val])) {
                                if (empty($activeInAff_program[$val]['CategoryExt']))
                                    $activeInAff_program[$val]['CategoryExt'] = addslashes($ctgr_ext_name);
                                else
                                    $activeInAff_program[$val]['CategoryExt'] = addslashes($activeInAff_program[$val]['CategoryExt'].EX_CATEGORY.$ctgr_ext_name);
                            }else{
                                if (!key_exists($val,$outside_program)){
                                    $logo_position = stripos($ctgr_grandson_page,"logo_{$val}.gif");
                                    $outside_program[$val] = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($ctgr_grandson_page, array('a href="','>'), '<',$logo_position)));
                                }
                            }
                        }
                    }
                    $more_ctgr = true;
                }

                if (!$more_ctgr){
                    while ($ctgr_url){
                        $m2_url = array();
                        $ctgr_son = $this->oLinkFeed->GetHttpResult($ctgr_url, $request);
                        $ctgr_son_page = preg_replace("/>\\s+</i", "><", $ctgr_son['content']);

                        if (stripos($ctgr_son_page,'img src="https://ui.belboon.com/images/arrow_right.png') !== false){
                            preg_match('/\/a><a\s+href=\"(.+)\"><img\s+src=\"https:\/\/ui\.belboon\.com\/images\/arrow_right\.png/i',$ctgr_son_page,$m2_url);
                            $ctgr_url = "https://ui.belboon.com{$m2_url[1]}";
                        } else
                            $ctgr_url = false;

                        $match_start_pos2 = strpos($ctgr_son_page,'Select category');
                        preg_match_all('/https:\/\/ui\.belboon\.com\/images\/logos\/100\/logo_(\d+)\.gif"/U',$ctgr_son_page,$logo2_prId,PREG_PATTERN_ORDER,$match_start_pos2);
                        preg_match_all('/programid\.(\d+)[,\.]/U',$ctgr_son_page,$m2_prId,PREG_PATTERN_ORDER,$match_start_pos2);
                        $prgm2_id_list = array_unique(array_merge($logo2_prId[1],$m2_prId[1]));

                        foreach ($prgm2_id_list as $v){
                            if (isset($activeInAff_program[$v])) {
                                if (empty($activeInAff_program[$v]['CategoryExt']))
                                    $activeInAff_program[$v]['CategoryExt'] = addslashes($ctgr_name);
                                else
                                    $activeInAff_program[$v]['CategoryExt'] = addslashes($activeInAff_program[$v]['CategoryExt'].EX_CATEGORY.$ctgr_name);
                            }else{
                                if (!key_exists($v,$outside_program)){
                                    $logo2_position = stripos($ctgr_grandson_page,"logo_{$v}.gif");
                                    $outside_program[$v] = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($ctgr_son_page, array('a href="','>'), '<',$logo2_position)));
                                }
                            }
                        }
                    }
                }
                if (stripos($ctgr_son_page,'style="width:220px;padding:5px 3px;', $sonStrPosition) === false) break;
            }
        }

        $objProgram->InsertProgramBatch($this->info["NetworkID"], $activeInAff_program);
        unset($activeInAff_program);

        if (!empty($outside_program)){
            echo "\tThe outside program list:\n";
            print_r($outside_program);
        }
    }
}
