<?php
include_once('text_parse_helper.php');
class LinkFeed_2075_Yiqifa
{
    function __construct($aff_id,$oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;

        $this->commission_patterns = array(
            '@销售额的@',
            '@每个行为数@',
            '@每个订单@',
            '@元@'
        );
    }

    function GetProgramFromAff()
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Program start @ {$check_date}\r\n";
        $this->isFull = $this->info['isFull'];
        $this->GtProgramByApi();
        echo "Craw Program end @ ".date("Y-m-d H:i:s")."\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function GtProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $base_program_num = $program_num = 0;

        $request = array('AccountSiteID'=>$this->info['AccountSiteID'],'method'=>'get',);

        $page = 1;
        $prePage = 100;
        $hasnNextPage = true;
        while ($hasnNextPage) {
            echo "page.$page\t";

            $strUrl = "http://o.yiqifa.com/servlet/interface?method=yiqifa.campaign.list.get&app_key={$this->info['APIKey2']}&app_secret={$this->info['APIKey3']}&pageIndex={$page}&pageSize={$prePage}";
            $cacheName="data_" . date("YmdH") . "_offer_list_page_$page.dat";
            $result = $this->oLinkFeed->GetHttpResultAndCache($strUrl,$request,$cacheName,'campaignName');
            
            $result = iconv("GBK","UTF-8",$result);
            $result = json_decode($result, true);
            if (!isset($result['result']['data']) || empty($result['result']['data'])) {
                mydie("Failed to get offer list from api.");
            }
            
            $totalNum = intval($result['result']['pageInfo']['total']);
            if ($page * $prePage >= $totalNum){
                $hasnNextPage = false;
            } else {
                $page ++;
            }
            
            foreach ($result['result']['data'] as $val) {
                $idInAff = intval($val['campaignId']);
                if (!$idInAff) {
                    continue;
                }
                
                $affDufaultUrl = "https://p.gouwubang.com/c?w={$this->info['APIKey4']}&c={$idInAff}&pf=m";
                if (strcmp($val['auditingType'], '无需审核') != 0){
                	$checkDfUrlResult = $this->oLinkFeed->findFinalUrl($affDufaultUrl);
                	if (stripos($checkDfUrlResult, 'p.gouwubang.com/error-pages/default.html?errortype=2') !== false) {
                		$partnership = 'Pending';
                	} elseif (stripos($checkDfUrlResult, 'p.gouwubang.com/l?l=') !== false) {
                		$partnership = 'Active';
                	}else{
                		echo $checkDfUrlResult . "\r\n";
                		mydie("The way how to deeplink have been changed by network!");
                	}
                } else {
                	$partnership = 'Active';
                }
                
                $arr_prgm[$idInAff] = array(
                		'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                		'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                		'IdInAff' => $idInAff,
                		'Partnership' => $partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                		"AffDefaultUrl" => addslashes($affDufaultUrl),
                );
                
                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idInAff, $this->info['crawlJobId']))
                {

	                $name = trim($val['campaignName']);
	                $desc = $val['introduction'];
	                $homepage = trim($val['logoUrl']);
	                $category = $val['label'];
	                $country = $val['country'];
	                $term = $val['conditions'];
	                $cookie = intval($val['timeToConfirmEffct']);
	                $logoUrl = $val['logoPath'];
	                $SEMPolicyExt = "";
	                if (stripos($val['conditions'],"SEM") !== false){
	                	$cdt_arr = explode('</p>',$val['conditions']);
	                	foreach ($cdt_arr as $cdt){
	                		if (stripos($cdt,"SEM") !== false) $SEMPolicyExt .= $cdt;
	                	}
	                	$SEMPolicyExt = strip_tags($SEMPolicyExt);
	                }else{
	                	$SEMPolicyExt = "UNKNOW";
	                }
	
	                $arr_prgm[$idInAff] += array(
	                	'CrawlJobId' => $this->info['crawlJobId'],
	                    'Name' => addslashes($name),
	                    'Description' => addslashes($desc),
	                    'Homepage' => addslashes($homepage),
	                    'StatusInAff' => 'Active',						    //'Active','TempOffline','Offline'
	                    'TermAndCondition' => addslashes($term),
	                    //'SupportDeepUrl' => 'YES',
	                    'CookieTime' => $cookie,
	                    'CategoryExt' => addslashes($category),
	                    'TargetCountryExt' => addslashes($country),
	                    'LogoUrl' => addslashes($logoUrl),
	                	"SEMPolicyExt" => addslashes($SEMPolicyExt)
	                );
	
	                //get commission
	                $commissionUrl = "http://o.yiqifa.com/servlet/interface?method=yiqifa.commission.list.get&app_key={$this->info['APIKey2']}&app_secret={$this->info['APIKey3']}&campaignId={$idInAff}&siteId={$this->info['APIKey4']}";
	                $comCacheName="data_" . date("YmdH") . "_commission_page_$idInAff.dat";
	                $comResult = $this->oLinkFeed->GetHttpResultAndCache($commissionUrl,$request,$comCacheName,'code":0');
	                $comResult = iconv("GBK","UTF-8", $comResult);
	                $comResult = json_decode($comResult, true);
	                if (isset($comResult['result']['data']) && !empty($comResult['result']['data'])) {
	                    $comm_arr = array('siteId' => array(),'nositeID' => array());
	                    $commisson = '';
	                    foreach ($comResult['result']['data'] as $acv){
	                        $date_now = date('Y-m-d');
	                        if ($date_now <= $acv['endDate'] && $date_now >= $acv['startDate']) {
	                            isset($acv['websiteId']) ? $comm_arr['siteId'][] = $acv : $comm_arr['nositeID'][] = $acv;
	                        }
	                    }
	                    empty($comm_arr['siteId']) ? $comm_arr = $comm_arr['nositeID'] : $comm_arr = $comm_arr['siteId'];
	                    foreach ($comm_arr as $cv){
	                        $commissionTxt = preg_replace($this->commission_patterns, '', $cv['commission']);
	                        if (stripos($commissionTxt, '%') === false){
	                            $commissionTxt = 'RMB ' . $commissionTxt;
	                        }
	                        $commisson .= $commissionTxt . ',';
	                    }
	                    $commisson = rtrim($commisson, ',');
	
	                    if ($commisson) {
	                        $arr_prgm[$idInAff]['CommissionExt'] = addslashes($commisson);
	                    }
	                }
	                $base_program_num++;
                }
                $program_num++;
                if(count($arr_prgm) >= 1){
                	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                	$arr_prgm = array();
                }
            }
        }

        if(count($arr_prgm)){
        	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }
        echo "\r\n\tGet Program by api end\r\n";

        if($program_num < 10){
            mydie("die: program count < 10, please check program.\n");
        }

        echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }

}