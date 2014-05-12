<?php

$lines = file('questions/hugefile.txt');
$good = fopen('afile.txt', 'w');
$bad = fopen('bfile.txt', 'w');

$cnt = 0;
$lno = 0;
$time = microtime(true);
$regex = '/([^\*]+\:)?([^\*\:]+)?\*(.+)/i';

foreach ($lines as $line) {
    $line = trim($line);
    $m = null;
    $lno++;
    
    if(($lno % 1500) == 0) {
        echo "Line: $lno Time: " . (microtime(true) - $time) . "\n";
        $time = microtime(true);
    }
    
    if($line == '') {
    	continue;
    }
    
    if(preg_match($regex, $line, $m)) {
        $m[1] = trim($m[1]); //cats
        $m[2] = trim($m[2]); //question
        $m[3] = trim($m[3]); //answers
		
        $ok = true;
        if($m[2] == '') {
        	$ok = false;
        }
        $ans = explode('*', $m[3]);
        foreach ($ans as $a) {
        	if(strlen($a) > 40 || $a == '') {
        		$ok = false;
        	}
        }
        
        if(strpos($m[2], ' ') === false) {
        	$ok = false;
        }
        
        if($ok) {
        	fwrite($good, $line . "\n");
        } else {
        	$cnt++;
        	fwrite($bad, $line . "\n");
        }
        
    } else {
    	$cnt++;
    	fwrite($bad, $line . "\n");
    }
}


echo "Found: $cnt\n";
?>
