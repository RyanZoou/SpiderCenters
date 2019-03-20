<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_46_ClixGalore
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->category_list = array();
    }

    function Login()
    {
        $request = array("method" => "get", "postdata" => "", );

        $r = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'],$request);
        $result = $r["content"];
        $__EVENTVALIDATION = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__EVENTVALIDATION"', 'value="'), '"'));
        $__VIEWSTATE = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__VIEWSTATE"', 'value="'), '"'));
        $this->info["LoginPostString"] = "__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE={$__VIEWSTATE}&__EVENTVALIDATION={$__EVENTVALIDATION}&txt_UserName=".urlencode($this->info["UserName"])."&txt_Password=".urlencode($this->info["Password"])."&cmd_login.x=53&cmd_login.y=12";
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);
    }

    function GetProgramFromAff()
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Program start @ {$check_date}\r\n";
        $this->isFull = $this->info['isFull'];
        if ($this->isFull){
            $this->getProgramCategory();
        }
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

        //step 1,login
        $this->Login();

        echo "get program \r\n";
        $program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => "",);
        $prgm_records = array();

        $__EVENTTARGET = '';
        $objProgram = new ProgramDb();
        $dd_filter_arr = array( 0 => 'Active',1 => 'Offline', 2 => 'TempOffline');
        foreach($dd_filter_arr as $dd_filter => $Status){
        	echo "\r\n crawl Status {$Status} program";
            $strUrl = "http://www.clixgalore.com/AffiliateViewJoinRequests.aspx";
            $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
            $result = $r["content"];
            $hasNextPage = true;
            $page = 1;
            $arr_prgm = array();
            $try = 0;
            while($hasNextPage){
            	echo "\r\n page $page.";
//                 echo "\r\n page $page.";
                if(!empty($result)){
                    if($page == 1){
                        $__EVENTVALIDATION = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__EVENTVALIDATION"', 'value="'), '"'));
                        $__VIEWSTATE = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__VIEWSTATE"', 'value="'), '"'));
                        $request["postdata"] = '__VIEWSTATE='.$__VIEWSTATE.'&__EVENTVALIDATION='.$__EVENTVALIDATION.'&dd_RequestStatus=10&AffProgramDropDown1%24aff_program_list=0&dd_BannerType=0&dd_filter='.$dd_filter.'&cmd_report=Retrieve+Details';
                    }else{
                        $__EVENTVALIDATION = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__EVENTVALIDATION"', 'value="'), '"'));
                        $__VIEWSTATE = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__VIEWSTATE"', 'value="'), '"'));
                        $request["postdata"] = '__EVENTTARGET='.$__EVENTTARGET.'&__EVENTARGUMENT=&__VIEWSTATE='.$__VIEWSTATE.'&__EVENTVALIDATION='.$__EVENTVALIDATION.'&dd_RequestStatus=10&AffProgramDropDown1%24aff_program_list=0&dd_BannerType=0&dd_filter='.$dd_filter.'&txt_advsearch=';
                    }
                }else{
                    mydie("die: postdata error.\n");
                }

                $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
                if ($r['code'] != 200){
                	if ($try < 3){
                		sleep($try*10);
                		continue;
                	}else{
                		var_dump($r);
                		mydie("\r\n get page $page info error");
                	}
                }
                $result = $r["content"];
                $tmp_target = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('Pages Found:', "<span>$page</span>", '__doPostBack(\'dg_Merchants$ctl24$ctl'), "'"));
                if($tmp_target == false) $hasNextPage = false;
                $__EVENTTARGET = urlencode('dg_Merchants$ctl24$ctl'.$tmp_target);

                $strLineStart = 'class="StdLink" title="View Merchant Details"';

                $nLineStart = 0;
                while ($nLineStart >= 0){
                    $nLineStart = stripos($result, $strLineStart, $nLineStart);
                    if ($nLineStart === false) break;
                    //class
                    $StatusInAff = $Status;
                    if($Status == 'Active'){
                        $tmp_start = $nLineStart - 170;
                        $class = $this->oLinkFeed->ParseStringBy2Tag($result, '<tr class="', '"', $tmp_start);
                        if($class == 'lowbalanceMediumWarning'){//lowbalanceHighWarning
                            $StatusInAff = 'TempOffline';
                        }
                    }

                    //id
                    $strMerID = intval($this->oLinkFeed->ParseStringBy2Tag($result, "OpenDetails(", ")", $nLineStart));
                    if (!$strMerID) break;

                    if(isset($prgm_records[$strMerID])) {
                        continue;
                    }else {
                    	echo "\t".$strMerID;
                    }
                    //name
                    $strMerName = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($result, '>' , "</a>", $nLineStart)));
                    if ($strMerName === false) break;

                    $StatusInAffRemark = $this->oLinkFeed->ParseStringBy2Tag($result, array('<td align="center">','<td align="center">','<td align="center">','<td align="center">'), "</td>", $nLineStart);
                    if(stripos($StatusInAffRemark, 'Approved') != false){
                        $Partnership = 'Active';
                    }elseif(stripos($StatusInAffRemark, 'Pending') != false){
                        $Partnership = 'Pending';
                    }elseif(stripos($StatusInAffRemark, 'Declined') != false){
                        $Partnership = 'Declined';
                    }else{
                        mydie("die: unknown $strMerName partnership: $StatusInAffRemark.\n");
                    }
                    $prgm_records[$strMerID] = 1;

                    $arr_prgm[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                        'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                        'IdInAff' => $strMerID,
                        'Partnership' => $Partnership                         //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                    );

                    if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                        if ($Partnership == 'Active' && $StatusInAff == "Active") {
                            $arr_prgm[$strMerID] += $this->getProgramDetail($strMerID);
                        } else {
                            $arr_prgm[$strMerID] += array(
                                "Homepage" => '',
                                "CommissionExt" => '',
                                "Description" => '',
                                "CookieTime" => 0,
								"TargetCountryExt"=>'',
                                "SubAffPolicyExt" => '',
                                "LogoUrl" => '',
                                "PaymentDays" => 0
                            );
                        }
                        if ($StatusInAff == 'TempOffline') {
                            $StatusInAffRemark = 'Low Balance';
                        } elseif ($StatusInAff == 'Offline') {
                            $StatusInAffRemark = 'Inactive';
                        }
                        $arr_prgm[$strMerID] += array(
                            "Name" => addslashes(html_entity_decode(trim($strMerName))),
                            "StatusInAff" => $StatusInAff,                        //'Active','TempOffline','Offline'
                            "StatusInAffRemark" => $StatusInAffRemark,
                            "CategoryExt" => isset($this->category_list[$strMerID]['CategoryExt']) ? addslashes($this->category_list[$strMerID]['CategoryExt']) : '',
                            'CrawlJobId' => $this->info['crawlJobId'],
                        );
                        $base_program_num ++;
                    }

                    $program_num++;
                    if(count($arr_prgm)){
                        $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                        $arr_prgm = array();
                    }
                }
                $page++;
                $try = 3;
                if($page > 1000){
                    mydie("die: Page overload.\n");
                }
            }
            if(count($arr_prgm)){
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                unset($arr_prgm);
            }
        }

        echo "\tGet Program by page end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }

        echo "\tUpdate ({$base_program_num}) base programs." . PHP_EOL;
        echo "\tUpdate ({$program_num}) program.\r\n";

        if ($this->isFull) {
            $program_num = 0;

            /* $sql = "select a.IdInAff from batch_program_46 a inner join batch_program_account_site_46 b on a.IdInAff=b.IdInAff and a.BatchID=b.BatchID where b.AccountSiteID='{$this->info['AccountSiteID']}' and b.BatchID='{$this->info['batchID']}' and b.Partnership='Active' and a.StatusInAff='Active'";
            $prgm_ids = $objProgram->objMysql->getRows($sql, 'IdInAff');
            $prgm_ids = array_keys($prgm_ids); */

            echo "get program from program management adv\r\n";
            $tmp_request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get");
            $__EVENTTARGET = '';
            $strUrl = "http://www.clixgalore.com/AffiliateNotificationReport.aspx";
            $result = "";
            $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);
            $hasNextPage = true;
            $page = 1;
            while ($hasNextPage) {
                echo "\r\n page $page \r\n";
                if (!empty($result)) {
                    $__EVENTVALIDATION = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__EVENTVALIDATION"', 'value="'), '"'));
                    $__VIEWSTATE = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__VIEWSTATE"', 'value="'), '"'));
                    $request["method"] = "post";
                    $request["postdata"] = '__EVENTTARGET=' . $__EVENTTARGET . '&__EVENTARGUMENT=&__LASTFOCUS=&__VIEWSTATE=' . $__VIEWSTATE . '&__EVENTVALIDATION=' . $__EVENTVALIDATION . '&AffProgramDropDown1%24aff_program_list=' . $this->info['APIKey1'] . '&txt_advsearch=';
                } elseif ($page != 1) {
                    mydie("die: postdata error.\n");
                }
                $r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
                $result = $r["content"];
                $tmp_target = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('Pages Found:', "<span>$page</span>", '__doPostBack(\'dg_Merchants$ctl44$ctl'), "'"));
                if ($tmp_target == false) $hasNextPage = false;
                $__EVENTTARGET = urlencode('dg_Merchants$ctl44$ctl' . $tmp_target);

                $strLineStart = 'class="StdLink" title="View Merchant Details"';
                $nLineStart = 0;
                $nLineStart = stripos($result, $strLineStart, $nLineStart);
                while ($nLineStart >= 0) {
                    $nLineStart = stripos($result, $strLineStart, $nLineStart);
                    if ($nLineStart === false) {
                        break;
                    }
                    $strMerID = intval($this->oLinkFeed->ParseStringBy2Tag($result, "OpenDetails(", ")", $nLineStart));
                    if (!$strMerID) {
                        break;
                    }
                    $strMerName = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($result, '>', "</a>", $nLineStart)));
                    if ($strMerName === false) {
                        break;
                    }
                    $tmpStr = trim($this->oLinkFeed->ParseStringBy2Tag($result, 'NAME="Label1">', "</span>", $nLineStart));
                    if (preg_match("/[A-Z]{2}/", $tmpStr,$matchs)){
                    	$TargetCountryExt = $matchs[0];
                    }else{
                    	$TargetCountryExt = "";
                    }
                    preg_match("/\d.*/", $tmpStr,$matchs);
                    $EPC30d = $matchs[0];
                    $TermAndCondition = "";
                    $tmpStr = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('javascript:TermsCondition', '>'), "</a>", $nLineStart));
                    if ($tmpStr == 'View T&C') {
                        $tc_url = "http://www.clixgalore.com/popup_ViewMerchantTC.aspx?ID=$strMerID";
                        $tc_arr = $this->oLinkFeed->GetHttpResult($tc_url, $tmp_request);
                        $tc_detail = $tc_arr["content"];
                        $TermAndCondition = trim($this->oLinkFeed->ParseStringBy2Tag($tc_detail, array('<textarea name="txt_tc"', '>'), "</textarea>"));
                    }
                    
                    $arr_prgm[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                        'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                        'IdInAff' => $strMerID,
                        "Name" => addslashes(html_entity_decode(trim($strMerName))),
                        "TargetCountryExt" => $TargetCountryExt,
                        "EPC30d" => $EPC30d,
                        "TermAndCondition" => addslashes($TermAndCondition),
                    );
