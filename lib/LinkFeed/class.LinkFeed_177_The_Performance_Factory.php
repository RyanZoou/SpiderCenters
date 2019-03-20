<?php
require_once 'text_parse_helper.php';
class LinkFeed_177_The_Performance_Factory
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->affParams = json_decode($this->info['APIKey1'], true);
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

        $retry = 1;
        while ($retry) {
            $url = sprintf('http://partners.offerfactory.com.au/offers/offers.json?api_key=%s', $this->info['APIKey1']);
            $r = $this->oLinkFeed->GetHttpResult($url, $request);
            $data = @json_decode($r['content'], true);
            if (isset($data['data']['offers']) && !empty($data['data']['offers'])) {
                break;
            }
            if ($retry > 3) {
                mydie('wrong format of api result.');
            }
            $retry ++;
        }

        foreach ($data['data']['offers'] as $v)
        {
            $IdInAff = $v['id'];
            if (empty($IdInAff))
                continue;

            $arr_prgm[$IdInAff] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $IdInAff,
                'AffDefaultUrl' => addslashes(trim(html_entity_decode($v['tracking_url']))),
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {
            	$v['categories'] = str_replace(',', EX_CATEGORY, $v['categories']);
            	$arr_prgm[$IdInAff] += array(
                    "Name" => addslashes(trim(html_entity_decode($v['name']))),
                    "TargetCountryExt" => addslashes(trim($v['countries_short'])),
                    "CategoryExt" => addslashes(trim($v['categories'])),
                    "Homepage" => addslashes(trim(html_entity_decode($v['preview_url']))),
                    "Description" => addslashes(trim(html_entity_decode($v['description']))),
                    "CommissionExt" => addslashes(sprintf('%s %s', $v['currency'], $v['payout'])),
                );
                $base_program_num ++;
            }
            $program_num ++;
        }
        echo "get api 1 $program_num\r\n";

        //get partnership
        $program_num = 0;
        $url = sprintf('https://offerfactory.api.hasoffers.com/Apiv3/json?api_key=%s&Target=Affiliate_Offer&Method=findAll', $this->info['APIKey2']);

        $retry = 1;
        while ($retry) {
            $r = $this->oLinkFeed->GetHttpResult($url, $request);
            $content = $r['content'];
            $data = @json_decode($content, true);
            if (isset($data['response']['data']) && !empty($data['response']['data'])) {
                break;
            }
            if ($retry > 3) {
                mydie('wrong format of api result.');
            }
            $retry ++;
        }

        foreach ($data['response']['data'] as $v)
        {
            $v = current($v);
            $IdInAff = $v['id'];
            if (empty($IdInAff) || !isset($arr_prgm[$IdInAff])) {
                continue;
            }

            switch($v['approval_status']){
                case 'approved':
                    $Partnership = 'Active';
                    break;
                case 'pending':
                    $Partnership = 'Pending';
                    break;
                case 'rejected':
                    $Partnership = 'Declined';
                    break;
                case '':
                    $Partnership = 'NoPartnership';
                    break;
                default:
                    mydie("die: new partnership [{$v['approval_status']}].\n");
                    break;
            }
            $arr_prgm[$IdInAff]['Partnership'] = $Partnership;

            if ($this->isFull) {

                switch ($v['status']) {
                    case 'active':
                        $StatusInAff = 'Active';
                        break;
                    default:
                        mydie("die: new StatusInAff [{$v['status']}].\n");
                        break;
                }
                $arr_prgm[$IdInAff] += array(
                    "TermAndCondition" => addslashes(trim($v['terms_and_conditions'])),
                    "StatusInAffRemark" => addslashes($v['status']),
                    "StatusInAff" => addslashes($StatusInAff),
                    'CrawlJobId' => $this->info['crawlJobId'],
                );
            }

            $program_num ++;
        }
        echo "get api 2 $program_num\r\n";

        $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
        unset($arr_prgm);

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 5) {
            mydie("die: program count < 5, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }

}
