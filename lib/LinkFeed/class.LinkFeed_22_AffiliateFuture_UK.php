<?php
class LinkFeed_22_AffiliateFuture_UK
{
	function __construct($aff_id, $oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->isFull = true;
	}
	
	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->oLinkFeed->clearHttpInfos($this->info["AccountSiteID"]);
        $this->isFull = $this->info['isFull'];
        $this->GetProgramByPage();
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
	}
	
	function LoginIntoAffService()
	{
		//get para __VIEWSTATE and then process default login
		if(!isset($this->info["AffLoginPostStringOrig"])) $this->info["AffLoginPostStringOrig"] = $this->info["LoginPostString"];
	
		$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "post",
				"postdata" => "",
		);
	
		$strUrl = $this->info["LoginUrl"];
		$r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
		$result = $r["content"];
	
		if(stripos($result, "__VIEWSTATE") === false) mydie("die: login for LinkFeed_22_AffiliateFuture_UK failed, __VIEWSTATE not found\n");
	
		$nLineStart = 0;
		$strViewState = $this->oLinkFeed->ParseStringBy2Tag($result, 'id="__VIEWSTATE" value="', '" />', $nLineStart);
	
		if($strViewState === false) mydie("die: login for LinkFeed_22_AffiliateFuture_UK failed, __VIEWSTATE not found\n");
	
		$this->info["LoginPostString"] = '__VIEWSTATE=' . urlencode($strViewState) . '&'. $this->info["AffLoginPostStringOrig"];
	
