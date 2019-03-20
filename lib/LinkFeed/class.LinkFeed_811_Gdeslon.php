<?php
require_once 'text_parse_helper.php';
class LinkFeed_811_Gdeslon
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;

        $this->islogined = false;
    }

    function login($try = 6)
    {
        if ($this->islogined) {
            echo "verify succ: " . $this->info["AffLoginVerifyString"] . "\n";
            return true;
        }

        $this->oLinkFeed->clearHttpInfos($this->info['AccountSiteID']);//删除缓存文件，删除httpinfos[$aff_id]变量
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => 'get'
        );
        $r = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
        $token = $this->oLinkFeed->ParseStringBy2Tag($r['content'], array('name="csrf-token"', 'content="'), '"');
        $this->info['LoginPostString'] = '{"user_session":{"email":"'.trim($this->info['UserName']).'","password":"'.trim($this->info['Password']).'","remember_me":true}}';
        echo $this->info['LoginPostString'].PHP_EOL;
        echo $token.PHP_EOL;
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => $this->info["LoginMethod"],
            "postdata" => $this->info["LoginPostString"],
            "no_ssl_verifyhost" => false,
        	"addheader" => array("content-type: application/json;charset=UTF-8","x-csrf-token: {$token}"),
        	"referer" => "https://www.gdeslon.ru/login"
        );

        $arr = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
        if ($arr["code"] == 0) {
            if (preg_match("/^SSL: certificate subject name .*? does not match target host name/i", $arr["error_msg"])) {
                $request["no_ssl_verifyhost"] = 1;
                $arr = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
            }
        }

        if ($arr["code"] == 200) {
            if (stripos($arr["content"], $this->info["LoginVerifyString"]) !== false) {
                echo "verify succ: " . $this->info["LoginVerifyString"] . "\n";
                $this->islogined = true;
                return true;
            }
        }

        if (!$this->islogined) {
            if ($try < 0) {
                mydie("Failed to login!");
            } else {
                echo "login failed ... retry $try...\n";
                sleep(30);
                $this->login(--$try);
            }
        }
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
        $base_program_num = $program_num = 0;
        
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => 'get'
        );

        $programInfo = array();
        $pApiUrl = 'https://www.gdeslon.ru/api/users/shops.xml?api_token=' . $this->info['APIKey1'];
        $cacheName="data_" . date("YmdH") . "program_list_xml.dat";
        $shops = $this->oLinkFeed->GetHttpResultAndCache($pApiUrl, $request, $cacheName, 'gdeslon');
        $shops = json_decode(json_encode(simplexml_load_string($shops)),true);

        foreach ($shops['shops']['shop'] as $val){
            $programInfo[$val['id']]['country'] = $val['country'];
            $programInfo[$val['id']]['categories'] = isset($val['categories']['category']) ? $val['categories']['category'] : '';
            $programInfo[$val['id']]['commission'] = $val['gs-commission-mark'];
        }

        $r = $this->oLinkFeed->GetHttpResult('http://api.gdeslon.ru/merchants.json');
        $apiResponse = json_decode($r['content'], true);

        if (empty($apiResponse)){
            mydie("Can't get aff program list!");
        }

        $this->login();
        foreach ($apiResponse as $val){
            $idInAff = intval($val['_id']);

            echo "$idInAff\t";
            $name = $val['name'];

            $pDetailUrl = 'https://www.gdeslon.ru/users/aliexpress-vip-' . $idInAff;
            $cacheName="data_" . date("YmdH") . "detail_$idInAff.dat";
            $result = $this->oLinkFeed->GetHttpResultAndCache($pDetailUrl, $request, $cacheName);

            if (stripos($result, 'Страница не найдена!') !== false){
                continue;
            }

            $strPos = 0;
            $LogoUrl = $this->oLinkFeed->ParseStringBy2Tag($result, 'user-image-regular" src="', '"', $strPos);
            $homepage = $this->oLinkFeed->ParseStringBy2Tag($result, array("class='user-url", 'href="'), '"', $strPos);

            if (isset($programInfo[$idInAff])){
                $Partnership = 'Active';
            }else{
                $Partnership = 'NoPartnership';
            }

            if ($Partnership == 'Active'){
                $needJoin = $this->oLinkFeed->ParseStringBy2Tag($result, array('<fieldset','button class="'), '"', $strPos);
                if ($needJoin) {
                    if ($needJoin == 'join') {
                        $Partnership = 'NoPartnership';
                    } else {
                        mydie("Find new partnership!");
                    }
                }
            }
            
            $arr_prgm[$idInAff] = array(
            		'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
            		'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
            		'IdInAff' => $idInAff,
            		'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
            		'AffDefaultUrl' => "https://sf.gdeslon.ru/cf/{$this->info['APIKey1']}?mid={$idInAff}"
            );
            
            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idInAff, $this->info['crawlJobId']))
            {
            	$statusInAff = 'Active';
				
	            $commission = strip_tags($this->oLinkFeed->ParseStringBy2Tag($result, '<td>Комиссия</td>','</tr', $strPos));
	            $CookieTime = strip_tags($this->oLinkFeed->ParseStringBy2Tag($result, '<td>Время жизни куки</td>','</tr', $strPos));
	
	            $desc = $this->oLinkFeed->ParseStringBy2Tag($result, array('Описание','<div','>'),'</div', $strPos);
	            $TermAndCondition = $this->oLinkFeed->ParseStringBy2Tag($result, array('Условия','<div','>'),'</div', $strPos);
	
	            $category = '';
	            if (isset($programInfo[$idInAff]) && !empty($programInfo[$idInAff]['categories'])){
	                if (isset($programInfo[$idInAff]['categories']['name'])){
	                    $category = $programInfo[$idInAff]['categories']['name'];
	                }else {
	                    foreach ($programInfo[$idInAff]['categories'] as $cv) {
	                        $category .= $cv['name'] . EX_CATEGORY;
	                    }
	                    $category = rtrim($category, EX_CATEGORY);
	                }
	            }
	
	            $targetCountryExt = isset($programInfo[$idInAff]['country']) ? $programInfo[$idInAff]['country'] : '';
	
	            $arr_prgm[$idInAff] += array(
	                'CrawlJobId' => $this->info['crawlJobId'],
	                "Name" => addslashes($name),
	                "Description" => addslashes($desc),
	                "Homepage" => addslashes($homepage),
	                "StatusInAff" => $statusInAff,                        //'Active','TempOffline','Offline'
	                "CommissionExt" => addslashes(trim($commission)),
	                "TermAndCondition" => addslashes($TermAndCondition),
	                'TargetCountryExt' => addslashes(trim($targetCountryExt)),
	                'CategoryExt' => addslashes(trim($category)),
	                'LogoUrl' => addslashes($LogoUrl),
	                'CookieTime' => addslashes($CookieTime),
	                //'SupportDeepUrl' => 'YES'
	            );
	            $base_program_num ++;
            }
            $program_num ++;
            if (count($arr_prgm) >= 100) {
            	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            	$arr_prgm = array();
            }
            
        }

        if (count($arr_prgm)) {
        	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }
        echo "\tGet Program by api end\r\n";

        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }

}