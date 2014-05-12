<?
/*
 * All the code for scrimnet on the leaf bots (not BotNetwork the hub)
 */

$bnet->register('cmd_slot', 'bn_newscrim', 'newscrim');
function bn_newscrim(&$bnet, $nick, $chan, $arg, $arg2) {
	global $channels,$irc;
	$user = $nick;
	if($user != 'BotNetwork' || $chan !='&scrims' || count($arg) < 6) {
		return 1;
	}
	$target = '';
	foreach($channels as &$chan) {
		if($chan->active != 1) {
			continue;
		}
		$c = $chan->name;
		$id = $arg[0]; // Scrimnet ID
		$l = $arg[1]; // Location - ex: east
		$g = $arg[2]; // Game
		$t = $arg[3]; // Teams - ex: 4v4
		$m = $arg[4]; // Map - ex: de_dust
		$s = $arg[5]; // Server - ex: ours
		$t = explode('v', $t, 2); // Explode VS in teams kThx
		$o = $arg[6]; // League [OPTIONAL]
		$games = explode(' ', get_chan_set($c, 'sn_games'));
		$region = explode(' ', get_chan_set($c, 'sn_region'));
		$mute =  get_chan_set($c, 'sn_mute');
		if(array_search($l, $region) !== FALSE && array_search($g, $games) !== FALSE && $mute != 'on') {
			$target .= $c . ',';
		}
	}
	$gs = mysql_fetch_row(mysql_query("SELECT * FROM `scrimnet_games` WHERE `abbrev` = '$g'"));
	$target = trim($target);
	if($target != '') {
		$irc->raw("PRIVMSG $target :\2SCRIMNET\2 \2(\2ID#".$id.") [".$l."] (".$gs[3].") [".$o."] (".$t[0]."vs".$t[1].") (".$m." @ ".$s.") [ ".$c." ]");
		return 0;
	}
	return 1;
}

function check_region($string) {
	$string = strtolower($string);
	$string = explode(' ', $string);
	$good = Array('east','cent','west');
	$bad = array_diff($string, $good);
	return implode(' ', $bad);
}

function check_games($string) {
	$string = strtolower($string);
	$string = explode(' ', $string);
	$out = Array();
	foreach($string as $chunky) {
		$game = get_game($chunky);
		if($game === FALSE) {
			return Array(FALSE, $chunky);
		}
		$out[] = $game;
	}
	return Array(TRUE, implode(' ', $out));
}

function get_game($string) {
	$result = mysql_query("SELECT * FROM `scrimnet_games` WHERE `cabbrev` LIKE '%". mysql_escape_string($string) . "%'");
	while($row = mysql_fetch_assoc($result)) {
		$barf = explode(' ', $row['cabbrev']);
		foreach ($barf as $fuckyou) {
			if(strtolower($fuckyou) == strtolower($string)) {
				return $row['abbrev'];
			}
		}
	}
	return FALSE;
}

