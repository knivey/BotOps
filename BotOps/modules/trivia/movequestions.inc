<?php
/*
 * This is used to move lines matching a string into a new Bfile
 * And removes those lines from Afile, leaving hugefile.txt unchanged
 */
//$uscaps = "/(Montgomery|Juneau|Phoenix|Little Rock|Sacramento|Denver|Hartford|Dover|Tallahassee|Atlanta|Honolulu|Boise|Springfield|Indianapolis|Des Moines|Topeka|Frankfort|Baton Rouge|Augusta|Annapolis|Boston|Lansing|Saint Paul|Jackson|Jefferson City|Helena|Lincoln|Carson City|Concord|Trenton|Santa Fe|Albany|Raleigh|Bismarck|Columbus|Oaklahoma City|Salem|Harrisburg|Providence|Columbia|Pierre|Nashville|Austin|Salt Lake City|Montpelier|Richmond|Olympia|Charleston|Madison|Cheyenne)/i";
//$matching = "/capital( city)? of/i";
$matching = "/science\:/i";
//$not = "/on this day/i";

$file = 'questions/hugefile.txt';

$lines = file ( $file );

$Afile = fopen('Afile.txt', 'w');
$Bfile = fopen('Bfile.txt', 'w');

foreach ( $lines as $line ) {
	$line = trim ( $line );
	if(preg_match($matching, $line)) {
		fwrite($Bfile, $line . "\n");
	} else {
		fwrite($Afile, $line . "\n");
	}
}

fclose($Afile);
fclose($Bfile);

?>
