<?php

/*
 * basic tools
 */

/**
 * Ensure $s contains only \r\n line seperation
 * @param string $s
 * @return string
 */
function makenice($s) {
	return preg_replace("/[\r\n]+/", "\r\n", $s);
}

/**
 * Takes a string of byte data and converts it to an int
 * @param string $s
 * @return number
 */
function str2int($s) {
  $hex = hexdump($s);
  return (int)hexdec($hex);
}

/**
 * Turn Bytes size into human readable format
 * @param int $size
 * @return string
 */
function convert($size) {
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).$unit[$i];
}

/**
 * Reverse the order of data string
 * @param string $s
 * @return string
 */
function revbo($s) {
  $s = str_split($s);
  $s = array_reverse($s);
  $s = implode('', $s);
  return $s;
}

/**
 * Provides a printable hex string from a data string
 * @param string $s
 * @return string
 */
function hexdump($s) {
        $s = str_split($s);
        $out = '';
        foreach($s as $c) {
                $hex = dechex(ord($c));
                if(strlen($hex) == 1) {
                        $hex = '0' . $hex;
                }
                $hex = strtoupper($hex);
                $out .= "$hex ";
        }
        return trim($out);
}

/**
 * Remove first character from string
 * @param string $string
 * @return string
 */
function ridfirst($string) {
	$string = str_split($string);
	unset($string[0]);
	return implode("", $string);
}

/**
 * Takes $txt and creates (argc, argv) multiple spaces ignored
 * @param string $txt
 * @return multitype:number array
 */
function niceArgs($txt) {
    $txt = explode(' ', $txt);
    $txt = argClean($txt);
    return Array(count($txt), $txt);
}

/** From IRCU SRC
 * Nickname characters are in range 'A'..'}', '_', '-', '0'..'9'
 *  anything outside the above set will terminate nickname.
 * In addition, the first character cannot be '-' or a Digit.
 * @param string $nick
 * @return bool
 */
function validNick($nick) {
    $first = preg_match('/[A-Z]|[a-z]([A-}]|_|-|[0-9])*/', $nick[0]);
    if($first != 1) {
        return false;
    }
    return true;
}

/**
 * Removes null elements from array.
 * @param array $arg
 * @return multitype:string
 */

function argClean($arg) {
	$out = Array();
	foreach($arg as $a)
		if($a != '')
			$out[] = trim($a);
	return $out;
}

/**
 * Searches $ar until it finds case insensitive match for $key
 * and returns it, or NULL on fail.
 * @param multiype $key
 * @param Array $ar
 * @return multitype
 */
function get_akey_nc($key, $ar) {
	if(is_array($ar)) {
		$keys = array_keys($ar);
		foreach($keys as &$k) {
			if(strtolower($key) == strtolower($k))
				return $k;
		}
	}
	return '';
}

/**
 * Search through $subject and return true if all of the $chars are found in it.
 * @param string $sunject
 * @param string $chars
 * @return bool
 */
function cisin($subject, $chars) {
	$s2 = str_split($chars);
	foreach($s2 as &$c) {
		if(strrpos($subject, $c) === FALSE)
			return FALSE;
	}
	return TRUE;
}

/**
 * Clear out an array to make it empty, this might be useful when there are references of the array.
 * @param array &$array
 */
function aclear(&$array) {
	$keys = array_keys($array);
	foreach($keys as &$key) {
		unset($array[$key]);
	}
}

/*
 * help convert IRC colour to html color
 */
$color[0] ="#ffffff"; // white
$color[1] ="#000000"; // black
$color[2] ="#00007f"; // blue
$color[3] ="#007f00"; // green
$color[4] ="#ff0000"; // light red
$color[5] ="#7f0000"; // red
$color[6] ="#9f009f"; // magenta
$color[7] ="#ff7f00"; // orange
$color[8] ="#ffff00"; // yellow
$color[9] ="#00ff00"; // lime
$color[10]="#006464"; // cyan
$color[11]="#00ffff"; // light cyan
$color[12]="#0000ff"; // light blue
$color[13]="#c200c2"; // pink
$color[14]="#7a7a7a"; // grey
$color[15]="#a6a6a6"; // light grey

/*
 * used to need this when mysql was fucked
function mb_unserialize($serial_str) {
	//$out = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $serial_str );
	$out = $serial_str;
	return unserialize($out);
}
*/

/**
 * Pad all elements of an array with spaces so they contain the same number of characters
 * Also will add one space to the existing max length
 * @param array $array
 * @return array
 */
