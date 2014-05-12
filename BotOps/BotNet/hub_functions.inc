<?php
//Adds dnrs
$bnet->register('cmd_slot', 'hub_dnr', '?dnr');
function hub_dnr(&$bnet, $nick, $chan, $arg, $arg2) {
	expirednrs();
	if(!hasflags($nick, 'O')) {
		$bnet->notice($nick, "Only BotNetwork Staff can add to the DNR list");
		return;
	}
	if(count($arg) < 3) {
		$bnet->notice($nick, "Usage: ?dnr <mask> <duration> <reason> [-FORCE] [-CONFIRM]");
		$bnet->notice($nick, "Notes: Mask will apply to channels-only if it starts with #...");
		$bnet->notice($nick, "Notes: Otherwise mask will match to both accounts and channels.");
		$bnet->notice($nick, "Notes: If duration is 0, then the DNR is indefinite");
		$bnet->notice($nick, "Notes: You may need to use -FORCE if the DNR will affect a great number of currently registered channels|accounts.");
		$bnet->notice($nick, "Notes: You may need to use -CONFIRM if the DNR will overule previous DNRs");
		$bnet->notice($nick, "\2Important Notes: This command will DELETE channels and accounts currently registered if they violate the DNR.");
		return;
	}
	$dnr = $arg[0];
	$duration = string2Seconds($arg[1]);
	if(!is_numeric($duration)) {
		$bnet->notice($nick, $duration);
		return;
	}
	if($duration != 0) {
		$duration = time() + $duration;
	}
	$reason = arg_range($arg, 2, -1);
	$reason = explode(' ', $reason);
	$force = false;
	$confirm = false;
	foreach($reason as $r) {
		if(strtolower($r) == '-confirm') {
			$confirm = true;
		}
		if(strtolower($r) == '-force') {
			if(hasflags($nick, 'A')) {
				$force = true;
			} else {
				$bnet->notice($nick, "Only Administrators may use this command with -FORCE");
				return;
			}
		}
	}
	$reason = implode(' ', $reason);
	//make the mask mysql safe
	$barf = mysql_escape_string($dnr);
	$barf = str_replace('*', '%', $barf);
	//Now get a list of channels currently registered this would affect.
	$chans = Array();
	$result = mysql_query("SELECT * from `bots`");
	while($row = mysql_fetch_assoc($result)) {
		$ch = explode(' ', $row['chans']);
		foreach($ch as $c) {
			$c = explode(':', $c);
			$c = $c[1];
			if(pmatch($dnr, $c)) {
				$chans[$c] = $c;
			}
		}
	}
	//Now get a list of accounts currently registered this would affect.
	$result = mysql_query("SELECT * from `users` WHERE name like '$barf'");
	$accounts = Array();
	while($row = mysql_fetch_assoc($result)) {
		if(cisin($row['flags'], 'O')) {
			$bnet->notice($nick, "Error: That mask conflicts with a staff members account(" . $row['name'] .  ")");
			return;
		}
		$accounts[$row['name']] = $row['name'];
	}
	$bnet->notice($nick, "\2Affected Channels:\2 " . implode(' ', $chans));
	$bnet->notice($nick, "\2Affected Accounts:\2 " . implode(' ', $accounts));
	//count channels and check the $force
	if(count($chans) > 1 && !$force) {
		$bnet->notice($nick, "Error: Because this command affects more then 1 currently registered channels you must use -FORCE");
		return;
	}
	if(count($accounts) > 1 && !$force) {
		$bnet->notice($nick, "Error: Because this command affects more then 1 currently registered accounts you must use -FORCE");
		return;
	}
	//see if mask is already DNRed
	$row = isdnr($dnr);
	if($row) {
		$bnet->notice($nick, "There is a DNR that already matches that: $row[mask] by $row[who]");
		return;
	}
	//search for previous now obsolete DNRs and list them then return unless confirm is true
	$dnrs = Array();
	$result = mysql_query("SELECT * from `dnr`");
	while($row = mysql_fetch_array($result)) {
		if(pmatch($dnr, $row['mask'])) {
			$dnrs[$row['mask']] = $row;
		}
	}
	$bnet->notice($nick, "\2Obsoleted DNRs:\2 " . implode(' ', array_keys($dnrs)));
	if(count($dnrs) > 3 && !$confirm) {
		$bnet->notice($nick, "Error: Because this command will overule existing DNRs please use -CONFIRM");
		return;
	}
	//remove old DNRs
	foreach($dnrs as $d) {
		mysql_query("DELETE from `dnr` where id = '$d[id]'");
		logs('BotNetwork', 'UNDNR', 'hidden', "Old DNR $d[mask] Made obsolete", 1, $nick, $chan);
	}
	//update database
	mysql_query("INSERT into `dnr` (`mask`,`who`,`date`,`reason`,`expires`) VALUES('" . mysql_escape_string($dnr) . "','" . mysql_escape_string($nick) . "','" . time() . "','" . mysql_escape_string($reason) . "','" . mysql_escape_string($duration) . "')");
	//log all changes to db
	logs('BotNetwork', 'DNR', 'hidden', $arg2, 1, $nick, $chan);
	//unregister channels
	foreach($chans as $ch) {
		$bots = chan_search($ch);
		$bots = explode(' ', $bots);
		foreach($bots as $bot) {
			$bnet->msg('&botchan', "$bot delchan $ch Channel violates new DNR $dnr by $nick");
			logs($bot, 'DELCHAN', 'hidden', "$ch Channel violates new DNR $dnr by $nick", 1, $nick, $ch);
		}
	}
	//unregistere accounts
	foreach($accounts as $act) {
		mysql_query("DELETE from `users` WHERE name = '" . mysql_escape_string($act) . "'");
		logs('BotNetwork', 'UNREGISTER', 'hidden', "Unregister of account $act due to DNR $dnr by $nick", 1, $act, $chan);
	}
	$bnet->notice($nick, "DNR has been added.");
}