function set_scrimnet($nick, $host, $hand, $chan, $access, $arg, $arg2) {
	global $irc;
	if($arg2 == '') {
		$irc->notice($nick, "ScrimNET settings for $chan");
		$irc->notice($nick, " Game(s)  - " . get_chan_set($chan, 'sn_games'));
		$irc->notice($nick, " Region(s) - " . get_chan_set($chan, 'sn_region'));
		$irc->notice($nick, " Mute   - " . get_chan_set($chan, 'sn_mute'));
		$irc->notice($nick, "End ScrimNET settings for $chan.");
		return;
	}
	$set = array_shift($arg);
	$arg2 = implode(' ', $arg);
	switch(strtolower($set)) {
		case 'mute':
			if($arg2 == '') {
				if(get_chan_set($chan, 'sn_mute') == 'DEFAULT') {
					$irc->notice($nick, 'Mute is currently: off.');
				} else {
					$irc->notice($nick, 'Mute is currently: ' . get_chan_set($chan, 'sn_mute'));
				}
				break;
			}
			$arg2 = strtolower($arg2);
			if($arg2 != 'on' && $arg2 != 'off') {
				$irc->notice($nick, "Please choose on or off.");
				break;
			}
			chan_set($chan, 'sn_mute', $arg2);
			$irc->notice($nick, 'Mute is now: ' . $arg2);
			break;
		case 'games':
			if($arg2 == '') {
				if(get_chan_set($chan, 'sn_games') == 'DEFAULT') {
					$irc->notice($nick, 'No games have been selected.');
				} else {
					$irc->notice($nick, 'Displaying scrim(s) for game(s): ' . get_chan_set($chan, 'sn_games'));
				}
				break;
			}
			$bad = check_games($arg2);
			if($bad[0] == FALSE) {
				$irc->notice($nick, "Didn't recognise: $bad[1], Please specify space seperated list of games you wish to see scrims for.");
				break;
			}
			$arg2 = $bad[1];
			chan_set($chan, 'sn_games', $arg2);
			$irc->notice($nick, 'Display scrims for game(s): ' . $arg2);
			break;
		case 'region':
			if($arg2 == '') {
				if(get_chan_set($chan, 'sn_region') == 'DEFAULT') {
					$irc->notice($nick, 'No region(s) have been selected.');
				} else {
					$irc->notice($nick, 'Display scrims for region(s): ' . get_chan_set($chan, 'sn_region'));
				}
				break;
			}
			$arg2 = str_replace('central', 'cent', $arg2);
			$arg2 = implode(' ', array_unique(explode(' ', $arg2)));
			$bad = check_region($arg2);
			if($bad) {
				$irc->notice($nick, "Didn't recognise: $bad, Please specify space seperated list using east west cent");
				break;
			}
			chan_set($chan, 'sn_region', $arg2);
			$irc->notice($nick, 'Display scrims for region(s): ' . $arg2);
			break;
	}
}

