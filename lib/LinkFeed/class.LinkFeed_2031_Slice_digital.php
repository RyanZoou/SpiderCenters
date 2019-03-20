<?php
require_once 'text_parse_helper.php';
require_once 'XML2Array.php';
class LinkFeed_2031_Slice_digital
{
	function __construct($aff_id, $oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->getStatus = false;
		$this->file = "programlog_{$aff_id}_".date("Ymd_His").".csv";
	}
	
	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull'];
		$this->GetProgramByApi();
		echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'],'Done');
	}
	
	function GetProgramByApi()
	{
		echo "\tGet Program by Api start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$base_program_num = 0;
		$program_num = 0;
		
		$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "get",
				"addheader" => array("API-Key: {$this->info['APIKey1']}"),
		);
		$page = 1;
		$limit = 100;
		$HasNextPage = true;
		while ($HasNextPage)
		{
			$list_url = "http://api.slice.digital/3.0/offers?page=$page&limit=$limit";
			$list_r = $this->oLinkFeed->GetHttpResult($list_url, $request);
			$list_r = json_decode($list_r['content'], true);
			//var_dump($list_r);exit;
			if ($list_r['status'] != 1)
				mydie("die: Crawl status is error");
			$count = $list_r['pagination']['total_count'];
			if (($page * $limit) >= $count)
				$HasNextPage = false;
			foreach ($list_r['offers'] as $v)
			{
				$IdInAff = $v['id'];
				
				$AffDefaultUrl = $v['link'];
				$arr_prgm[$IdInAff] = array(
						'AccountSiteID' => $this->info["AccountSiteID"],
						'BatchID' => $this->info['batchID'],
						'IdInAff' => $IdInAff,
						'Partnership' => 'Active',
						'AffDefaultUrl' => addslashes($AffDefaultUrl)
				);
				
				if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId']))
				{
				
					$prgm_name = $v['title'];
					
					//Homepage
					$final_url = $this->oLinkFeed->findFinalUrl($v['preview_url']);
					$Homepage_arr = parse_url($final_url);
					if (isset($Homepage_arr['scheme'])){
						$Homepage = $Homepage_arr['scheme'].'://'.$Homepage_arr['host'];
					}else {
						$Homepage = '';
					}
					
					$desc = $v['description'];
					$LogoUrl = $v['logo'];
					
					//CategoryExt
					$category_arr = array();
					foreach ($v['categories'] as $category)
						$category_arr[] = $category;
					$CategoryExt = implode(EX_CATEGORY, $category_arr);
					
					//TargetCountryExt
					$country_arr = array();
					foreach ($v['countries'] as $country)
						$country_arr[] = $country;
					$TargetCountryExt = implode(',', $country_arr);
					
					//CommissionExt
					$commission_arr = array();	
					foreach ($v['payments'] as $commission)
					{
						if ($commission['type'] == 'fixed')
							$commission_arr[] = $commission['title'].': '.$commission['currency'].' '.$commission['revenue'];
						if ($commission['type'] == 'percent')
							$commission_arr[] = $commission['title'].': '.$commission['revenue'].'%';
					}
					$CommissionExt = implode('|', $commission_arr);
					
					//SEMPolicyExt
					$SEMPolicyExt = '';
					if (isset($v['sources'][12]) && $v['sources'][12]['allowed'] == 1){
						$SEMPolicyExt = 'Allowed';
					}else {
						$SEMPolicyExt = 'Disallowed';
					}
					if (stripos($v['description'], 'Paid Search')){
						$SEMPolicyExt .= "\n".trim($this->oLinkFeed->ParseStringBy2Tag($v['description'],array('Paid Search','</p>'),'<p><strong>'));
					}
					
					//SupportDeepurl
					$detail_url = "http://api.slice.digital/3.0/offer/$IdInAff";
					$detail_r = $list_r = $this->oLinkFeed->GetHttpResult($detail_url, $request);
					$detail_r = json_decode($detail_r['content'], true);
					if ($detail_r['offer']['allow_deeplink'])
						$SupportDeepurl = 'YES';
					else 
						$SupportDeepurl = 'NO';
					
					$arr_prgm[$IdInAff] += array(
							'CrawlJobId' => $this->info['crawlJobId'],
							"Name" => addslashes($prgm_name),
							"CategoryExt" => addslashes($CategoryExt),
							"TargetCountryExt" => $TargetCountryExt,
							"Homepage" => addslashes($Homepage),
							"Description" => addslashes($desc),
							"EPCDefault" => $v['epc'],
							"CommissionExt" => addslashes($CommissionExt),
							"StatusInAff" => 'Active',						//'Active','TempOffline','Offline'
							"SupportDeepUrl" => $SupportDeepurl,
							"LogoUrl" => addslashes($LogoUrl),
							"SEMPolicyExt" => addslashes($SEMPolicyExt)
					);
					$base_program_num++;
				}
				$program_num++;
				if(count($arr_prgm) >= 100){
					$objProgram->InsertProgramBatch($this->info['NetworkID'],$arr_prgm);
					$arr_prgm = array();
				}
			}
			if(count($arr_prgm)){
				$objProgram->InsertProgramBatch($this->info['NetworkID'],$arr_prgm);
				$arr_prgm = array();
			}
			$page++;
		}
	
		echo "\tGet Program by api end\r\n";
		
		if($program_num < 10){
			mydie("die: program count < 10, please check program.\n");
		}
		
		echo "\tUpdate ({$base_program_num}) base program.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
	}
}
?>