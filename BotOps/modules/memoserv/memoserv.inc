<?PHP
// create table `memos` (`id` int not null auto_increment, `to` VARCHAR(255) not null, `status` VARCHAR(255) not null, `from` VARCHAR(255) not null, `date` varchar(255) not null, `text` text not null, primary key(id));

/*
 * MemoServ Module details
 * * MemoServ runs on the hub and all comands are access with the hub
 * * The hub may signal leaf bots to send messages to users notifying them of new memos.
 * TODO blocked senders
 * 
 * This module gives the following channel settings:
 * None.
 * 
 * This module gives the following account settings under MemoServ::
 * * notify on|off
 * ** Should MemoServ notify users when they have unread memos.
 * * email proxy|forward|off
 * ** Should MemoServ send memos to the user in email when they are received
 * ** If set to 'forward' the memos will remain in the system as unread.
 * ** If set to 'proxy' the memos will be marked as read.
 * 
 * 
 * This module gives the following commands:
 * * Memo <send|read|del|list|status>
 * ** send <target> <message>
 * *** Target can be username, ~group, or #channel (all users on channel)
 * ** read <id>
 * *** Shows detailed information about that memo.
 * ** list [unread|read|all] [page]
 * *** Gives a list of memos (unread by default)
 * *** If too many memos exist to be displayed in output you may specify page.
 * *** list should have <id> <sender> <status> <date> <first 30 chars>
 * ** del <id>
 * *** Deletes memos permanently.
 * ** status
 * *** gives a list of memos you've sent, and if their status (unread, read, emailed, etc)
 * 
 * * Memogroup <add|del|list>
 * * - Memo groups allow memos to be copied to all members in the group when a memo is sent to it
 * ** Add groupname user
 * *** Creates group if user is first, or add user to existing group.
 * ** del groupname user 
 * *** removes user from group, removes group if user is last in it.
 * ** list
 * *** lists all groups
 */

/*
 * TODO still need to make groups stuff
 */

$bnet->register('luseron_slot', 'hub_memonotify', '');
function hub_memonotify($bnet, $nick, $host, $hand) {
	global $irc;
	$notify = get_hand_set($hand, 'memo_notify');
	switch ($notify) {
		case 'on':
		default:
			//check if they have new memos
			$row = mysql_fetch_array(mysql_query("SELECT count(*) FROM `memos` where `to` = '" . mysql_escape_string($hand) . "' AND (status = 'unread' OR status = 'emailed')"));
			if($row['count(*)'] > 0) {
				$irc->msg($nick, "You have " . $row['count(*)'] . " memo(s) waiting to be read. Type memo for more information on MemoServ");
			}
			break;
		case 'off':
			break;
	}
}

$AccountSet->addModule('MemoServ', 'Memo sending services');
$AccountSet->regModuleSet('MemoServ', 'notify', 'memo_notify', Array('on','off'), 'Should MemoServ notify you when you have new memos', 'on');
$AccountSet->regModuleSet('MemoServ', 'email', 'memo_email', Array('forward','proxy','off'), 'Should MemoServ email you when you have new memos, forward sends emails and saves in system, proxy removes memo after sending', 'off');

