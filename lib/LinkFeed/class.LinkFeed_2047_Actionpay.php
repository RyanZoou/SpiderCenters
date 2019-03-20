<?php
include_once('text_parse_helper.php');
class LinkFeed_2047_Actionpay
{
    function __construct($aff_id,$oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;

        $this->countryExt = array(
            6537 => 'RU',
            6825 => 'RU',
            9398 => 'RU',
            2741 => 'RU',
            8056 => 'RU',
            9735 => 'RU',
            10415 => 'RU',
            10276 => 'RU',
            5712 => 'RU',
            9789 => 'RU',
            9926 => 'RU',
            7934 => 'RU',
            8035 => 'RU',
            8678 => 'RU',
            10955 => 'RU',
            8338 => 'RU',
            4554 => 'RU',
            9982 => 'RU',
            4213 => 'RU',
            8057 => 'RU',
            10303 => 'RU',
            10224 => 'RU',
            8719 => 'RU',
            3781 => 'RU',
            11074 => 'RU',
            6755 => 'RU',
            10449 => 'RU',
            11184 => 'RU',
            11166 => 'RU',
            11050 => 'RU',
            11049 => 'BY,RU',
            8611 => 'RU',
            8309 => 'RU',
            10522 => 'RU',
            3898 => 'RU',
            11154 => 'RU',
            4297 => 'RU',
            10756 => 'BR',
            10757 => 'BR',
            11023 => 'BR',
            10913 => 'BR',
            9752 => 'BR',
            10281 => 'BR',
            10808 => 'BR',
            9485 => 'BR',
            10738 => 'BR',
            8626 => 'BR',
        );
    }

    function GetProgramFromAff()
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
        $this->GetProgramByApi();
        echo "Craw Program end @ ".date("Y-m-d H:i:s")."\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }
    
    function login($retry=3) {
    	$request = array('AccountSiteID'=>$this->info['AccountSiteID'],'method'=>'get');
    	//第一次访问获取token
    	$token = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'].'login/',$request);
    	$token = $this->oLinkFeed->ParseStringBy2Tag($token['content'],"data['__ct'] = '","'");
    	$this->info['LoginPostString'] = str_replace("KKKKKK", $token, $this->info['LoginPostString']);
    	$lgoin_request = array('AccountSiteID'=>$this->info['AccountSiteID'],
    			'method'=>'post',
    			'addheader' => array('x-requested-with: XMLHttpRequest','content-type: application/x-www-form-urlencoded; charset=UTF-8'),
    			'postdata' => $this->info['LoginPostString'],
    			//'referer' => 'https://actionpay.net/ru-en/session/login/'
    	);
    	$result = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'],$lgoin_request);
    	if (strpos($result['content'] , $this->info['UserName'])){
    		echo "login succ".PHP_EOL;
    		return true;
    	}elseif (strpos($result['content'] , 'error')) {
    		echo "login failed , try again later(60s)".PHP_EOL;
    		sleep(60);
    		$retry--;
    		if ($retry > 0){
    			$this->login($retry);
    		}else {
    			mydie('login failed too many times');
    		}
    	}else{
    		mydie('network login operate have change , please check code');
    	}
    }

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $countryArr = $objProgram->getCountryCode();
        $arr_prgm = array();
        $base_program_num = 0;
        $program_num = 0;
        $request = array('AccountSiteID'=>$this->info['AccountSiteID'],'method'=>'get');
        
        //login first 
        $this->login();

        //Get all programs whose have partnership with Brandreward
        $avilableArr = array();
        $url = sprintf('https://api.actionpay.net/en/apiWmMyOffers/?key=%s&format=json', $this->info['APIKey1']);
        //$r = $this->GetHttpResult($url, $request, 'favouriteOffers', 'Active_program_data');
        $cacheName = "data_" . date("YmdH") . "_Active_program_data.dat";
        $r = $this->oLinkFeed->GetHttpResultAndCache($url,$request,$cacheName,'favouriteOffers');
        $data = json_decode($r, true);
        if (empty($data['result']['favouriteOffers'])) {
            mydie("Failed to get programs whose have partnership with Brandreward!");
        }
        foreach ($data['result']['favouriteOffers'] as $v){
            if ($v['available']){
                $avilableArr[] = $v['offer']['id'];
            }
        }


