<?php
include_once('text_parse_helper.php');
class LinkFeed_2073_Affiliates_TW
{
    function __construct($aff_id,$oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;

        $this->Affiliate_ID = '8109';

        $this->islogined = false;
    }

    function Login()
    {
        if ($this->islogined) return $this->islogined;

        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);
        $result = $this->oLinkFeed->GetHttpResult("https://aff.affiliates.com.tw/affiliates/login", $request);
        $content = $result['content'];
        $token = $this->oLinkFeed->ParseStringBy2Tag($content, array('name="authenticity_token', 'value="'), '"');
        $this->info["LoginPostString"] = 'utf8=%E2%9C%93&authenticity_token='.urlencode($token).'&affiliate%5Blogin_info%5D='.urlencode(trim($this->info['UserName'])).'&affiliate%5Bpassword%5D='.urlencode(trim($this->info['Password']));
        $this->info["referer"] = 'https://aff.affiliates.com.tw/affiliates/login';
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => $this->info["LoginMethod"],
            "postdata" => $this->info["LoginPostString"],
            "no_ssl_verifyhost" => true,
        );

        if (isset($this->info["referer"])) $request["referer"] = $this->info["referer"];
        $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
        $arr = $this->oLinkFeed->GetHttpResult("https://aff.affiliates.com.tw/affiliates", array("AccountSiteID" => $this->info["AccountSiteID"],"method" => 'get'));

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
            }
            //handle redir by meta tag
            if (!$this->islogined && stripos($arr["content"], "REFRESH") !== false && isset($this->info["LoginSuccUrl"]) && $this->info["LoginSuccUrl"]) {
                $url_path = @parse_url($this->info["LoginSuccUrl"], PHP_URL_PATH);//parse_url用于解析url，返回一个关联数组。parse_url("xxx", PHP_URL_PATH)返回数组的path值
                if ($url_path && stripos($arr["content"], $url_path) !== false) {
                    echo "good, verify succ (redir by meta tag) <br>\n";
                    $this->islogined = true;
                }
            }
        }

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
        $program_num = 0;
        $base_program_num = 0;

        $this->Login();

        $request = array('AccountSiteID'=>$this->info['AccountSiteID'],'method'=>'get',);
        $requestPost = array('AccountSiteID'=>$this->info['AccountSiteID'],'method'=>'post',"no_ssl_verifyhost" => true);

        $page = 1;
        $prePage = 100;
        $hasnNextPage = true;
        while ($hasnNextPage) {
            echo "page.$page\t";

            $strUrl = "http://api.affiliates.com.tw/api/v1/affiliates/offers.json?api_key={$this->info['APIKey1']}&per_page=$prePage&page=$page";
            $cacheName = "data_" . date("YmdH") . "_offer_list_page_$page.dat";
            $result = $this->oLinkFeed->GetHttpResultAndCache($strUrl,$request,$cacheName,'offers');
            $result = @json_decode($result, true);
            if (!isset($result['data']['offers']) || empty($result['data']['offers'])) {
                mydie("Failed to get offer list from api.");
            }

            $current_count = count($result['data']['offers']);
            if ($current_count < $prePage){
            	$hasnNextPage = false;
            } else {
            	$page ++;
            }

            foreach ($result['data']['offers'] as $val) {
                $detailResult = '';
                $idInAff = intval($val['id']);
                if (!$idInAff) {
                    continue;
                }
                
                switch ($val['status']) {
                	case 'Active':
                	case '使用中':
                		$Partnership = 'Active';
                		break;
                	case 'Apply to Run':
                	case '申請推廣':
                		$Partnership = 'NoPartnership';
                		break;
                	case 'Confirming':
                		$Partnership = 'Pending';
                		break;
                	case 'Pending':
                	case '確認中':
					case '審核中':
                		$Partnership = 'Pending';
                		break;
                	case 'Paused':
                	case '已暫停':
                		$Partnership = 'Declined';
                		break;
                	default:
                		echo $idInAff.PHP_EOL;
                		mydie("New approval_status appeared: {$val['status']} ");
                		break;
                }

                $affDufaultUrl = '';
                if ($Partnership == 'Active') {
                    $offerUrl = 'https://aff.affiliates.com.tw/affiliates/offers/' . $idInAff;
                    $cacheName = "data_" . date("YmdH") . "_offer_detail_$idInAff.dat";
                    $detailResult = $this->oLinkFeed->GetHttpResultAndCache($offerUrl,$request,$cacheName,'authenticity_token');
                    $token = $this->oLinkFeed->ParseStringBy2Tag($detailResult,array('name="authenticity_token"', 'value="'), '"');
                    $outh_id = intval($this->oLinkFeed->ParseStringBy2Tag($detailResult,array('請選擇推廣', 'id="offer_variant_id_'), '"'));

                    $requestPost['postdata'] = "utf8=%E2%9C%93&authenticity_token=" . urlencode($token) . "&offer_variant%5Bid%5D=$outh_id&subid_1=&subid_2=&subid_3=&subid_4=&subid_5=&aff_uniq_id=&offer_id=$idInAff";
                    $dUrl = 'https://aff.affiliates.com.tw/affiliates/offer_variants/generate_url';
                    $cacheName = "data_" . date("YmdH") . "_offer_default_$idInAff.dat";
                    $result = $this->oLinkFeed->GetHttpResultAndCache($dUrl,$requestPost,$cacheName,'');
                    $result = json_decode($result, true);
                    $affDufaultUrl = trim($this->oLinkFeed->ParseStringBy2Tag($result['modal_content'], 'readonly>', '</textarea>'));
                }

                $arr_prgm[$idInAff] = array(
                		'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                		'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                		'IdInAff' => $idInAff,
                		'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                		"AffDefaultUrl" => addslashes($affDufaultUrl),
                );
                
                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $idInAff, $this->info['crawlJobId']))
                {

	                $name = $val['name'];
	                $desc = $val['brand_background'] . ' ; ' . $val['product_description'];
	                $homepage = $val['preview_url'];
	                $supportDeepUrl = intval($val['deeplink']) == 1 ? 'YES' : 'NO';
	                $category = join(EX_CATEGORY, $val['categories']);
	                $country = @join(',', $val['countries']);
	                $term = "allow traffic: " . join(',', $val['restrictions']) . "; suggested_media : " . $val['suggested_media'];

                    $cookie = 0;
	                if (!empty($detailResult)) {
                        $cookie = intval($this->oLinkFeed->ParseStringBy2Tag($detailResult,array('Cookie 有效天數', '<div class="span8">'), '</'));
                    }
	                $commission = preg_replace('@NT\$@', 'TWD', $val['commission_range']);
	                
	                //SEM
	                $SEMPolicyExt = '';
	                $policy = $val['disclaimer'];
	                $is_set_sem = strpos($policy, '關鍵字PPC');
	                if ($is_set_sem !== false){
	                	$SEMPolicyExt .= "關鍵字PPC".$this->oLinkFeed->ParseStringBy2Tag($policy,"關鍵字PPC");
	                }
	
	                $arr_prgm[$idInAff] += array(
	                	'CrawlJobId' => $this->info['crawlJobId'],
	                    "Name" => addslashes($name),
	                    "Description" => addslashes($desc),
	                    "Homepage" => addslashes($homepage),
	                    "StatusInAff" => 'Active',						    //'Active','TempOffline','Offline'
	                    "CommissionExt" => addslashes($commission),
	                    "TermAndCondition" => addslashes($term),
	                    "SupportDeepUrl" => $supportDeepUrl,
	                    'CookieTime' => $cookie,
	                    'CategoryExt' => addslashes($category),
	                    'TargetCountryExt' => addslashes($country),
	                	"SEMPolicyExt" => addslashes($SEMPolicyExt)
	                );
	                $base_program_num++;
                }
                $program_num++;
                if(count($arr_prgm) >= 100){
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