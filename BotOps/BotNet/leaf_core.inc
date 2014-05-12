<?PHP
/*
 * Core functions for bots
 */

/*
 * This section provides global command functionality for leaf bots
 */

include('scorebot_leaf.php');


$bnet->register('cmd_slot', 'bn_global', 'global');
function bn_global(&$bnet, $user, $target, $arg, $arg2) {
	global $irc, $channels;
	if($user != 'BotNetwork') return;
	$type = $arg[0];
	switch($type) {
		case 'newfeature':
			$type = 'New Feature';
			break;
		case 'announcement':
			$type = 'Announcement';
			break;
	}
	$hand = $arg[1];
	$hand2 = $arg[2];
	$msg = arg_range($arg, 3, -1);
	$t = '';
	foreach($channels as &$ch) {
		if(!array_key_exists('global', $ch->settings) || $ch->settings['global'] == 'on') {
			$t .= $ch->name . ',';
		}
	}
	$t = trim($t);
	$irc->raw("PRIVMSG $t :\2[GLOBAL $type]\2 from $hand & $hand2: $msg");
}

/*
 * This lets our hub send messages to channels/users using the leaf bots
 */

$bnet->register('cmd_slot', 'bn_privmsg', 'privmsg');
function bn_privmsg(&$bnet, $user, $target, $arg, $arg2) {
	global $irc;
	$irc->msg($arg[0], arg_range($arg, 1, -1));
}

/*
 * Called by hub to update our binds
 */

$bnet->register('cmd_slot', 'bn_bind', 'bind');
function bn_bind(&$bnet, $user, $target, $arg, $arg2) {
	global $Commands;
	if($user != 'BotNetwork') return;
	$Commands->bind($arg[0], $arg[1], $arg[2], $arg[3], '');
}

$bnet->register('cmd_slot', 'bn_unbind', 'unbind');
function bn_unbind(&$bnet, $user, $target, $arg, $arg2) {
	global $Commands;
	if($user != 'BotNetwork') return;
	$Commands->unbind($arg[0]);
}

/*
 * called by the hub after filters table is updated
 */

$bnet->register('cmd_slot', 'bn_loadfilters', 'loadfilters');
function bn_loadfilters(&$bnet, $user, $target, $arg, $arg2) {
	global $irc;
	if($user != 'BotNetwork') return;
	$irc->loadFilters();
}

/*
 * Addchan is basically the same as irc addchan, added it for ?apps accept to function
 * staff probably shouldn't use this.. 
 */

$bnet->register('cmd_slot', 'bn_addchan', 'addchan');
function bn_addchan(&$bnet, $user, $target, $arg, $arg2) {
	if($user != 'BotNetwork') return;
	if($arg[1]{0} != '*') {
		$this->notice($user, "Must use *account");
		return 1;
	}
	cmd_addchan($user, '', $user, $target, '0', $arg, $arg2);
	return 0;
}

$bnet->register('cmd_slot', 'bn_delchan', 'delchan');
function bn_delchan(&$bnet, $user, $target, $arg, $arg2) {
	if($user != 'BotNetwork') return;
	cmd_delchan($user, '', $user, $target, '0', $arg, $arg2);
	return 0;
}

/*
 * Sometimes our hub needs to suspend channels i guess
 */
$bnet->register('cmd_slot', 'bn_suspend', 'suspend');
function bn_suspend(&$bnet, $user, $target, $arg, $arg2) {
	if($user != 'BotNetwork') return;
	cmd_suspend($user, '', $user, $target, '0', $arg, $arg2);
	return 0;
}

/*
 * loadchan and deletechan, these let our ?switch command work
 * they do nothing to the DB just tell our bots they need to load or unload channels
 */

$bnet->register('cmd_slot', 'bn_loadchan', 'loadchan');
function bn_loadchan(&$bnet, $nick, $target, $arg, $arg2) {
	global $channels, $irc;
	if($nick != 'BotNetwork') return 1;
	$arg[0] = strtolower($arg[0]);
	$channels[$arg[0]] = new CHAN('1', $arg[0], $irc);
	$row = mysql_fetch_array(mysql_query("SELECT trig,settings FROM `$irc->nick` WHERE name = '" . mysql_escape_string($arg[0]) . "'"), MYSQL_ASSOC);
	if(is_array($row) && array_key_exists('settings', $row) && is_string($row['settings'])) {
		$settings = unserialize($row['settings']);
		if(is_array($settings)) {
			$channels[$arg[0]]->settings = $settings;
			$channels[$arg[0]]->savesettings(); //updates internal vars
		}
	}
	if(is_array($row) && array_key_exists('trig', $row) && is_string($row['trig']))
	$channels[$arg[0]]->trig = $row['trig'];
	$channels[$arg[0]]->rejointrys = 0;
	$irc->raw("JOIN $arg[0]");
}

$bnet->register('cmd_slot', 'bn_deletechan', 'deletechan');
function bn_deletechan(&$bnet, $nick, $target, $arg, $arg2) {
	global $irc, $channels;
	if($nick != 'BotNetwork') return 1;
	$irc->raw("PART $arg[0]");
	unset($channels[strtolower($arg[0])]);
}




