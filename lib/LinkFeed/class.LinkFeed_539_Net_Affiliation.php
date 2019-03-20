<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_539_Net_Affiliation
{
	function __construct($aff_id, $oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->isFull = true;
		
		$this->partnership = array();
		$this->countryarr = json_decode($this->info['APIKey2'],true);
		$this->coupon_sites = json_decode($this->info['APIKey3'],true);
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
		$countryarr = $this->countryarr;
		foreach($countryarr as $k){
			echo "\tGet Program by page start\r\n";
			$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info);
			$objProgram = new ProgramDb();
			$program_num = $base_program_num = 0;
			$tmp = 0;
			$request = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "post","postdata" => "",);
			//-------------------登陆-----------------------------------
			$request_Login = $request;
			//$request_Login['postdata'] = "login%5Bemail%5D=info%40couponsnapshot.com+&login%5Bmdp%5D=Tskkd14s7j%26d&login%5Bremember%5D=on";
			$request_Login['postdata'] = $this->info['LoginPostString'];
			//-------------------选择mega在联盟中登记过的站点,必须要有这一步，不然下面"postulationaff"会变成其他词，从而无法拿到token-----------------------------
			$request_chooseSite = $request;
			$request_chooseSite['postdata'] = "hidden_res_type=s&hidden_res_id=$k";
			$this->oLinkFeed->GetHttpResult("https://www6.netaffiliation.com/",$request_chooseSite);
			//-------------------点击“register a program”之后，获取token-----------
			$request_available = $request;
			$arr_token = $this->oLinkFeed->GetHttpResult("https://www6.netaffiliation.com/affiliate/program/management",$request_available);
			preg_match_all('#name=\"postulationaff\[\_csrf\_token\]\" value=\"(.*)\" id=\"postulationaff\_\_csrf\_token\" \/>#', $arr_token['content'], $matches);
			$token = $matches[1][0];
			//-------------------开始爬取-----------------------------------------------------------------
			$partnershipType = array(
					"active" => 2,
					"pending" => 1,
					"NoPartnership" => '-1',
					"Declined" => 3
			);//2代表active,1代表pending,-1代表NoPartnership，3代表Declined

			foreach ($partnershipType as $status => $v){
				echo "start get $status programs\r\n";
				$partnerShipCode = $v;
				$prgmNbPerPage = 9999;
				$cid = $k;
//              $program_num += $this->GetProgramBySearchBR($request,$token,$v,9999,$k);
// 				function GetProgramBySearchBR($request,$token,$partnerShipCode,$prgmNbPerPage,$cid){
                $this->GetDefultUrlByApi();
                //先爬取第一页，得到分页栏中的分页个数
                $requestFirst = $request;
                $requestFirst['postdata'] =		 'postulationaff%5Bpage_courante%5D=1'
                		.'&postulationaff%5Bnb_resultat_par_page%5D='.$prgmNbPerPage
                		.'&postulationaff%5B_csrf_token%5D='.$token
                		.'&postulationaff%5Betat_programmme%5D='.$partnerShipCode
                		.'&postulationaff%5Bmots_clefs%5D='
                				.'&postulationaff%5Bdate%5D='
                						.'&formName=postulationaff';
                $showReq = $request;
                $showReq['addheader'] = array("X-Requested-With:XMLHttpRequest");
                
                $requestFirst['addheader'] = array("X-Requested-With:XMLHttpRequest");
                $arrFirstPage = $this->oLinkFeed->GetHttpResult("https://www6.netaffiliation.com/affiliate/program/management/get-programme", $requestFirst);
                $programNum = $this->oLinkFeed->ParseStringBy2Tag($arrFirstPage['content'], '<h2 class=\"gris left\">',' result(s)');//program数
                $pageNum = ceil($programNum/$prgmNbPerPage);//页数
                for ($p=1;$p<=$pageNum;$p++){//分页循环爬取
                	$nOffset = 0;
                	$requestPrgm = $request;
                	$requestPrgm['postdata'] =	'postulationaff%5Bpage_courante%5D='.$p
                	.'&postulationaff%5Bnb_resultat_par_page%5D='.$prgmNbPerPage
                	.'&postulationaff%5B_csrf_token%5D='.$token
                	.'&postulationaff%5Betat_programmme%5D='.$partnerShipCode
                	.'&postulationaff%5Bmots_clefs%5D='
                			.'&postulationaff%5Bdate%5D='
                			.'&formName=postulationaff';
                	$requestPrgm['addheader'] = array("X-Requested-With:XMLHttpRequest");
                	$arr = $this->oLinkFeed->GetHttpResult("https://www6.netaffiliation.com/affiliate/program/management/get-programme", $requestPrgm);
                	$arr = json_decode($arr['content'], true);
                	$page_content = str_replace(array("\r","\n","\t"), "", $arr['html']);
                	preg_match_all('/id="prog_(\d*)">/', $page_content,$matches);
                	foreach ($matches[1] as $prgm){//program循环爬取
                		$idInAff = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, array('id="prog_'),'">', $nOffset));$namePos = $nOffset;
                		$name = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, 'target="_blank">','</a>',$namePos));$namePos = $nOffset;
                		$homePage = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, '<a href="', '" target="_blank"',$namePos));$namePos = $nOffset;
                		$contact = trim($this->oLinkFeed->ParseStringBy2Tag($page_content, '<h5>Program manager :</h5>', '</span>',$namePos));$namePos = $nOffset;
                
                		if($partnerShipCode == '2'){
                			$partnerShip = 'Active';
                			$StatusInAff = 'Active';
                			$this->partnership[$prgm] = 1;
                		}elseif($partnerShipCode == '3'){
                			$partnerShip = 'Declined';
                			$StatusInAff = 'Active';
                		}elseif($partnerShipCode == '-1'){
                			$partnerShip = 'NoPartnership';
                			$StatusInAff = 'Active';
                		}elseif($partnerShipCode == '1'){
                			$partnerShip = 'Pending';
                			$StatusInAff = 'Active';
                		}
                		if (isset($this->partnership[$prgm]))
                			$partnerShip = 'Active';
                
                		if($this->isFull)
                		{
                			//Description需要另外爬取页面
                			$requestDesc = $request;
                			$requestDesc['postdata'] = 'id='.$prgm;
                			$requestDesc['addheader'] = array("X-Requested-With:XMLHttpRequest");
                			$arrDescPage = $this->oLinkFeed->GetHttpResult("https://www6.netaffiliation.com/affiliate/program/management/show-desc", $requestDesc);
                			$description = $arrDescPage['content'];
                				
                				
                			//commission也需要另外爬页面
                			$requestCom = $request;
                			$requestCom['postdata'] = 'id='.$prgm;
                			$requestCom['addheader'] = array("X-Requested-With:XMLHttpRequest");
                			$arrComPage = $this->oLinkFeed->GetHttpResult("https://www6.netaffiliation.com/affiliate/program/management/show-infos-rem", $requestDesc);
                			$jsString = $this->oLinkFeed->ParseStringBy2Tag($arrComPage['content'], '<!--  CONTENEUR REMUNERATION   -->','<div');
                			$commission = str_replace($jsString, '', $arrComPage['content']);//将commission页面中的javascript代码去除
                			//判断此program是否支持deeplink
                			$requestDeep = $request;
                			$requestDeep['postdata'] = 'id='.$prgm;
                			$requestDeep['addheader'] = array("X-Requested-With:XMLHttpRequest");
                			$arrDeepPage = $this->oLinkFeed->GetHttpResult("https://www6.netaffiliation.com/affiliate/program/management/show-visual", $requestDeep);
                			preg_match_all('#Deeplink#', $arrDeepPage['content'],$matches);
                			if(isset($matches[0][0]) && $matches[0][0] == 'Deeplink'){
                				$supportDeepUrl = 'YES';
                			}else{
                				$supportDeepUrl = 'NO';
                			}
                			
                			//SupportType
                			if ($status == 'active'){
                			    if ($k == 434511){
                                    $SupportType = "Content" . EX_CATEGORY . "Coupon";
                                }elseif ($k == 446227) {
									$SupportType = '';
									$requestST = $request;
									$requestST['method'] = "get";
									$cache_name = "detail_{$prgm}_" . date("ymdh") . "cache";
									$detailUrl = "https://www6.netaffiliation.com/affiliate/publication-media?prog={$prgm}";
									$detResult = $this->oLinkFeed->GetHttpResultAndCache($detailUrl, $requestST, $cache_name);
									//                 			$detResult = $this->oLinkFeed->GetHttpResult($detailUrl,$requestST);
									$allowList = $this->oLinkFeed->ParseStringBy2Tag($detResult, array("List of features authorized", 'class="jsNetaContenu"'), "</div>");
									if (strpos($allowList, "Vouchers websites") !== false) {
										$SupportType = "Content" . EX_CATEGORY . "Coupon";
									} else {
										$SupportType = "Content";
                                    }
                                }
                			}
                		}
                
                		//将同一个商家的program，根据不同的coupon站点拆分为不同的program，并得到他们的affdefaulturl。
                		$showReq["postdata"] = "id=$idInAff&etatprog=2";
                		$showResult = $this->oLinkFeed->GetHttpResult('https://www6.netaffiliation.com/affiliate/program/management/show', $showReq);
                		$showResult = preg_replace("@>\s+<@", '><', $showResult['content']);
                		if ($k == 434511){
							$listStr = $this->oLinkFeed->ParseStringBy2Tag($showResult, array('Brandreward Coupon ', 'tr>'), '<tr class="even"');
						}elseif ($k == 446227){
							$listStr = $this->oLinkFeed->ParseStringBy2Tag($showResult, array('Brandreward Content ', 'tr>'), '<tr class="even"');
						}