//					if ($arr_prgm[$strMerID]['EPC30d']=="\xAC0.00"||"\xAC1.00"==$arr_prgm[$strMerID]['EPC30d']){
//						$arr_prgm[$strMerID]['EPC30d']='';
//					}

                    $program_num++;
                    if (count($arr_prgm) >= 100) {
                        $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
                        $arr_prgm = array();
                    }
                    
                }
                $page++;
                if ($page > 100) {
                    mydie("die: Page overload.\n");
                }
            }

            if (count($arr_prgm)) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
                unset($arr_prgm);
            }
        }
        echo "\tUpdate ({$program_num}) program from program management adv.\r\n";
    }

    function getProgramCategory()
    {
        $this->login();
        echo "\tGet Program category start @ ".date("Y-m-d H:i:s")."\r\n";
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "post", "postdata" => "", 'SSLV'=> 3);
        $results = '';

        $page = 1;
        $hasNextPage = true;
        $__EVENTTARGET = '';
        while ($hasNextPage) {
            echo "\t page $page.";

            if ($page == 1) {
                $request['method'] = 'get';
            } else {
                $request['method'] = 'post';
                $__EVENTVALIDATION = urlencode($this->oLinkFeed->ParseStringBy2Tag($results, array('name="__EVENTVALIDATION"', 'value="'), '"'));
                $__VIEWSTATE = urlencode($this->oLinkFeed->ParseStringBy2Tag($results, array('name="__VIEWSTATE"', 'value="'), '"'));
                $request["postdata"] = "__EVENTTARGET={$__EVENTTARGET}&__EVENTARGUMENT=&__VIEWSTATE={$__VIEWSTATE}&__EVENTVALIDATION={$__EVENTVALIDATION}&AffProgramdropdown1%24aff_program_list={$this->info['APIKey1']}&dd_category=0&txt_advsearch=";
            }

            $results = $this->oLinkFeed->GetHttpResult('http://www.clixgalore.com/AffiliateMerchantCategoryAnalysis.aspx', $request);
            $results = $results['content'];
            if (strpos($results, 'clixGalore - Category Analysis') === false) {
                mydie("Can't get category page!");
            }

            $strLineStart = "javascript:window.status='View Merchant Details'";

            $nLineStart = 0;
            while ($nLineStart >= 0) {
                $nLineStart = stripos($results, $strLineStart, $nLineStart);
                if ($nLineStart === false) {
                    break;
                }

                $strMerID = intval($this->oLinkFeed->ParseStringBy2Tag($results, 'href="javascript:DisplayMerchant(', ')', $nLineStart));
                $ctgr_arr = array();
                $ctgr_arr[] = trim($this->oLinkFeed->ParseStringBy2Tag($results, array('<td','<td','<td','<td','>'), '<', $nLineStart));
                $ctgr_arr[] = trim($this->oLinkFeed->ParseStringBy2Tag($results, array('<td','>'), '<', $nLineStart));
                $ctgr_arr[] = trim($this->oLinkFeed->ParseStringBy2Tag($results, array('<td','>'), '<', $nLineStart));
                $ctgr_arr[] = trim($this->oLinkFeed->ParseStringBy2Tag($results, array('<td','>'), '<', $nLineStart));
                foreach ($ctgr_arr as $key => $ctVal) {
                    if ($ctVal == '&nbsp;') {
                        unset($ctgr_arr[$key]);
                    }
                }
                $this->category_list[$strMerID]['CategoryExt'] = join(EX_CATEGORY, $ctgr_arr);
            }

            $tmp_target = trim($this->oLinkFeed->ParseStringBy2Tag($results, array('Pages Found:', "<span>$page</span>", 'href="javascript:__doPostBack(\'dg_Merchants$ctl24$ct'), "'"));
            if ($tmp_target == false) {
                $hasNextPage = false;
                break;
            } else {
                $__EVENTTARGET = urlencode('dg_Merchants$ctl24$ct' . $tmp_target);
                $page++;
            }
        }
        echo "\n\tGet Program category end @ ".date("Y-m-d H:i:s")."\r\n";
    }

    function getProgramDetail($idInAff)
    {
        $tmp_request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get");
        $prgm_url = "http://www.clixgalore.com/PopupMerchantDetails.aspx?ID=".$idInAff;
        $prgm_arr = $this->oLinkFeed->GetHttpResult($prgm_url, $tmp_request);
        $prgm_detail = $prgm_arr["content"];
        $LogoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<img id="small_image" src="', '"'));
		/* $tmpStr = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, 'lbl_Currency">', '</span>'));
		$TargetCountryExt = substr($tmpStr, 0, 2);
		if ($TargetCountryExt=="\xE2\x82"){
			$TargetCountryExt='EU';
		} */
        if (stripos('http://www.clixGalore.com/images/merchant/', $LogoUrl) == false)
            $LogoUrl = '';
        $CookieTime = intval(trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, 'id="lbl_cookie_expiry">', '</span>')));
        if (empty($CookieTime) || $CookieTime == 'N/A' || !is_int($CookieTime)) $CookieTime = 0;
        $PaymentDays = intval(trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<span id="lbl_approve_after">After', 'day')));
        if (empty($PaymentDays) || !is_int($PaymentDays)) $PaymentDays = 0;
        $Homepage = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('StdLink" href="'), '"'));
        $CommissionExt = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, 'id="lbl_commission_rate">', '</span>'));
        $desc = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, 'id="lbl_description">', '</span>'));

        $SubAffPolicyExt = "";
        $lbl_traffic = strip_tags($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, 'id="lbl_traffic">', '</span>'));
        if($lbl_traffic){
            $SubAffPolicyExt = "Not Accepting Traffic From: " . $lbl_traffic;
        }

        $program_info = array(
            "Homepage" => addslashes($Homepage),
            "CommissionExt" => addslashes($CommissionExt),
            "Description" => addslashes($desc),
            "CookieTime" => $CookieTime,
// 			"TargetCountryExt" => addslashes($TargetCountryExt),
            "SubAffPolicyExt" => addslashes($SubAffPolicyExt),
            "LogoUrl" => addslashes($LogoUrl),
            "PaymentDays" => $PaymentDays
        );
        return $program_info;
    }

}
