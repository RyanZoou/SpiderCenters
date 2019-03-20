<?php
include_once('text_parse_helper.php');
class LinkFeed_2080_LinkPrice
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
        $program_num = $base_program_num = 0;

        $apiUrl = 'http://api.linkprice.com/shoplist2.php?a_id=' . $this->info['APIKey1'] . '&type=json';
        $result = json_decode(file_get_contents($apiUrl),true);

        foreach ($result as $val) {
            $IdInAff = $val['merchant_id'];
            if(!$IdInAff){
                continue;
            }

            $StatusInAffRemark = $val['status'];
            switch ($StatusInAffRemark) {
                case 'NON':
                    $Partnership = 'NoPartnership';
                    break;
                case 'APR':
                    $Partnership = 'Active';
                    break;
                case 'REQ':
                    $Partnership = 'Pending';
                    break;
                case 'DEN':
                    $Partnership = 'Declined';
                    break;
                default :
                    mydie("Find new partnership :$StatusInAffRemark");
                    break;
            }
            $AffDefaultUrl = $val['link'];

            $arr_prgm[$IdInAff] = array(
                'AccountSiteID' => $this->info["AccountSiteID"],
                'BatchID' => $this->info['batchID'],
                'IdInAff' => $IdInAff,
                'Partnership' => $Partnership,
                'AffDefaultUrl' => addslashes($AffDefaultUrl)
            );

            if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $IdInAff, $this->info['crawlJobId'])) {


                $Name = $val['merchant_name'];
                $CategoryExt = $val['category_name'];

                //api中没有Homepage 从AffDefaultUrl中截取 
//                 $Homepage = urldecode(str_replace('http://click.linkprice.com/click.php?m=' . $IdInAff . '&a=' . $this->info['APIKey1'] . '&l=9999&l_cd1=B&l_cd2=1&s=&tu=', '', $AffDefaultUrl));

                //get commission
                $CommissionExt = Array();
                foreach ($val['pgm'] as $v) {
                    if ($v['hidden_yn'] == 'Y' || $v['commission'] == '') continue;
                    $CommissionExt[] = strpos($v['commission'], '%') === false ? $v['commission'] . '원' : $v['commission'];
                }
                $CommissionExt = implode(',', $CommissionExt);

                $arr_prgm[$IdInAff] += array(
                    "Name" => addslashes((trim($Name))),
                    "Homepage" => "",
                    "StatusInAffRemark" => addslashes($StatusInAffRemark),
                    "StatusInAff" => 'Active',
                    "CommissionExt" => addslashes($CommissionExt),
                    "SupportDeepUrl" => 'YES',
                    'LogoUrl' => addslashes($val['banner_url']),
                    'CategoryExt' => addslashes($CategoryExt),
                    'CrawlJobId' => $this->info['crawlJobId']
                );
                $base_program_num ++;
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

}