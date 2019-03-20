<?php 
require_once 'text_parse_helper.php';

class LinkFeed_351_Moreniche
{
	function __construct($aff_id,$oLinkFeed)
	{
		$this->oLinkFeed = $oLinkFeed;
		$this->info = $oLinkFeed->getAffById($aff_id);
		$this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
		$this->getStatus = false;
		/*
		if (SID == 'bdg01')
		{
			$this->AffiliateID = '134971';
		}else{
			
		}
		*/
	}
	
	function GetProgramFromAff()
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Program start @ {$check_date}\r\n";
		$this->isFull = $this->info['isFull']; 
		$this->GetProgramByPage();
		echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'],'Done');
	}
	
	function GetProgramByPage()
	{
		echo "\tGet Program by page start\r\n";
		$objProgram = new ProgramDb();
		$arr_prgm = array();
		$program_num = 0;
		$base_program_num = 0;
		
		//1.login
		$this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info,2);
		
		//get merchants
		$request = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				"method" => "get",
				"postdata" => '',
		);
		//get sem policy
		/*
		$sem_url = "https://moreniche.com/advertising-policy/";
		$sem_res = $this->oLinkFeed->GetHttpResult($sem_url,$request);
		$sem_res = $sem_res['content'];
		$sem_pub = trim($this->oLinkFeed->ParseStringBy2Tag($sem_res,"allows you to bid on ‘brand’ keywords. If you break these terms your account may be suspended.</p>",'</div>'));
		if (empty($sem_pub)) mydie('get public sem policy error');
		*/
		
		$url = 'https://app.moreniche.com/merchants';
		$re = $this->oLinkFeed->GetHttpResult($url,$request);
		$re = $re['content'];
		//print_r($re);exit;
		$programStr = $this->oLinkFeed->ParseStringBy2Tag($re, array('<table', '>'), '</table');
        $programStr = preg_replace('@>\s+<@', '><', $programStr);
		$result = explode('</tr><tr', $programStr);
		foreach ($result as $k => $v)
		{
			$startLine = 0;
			$strMerID = intval(trim($this->oLinkFeed->ParseStringBy2Tag($v, '<a href="/merchants/info/', '"', $startLine)));
			if (!$strMerID) {
				continue;
			}
			echo "$strMerID\t";

			$LogoUrl = trim($this->oLinkFeed->ParseStringBy2Tag($v, '<img src="', '"', $startLine));
			$detail_url = 'https://app.moreniche.com'.trim($this->oLinkFeed->ParseStringBy2Tag($v, '<a href="', '"', $startLine));
			$strMerName = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($v, array('<h4', '>'), '</h4', $startLine)));
			$CategoryExt = trim($this->oLinkFeed->ParseStringBy2Tag($v, array('<small', '>'), '</small', $startLine));
			$CommissionExt = trim($this->oLinkFeed->ParseStringBy2Tag($v, array('<small', '>'), '</small', $startLine));
			$CommissionExt = trim(str_replace(array(' ','\n','\r','<br>'), '', $CommissionExt));
			$countrty_arr = array();
			while (1)
			{
				$countrty = trim($this->oLinkFeed->ParseStringBy2Tag($v, '<img src="/images/flags/', '.', $startLine));
				if (empty($countrty))
					break;
				$countrty_arr[] = str_replace('-', ' ', $countrty);
			}
			$TargetCountryExt = implode(',', $countrty_arr);
			
			//get program from detailPage
			$detail_r = $this->oLinkFeed->GetHttpResult($detail_url,$request);
			$detail_r = $detail_r['content'];
			/*
			$SEMPolicyExt = trim($this->oLinkFeed->ParseStringBy2Tag($detail_r,'<small>PPC Marketing:','</small>'));
			if ($SEMPolicyExt == '<a href="https://moreniche.com/advertising-policy" target="_blank">Network Policy</a>'){
				$SEMPolicyExt = $sem_pub;
			}elseif (strpos($SEMPolicyExt, 'Not Allowed')){
				$SEMPolicyExt = "Not Allowed";
			}else {
				mydie('find new sem policy plz check network');
			}
			*/
			$Homepage = trim($this->oLinkFeed->ParseStringBy2Tag($detail_r, array('<div class="avatar-xxl">', '<a href="'), '"'));
			$EPCDefault = trim($this->oLinkFeed->ParseStringBy2Tag($detail_r, '<small>Avg EPC: $', '<'));
			$desc = trim(strip_tags($this->oLinkFeed->ParseStringBy2Tag($detail_r, '</a></h1>', '</div>')));
			//get AffDefaultUrl
			$link_url = "https://app.moreniche.com/merchants/links/$strMerID";
			$link_r = $this->oLinkFeed->GetHttpResult($link_url,$request);
			$link_r = $link_r['content'];
			$AffDefaultUrl = trim(html_entity_decode($this->oLinkFeed->ParseStringBy2Tag($link_r, '<code>', '<')));
			
			$arr_prgm[$strMerID] = array(
					'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
					'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
					'IdInAff' => $strMerID,
					'Partnership' => 'Active',                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
					"AffDefaultUrl" => addslashes($AffDefaultUrl),
			);
			
			if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId']))
			{
				
				$arr_prgm[$strMerID] += array(
						'CrawlJobId' => $this->info['crawlJobId'],
						"Name" => addslashes($strMerName),
						"TargetCountryExt" => $TargetCountryExt,
						"EPCDefault" => $EPCDefault,
						"StatusInAff" => 'Active',                        //'Active','TempOffline','Offline'
						"Description" => addslashes($desc),
						"Homepage" => $Homepage,
						"CommissionExt" => addslashes($CommissionExt),
						"CategoryExt" => addslashes($CategoryExt),
						//"SupportDeepUrl"=>'YES',
						"LogoUrl" => addslashes($LogoUrl),
// 						"SEMPolicyExt" => addslashes($SEMPolicyExt)
				);
				$base_program_num++;
				
			}
			$program_num++;
			if(count($arr_prgm) >= 100)
			{
				$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
// 				$objProgram->updateProgram($this->info["AffId"], $arr_prgm);
				$arr_prgm = array();
			}
		}
		if(count($arr_prgm))
		{
			$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
// 			$objProgram->updateProgram($this->info["AffId"], $arr_prgm);
			unset($arr_prgm);
		}
		
		echo "\tGet Program by page end\r\n";
		
		if($program_num < 10){
			mydie("die: program count < 10, please check program.\n");
		}
		echo "\tUpdate ({$base_program_num}) base program.\r\n";
		echo "\tUpdate ({$program_num}) program.\r\n";
	}

    function GetTransactionFromAff($start_date, $end_date)
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

        $objTransaction = New TransactionDb();
        $arr_transaction = $arr_find_Repeated_transactionId = array();
        $tras_num = 0;