$bnet->register('cmd_slot', 'hub_undnr', '?undnr');
function hub_undnr(&$bnet, $nick, $chan, $arg, $arg2) {
	expirednrs();
	if(!hasflags($nick, 'O')) {
		$bnet->notice($nick, "Only BotNetwork Staff can add to the DNR list");
		return;
	}
	if(count($arg) < 1) {
		$bnet->notice($nick, "Usage: ?undnr <mask>");
		$bnet->notice($nick, "Will remove DNRs that mask matches. But not DNRs that would match mask.");
		return;
	}
	$dnr = $arg[0];
	$dnrs = Array();
	$result = mysql_query("SELECT * from `dnr`");
	while($row = mysql_fetch_array($result)) {
		if(pmatch($dnr, $row['mask'])) {
			$dnrs[$row['mask']] = $row;
			mysql_query("DELETE from `dnr` WHERE id = '$row[id]'");
			logs('BotNetwork', 'UNDNR', 'hidden', "DNR $row[mask] removed by $nick using ?undnr $arg2", 1, $nick, $chan);
		}
	}
	$bnet->notice($nick, "\2Removed DNRs:\2 " . implode(' ', array_keys($dnrs)));
}

$bnet->register('cmd_slot', 'hub_listdnr', '?listdnr');
function hub_listdnr(&$bnet, $nick, $chan, $arg, $arg2) {
	expirednrs();
	$result = mysql_query("SELECT * from `dnr`");
	while($row = mysql_fetch_array($result)) {
		$row['date'] = strftime('%I:%M%p - %D', $row['date']);
		if($row['expires'] == 0) {
			$row['expires'] = 'Never.';
		} else {
			$row['expires'] = strftime('%I:%M%p - %D', $row['expires']);
		}
		$bnet->notice($nick, "\2DNR:\2 $row[mask] \2By:\2 $row[who] \2Date:\2 $row[date] \2Expires:\2 $row[expires] \2Reason:\2 $row[reason]");
	}
	$bnet->notice($nick, "End of DNR listing.");
}

