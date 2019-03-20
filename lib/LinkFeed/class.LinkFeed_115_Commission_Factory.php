<?php
require_once 'text_parse_helper.php';
class LinkFeed_115_Commission_Factory
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->username = urlencode($this->info["UserName"]);
        $this->password = urlencode($this->info["Password"]);
    }

    function Login()
    {
        $strUrl = "https://dashboard.commissionfactory.com/LogIn/";
        $request = array(
            "method" => "get",
            "postdata" => "",
        );
        $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);

        $result = $r["content"];
        $__EVENTVALIDATION = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__EVENTVALIDATION"', 'value="'), '"'));
        $__VIEWSTATE = urlencode($this->oLinkFeed->ParseStringBy2Tag($result, array('name="__VIEWSTATE"', 'value="'), '"'));
        $strUrl = "https://dashboard.commissionfactory.com/LogIn/";
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => "post",
            "postdata" => "",
        );
        $request["postdata"] = "ctl10=ctl10%7CbtnLogIn&__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE={$__VIEWSTATE}&__VIEWSTATEGENERATOR=25748CED&__EVENTVALIDATION={$__EVENTVALIDATION}&txtLoginUsername={$this->username}&txtLoginPassword={$this->password}&txtExpiredUsername=&txtExpiredPasswordOld=&txtExpiredPasswordNew=&txtExpiredPasswordConfirm=&txtForgotUsername=&txtResetUsername=&txtResetPasswordNew=&txtResetPasswordConfirm=&__ASYNCPOST=true&btnLogIn=Log%20In";
        $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
        $result = $r["content"];
        if(stripos($result,'Affiliate') === false)
        {
            mydie("die: failed to login.\n");
        }
        else
        {
            echo "login succ.\n";
        }
    }

    function GetProgramFromAff()
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Program start @ {$check_date}\r\n";
        $this->isFull = $this->info['isFull'];
        $this->GetProgramByApi();
        echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
        $this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
    }

    function GetProgramByApi()
    {
        echo "\tGet Program by api start\r\n";
        $objProgram = new ProgramDb();
        $arr_prgm = $arr_prgm_name = array();
        $program_num = $base_program_num = 0;
        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);

        $this->Login();
        $strUrl = "https://api.commissionfactory.com/V1/Affiliate/Merchants?apiKey=".$this->info['APIKey1'];
        $r = $this->oLinkFeed->GetHttpResult($strUrl,$request);
        $result = $r["content"];
        $result = json_decode($result);

        foreach($result as $v){
            $strMerID = intval($v->Id);
            if($strMerID < 1) {
                continue;
            }
            $StatusInAffRemark = trim($v->Status);
            if($StatusInAffRemark == "Joined"){
                $Partnership = "Active";
            }elseif($StatusInAffRemark == "Pending"){
                $Partnership = "Pending";
            }elseif($StatusInAffRemark == "Not Joined"){
                $Partnership = "NoPartnership";
            }else{
                $Partnership = "NoPartnership";
            }
            $AffDefaultUrl = trim($v->TrackingUrl);

            $arr_prgm[$strMerID] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $strMerID,
                'Partnership' => $Partnership,
                "AffDefaultUrl" => addslashes($AffDefaultUrl),
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {
                $strMerName = trim($v->Name);
                $Homepage = trim($v->TargetUrl);
                $CategoryExt = trim($v->Category);
                $desc = trim($v->Summary);
                $TermAndCondition = trim($v->TermsAndConditions);
                $LogoUrl = trim($v->AvatarUrl);
                $TargetMarket = trim($v->TargetMarket);
                if ($v->CommissionType == 'Percent per Sale')
                    $CommissionExt = trim($v->CommissionRate) . '%';
                else
                    $CommissionExt = trim($v->CommissionRate) . " " . trim($v->CommissionType);

                $JoinDate = trim($v->DateCreated);
                $JoinDate = date("Y-m-d H:i:s", strtotime($JoinDate));

                $prgm_url = "http://dashboard.commissionfactory.com/Affiliate/Merchants/{$strMerID}/";
                $cache_file = 'Merchants_detail_' . $strMerID . '_' . date('YmdH');
                $re = $this->oLinkFeed->GetHttpResultAndCache($prgm_url, $request, $cache_file);
                $restrictions = trim($this->oLinkFeed->ParseStringBy2Tag($re,array('Program Restrictions','</h3>'),'<div id="cphBody_cphBody_ctl01">'));
                $restrictions = explode('</div>', $restrictions);
                $support = '';
                foreach($restrictions as $restriction){
                	if (strpos($restriction,'Content Site')){
                		if(strpos($restriction,'divTagPill allowed')){
                			$support .= 'Content Site|||';
                		}
                	}
                	if (strpos($restriction,'Coupon Sites')){
                		if(strpos($restriction,'divTagPill allowed')){
                			$support .= 'Coupon Site|||';
                		}
                	}
                }
                $support = rtrim($support,'|||');
                
                if (strpos($re, "<p>Paid Search *</p>")){
                	if (strpos($re, '<div id="cphBody_cphBody_pnlPayPerClick">')){
                		$SEMPolicyExt = "allowed\n";
                		$SEMPolicyExt .= trim($this->oLinkFeed->ParseStringBy2Tag($re,'<div id="cphBody_cphBody_pnlPayPerClick">','</div>'));
                	}else {
                		$SEMPolicyExt = 'allowed';
                	}
                }else {
                	$SEMPolicyExt = 'disallowed';
                }
                $PaymentDays = intval(trim($this->oLinkFeed->ParseStringBy2Tag($re, array('<span class="item">Tracking Period</span>', '<span class="value">'), '</span>')));
                $CookieTime = intval(trim($this->oLinkFeed->ParseStringBy2Tag($re, array('<span class="item">per sale</span>', '<span class="value">'), '</span>')));
                $arr_prgm[$strMerID] += array(
                    'CrawlJobId' => $this->info['crawlJobId'],
                    "Name" => addslashes(trim($strMerName)),
                    "Homepage" => $Homepage,
                    "CategoryExt" => addslashes($CategoryExt),
                    "JoinDate" => $JoinDate,
                    "CommissionExt" => addslashes($CommissionExt),
                    "StatusInAffRemark" => addslashes($StatusInAffRemark),
                    "Description" => addslashes($desc),
                    "TermAndCondition" => addslashes($TermAndCondition),
                    "LogoUrl" => addslashes($LogoUrl),
                    "PaymentDays" => addslashes($PaymentDays),
                    "CookieTime" => addslashes($CookieTime),
                	"SEMPolicyExt" => addslashes($SEMPolicyExt),
                    'TargetCountryExt' => addslashes($TargetMarket),
                	"SupportType" => $support
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

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

	function getTransactionFromAff($start_date, $end_date)
	{
		$check_date = date("Y-m-d H:i:s");
		echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

		$objTransaction = New TransactionDb();
		$arr_transaction = $arr_find_Repeated_transactionId = array();
		$tras_num = 0;

		$api_url = "https://api.commissionfactory.com.au/V1/Affiliate/Transactions?apiKey=[apiKey]&fromDate=[fromDate]&toDate=[toDate]";
		$request = array("AccountSiteID" => $this->info["AccountSiteID"],"method" => "get");

		$url = str_replace(array('[apiKey]', '[fromDate]', '[toDate]'), array($this->info['APIKey1'], $start_date, $end_date), $api_url);
		echo "req => {$url} \n";
		$result = $this->oLinkFeed->GetHttpResult($url, $request);
		if ($result['code'] != 200){
			mydie("Download json file failed.");
		}
		$result = json_decode($result['content'], true);

		foreach ($result as $v)
		{
			$TransactionId = trim($v['Id']);
			if (!$TransactionId) {
				continue;
			}

			if (isset($arr_find_Repeated_transactionId[$TransactionId])) {
				mydie("The transactionId={$TransactionId} have early exists!");
			} else {
				$arr_find_Repeated_transactionId[$TransactionId] = '';
			}

			$arr_transaction[$TransactionId] = array(
				'AccountSiteID' => $this->info["AccountSiteID"],
				'BatchID' => $this->info['batchID'],
				'TransactionId' => $TransactionId,                      //must be unique
				'Id' => addslashes($v['Id']),
				'DateCreated' => addslashes($v['DateCreated']),
				'DateModified' => addslashes($v['DateModified']),
				'MerchantId' => addslashes($v['MerchantId']),
				'MerchantName' => addslashes($v['MerchantName']),
				'MerchantAvatarUrl' => addslashes($v['MerchantAvatarUrl']),
				'TrafficType' => addslashes($v['TrafficType']),
				'TrafficSource' => addslashes($v['TrafficSource']),
				'CreativeType' => addslashes($v['CreativeType']),
				'CreativeId' => addslashes($v['CreativeId']),
				'CreativeName' => addslashes($v['CreativeName']),
				'CustomerIpAddress' => addslashes($v['CustomerIpAddress']),
				'CustomerCountryCode' => addslashes($v['CustomerCountryCode']),
				'CustomerCountryName' => addslashes($v['CustomerCountryName']),
				'OrderId' => addslashes($v['OrderId']),
				'UniqueId' => addslashes($v['UniqueId']),
				'TrackingMethod' => addslashes($v['TrackingMethod']),
				'SaleValue' => addslashes($v['SaleValue']),
				'Commission' => addslashes($v['Commission']),
				'ReportedCurrencyCode' => addslashes($v['ReportedCurrencyCode']),
				'ReportedCurrencyName' => addslashes($v['ReportedCurrencyName']),
				'ReportedSaleValue' => addslashes($v['ReportedSaleValue']),
				'CustomerIpBlacklisted' => addslashes($v['CustomerIpBlacklisted']),
				'TrafficSourceApproved' => addslashes($v['TrafficSourceApproved']),
				'Status' => addslashes($v['Status']),
				'VoidReason' => addslashes($v['VoidReason']),
				'AmendedReason' => addslashes($v['AmendedReason']),
				'CouponCode' => addslashes($v['CouponCode']),
			);
			$tras_num ++;

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