function array_padding($array) {
	$pad = 0;
	for ($i = 0; $i < count($array); $i++) {
		if (strlen($array[$i]) - substr_count($array[$i], "\2") > $pad) {
			$pad = strlen($array[$i]) - substr_count($array[$i], "\2");
		}
	}
	for ($i = 0; $i < count($array); $i++) {
		if($pad - strlen($array[$i]) + substr_count($array[$i], "\2") + 1 > 0) {
			$array[$i] = $array[$i] . str_repeat(' ',$pad - strlen($array[$i]) + substr_count($array[$i], "\2") + 1);
		}
	}
	return $array;
}

/**
 * Takes a 2D Array and pads it to make a nicely printable table.
 * The first dimension of the array is the rows, the second columns.
 * @param array $array
 * @return array
 */
function multi_array_padding($array) {
	//First dimension is rows second is cols
	$c = 0;$r = 0;
	$col = Array();
	$cols = count($array[0]);
	$rows = count($array);
	for($c = 0; $c < $cols; $c++) { // go through each col
		for($r = 0; $r < $rows; $r++) { // go through each row
			$col[] = $array[$r][$c];
		}
		$col = array_padding($col);
		for($r = 0; $r < $rows; $r++) { // go through each row again and reset it
			$array[$r][$c] = $col[$r];
		}
		$col = Array();
	}
	return $array;
}

/**
 * Generate an IRC progress bar using chr(22) codes
 * $n is fill amount, $d is the total size
 * @param number $n
 * @param number $d
 * @return string
 */
function bar_meter($n, $d) {
	$out = chr(22);
	if($d < $n) {
		return "Error: bar overfull";
		break;
	}
	if($d < 10) {
		return "Error: bar too small";
		break;
	}
	$text = (int)(($n / $d) * 100) . '%';
	$textpos = (($d / 2) - ((strlen($text)) / 2));
	for($i = 0; $i < $d; $i++) {
		if($i < $textpos || $i > ($textpos + strlen($text))) {
			$out .= ' ';
		} else {
			$out .= $text{(int)($i - $textpos)};
		}
		if($i == $n) {
			$out .= chr(22);
		}
	}
	return $out;
}

/**
 * Check if $string matches $mask using ? and * wildcards
 * @param string $mask
 * @param string $string
 * @param bool $ignoreCase
 * @return bool
 */
function pmatch($mask, $string, $ignoreCase = TRUE) {
	$expr = preg_replace_callback ('/[\\\\^$.[\\]|()?*+{}\\-\\/]/', function ($matches) {
		switch ($matches [0]) {
			case '*' :
				return '.*';
			case '?' :
				return '.';
			default :
				return '\\' . $matches [0];
		}
	}, $mask);
	
	$expr = '/' . $expr . '/';
	if ($ignoreCase) {
		$expr .= 'i';
	}
	
	return (bool) preg_match($expr, $string);
}

/**
 * Check if an email address is considered avlid
 * @param string $email
 * @return boolean
 */