$bnet->register('cmd_slot', 'hub_delbot', '?delbot');
function hub_delbot(&$bnet, $nick, $chan, $arg, $arg2) {
	if(!hasflags($nick, 'A')) {
		$bnet->notice($nick, "Only Administrators can start bots");
		return;
	}
	if($arg[0] == '') {
		$bnet->notice($nick, "Syntax: ?delbot <botnick>");
		return;
	}
	if(!bot_exists($arg[0])) {
		$bnet->notice($nick, "A bot by that name doesn't exist.");
	} else {
		$botstat = bots_status();
		$bot = get_akey_nc($arg[0], $botstat);
		if($botstat[$bot]['started']) {
			exec("kill " . $botstat[$bot]['pid']);
		}
		//TODO delbot on custom bot owners
		mysql_query("DROP TABLE `" . mysql_escape_string($arg[0]) . "`");
		mysql_query("DELETE FROM bots WHERE name = '" . mysql_escape_string($arg[0]) . "'");
		$bnet->notice($nick, "$arg[0] has been deleted");
	}
}

$bnet->register('cmd_slot', 'hub_startbots', '?startbots');
function hub_startbots(&$bnet, $nick, $chan, $arg, $arg2) {
	if(!hasflags($nick, 'O')) {
		$bnet->notice($nick, "Only Administrators can start bots");
		return;
	}
	elseif (empty($arg)) {
		$bnet->notice($nick, "Syntax: ?startbots <*|name>");
		return;
	}
	$botstat = bots_status();
	if ($arg[0] == "*") {
		foreach ($botstat as &$bot) {
			if(!$bot['started']) {
				exec("php bnscript.php $bot[name] > /dev/null"); //TODO check if bot is suspended, once suspensions work...
				$bnet->notice($nick, "Starting $bot[name]...");
			}
		}
	} else {
		if(array_key_exists($arg[0], $botstat)) {
			if($botstat[$arg[0]]['started'] == TRUE) {
				$bnet->notice($nick, "$arg[0] is already started with pid " . $botstat[$arg[0]]['pid']);
			} else {
				exec("php bnscript.php $arg[0] > /dev/null");
				$bnet->notice($nick, "Starting $arg[0]");
			}
		} else {
			$bnet->notice($nick, "The name you entered was incorrect, pay attention to case.");
		}
		return;
	}
	return;
}

//let our bots request a message to be sent to a channel they aren't in through another bot
$bnet->register('cmd_slot', 'hub_privmsg', '?privmsg');
function hub_privmsg(&$bnet, $user, $chan, $arg, $arg2) {
	$bot = explode(' ', chan_search($arg[0]));
	$bot = $bot[0]; // normally i'd wanna select bot with less to do
	//TODO support for msging to users as well, using CPRIVMSG
	$bnet->msg($bot, 'privmsg ' . $arg2);
}

$bnet->register('cmd_slot', 'hub_ping', '?ping');
function hub_ping(&$bnet, $user, $chan, $arg, $arg2) {
	$bnet->msg($chan, "\2$user\2: Pong!");
}

$bnet->register('cmd_slot', 'hub_newscrim', 'newscrim');
function hub_newscrim(&$bnet, $user, $target, $arg, $arg2) {
	if($target == 'BotNetwork' && $bnet->bnet->isbot($bnet->bnet->getid($user)) && $user != 'BotNetwork') {
		$bnet->msg('&scrims', "newscrim $arg2");
	} else {
		return 1;
	}
}

$bnet->register('cmd_slot', 'hub_info_bnet', '?info');
function hub_info_bnet(&$bnet, $user, $chan, $arg, $arg2) {
	global $ppl;
	// check if user is visible if its a nick
	if(empty($arg)) {
		$arg[0] = '*' . $user;
	}
	$what = str_split($arg[0]);
	if($what[0] != '#' && $what[0] != '*') {
		$key = get_akey_nc($arg[0], $ppl);
		if($key == '') {
			$bnet->notice($user, "$arg[0] is not seen by the botnet."); //TODO make sure this works
			return;
		}
	}
	hub_info_base($bnet, $user, '', $user, $arg, $arg2, true);
}

$bnet->register('lpart_slot', 'hub_chanserv_autos', '');
function hub_chanserv_autos(&$bnet, $bot, $nick, $chan, $msg) {
	if($nick == 'ChanServ') {
		$bnet->msg($bot, "suspend $chan ChanServ has parted: $msg");
	}
}

