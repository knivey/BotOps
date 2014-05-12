<?php

$file = 'questions/hugefile.txt';
$outfile = 'results.txt';
$lines = file($file);
$outs = fopen($outfile, 'w');
//$nfile = 'newdb.txt';
//$nf = fopen($nfile, 'w');

$q = Array();

for($i=0; $i <256; $i++) {
    $q[$i] = Array();
}

$cnt = 0;
$cats = Array();

function popcats($m) {
    global $cats;
    $c = strtolower($m[0]);
    //$num = count(explode(' ', $c));
    //if($num < 4) 
    if(!array_key_exists($c, $cats)) {
        $cats[$c] = 0;
    }
    $cats[$c]++;
}

$lno = 0;
$time = microtime(true);
$skip = 0;

foreach ($lines as $line) {
    $line = trim($line);
    $m = null;
    $lno++;
    
    if(($lno % 1000) == 0) {
        echo "Line: $lno Time: " . (microtime(true) - $time) . "\n";
        $time = microtime(true);
    }
    
    $regex = '/([^\*]+\:)?([^\*\:]+)?\*(.+)/i';
    
    if(preg_match($regex, $line, $m)) {
        $wl = array_shift($m);
        $m[0] = trim($m[0]);
        $m[1] = trim($m[1]);
        $m[2] = trim($m[2]);
        $m[3] = $lno;
        $m[4] = $wl;

        //popcats($m);

        $key = strtolower($m[1]);
        $len = strlen($key);
        /*
        $t = explode('*', $m[2]);
        $a = Array();
        foreach($t as $tt) {
            $a[strtolower($tt)] = $tt;
        }
        $m[2] = $a;
        */
        
        //var_dump($m);
        $nope = false;
        if ($len < 255 && $len > 4) {
            for ($i = $len - 3; $i <= $len + 3; $i++) {
                $keys = array_keys($q[$i]);
                foreach ($keys as $k) {
                    if (strlen($k) > 255) {
                        continue;
                    }
                    if (levenshtein($k, $key) <= 3) {
                        $cnt++;
                        $nope = true;
                        $olno = $q[$i][$k][3];
                        $owl = $q[$i][$k][4];
                        fwrite($outs, "O:($olno) $owl\nN:($lno) $wl\n");
                        echo "O:($olno) $owl\nN:($lno) $wl\n";
                        break;
                    }
                }
            }
        } else {
            $skip++;
        }
        if(!$nope) {
            $q[$len][$key] = $m;
        }
        if(empty($q)) {
            $q[$len][$key] = $m;
        }
    }
}

/*
foreach ($q as $l) {
    if($l[0] != '') {
        $line = $l[0] . ' ' . $l[1] . '*' . implode('*', $l[2]);
    } else {
        $line = $l[1] . '*' . implode('*', $l[2]);
    }
    fwrite($nf, $line . "\n");
}
*/
echo "Matched: $cnt Skipped: $skip\n";
//echo $cntb . "\n";


function cmp($a, $b)
{
    if (strlen($a) == strlen($b))
        return 0;
    if (strlen($a) > strlen($b))
        return 1;
    return -1;
}
fclose($outs);
//uksort($cats, "cmp");
//var_dump($cats);
?>
