<?php
require_once 'text_parse_helper.php';
require_once INCLUDE_ROOT."wsdl/adcell_api/adcell.php";
class LinkFeed_360_Adcell
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
        $arr_prgm = array();
        $program_num = $base_program_num = 0;

        $api = new AdcellApi();
        $token = $api->getToken(json_decode($this->info['APIKey1'], true));

        //getCategories
        $reponseCategory = $api->category(
            array(
                'token' => $token,
            )
        );
        $cateList = array();
        foreach ($reponseCategory->data->items as $cate){
            $cateList[$cate->categoryId] = $cate;
        }


        $count = 0;
        $page  = 1;
        do{
            echo 'Page:'.$page."\t";
            $programIds = array();
            $reponseData = $api->apply(
                array(
                    'token' => $token,
                    'page'  => $page
                )
            );

            if($reponseData->status != 200) {
                continue;
            }
            $totalItems = $reponseData->data->total->totalItems;
            $row        = $reponseData->data->rows;
            $lastPage   = ceil($totalItems/$row);

            $count += count($reponseData->data->items);
            //var_dump($reponseData->data->items);exit;
            foreach ($reponseData->data->items as $value){

                $strMerID = trim($value->programId);
                if($strMerID < 1) {
                    continue;
                }

                if($value->affiliateStatus == 'accepted') {
                    $Partnership = 'Active';
                } elseif($value->affiliateStatus == 'application') {
                    $Partnership = 'Pending';
                } else {
                    $Partnership = 'NoPartnership';
                }

                $arr_prgm[$strMerID] = array(
                    'AccountSiteID' => $this->info["AccountSiteID"],
                    'BatchID' => $this->info['batchID'],
                    'IdInAff' => $strMerID,
                    'Partnership' => $Partnership,
                );

                if ($this->isFull && !$objProgram->checkBaseProgramInfoExistsByIdinaff($this->info['NetworkID'], $strMerID, $this->info['crawlJobId'])) {

                    if ($value->isActive == 1) {
                        $StatusInAff = 'Active';
                    } else {
                        $StatusInAff = 'Offline';
                    }

                    $programIds[] = $value->programId;

                    if (stripos("deeplink", $value->programTags) != false) {
                        $SupportDeepUrl = 'YES';
                    } else {
                        $SupportDeepUrl = 'UNKNOWN';
                    }

                    $CategoryExt = '';
                    $programCategoryIdsArr = explode(',', $value->programCategoryIds);
                    if (is_array($programCategoryIdsArr)) {
                        foreach ($programCategoryIdsArr as $cateId) {
                            if (isset($cateList[(int)$cateId]->categoryName)) {
                                $CategoryExt .= $cateList[(int)$cateId]->categoryName . EX_CATEGORY;
                            }
                        }
                    }

                    $arr_prgm[$strMerID] += array(
                        'CrawlJobId' => $this->info['crawlJobId'],
                        "Name" => addslashes($value->programName),
                        "Homepage" => addslashes($value->programUrl),
                        "CategoryExt" => addslashes($CategoryExt),
                        "CommissionExt" => '',
                        "StatusInAffRemark" => addslashes($value->isActive),
                        "StatusInAff" => $StatusInAff,
                        "Description" => addslashes($value->description),
                        "TermAndCondition" => addslashes($value->termsAndConditions),
                        "LogoUrl" => addslashes($value->programLogoUrl),
                        "PaymentDays" => addslashes($value->maximumPaybackPeriod),
                        "CookieTime" => addslashes($value->cookieLifetime),
                        "TargetCountryExt" => $value->allowedCountries,
                        "SupportDeepUrl" => $SupportDeepUrl
                    );
                    $base_program_num ++;
                }
                $program_num ++;
            }

            if ($this->isFull && !empty($programIds)) {
                //commissionExt
                $token = $api->getToken(json_decode($this->info['APIKey1'], true));
                $reponseCommission = $api->commission(
                    array(
                        'programIds[]' => $programIds,
                        'token' => $token,
                    )
                );
                if ($reponseCommission) {
                    foreach ($arr_prgm as $pKey => $pValue) {
                        foreach ($reponseCommission->data->items as $comValue) {
                            if ($pKey == $comValue->programId) {
                                foreach ($comValue->events as $comEvent) {
                                    if ($comEvent->currentCommission)
                                        $arr_prgm[$pKey]['CommissionExt'] .= $comEvent->currentCommission . $comEvent->commissionUnit . '|';
                                    elseif ($comEvent->minimumCommission == $comEvent->maximumCommission)
                                        $arr_prgm[$pKey]['CommissionExt'] .= $comEvent->minimumCommission . $comEvent->commissionUnit . '|';
                                    else
                                        $arr_prgm[$pKey]['CommissionExt'] .= $comEvent->minimumCommission . $comEvent->commissionUnit . '-' . $comEvent->maximumCommission . $comEvent->commissionUnit . '|';

                                    $arr_prgm[$pKey]['CommissionExt'] = addslashes($arr_prgm[$pKey]['CommissionExt']);
                                }
                            }
                        }
                    }
                }
            }

            $objProgram->InsertProgramBatch($this->info["NetworkID"], $arr_prgm, true);
            $arr_prgm = array();

            $page ++ ;
        }while($lastPage>=$page);

        echo "\n\tGet Program by api end\r\n";
        if ($program_num < 10) {
            mydie("die: program count < 10, please check program.\n");
        }
        echo "\tUpdate ({$base_program_num}) base programs.\r\n";
        echo "\tUpdate ({$program_num}) site programs.\r\n";
    }


}