$bnet->register('lppladd_slot', 'hub_nickonline', '');
function hub_nickonline(&$bnet, $nick, $host) {
	//$bnet->msg('&logs', "New nick seen $nick!$host");
	$hand = host2hand($host);
	if($hand == '') {
		return;
	}
	$nicks = hand2nick($hand);
	if(count($nicks) > 1) { // user was already online
		return;
	}
	foreach($bnet->luseron_slot as $slot) {
		$slot['function']($bnet, $nick, $host, $hand);
	}
}

$bnet->register('lppldel_slot', 'hub_nickoffline', '');
function hub_nickoffline(&$bnet, $nick, $host, $hand) {
	//$bnet->msg('&logs', "Nick no longer seen $nick!$host");
	if($hand == '') {
		return;
	}
	//make sure user isn't on from another nickname
	$nicks = hand2nick($hand);
	if(count($nicks) > 1) {
		return;
	}
	foreach($bnet->luseroff_slot as $slot) {
		$slot['function']($bnet, $nick, $host, $hand);
	}
}

$bnet->register('luseron_slot', 'hub_useronline', '');
function hub_useronline($bnet, $nick, $host, $hand) {
	$bnet->msg('&logs', "$hand is now online from $nick!$host");
	mysql_query("UPDATE users set lastseen = 'now' where name = '" . mysql_escape_string($hand) . "'");
}
	
$bnet->register('luseroff_slot', 'hub_useroffline', '');
function hub_useroffline($bnet, $nick, $host, $hand) {
	$t = time();
	$bnet->msg('&logs', "$hand is now offline from $nick!$host");
	mysql_query("UPDATE users set lastseen = '$t' where name = '" . mysql_escape_string($hand) . "'");
}

$bnet->register('cmd_slot', 'hub_restart', '?restart');
function hub_restart(&$bnet, $user, $chan, $arg, $arg2) {
	global $irc, $apps;
	if(!hasflags($user, 'A')) {
		$bnet->notice($user, "You lack access to that command");
		return;
	}
	$irc->raw("QUIT :Restarting...");
	$bnet->bnet->netdie('Restarting...');
	$irc->process();
	$irc->_die();
	$apps->_die();
	usleep(1000000);
	exec("php hub.php > logs/BotNetwork");
	exit;
}

$bnet->register('cmd_slot', 'hub_clearerrors', '?clearerrors');
function hub_clearerrors(&$bnet, $user, $chan, $arg, $arg2) {
	if(!hasflags($user, 'D')) {
		$bnet->notice($user, "You lack access to that command");
		return;
	}
	$bnet->notice($user, "Clearing . . .");
	$stime = microtime_float();
	exec("echo \'\' > logs/error.log.xml");
	$tot_time = microtime_float() - $stime;
	$bnet->msg('&logs', "Error log cleared ($tot_time)");
	$bnet->notice($user, "Completed task took $tot_time ms");
}

$bnet->register('cmd_slot', 'hub_addfilter', '?addfilter');
function hub_addfilter(&$bnet, $user, $chan, $arg, $arg2) {
	if(!hasflags($user, 'A')) {
		$bnet->notice($user, "You lack access to that command");
		return;
	}
	if(count($arg) < 1) {
		$bnet->notice($user, "Usage: ?addfilter <*filter*>");
		return;
	}
	//check if any of our current rules already match this
	$result = mysql_query("SELECT * FROM filters");
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
	//cols: who, made, text, caught
		if(pmatch($row['text'], $arg2)) {
			$bnet->notice($user, "There is a rule that would match that already: " . $row['id']);
			return;
		}
	}
	mysql_query("INSERT INTO filters (who,made,text,caught) VALUES('" . mysql_escape_string($user) . "','" . time() . "','" . mysql_escape_string('*' . $arg2 . '*') . "','0')");
	$bnet->msg('&botchan', 'loadfilters');
}

$bnet->register('cmd_slot', 'hub_listfilters', '?listfilters');
function hub_listfilters(&$bnet, $user, $chan, $arg, $arg2) {
	$result = mysql_query("SELECT * FROM filters");
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$bnet->notice($user, "\2ID:\2 $row[id] \2Date Made:\2 " . date('r', $row['made']) . " \2Who:\2 $row[who] \2Times activated:\2 $row[caught] \2Filter:\2 $row[text]");
	}
	$bnet->notice($user, "End of list.");
}