        //Get all programs and detail
        $page = 1;
        $hasNextPage = true;
        while ($hasNextPage){
            echo "page:$page\t";
            $url = sprintf('https://api.actionpay.net/en/apiWmOffers/?key=%s&format=json&page=%s', $this->info['APIKey1'], $page);
            $cacheName = "data_" . date("YmdH") . "_offers_page$page.dat";
            $r = $this->oLinkFeed->GetHttpResultAndCache($url,$request,$cacheName,'offers');
            $data = json_decode($r, true);
            if ($page >= $data['result']['pageCount']) {
                $hasNextPage = false;
            }

            if (empty($data['result']['offers'])) {
                continue;
            }

            foreach ($data['result']['offers'] as $val) {
                $idInAff = intval($val['id']);

                if (!$idInAff) {
                    continue;
                }

                $statusInRemark = $val['status']['name'];
                if ($statusInRemark == 'Active'){
                    $status = 'Active';
                }else{
                    mydie("Find new status({$val['status']['name']}) in api!");
                }
                
                //get Partnership and AffDefaultUrl
                $partnership = 'NoPartnership';
                if (in_array($idInAff, $avilableArr)) {
                	$partnership = 'Active';
                }
                $AffDefaultUrl = '';
                $secondId = '';
                if ($status == 'Active' && $partnership == 'Active'){
                	$url = sprintf('https://api.actionpay.net/en/apiWmLinks/?key=%s&format=json&offer=%s', $this->info['APIKey1'], $idInAff);
                	$cacheName = "data_" . date("YmdH") . "_links_$idInAff.dat";
                	$r = $this->oLinkFeed->GetHttpResultAndCache($url,$request,$cacheName,'links');
                	$data = json_decode($r, true);
                	$AffDefaultUrl = @$data['result']['links'][0]['url'];
                	$AffDefaultUrl = str_ireplace('subaccount', '[SUBTRACKING]', $AffDefaultUrl);
                
                	if ($val['deeplink']) {
                		$lastLink = end($data['result']['links']);
                		$deepUrl = $lastLink['url'];
                		if (stripos($deepUrl, '/url=') !== false) {
                			$secondId = $this->oLinkFeed->ParseStringBy2Tag($deepUrl, 'click/', '/');
                		}
                
                	}
                }
                
                $arr_prgm[$idInAff] = array(
                		'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                		'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                		'IdInAff' => $idInAff,
                		'Partnership' => $partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                		"AffDefaultUrl" => addslashes($AffDefaultUrl),
                );
                
                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idInAff, $this->info['crawlJobId']))
	            {
	
	                $countryExt = '';
	                if (is_array($val['geo']['includeCountries']) && !empty($val['geo']['includeCountries'])){
	                    $countryExt = join(',', $val['geo']['includeCountries']);
	                }elseif(is_array($val['geo']['excludeCountries']) && !empty($val['geo']['excludeCountries'])){
	                    $cArr = $countryArr;
	                    foreach ($val['geo']['excludeCountries'] as $ecv) {
	                        unset($cArr[$ecv]);
	                    }
	                    $countryExt = join(',', array_keys($cArr));
	                }elseif(is_array($val['geo']['includeCities']) && !empty($val['geo']['includeCities'])){
	                    if (isset($this->countryExt[$idInAff])){
	                        $countryExt = $this->countryExt[$idInAff];
	                    }else{
	                        $countryExt = 'RU';
	                    }
	                }elseif(is_array($val['geo']['excludeCities']) && !empty($val['geo']['excludeCities'])){
	                    mydie("Find new merchant(idInAff=$idInAff,name={$val['name']}) only disallow traffic of some city!");
	                }elseif($val['geoString'] == 'All countries'){
	                    $countryExt = 'Global';
	                }
	
	                $categoryExt = '';
	                if (is_array($val['categories']) && !empty($val['categories'])) {
	                    foreach ($val['categories'] as $cv) {
	                        $categoryExt .= $cv['name'] . EX_CATEGORY;
	                    }
	                    $categoryExt = rtrim($categoryExt, EX_CATEGORY);
	                }
	
	                $commissionExt = '';
	                if (is_array($val['aims']) && !empty($val['aims'])) {
	                    foreach ($val['aims'] as $cv) {
	                        $commissionExt .= $cv['price'] . ',';
	                    }
	                    $commissionExt = rtrim($commissionExt, ',');
	                }
	
	                $termAndCondition = 'deniedTrafficTypes: ';
	                if (!empty($val['deniedTrafficTypes'])) {
	                    foreach ($val['deniedTrafficTypes'] as $tv) {
	                        $termAndCondition .= $tv['name'] . ',';
	                    }
	                    $termAndCondition = rtrim($termAndCondition, ',');
	                }
	                $termAndCondition = $termAndCondition . ' ; trafficTypes: ';
	                if (!empty($val['trafficTypes'])) {
	                    foreach ($val['trafficTypes'] as $tv) {
	                        $termAndCondition .= $tv['name'] . ',';
	                    }
	                    $termAndCondition = rtrim($termAndCondition, ',');
	                }
	                
	                //SEM & SupportType
	                $SEMPolicyExt = '';
	                $SupportType = '';
	                $detailpage = $this->oLinkFeed->GetHttpResult('https://actionpay.net/ru-en/wmOffers/view/id:'.$idInAff , $request);
	                if (!stripos($detailpage['content'], $this->info['UserName'])) $this->login();
	                $policy = $this->oLinkFeed->ParseStringBy2Tag($detailpage['content'],array("<h5>Traffic types</h5>",'<table>'),'</table>');
	                $policylist = explode('</tr>', $policy);
	           		foreach ($policylist as $poli){
                    	if(stripos($poli, 'search')){
                    		$SEMPolicyExt .= trim($this->oLinkFeed->ParseStringBy2Tag($poli,array('<label','>'),'</label>'))." ";
                    		$SEMPolicyExt .= trim($this->oLinkFeed->ParseStringBy2Tag($poli,array('title','"'),'"'))."\n";
                    	}
                    	if (strpos($poli, 'Coupon') && strpos($poli, 'img/style/yes.png')){
                    		$SupportType = "Content".EX_CATEGORY."Coupon";
                    	}elseif (strpos($poli, 'Coupon') && !strpos($poli, 'img/style/yes.png')){
                    		$SupportType = "Content";
                    	}
                    }
	                $arr_prgm[$idInAff] += array(
	                	'CrawlJobId' => $this->info['crawlJobId'],
	                    'Name' => addslashes($val['name']),
	                    'Description' => addslashes($val['description']),
	                    'Homepage' => addslashes($val['link']),
	                    'CommissionExt' => addslashes($commissionExt),
	                    'CreateDate' => addslashes($val['createDate']),
	                    'StatusInAffRemark' => addslashes($statusInRemark),
	                    'StatusInAff' => $status,
	                    'SupportDeepUrl' => $val['deeplink'] ? 'YES' : 'NO',
	                    'TargetCountryExt' => addslashes($countryExt),
	                    'CategoryExt' => addslashes($categoryExt),
	                    'TermAndCondition' => addslashes($termAndCondition),
	                    'LogoUrl' => addslashes($val['logo']),
	                    'SecondIdInAff' => addslashes($secondId),
	                	'SEMPolicyExt' => addslashes($SEMPolicyExt),
	                	'SupportType' => $SupportType
	                );
	                $base_program_num++;
	            }
	            $program_num++;
	            if(count($arr_prgm) >= 100){
	            	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
	            	$arr_prgm = array();
	            }
            }
             $page ++;
        }

        if(count($arr_prgm)){
        	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }
        echo "\n\tGet Program by api end\r\n";

        if($program_num < 10){
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }
    
}