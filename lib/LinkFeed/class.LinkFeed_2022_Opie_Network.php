<?php
class LinkFeed_2022_Opie_Network
{
    function __construct($aff_id,$oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->islogined = false;
    }

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
        $program_num = 0;
        $base_program_num = 0;

        $strUrl = "http://partners.opienetwork.com/offers/offers.json?api_key={$this->info['APIKey1']}";
        $r = $this->oLinkFeed->GetHttpResult($strUrl);
        if(empty($r['content']))
            mydie("Error type is can not get infomation from Api");

        $apiResponse = @json_decode($r['content'], true);
        if(!isset($apiResponse['data']) || empty($apiResponse['data']))
            mydie("API call failed !");

        $result = $apiResponse['data']['offers'];
        
        foreach($result as $v)
        {
            $IdInAff = intval(trim($v['id']));
            if(!$IdInAff)
                continue;
            echo "$IdInAff\t";
            
            $AffDefaultUrl = str_replace("&amp;", "&", $v['tracking_url']);
            $arr_prgm[$IdInAff] = array(
            		'AccountSiteID' => $this->info["AccountSiteID"],      //attention there is ID not Id
            		'BatchID' => $this->info['batchID'],                  //attention there is ID not Id
            		'IdInAff' => $IdInAff,
            		'Partnership' => 'Active',                        //'NoPartnership','Active','Pending','Declined','Expired','Removed'
            		"AffDefaultUrl" => addslashes($AffDefaultUrl),
            );
            
            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId']))
            {
            
	            $CommissionExt = trim($v['currency']) . trim($v['payout']);
	
	            $arr_prgm[$IdInAff] += array(
	            		'CrawlJobId' => $this->info['crawlJobId'],
	                	"Name" => addslashes((trim($v['name']))),
	                	"Description" => addslashes($v['description']),
		                "Homepage" => addslashes($v['preview_url']),
		                "StatusInAff" => 'Active',						//'Active','TempOffline','Offline'
		                "CommissionExt" => addslashes($CommissionExt),
		                'TargetCountryExt'=> addslashes($v['countries_short']),
		                'CategoryExt' => addslashes($v['categories']),
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

        if($program_num < 5){
            mydie("die: program count < 10, please check program.\n");
        }

        echo "\tUpdate ({$base_program_num}) base program.\r\n";
        echo "\tUpdate ({$program_num}) program.\r\n";
    }

}