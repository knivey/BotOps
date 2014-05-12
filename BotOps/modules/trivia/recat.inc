<?php

$newcat = "Geography: Capitals: ";

$file = 'Bfile.txt';
$lines = file($file);

$nfile = 'newdb.txt';
$nf = fopen($nfile, 'w');

$q = Array();

$cnt = 0;
$lno = 0;
$time = microtime(true);

foreach ($lines as $line) {
    $line = trim($line);
    $m = null;
    $lno++;
    
    if(($lno % 1500) == 0) {
        echo "Line: $lno Time: " . (microtime(true) - $time) . "\n";
        $time = microtime(true);
    }
    
    $regex = '/([^\*]+\:)?([^\*\:]+)?\*(.+)/i';
    
    if(preg_match($regex, $line, $m)) {
        array_shift($m);
        $cat = $m[0] = trim($m[0]);
        $m[1] = trim($m[1]);
        $ques = $m[1] = rtrim($m[1], '.?');
        $m[2] = trim($m[2]);

        $t = explode('*', $m[2]);
        $a = Array();
        foreach($t as $tt) {
            $a[strtolower($tt)] = $tt;
        }
        
        $ans = $m[2] = $a;

        $q[] = Array('c' => $cat, 'q' => $ques, 'a' => $ans);
    } else {
    	echo $line . "\n";
    }
}


foreach ($q as $l) {
	$line = $newcat . $l['q'] . '*' . implode('*', $l['a']);
    /*
    if($l[0] != '') {
        $line = $l[0] . ' ' . $l[1] . '*' . implode('*', $l[2]);
    } else {
        $line = $l[1] . '*' . implode('*', $l[2]);
    }*/
    fwrite($nf, $line . "\n");
}

//echo "Found: $cnt\n";
?>
