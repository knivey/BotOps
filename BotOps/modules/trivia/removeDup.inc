<?php
$file = 'questions/hugefile.txt';
$outsa = fopen('Afile.txt', 'w');
$outsb = fopen('Bfile.txt', 'w');
$lines = file($file);

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
$q = Array();

function stripStuff($s) {
    $out = '';
    foreach(str_split($s) as $c) {
	if(preg_match('/[a-z]|[A-Z]|[0-9]| /', $c)) {
	    $out .= $c;
	}
    }
    return $out;
}

foreach ($lines as $line) {
    $line = trim($line);
    $m = null;
    $lno++;

    $regex = '/([^\*]+\:)?([^\*\:]+)?\*(.+)/i';

    if(preg_match($regex, $line, $m)) {
        $wl = array_shift($m);
        $m[0] = trim($m[0]);
        $m[1] = trim($m[1]);
        $m[2] = trim($m[2]);
        $m[3] = $lno;
        $m[4] = $wl;
        $m[5] = stripStuff(strtolower($m[1]));

        $t = explode('*', $m[2]);
        $a = Array();
        foreach($t as $tt) {
            $a[strtolower($tt)] = $tt;
        }
        $m[2] = $a;

        $q[strlen($m[5])][] = $m;
    }
}

$tosort = Array();

$found = 0;
foreach($q as $qqkey => $qq) {
    foreach($qq as $tkey => $t) {
	foreach($qq as $ttkey => $tt) {
	    if($t[3] != $tt[3] && $t[5] == $tt[5]) {
		$found++;
		if(!array_key_exists($t[5], $tosort)) {
		    $tosort[$t[5]][$t[3]] = $t;
		}
		$tosort[$t[5]][$tt[3]] = $tt;
		unset($q[$qqkey][$ttkey]);
	    }
	}
    }
}

ksort($tosort);
$newlines = $lines;
$cunt = 0;
foreach($tosort as $th) {
    foreach($th as $thh) {
	$cunt++;
	unset($newlines[$thh[3] - 1]);
	fwrite($outsb, $thh[4] . "\n");
    }
}

foreach($newlines as $l) {
    $l = trim($l);
    fwrite($outsa, $l . "\n");
}

echo "Found: $found OR: $cunt\n";

fclose($outsa);
fclose($outsb);
//uksort($cats, "cmp");
//var_dump($cats);
?>
