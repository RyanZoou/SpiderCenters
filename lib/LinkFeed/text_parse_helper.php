<?php

/**
 *
 * functions to help parse text(html csv and etc.)
 *
 * @author: liwei
 *
 */

// get the coupon code from the text
function get_linkcode_by_text($text)
{
	$text = html_entity_decode($text, null, 'utf-8');
	$regPlace = array(
		'/code_[\dx\d]+/mis',
		'/coupon\b[:\s"]*(\$[\d\.]+|[\d]+%)\b/mis',
		'/(\d{3}x[\d]+.jpg)/mis',
		);
	foreach($regPlace as $v){
		$text = preg_replace($v,'',$text);
	}
	$checkNull = 0;
	$regCodes = array(
			//array('reg' => '@Enter\s+(\w+)\s+at@mis', 'group' => 1),
			//array('reg' => '@\stype(=|,|:|\s)"?(\w+)"? (as|at)@mis', 'group' => 2),
			//array('reg' => '/\bUse Code\b [\'"]*\b([\w]+)\b/mis', 'group' => 1),
			//array('reg' => '/\bCode\b[=,:] [\'"]*\b([\w]+)\b/mis', 'group' => 1),
	        array('reg' => '/\b(use coupon|with coupon|Code)\b[:\s"]*“(.+)”/mis', 'group'=>2),
			array('reg' => '/\b(coupon code with|with the|with coupon|use coupon|enter coupon|with|enter|use|promo|using|voucher|coupon|promotion|w)\b[\/]* \b(code use code|code|coupon code|coupon|promotion)\b[:\s"-]*([\w\d#&\*\$\%\/]+)/mis', 'group' => 3),
	        array('reg' => '/\benter ([\w\d#&\*\$\%]+) (((coupon|voucher|promo|promotion) code)|code)\b/i', 'group'=>1),
			array('reg' => '@\sDiscount voucher\s?(=|,|:|\s)\s?"?(\w+)@mis', 'group' => 2),
			array('reg' => '@^Discount voucher\s?(=|,|:|\s)\s?"?(\w+)@mis', 'group' => 2),
			array('reg' => '/(\bcode is|w\/Code|\bCode:|promocode|ecoupon|couponcode|coupon:|\bcode|\bGutscheincode|\bpromo codes) ([\w\d#&\*\$\%\/\-]+)/mis', 'group'=>2),
			array('reg' => '/\b(code|coupon)\b[:\s"(=]*([\w\d#&\*\$\%\/]+)/mis', 'group'=>2),
			array('reg' => '/\b(code|coupon|bundle_code)\b[\?_:-]*([\w\d#&\*\$\%\/]+)/mis', 'group'=>2),
            );

	$text = preg_replace('/book now|when you refer a friend|â€œ|â€|\'|"/i', '', $text);
	foreach ($regCodes as $v)
	{
		if (preg_match($v['reg'], $text, $g))
		{
			
			$r = check_code_exist($g[$v['group']]);
			if(!empty($r))
				break;
			else
				$checkNull=1;
		}
	}
	if($checkNull){
		foreach ($regCodes as $v)
		{
			if (preg_match($v['reg'], $text, $g))
			{
				
				$r = check_code_exist($g[$v['group']]);
				$text = str_replace($g[0],'',$text);
				if(!empty($r))
					break;
			}
		}
	}
	if (!empty($r))
	{
		if (strlen($r) < 2)
			return;
		if (preg_match('@(banner|need|needed|required|necessary)@is', $r))
			return;
		return check_code_exist($r);
	}
	return '';
}

