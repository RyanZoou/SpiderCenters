<?php
include_once(dirname(__FILE__) . "/class.LinkFeed_TAG.php");
class LinkFeed_196_TAG_SG extends LinkFeed_TAG
{
    function __construct($aff_id, $oLinkFeed)
    {
        $this->oLinkFeed = $oLinkFeed;
        $this->info = $oLinkFeed->getAffById($aff_id);
        $this->debug = isset($oLinkFeed->debug) ? $oLinkFeed->debug : false;
        $this->isFull = true;
        $this->username = urlencode($this->info["UserName"]);
        $this->password = urlencode($this->info["Password"]);
        $this->domain = $this->info["APIKey2"];
    }

}
