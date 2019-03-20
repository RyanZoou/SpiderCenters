<?php
require_once 'text_parse_helper.php';
class LinkFeed_26_Affili_net
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;

        $this->API_USERNAME = $this->info['APIKey1'];
        $this->API_PASSWORD = $this->info['APIKey2'];
        $this->PRODUCT_PASSWORD = $this->info['APIKey3'];
        $this->idListLimit = $this->info['APIKey4'];

        $params = json_decode($this->info['APIKey5'], true);
        $this->ctgr_postdata_checked = $params['ctgr_postdata_checked'];
		$this->ctgr_postdata_event = $params['ctgr_postdata_event'];
		$this->ctgr_postdata_view = $params['ctgr_postdata_view'];
		$this->ctgr_postdata_view_origin = $params['ctgr_postdata_view_origin'];
		$this->ctgr_postdata_common1 = $params['ctgr_postdata_common1'];
		$this->ctgr_postdata_common2 = $params['ctgr_postdata_common2'];
		$this->ctgr_postdata_common3 = $params['ctgr_postdata_common3'];
		$this->ctgr_postdata_common4 = $params['ctgr_postdata_common4'];
        $this->ctgr_postdata_common5 = $params['ctgr_postdata_common5'];
    }

    function LoginIntoAffService()
    {
        //get para __VIEWSTATE and then process default login
        if(!isset($this->info["LoginPostStringOrig"])) {
            $this->info["LoginPostStringOrig"] = $this->info["LoginPostString"];
        }
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);
        $url = $this->info["LoginUrl"];
        $r = $this->oLinkFeed->GetHttpResult($url, $request);
        $content = $r["content"];
        $param = array(
            '__EVENTTARGET' => 'ctl00$body$btnLogin',
            '__EVENTARGUMENT' => '',
            '__VIEWSTATE' => '',
            '__VIEWSTATEGENERATOR' => '',
            '__EVENTVALIDATION' => '',
        );
        $keywords = array('__VIEWSTATE', '__VIEWSTATEGENERATOR', '__EVENTVALIDATION');
        foreach ($keywords as $keyword){
            if (preg_match(sprintf('@id="%s" value="(.*?)"@', $keyword), $content, $g)){
                $param[$keyword] = $g[1];
            }else{
                mydie("login failed: $keyword");
            }
        }
        $this->info["LoginPostString"] = http_build_query($param) . "&" . $this->info["LoginPostStringOrig"];
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info, 2, true, true, false);
        return "stophere";
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
        $this->LoginIntoAffService();
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get");

        /* <LIVE DATA> */
        define ("WSDL_LOGON", "https://api.affili.net/V2.0/Logon.svc?wsdl");
        define ("WSDL_PROG",  "https://api.affili.net/V2.0/PublisherProgram.svc?wsdl");

        $Username = $this->API_USERNAME; // the publisher ID
        $Password = $this->API_PASSWORD; // the publisher web services password

        $SOAP_LOGON = new SoapClient(WSDL_LOGON, array('trace'=> true));
        $Token = $SOAP_LOGON->Logon(array(
            'Username'  => $Username,
            'Password'  => $Password,
            'WebServiceType' => 'Publisher'
        ));

        $params = array('Query' => '');
        try {
            $SOAP_REQUEST = new SoapClient(WSDL_PROG, array('trace'=> true));

            $req = $SOAP_REQUEST->GetAllPrograms(array(
                'CredentialToken' => $Token,
                'GetProgramsRequestMessage' => $params
            ));

            foreach($req->Programs->ProgramSummary as $prgm) {
                $IdInAff = $prgm->ProgramId;

                if (!$IdInAff) continue;

                $Partnership = "NoPartnership";
                $StatusInAffRemark = $prgm->PartnershipStatus;
                if ($StatusInAffRemark == 'Active') {
                    $Partnership = 'Active';
                } elseif ($StatusInAffRemark == 'Declined') {
                    $Partnership = 'Declined';
                } elseif ($StatusInAffRemark == 'Waiting') {
                    $Partnership = 'Pending';
                } elseif ($StatusInAffRemark == 'Paused') {
                    $Partnership = 'Expired';
                } elseif ($StatusInAffRemark == 'NotApplied') {
                    $Partnership = 'NoPartnership';
                }
                $StatusInAff = 'Active';
                if ($this->info["AccountSiteID"] == 200 && $IdInAff == '12489') {
                    $Partnership = 'Active';
                    $StatusInAff = 'TempOffline';
                }

                $arr_prgm[$IdInAff] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $IdInAff,
                    'Partnership' => $Partnership,
                );


                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
                    $prgm_cagr[$IdInAff] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],
                        'BatchID' => $this->info['batchID'],
                        'IdInAff' => $IdInAff,
                        "CategoryExt" => ''
                    );

                    $CommissionExt = '
                PayPerSale: ' . $prgm->CommissionRates->PayPerSale->MinRate . ' - ' . $prgm->CommissionRates->PayPerSale->MaxRate . ',
                PayPerLead: ' . $prgm->CommissionRates->PayPerLead->MinRate . ' - ' . $prgm->CommissionRates->PayPerLead->MaxRate . ',
                PayPerClick: ' . $prgm->CommissionRates->PayPerClick->MinRate . ' - ' . $prgm->CommissionRates->PayPerClick->MaxRate . '
                ';

                    $homepage = $this->oLinkFeed->findFinalUrl($prgm->Url, array("nobody" => "unset"));
                    $sem_url = "http://publisher.affili.net/Programs/ProgramInfo.aspx?pid={$IdInAff}";
                    $sem_res = $this->oLinkFeed->GetHttpResult($sem_url,$request);
                    $sem_res = $sem_res['content'];
                    $needle = '<h2>
                <span id="ContentPlaceHolderContent_Frame1ContentWithNavigation_ctl01_lblSEM" class="txt_orange_bold">SEM Policy:</span>&nbsp;</h2>';
                    if (strpos($sem_res, $needle)){
                    	$SEMPolicyExt = trim($this->oLinkFeed->ParseStringBy2Tag($sem_res,'<a id="asem" name="sem"></a>',"</tr>"));
                    }else {
                    	$SEMPolicyExt = '';
                    }

                    $arr_prgm[$IdInAff] += array(
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Name" => addslashes($prgm->ProgramTitle),
                        "Homepage" => addslashes($homepage),                                //注意该项，迁移至老系统时该项需要需要特殊处理（因为有可能为空）
                        "Description" => addslashes($prgm->Description),
                        "TermAndCondition" => addslashes($prgm->Limitations),
                        "StatusInAffRemark" => addslashes($StatusInAffRemark),
                        "StatusInAff" => $StatusInAff,
                        "CommissionExt" => addslashes($CommissionExt),
                    	"SEMPolicyExt" => addslashes($SEMPolicyExt)
                    );
                    $base_program_num++;
                }

                $program_num++;
                if (count($arr_prgm) >= 100) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }
            }

        } catch( Exception $e ) {
            mydie("die: Api error.\n");
        }

        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }
        if ($this->isFull) {
            echo "\tSet program category int.\r\n";
            $this->getCategoryBypage($prgm_cagr,$objProgram);
        }

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

    function getCategoryBypage($prgm_cagr,$objProgram)
    {

        echo "\tGet Category by page start\r\n";

        $ctgr_arr = $this->getCategoryList();

        $father_cagr = '##############';
        $outside_prgm = array();
        $father_cagr_name = '';
        foreach ($ctgr_arr as $key => &$val)
        {

            if (strpos($val[0],$father_cagr) === false)
            {
                $father_cagr = $val[0];
                $father_cagr_name = $val[1] . ' > ';
                $ctgr_name = $val[1];
                $prgm_id_list = $this->getCtgrPrgmList($val);
                unset($val);
            } else
            {
                $ctgr_name = $father_cagr_name . $val[1];
                $prgm_id_list = $this->getCtgrPrgmList($val);
                unset($val);
            }

            if (empty($prgm_id_list)) continue;

            foreach ($prgm_id_list as $v)
            {
                if (key_exists($v,$prgm_cagr))
                {
                    if (empty($prgm_cagr[$v]['CategoryExt']) || strpos($ctgr_name,$prgm_cagr[$v]['CategoryExt']) !== false)
                        $prgm_cagr[$v]['CategoryExt'] = $ctgr_name;
                    else
                        $prgm_cagr[$v]['CategoryExt'] .= EX_CATEGORY.$ctgr_name;
                }else
                {
                    $outside_prgm[] = $v;
                }
            }
        }
        $noCtgrIdList = $this->rememberNoCategoryProgram($this->info['AccountSiteID'], $prgm_cagr);
        echo "\nThe programsId list of no category :" . join(',', $noCtgrIdList) . "\n";

        if (count($noCtgrIdList) > $this->idListLimit)
            mydie("\nToo many programs have no category! Id list :" . join(',', $noCtgrIdList) . "\n");

        // print_r($prgm_cagr);exit;

        $objProgram->InsertProgramBatch($this->info["NetworkID"], $prgm_cagr);
        unset($prgm_cagr);
    }

    function getCtgrPrgmList($ctgr_arr)
    {
        $prgm = array();
        $url = 'http://publisher.affili.net/Programs/ProgramSearch.aspx?nr=1&pnp=3';
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "post",
            "postdata" => $this->ctgr_postdata_common1
                . $this->ctgr_postdata_event . 'ctl00%24ctl00%24ContentPlaceHolderContent%24Frame1Content%24btnSearch'
                . $this->ctgr_postdata_common2
                . $this->ctgr_postdata_view . $this->ctgr_postdata_view_origin
                . $this->ctgr_postdata_common3
                . $this->ctgr_postdata_checked . $ctgr_arr['checked_value']
                . $this->ctgr_postdata_common4
        );
        $r = $this->oLinkFeed->GetHttpResult($url, $request);
        $result = $r['content'];
        $prgm_str = trim($this->oLinkFeed->ParseStringBy2Tag($result, 'Hide/Show column:', '</table>'));
        if (empty($prgm_str)) return $prgm;
        //echo $prgm_str;exit;
        preg_match_all('/ProgramInfo\.aspx\?pid=(\d+)["#]/',$prgm_str,$m);

        if (!isset($m[1]) || empty($m[1])) mydie("The result of program ID is empty when search category, please check the Regular expression");
        foreach (array_unique($m[1]) as $val)
            $prgm[] = $val;
        if (strpos($result,'ctl00$ctl00$ContentPlaceHolderContent$Frame1Content$ucPaging$ibForward') !== false)
        {
            $max_page = intval($this->oLinkFeed->ParseStringBy2Tag($result, array('ContentPlaceHolderContent_Frame1Content_ucPaging_lMaxPage','"maxPage">'), '</span>'));
            $pre_num = intval($this->oLinkFeed->ParseStringBy2Tag($result, array('ContentPlaceHolderContent_Frame1Content_ucPaging_ddlItemsPerPage','selected="selected" value="'), '"'));
            $viewstate = trim($this->oLinkFeed->ParseStringBy2Tag($result, '|__VIEWSTATE|', '|'));

            if (!$max_page || !is_numeric($max_page))
                mydie("Can't find the max page !");

            preg_match_all('/id="ContentPlaceHolderContent_Frame1Content_ucPaging_LinkButton(\d+)"/',$result,$page_link);
            if (!isset($page_link[1]) || empty($page_link[1])) mydie("Can't find the next page link !");

            for ($i = 1; $i <= count($page_link[1]); $i ++) //count($page_link[1])
            {
                $current_page_num = intval($this->oLinkFeed->ParseStringBy2Tag($result, 'id="ContentPlaceHolderContent_Frame1Content_ucPaging_HiddenCurrentPage" value="', '"'));

                $request['postdata'] = $this->ctgr_postdata_common5.$page_link[1][$i-1]
                    . $this->ctgr_postdata_event . 'ctl00%24ctl00%24ContentPlaceHolderContent%24Frame1Content%24ucPaging%24LinkButton'.$page_link[1][$i-1]
                    . $this->ctgr_postdata_common2
                    . $this->ctgr_postdata_view . urlencode($viewstate)
                    . $this->ctgr_postdata_common3
                    . $this->ctgr_postdata_checked . $ctgr_arr['checked_value']
                    . $this->ctgr_postdata_common4
                    . urlencode('ctl00$ctl00$ContentPlaceHolderContent$Frame1Content$ucPaging$ddlItemsPerPage') . "=$pre_num&"
                    . urlencode('ctl00$ctl00$ContentPlaceHolderContent$Frame1Content$ucPaging$HiddenCurrentPage') . "=$current_page_num&"
                    . urlencode('ctl00$ctl00$ContentPlaceHolderContent$Frame1Content$ucPaging$HiddenMaxPage') . "=$max_page";

                $r = $this->oLinkFeed->GetHttpResult($url, $request);
                $result = $r['content'];
                $prgm_str2 = trim($this->oLinkFeed->ParseStringBy2Tag($result, 'Hide/Show column:', '</table>'));

                /*if (empty($prgm_str)){
                    mydie("Result of {$ctgr_arr[1]} is null");
                }*/
                preg_match_all('/ProgramInfo\.aspx\?pid=(\d+)["#]/',$prgm_str2,$m);
                $idList = join(',',$m[1]);
                if (!isset($m[1]) || empty($m[1])) mydie("The result of program ID is empty when search category ,idList:'.$idList.', please check the Regular expression");

                foreach (array_unique($m[1]) as $val)
                    $prgm[] = $val;
            }
        }
        return $prgm;
    }

    function getCategoryList()
    {
        $category_list = array();
        $this->LoginIntoAffService();

        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);
        $url = 'http://publisher.affili.net/Programs/ProgramSearch.aspx?nr=1&pnp=3';

        $r = $this->oLinkFeed->GetHttpResult($url, $request);
        $content = $r['content'];
        $content = str_replace("\r", "\n", $content);
        $ctgr_str = $this->oLinkFeed->ParseStringBy2Tag($content, array('ContentPlaceHolderContent_Frame1Content_radCategoryTreeClientData','[['), ']]');
        try {
            $ctgr_arr = explode('],[',$ctgr_str);

            foreach ($ctgr_arr as $key => $val)
            {
                $ctgr_arr_son = explode(',',$val);
                foreach ($ctgr_arr_son as $k => $v)
                {
                    $vv = trim($v,"'");
                    if (!empty($vv) && !is_numeric($vv) && !in_array($vv,array('false','true','{}')))
                        $category_list[$key][] = $vv;
                }
            }
            for ($i = 0; $i < count($category_list); $i ++)
            {
                $check_val = '';
                for ($j = 0; $j < count($category_list); $j ++)
                {
                    if ($j == $i)
                        $check_val .= '1';
                    else
                        $check_val .= '0';
                }
                $category_list[$i]['checked_value'] = $check_val;
            }
        } catch (Exception $e) {
            mydie("Get category list failed : {$e->getMessage()} \n");
        }
        return $category_list;
    }

    function rememberNoCategoryProgram($AccountSiteID, $prgm_cagr)
    {
        if (!$AccountSiteID || empty($prgm_cagr))
            return false;

        $programIdList = array();

        foreach ($prgm_cagr as $val){
            if (empty($val['CategoryExt']))
                $programIdList[] = $val['IdInAff'];
        }
        $pIdList = join(',', $programIdList);

        $cache_file = $this->oLinkFeed->fileCacheGetFilePath($AccountSiteID, "AccountSiteID_{$AccountSiteID}_noCategoryProgramList.dat", 'categoryRemaindFile', true);
        if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {

            $this->oLinkFeed->fileCachePut($cache_file, $pIdList);
        }else {
            $result = file_get_contents($cache_file);
            $old_programIdList = explode(',', $result);
            if (count($programIdList) - count($old_programIdList) > 10) {
                mydie("More than 10 programs lose category, the new ListCount :".count($programIdList). "&newList :".$pIdList.",the oldListCount:".count($old_programIdList)."& oldList:".$result." please check the page!");
            }else {
                unlink($cache_file);
                $this->oLinkFeed->fileCachePut($cache_file, $pIdList);
            }
        }

        return $programIdList;
    }

}