function isemail($email) {
	return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Get the time including microseconds as a float
 * @return float
 * @codeCoverageIgnore
 */
function microtime_float()
{
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}

/**
 * Puts an array of args back together into a string using $start and $end
 * If $end is -1 then it is length of the array
 * @param array $args
 * @param int $start
 * @param int $end
 * @return string
 */
function arg_range($args, $start, $end) { // returns a range from args
	$out = '';
	if($end == -1) {
		$end = count($args) + 1;
	}
	$count = 0;
	foreach($args as $a) {
		if($count >= $start && $count <= $end) {
			if($count != 0 && $count != $start) {
				$out .= ' ';
			}
			$out .= $a;
		}
		$count++;
	}
	return $out;
}
/*
 * This is the fancy one that handles our $1 $2- $+ type vars
 *             *****(No longer used (currently))*****
 * @param string $format
 * @param array $args
 * @return mixed
 *
function format_string($format, $args) { // Formats a string
	$format = explode(' ', $format);
	$out = '';
	$c = 0;
	foreach($format as $chunk) {
		if($c != 0)
		$out .= ' ';
		else
		if($chunk == '')
		$out .= ' ';
		if(strlen($chunk) > 0 && $chunk{0} == '$') {
			$tchunk = trim($chunk);
			$tchunk = trim(implode('', explode('$', $tchunk)));
			$toend = false;
			if($tchunk{strlen($tchunk)-1} == '-') {
				$toend = true;
			}
			$pos = (int)trim($tchunk);
			if($pos > 0) {
				if(!$toend) {
					if(array_key_exists($pos-1, $args)) {
						$out .= $args[$pos-1];
					}
				} else {
					$out .= arg_range($args, $pos-1, -1);
				}
			}
		} else {
			$out .= $chunk;
		}
		$c++;
	}
	return str_replace('jfjuvwjnenweiej993j32mn09f90f203j0f243j90tgj249jg', '$', $out);
}
*/

/**
 * Workaround for XML-RPC failures, Encodes &#32; style encoding in our own
 * encoding so the xml-rpc package doesn't get confused.
 * @param string $str
 * @return string
 */
function ReEncode($str) {
    $str = preg_replace_callback("/(\((?P<oc>\/?)ReEncode\))/",
            function($m) {
                if($m['oc'] == '/')
                    return "(ReEncode)close(/ReEncode)";
                else
                    return "(ReEncode)open(/ReEncode)";
            }, $str);
    $out = preg_replace_callback("/(&#(?P<num>[0-9]+);)/",
            function($m) {
                return "(ReEncode)$m[num](/ReEncode)";
            }, $str);
    return $out;
}

/**
 * Decodes strings encoded with ReEncode, also decoding original &#99;
 * @param string $str
 * @return string
 */
function ReDeEncode($str) {
    $out = preg_replace_callback("/(\(ReEncode\)(?P<code>[^(]+)\(\/ReEncode\))/",
            function($m) {
                if($m['code'] == 'open')
                    return "(ReEncode)";
                if($m['code'] == 'close')
                    return "(/ReEncode)";
                if($m['code'] != 'open' && $m['code'] != 'close')
                    return chr(intval($m['code']));
            }, $str);
     return $out;
}


/**
 * Take an array and recursivly decode string keys, string values
 * or sub arrays that have been ReEncoded
 * @param array $array
 * @return array
 */
function ArReDeEncode($array) {
    if(!is_array($array)) {
        if(is_string($array)) {
            return ReDeEncode($array);
        } else {
            return $array;
        }
    }
    //luckly keys cannot be arrays
    $newArrayA = Array();
    foreach($array as $key => $val) {
    	$newval = $val;
    	if(is_array($val)) {
    		$newval = ArReDeEncode($val);
    	}
    	if(is_string($val)) {
    		$newval = ReDeEncode($val);
    	}
        if(is_string($key)) {
            $newKey = ReDeEncode($key);
            $newArrayA[$newKey] = $newval;
        } else {
            $newArrayA[$key] = $newval;
        }
    }
    return $newArrayA;
}

/**
 * This will make an argv array while treating "quoatable(\") arguments"
 * as one argument in the array. If there is an error it will return
 * a number indicating the posistion of the error. (ugly)
 * @param string $string
 * @return number|string
 */
function makeArgs($string) {
    /* 7/3/09 - Finished
     * take $string  and make args split by
     * spaces but including " support and \ support
     * then return as an array or a pos of error
     */
    $s = str_split($string);
    $skip = false;
    $pos = -1;
    $args = Array();
    $inQuote = false;
    $lastChar = ' ';

    $curArg = 0;
    $Bskip = false;

    foreach ($s as $c) {
        $pos++;
        if ($Bskip) {
            $Bskip = false; // skip adding char too
            $lastChar = $c;
            continue;
        }
        if ($c == "\\") {
            $skip = true; //skip over next char
            $lastChar = $c;
            continue;
        }
        if ($skip) {
            $skip = false; // only skip one char
            $lastChar = $c;
            if(!array_key_exists($curArg, $args)) {
            	$args[$curArg] = '';
            }
            $args[$curArg] .= $c;
            continue;
        }

        if ($c == '"') {
            if ($inQuote == false) {
                if ($lastChar != ' ') { //Quote should only come at begin of arg
                    return $pos+1; // Error at pos
                }
                $inQuote = true;
                $lastChar = $c;
                continue;
            } else {
                if (isset($string{$pos + 1}) && $string{$pos + 1} != ' ') {
                    //only end or space should follow end quote
                    return $pos + 1;
                }
                if ($lastChar == '"' && !isset($args[$curArg])) {
                    $args[$curArg] = '';
                }
                $Bskip = true; //skip next space
                $inQuote = false;
                $curArg++;
                $lastChar = $c;
                continue;
            }
        }
        if (!$inQuote && $c == ' ') {
        	if($lastChar != ' ') {
            	$curArg++;
        	}
            $lastChar = $c;
            continue;
        }
        $lastChar = $c;
        if(!array_key_exists($curArg, $args)) {
        	$args[$curArg] = '';
        }
        $args[$curArg] .= $c;
    }
    return $args;
}