$bnet->register('cmd_slot', 'hub_delfilter', '?delfilter');
function hub_delfilter(&$bnet, $user, $chan, $arg, $arg2) {
	if(!hasflags($user, 'A')) {
		$bnet->notice($user, "You lack access to that command");
		return;
	}
	if(count($arg) != 1) {
		$bnet->notice($user, "Usage: ?delfilter id");
		return;
	}
	mysql_query("DELETE FROM filters WHERE id = '" . mysql_escape_string($arg2) . "'");
	$bnet->msg('&botchan', 'loadfilters');
	$bnet->notice($user, "Removed.");
}

$bnet->register('cmd_slot', 'hub_bind', '?bind');
function hub_bind(&$bnet, $user, $chan, $arg, $arg2) {
	global $Commands;
	if(!hasflags($user, 'A')) {
		$bnet->notice($user, "You lack access to that command");
		return;
	}
	if(count($arg) != 2) {
		$bnet->notice($user, "Usage: ?bind <bindname> <module>.<command>");
		return;
	}
	//check if bind exists
	if($Commands->IsBound($arg[0])) {
		$bnet->notice($user, '-1', "A bind by that name already exists.");
		return;
	}
	//check if fucntion/mod exists
	$cmd = explode('.', $arg[1]);
	$mod = $cmd[0];
	$cmd = $cmd[1];
	$cmdidx = $Commands->CmdExists($cmd, $mod);
	if($cmdidx == '') {
		$bnet->notice($user, "$cmd - $mod That command was not found.");
		return;
	}
	//get default bind info
	$access = $Commands->cmds[$cmdidx]['access'];
	$loglvl = $Commands->cmds[$cmdidx]['log'];
	//update our bind, mysql, send updates to bnet
	$Commands->bind($arg[0], $cmdidx, $access, $loglvl, '');
	$bnet->msg('&botchan', "bind $arg[0] $cmdidx $access $loglvl");
	mysql_query("DELETE from Commands where bname = '" . mysql_escape_string($arg[0]) . "'"); //Better safe then sorry
	mysql_query("INSERT into Commands (bname,cname,used,access,args,log,module,fname) VALUES('" . mysql_escape_string($arg[0]) . "', '" . mysql_escape_string($cmd) . "', '0', '" . mysql_escape_string($access) . "', '', '" . mysql_escape_string($loglvl) . "', '" . mysql_escape_string($mod) . "', '" . mysql_escape_string($cmdidx) . "')");

}

$bnet->register('cmd_slot', 'hub_modcmd', '?modcmd');
function hub_modcmd(&$bnet, $user, $chan, $arg, $arg2) {
	global $Commands;
	if(!hasflags($user, 'A')) {
		$bnet->notice($user, "You lack access to that command");
		return;
	}
	if(count($arg) != 3) {
		$bnet->notice($user, "Usage: ?modcmd <bindname> <access> <loglevel>");
		return;
	}
	//check if bind exists
	if(!$Commands->IsBound($arg[0])) {
		$bnet->notice($user, "A bind by that name does not exist.");
		return;
	}
	$cmd = $Commands->getCmd($arg[0]);
	$Commands->bind($arg[0], $cmd, $arg[1], $arg[2], '');
	$bnet->msg('&botchan', "bind $arg[0] $cmd $arg[1] $arg[2]");
	mysql_query("UPDATE Commands set access = '" . mysql_escape_string($arg[1]) . "', log = '" . mysql_escape_string($arg[2]) . "' WHERE bname = '" . mysql_escape_string($arg[0]) . "'");
}

$bnet->register('cmd_slot', 'hub_showcommands', '?showcommands');
function hub_showcommands(&$bnet, $user, $chan, $arg, $arg2) {
	global $Commands;
	$bnet->notice($user, "\2All Bindings - name access loglvl function\2");
	foreach($Commands->binds as $b)
		$bnet->notice($user, "$b[bname] $b[access] $b[log] $b[cmd]");
	$bnet->notice($user, "\2All Functions - name default_access default_loglvl\2");
	foreach($Commands->cmds as $b)
		$bnet->notice($user, "$b[function] $b[access] $b[log]");
}