//                		$listStr = $this->oLinkFeed->ParseStringBy2Tag($showResult, array('Brandreward Coupon ', 'tr>'), '<tr class="even"');
                		$listArr = explode('</tr><tr', $listStr);
                		if (empty($listArr)){
                			mydie("The page have changed, please check it.");
                		}
                		foreach ($listArr as $site) {
                			$partnerShipSite = $partnerShip;
                			$siteStrArr = explode('</td><td', $site);
                			$couponCode = trim(strip_tags($siteStrArr[1]));
                			$couponCode = trim(substr($couponCode, -8, 8));
                			if (!isset($this->coupon_sites[$couponCode])) {
                				echo $idInAff.PHP_EOL;
                				print_r($arr);
                				mydie("Find new coupon country: " . $couponCode);
                			}
                			$programId = $idInAff . '_' . $this->coupon_sites[$couponCode];
                			if ($couponCode != 'CoupMENA') {
                				$country = substr($couponCode, -2, 2);
                			} else {
                				$country = 'Algeria,Bahrain,Egypt,Iran,Iraq,Israel,Jordan,Kuwait,Lebanon,Libya,Morocco,Oman,Qatar,Saudi Arabia,State of Palestine,Syrian Arab ,Republic,Tunisia,United Arab Emirates,Yemen';
                			}
                
                			$AffDefaultUrl = '';
                			if (isset($this->AffDefaultUrlList[$programId])) {
                				$AffDefaultUrl = $this->AffDefaultUrlList[$programId];
                			}
                
                			if (stripos($siteStrArr[5], 'id="postulationSite') === false && $partnerShipSite == 'Active') {
                				$partnerShipSite = 'NoPartnership';
                			}
                				
                			$arr_prgm[$programId] = array(
                					'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
                					'BatchID' => $this->info['batchID'],
                					'IdInAff' => $programId,
                					'Partnership' => $partnerShipSite,
                					'AffDefaultUrl' => addslashes($AffDefaultUrl)
                			);
                				
                			if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $programId, $this->info['crawlJobId']))
                			{
                				$arr_prgm[$programId] += array(
                						'CrawlJobId' => $this->info['crawlJobId'],
                						"Name" => addslashes(html_entity_decode($name)),
                						"StatusInAff" => $StatusInAff,
                						"Contacts" => addslashes($contact),
                						"TargetCountryExt" => addslashes($country),
                						"Description" => addslashes($description),
                						"Homepage" => addslashes(trim($homePage)),
                						"CommissionExt" => addslashes(trim($commission)),
                						"SupportDeepUrl" => $supportDeepUrl,
                						"SupportType" => $SupportType
                				);
                				$base_program_num++;
                			}
                			$program_num++;
                			$tmp++;
                			echo $tmp."\t";
                			if (count($arr_prgm)>=100){
                				$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                				$arr_prgm = array();
                			}
                		}
                		
                	}
                }
                if (count($arr_prgm)){
                	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
                	$arr_prgm = array();
                }
				echo "finish get $status programs\r\n";
			}
			echo "\tGet Program by page end\r\n";
			if($tmp < 10){
				mydie("die: program count < 10, please check program.\n");
			}
		}
		echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
        echo "\tGet Program by Api end\r\n";
	}
	
	function GetDefultUrlByApi(){                   //执行该方法前必须先登录。
		if (!empty($this->AffDefaultUrlList)) {
			return $this->AffDefaultUrlList;
		}
	
		$arr_return = array();
		$request = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "get","postdata" => "",);
	
		$site_list = $this->coupon_sites;
		foreach ($site_list as $key => $site){
			$api_url = "http://flux.netaffiliation.com/xmltrack.php?sec=2067253FBC6E6EB41EFB90&site=$site&supp=txt,liens_generiques";
			$result = $this->oLinkFeed->GetHttpResult($api_url,$request);
			$result = XML2Array::createArray($result['content']);
			//            print_r($result);exit;
	
			foreach ($result['listing']['prog'] as $val){
				if (!isset($val['@attributes']['id']) || !$val['@attributes']['id']){
					continue;
				}
				$programId = $val['@attributes']['id'] . "_" . $site;
				$deepUrl = '';
				$tag = $val['tags'];
				if (is_array($tag) && !empty($tag)){
					if (isset($tag['liens_generiques']['element']['track'])) {
						$deepArr = $tag['liens_generiques']['element']['track'];
						if (isset($deepArr['@cdata']) && stripos($deepArr['@cdata'], 'http') !== false){
							$deepUrl = $deepArr['@cdata'];
						}else{
							foreach ($deepArr as $dv){
								if (stripos($dv['@cdata'], 'http') !== false){
									$deepUrl = $dv['@cdata'];
									break;
								}
							}
						}
					}elseif(isset($tag['txt']['element'])){
						if (isset($tag['txt']['element']['track'])){
							$urlArr = $tag['txt']['element']['track'];
						}else{
							$urlArr = $tag['txt']['element'][0]['track'];
						}
						if ($programId == '58009_440267'){
							print_r($urlArr);exit;
						}
	
						foreach ($urlArr as $dlv){
							if (stripos($dlv['@cdata'], 'http') !== false){
								$deepUrl = $dlv['@cdata'];
								break;
							}
						}
					}elseif (isset($tag['liens_generiques']['element'][0]['track'])) {
						$deepArr = $tag['liens_generiques']['element'][0]['track'];
						if (isset($deepArr['@cdata']) && stripos($deepArr['@cdata'], 'http') !== false){
							$deepUrl = $deepArr['@cdata'];
						}else{
							var_dump($tag);
							mydie("Can't get program(idinaff=$programId) affdefaultUrl from api");
						}
					}else{
						var_dump($tag);
						mydie("Can't get program(idinaff=$programId) affdefaultUrl from api");
					}
				}
				if ($deepUrl){
					$arr_return[$programId] = str_replace('{XXX}', '', $deepUrl);
				}else{
					//If still can't get the affdefaulturl, then crawl the img api.
					$idinaff = $val['@attributes']['id'];
					$imgUrl = "http://flux.netaffiliation.com/xmltrack.php?sec=2067253FBC6E6EB41EFB90&site=$site&prog=$idinaff&supp=img";
					$imgResult = $this->oLinkFeed->GetHttpResult($imgUrl,$request);
					$imgResult = XML2Array::createArray($imgResult['content']);
// 					var_dump($imgResult);exit;
					$imgTags = $imgResult['listing']['prog']['tags']['img']['element'];
					$trackArr = isset($imgTags['track']) ? $imgTags['track'] : $imgTags[0]['track'];
					foreach ($trackArr as $tv){
						if (preg_match('@a href="(http.*)"><img@', $tv['@cdata'], $m)){
							$arr_return[$programId] = $m[1];
							break;
						}
					}
				}
			}
		}
		$this->AffDefaultUrlList = $arr_return;
		return $arr_return;
	}
}