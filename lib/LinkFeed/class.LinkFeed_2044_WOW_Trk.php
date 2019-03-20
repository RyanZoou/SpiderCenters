<?php
require_once "class.LinkFeed_HasOffers.php";
class LinkFeed_2044_WOW_Trk extends LinkFeed_HasOffers
{
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

}