$bnet->register('cmd_slot', 'hub_unbind', '?unbind');
function hub_unbind(&$bnet, $user, $chan, $arg, $arg2) {
	global $Commands;
	if(!hasflags($user, 'A')) {
		$bnet->notice($user, "You lack access to that command");
		return;
	}
	if(count($arg) != 1) {
		$bnet->notice($user, "Usage: ?unbind <bindname>");
		return;
	}
	//check if bind exists
	if(!$Commands->IsBound($arg[0])) {
		$bnet->notice($user, "A bind by that name does not exist.");
		return;
	}
	$Commands->unbind($arg[0]);
	$bnet->msg('&botchan', "unbind $arg[0]");
	mysql_query("DELETE from Commands where bname = '" . mysql_escape_string($arg[0]) . "'");
}

$bnet->register('cmd_slot', 'hub_bots', '?bots');
function hub_bots(&$bnet, $user, $chan, $arg, $arg2) {
	if(!hasflags($user, 'A')) {
		$bnet->notice($user, "Only Administrators can manage bots");
		return;
	}
	$bots = bots_status();
	$keys = array_keys($bots);
	$out = array(array('Bot','Started','Enabled','Pid','Server:Port','Bot Ip'));
	foreach($keys as &$key) {
		if($bots[$key]['started'])
		$out[] = Array($key,'Yes',$bots[$key]['active'],$bots[$key]['pid'],$bots[$key]['server'].$bots[$key]['port'],$bots[$key]['ip']);
		else
		$out[] = Array($key,"\2NO\2",$bots[$key]['active'],'None',$bots[$key]['server'].$bots[$key]['port'],$bots[$key]['ip']);
	}
	$out = multi_array_padding($out);
	foreach($out as &$o)
	$bnet->notice($user, implode('', $o));
}

$bnet->register('cmd_slot', 'hub_gline', '?gline');
function hub_gline(&$bnet, $user, $chan, $arg, $arg2) {
	$bnet->bnet->glines[$arg[0]] = Array('by' => $user, 'expire' => 'never', 'reason' => arg_range($arg, 1, -1));
	$bnet->notice($user, "G-Lined...");
}

$bnet->register('cmd_slot', 'hub_ungline', '?ungline');
function hub_ungline(&$bnet, $user, $chan, $arg, $arg2) {
	unset($bnet->bnet->glines[$arg[0]]);
	$bnet->notice($user, "Un G-Lined...");
}

$bnet->register('cmd_slot', 'hub_staff', '?staff');
function hub_staff(&$bnet, $user, $chan, $arg, $arg2) {
	$result = mysql_query("SELECT `flags`,`name` FROM `users` WHERE `flags` REGEXP '[GOTSAD]'");
	$out = array(array("Name","Flags","Position"));
	for ($i = 0; $i < mysql_num_rows($result); $i++) {
		$out[$i + 1][0] = trim(mysql_result($result,$i,1));
		$out[$i + 1][1] = trim(mysql_result($result,$i,0));
		$out[$i + 1][2] = trim(staff_position($out[$i + 1][0]));
	}
	$out = multi_array_padding($out);
	foreach($out as &$line)
	$bnet->notice($user, implode('', $line));
}

$bnet->register('cmd_slot', 'hub_trace', '?trace');
/**
 * trace searches for users/channs and performs different actions
 *
 * @param object $bnet reference to the botnet
 * @param string $user username
 * @param string $chan channel
 * @param array $arg command arguments as an array
 * @param string $arg2 commnd arguments
 */
