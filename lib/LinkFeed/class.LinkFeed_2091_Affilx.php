<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/16
 * Time: 10:45
 */
class LinkFeed_2091_Affilx
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
		$this->isFull = $this->info['isFull'];
		$this->GetProgramByApi();
		echo "Craw Program end @ " . date("Y-m-d H:i:s") . "\r\n";
		$this->oLinkFeed->setBatchCrawlStatus($this->info['batchID'], 'Done');
	}
	function GetProgramByApi()
	{
		echo "\tGet Program by Api start\r\n";
		$objProgram = new ProgramDb();
		$strUrl = "https://api.hasoffers.com/Apiv3/json?Target=Affiliate_Offer&Method=findAll&api_key={$this->info['APIKey1']}&NetworkId={$this->info['APIKey2']}";
		$r = $this->oLinkFeed->GetHttpResult($strUrl);
		$apiResponse = json_decode($r['content'], true);

		$arr = $apiResponse['response'];
		if (200==$arr['httpStatus'])
		{
			echo 'API call successful'.PHP_EOL;
		}
		else
		{
			mydie("API call failed...");
		}
		$res = $arr['data'];
		list ($program_num, $arr_prgm,$base_program_num) = array(0, array(),0);

		foreach ($res as $v) {
			$item = $v['Offer'];
			$IdInAff = $item['id'];
			if (!$IdInAff)
				continue;

			//$Partnership = ('approved'==$item['approval_status'])? 'Active': 'NoPartnership';
			if ('approved'==$item['approval_status'])
			{
				if ('active'==$item['status'])
				{
					$Partnership = 'Active';
				}
				else
				{
					$Partnership = 'Pending';
				}
			}
			else
			{
				$Partnership = 'NoPartnership';
			}
			$AffDefaultUrl = "http://affilx.go2cloud.org/aff_c?offer_id={$IdInAff}&aff_id={$this->info['APIKey3']}";
			$arr_prgm [$IdInAff] = array(
				"AccountSiteID" => $this->info["AccountSiteID"],
				'BatchID' => $this->info['batchID'],
				"IdInAff" => $IdInAff,
				'Partnership' => $Partnership,
				'AffDefaultUrl' => $AffDefaultUrl,
			);
			if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
				$arr_prgm[$IdInAff] += array(
					'CrawlJobId' => $this->info['crawlJobId'],
					'Name' => addslashes((trim($item['name']))),
					'StatusInAff' => 'Active',
					"Homepage" => addslashes((trim($item['preview_url']))),
					'SupportDeepUrl' => 'UNKNOWN',
					"Description" => addslashes((trim($item['description']))),
					"CommissionExt" => "$".addslashes((floatval($item['default_payout']))),
				);

				if (count($arr_prgm) >= 100) {
					$objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
					$arr_prgm = array();
				}
				$base_program_num ++;
			}
			$program_num++;
		}
		if (count($arr_prgm)) {
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
}