//CMDDEFAULTS 1 0
function cmd_addscrim_scrim($nick, $host, $hand, $chan, $access, $arg, $query) { // Coded By Nyxzo
	global $irc;
	global $bnet;
	if(count($arg) < 5) { // Check if args are NULL
		$irc->notice($nick, "Syntax: addscrim <region> <game> <teams> <map> <server> [Optional: <league>]");
		return 1;
	}
	$l = strtolower($arg[0]); // Location - ex: east
	$g = strtolower($arg[1]); // Game - ex: cs1.6
	$t = strtolower($arg[2]); // Teams - ex: 4v4
	$m = strtolower($arg[3]); // Map - ex: de_dust
	$s = strtolower($arg[4]); // Server - ex: ours
	$o = strtolower($arg[5]); // Optional field - must start with CAL, CEVO, OGL
	$check = mysql_fetch_row(mysql_query("SELECT * FROM `scrimnet_scrims` WHERE `teama` = '$chan' AND `status` = 'P'"));
	if($check[10] == 'P' AND $check[6] == $chan) { // Make sure there is not a pending scrim
		$irc->notice($nick, "Sorry a scrim has already been posted from this channel.");
		return 1;
	}
	$gamecheck = mysql_num_rows(mysql_query("SELECT * FROM `scrimnet_games` WHERE `abbrev` = '$g'"));
	if($o != NULL){
	$leaguechk = mysql_num_rows(mysql_query("SELECT * FROM `scrimnet_leagues` WHERE `abbrev` = '$o'"));
	}
	if(preg_match('#east#i', $l) || preg_match('#west#i', $l) || preg_match('#cent#i', $l)) { // Region Check East, West, Central
		if($gamecheck != 0) { // Game Check Query DB for listed games and match to arg.
		if($leaguechk != 0 OR $o == NULL) {
			if(preg_match('#vs#i', $t)) {
				$t = explode('vs', $t, 2); // Explode VS
			} elseif(preg_match('#v#i', $t)) {
				$t = explode('v', $t, 2); // Explode VS
			} else {
				$irc->notice($nick, "Bad syntax with teams. Ex: 4vs4 or 4v4");
				return 1;
			}
			if(is_numeric($t[0]) AND is_numeric($t[1])) { // Make sure teams are numeric i.e: 4 vs 4 AND NOT 4 vs four
				// $game = mysql_fetch_row(mysql_query("SELECT * FROM `scrimnet_games` WHERE `abbrev` = '$g'"));
				mysql_query("INSERT INTO `scrimnet_scrims` ( `game` , `map` , `size` , `server` , `region` , `teama` , `league` ) VALUES ( '" . mysql_escape_string($g) . "', '" . mysql_escape_string($m) . "', '".$t[0]."vs".$t[1]."', '" . mysql_escape_string($s) . "', '" . mysql_escape_string($l) . "', '" . mysql_escape_string($chan) . "', '" . mysql_escape_string($o) ."' )");
				$id = mysql_fetch_row(mysql_query("SELECT * FROM `scrimnet_scrims` WHERE `teama` = '$chan' AND `status` = 'P'"));
				$bnet->msg('BotNetwork', "newscrim ".$id[0]." ".$l." ".$g." ".$t[0]."v".$t[1]." ".$m." ".$s." ".$o);
			} else {
				$irc->notice($nick, "Bad syntax with teams they must be numeric. Ex: 4vs4 or 4v4");
				return 1;
			}
		} else {
			$irc->notice($nick, "Sorry, unsupported league. You may have to omit this detail. Supported Leagues: CAL, CEVO Ex: CAL-O, CAL-IM");
			return 1;
		}
		} else {
			$game = mysql_query("SELECT * FROM `scrimnet_games`");
			$games = " ";
             while ($row = mysql_fetch_row($game)) {
                     $games .= $row[1] . ", ";
             } // Output supported games from the database
			$irc->notice($nick, "Sorry, unsupported game. Supported Games: ".$games);
			return 1;
		}
	} else {
		$irc->notice($nick, "Sorry, unsupported region. Supported Regions: east, west, central");
		return 1;
	}
}

//CMDDEFAULTS 1 0
function cmd_scrim_scrim($nick, $host, $hand, $chan, $access, $arg, $query) { // Coded By Nyxzo
	global $irc, $bnet;
	if(empty($arg[0])) { // Make sure the args are not NULL
		$irc->notice($nick, "Syntax: scrim <scrim #id>");
		return 1;
	}
	$id = strtolower($arg[0]);
	$c = mysql_fetch_row(mysql_query("SELECT * FROM `scrimnet_scrims` WHERE `id` = '$id' AND `status` = 'P'"));
	
	if($c != 0) {
		$teama = $c[6];
		$teamb = $chan;
		if ($teama != $chan) {
		mysql_query("UPDATE `scrimnet_scrims` SET `teamb` = '$chan', `status` = 'A' WHERE `scrimnet_scrims`.`id` = '$id' LIMIT 1 ;");
		$bnet->msg('&scrims', '?PRIVMSG ' . $teama . ' ' . "SCRIMNET [ID#".$id."] Your scrim has been accepted by ".$teamb.". (".$teama." VS ".$teamb.")");
		$irc->msg($chan, "SCRIMNET [ID#".$id."] Scrim has been accepted. (".$teama." VS ".$teamb.")");		
		} else {
		$irc->notice($nick, "Sorry, you can not play a scrim vs yourself with our system.");
		return 1;
		}
	} else {
		$e = mysql_fetch_row(mysql_query("SELECT * FROM `scrimnet_scrims` WHERE `id` = '$id'"));
		if($e != 0) {
			if($e[10] == "A") {
				$irc->notice($nick, "Sorry, this scrim was already accepted on $e[9].");
				return 1;
			} elseif($e[10] == "E") {
				$irc->notice($nick, "Sorry, this scrim has expired.");
				return 1;
			} elseif($e[10] == "D") {
				$irc->notice($nick, "Sorry, this scrim was deleted.");
				return 1;
			}
		} else {
			$irc->notice($nick, "Sorry, scrim id #$id does not exist.");
			return 1; //what are these return 1's you might ask, if a function returns non-false it wont get logged
		}
	}
}