function hub_trace(&$bnet, $user, $chan, $arg, $arg2) {
	$ppl = &$bnet->Iusers;
	// ?trace <action>() <search>() complex
	// first need to put each function in an array

	/*
	 * should change this to first set results to all possible results
	 * then trim those results if they don't match our searches
	 */
	$ms = Array();
	$stime = microtime_float();
	$psearch = Array('host' => '', 'bnauthed' => '', 'user' => '', 'authed' => '', 'clones' => '1', 'bot' => '');
	$results = Array();

	$pat = '/\$([a-z]*)\((([ -\'*-~]|\\\\\(|\\\\\))*)\)[\n\r $]/';
	//$pat = '/[\$(.*)\((.*)\)[\s|$]]/';
	preg_match_all($pat, $arg2 . ' ', $func, PREG_SET_ORDER);
	foreach($func as $f) { // first we find the search things
		switch(strtolower($f[1])) {
			case 'print':
			case 'attack':
			case 'remove':
			case 'msg':
				break; // do nothing for now
			case 'chan':
				$chans = explode(',', $f[2]);
				$max = NULL; $min = NULL;
				foreach($chans as &$c) {
					if(pmatch('maxusers=*', $c)) {
						$max = explode('=', $c);
						$max = $max[1];
						continue;
					}
					if(pmatch('minusers=*', $c)) {
						$max = explode('=', $c);
						$max = $min[1];
						continue;
					}
					$c = explode('#', $c);
					if(count($c) > 1) {
						$c[1] = '#' . $c[1];
					} else {
						$c[1] = '#' . $c[0];
						$c[0] = NULL;
					}
				}
				break;
			case 'ppl':
				$arg = explode(',', $f[2]);
				foreach($arg as &$a) {
					$a = explode('=', $a);
					switch($a[0]) {
						case 'host':
							$psearch['host'] = $a[1];
							if(empty($results)) {// we are just starting
								$keys = array_keys($ppl);
								foreach($keys as &$p) {
									if(pmatch($a[1], $p . '!' . $ppl[$p]['host'])) {
										//$results[] = Array('nick' => $p, 'host' => $ppl[$p]['host'], 'bot' => $ppl[$p]['bot']);
										$results[] = $ppl[$p];
									}
								}
							} else { //we already have results narrow down
								$keys = array_keys($results);
								foreach($keys as &$p) {
									if(!pmatch($a[1], $results[$p]['host'])) {
										unset($results[$p]);
									}
								}
							}
							break;
						case 'bnauthed':
							$psearch['bnauthed'] = $a[1];
							break;
						case 'user':
							$psearch['user'] = $a[1];
							break;
						case 'authed':
							$psearch['authed'] = $a[1];
							break;
						case 'clones':
							$psearch['clones'] = $a[1];
							break;
						case 'bot':
							$psearch['bot'] = $a[1];
							if(empty($results)) {// we are just starting
								$keys = array_keys($ppl);
								foreach($keys as &$p) {
									if(array_search($a[1], $ppl[$p]['frombot'])) {
										$results[] = $ppl[$p];
									}
								}
							} else { //we already have results narrow down
								$keys = array_keys($results);
								foreach($keys as &$p) {
									if(array_search($a[1], $results[$p]['bot']) === FALSE) {
										unset($results[$p]);
									}
								}
							}
							break;
						default:
							$bnet->notice($user, "Invalid Arg to ppl function: $a[0]");
							break;
					}
				}
				break;
			default:
				$bnet->notice($user, "Undefined function $f[1]");
				break;
		}
	}
	// do the searches
	foreach($func as $f) { // now process action functions
		switch(strtolower($f[1])) {
			case 'print':
				$bnet->notice($user, count($results) . ' Results');
				$out = array(array('Nick', 'Host', 'BN Username', 'BN flags', 'Is IRCOP?', 'Bot(s)', 'Joined Channel(s)'));
				if(strtolower($f[2]) != 'count') {
					foreach($results as &$lol) {
						$ircop = 'False';
						if(cisin($lol['ircflags'], '*')) {
							$ircop = 'True';
						}
						$out[] = Array($lol['nick'], $lol['host'], $lol['user'], $lol['flags'], $ircop, implode(' ', $lol['frombot']), implode(' ', $bnet->get_Iuser_chans($lol['nick'])));
					}
				}
				$out = multi_array_padding($out);
				foreach($out as &$o)
					$bnet->notice($user, implode('', $o));
				break;
		}
	}
	$bnet->notice($user , 'Trace took ' . (microtime_float() - $stime) . ' microseconds to run');
}

$chrno->addtimer('cleansuspends', (time() + 21600), 21600, 'cleansuspends();');
function cleansuspends() {
	global $bnet;
	$suspends = get_old_suspends();
	foreach($suspends as $bot => $chans) {
		$chans = explode(' ', $chans);
		foreach($chans as $chan) {
			$bnet->msg('&botchan', "$bot delchan $chan Old suspend");
		}
	}
}
