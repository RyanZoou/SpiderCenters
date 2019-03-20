<?php
class LinkFeed_15_Zanox
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->soapClient = null;
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
        $arr_prgm = array();
        $program_num = $base_program_num = $page = $total = 0;
        $items = 50;

        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get");
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info,1,false);

        do {
            $url = sprintf("http://api.zanox.com/json/2011-03-01/programs?page=%s&connectid=" . $this->info['APIKey1'] . "&items=%s", $page, $items);
            $r = $this->oLinkFeed->GetHttpResult($url, $request);
            $data = @json_decode($r['content'], true);
            if (empty($total)) {
                $total = (int)$data['total'];
            }
            echo "\tpage: $page";

            foreach ((array)$data['programItems']['programItem'] as $prgm) {
                $strMerID = (int)$prgm['@id'];
                if (!$strMerID) {
                    continue;
                }

                $tmp_arr = $this->getProgramPartnershipById($strMerID);
                $Partnership = isset($tmp_arr['Partnership']) ? addslashes($tmp_arr['Partnership']) : 'NoPartnership';
                $tmp_arr = $this->getProgramDeepLinkById($strMerID);
                $AffDefaultUrl = isset($tmp_arr['AffDefaultUrl']) ? addslashes($tmp_arr['AffDefaultUrl']) : '';

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $strMerID,
                    'Partnership' => $Partnership,
                    'AffDefaultUrl' => $AffDefaultUrl,
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    $arr_prgm[$strMerID] += array(
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Name" => addslashes(html_entity_decode($prgm['name'])),
                        "RankInAff" => round($prgm['adrank']),
                        "StatusInAffRemark" => addslashes($prgm['status']),
                        "CookieTime" => intval(@$prgm['returnTimeSales']) / 86400,
                        "StatusInAff" => ucfirst(addslashes($prgm['status'])),                        //'Active','TempOffline','Offline'
                        "Description" => '',
                        "Homepage" => addslashes($prgm['url']),
                        "TermAndCondition" => '',
                        "SEMPolicyExt" => '',
                        "CategoryExt" => '',
                        "JoinDate" => isset($prgm['startDate']) ? date("Y-m-d H:i:s", strtotime($prgm['startDate'])) : "",
                        "TargetCountryExt" => '',
                        "LogoUrl" => addslashes($prgm['image']),
                        'SupportDeepUrl' => 'UNKNOWN',
                        'CommissionExt' => '',
                        'MobileFriendly' => 'UNKNOWN',
                    );

                    if (isset($prgm['policies']['policy']) && is_array($prgm['policies']['policy']))
                        $arr_prgm[$strMerID]['SEMPolicyExt'] = addslashes($this->key_implode(',', $prgm['policies']['policy'], '$'));
                    if (isset($prgm['industries']['main']) && is_array($prgm['industries']['main']))
                        $mainCate = addslashes($this->key_implode(',', $prgm['industries']['main'], '$'));
                    if (isset($prgm['industries']['sub']) && is_array($prgm['industries']['sub']))
                        $subCate = addslashes($this->key_implode(',', $prgm['industries']['sub'], '$'));
                    if (!empty($subCate))
                        $arr_prgm[$strMerID]['CategoryExt'] = $mainCate . '-' . $subCate;
                    else
                        $arr_prgm[$strMerID]['CategoryExt'] = $mainCate;
                    if (isset($prgm['regions']) && is_array($prgm['regions']))
                        $arr_prgm[$strMerID]['TargetCountryExt'] = $this->key_implode(',', $prgm['regions'], 'region');
                    if (isset($prgm['terms']))
                        $arr_prgm[$strMerID]['TermAndCondition'] = addslashes($prgm['terms']);
                    $desc = "";
                    if (isset($prgm['description']))
                        $desc = $prgm['description'];
                    if (isset($prgm['descriptionLocal'])) {
                        if (empty($desc))
                            $desc = "\r\r\r\r";
                        $desc .= $prgm['descriptionLocal'];
                    }
                    $arr_prgm[$strMerID]['Description'] = addslashes($desc);

                    if ($arr_prgm[$strMerID]['StatusInAff'] == 'Active') {
                        //get supportdeepurl and AffDefaultUrl
                        $tmp_arr = $this->getProgramDeepLinkById($strMerID);
                        if(isset($tmp_arr['SupportDeepUrl'])){
                            $arr_prgm[$strMerID]['SupportDeepUrl'] = addslashes($tmp_arr['SupportDeepUrl']);
                        }

                        //get CommissionExt
                        $commission_url = "https://marketplace.zanox.com/zanox/affiliate/{$this->info['APIKey3']}/{$this->info['APIKey4']}/merchant-profile/{$strMerID}/commission-groups/";
                        $cacheFileName = 'api_commission_' . $strMerID . '_' . date("YW").".dat";
                        $content = $this->oLinkFeed->GetHttpResultAndCache($commission_url, $request, $cacheFileName);
                        $comm_r = $this->oLinkFeed->ParseStringBy2Tag($content, 'Standard Commission Rate</h4>', '</div>');
                        if ($comm_r){
                            if (stripos($comm_r, 'No commission details') !== false)
                                $arr_prgm[$strMerID]['CommissionExt'] = '0';
                            else{
                                $comm_r = str_replace('</li><li>', ',', $comm_r);
                                $arr_prgm[$strMerID]['CommissionExt'] = addslashes(trim(strip_tags($comm_r)));
                            }
                        }

                        //get MobileFriendly
                        $mb_url = "https://marketplace.zanox.com/zanox/affiliate/{$this->info['APIKey3']}/{$this->info['APIKey4']}/merchant-profile/{$strMerID}";
                        $cacheFileName = 'api_MobileFriendly_' . $strMerID . '_' . date("YW").".dat";
                        $content = $this->oLinkFeed->GetHttpResultAndCache($mb_url, $request, $cacheFileName);
                        if (preg_match('@<h4>Optimized for Mobile</h4>\s+<div.*?>\s+(.*?)\s+</div>@', $content, $g)) {
                            if (strtoupper(trim($g[1])) == 'YES') {
                                $arr_prgm[$strMerID]['MobileFriendly'] = 'YES';
                            } else {
                                $arr_prgm[$strMerID]['MobileFriendly'] = 'NO';
                            }
                        }
                    }

                    $base_program_num++;
                }
                $program_num ++;

                if (count($arr_prgm) >= 100) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    $arr_prgm = array();
                }
            }
            if (count($arr_prgm) > 0) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }
            $page++;

        } while ($page < 1000 && $page * $items < $total);

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

    private function getSoapClient($force = false)
    {
        require_once INCLUDE_ROOT."wsdl/zanox-api_client/ApiClient.php";

        if (!is_object($this->soapClient) || $force)
        {
            $client = ApiClient::factory(PROTOCOL_SOAP);
            $client->setConnectId($this->info['APIKey1']);
            $client->setSecretKey($this->info['APIKey2']);
            $this->soapClient = $client;
        }
        return $this->soapClient;
    }

    function getProgramPartnershipById($idinaff)
    {
        $return_arr = array();
        $client = $this->getSoapClient();
        try{
            $return_obj = $client->getProgramApplications(null, $idinaff, null, 0, 10);
            if(isset($return_obj->total) && $return_obj->total > 0){
                foreach ($return_obj->programApplicationItems->programApplicationItem as $prgm) {
                    if(isset($prgm->program)){
                        $strMerID = intval($prgm->program->id);
                        if($strMerID != $idinaff) {
                            continue;
                        }

                        $CreateDate = isset($prgm->createDate) ? date("Y-m-d H:i:s", strtotime($prgm->createDate)) : "";
                        if($prgm->status == "confirmed"){
                            $Partnership = "Active";
                        }elseif($prgm->status == "open"){
                            $Partnership = "NoPartnership";
                        }elseif($prgm->status == "waiting"){
                            $Partnership = "Pending";
                        }elseif($prgm->status == "deferred"){
                            $Partnership = "Expired";
                        }elseif($prgm->status == "rejected"){
                            $Partnership = "Declined";
                        }elseif(in_array($prgm->status, array("closed", "blocked", "terminated", "canceled", "called", "deleted"))){
                            $Partnership = "NoPartnership";
                        }else{
                            $Partnership = "NoPartnership";
                        }

                        $return_arr = array("CreateDate" => $CreateDate, "Partnership" => $Partnership);

                        if($Partnership == "Active"){
                            break;
                        }
                    }
                }
            }
        }
        catch (Exception $e) {
            echo $e->getMessage()."\n";
        }
        return $return_arr;
    }

    function getProgramDeepLinkById($idinaff)
    {
        $return_arr = array();
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get");
        $url = sprintf("https://api.zanox.com/json/2011-03-01/admedia?program=%s&connectid={$this->info['APIKey1']}&admediumtype=text", $idinaff);
        $cacheFileName = 'api_deeplink_' . $idinaff . '_' . date("YW").".dat";
        $r = $this->oLinkFeed->GetHttpResultAndCache($url, $request, $cacheFileName);
        $data = @json_decode($r, true);

        if((int)$data['total'] > 1){
            foreach ((array)$data['admediumItems']['admediumItem'] as $prgm)
            {
                $strMerID = (int)$prgm['program']['@id'];
                if (!$strMerID || $strMerID != $idinaff)
                    continue;

                if(isset($prgm['trackingLinks']['trackingLink'])){
                    foreach($prgm['trackingLinks']['trackingLink'] as $v) {
                        if(isset($v['ppc']) && !empty($v['ppc'])){
                            $SupportDeepurl = 'YES';
                            $TrackingLink = $v['ppc'];
                            $TrackingLink = substr($TrackingLink, 0, stripos($TrackingLink, "&zpar9"));
                            $return_arr = array("SupportDeepUrl" => $SupportDeepurl, "AffDefaultUrl" => addslashes($TrackingLink));
                            break 2;
                        }
                    }
                }
            }
        }
        return $return_arr;
    }

    private function key_implode($glue, $array, $key)
    {
        $t = array();
        if (key_exists($key, $array) && !is_array($array[$key]))
            return $array[$key];
        foreach ($array as $v)
        {
            if (is_array($v) && key_exists($key, $v))
            {
                if (is_array($v[$key]))
                    $t[] = implode(',', $v[$key]);
                else
                    $t[] = $v[$key];
            }
        }
        return implode($glue, $t);
    }

}
