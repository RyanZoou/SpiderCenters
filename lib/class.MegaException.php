<?php
class MegaException extends Exception
{
	public function __construct($message, $code=0) {
		// make sure everything is assigned properly
		parent::__construct($message, $code);
	}

	public function __toString() {
		$str = __CLASS__ . " @ " . date("Y-m-d H:i:s") . " : {$this->message}\n";
		//if(defined("DEBUG_MODE") && DEBUG_MODE) $str .= $this->getTraceAsString();
		return $str;
	}
}
?>