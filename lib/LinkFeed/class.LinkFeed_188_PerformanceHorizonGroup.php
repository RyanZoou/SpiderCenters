<?php
require_once 'text_parse_helper.php';

class LinkFeed_188_PerformanceHorizonGroup
{

    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $oLinkFeed = new LinkFeed();
        $this->info = $oLinkFeed->getAffById($aff_id);
        if (!isset($this->info) || empty($this->info)) {
            $this->info = $oLinkFeed->getAffById($aff_id);
        }
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;

        /*if(SID == 'bdg02'){
            $this->user_api_key = 'vbn64GMc';
            $this->user_api_name = 'p3tew145y3tag41n';
            $this->publisher_id = '1100l8645';
        }else{
            $this->user_api_key = 'ds1IcLda';
            $this->user_api_name = 'p3tew145y3tag41n';
            $this->publisher_id = '1101l11052';
        }*/

    }
    
    public function GetProgramFromAff()
    {
        $start = date("Y-m-d H:i");
        echo "Network 188 start@$start\r\n";
        $this->isFull = $this->info['isFull'];
        $objProgram = new ProgramDb();
        $program_num = 0;
        $i = 0;
        $three_status = array(
            "approved" => "https://{$this->info['APIKey2']}:".$this->info['APIKey1']."@api.performancehorizon.com/user/publisher/".$this->info['APIKey3']."/campaign/a/tracking",
            "pending" => "https://{$this->info['APIKey2']}:".$this->info['APIKey1']."@api.performancehorizon.com/user/publisher/".$this->info['APIKey3']."/campaign/p/tracking",
            "rejected" => "https://{$this->info['APIKey2']}:".$this->info['APIKey1']."@api.performancehorizon.com/user/publisher/".$this->info['APIKey3']."/campaign/r/tracking",

        );
        $list = array();
        foreach ($three_status as $status => $url) {
            $info = $this->oLinkFeed->GetHttpResult($url);
            $info = json_decode($info['content'], true);
            foreach ($info['campaigns'] as $data) {
                //AffId
                //$list[$data['campaign']['campaign_id']]['AffId'] = $this->info['NetworkID'];
                
                
                //IdInAff
                $list[$data['campaign']['campaign_id']]['IdInAff'] = $data['campaign']['campaign_id'];
                $IdInAff = $data['campaign']['campaign_id'];
                
                //AffDefaultUrl
                $list[$data['campaign']['campaign_id']]['AffDefaultUrl'] = addslashes($data['campaign']['tracking_link']);
                //partnership
                if ($status == "approved") {
                	$list[$data['campaign']['campaign_id']]['Partnership'] = 'Active';
                } elseif ($status == "pending") {
                	$list[$data['campaign']['campaign_id']]['Partnership'] = 'Pending';
                } elseif ($status == "rejected") {
                	$list[$data['campaign']['campaign_id']]['Partnership'] = 'Declined';
                } else {
                	$list[$data['campaign']['campaign_id']]['Partnership'] = 'NoPartnership';
                }
                
                $list[$data['campaign']['campaign_id']] += array(
                		'AccountSiteID' => $this->info["AccountSiteID"],
                		'BatchID' => $this->info['batchID']
                );
                
                //print_r($list[$data['campaign']['campaign_id']]);exit;
                
                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId']))
                {
                
                	//Homepage
                	$list[$data['campaign']['campaign_id']]['Homepage'] = addslashes($data['campaign']['destination_url']);
	                //Name
	                $list[$data['campaign']['campaign_id']]['Name'] = addslashes(trim($data['campaign']['title']));
	                //CrawlJobId
	                $list[$data['campaign']['campaign_id']]['CrawlJobId'] = $this->info['crawlJobId'];
	                	
	                //CommissionExt
	                /* $list[$data['campaign']['campaign_id']]['CommissionExt'] = implode("|", $data['campaign']['commissions']);
	                if (empty($list[$data['campaign']['campaign_id']]['CommissionExt']) && isset($data['campaign']['default_commission_rate'])) {
	                    $list[$data['campaign']['campaign_id']]['CommissionExt'] = (intval($data['campaign']['default_commission_rate'])) ? intval($data['campaign']['default_commission_rate']) . '%' : addslashes($data['campaign']['default_commission_rate']);
	                } */
	                $Commission_url = "https://{$this->info['APIKey2']}:{$this->info['APIKey1']}@api.performancehorizon.com/user/publisher/{$this->info['APIKey3']}/campaign/{$data['campaign']['campaign_id']}/commission";
	                //$Commission_url = "https://$this->user_api_name:QgHB8VMI@api.performancehorizon.com/user/publisher/305556/campaign/11l71/commission";
	                $Comm_result = $this->oLinkFeed->GetHttpResult($Commission_url);
	                $Comm_result = json_decode($Comm_result['content'],true);//var_dump($Comm_result);exit;
	
	                //$Comm_p_result = $Comm_result['commissions'][0]['publisher_commission']['commissions']; 									//publisher_commission
	                $Comm_g_result = $Comm_result['commissions'][1]['group_commission']['commissions'];											//group_commission
	                $Comm_c_result = $Comm_result['commissions'][2]['campaign_commission']['commissions'];                                     	//campaign_commission     一般只有这个，但是以上两个偶尔也会出现
	
	                $Commission = 0;
	                $Commissionr = array();
	                $Commissionv = array();
	                foreach ($Comm_c_result as $v){
	                    if($v['active'] == 'y'){
	                        if(!empty($v['commission_rate'])){
	                            $Commissionr[] = $v['commission_rate'];
	                        }
	                        if(!empty($v['commission_value'])){
	                            $Commissionv[] = $v['commission_value'];
	                        }
	                    }
	                }
	                if(array_sum($Commissionr) != 0){
	                    $Commission = array_sum($Commissionr)/count($Commissionr);
	                    $Commission = round($Commission,2);
	                    $Commission = $Commission. '%';
	                }elseif(array_sum($Commissionv) != 0){
	                    $Commission = array_sum($Commissionv)/count($Commissionv);
	                    $Commission = round($Commission,2);
	                }
	
	                if(empty($Commission)){
	                    $Commissionr = array();
	                    $Commissionv = array();
	                    foreach ($Comm_g_result as $v){
	                        if($v['active'] == 'y'){
	                            if(!empty($v['commission_rate'])){
	                                $Commissionr[] = $v['commission_rate'];
	                            }
	                            if(!empty($v['commission_value'])){
	                                $Commissionv[] = $v['commission_value'];
	                            }
	                        }
	                    }
	                    if(array_sum($Commissionr) != 0){
	                        $Commission = array_sum($Commissionr)/count($Commissionr);
	                        $Commission = round($Commission,2);
	                        $Commission = $Commission. '%';
	                    }elseif(array_sum($Commissionv) != 0){
	                        $Commission = array_sum($Commissionv)/count($Commissionv);
	                        $Commission = round($Commission,2);
	                    }
	                }
	
	                $list[$data['campaign']['campaign_id']]['CommissionExt'] = $Commission;
	                //var_dump($list[$data['campaign']['campaign_id']]);exit;
	                //print_r($Commission);exit;
	                
	                //LogoUrl
					if (!empty($data['campaign']['campaign_logo']))
						$list[$data['campaign']['campaign_id']]['LogoUrl'] = $data['campaign']['campaign_logo'];
					else 
						$list[$data['campaign']['campaign_id']]['LogoUrl'] = '';
	
					//CookieTime
					$list[$data['campaign']['campaign_id']]['CookieTime'] = $data['campaign']['cookie_period']/(3600*24);
					
					//CategoryExt
					$list[$data['campaign']['campaign_id']]['CategoryExt'] = $data['campaign']['vertical_name'];
					
	                //TermAndCondition
	                $list[$data['campaign']['campaign_id']]['TermAndCondition'] = '';
	                if (isset($data['campaign']['terms']) && !empty($data['campaign']['terms'])) {
	                    foreach ($data['campaign']['terms'] as $key => $value) {
	                        $list[$data['campaign']['campaign_id']]['TermAndCondition'][] = $key . ":<br>" . $value['terms'];
	                    }
	                    if (count($list[$data['campaign']['campaign_id']]['TermAndCondition'])) {
	                        $list[$data['campaign']['campaign_id']]['TermAndCondition'] = implode("|||", $list[$data['campaign']['campaign_id']]['TermAndCondition']);
	                        $list[$data['campaign']['campaign_id']]['TermAndCondition'] = addslashes($list[$data['campaign']['campaign_id']]['TermAndCondition']);
	                    }
	                } else {
	                    $list[$data['campaign']['campaign_id']]['TermAndCondition'] = "";
	                }
	                //SupportDeepUrl
	                if ($data['campaign']['allow_deep_linking'] == "y") $list[$data['campaign']['campaign_id']]['SupportDeepUrl'] = 'YES';
	                elseif ($data['campaign']['allow_deep_linking'] == "n") $list[$data['campaign']['campaign_id']]['SupportDeepUrl'] = 'NO';
	                else $list[$data['campaign']['campaign_id']]['SupportDeepUrl'] = 'UNKNOWN';
	                //SecondIdInAff    => advertiser_id
	                $list[$data['campaign']['campaign_id']]['SecondIdInAff'] = $data['campaign']['advertiser_id'];
	                //Description
	                if (!empty($data['campaign']['description'])) {
	                    foreach ($data['campaign']['description'] as $key => $value) {
	                        $list[$data['campaign']['campaign_id']]['Description'][] = addslashes($value);
	                    }
	                }
	                if (isset($list[$data['campaign']['campaign_id']]['Description']) && !empty($list[$data['campaign']['campaign_id']]['Description'])) {
	                    $list[$data['campaign']['campaign_id']]['Description'] = implode("|", $list[$data['campaign']['campaign_id']]['Description']);
	                } else {
	                    $list[$data['campaign']['campaign_id']]['Description'] = "";
	                }
	                //StatusInAff
	                if ($data['campaign']['status'] == "a") {
	                    $list[$data['campaign']['campaign_id']]['StatusInAff'] = 'Active';
	                } elseif ($data['campaign']['status'] == "r" || stripos($list[$data['campaign']['campaign_id']]['Name'],"retired")!==false) {
	                    $list[$data['campaign']['campaign_id']]['StatusInAff'] = 'Offline';
	                } else {
	                    mydie("new status(StatusInAff) => {$data['campaign']['status']} apprear");
	                }
	
	                if(stripos($list[$data['campaign']['campaign_id']]['TermAndCondition'], 'Publishers may only use coupons and promotional codes that are provided exclusively through the affiliate program') !== false){
	                    $list[$data['campaign']['campaign_id']]['AllowNonaffCoupon'] = 'NO';
	                }else{
	                    $list[$data['campaign']['campaign_id']]['AllowNonaffCoupon'] = 'UNKNOWN';
	                }
	                //var_dump($list[$data['campaign']['campaign_id']]);exit;
	
	                $i++;
                }
                $program_num++;
            }
        }

        //print_r($list);

        $objProgram->InsertProgramBatch($this->info["NetworkID"], $list, true);
//         $objProgram->updateProgram($this->info['AffId'], $list);
        echo "\tUpdate ({$i}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
        echo "affiliate 188 end @".date('Y-m-d H:i:s')."\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

}

