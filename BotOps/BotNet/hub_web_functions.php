<?

$bnet->register('cmd_slot', 'web_showcommands', '?webget_showcommands');
function web_showcommands(&$bnet, $user, $chan, $arg, $arg2) {
	global $Commands;
	$bnet->msg($user, 'webreplystart');
	foreach($Commands->binds as $b)
		$bnet->msg($user, "webreply $b[bname] $b[access] $b[log] $b[cmd]");
	$bnet->msg($user, 'webreplyend');
}

$bnet->register('cmd_slot', 'web_chaninfo', '?webget_chaninfo');
function web_chaninfo(&$bnet, $user, $chan, $arg, $arg2) {
	$arg[0] = strtolower($arg[0]);
	$bnet->msg($user, 'webreplystart');
	if(!array_key_exists($arg[0], $bnet->Ichans)) {
		$bnet->msg($user, 'webreply ' . serialize('error channel not joined'));
		$bnet->msg($user, 'webreplyend');
		return;
	}
	$bigstr = serialize($bnet->Ichans[$arg[0]]);
	$split = str_split($bigstr, 400);
	foreach($split as $line) {
		$bnet->msg($user, 'webreply ' . $line);
	}
	$bnet->msg($user, 'webreplyend');
	return;
}