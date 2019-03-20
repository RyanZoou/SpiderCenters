<?php
require_once "class.LinkFeed_HasOffers.php";
class LinkFeed_2052_Foreo_Inhouse extends LinkFeed_HasOffers
{
    private $islogined = false;

    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->NetworkId = $this->info['APIKey1'];
        $this->apikey = $this->info['APIKey2'];
        $this->Currency = $this->info['APIKey3'];
        $this->Affiliate_ID = $this->info['APIKey4'];
    }

    public function Login()
    {
        if ($this->islogined) return $this->islogined;

        $request = array("AccountSiteID" => $this->info["AccountSiteID"], "method" => "get",);
        $result = $this->oLinkFeed->GetHttpResult("https://flip.hasoffers.com/", $request);
        $content = $result['content'];
        $token_key = $this->oLinkFeed->ParseStringBy2Tag($content, array('name="data[_Token][key]', 'value="'), '"');
        $token_field = $this->oLinkFeed->ParseStringBy2Tag($content, array('name="data[_Token][fields]', 'value="'), '"');

        $this->info["LoginPostString"] = '_method=POST&data%5B_Token%5D%5Bkey%5D=' . urlencode($token_key) . '&data%5BUser%5D%5Bemail%5D='. urlencode($this->info['UserName']) .'&data%5BUser%5D%5Bpassword%5D='. urlencode($this->info['Password']) .'&data%5B_Token%5D%5Bfields%5D=' . urlencode($token_field);
        $this->info["referer"] = 'https://flip.hasoffers.com/';
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => $this->info["LoginMethod"],
            "postdata" => $this->info["LoginPostString"],
            "header" => 1,
            "addheader" => array(
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36',
                'Origin: https://flip.hasoffers.com',
                'Host: flip.hasoffers.com',
                'Referer: https://flip.hasoffers.com/',
                'Pragma: no-cache',
                'Connection: keep-alive',
                'Cache-Control: no-cache',
                'Content-Type: application/x-www-form-urlencoded',
                'Upgrade-Insecure-Requests: 1',
            )
        );

        $this->oLinkFeed->GetHttpResult('https://flip.hasoffers.com/', $request);

        $request_more = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => 'get',
            "header" => 1,
            "addheader" => array(
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36',
                'Host: flip.hasoffers.com',
                'Connection: keep-alive',
                'Cache-Control: no-cache',
                'Upgrade-Insecure-Requests: 1',
            )
        );

        $arr = $this->oLinkFeed->GetHttpResult('http://flip.hasoffers.com/publisher/', $request_more);

        if ($arr["code"] == 0) {
            if (preg_match("/^SSL: certificate subject name .*? does not match target host name/i", $arr["error_msg"])) {
                $request["no_ssl_verifyhost"] = 1;
                $arr = $this->oLinkFeed->GetHttpResult($this->info['LoginUrl'], $request);
            }
        }

        if ($arr["code"] == 200) {
            if (stripos($arr["content"], $this->info["LoginVerifyString"]) !== false) {
                echo "verify succ: " . $this->info["LoginVerifyString"] . "\n";
                $this->islogined = true;
            }
            //handle redir by meta tag
            if (!$this->islogined && stripos($arr["content"], "REFRESH") !== false && isset($this->info["LoginSuccUrl"]) && $this->info["LoginSuccUrl"]) {
                $url_path = @parse_url($this->info["LoginSuccUrl"], PHP_URL_PATH);//parse_url用于解析url，返回一个关联数组。parse_url("xxx", PHP_URL_PATH)返回数组的path值
                if ($url_path && stripos($arr["content"], $url_path) !== false) {
                    echo "good, verify succ (redir by meta tag) <br>\n";
                    $this->islogined = true;
                }
            }
        }

    }

    public function GetTransactionFromAff($start_date, $end_date)
    {
        $check_date = date("Y-m-d H:i:s");
        echo "Craw Transaction from $start_date to $end_date \t start @ {$check_date}\r\n";

        $this->Login();
        $objTransaction = New TransactionDb();
        $arr_transaction = $arr_find_Repeated_transactionId = array();
        $tras_num = 0;

        $url = 'https://api-p03.hasoffers.com/v3/Affiliate_Report.json';
        $b_date = date('Y-m-d', strtotime($start_date));
        $e_date = date('Y-m-d', strtotime($end_date));
        $request = array(
            "AccountSiteID" => $this->info["AccountSiteID"],
            "method" => 'post',
        );

        $page = 1;
        $haveNextPage = true;
        while ($haveNextPage) {
            echo "page$page\t";
            $request['postdata'] = 'page=' . $page . '&limit=100&fields%5B%5D=Stat.offer_id&fields%5B%5D=Stat.offer_url_id&fields%5B%5D=Stat.datetime&fields%5B%5D=Offer.name&fields%5B%5D=OfferUrl.name&fields%5B%5D=Stat.conversion_status&fields%5B%5D=Stat.payout&fields%5B%5D=Stat.currency&fields%5B%5D=Stat.conversion_sale_amount&fields%5B%5D=Stat.ad_id&fields%5B%5D=Stat.affiliate_info1&sort%5BStat.datetime%5D=desc&filters%5BStat.date%5D%5Bconditional%5D=BETWEEN&filters%5BStat.date%5D%5Bvalues%5D%5B%5D=' . $b_date . '&filters%5BStat.date%5D%5Bvalues%5D%5B%5D=' . $e_date . '&data_start=' . $b_date . '&data_end=' . $e_date . '&hour_offset=8&Method=getConversions&NetworkId=flip&SessionToken=ZmxpcDphZmZpbGlhdGVfdXNlcjoxNTA2OjE6MjA5NTc5ZWFjMTI2YWM3YTZlY2JkZmFiMzdhZjA1YmVhOTQzNWU3MTY0MDE3Y2YyOTE0OTE4YTQ1NmNmZTYwNA%3D%3D';
            $cache_file = $this->oLinkFeed->fileCacheGetFilePath($this->info["AccountSiteID"], "data_" . date("YmdH") . "_Transaction_page$page.cache", 'Transaction', true);
            if (!$this->oLinkFeed->fileCacheIsCached($cache_file)) {
                echo "req => {$url} \n";
                $result = $this->oLinkFeed->GetHttpResult($url, $request)['content'];
                $result_data = json_decode($result, true);
                if (!isset($result_data['response']['httpStatus']) || $result_data['response']['httpStatus'] != 200) {
                    mydie("Download cache file failed.");
                }
                $this->oLinkFeed->fileCachePut($cache_file, $result);
            }
            $result = file_get_contents($cache_file);
            $result = json_decode($result, true);
            $data = $result['response']['data'];
            if ($data['page'] >= $data['pageCount']) {
                $haveNextPage = false;
            } else {
                $page++;
            }

            foreach ($data['data'] as $val) {
                $TransactionId = $ad_id = trim($val['Stat']['ad_id']);

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
                    'ad_id' => addslashes($ad_id),
                    'offer_id' => addslashes($val['Stat']['offer_id']),
                    'offer_name' => addslashes($val['Offer']['name']),
                    'datetime' => addslashes($val['Stat']['datetime']),
                    'conversion_status' => addslashes($val['Stat']['conversion_status']),
                    'payout' => addslashes($val['Stat']['payout']),
                    'currency' => addslashes($val['Stat']['currency']),
                    'conversion_sale_amount' => addslashes($val['Stat']['conversion_sale_amount']),
                    'affiliate_info1' => addslashes($val['Stat']['affiliate_info1']),
                    'conversion_sale_amount_USD' => addslashes($val['Stat']['conversion_sale_amount@USD']),
                    'payout_USD' => addslashes($val['Stat']['payout@USD']),
                );
                $tras_num++;

                if (count($arr_transaction) >= 100) {
                    $objTransaction->InsertTransactionToBatch($this->info["NetworkID"], $arr_transaction);
                    $arr_transaction = array();
                }
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