function hub_memo($nick, $host, $hand, $arg, $arg2) {
	global $irc, $bnet;
	if($hand == '') {
		$irc->notice($nick, "You are not authed to BotNetwork");
		return;
	}
	if(empty($arg2)) {
		$irc->notice($nick, "To send memos:        memo send <target> <message>");
		$irc->notice($nick, "To list your memos:   memo list [unread|read|all] [page]");
		$irc->notice($nick, "To read your memos:   memo read <id>");
		$irc->notice($nick, "To delete your memos: memo del <id>");
		$irc->notice($nick, "To check sent status: memo status");
		$irc->notice($nick, "To change your settings on memoserv /msg BotNetwork set memoserv");
		return;
	}
	switch(strtolower($arg[0])) {
		case 'send':
			$targets = Array();
			if(empty($arg[2])) {
				$irc->notice($nick, "Usage: memo send <target> <message text>");
				break;
			}
			if($arg[1]{0} == '#') { // sending to channel's users
				if(chan_search($arg[1]) == '') {
					$irc->notice($nick, "That channel isn't registered with BotNetwork");
					return;
				}
				$users = explode(' ', chan_users($arg[1]));
				foreach($users as $u) {
					$temp = explode(':', $u);
					unset($temp[0]);
					$targets[] = implode('', $temp);
				}
			}
			if($arg[1]{0} == '~') { // sending to a group, will be done later
				$irc->notice($nick, "Sending to groups isn't supported yet");
				//TODO groups
				return;
			}
			if($arg[1]{0} != '~' && $arg[1]{0} != '#') { //sending to account
				if(!hand_exists($arg[1])) {
					$irc->notice($nick, "That user doesn't exist, you can only send memos to people who have a BotNetwork account.");
					break;
				}
				$targets[] = gethandcase($arg[1]);
			}
			$text = arg_range($arg, 2, -1);
			foreach($targets as $target) {
				$status = 'unread';
				//Check if the target has email notify
				$email = get_hand_set($target, 'memo_email');
				switch($email) {
					case 'proxy':
						$status = 'proxy';
						break;
					case 'forward':
						$status = 'emailed';
						break;
					case '':
					case 'off':
					default:
						break;
				}
				if($status == 'proxy' || $status == 'forward') {
					if(get_hand_email($target) != '') {
						$postmail = array(
							get_hand_email($target),
							"New memo received from $hand",
							"This email is to notify you of a new memo on BotNetwork MemoServ" .
								"\nFrom: $hand" .
								"\nDate: " . time() .
								"\nMessage: $text",
            				"From: " . $irc->nick . " MemoServ@botnetwork.org\n");
            			mail($postmail[0],$postmail[1],$postmail[2],$postmail[3]);
					}
				}
				$notify = get_hand_set($target, 'memo_notify');
				switch ($notify) {
					case 'on':
					default:
						$n = hand2nick($target);
						if(!empty($n)) {
							$irc->msg($n[0], "You have just received a memo from $hand. Type memo for more information on MemoServ");
						}
						break;
					case 'off':
						break;
				}
				if($status != 'proxy') { // proxy means we just forward no need to save
					mysql_query("INSERT INTO `memos` (`to`,`status`,`from`,`date`,`text`) values('" . mysql_escape_string($target) . "','$status','" . mysql_escape_string($hand) . "','" . time() . "','" . mysql_escape_string($text) . "')");
				}
			}
			$irc->notice($nick, "Memo Sent...");
			break;
		case 'list':
			// [unread|read|all] [page]
			if(empty($arg[1])) {
				$arg[1] = 'unread';
			}
			switch(strtolower($arg[1])) {
				case 'unread':
					$result = mysql_query("SELECT * FROM `memos` where `to` = '" . mysql_escape_string($hand) . "' AND (status = 'unread' OR status = 'emailed') ORDER BY date desc");
					break;
				case 'read':
					$result = mysql_query("SELECT * FROM `memos` where `to` = '" . mysql_escape_string($hand) . "' AND status = 'read' ORDER BY date desc");
					break;
				case 'all':
					$result = mysql_query("SELECT * FROM `memos` where `to` = '" . mysql_escape_string($hand) . "' ORDER BY date desc");
					break;
				default:
					$irc->notice($nick, "Unknown type: $arg[1] choose read unread or all");
					return;
			}
			$memos = Array();
			while($row = mysql_fetch_array($result)) {
				$memos[] = $row;
			}
			$page = 1;
			if(!empty($arg[2])) {
				if(!is_numeric($arg[2]) || $page < 1) {
					$irc->notice($nick, "$arg[2] is not recognised as a page number.");
					return;
				}
				$page = $arg[2];
			}
			if(count($memos) == 0 || ($page != 1 && count($memos) < ($page * 5))) {
				$irc->notice($nick, "No memos in list.");
				return;
			}
			$out = Array(Array("ID","FROM","DATE","STATUS"));
			for($i = (($page - 1) * 5); $i < ($page * 5); $i++) {
				if(array_key_exists($i, $memos)) {
					$out[] = Array($memos[$i]['id'],$memos[$i]['from'],strftime('%I:%M:%S:%p - %a, %B %d %Y', $memos[$i]['date']),$memos[$i]['status']);
				}
			}
			$out = multi_array_padding($out);
			foreach($out as $line) {
				$irc->notice($nick, implode('', $line));
			}
			break;
		case 'read':
			if(empty($arg[1]) || !is_numeric($arg[1])) {
				$irc->notice($nick, "Usage: memo read <ID>");
				break;
			}
			$row = mysql_fetch_array(mysql_query("SELECT * FROM `memos` where `to` = '" . mysql_escape_string($hand) . "' AND `id` = '" . mysql_escape_string($arg[1]) . "'"));
			if(!empty($row)) {
				$row['date'] = strftime('%I:%M:%S:%p - %a, %B %d %Y', $row['date']);
				$irc->notice($nick, "\2From\2: $row[from] \2Date\2: $row[date] \2Message\2: $row[text]");
				mysql_query("UPDATE `memos` SET `status` = 'read' WHERE `id` = '" . mysql_escape_string($arg[1]) . "'");
			} else {
				$irc->notice($nick, "No memo by that id for you");
			}
			break;
		case 'delete':
		case 'del';
			if(empty($arg[1]) || !is_numeric($arg[1])) {
				$irc->notice($nick, "Usage: memo del <ID>");
				break;
			}
			$row = mysql_fetch_array(mysql_query("SELECT * FROM `memos` where `to` = '" . mysql_escape_string($hand) . "' AND `id` = '" . mysql_escape_string($arg[1]) . "'"));
			if(!empty($row)) {
				mysql_query("DELETE FROM `memos` where `to` = '" . mysql_escape_string($hand) . "' AND `id` = '" . mysql_escape_string($arg[1]) . "'");
				$irc->notice($nick, "Deleted...");
			} else {
				$irc->notice($nick, "No memo by that ID for you.");
			}
			break;
		case 'status':
			$result = mysql_query("SELECT * FROM `memos` where `from` = '" . mysql_escape_string($hand) . "' AND `status` = 'unread' ORDER BY date desc");
			$memos = Array();
			while($row = mysql_fetch_array($result)) {
				$memos[] = $row;
			}
			$page = 1;
			if(!empty($arg[2])) {
				if(!is_numeric($arg[2]) || $page < 1) {
					$irc->notice($nick, "$arg[2] is not recognised as a page number.");
					return;
				}
				$page = $arg[2];
			}
			if(count($memos) == 0 || ($page != 1 && count($memos) < ($page * 5))) {
				$irc->notice($nick, "No memos in list.");
				return;
			}
			$out = Array(Array("ID","FROM","TO","DATE","STATUS"));
			for($i = (($page - 1) * 5); $i < ($page * 5); $i++) {
				if(array_key_exists($i, $memos)) {
					$out[] = Array($memos[$i]['id'],$memos[$i]['from'],$memos[$i]['to'],strftime('%I:%M:%S:%p - %a, %B %d %Y', $memos[$i]['date']),$memos[$i]['status']);
				}
			}
			$irc->notice($nick, "Displaying page $page of memos sent by you with unread status.");
			$out = multi_array_padding($out);
			foreach($out as $line) {
				$irc->notice($nick, implode('', $line));
			}
			break;
		default:
			$irc->notice($nick, "Unrecognised action, use send, list, read, del or status");
			break;
	}
}