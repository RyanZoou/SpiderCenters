<?php
class GetOptions
{
	static function get_options_setting($longopts)
	{
		$arrRet = array();
		$arrSetting = array();
		foreach($longopts as $v)
		{
			$name = str_replace(":","",$v);
			$arrRet[$name] = false;
			if(substr($v,-2) == "::") $arrSetting[$name] = 2;//boolean
			else if(substr($v,-1) == ":") $arrSetting[$name] = 1;//required
			else $arrSetting[$name] = 0;
		}
		return array($arrRet,$arrSetting);
	}
	
	static function get_long_options($longopts)
	{
		list ($arrRet,$arrSetting) = self::get_options_setting($longopts);
		$strparameters = "";
		if(isset($_SERVER['argc']))
		{
			$tmp = $_SERVER['argv'];
			unset($tmp[0]);
			$strparameters = trim(implode(" ",$tmp));
		}

		$arrTmp = explode("--",$strparameters);
		foreach($arrTmp as $v)
		{
			$v = trim($v);
			$arrTmp2 = explode("=",$v,2);
			$para = $arrTmp2[0];
			if($para == "") continue;
			if(!isset($arrRet[$para]))
			{
				printf("warning: undefined parameter: %s\n",$para);
				continue;
			}
			
			if($arrSetting[$para] == 2) $arrRet[$para] = 1;
			else $arrRet[$para] = isset($arrTmp2[1]) ? $arrTmp2[1] : "";
		}
		
		foreach($arrRet as $k => $v)
		{
			if($arrSetting[$k] == 1 && $v === false)
			{
				self::printhelp($longopts);
				mydie("die:para $k is required!\n");
			}
		}
		
		return $arrRet;
	}
	
	function printhelp($longopts,$basename="")
	{
		if(!$basename && isset($_SERVER['argv'])) $basename = $_SERVER['argv'][0];
		if(!$basename) $basename = "some-php-file.php";
		list ($arrRet,$arrSetting) = self::get_options_setting($longopts);
		
		print "\n";
		print "Usage:\n";
		print "\tphp $basename";
		foreach($arrSetting as $name => $v) if($v == 1) echo " --" . $name . "=<" . $name . ">";
		foreach($arrSetting as $name => $v)	if($v == 2) echo " --" . $name;
		foreach($arrSetting as $name => $v)	if($v == 0) echo " [--" . $name . "=<" . $name . ">]";
		echo "\n\n";
	}
	
	static function get_option_str($longopts,$opt,$ignored=array())
	{
		if(is_string($ignored)) $ignored = array($ignored);
		$arr_return = array();
		list ($arrRet,$arrSetting) = self::get_options_setting($longopts);
		
		foreach($opt as $name => $v)
		{
			if(in_array($name,$ignored)) continue;
			if(empty($v)) continue;
			$setting = $arrSetting[$name];
			if($setting == 2) $arr_return[] = "--" . $name;
			else $arr_return[] = "--" . $name . "=" . escapeshellcmd($v);
		}
		return implode(" ",$arr_return);
	}
}//end class
?>