//CMDDEFAULTS 1 1
function cmd_delscrim_scrim($nick, $host, $hand, $chan, $access, $arg, $query) { // Coded By Nyxzo
	global $irc;
	if(empty($arg[0])) {
		$irc->notice($nick, "Syntax: delscrim <scrim #id>");
		return 1;
	}
	$id = $arg[0];
	$c = mysql_fetch_row(mysql_query("SELECT * FROM `scrimnet_scrims` WHERE `id` = '$id' AND `status` = 'P'"));
	if ($c != 0) {
		if($c[6] == $chan OR hasflags($hand, 'T|O')) {
			mysql_query("UPDATE `scrimnet_scrims` SET `status` = 'D' WHERE `scrimnet_scrims`.`id` = '$id' LIMIT 1");
			if(hasflags($hand, 'T|O')){
			$irc->notice($nick, "Scrim #".$id." has been removed for channel ".$c[6].".");
			return 1;
			} else {
			$irc->notice($nick, "Scrim for ".$chan." has been removed.");
			return 1;
			}
		} else {
			$irc->notice($nick, "Sorry, you can not delete another channels scrim.");
			return 1;
		}
	} else {
		$irc->notice($nick, "Sorry, ethier this scrim has already been accepted or has been deleted.");
		return 1;
	}
}

//CMDDEFAULTS 1 0
function cmd_mute_scrim($nick, $host, $hand, $chan, $access, $arg, $query) { 
	global $irc;
	$current = get_chan_set($chan, 'sn_mute');
	if($current == 'on') {
		$current = 'off';
	} else {
		$current = 'on';
	}
	chan_set($chan, 'sn_mute', $current);
	$irc->notice($nick, "ScrimNET muting for $chan is now $current");
}

//CMDDEFAULTS 1 0
function cmd_list_scrim($nick, $host, $hand, $chan, $access, $arg, $query) { // Coded By Nyxzo // This command is actually .findscrim 
	global $irc;
	if(empty($arg[0])) {
		$irc->notice($nick, "Syntax: findscrim <game> [region] [teams] [map] [league] [server]");
		return 1;
	}
	if(isset($arg[0])) { $game = 'WHERE game = \''.$arg[0].'\''; } else { $game = 'WHERE 1'; }
	if(isset($arg[1])) { $region = 'AND region = \''.$arg[1].'\''; } else { $region = ''; }
	if(isset($arg[2])) { $teams = 'AND size = \''.$arg[2].'\''; } else { $teams = ''; }
	if(isset($arg[3])) { $map = 'AND map = \''.$arg[3].'\''; } else { $map = ''; }
	if(isset($arg[4])) { $league = 'AND league = \''.$arg[4].'\''; } else { $league = ''; }
	if(isset($arg[5])) { $server = 'AND server = \''.$arg[5].'\''; } else { $server = ''; }

	$query = mysql_query("SELECT * FROM `scrimnet_scrims` $game $region $map $teams $server $league AND status = 'P' ORDER BY timedate DESC;");
	$irc->notice($nick, ".:::::. ScrimNET Listings .:::::.");
	if(mysql_num_rows($query) > 0) {
		while($row = mysql_fetch_assoc($query)) {
			$irc->notice($nick, "[ID#$row[id]] [$row[map]] [$row[teama]] [$row[size]] [$row[server]] [$row[region]] [$row[timedate]]");
		}
			$irc->notice($nick, "To play a scrim use: .scrim #id");
	} else {
		$irc->notice($nick, "Sorry, no results were returned.");
	}
}

