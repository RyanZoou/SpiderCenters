<?php
class LinkFeed_28_Apd_performance
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->file = "programlog_{$aff_id}_" . date("Ymd_His") . ".csv";
    }

    function GetProgramFromAff()
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Program start @ {$check_date}\r\n";
        $this->isFull = $this->info['isFull'];
        $this->GetProgramByApi();
//        $this->GetProgramByPage();
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function login(){
    	$requset = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "get");
    	$result = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'],$requset);
    	if ($result['code'] == 503){
//			$s = $this->oLinkFeed->ParseStringBy2Tag($result['content'],array(),);
		}

    	var_dump($result);exit;

    	$requset = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "post","postdata" => urlencode($this->info['LoginPostString']),'header' => 1,'referer'=>'https://www.apdperformance.com.au/login.user');
    	$requset['addheader'] = array('upgrade-insecure-requests:1','user-agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36','accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8','accept-encoding:gzip, deflate, br','accept-language:zh-CN,zh;q=0.9','cache-control:no-cache','content-length:42','content-type:application/x-www-form-urlencoded','pragma:no-cache');
    	$result = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'],$requset);
    	var_dump($result);exit;
	}

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = $base_program_num = 0;
        //login
//        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);
//		$this->login();
        $request = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "get","postdata" => "");

        $AccountSid = urlencode($this->info['APIKey1']);
        $AccountToken = urlencode($this->info['APIKey2']);

        $hasNextPage = true;
        $perPage = 100;
        $page = 1;

        while($hasNextPage){
            $strUrl = "https://{$AccountSid}:{$AccountToken}@api.impactradius.com/Mediapartners/{$AccountSid}/Campaigns.json?PageSize={$perPage}&Page=$page";
            $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
            $result = $r["content"];

            $result = json_decode($result);

            $page++;

            $numpages = "@numpages";
            $numReturned = intval($result->$numpages);
            if(!$numReturned) {
                break;
            }
            if($page > $numReturned){
                $hasNextPage = false;
            }

            $mer_list = $result->Campaigns;

            foreach($mer_list as $v)
            {
                $strMerID = intval($v->CampaignId);
                if(!$strMerID) {
                    continue;
                }

                $StatusInAffRemark = $v->ContractStatus;
                if($StatusInAffRemark == "Expired"){
                    $Partnership = "Expired";
                }elseif($StatusInAffRemark == "Active"){
                    $Partnership = "Active";
                }else{
                    $Partnership = "NoPartnership";
                }
                $TrackingLink = $v->TrackingLink;

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                    'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
                    'IdInAff' => $strMerID,
                    'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
                    "AffDefaultUrl" => addslashes($TrackingLink),
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    $strMerName = $v->CampaignName;
                    $Homepage = $v->CampaignUrl;
                    $LogoUrl = "https://www.apdperformance.com.au" . $v->CampaignLogoUri;
                    $AllowsDeeplinking = $v->AllowsDeeplinking;
					/*$termUrl = "https://www.apdperformance.com.au/secure/mediapartner/campaigns/mp-view-io-by-campaign-flow.ihtml?c=$strMerID";
					$term = $this->oLinkFeed->GetHttpResult($termUrl, $request)['content'];

					$search = array("/<script[^>]*?>.*?<\/script>/si", // 去掉 javascript
						"/<style[^>]*?>.*?<\/style>/si", // 去掉 css
						"/<[\/!]*?[^<>]*?>/si", // 去掉 HTML 标记
						"/<!--[\/!]*?[^<>]*?>/si", // 去掉 注释标记
						"/([\r\n])[\s]+/", // 去掉空白字
					);
					$replace = array("",
						"",
						"",
						"",
						",\t",
					);
					$TermAndCondition = preg_replace($search, $replace, $term);*/
                    if (stripos($AllowsDeeplinking, "true") !== false) {
                        $SupportDeepurl = 'YES';
                    } else {
                        $SupportDeepurl = 'NO';
                    }
                    if (empty($v->ShippingRegions)) {
                        $TargetCountryExt = "";
                    } else {
                        $TargetCountryExt = implode(',', $v->ShippingRegions);
                    }
                    $desc = $v->CampaignDescription;

                    $StatusInAff = 'Active';
                    if (stripos($strMerName, 'paused') !== false) {
                        $StatusInAff = 'Offline';
                    }

                    $commission = "";
                    $PublicTermsUri = "https://{$AccountSid}:{$AccountToken}@api.impactradius.com".$v->PublicTermsUri;
					$comm_res = $this->oLinkFeed->GetHttpResult($PublicTermsUri,$request);

					if($comm_res['code'] == 200){
						$comm_res = json_decode($comm_res['content'],1);
						if (!isset($comm_res['PayoutTermsList'])){
							var_dump($comm_res);
//								mydie();
						}
						foreach ($comm_res['PayoutTermsList'] as $payout){
							$commission .= $payout['TrackerName'].': ';
							if (!empty($payout['PayoutPercentage']) && empty($payout['PayoutAmount'])){
								if($payout['PayoutPercentageLowerLimit'] == $payout['PayoutPercentageUpperLimit'] || $payout['PayoutPercentageLowerLimit'] == 0){
									$commission .= $payout['PayoutPercentage']."% |";
								}else{
									$commission .= $payout['PayoutPercentageLowerLimit']."%-".$payout['PayoutPercentageUpperLimit']."%|";
								}
							}elseif (!empty($payout['PayoutAmount']) && empty($payout['PayoutPercentage'])){
								$currency = '';
								switch ($payout['PayoutCurrency']) {
									case "AUD":
										//联盟里面显示是$  按联盟来
										$currency = "$";
										break;
									case "USD":
										$currency = "$";
										break;
									case "EUR":
										$currency = "€";
										break;
									case "GBP":
										$currency = "￡";
										break;
									case "CAD":
										$currency = "CAD";
										break;
									default:
										$currency = $payout['PayoutCurrency'];
										echo $strMerID." currency is ".$currency."\r";
								}
								if($payout['PayoutAmountLowerLimit'] == $payout['PayoutAmountUpperLimit'] || $payout['PayoutAmountLowerLimit'] == 0){
									$commission .= $currency.$payout['PayoutAmount']."|";
								}else{
									$commission .= $currency.$payout['PayoutAmountLowerLimit']."-".$currency.$payout['PayoutAmountUpperLimit']."|";
								}
							}elseif ((!empty($payout['PayoutPercentage']) || !empty($payout['PayoutPercentageUpperLimit'])) && !empty($payout['PayoutAmount'])){
								if($payout['PayoutPercentageLowerLimit'] == $payout['PayoutPercentageUpperLimit'] || $payout['PayoutPercentageLowerLimit'] == 0){
									$commission .= $payout['PayoutPercentage']."% ,";
								}else{
									$commission .= $payout['PayoutPercentageLowerLimit']."%-".$payout['PayoutPercentageUpperLimit']."%,";
								}
								$currency = '';
								switch ($payout['PayoutCurrency']) {
									case "AUD":
										//联盟里面显示是$  按联盟来
										$currency = "$";
										break;
									case "USD":
										$currency = "$";
										break;
									case "EUR":
										$currency = "€";
										break;
									case "GBP":
										$currency = "￡";
										break;
									case "CAD":
										$currency = "CAD";
										break;
									default:
										$currency = $payout['PayoutCurrency'];
										echo $strMerID." currency is ".$currency."\r";
								}
								if($payout['PayoutAmountLowerLimit'] == $payout['PayoutAmountUpperLimit'] || $payout['PayoutAmountLowerLimit'] == 0){
									$commission .= $currency.$payout['PayoutAmount']."|";
								}else{
									$commission .= $currency.$payout['PayoutAmountLowerLimit']."-".$currency.$payout['PayoutAmountUpperLimit']."|";
								}
							}
						}
						$commission = rtrim($commission,"|");
					}else{
						echo "{$strMerID} no comm info\t";
					}

                    $arr_prgm[$strMerID] += array(
                        "CrawlJobId" => $this->info['crawlJobId'],
                        "Name" => addslashes(html_entity_decode($strMerName)),
                        "StatusInAffRemark" => $StatusInAffRemark,
                        "StatusInAff" => $StatusInAff,                        //'Active','TempOffline','Offline'
                        "TargetCountryExt" => $TargetCountryExt,
                        "Description" => addslashes($desc),
                        "Homepage" => addslashes($Homepage),
                        "SupportDeepUrl" => $SupportDeepurl,
                        "LogoUrl" => addslashes($LogoUrl),
						"CommissionExt" => trim($commission),
//                        "TermAndCondition" => addslashes($TermAndCondition),
                    );
                    $base_program_num ++;
                }
                $program_num++;

                if(count($arr_prgm) >= 100){
                    $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
                    $arr_prgm = array();
                }
            }
        }
        if(count($arr_prgm)){
            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm);
            unset($arr_prgm);
        }

        echo "\tGet Program by api end\r\n";

        if($program_num < 10){
            mydie("die: program count < 10, please check program.\n");
        }

        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }

    function GetProgramByPage()
    {
        echo "\tGet Program by page start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = array();
        $program_num = $base_program_num = 0;

        //step 1,login
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info);

        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "get",
            "postdata" => "",
        );

        $strUrl = "https://www.apdperformance.com.au/secure/mediapartner/campaigns/mp-manage-active-ios-flow.ihtml?execution=e1s1";
        $this->oLinkFeed->GetHttpResult($strUrl,$request);

        $page = 1;
        $hasNextPage = true;
        while($hasNextPage){
            $start = ($page - 1) * 100;
            $strUrl = "https://www.apdperformance.com.au/secure/nositemesh/mediapartner/mpCampaignsJSON.ihtml?startIndex=$start&pageSize=100&tableId=myCampaignsTable&q=&page=$page";
            $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
            $result = $r["content"];
            $result = json_decode($result);
            //var_dump($result);exit;
            $total = intval($result->totalCount);
            if($total < ($page * 100)){
                $hasNextPage = false;
            }
            $page++;

            $data = $result->records;
            foreach($data as $v){
                //id
                $strMerID = intval($v->id->crv);
                if (empty($strMerID)) {
                    break;
                }

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                    $strMerName = trim($this->oLinkFeed->ParseStringBy2Tag($v->name->dv, '">', "</a>"));
                    if ($strMerName === false) {
                        break;
                    }

                    $re = json_encode($v);
                    $CommissionExt = trim($this->oLinkFeed->ParseStringBy2Tag($re, '<div class=\"textSpaced\">', "<\/div>"));

                    //contact
                    $con_url = "https://www.apdperformance.com.au/secure/directory/campaign.ihtml?d=lightbox&n=footwear+etc&c=$strMerID";
                    $con_r = $this->oLinkFeed->GetHttpResult($con_url, $request);
                    $con_r = $con_r['content'];

                    $CategoryExt = trim($this->oLinkFeed->ParseStringBy2Tag($con_r, array('id="categoryLink"', '>'), '<'));
                    $con_name = trim($this->oLinkFeed->ParseStringBy2Tag($con_r, 'id="contactName">', "</div>"));
                    $con_detail = trim($this->oLinkFeed->ParseStringBy2Tag($con_r, array('Send email', '<div class="truncate dirContactDetails">'), "</div>"));
                    $Contacts = $con_name . ':' . $con_detail;
                    
                    //terms
                    $term = "";
                    $detail_url = "https://www.apdperformance.com.au/secure/mediapartner/campaigns/mp-view-io-by-campaign-flow.ihtml?c={$strMerID}";
                    $detail_r = $this->oLinkFeed->GetHttpResult($detail_url, $request);
                    $detail_r = $detail_r['content'];
                    $terms = trim($this->oLinkFeed->ParseStringBy2Tag($detail_r,array("Approval Terms","Window.document.write('"),"');"));
                    if (!empty($terms)){
                    	$terms = strip_tags($terms);
                    	//这里获取到了允许和不允许的terms数组
                    	$approval_terms = array_filter(explode('\r\n- ', trim($this->oLinkFeed->ParseStringBy2Tag($terms,"on their program:","The following"))));
                    	$not_allowed_terms = array_filter(explode('\r\n- ', trim($this->oLinkFeed->ParseStringBy2Tag($terms,"prior approval:"))));
                    	if (in_array("Coupon", $not_allowed_terms) && in_array("Content", $not_allowed_terms)){
                    		echo "idinaff:{$strMerID}".PHP_EOL;
                    		mydie(" not allowed Coupon and Content");
                    		$term = null;
                    	}elseif (!in_array("Coupon", $not_allowed_terms) && in_array("Content", $not_allowed_terms)){
                    		$term = "Coupon";
                    	}elseif (in_array("Coupon", $not_allowed_terms) && !in_array("Content", $not_allowed_terms)){
                    		$term = "Content";
                    	}else{

                    	    /*Sunny Chen 要求只根据不允许来判断，若不允许的范围未提及，默认都支持
                    		if ((in_array("Coupon", $approval_terms) && in_array("Content", $approval_terms)) || (!in_array("Coupon", $approval_terms) && !in_array("Content", $approval_terms))){
                    			$term = "";
                    		}elseif (!in_array("Coupon", $approval_terms) && in_array("Content", $approval_terms)){
                    			$term = "Content";
                    		}elseif (in_array("Coupon", $approval_terms) && !in_array("Content", $approval_terms)){
                    			$term = "Coupon";
                    		}
                    	    */

                            $term = "";
                    	}
                    }else{
                    	$term = "";
                    }
					$search = array("/<script[^>]*?>.*?<\/script>/si", // 去掉 javascript
						"/<style[^>]*?>.*?<\/style>/si", // 去掉 css
						"/<[\/!]*?[^<>]*?>/si", // 去掉 HTML 标记
						"/<!--[\/!]*?[^<>]*?>/si", // 去掉 注释标记
						"/([\r\n])[\s]+/", // 去掉空白字
					);
					$replace = array("",
						"",
						"",
						"",
						",\t",
					);
					$TermAndCondition = preg_replace($search, $replace, $detail_r);

                    $arr_prgm[$strMerID] = array(
                        'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                        'BatchID' => $this->info['batchID'],
                        "IdInAff" => $strMerID,
                        "CrawlJobId" => $this->info['crawlJobId'],
                        "Name" => addslashes(html_entity_decode($strMerName)),
                        "CommissionExt" => addslashes($CommissionExt),
                        "Contacts" => addslashes($Contacts),
                        'CategoryExt' => addslashes($CategoryExt),
                    	'SupportType' => $term,
						"TermAndCondition" => trim($TermAndCondition)
                    );
                    $base_program_num ++;
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

        echo "\tGet Program by page end\r\n";

        if($program_num < 10){
            mydie("die: program count < 10, please check program.\n");
        }

        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }


}