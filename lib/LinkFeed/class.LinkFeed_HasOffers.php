<?php
class LinkFeed_HasOffers
{
    function GetProgramFromAff()
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Program start @ {$check_date}\r\n";
        $this->isFull = $this->info['isFull'];
        $this->GetProgramByApi();
        echo "Craw Program end @ ".date("Y-m-d H:i:s")."\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $base_program_num = $program_num = 0;
        $request = array('AccountSiteID'=>$this->info['AccountSiteID'],'method'=>'get',);
        $curl_error_num = 0;

        $strUrl = "https://{$this->NetworkId}.api.hasoffers.com/Apiv3/json?api_key={$this->apikey}&Target=Affiliate_Offer&Method=findAll";
        $r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
        if(empty($r['content'])) {
            mydie("Error type is can not get infomation from Api");
        }
        $apiResponse = @json_decode($r['content'], true);
        if(!isset($apiResponse['response']['status']) || $apiResponse['response']['status'] != 1) {
            mydie("API call failed ({$apiResponse['response']['errorMessage']})");
        }

        $result = $apiResponse['response']['data'];

//        print_r($result);exit;

        foreach($result as $item)
        {
            $v = $item['Offer'];
            $IdInAff = intval(trim($v['id']));
            if(!$IdInAff)
                continue;
            echo "$IdInAff\t";

            switch ($v['approval_status'])
            {
                case 'approved':
                    $Partnership = 'Active';
                    break;
                case 'Pending':
                    $Partnership = 'Pending';
                    break;
                case 'pending':
                    $Partnership = 'Pending';
                    break;
                case 'rejected':
                    $Partnership = 'Declined';
                    break;
                case null:
                    $Partnership = 'NoPartnership';
                    break;
                case '':
                    $Partnership = 'NoPartnership';
                    break;
                default:
                    mydie("New approval_status appeared: {$v['approval_status']} ");
                    break;
            }

            $StatusInAffRemark = $v['status'];
            if($StatusInAffRemark == 'active') {
                $StatusInAff = 'Active';
            } else {
                mydie("New StatusInAffRemark appeared: $StatusInAffRemark ");
            }

            $AffDefaultUrl = '';
            if ($StatusInAff == 'Active' && $Partnership == 'Active') {
                if ($v['require_terms_and_conditions'] == 1) {
                    $find_url = "https://{$this->NetworkId}.api.hasoffers.com/Apiv3/json?api_key={$this->apikey}&Target=Affiliate_Offer&Method=findById&id={$IdInAff}";
                    $cache_name = "Partnership_{$IdInAff}_" . date('YmdH') . '.cache';
                    $find_result = $this->oLinkFeed->GetHttpResultAndCache($find_url, $request, $cache_name);
                    $find_result = json_decode($find_result, true);

                    if ($find_result['response']["status"] != 1) {
                    	do {
                    		if ($curl_error_num >= 5){
                    			print_r($find_result['response']['errors']);
                    			mydie("Failed request api method findById by {$IdInAff}!");
                    		} 
                    		$curl_error_num++;
                    		echo "get AffDefaultUrl http response error {$curl_error_num}th , try again later".PHP_EOL;
                    		sleep(5);
                    		$find_result = $this->oLinkFeed->GetHttpResultAndCache($find_url, $request, $cache_name);
                    		$find_result = json_decode($find_result, true);
                    	}while($find_result['response']["status"] != 1);
						$curl_error_num = 0;
                    }
                    $agree = @$find_result['response']['data']['AffiliateOffer']['agreed_terms_and_conditions'];
                    $Partnership = is_null($agree) ? 'NoPartnership' : 'Active';
                }

                //get AffDefaultUrl
                $default_url = "https://{$this->NetworkId}.api.hasoffers.com/Apiv3/json?api_key={$this->apikey}&Target=Affiliate_Offer&Method=generateTrackingLink&offer_id={$IdInAff}";
                $cache_name = "AffDefaultUrl_{$IdInAff}_" . date('YmdH') . '.cache';
                $default_result = $this->oLinkFeed->GetHttpResultAndCache($default_url, $request, $cache_name);
                $default_result = json_decode($default_result, true);

                if ($default_result['response']["status"] != 1) {
                    echo "Failed request api method generateTrackingLink by {$IdInAff} : {$default_result['response']['errorMessage']}! \n\r";
                }

                $AffDefaultUrl = isset($default_result['response']['data']['click_url']) ? addslashes($default_result['response']['data']['click_url']) : '';
                sleep(1);
            }

            $arr_prgm[$IdInAff] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                'IdInAff' => $IdInAff,
                'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                'AffDefaultUrl' => addslashes($AffDefaultUrl),
            );
            

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
            	$desc = strip_tags($v['description']);
                $Homepage = $v['preview_url'];
                $TermAndCondition = $v['require_terms_and_conditions'] == 1 ? addslashes(strip_tags($v['terms_and_conditions'])) : '';
                $SupportDeepUrl = intval($v['allow_website_links']) == 1 ? 'YES' : 'NO';