function check_code_exist($code)
{
    $cdoeList = array(
        "a", "able", "about", "above", "abst", "accordance", "according", "accordingly", "across", "act", "actually", "added", "adj", "affected", "affecting", "affects", "after", "afterwards", "again", "against", "ah", "all", "almost", "alone", "along", "already", "also", "although", "always", "am", "among", "amongst", "an", "and", "announce", "another", "any", "anybody", "anyhow", "anymore", "anyone", "anything", "anyway", "anyways", "anywhere", "apparently", "approximately", "are", "aren", "arent", "arise", "around", "as", "aside", "ask", "asking", "at", "auth", "available", "away", "awfully", "b", "back", "be", "became", "because", "become", "becomes", "becoming", "been", "before", "beforehand", "begin", "beginning", "beginnings", "begins", "behind", "being", "believe", "below", "beside", "besides", "between", "beyond", "biol", "both", "brief", "briefly", "but", "by", "c", "ca", "came", "can", "cannot", "can't", "cause", "causes", "certain", "certainly", "co", "com", "come", "comes", "contain", "containing", "contains", "could", "couldnt", "d", "date", "did", "didn't", "different", "do", "does", "doesn't", "doing", "done", "don't", "down", "downwards", "due", "during", "e", "each", "ed", "edu", "effect", "eg", "eight", "eighty", "either", "else", "elsewhere", "end", "ending", "enough", "especially", "et", "et-al", "etc", "even", "ever", "every", "everybody", "everyone", "everything", "everywhere", "ex", "except", "f", "far", "few", "ff", "fifth", "first", "five", "fix", "followed", "following", "follows", "for", "former", "formerly", "forth", "found", "four", "from", "further", "furthermore", "g", "gave", "get", "gets", "getting", "give", "given", "gives", "giving", "go", "goes", "gone", "got", "gotten", "h", "had", "happens", "hardly", "has", "hasn't", "have", "haven't", "having", "he", "hed", "hence", "her", "here", "hereafter", "hereby", "herein", "heres", "hereupon", "hers", "herself", "hes", "hi", "hid", "him", "himself", "his", "hither", "home", "how", "howbeit", "however", "hundred", "i", "id", "ie", "if", "i'll", "im", "immediate", "immediately", "importance", "important", "in", "inc", "indeed", "index", "information", "instead", "into", "invention", "inward", "is", "isn't", "it", "itd", "it'll", "its", "itself", "i've", "j", "just", "k", "keep	keeps", "kept", "kg", "km", "know", "known", "knows", "l", "largely", "last", "lately", "later", "latter", "latterly", "least", "less", "lest", "let", "lets", "like", "liked", "likely", "line", "little", "'ll", "look", "looking", "looks", "ltd", "m", "made", "mainly", "make", "makes", "many", "may", "maybe", "me", "mean", "means", "meantime", "meanwhile", "merely", "mg", "might", "million", "miss", "ml", "more", "moreover", "most", "mostly", "mr", "mrs", "much", "mug", "must", "my", "myself", "n", "na", "name", "namely", "nay", "nd", "near", "nearly", "necessarily", "necessary", "need", "needs", "neither", "never", "nevertheless", "new", "next", "nine", "ninety", "no", "nobody", "non", "none", "nonetheless", "noone", "nor", "normally", "nos", "not", "noted", "nothing", "now", "nowhere", "o", "obtain", "obtained", "obviously", "of", "off", "often", "oh", "ok", "okay", "old", "omitted", "on", "once", "one", "ones", "only", "onto", "or", "ord", "other", "others", "otherwise", "ought", "our", "ours", "ourselves", "out", "outside", "over", "overall", "owing", "own", "p", "page", "pages", "part", "particular", "particularly", "past", "per", "perhaps", "placed", "please", "plus", "poorly", "possible", "possibly", "potentially", "pp", "predominantly", "present", "previously", "primarily", "probably", "promptly", "proud", "provides", "put", "q", "que", "quickly", "quite", "qv", "r", "ran", "rather", "rd", "re", "readily", "really", "recent", "recently", "ref", "refs", "regarding", "regardless", "regards", "related", "relatively", "research", "respectively", "resulted", "resulting", "results", "right", "run", "s", "said", "same", "saw", "say", "saying", "says", "sec", "section", "see", "seeing", "seem", "seemed", "seeming", "seems", "seen", "self", "selves", "sent", "seven", "several", "shall", "she", "shed", "she'll", "shes", "should", "shouldn't", "show", "showed", "shown", "showns", "shows", "significant", "significantly", "similar", "similarly", "since", "six", "slightly", "so", "some", "somebody", "somehow", "someone", "somethan", "something", "sometime", "sometimes", "somewhat", "somewhere", "soon", "sorry", "specifically", "specified", "specify", "specifying", "still", "stop", "strongly", "sub", "substantially", "successfully", "such", "sufficiently", "suggest", "sup", "sure	t", "take", "taken", "taking", "tell", "tends", "than", "thank", "thanks", "thanx", "that", "that'll", "thats", "that've", "the", "their", "theirs", "them", "themselves", "then", "thence", "there", "thereafter", "thereby", "thered", "therefore", "therein", "there'll", "thereof", "therere", "theres", "thereto", "thereupon", "there've", "these", "they", "theyd", "they'll", "theyre", "they've", "think", "this", "those", "thou", "though", "thoughh", "thousand", "throug", "through", "throughout", "thru", "thus", "til", "tip", "to", "together", "too", "took", "toward", "towards", "tried", "tries", "truly", "try", "trying", "ts", "twice", "two", "u", "un", "under", "unfortunately", "unless", "unlike", "unlikely", "until", "unto", "up", "upon", "ups", "us", "use", "used", "useful", "usefully", "usefulness", "uses", "using", "usually", "v", "value", "various", "'ve", "very", "via", "viz", "vol", "vols", "vs", "w", "want", "wants", "was", "wasnt", "way", "we", "wed", "welcome", "we'll", "went", "were", "werent", "we've", "what", "whatever", "what'll", "whats", "when", "whence", "whenever", "where", "whereafter", "whereas", "whereby", "wherein", "wheres", "whereupon", "wherever", "whether", "which", "while", "whim", "whither", "who", "whod", "whoever", "whole", "who'll", "whom", "whomever", "whos", "whose", "why", "widely", "willing", "wish", "with", "within", "without", "wont", "words", "world", "would", "wouldnt", "www", "x", "y", "yes", "yet", "you", "youd", "you'll", "your", "youre", "yours", "yourself", "yourselves", "you've", "z", "zer" , "expires","applies","applied","will","code","50€","revealed","promo"
    );
	if(in_array(strtolower($code),$cdoeList)){
		return;
	}
	return $code;
}