//        1.login
        $this->oLinkFeed->LoginIntoAffService($this->info["AccountSiteID"],$this->info,2);

        $url = 'https://app.moreniche.com/sales/builder';
        $b_date = date('d/m/Y', strtotime($start_date));
        $e_date = date('d/m/Y', strtotime($end_date));
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => 'post', 'postdata' => 'dateRange=CUSTOM&fromDate='.urlencode($b_date).'&toDate='.urlencode($e_date).'&export_format=');

		$cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "data_" . date("YmdH") . "_Transaction.cache", 'Transaction', true);
		if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
			echo "req => {$url} \n";
			$result = $this->oLinkFeed->GetHttpResult($url, $request);
			if ($result['code'] != 200){
				mydie("Download cache file failed.");
			}
		}

        $tbody_header_str = preg_replace('@>\s+<@', '><', $this->oLinkFeed->ParseStringBy2Tag($result['content'], '<thead>', '</thead>'));
        $tbody_str = preg_replace('@>\s+<@', '><', $this->oLinkFeed->ParseStringBy2Tag($result['content'], '<tbody>', '</tbody>'));
        $tbody_header_arr= explode('</th><th>', $tbody_header_str);
        $tbody_arr = explode('</tr><tr', $tbody_str);
        $tbody_header_arr = array_map(function ($c){return trim($c);}, $tbody_header_arr);
        $tbody_header_arr[0] = preg_replace('@<tr><th>@', '', $tbody_header_arr[0]);
        array_pop($tbody_header_arr);

        if (strcmp(join(',', $tbody_header_arr), 'TID,Offer,Time/Date,CTS,Commission,Country,Referral,(s1) customValue,(s2) Campaigns,(s3) Keywords,(s4) customValue,(s5) customValue,Tracking Method,SKUs,SkUs Description,Order Total,Click Method,Coupon,Commission Rate,secs,ARM,ARM %,ARM fee') != 0){
        	mydie("The filed list have changed, please change the crawl code.");
		}

        if (empty($tbody_arr)) {
        	mydie("Can't get transaction data.");
		}
        $empty_num = 0;

		foreach ($tbody_arr as $val) {
			$strPos = 0;
            $TransactionId = $TID = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'all_offers"><td>','</', $strPos));

            $OfferId = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'merchants/info/','"', $strPos));
            $Offer = trim($this->oLinkFeed->ParseStringBy2Tag($val, '>','</', $strPos));
            $TimeDate = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'align="center">','</', $strPos));
            $CTS = trim($this->oLinkFeed->ParseStringBy2Tag($val, '<td>','</', $strPos));
            $Commission = trim($this->oLinkFeed->ParseStringBy2Tag($val, '"right">','</', $strPos));
            $Country = trim($this->oLinkFeed->ParseStringBy2Tag($val, '"hidden">','</', $strPos));
            $Referral = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'align="left">','</', $strPos));
            $s1_customValue = trim($this->oLinkFeed->ParseStringBy2Tag($val, array('<td','>'),'<', $strPos));
            $s2_Campaigns = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'align="left">','</', $strPos));

            //sid
			if (!$s2_Campaigns) {
                $empty_num ++;
			}

            $s3_Keywords = trim($this->oLinkFeed->ParseStringBy2Tag($val, array('<td','>'),'<', $strPos));
            $s4_customValue = trim($this->oLinkFeed->ParseStringBy2Tag($val, array('<td','>'),'<', $strPos));
            $s5_customValue = trim($this->oLinkFeed->ParseStringBy2Tag($val, array('<td','>'),'<', $strPos));
            $TrackingMethod = trim($this->oLinkFeed->ParseStringBy2Tag($val, array('<td','>'),'<', $strPos));
            $SKUs = trim($this->oLinkFeed->ParseStringBy2Tag($val, array('style="white', '>'),'</', $strPos));
            $SkUsDescription = trim($this->oLinkFeed->ParseStringBy2Tag($val, array('style="white', '>'),'</', $strPos));
            $OrderTotal = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'align="left">','</', $strPos));
            $ClickMethod = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'align="left">','</', $strPos));
            $Coupon = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'align="left">','</', $strPos));
            $CommissionRate = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'align="left">','</', $strPos));
            $secs = trim($this->oLinkFeed->ParseStringBy2Tag($val, 'align="left">','</', $strPos));
            $ARM = trim($this->oLinkFeed->ParseStringBy2Tag($val, '<td>','</', $strPos));
            $ARM_percent = trim($this->oLinkFeed->ParseStringBy2Tag($val, '<td>','</', $strPos));
            $ARMfee = trim($this->oLinkFeed->ParseStringBy2Tag($val, '<td>','</', $strPos));

            if (!$TransactionId) {
				continue;
			}
			if (isset($arr_find_Repeated_transactionId[$TransactionId])) {
				mydie("The transactionId={$TransactionId} have earldy exists!");
			} else {
				$arr_find_Repeated_transactionId[$TransactionId] = '';
			}

			$arr_transaction[$TransactionId] = array(
				'AccountSiteID' => $this->info["AccountSiteID"],
				'BatchID' => $this->info['batchID'],
				'TransactionId' => addslashes($TransactionId),
                'TID' => addslashes($TID),
                'OfferId' => addslashes($OfferId),
                'Offer' => addslashes($Offer),
                'TimeDate' => addslashes($TimeDate),
                'CTS' => addslashes($CTS),
                'Commission' => addslashes($Commission),
                'Country' => addslashes($Country),
                'Referral' => addslashes($Referral),
                's1_customValue' => addslashes($s1_customValue),
                's2_Campaigns' => addslashes($s2_Campaigns),
                's3_Keywords' => addslashes($s3_Keywords),
                's4_customValue' => addslashes($s4_customValue),
                's5_customValue' => addslashes($s5_customValue),
                'TrackingMethod' => addslashes($TrackingMethod),
                'SKUs' => addslashes($SKUs),
                'SkUsDescription' => addslashes($SkUsDescription),
                'OrderTotal' => addslashes($OrderTotal),
                'ClickMethod' => addslashes($ClickMethod),
                'Coupon' => addslashes($Coupon),
                'CommissionRate' => addslashes($CommissionRate),
                'secs' => addslashes($secs),
                'ARM' => addslashes($ARM),
                'ARM_percent' => addslashes($ARM_percent),
                'ARMfee' => addslashes($ARMfee)
			);
			$tras_num ++;

			if ($empty_num > 20) {
				mydie("Transaction page have changed!");
			}

			if (count($arr_transaction) >= 100) {
				$objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
				$arr_transaction = array();
			}
		}

        if (count($arr_transaction) > 0) {
            $objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
            unset($arr_transaction);
        }
        unset($arr_find_Repeated_transactionId);

        echo "\tUpdate ({$tras_num}) Transactions.\r\n";
        echo "Craw Transaction end @ " . date("Y-m-d H:i:s") . "\r\n";

        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

}
?>