                if ($v['payout_type'] == 'cpa_percentage') {
                    $CommissionExt = $v['percent_payout'] . '%';
                } elseif ($v['currency']) {
                    $CommissionExt = $v['currency'] . ' ' . round($v['default_payout'], 2);
                } else {
                    $CommissionExt = $this->Currency . round($v['default_payout'], 2);
                }
                
                //SEM
                $SEMPolicyExt = '';
                $desc_table = $this->oLinkFeed->ParseStringBy2Tag($v['description'],array("<tbody",">"),"</tbody>");
                if (!empty($desc_table)){
                	$desc_table = explode("</tr>",$desc_table);
                	foreach ($desc_table as $oneline){
                		if(stripos($oneline, 'sem') !== false){
                			$SEMPolicyExt .= strip_tags($oneline)."\n";
                		}
                	}
                }

                $arr_prgm[$IdInAff] += array(
                    'CrawlJobId' => $this->info['crawlJobId'],
                    'Name' => addslashes((trim($v['name']))),
                    'Homepage' => addslashes($Homepage),
                    'StatusInAffRemark' => addslashes($StatusInAffRemark),
                    'StatusInAff' => $StatusInAff,                        //'Active','TempOffline','Offline'
                    'CommissionExt' => addslashes($CommissionExt),
                    'Description' => addslashes($desc),
                    'TermAndCondition' => $TermAndCondition,
                    'SupportDeepUrl' => $SupportDeepUrl,
                    'TargetCountryExt' => '',
                    'CategoryExt' => '',
                    'LogoUrl' => '',
                	"SEMPolicyExt" => addslashes($SEMPolicyExt)
                );

                //get TargetCountry
                $countries_url = "https://{$this->NetworkId}.api.hasoffers.com/Apiv3/json?api_key={$this->apikey}&Target=Affiliate_Offer&Method=getTargetCountries&ids[]={$IdInAff}";
                $cache_name = "Country_{$IdInAff}_" . date('YmdH') . '.cache';
                $countries_result = $this->oLinkFeed->GetHttpResultAndCache($countries_url, $request, $cache_name);
                $countries_result = json_decode($countries_result, true);
                $CountryExt = array();

                if ($countries_result['response']['status'] == 1) {
                    foreach ($countries_result['response']['data'][0]['countries'] as $k => $val) {
                        $CountryExt[] = $k;
                    }
                    if (!empty($CountryExt)) {
                        $arr_prgm[$IdInAff]['TargetCountryExt'] = addslashes(implode(",", $CountryExt));
                    }
                }

                //get CategoryExt
                $category_url = "https://{$this->NetworkId}.api.hasoffers.com/Apiv3/json?api_key={$this->apikey}&Target=Affiliate_Offer&Method=getCategories&ids[]={$IdInAff}";
                $cache_name = "Category_{$IdInAff}_" . date('YmdH') . '.cache';
                $category_result = $this->oLinkFeed->GetHttpResultAndCache($category_url, $request, $cache_name);
                $category_result = json_decode($category_result, true);
                $Category = array();

                if ($category_result['response']['status'] == 1) {
                    foreach ($category_result['response']['data'][0]['categories'] as $val) {
                        $Category[] = $val['name'];
                    }
                    if (!empty($Category)) {
                        $arr_prgm[$IdInAff]['CategoryExt'] = addslashes(implode(EX_CATEGORY, $Category));
                    }
                }

                //get LogoUrl
                $LogoUrl_url = "https://{$this->NetworkId}.api.hasoffers.com/Apiv3/json?api_key={$this->apikey}&Target=Affiliate_Offer&Method=getThumbnail&ids[]={$IdInAff}";
                $cache_name = "Logo_{$IdInAff}_" . date('YmdH') . '.cache';
                $LogoUrl_result = $this->oLinkFeed->GetHttpResultAndCache($LogoUrl_url, $request, $cache_name);
                $LogoUrl_result = json_decode($LogoUrl_result, true);

                if ($LogoUrl_result['response']['status'] == 1) {
                    $Logo = end($LogoUrl_result['response']['data'][0]['Thumbnail']);
                    $arr_prgm[$IdInAff]['LogoUrl'] = addslashes($Logo['url']);
                }
                $base_program_num ++;
            }

            $program_num++;
            if(count($arr_prgm) >= 100){
                $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                $arr_prgm = array();
            }
        }

        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            unset($arr_prgm);
        }
        echo "\tGet Program by api end\r\n";

        if($program_num < 10 && $this->info["NetworkID"] != 2052){
            mydie("die: program count < 10, please check program.\n");
        }

        echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }

}