//code preg match
function check_linkcode_exclude_sym($code){
	if(!empty($code)){
		$regCodes = array(
				array('reg'=>'/([\w\d]+) & \d+/mis','group'=>1)
			);
		foreach ($regCodes as $v)
		{
			if (preg_match($v['reg'], $code, $g))
			{
				$r = $g[$v['group']];
				break;
			}
		}
		if(!empty($r)){
			$code = $r;
		}

	}
	return $code;
}

function get_linkcode_by_text_de($text)
{
	$regCodes = array(
			array('reg' => '@\scode\s?(=|,|:|\s)\s?"?(\w+)@mis', 'group' => 2),
			array('reg' => '@^code\s?(=|,|:|\s)\s?"?(\w+)@mis', 'group' => 2),
			array('reg' => '@\sGutscheincode\s?(=|,|:|\s)\s?"?(\w+)@mis', 'group' => 2),
			array('reg' => '@^Gutscheincode\s?(=|,|:|\s)\s?"?(\w+)@mis', 'group' => 2),

	);
	foreach ($regCodes as $v)
	{
		if (preg_match($v['reg'], $text, $g))
		{
			$r = $g[$v['group']];
			break;
		}
	}
	if (!empty($r))
	{
		if (strlen($r) < 3)
			return;
		if (preg_match('@(banner|need|needed|required|necessary)@is', $r))
			return;
		return $r;
	}
}

function parse_time_str($text, $format = null, $lastSecond = false)
{
	$r = '0000-00-00';
	if (empty($text))
		return $r;
	if ($text == '0000-00-00' || $text == '0000-00-00 00:00:00' || $text == 'N/A' || $text == 'NA' || $text == 'ongoing')
		return $r;
	if (empty($format))
	{
		$date = strtotime($text);
		if ($date > 946713600) //2000-1-1 to make sure a real time
		{
			if ($lastSecond)
				return date('Y-m-d 23:59:59', $date);
			else
				return date('Y-m-d 00:00:00', $date);
		}
	}
	else if ($format == 'Y-m-d H:i:s')
	{
		$date = strtotime($text);
		if ($date > 946713600)
			return date('Y-m-d H:i:s', $date);
	}
	else if ($format == 'm-d-Y')
	{
		if (preg_match('@(\d+)-(\d+)-(\d+)@', $text, $g))
		{
			$date = strtotime(sprintf("%s-%s-%s", $g[3], $g[1], $g[2]));
			if ($date > 946713600)
			{
				if ($lastSecond)
					return date('Y-m-d 23:59:59', $date);
				else
					return date('Y-m-d 00:00:00', $date);
			}
		}
	}
	else if ($format == 'd/m/Y')
	{
		if (preg_match('@(\d+)/(\d+)/(\d+)@', $text, $g))
		{
			$date = strtotime(sprintf("%s-%s-%s", $g[3], $g[2], $g[1]));
			if ($date > 946713600)
			{
				if ($lastSecond)
					return date('Y-m-d 23:59:59', $date);
				else
					return date('Y-m-d 00:00:00', $date);
			}
		}
	}
	else if ($format == 'm/d/Y')
	{
		if (preg_match('@(\d+)/(\d+)/(\d+)@', $text, $g))
		{
			$date = strtotime(sprintf("%s-%s-%s", $g[3], $g[1], $g[2]));
			if ($date > 946713600)
			{
				if ($lastSecond)
					return date('Y-m-d 23:59:59', $date);
				else
					return date('Y-m-d 00:00:00', $date);
			}
		}
	}
	else if ($format == 'd.m.Y')
	{
		if (preg_match('@(\d+)\.(\d+)\.(\d+)@', $text, $g))
		{
			$date = strtotime(sprintf("%s-%s-%s", $g[3], $g[2], $g[1]));
			if ($date > 946713600)
			{
				if ($lastSecond)
					return date('Y-m-d 23:59:59', $date);
				else
					return date('Y-m-d 00:00:00', $date);
			}
		}
	}
	else if ($format == 'd/m/Y H:i')
	{
		if (preg_match('@(\d+)/(\d+)/(\d+) (\d+):(\d+)@', $text, $g))
		{
			$date = strtotime(sprintf("%s-%s-%s %s:%s:00", $g[3], $g[2], $g[1], $g[4], $g[5]));
			if ($date > 946713600)
				return date('Y-m-d H:i:s', $date);
		}
	}
	else if ($format == 'Y-m-d h:i:s A')
	{
		if (preg_match('@(.*?) (AM|PM)@i', $text, $g))
		{
			$date = strtotime($g[1]);
			if ($date > 946713600)
			{
				if ($g[2] == 'pm' || $g[2] == 'PM')
					$date += 43200;
				return date('Y-m-d H:i:s', $date);
			}
		}
	}
	else if ($format == 'millisecond')
	{
		$millisecond = intval($text);
		if ($millisecond > 0)
		{
			$date = (int)($millisecond / 1000);
			if ($date > 946713600)
				return date('Y-m-d H:i:s', $date);
		}
	}
	return $r;
}

