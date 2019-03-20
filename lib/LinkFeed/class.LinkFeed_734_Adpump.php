<?php
require_once 'text_parse_helper.php';

class LinkFeed_734_Adpump
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;

        $this->file = "programlog_{$aff_id}_" . date("Ymd_His") . ".csv";

    }

    function Login()
    {
        $_ctURL = "https://adpump.com/uk-en/session/login/";
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "get"
        );
        $_ctResult = $this->oLinkFeed->GetHttpResult($_ctURL, $request);//print_r($_ctResult);exit;
        $_ct = trim($this->oLinkFeed->ParseStringBy2Tag($_ctResult['content'], "data['__ct'] = '", "'"));
        $this->info['LoginPostString'] .= "&__ct=$_ct";

        $Header = array(
            'X-Requested-With: XMLHttpRequest',
            'Accept-Encoding: gzip, deflate, br',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Accept: */*',
            'Accept-Language: zh-CN,zh;q=0.8',
            'Referer: https://adpump.com/uk-en/',
        );

        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "post",
            "postdata" => $this->info['LoginPostString'],
            "addheader" => $Header,
        );
        $arr = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
        //print_r($arr);exit;
        if (stripos($arr['content'], 'authorized":true') !== false)
            echo "login succ\r\n";
        else
            mydie("login failed\r\n");

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

        //step 1,login
        $this->Login();

        //step 2,get program from page
        $page = 1;
        $HasNextPage = true;
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "get"
        );
        while ($HasNextPage) {
            $page_url = "https://adpump.com/uk-en/wmOffers/page:$page?action=&act=";
            $page_r = $this->oLinkFeed->GetHttpResult($page_url, $request);
            $page_r = $page_r['content'];
            if (!isset($lastPage))
                $lastPage = trim($this->oLinkFeed->ParseStringBy2Tag($page_r, array('<span class="page last">', '>'), '<'));

            if ($page == $lastPage)
                $HasNextPage = false;

            $nLineStart = 0;
            while (1) {
                $LogoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($page_r, array('<td data-column="logo">', '<img src="'), '"', $nLineStart));
                if (!empty($LogoUrl))
                    $LogoUrl = 'https:' . $LogoUrl;
                else
                    break;
                $RankInAff = trim($this->oLinkFeed->ParseStringBy2Tag($page_r, '<span class="rating-value">', '<', $nLineStart));
                $detail_page = trim($this->oLinkFeed->ParseStringBy2Tag($page_r, '<a target="_blank" href="', '"', $nLineStart));
                $IdInAff = trim($this->oLinkFeed->ParseStringBy2Tag($detail_page, 'id:', ''));
                if (empty($IdInAff)) {
                    continue;
                }

                echo "$IdInAff\t";

                $Name = trim($this->oLinkFeed->ParseStringBy2Tag($page_r, '>', '<', $nLineStart));
                $CommissionExt = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($page_r, '<td data-column="maxPrice">', '</td>', $nLineStart)));
                if (!empty($CommissionExt)) {
                    $CommissionExt = str_replace('up to ', '', $CommissionExt);
                }
                $CommissionExt = str_replace('p', 'RUR', $CommissionExt);
                $CommissionExt = str_replace('&euro;', 'EUR', $CommissionExt);

                $LineStart = 0;
                $detail_r = $this->oLinkFeed->GetHttpResult($detail_page, $request);
                $detail_r = $detail_r['content'];

                if (stripos($detail_r, 'Register and start earning') !== false) {
                    echo "cookie is Invalid, retry login...\r\n";
                    $this->Login();
                    $detail_r = $this->oLinkFeed->GetHttpResult($detail_page, $request);
                    $detail_r = $detail_r['content'];
                }

                $category_arr = array();
                while (1) {
                    $category = trim($this->oLinkFeed->ParseStringBy2Tag($detail_r, array('<li class="active" >', '>'), '<', $LineStart));
                    if (!empty($category))
                        $category_arr[] = $category;
                    else
                        break;
                }
                $CategoryExt = implode(EX_CATEGORY,$category_arr);

                $Homepage = trim($this->oLinkFeed->ParseStringBy2Tag($detail_r, '<a target="_blank" href="', '"', $LineStart));

                //getpartnership
                $partnership_str = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($detail_r, '<span id="wmOffers-button-add"', '</span>', $LineStart)));
                if (stripos($partnership_str, 'Get links') !== false) {
                    $Partnership = 'Active';
                    $getLink_Url = trim($this->oLinkFeed->ParseStringBy2Tag($detail_r, '<button data-wmgetlinks="', '"'));
                    $link_r = $this->oLinkFeed->GetHttpResult($getLink_Url, $request);
                    $AffDefaultUrl_arr = trim($this->oLinkFeed->ParseStringBy2Tag($link_r['content'], array('<textarea', '>'), '</textarea>'));
                    $AffDefaultUrl_arr = explode("\n", $AffDefaultUrl_arr);
                    $AffDefaultUrl = end($AffDefaultUrl_arr);
                } elseif (stripos($partnership_str, 'Request is sent') !== false) {
                    $Partnership = 'Pending';
                    $AffDefaultUrl = '';
                } elseif (stripos($partnership_str, 'Register and start earning') !== false) {
                    print_r($detail_r);
                    mydie("IdInAff is $IdInAff, cookie is Invalid");
                } else {
                    $Partnership = 'NoPartnership';
                    $AffDefaultUrl = '';
                }
                
                $arr_prgm[$IdInAff] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                    'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                    'IdInAff' => $IdInAff,
                    'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                    "AffDefaultUrl" => addslashes($AffDefaultUrl),
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
                    $TargetCountryExt = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($detail_r, '<h4>Geo targeting:</h4>', '</p>', $LineStart)));
                    $desc = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($detail_r, '<h4>Description:</h4>', '<div', $LineStart)));
                    $Deeplink = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($detail_r, '<td>Deeplink:</td>', '</td>', $LineStart)));
                    if ($Deeplink == 'Yes')
                        $SupportDeepUrl = 'YES';
                    elseif ($Deeplink == 'No')
                        $SupportDeepUrl = 'NO';
                    else
                        $SupportDeepUrl = 'UNKNOWN';
                    $JoinDate = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($detail_r, '<td>Start date:</td>', '</td>', $LineStart)));
                    $JoinDate = date('Y-m-d H:i:s', strtotime($JoinDate));
                    //SEM
                    $SEMPolicyExt = '';
                    $SupportType = "";
                    $policy = $this->oLinkFeed->ParseStringBy2Tag($detail_r,array("<h5>Traffic types</h5>",'<table>'),'</table>');
                    $policylist = explode('</tr>', $policy);
                    foreach ($policylist as $poli){
                    	if(stripos($poli, 'search')){
                    		$SEMPolicyExt .= trim($this->oLinkFeed->ParseStringBy2Tag($poli,array('<label','>'),'</label>'))." ";
                    		$SEMPolicyExt .= trim($this->oLinkFeed->ParseStringBy2Tag($poli,array('title','"'),'"'))."\n";
                    	}elseif (stripos($poli, 'Content') && (stripos($poli, 'star.png') || stripos($poli, 'yes.png'))){
                    		$SupportType .= "Content".EX_CATEGORY;
                    	}elseif (stripos($poli, 'Coupon/Deals sites') && stripos($poli, 'no.png') === false){
                    		$SupportType .= "Coupon".EX_CATEGORY;
                    	}
                    }
                    $SupportType = rtrim($SupportType, EX_CATEGORY);

                    $arr_prgm[$IdInAff] += array(
                        "CrawlJobId" => $this->info['crawlJobId'],
                        "Name" => addslashes(trim($Name)),
                        "Homepage" => addslashes($Homepage),
                        "RankInAff" => $RankInAff,
                        "StatusInAff" => 'Active',                        //'Active','TempOffline','Offline'
                        //"MobileFriendly" => 'UNKNOWN',
                        "JoinDate" => $JoinDate,
                        "CommissionExt" => addslashes($CommissionExt),
                        "CategoryExt" => addslashes($CategoryExt),
                        'TargetCountryExt' => addslashes($TargetCountryExt),
                        "Description" => addslashes($desc),
                        "SupportDeepUrl" => $SupportDeepUrl,
                    	'SEMPolicyExt' => addslashes($SEMPolicyExt),
                    	'SupportType' => $SupportType
                    );
                    $base_program_num++;
                }
                $program_num++;
                if (count($arr_prgm) >= 100) {
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                    echo "update NO.$program_num\r\n";
                    $arr_prgm = array();
                }
            }
            if (count($arr_prgm)) {
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                unset($arr_prgm);
            }
            $page++;
        }


        echo "\tGet Program by page end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
        echo "\tSet program country int.\r\n";
    }
}

?>