<?php
require_once 'text_parse_helper.php';
class LinkFeed_2057_DuoMai
{
	function __construct($aff_id,$oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->file = "programlog_{$aff_id}_".date("Ymd_His").".csv";
	}
	
	
	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
		$this->GetProgramByPage();
		echo "Craw Program end @ ".date("Y-m-d H:i:s")."\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'],'Done');
	}
	
	
	function GetProgramByPage()
	{
		echo "\tGet Program by page start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$program_num = 0;
		$base_program_num = 0;
		
		//step 1,login
		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"], $this->info);
		//export active execl
		$request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get", "postdata" => "",);
		$strUrl = "https://www.duomai.com/index.php?m=siter_act&a=index&export=true&sid={$this->info['APIKey1']}";
		$r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
		$content = $r['content'];
		
		$delimiter = ","; $enclosure = '"';
		$handle = fopen("php://memory", "rw");
		fwrite($handle, $content);
		fseek($handle, 0);
		$header = fgetcsv($handle, 4096,  $delimiter, $enclosure);
        $headerStr = join(',', $header);
		if (stripos($headerStr ,'网站ID,活动分类,活动ID,活动名称,网址,佣金,活动时间(起),活动时间(止),活动分类,RD,结算周期,自定义链接,加密链接') !== false) {
            mydie("The layout of the csv file has changed!");
        }

		$data = array();
		while($fields = fgetcsv($handle, 0, $delimiter, $enclosure))
		{
		    $line = array();
		    if (is_array($fields))
		    {
		        foreach ($fields as $k => $field)
		        {
		            $line[$k] = $field;
		        }
		        $data[] = $line;
		    }
		}
		fclose($handle);
		$prgmActive = array();
		foreach ($data as $prgm){
		    $prgmActive[$prgm[2]] = $prgm;
		}

		//get all program by page
		$strUrl="http://www.duomai.com/index.php?m=siter_act&a=index&p=1";
		$r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
		$content = $r['content'];
		preg_match("/<a class='page_last' href='\/index.php\?m=siter_act&a=index&p=(\d+)' >最后一页<\/a><\/div>/",$content,$matches);
		$totalPageNum = $matches[1];
		if(!$totalPageNum)
		    mydie("Download Page failed.");
		
		$page = 1;
		$PartnershipStatus = array('未申请'=>'NoPartnership','审核中'=>'Pending','已审核'=>'Active','已拒绝'=>'Declined');
		$try = 3;
		while($page<=$totalPageNum){
		    echo "page:".$page." \t";
		    $strUrl=sprintf("http://www.duomai.com/index.php?m=siter_act&a=index&p=%d",$page);
		    $r = $this->oLinkFeed->GetHttpResult($strUrl, $request);
		    if ($r['code'] !== 200){
		    	if ($try){
		    		$try--;
					var_dump($r);
					echo "get $page program page error, try again later";
					sleep(5);
					continue;
				}else{
					mydie(var_dump($r));
				}
			}
		    $fileContent = $this->oLinkFeed->ParseStringBy2Tag($r['content'], array('<div class="list_detail">','<table width="100%" border="0" cellspacing="0" cellpadding="0">'), "</table>");
		    
		    $strLineStart = '<tr>';
		    $nLineStart = 0;
		    while ($nLineStart >= 0){
		        $nLineStart = stripos($fileContent, $strLineStart, $nLineStart);
		        if ($nLineStart === false) break;
		        
		        $lineContent = $this->oLinkFeed->ParseStringBy2Tag($fileContent, '<tr>', '</tr>', $nLineStart);
		        $StatusInAff = "Active";
		        if(preg_match('/<input name="ads_id\[\]" type="checkbox" value="(\d+)" class="check_act_id fl" \/>/', $lineContent,$matMerId)){
		            $strMerID = $matMerId[1];
		        }else{
		            continue;
		        }
		        
		        preg_match_all('/<td>(.*?)<\/td>/is', $lineContent,$mattd);
		        
		        //get Partnership and AffDefaultUrl
		        $Partnership = $PartnershipStatus[trim(strip_tags($mattd[1][10]))];
		        $AffDefaultUrl = '';
		        $AffDefaultDomain = array(
		        		"www.jdoqocy.com", "www.anrdoezrs.net", "www.kqzyfj.com", "www.tqlkg.com", "www.dpbolvw.net", "www.tkqlhce.com", "www.qksrv.net"
		        );
		        if(isset($prgmActive[$strMerID])){
		        	if (preg_match('@^https?://[-A-Za-z0-9\+&#\/%\?=~_|!:,\.;]+[-A-Za-z0-9\+&#\/%=~_|]@i', $prgmActive[$strMerID][10])){
		        		$AffDefaultUrl = $prgmActive[$strMerID][10];
		        	} elseif (preg_match('@^https?://[-A-Za-z0-9\+&#\/%\?=~_|!:,\.;]+[-A-Za-z0-9\+&#\/%=~_|]@i', $prgmActive[$strMerID][11])) {
		        		$AffDefaultUrl = $prgmActive[$strMerID][11];
		        	} elseif (preg_match('@^https?://[-A-Za-z0-9\+&#\/%\?=~_|!:,\.;]+[-A-Za-z0-9\+&#\/%=~_|]@i', @$prgmActive[$strMerID][12])) {
		        		$AffDefaultUrl = $prgmActive[$strMerID][12];
		        	}
		        
		        	foreach ($AffDefaultDomain as $AffDefaultDomainValue){
		        		if(preg_match("/http:\/\/$AffDefaultDomainValue\/click-\d+-\d+\?/is", $AffDefaultUrl,$AffDefaultUrlMatches)){
		        			$AffDefaultUrl = $AffDefaultUrlMatches[0].'sid=[SUBTRACKING]&url=[DEEPURL]';
		        			break;
		        		}
		        		if(preg_match("/http:\/\/$AffDefaultDomainValue\/links\/\d+\/type\/dlg\/sid/is", $AffDefaultUrl,$AffDefaultUrlMatches)){
		        			$AffDefaultUrl = $AffDefaultUrlMatches[0].'/[SUBTRACKING]/[PURE_DEEPURL]';
		        			break;
		        		}
		        	}
		        }
		        if (preg_match('@^https?://[-A-Za-z0-9\+&#\/%\?=~_|!:,\.;]+[-A-Za-z0-9\+&#\/%=~_|]@i', $AffDefaultUrl)) {
		        	$arr_prgm[$strMerID]['AffDefaultUrl'] =addslashes($AffDefaultUrl);
		        }
		        
		        $arr_prgm[$strMerID] = array(
		        		'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
		        		'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
		        		'IdInAff' => $strMerID,
		        		'Partnership' => $Partnership,                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
		        		"AffDefaultUrl" => addslashes($AffDefaultUrl),
		        );

		        if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId']))
		        {
			        $logoUrl = $name = $homePage = '';
			        if(preg_match('/<img src="(.*?)" width="120" height="45" \/>/', trim($mattd[1][1]),$matLogo)) {
                        $logoUrl = 'http://www.duomai.com/' . $matLogo[1];
                    }
			        if(preg_match('/<em><a href="\/index.php\?m=siter_act&a=view&ads_id=(\d+)" target="_blank" class="blue">(.*?)<\/a><\/em>/is',trim($mattd[1][3]),$matName)){
			            $name=$matName[2];
			        }
                    if(isset($prgmActive[$strMerID])){
                        if (preg_match('@^https?://[-A-Za-z0-9\+&#\/%\?=~_|!:,\.;]+[-A-Za-z0-9\+&#\/%=~_|]@i', $prgmActive[$strMerID][3])){
                            $homePage = $prgmActive[$strMerID][3];
                        } elseif (preg_match('@^https?://[-A-Za-z0-9\+&#\/%\?=~_|!:,\.;]+[-A-Za-z0-9\+&#\/%=~_|]@i', $prgmActive[$strMerID][4])) {
                            $homePage = $prgmActive[$strMerID][4];
                        }elseif (preg_match('@^https?://[-A-Za-z0-9\+&#\/%\?=~_|!:,\.;]+[-A-Za-z0-9\+&#\/%=~_|]@i', $prgmActive[$strMerID][5])) {
                            $homePage = $prgmActive[$strMerID][5];
                        }
                    }

			        $CategoryExt=trim($mattd[1][5]);
			        $CommissionExt = trim($mattd[1][6]);
			        $ReturnDays = (int)trim($mattd[1][7]);
			        $validityDate = explode('至',trim($mattd[1][8]));
			        $JoinDate = $validityDate[0];
			        $DropDate = $validityDate[1];

			        //详情页
                    echo $strMerID . "\t";
                    $detailUrl = "http://www.duomai.com/index.php?m=siter_act&a=view&ads_id=$strMerID";
                    $cacheFileName = date('Ymd') . "_detail_$strMerID.cache";
			        $detail_result = $this->oLinkFeed->GetHttpResultAndCache($detailUrl, $request, $cacheFileName, '多麦广告联盟 Copyright');
			        if(preg_match('/<span><a href="(.*?)" target="_blank">(.*?)<\/a><\/span>/is',$detail_result,$matHomepage)){
			            $homePage = $matHomepage[1];
			        }
			        $Description = trim($this->oLinkFeed->ParseStringBy2Tag($detail_result, array('<td colspan="3">','<div>'), "</div>"));
			        $TermAndCondition = trim($this->oLinkFeed->ParseStringBy2Tag($detail_result, array('控制条件：','</td>'), "</td>"));
			        
			        $SEMPolicyExt = '';
			        $sem_res = trim($this->oLinkFeed->ParseStringBy2Tag($detail_result, array('是否允许购买关键字','<dd>'), "</dd>"));
			        switch ($sem_res){
			        	case '否':
			        		$SEMPolicyExt = 'disallowed';
			        		break;
			        	case '是':
			        		$SEMPolicyExt = 'allowed';
			        		break;
			        	default:
			        		print_r($detail_result);
			        		mydie('find new SEM Policy');
			        }
			        
			        $arr_prgm[$strMerID] += array(
						'CrawlJobId' => $this->info['crawlJobId'],
			            "Name" => addslashes(trim($name)),
			            "Homepage" => addslashes($homePage),
			            "CategoryExt" => addslashes($CategoryExt),
			            //"TargetCountryExt" => '',
			            "JoinDate" => addslashes($JoinDate),
			            "DropDate" => addslashes($DropDate),
			            "TermAndCondition" => addslashes($TermAndCondition),
			            "StatusInAff" => $StatusInAff,						//'Active','TempOffline','Inactive'
			            "Description" => addslashes($Description),
			            "CommissionExt" => addslashes($CommissionExt),
			            "CookieTime" => addslashes($ReturnDays),
			            //"SupportDeepUrl" => "YES",
			            'LogoUrl' => addslashes($logoUrl),
			        	"SEMPolicyExt" => $SEMPolicyExt
			        );
			        $base_program_num++;
		        }
		        $program_num++;
		        if(count($arr_prgm) >= 1){
		        	$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
		        	$arr_prgm = array();
		        }
		    }
		    
		    $page++;
		}
		if(count($arr_prgm)){
			$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
		    unset($arr_prgm);
		}
		
		if($program_num < 10){
		    mydie("die: program count < 10, please check program.\n");
		}
		
		echo "\tUpdate ({$base_program_num}) base program.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
	}
	
}