// compatible with older code.
// use fgetcsv_str now.
function csv_string_to_array($content, $delimiter = ",", $line_delimiter = "\n")
{
	$r = array();
	$lines = explode($line_delimiter, $content);
	if (empty($lines) || !is_array($lines))
		return $r;
	$titles = array();
	for($i = 0; $i < count($lines); $i ++)
	{
		$fields = mem_getcsv($lines[$i], $delimiter, '"', '"');
		$line = array();
		if ($i == 0)
		{
			$header = $fields;
			continue;
		}
		else if (is_array($fields))
		{
			foreach ($fields as $k => $field)
			{
				if (!empty($header[$k]))
					$line[$header[$k]] = $field;
				else
					$line[$k] = $field;
			}
			$r[] = $line;
		}
	}
	return $r;
}

// $escape is not supported in some version of php.
// ignored parameter $escape.
function fgetcsv_str($content, $length = 0, $delimiter = ",", $enclosure = '"', $escape = '\\')
{
	$handle = fopen("php://memory", "rw");
	fwrite($handle, $content);
	fseek($handle, 0);
	$header = fgetcsv($handle, 4096,  $delimiter, $enclosure);
	$r = array();
	while($fields = fgetcsv($handle, $length, $delimiter, $enclosure))
	{
		$line = array();
		if (is_array($fields))
		{
			foreach ($fields as $k => $field)
			{
				if (!empty($header[$k]))
					$line[$header[$k]] = $field;
				else
					$line[$k] = $field;
			}
			$r[] = $line;
		}
	}
	fclose($handle);
	return $r;
}

function mem_getcsv($input, $delimiter=',', $enclosure='"', $escape=null, $eol=null)
{
	$temp = fopen("php://memory", "rw");
	fwrite($temp, $input);
	fseek($temp, 0);
	$data = fgetcsv($temp, 4096, $delimiter, $enclosure);
	fclose($temp);
	return $data;
}

function str_force_utf8($str)
{
	return preg_replace('/[^(\x20-\x7F)]*/','', $str);
}

function create_link_htmlcode($link)
{
	if (empty($link['LinkAffUrl']))
		return '';
	$url = $link['LinkAffUrl'];
	if (empty($link['LinkName']))
		$name = $url;
	else
		$name = $link['LinkName'];
	return sprintf('<a href="%s">%s</a>', $url, $name);
}

function create_link_htmlcode_image($link)
{
	if (empty($link['LinkAffUrl']))
		return '';
	$url = $link['LinkAffUrl'];
	if (empty($link['LinkName']))
		$name = $url;
	else
		$name = $link['LinkName'];
	if (!empty($link['LinkImageUrl']))
		return sprintf('<a href="%s"><img src="%s" alt="%s"></a>', $url, $link['LinkImageUrl'], $name);
	return sprintf('<a href="%s">%s</a>', $url, $name);
}

function xml_parser($str, $retry= 1){
    $xml_parser = xml_parser_create();
    while ($retry){
        if(xml_parse($xml_parser,$str,true)){
            return (json_decode(json_encode(simplexml_load_string($str)),true));
        }else{
            $retry --;
        }
    }
    xml_parser_free($xml_parser);
    return false;
}