// 		print_r($this->info);exit;
		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info,2,true,true,false);
		return "stophere";
	}
	
	function GetCategoryByListPage(){
		echo "\tGet Category by page start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = array();
	
// 		print_r($this->info);exit;
		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info,1,false);
		$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "get",
				"postdata" => "",
		);
		$strUrl = "http://afuk.affiliate.affiliatefuture.co.uk/merchants/Default.aspx";
		$r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
		$result = $r["content"];
		preg_match_all('/VERTICAL-ALIGN(.*?[\s\S]*?)<\/table>/',$result,$tables);
		if(!is_array($tables[1])) mydie("get category page failed !");
		$programs = array();
		$baseurl = "afuk.affiliate.affiliatefuture.co.uk/merchants/";
		foreach ($tables[1] as $key=> $table){
			//一级分类
			preg_match('/<a.*?boldblue.*?href="(.*?)".*?>(.*?)<\/a>/',$table,$first);
			//二级分类
			preg_match_all('/text.*?href="(.*?)".*?>(.*?)<\/a>/',$table,$second);
			if(!isset($first[1]) || empty($first[1])) continue;
			if(!isset($second[1]) && !$second[1]){
				//一级分类信息
				$info = $this->oLinkFeed->GetHttpResult($baseurl.$first[1],$request);
				$res = $info['content'];
				preg_match_all('/cat=[0-9]*?&id=(\d+)/',$res,$pid);
				if(isset($pid[1])){
					foreach ($pid[1] as $p){
						$programs[$p]['SubCate'] = '';
						$programs[$p]['MainCate'] = $first[2];
					}
                }
	
			}else{
				//二级分类信息
				foreach($second[1] as $k => $cateUrl){
					$info = $this->oLinkFeed->GetHttpResult($baseurl.$cateUrl,$request);
					$res = $info['content'];
					preg_match_all('/cat=[0-9]*?&id=(\d+)/',$res,$pid);
					if(isset($pid[1])){
						foreach ($pid[1] as $p){
							$programs[$p]['SubCate'] = $second[2][$k];
							$programs[$p]['MainCate'] = $first[2];
						}
					}
				}
			}
		}
		return $programs;
	}
	
	function GetProgramByPage()
	{
		$CategoryList = $this->GetCategoryByListPage();
	
		echo "\tGet Program by page start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = $record_arr = array();
		$program_num = $base_program_num = 0;
		//step 1,login
		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info,1,false);
	
		$arr_return = array("AffectedCount" => 0,"UpdatedCount" => 0,);
	
		$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "get",
				"postdata" => "",
		);
	
		//Step1 Get all approval merchants
		$strUrl = "http://afuk.affiliate.affiliatefuture.co.uk/programmes/MerchantsJoined.aspx";
		$r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
		$result = $r["content"];
	
		$strLineStart = '<tr onmouseover="bgColor=\'#E7EBF4\'" onmouseout="bgColor=\'#ffffff\'">';
	
		$nLineStart = 0;
		while ($nLineStart >= 0)
		{
			$nLineStart = stripos($result, $strLineStart, $nLineStart);
			if ($nLineStart === false) break;
	
			$Homepage = $this->oLinkFeed->ParseStringBy2Tag($result, array('merchantLnk', 'href="'), '"', $nLineStart);
			//name
			$strMerName = $this->oLinkFeed->ParseStringBy2Tag($result, 'target="_blank">', '</a>', $nLineStart);
			if ($strMerName === false) break;
			$strMerName = trim($strMerName);
				
			$StatusInAff = 'Active';
			if(stripos($strMerName,"closed") !== false){
				$StatusInAff = 'Offline';
				$strMerName = trim(str_ireplace("closed","",$strMerName));
			}
			if(stripos($strMerName,"paused") !== false){
				$StatusInAff = 'Offline';
				$strMerName = trim(str_ireplace("paused","",$strMerName));
			}
	
			$str2id = $this->oLinkFeed->ParseStringBy2Tag($result,'getlinks_url.aspx?p=','"',$nLineStart);
			if($str2id === false) break;
			$arr = explode("&amp;id=",$str2id);
			if(sizeof($arr) != 2) mydie("die: wrong str2id $str2id\n");
			list($programmeid,$strMerID) = $arr;
			if(!is_numeric($programmeid) || !is_numeric($strMerID)) mydie("die: wrong str2id $str2id\n");
	
			$AffDefaultUrl = trim($this->oLinkFeed->ParseStringBy2Tag($result, array('<textarea', '>'),'<',$nLineStart));

			if (!isset($record_arr[$strMerID])) {
			    $record_arr[$strMerID] = '';
            } else {
			    continue;
            }

			$arr_prgm[$strMerID] = array(
					'AccountSiteID' => $this->info["AccountSiteID"],
					'BatchID' => $this->info['batchID'],
					'IdInAff' => $strMerID,
					'Partnership' => "Active",
					'AffDefaultUrl' => addslashes($AffDefaultUrl)
			);
			
			if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
				
				$dLineStart = 0;
				$prgm_url = "http://afuk.affiliate.affiliatefuture.co.uk/programmes/Details.aspx?id=$strMerID";
				$cache_name = "detail_{$strMerID}_" . date('ymdh') . '.cache';
                $prgm_detail = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request, $cache_name);
				$desc = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<div class="wordwrap">', '</div>', $dLineStart));
				$TermAndCondition = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, array('<div id="tabs-2" class="wordwrap"', '>'), '</div>', $dLineStart));
				$logoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<img id="imgAdvertiserLogo" src="', '"', $dLineStart));
				$CookieTime = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<div id="divStatCookieLength" class="stat">', ' ', $dLineStart));
				$desc .= '\r<br>'.trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<div id="gvProgrammes_ctl02_progDetails" class="description" style="display: none">', '</div>', $dLineStart));
				$CommissionExt = trim($this->oLinkFeed->ParseStringBy2Tag($prgm_detail, '<b>', '</b>', $dLineStart));
					
					
				$arr_prgm[$strMerID] += array(
                    "CrawlJobId" => $this->info['crawlJobId'],
                    "Name" => addslashes(html_entity_decode($strMerName)),
                    "StatusInAff" => $StatusInAff,						//'Active','TempOffline','Offline'
                    "Description" => addslashes($desc),
                    "Homepage" => $Homepage,
                    "TermAndCondition" => addslashes($TermAndCondition),
                    "LastUpdateTime" => date("Y-m-d H:i:s"),
                    "SupportDeepUrl" => "YES",
                    "AffDefaultUrl" => addslashes($AffDefaultUrl),
                    "Remark" => $programmeid, //here,we save programmeid to MerchantRemark
                    "CommissionExt" => addslashes($CommissionExt),
                    "CategoryExt" => addslashes($CategoryList[$strMerID]['MainCate'].'-'.$CategoryList[$strMerID]['SubCate']),
                    "LogoUrl" => addslashes($logoUrl),
                    "CookieTime" => $CookieTime,
				);
				$base_program_num++;
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
	
		echo "\tGet Program by page end\r\n";
	
		if($program_num < 10){
			mydie("die: program count < 10, please check program.\n");
		}
	
		echo "\tUpdate ({$base_program_num}) base programs.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
// 		echo "\tSet program country int.\r\n";
// 		$objProgram->setCountryInt($this->info["AffId"]);
	}
}