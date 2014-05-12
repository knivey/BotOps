<?php
/*
 * - callback functions for core functionality of the hub
 * - keeps an array of channels and users for advanced info
 * - provides function registration for callbacks on events (join/privmsg/raw/etc)
 * - provides functions for hub to communicate to botnet (join/part/msg/notice/etc)
 */

include('hub_bnet.inc');

class hub_bnet_core {
	/*
	 * These *_slot arrays hold all our registered event callbacks
	 * these are for local botnet operations
	 *
	 * l*_slot is for linked bot events (things our bot see on IRC)
	 * 
	 * $['name'] = Array()
	 * 'function' = name of callback function;
	 * 'on' = determins if the function will be called, matched differently for all slot types
	 * if on is blank then that function should always be called for that slot
	 * 
	 * USER ON BOTNET SLOTS
	 * auth		- user authed
	 * 		on	- always called
	 * 		arg	- (&$bnet, $user)
	 * quit		- user left botnet
	 * 		on	- always called
	 * 		arg	- (&$bnet, $user, $quitmsg)
	 * part		- see join
	 * join		- user joined a channel
	 * 		on	- channel name, if '' then always
	 * 		arg	- (&$bnet, $nick, $chan)
	 * cmd		- user ?commands
	 * 		on	- matched to first word on a privmsg
	 * 		arg	- (&$bnet, $user, $chan, $arg, $arg2)
	 * 
	 * IRC LINK SLOTS (preceed with an l)
	 * mode		- mode change
	 * 		on	- modes that were set or unset
	 * 		arg	- (&$bnet, $who, $chan, $mode, add/del); add/del is true for being set false for unset
	 * lostirc	- a bot has disconnected from irc
	 * 		on	- always called
	 * 		arg - (&$bnet, $bot)
	 * gotirc	- a bot connected to irc
	 * 		on	- always called
	 * 		arg	- (&$bnet, $bot)
	 * nick		- a user on irc has changed their nick
	 * 		on	- always called
	 * 		arg	- (&$bnet, $oldnick, $newnick)
	 * join		- a user has joined a channel
	 * 		on	- #channel or always if ''
	 * 		arg	- (&$bnet, $nick, $chan)
	 * part		- a user left our channel
	 * 		on	- #channel or always if ''
	 * 		arg	- (&$bnet, $bot, $nick, $chan, $msg)
	 * quit		- a user quit IRC
	 * 		on	- always
	 * 		arg	- (&$bnet, $nick, $msg)
	 * kick		- user has been kicked from a channel
	 * 		on	- always
	 * 		arg	- ($&bnet, $by, $chan, $who, $msg)
	 * privmsg	- privmsg sent to channel
	 * 		on	- '' for always, anything else goes to pmatch
	 * 		arg	- ($&bnet, $who, $chan, $msg)
	 * notice	- same as privmsg but for notices
	 * ban		- a ban has been set on a channel (also called for initial ban listing)
	 * 		on	- always
	 * 		arg	- (&$bnet, $chan, $who, $ban, $time)
	 * unban	- a ban has been lifted on a channel
	 * 		on	- always
	 * 		arg	- (&$bnet, $chan, $who, $ban)
	 * usermode	- called when a user gets op/deop/voice/devoice
	 * 		on	- always
	 * 		arg - ($&bnet, $user, $chan, $by, $mode) mode is one of +o,-o,+v,-v
	 * ppladd   - called when a user is first added to ppl array
	 *      on  - always
	 *      arg - ($&bnet, $nick, $host)
	 * ppldel   - called when a user is removed from ppl array
	 *      on  - always
	 *      arg - ($&bnet, $nick, $host, $hand)
	 * useron   - called when a user becomes marked as online
	 *      on  - always
	 *      arg - ($&bnet, $nick, $host, $hand)
	 * useroff  - called when a user becomes marked offline
	 *      on  - always
	 *      arg - ($&bnet, $nick, $host, $hand)
	 */
	public $auth_slot = Array();
	public $join_slot = Array();
	public $part_slot = Array();
	public $quit_slot = Array();
	public $cmd_slot = Array();
	
	public $llostirc_slot = Array(); //the bot got disconnected from IRC
	public $gotirc_slot = Array(); //the bot established an IRC connection
	public $lnick_slot = Array(); // tells us about nick changes
	public $ljoin_slot = Array(); //user joins a chan
	public $lpart_slot = Array(); //user left channel
	public $lquit_slot = Array(); //user quit IRC
	public $lmode_slot = Array(); //user set modes on a channel
	public $lkick_slot = Array(); //user has been kicked from a channel
	public $lprivmsg_slot = Array(); //user has sent a privmsg
	public $lnotice_slot = Array(); //user has sent a notice
	public $lban_slot = Array();
	public $lunban_slot = Array();
	public $lusermode_slot = Array();
	public $lppladd_slot = Array();
	public $lppldel_slot = Array();
	public $luseron_slot = Array();
	public $luseroff_slot = Array();
	
	public $Ichans = Array(); //Holds information about global channels (IRC chans from bots)
	/* $Ichans[name] = Array() name key is always lowercase
	 * frombot[bot] = Array telling us which bots are on a channel, only information from the first bot in this array will be accepted
	 * * bot = name of the bot
	 * name = name of channel in original case
	 * users[nick] = Array of users on the channel
	 * * nick = name of user
	 * * time = time user joined the channel or 0 if user was there before us
	 * * modes = Array is user opped or voiced
	 * log = Array with max of 1024 lines of actions from channel (not including /quits)
	 * modes = channel modes (not bans) is array with keys as the mode
	 * bans[mask] = Array of channel bans
	 * * mask = *!*@* mask of ban
	 * * who = who set the ban
	 * * time = time ban was set
	 * topic = Array with channel topic info
	 * * time = time topic was set
	 * * who = who set topic
	 * * text = the topic
	 * time = time channel was created on irc
	 */
	public $Iusers = Array(); // users on irc (this is a reference to the global $ppl array)
	/* $Iusers[nick] = Array()
	 * host = hostname
	 * ircflags = flags from irc /who (lets us know if user is authserv authed, an ircop etc)
	 * user = BN username TODO update the $ppl[]['user'] when user auths/logs out MAKE SURE IT LOGSOUT FOR ALL MATCHING HOSTS NOT JUST NICKNAME
	 * frombot[bot] = Array telling us the bots that provided this info
	 * * bot = name of the bot
	 * flags = BN flags TODO update the $ppl[]['flags'] from oset
	 * onsplit = 'false' of not on split, time they split if they did quit with a *.net *.split TODO unset netsplit users after certain amount of time
	 */
	public $Lchans; //Extended information about our local botnet &channels
	public $Lusers; //Information about local botnet users
	/* $Luser[name] = Array()
	 * name = username
	 * id = users bnet id
	 * isbot = true if user is a bot
	 * isweb = ID of webauther
	 * botinfo = Array of information if user is a bot
	 * * onirc = status of the bots IRC connection ('No', 'Not Authed', 'Yes')
	 * * 
	 */
	public $bnet; // our base botnet class for low level connection handling and routing
	
	public $last_in;
	
	public function netdie() {
		$this->bnet->netdie();
	}
	
	function __construct($ip, $port) {
		global $ppl;
		$this->Iusers = &$ppl;
		$this->bnet = new hub_bnet($ip, $port);
	}
	
	function process() {
		$this->bnet->read();
		$this->bnet->pingz();
		$this->bnet->checkauths();
		$this->bnet->cleanbuffers();
		while($this->bnet->hasdata()) {
			$data = $this->bnet->getdata();
			$arg2 = $data['text'];
			echo "(BotNet-In) User: $data[user] Id: $data[id] Text: $data[text]\n";
			$data['text'] = trim($data['text']);
			$arg = explode(' ', $data['text']);
			$arg = argClean($arg);
			$user = $data['user'];
			if(empty($arg)) continue;			
			if(strtolower($arg[0]) != 'auth' && strtolower($arg[0]) != 'botauth') {// lets not steal our own passes kthx
				$this->bnet->route('-1', '&raw', ":BotNetwork!BotNetwork@hidden PRIVMSG &raw :(BotNet-In) Id: $data[id] User: $data[user] Text: $data[text]");
				$this->last_in = "(from: $data[user]) $data[text]";
			} else {
				//$irc->msg('#botstaff', "$data[user] connected to botnet.");
				$this->bnet->route('-1', '&raw', ":BotNetwork!BotNetwork@hidden PRIVMSG &raw :(AUTHED) Id: $data[id] User: $data[user]");
				$this->bnet->route('-1', '&logs', ":BotNetwork!BotNetwork@hidden PRIVMSG &logs :(BNET AUTHED) Id: $data[id] User: $data[user]");
				$this->last_in = "(from: $data[user]) AUTH";
			}  // That shouldn't be triggered anyway checkauths clears it

			if($arg[0]{0} == ':') { // Should only be sent by bots, these lines relay us information about IRC
				$arg[0] = ridfirst($arg[0]);
				switch(strtolower($arg[0])) {
					case 'create'://create the channel to receive info, called when a bot joins a channel
						// :create #chan
						$this->l_create($user, $arg[1]);
						break;
					case 'lostchan'://Our bot has left a channel
						// :lostchan #chan
						$this->l_lostchan($user, $arg[1]);
						break;
					case 'lostirc':
						// :lostirc
						foreach($this->llostirc_slot as $slot) {
							$slot['function']($this, $user);
						}
						$this->Lusers[$user]['botinfo']['onirc'] = 'No';
						$this->l_cleanup($user);
						break;
					case 'gotirc':
						// :gotirc
						foreach($this->llostirc_slot as $slot) {
							$slot['function']($this, $user);
						}
						$this->Lusers[$user]['botinfo']['onirc'] = 'Not Authed';
						break;
					case 'gotauth':
						$this->Lusers[$user]['botinfo']['onirc'] = 'Yes';
						break;
					case 'ctime'://time of channels creation from :clone.GameSurge.net 329 GothLinux #bots 1212526432
						// :ctime #chan unixtime
						if($this->bot_canwrite($user, strtolower($arg[1])))
							$this->Ichans[strtolower($arg[1])]['time'] = $arg[2];
						break;
					case 'nick':// tells us about nick changes
						// :nick oldnick newnick
						$this->l_nick($user, $arg[1], $arg[2]);
						break;
					case 'join'://user joined a channel
						// :join nick ident@host #chan
						// TODO if user is new do a /who and get ircflags possibly make a /who queue for this and execute every few min (/who can have commas)
						$nick = $arg[1];
						$new = false;
						if(!array_key_exists($nick, $this->Iusers)) {
							$new = true;
						}
						$this->Iusers[$nick]['frombot'][$user] = $user;
						$this->Iusers[$nick]['nick'] = $nick;
						$this->Iusers[$nick]['host'] = $arg[2];

						if(!array_key_exists('user', $this->Iusers[$nick]) || $this->Iusers[$nick]['user'] == '') { // no need to keep updating this info
							$this->Iusers[$nick]['user'] = host2hand($arg[2]);
							if($this->Iusers[$nick]['user'] == '') {
								$this->Iusers[$nick]['flags'] = NULL;
							} else {
								$this->Iusers[$nick]['flags'] = flags($this->Iusers[$nick]['user']);
							}
						}
						if($new) {
							foreach($this->lppladd_slot as $slot) {
								$slot['function']($this, $nick, $arg[2]);
							}
						}
						$this->l_join($user, $arg[1], $arg[3]);
						break;
					case 'names':
						//:names #chan name1 name2 etc
						$chan = $arg[1];
						array_shift($arg);array_shift($arg);
						foreach($arg as $nick) {
							if(!array_key_exists($nick, $this->Iusers)) {
								$this->Iusers[$nick]['frombot'][$user] = $user;
								$this->Iusers[$nick]['nick'] = $nick;
								$this->Iusers[$nick]['host'] = 'unknown@unknown';
							} else {
								$this->Iusers[$nick]['frombot'][$user] = $user;
							}
							$this->l_join($user, $nick, $chan);
						}
						break;
					case 'who':
						// :who ident host server nick flags :int realname
						$this->l_who($user, trim("$arg[1]@$arg[2]"), $arg[4], $arg[5]);
						break;
					case 'part':
						// :part #chan nick msg
						//l_part($bot, $who, $chan, $msg)
						$this->l_part($user, $arg[2], $arg[1], arg_range($arg, 3, -1));
						break;
					case 'quit':
						// :quit nick :msg
						$this->l_quit($user, $arg[1], arg_range($arg, 2, -1));
						break;
					case 'mode':
						// :mode nick #chan modechange
						$this->l_mode($user, $arg[1], $arg[2], arg_range($arg, 3, -1));
						break;
					case 'ban':
						//:ban bot #chan ban!ban@ban bywho time
						//:BAN BotOps #bots fark!*@* GothLinux 1212686858
						//($bot, $who, $chan, $ban, $time)
						$this->l_ban($user, $arg[4], $arg[2], $arg[3], $arg[5]);
						break;
					case 'chgtopic': //received on topic changes
						//:CHGTOPIC nick #chan topic
						if($this->bot_canwrite($user, strtolower($arg[1]))) {
							$this->Ichans[strtolower($arg[2])]['topic']['text'] = arg_range($arg, 3, -1);
							$this->Ichans[strtolower($arg[2])]['topic']['who'] = $arg[1];
							$this->Ichans[strtolower($arg[2])]['topic']['time'] = time();
							$fhost = "$arg[1]!" . $this->Iusers[$arg[1]]['host'];
							$this->route(-1, strtolower($arg[2]), ":$fhost TOPIC $arg[2] :" . arg_range($arg, 3, -1));
						}
						break;
					case 'topic': // received on initial topic
						// :topic #chan the topic
						if($this->bot_canwrite($user, strtolower($arg[1]))) {
							$this->Ichans[strtolower($arg[1])]['topic']['text'] = arg_range($arg, 2, -1);
						}
						break;
					case 'topicinfo': // give info on the topic
						// :topicinfo #chan nick unixtime
						if($this->bot_canwrite($user, strtolower($arg[1]))) {
							$this->Ichans[strtolower($arg[1])]['topic']['who'] = $arg[2];
							$this->Ichans[strtolower($arg[1])]['topic']['time'] = $arg[3];
						}
						break;
					case 'kick':
						//currently if its us being kicked our bot will send a lostchan line for us
						// :kick whonick bynick #chan :message
						$arg[4] = ridfirst($arg[4]);
						$this->l_kick($user, $arg[1], $arg[2], $arg[3], arg_range($arg, 4, -1));
						break;
					case 'privmsg':
						// :privmsg nick #chan message
						if($this->bot_canwrite($user, $arg[2])) {
							$fhost = "$arg[1]!" . $this->Iusers[$arg[1]]['host'];
							$msg = arg_range($arg, 3, -1);
							$this->route(-1, $arg[2], ":$fhost PRIVMSG $arg[2] :$msg");
							foreach($this->lprivmsg_slot as $slot) {
								if($slot['on'] == '' || pmatch($slot['on'], $msg)) {
									$slot['function']($arg[1], $arg[2], $msg);
								}
							}
						}
						break;
					case 'notice':
						// :notice nick #chan message
						if($this->bot_canwrite($user, $arg[2])) {
							$fhost = "$arg[1]!" . $this->Iusers[$arg[1]]['host'];
							$msg = arg_range($arg, 3, -1);
							$this->route(-1, $arg[2], ":$fhost NOTICE $arg[2] :$msg");
							foreach($this->lnotice_slot as $slot) {
								if($slot['on'] == '' || pmatch($slot['on'], $msg)) {
									$slot['function']($arg[1], $arg[2], $msg);
								}
							}
						}
						break;
				}
				continue; // don't parse our text in next switch
			}
			
			switch(strtolower($arg[0])) {
				case 'authed': //an authed line is regenerated by checkauths
					if($this->bnet->isbot($data['id'])) {
						$this->join($data['user'], '&bots');
						$this->join($data['user'], '&botchan');
						$this->join($data['user'], '&debug');
						$this->join($data['user'], '&scrims');
						$this->Lusers[$data['user']] = Array('isbot' => true, 'name' => $data['user'], 'id' => $data['id']);
						break;
					}
					if($this->bnet->isweb($data['id'])) {
						$this->Lusers[$data['user']] = Array('isweb' => $this->bnet->isweb($data['id']), 'name' => $data['user'], 'id' => $data['id']);
						$this->join($data['user'], '&botchan');
						break;
					}
					foreach($this->auth_slot as $call) {
						$call['function']($data['user']);
					}
					$this->join($data['user'], '&bots');
					if(hasflags($data['user'], 'O') == 1) {
						$this->join($data['user'], '&debug');
					}
					if(hasflags($data['user'], 'A') == 1) {
						$this->join($data['user'], '&logs');
					}
					if(hasflags($data['user'], 'D') == 1) {
						//$this->join($data['user'], '&raw');
						$this->join($data['user'], '&botchan');
					}
					$this->Lusers[$data['user']] = Array('isbot' => false, 'name' => $data['user'], 'id' => $data['id']);
					break;
				case 'join': //TODO update join command so you can /join #chan1,#chan2
					if(count($arg) < 2) break;
					$chan = $arg[1];
					$this->join($data['user'], $chan);
					foreach($this->join_slot as $call) {
						if($call['on'] == '' || strtolower($call['on']) == strtolower($chan))
							$call['function']($data['user'], $chan);
					}
					break;
				case 'part':
					$chan = $arg[1];
					if(array_key_exists(2, $arg))
						$arg[2] = ridfirst($arg[2]);
					else
						$arg[2] = '';
					$msg = arg_range($arg, 2,-1);
					$this->part($user, $data['id'], $chan, $msg);
					foreach($this->part_slot as $call)
						if($call['on'] == '' || strtolower($call['on']) == strtolower($chan))
							$call['function']($data['user'], $chan, $msg);
					break;
				case 'quit':
					if(array_key_exists(1, $arg))
						$arg[1] = ridfirst($arg[1]);
					$msg = arg_range($arg, 1, -1);
					if(array_key_exists('quit', $this->bnet->data[$data['id']]) && $this->bnet->data[$data['id']]['quit'] == true) {
						//we've already processed this quit. . .
						$this->bnet->close($data['id'], $msg);
						break;
					}
					if(!array_key_exists('quit', $this->bnet->data[$data['id']])) {
						$this->bnet->raw($data['id'], "ERROR :Closing Link: hidden (Client Quit)");
						
					}
					$this->bnet->data[$data['id']]['quit'] = true;
					foreach($this->quit_slot as $call)
						if($call['on'] == '' || pmatch($call['on'], $msg))
							$call['function']($data['user'], $msg);
					if($this->bnet->isbot($data['id'])) {
						$this->l_cleanup($user);
						$this->bnet->data[$data['id']]['isbot'] = false;
						unset($this->Lusers[$user]);
						$this->bnet->close($data['id'], $msg);
					} else {
						unset($this->Lusers[$user]);
						$this->bnet->close($data['id'], $msg);
					}
					break;
				case 'privmsg':
					$this->bnet->route($data['id'], $arg[1], ":$data[user]!$data[user]@hidden " . $arg2);
					$target = $arg[1];
					$arg[2] = ridfirst($arg[2]);
					$msg = arg_range($arg, 2, -1);
					$cmd = $arg[2];
					$arg2 = arg_range($arg, 3, -1);
					array_shift($arg);array_shift($arg);array_shift($arg);
					foreach($this->cmd_slot as $call)
						if($call['on'] == '' || strtolower($call['on']) == strtolower($cmd))
							$call['function']($this, $data['user'], $target, $arg, $arg2);
					break;
				case 'notice':
					$this->bnet->route($data['id'], $arg[1], ":$data[user]!$data[user]@hidden " . $arg2);
					$target = $arg[1];
					$arg[2] = ridfirst($arg[2]);
					$msg = arg_range($arg, 2, -1);
					break;
				case 'list':
					if(!array_key_exists(1, $arg))
						$arg[1] = '';
					$this->clist($data['user'], $data['id'], $arg[1]);
					break;
				case 'ping':
					$this->bnet->raw($data['id'], ":BotNetwork PONG BotNetwork :" . arg_range($arg, 1, -1));
					break;
				case 'names':
					$this->names($data['user'], $data['id'], trim($arg[1]));
					break;
			}
		}
	}
	
	function l_mode($bot, $who, $chan, $mode) {
		$chan = strtolower($chan);
		if(!$this->bot_canwrite($bot, $chan)) {
			return;
		}
		$fhost = $who;
		if(array_key_exists($who, $this->Iusers))
			$fhost = "$who!" . $this->Iusers[$who]['host'];
		$this->route(-1, $chan, ":$fhost MODE $chan $mode");
		$modeargs = explode(' ', $mode);
		$mode = str_split(trim($modeargs[0]));
		$add = false; $del = false;
		$count = 1; // keep track of which arg we are on
		foreach($mode as $m) {
			if($m == '+') {
				$add = true;
				$del = false;
				continue;
			}
			if($m == '-') {
				$add = false;
				$del = true;
				continue;
			}
			if($add) {
				switch($m) { // some modes have args some are bans op/deop etc
					case 'b':
						$this->l_ban($bot, $who, $chan, $modeargs[$count++], time());
						break;
					case 'o':
						$this->l_op($modeargs[$count++], $chan, $who); //the irc server fixes case for us
						break;
					case 'v':
						$this->l_voice($modeargs[$count++], $chan, $who);
						break;
					case 'l':
						$this->Ichans[$chan]['modes'][$m] = $modeargs[$count++];
						break;
					case 'k':
						$this->Ichans[$chan]['modes'][$m] = $modeargs[$count++];
						break;
					default:
						$this->Ichans[$chan]['modes'][$m] = '';
						foreach($this->lmode_slot as $slot) {
							if(cisin($m, $slot['on'])) {
								$slot['function']($this, $who, $chan, $m, true);
							}
						}
				}
				continue;
			}
			if($del) {
				switch($m) { // some modes have args some are bans op/deop etc
					case 'b':
						$this->l_unban($bot, $who, $chan, $modeargs[$count++]);
						break;
					case 'o':
						$this->l_deop($modeargs[$count++], $chan, $who); //the irc server fixes case for us
						break;
					case 'v':
						$this->l_devoice($modeargs[$count++], $chan, $who); //the irc server fixes case for us
						break;
					default:
						unset($this->Ichans[$chan]['modes'][$m]);
						foreach($this->lmode_slot as $slot) {
							if(cisin($m, $slot['on'])) {
								$slot['function']($this, $who, $chan, $m, false);
							}
						}
				}
				continue;
			}
		}
	}
	
	function l_op($who, $chan, $by) { // no need to check if our bot can write since this gets called from l_mode
		$this->Ichans[$chan]['users'][$who]['modes']['o'] = 'o';
		foreach($this->lusermode_slot as $slot)
			$slot['function']($this, $who, $chan, $by, '+o');
	}
	
	function l_deop($who, $chan, $by) { // no need to check if our bot can write since this gets called from l_mode
		unset($this->Ichans[$chan]['users'][$who]['modes']['o']);
		foreach($this->lusermode_slot as $slot)
			$slot['function']($this, $who, $chan, $by, '-o');
	}
	
	function l_voice($who, $chan, $by) { // no need to check if our bot can write since this gets called from l_mode
		$this->Ichans[$chan]['users'][$who]['modes']['v'] = 'v';
		foreach($this->lusermode_slot as $slot)
			$slot['function']($this, $who, $chan, $by, '+v');
	}
	
	function l_devoice($who, $chan, $by) { // no need to check if our bot can write since this gets called from l_mode
		unset($this->Ichans[$chan]['users'][$who]['modes']['v']);
		foreach($this->lusermode_slot as $slot)
			$slot['function']($this, $who, $chan, $by, '-v');
	}
	
	function l_ban($bot, $who, $chan, $ban, $time) { // this can be called by l_mode and by the bots giving us the initial ban listing
		$chan = strtolower($chan);
		if(!$this->bot_canwrite($bot, $chan)) {
			return;
		}
		$this->Ichans[$chan]['bans'][$ban] = Array('mask' => $ban, 'time' => $time, 'who' => $who);
		foreach($this->lban_slot as $slot) {
			$slot['function']($this, $chan, $who, $ban, $time); 
		}
	}
	
	function l_unban($bot, $who, $chan, $ban) { // no need to check if our bot can write since this gets called from l_mode
		$chan = strtolower($chan);
		if(!$this->bot_canwrite($bot, $chan)) {
			return;
		}
		unset($this->Ichans[$chan]['bans'][$ban]);
		foreach($this->lunban_slot as $slot) {
			$slot['function']($this, $chan, $who, $ban);
		}
	}
	
	function l_who($bot, $host, $nick, $flags) {
		if(!array_key_exists($nick, $this->Iusers)) { // most likely we are processing a who response after the user quited
			return;
		}
		$new = false;
		if(!array_key_exists('host', $this->Iusers[$nick]) || $this->Iusers[$nick]['host'] == 'unknown@unknown') {
			$new = true;
		}
		$this->Iusers[$nick]['frombot'][$bot] = $bot;
		$this->Iusers[$nick]['host'] = $host;
		$this->Iusers[$nick]['ircflags'] = $flags;
		$this->Iusers[$nick]['onsplit'] = false;
		if(!array_key_exists('user', $this->Iusers[$nick]) || $this->Iusers[$nick]['user'] == '') {
			$this->Iusers[$nick]['user'] = host2hand($host);
			if($this->Iusers[$nick]['user'] == '') {
				$this->Iusers[$nick]['flags'] = NULL;
			} else {
				$this->Iusers[$nick]['flags'] = flags($this->Iusers[$nick]['user']);
			}
		}
		if($new) {
			foreach($this->lppladd_slot as $slot) {
				$slot['function']($this, $nick, $host);
			}
		}
	}
	
	function l_nick($bot, $oldnick, $newnick) {
		//check if nick has already been changed
		$fhost = '';
		if(array_key_exists($oldnick, $this->Iusers)) {
			//update Iusers
			$olduser = $this->Iusers[$oldnick];
			$fhost = $oldnick . '!' . $this->Iusers[$oldnick]['host'];
			unset($this->Iusers[$oldnick]);
			$olduser['nick'] = $newnick;
			$this->Iusers[$newnick] = $olduser;
		} else {
			return;
		}
		//update all channels['users']
		foreach($this->Ichans as $chan) {
			foreach($chan['users'] as $u) {
				if($u['nick'] == $oldnick) {
					$nu = $u;
					$nu['nick'] = $newnick;
					unset($this->Ichans[strtolower($chan['name'])]['users'][$oldnick]);
					$this->Ichans[strtolower($chan['name'])]['users'][$newnick] = $nu;
					$this->Iroute($newnick, ":$fhost NICK :$newnick");
				}
			}
		}
		if($this->bot_canslot($bot, $newnick)) {
			foreach($this->lnick_slot as $slot) {
				$slot['function']($this, $oldnick, $newnick);
			}
		}
	}
	
	function l_cleanup($user) {
		/*
		 * Clean up users
		 */
		$ks = array_keys($this->Iusers);
		foreach($ks as $k) {
			if(count($this->Iusers[$k]['frombot']) > 1 && array_search($user, $this->Iusers[$k]['frombot']) !== FALSE)
				unset($this->Iusers[$k]['frombot'][$user]);
			if(count($this->Iusers[$k]['frombot']) == 1 && array_search($user, $this->Iusers[$k]['frombot']) !== FALSE) {
				foreach($this->lppldel_slot as $slot) {
					if(!array_key_exists('user', $this->Iusers[$k])) {
						$this->Iusers[$k]['user'] = '';
					}
					$slot['function']($this, $k, $this->Iusers[$k]['host'], $this->Iusers[$k]['user']);
				}
				unset($this->Iusers[$k]);
			}
		}
		/*
		 * Clean up channels
		 */
		$ks = array_keys($this->Ichans);
		foreach($ks as $k) {
			$this->l_lostchan($user, $k);
		}
	}
	
	function bot_canslot($bot, $nick) { // Make sure a slot only gets called once per event
		$nick = $this->Iusers[$nick];
		$bots = array_keys($nick['frombot']);
		if($bot == $bots[0]) {
			return true;
		} else {
			return false;
		}
	}
	
	function bot_canwrite($bot, $chan) { // checks if our bot is first in the frombot array on a channel
		if(!array_key_exists(strtolower($chan), $this->Ichans)) {
			return true;
		}
		$c = $this->Ichans[strtolower($chan)];
		$bots = array_keys($c['frombot']);
		if($bot == $bots[0]) {
			return true;
		} else {
			return false;
		}
	}

	//return channels an IRC user shares with our bots
	function get_Iuser_chans($user) {
		if(array_key_exists('', $this->Ichans)) {
			unset($this->Ichans['']);
		}
		$chans = Array();
		foreach($this->Ichans as $chan) {
			$keys = array_keys($chan['users']);
			foreach($keys as $k) {
				$u = $chan['users'][$k];
				if(!array_key_exists('nick', $u)) {
					$this->msg('&debug', "Warning $k in $chan[name] caught without nick key deleteing");
					unset($this->Ichans[strtolower($chan['name'])]['users'][$k]);
				} else {
					if($u['nick'] == $user) {
						$chans[strtolower($chan['name'])] = strtolower($chan['name']);
					}
				}
			}
		}
		return $chans;
	}
	
	function l_join($bot, $nick, $chan) {
		$chan = strtolower($chan);
		if($this->bot_canwrite($bot, $chan)) {
			$this->Ichans[$chan]['users'][$nick] = Array('nick' => $nick, 'time' => time(), 'modes' => Array());
			$fhost = $nick . '!' . $this->Iusers[$nick]['host'];
			$this->route(-1, $chan, ":$fhost JOIN $chan");
			foreach($this->ljoin_slot as $slot) {
				if(strtolower($slot['on']) == $chan || $slot['on'] == '') {
					$slot['function']($this, $nick, $chan);
				}
			}
		}
	}
	
	function l_part($bot, $who, $chan, $msg) {
		$chan = strtolower($chan);
		if($this->bot_canwrite($bot, $chan)) {
			if(!array_key_exists($who, $this->Ichans[$chan]['users'])) {
				$this->msg('&debug', "Warning $who parting $chan but is not set in chan['users']");
			} else {
				unset($this->Ichans[$chan]['users'][$who]);
			}
			$fhost = $who . '!' . $this->Iusers[$who]['host'];
			$this->route(-1, $chan, ":$fhost PART $chan :$msg");
			foreach($this->lpart_slot as $slot) {
				if(strtolower($slot['on']) == $chan || $slot['on'] == '') {
					$slot['function']($this, $bot, $who, $chan, $msg);
				}
			}
		}
		//If user is no longer in any chans unset from Iusers
		foreach($this->Ichans as $chan) {
			foreach($chan['users'] as $u) {
				if($u['nick'] == $who) {
					return;
				}
			}
		}
		if(!array_key_exists($who, $this->Iusers)) {
			return; // User was already deleted
		}
		foreach($this->lppldel_slot as $slot) {
			if(!array_key_exists('user', $this->Iusers[$who])) {
				$this->Iusers[$who]['user'] = '';
			}
			$slot['function']($this, $who, $this->Iusers[$who]['host'], $this->Iusers[$who]['user']);
		}
		unset($this->Iusers[$who]);
	}
	
	function l_kick($bot, $who, $by, $chan, $msg) {
		$chan = strtolower($chan);
		if($this->bot_canwrite($bot, $chan)) {
			unset($this->Ichans[$chan]['users'][$who]);
			$fhost = $by . '!' . $this->Iusers[$by]['host'];
			$this->route(-1, $chan, ":$fhost KICK $chan $who :$msg");
			foreach($this->lkick_slot as $slot) {
				$slot['function']($this, $by, $chan, $who, $msg);
			}
		}
		//If user is no longer in any chans unset from Iusers
		foreach($this->Ichans as $chan) {
			foreach($chan['users'] as $u) {
				if($u['nick'] == $who) {
					return;
				}
			}
		}
		foreach($this->lppldel_slot as $slot) {
			$slot['function']($this, $who, $this->Iusers[$who]['host'], $this->Iusers[$who]['user']);
		}
		unset($this->Iusers[$who]);
	}
	
	function l_quit($bot, $who, $msg) {
		// check if user hasn't already been deleted
		if(!array_key_exists($who, $this->Iusers))
			return;
		foreach($this->lquit_slot as $slot) {
			$slot['function']($this, $who, $msg);
		}
		$fhost = $who . '!' . $this->Iusers[$who]['host'];
		$this->Iroute($who, ":$fhost QUIT :$msg");
		
		// save this headache for another day
		//if($msg == '*.net *.split') {
		//	$this->Iusers[$who]['onsplit'] = time();
		//	return;
		//}
		
		//remove user
		foreach($this->lppldel_slot as $slot) {
			if(!array_key_exists('user', $this->Iusers[$who])) {
				$this->Iusers[$who]['user'] = '';
			}
			$slot['function']($this, $who, $this->Iusers[$who]['host'], $this->Iusers[$who]['user']);
		}
		unset($this->Iusers[$who]);
		//remove user from channels
		foreach($this->get_Iuser_chans($who) as $c) {
			foreach($this->Ichans[$c]['users'] as $u) {
				if($u['nick'] == $who) {
					unset($this->Ichans[$c]['users'][$u['nick']]);
				}
			}
		}
	}
	
	function l_create($bot, $chan) {
		$c = $chan;
		$chan = strtolower($chan);
		if(array_key_exists($chan, $this->Ichans)) {
			$this->Ichans[$chan]['frombot'][$bot] = $bot;
			return;
		} else {
			$this->Ichans[$chan] = Array();
			$this->Ichans[$chan]['name'] = $c;
			$this->Ichans[$chan]['users'] = Array();
			$this->Ichans[$chan]['log'][] = 'CREATED ' . time();
			$this->Ichans[$chan]['modes'] = Array();
			$this->Ichans[$chan]['bans'] = Array();
			$this->Ichans[$chan]['topic'] = Array('time' => '', 'who' => '', 'text' => '');
			$this->Ichans[$chan]['time'] = 0;
			$this->Ichans[$chan]['frombot'][$bot] = $bot;
		}
	}
	
	function l_lostchan($bot, $chan) { 
		$c = $chan;
		$chan = strtolower($chan);
		if(count($this->Ichans[$chan]['frombot']) == 1 && array_key_exists($bot, $this->Ichans[$chan]['frombot'])) {
			foreach($this->Ichans[$chan]['users'] as $u) {
				if(count($this->get_Iuser_chans($u['nick'])) == 1) {
					foreach($this->lppldel_slot as $slot) {
						$who = $u['nick'];
						if(array_key_exists($who, $this->Iusers)) {
							$slot['function']($this, $who, $this->Iusers[$who]['host'], $this->Iusers[$who]['user']);
						}
					}
					unset($this->Iusers[$u['nick']]);
				}
			}
			unset($this->Ichans[$chan]);
			foreach($this->bnet->chans as $ch) {
				if(array_key_exists('name', $ch) && $ch['name'] == $chan) {
					foreach($ch['users'] as $u) {
						if($u['id'] != -1)
							$this->bnet->raw($u['id'], ":BotNetwork!BotNetwork@hidden KICK $c $u[name] :Channel Lost");
					}
				}
			}
			unset($this->bnet->chans[$chan]);
		} else {
			unset($this->Ichans[$chan]['frombot'][$bot]);
		}
	}
	
	function Iroute($nick, $text) {
		$users = Array();
		foreach($this->get_Iuser_chans($nick) as $c) {
			if(array_key_exists(strtolower($c), $this->bnet->chans)) {
				foreach($this->bnet->chans[strtolower($c)]['users'] as $u) {
					$users[$u['id']] = $u['id'];
				}
			}
		}
		foreach($users as $target) {
			$this->bnet->raw($target, $text);
		}
	}
	
	function privmsg($target, $fromid, $message) {
		$this->bnet->privmsg($target, $fromid, $message);
	}
	
	function msg($target, $msg) {
		$this->bnet->privmsg($target, '-1', $msg);
	}
	
	function notice($target, $msg) {
		$this->bnet->notice($target, '-1', $msg);
	}
	
	function register($type, $function, $mask) {
		$arr = &$this->$type;
		$arr[$function] = Array('function' => $function, 'on' => $mask);
	}
	
	function join($user, $chan) {
		if($chan{0} != '&' && !array_key_exists(strtolower($chan), $this->Ichans)) {
			$this->notice($user, 'That #Channel doesn\'t exist, You can only create channels that start with a &');
			return;
		}
		$chan = strtolower($chan);
		if(cisin(',', $chan)) {
			$this->notice($user, 'Channels cannot contain a comma');
			return;
		}
		$id = $this->bnet->getid($user);
		if($this->bnet->isin($id, $chan)) {
			return; // user is already in that channel..
		}
		$this->bnet->join($user, $id, $chan); // tell bnet user is in the channel for routings
		$this->bnet->raw($id, ":$user!$user@hidden JOIN $chan");
		$this->names($user, $id, $chan);
		if($chan{0} == '#') { //UH-OH ITS AN IRC CHANNEL lets send more info
			$us = ':BotNetwork';
			$modes = '+' . implode('', array_keys($this->Ichans[$chan]['modes']));
			$this->route(-1, $user, "$us 324 $user $chan $modes");
			$this->route(-1, $user, "$us 329 $user $chan " . $this->Ichans[$chan]['time']);
			$this->route(-1, $user, "$us 332 $user $chan :" . $this->Ichans[$chan]['topic']['text']);
			$this->route(-1, $user, "$us 333 $user $chan " . $this->Ichans[$chan]['topic']['who'] . ' ' . $this->Ichans[$chan]['topic']['time']);
		}
		$this->bnet->route($id, $chan, ":$user!$user@hidden JOIN $chan");
		$pre = 'a';
		if($id == '-1') $pre = 'q';
		if($this->bnet->isbot($id)) $pre = 'h'; // (qaohv)~&@%+
		$this->bnet->route(-1, $chan, ":BotNetwork MODE $chan +$pre $user");
	}
	
	function part($user, $id, $chan, $msg) {
		if($this->bnet->part($id, $chan)) {
			$this->raw($id, ":$user!$user@hidden PART $chan");
			$this->route($id, $chan, ":$user!$user@hidden PART $chan :$msg");
		} else {
			$this->raw($id, ":NOTICE ERROR :You're not on $chan");
		}
	}
	
	function names($user, $id, $chan) {
		$names = $this->bnet->names($chan);
		if($chan{0} == '#') {
	    	foreach($this->Ichans[strtolower($chan)]['users'] as $name) {
	    		$mode = '';
	    		if(array_key_exists('o', $name['modes'])) {
	    			$mode .= '@';
	    		}
	    		if(array_key_exists('v', $name['modes'])) {
	    			$mode .= '+';
	    		}
	    		$names .= $mode . $name['nick'] . ' ';
	    	}
	    }
	    $names = trim($names);
	    $this->bnet->raw($id, ":BotNetwork 353 $user = $chan :" . $names);
    	$this->bnet->raw($id, ":BotNetwork 366 $user $chan :End of /NAMES list.");
	}
	
	function clist($user, $id, $chans) {
		//should recognise $arg[0] as comma sep list of chans
		//and $arg[1] to limit to only irc chans from a bot
		$this->bnet->raw($id, ":BotNetwork 321 $user Channel Users :[modes] Topic");
		foreach($this->bnet->chans as &$chan)
			$this->bnet->raw($id, ":BotNetwork 322 $user " . $chan['name'] . ' ' . count($chan['users']) . " :[] modes and topic not finished");
		$this->bnet->raw($id, ":BotNetwork 323 $user :End of /LIST");
	}
	
	function raw($id, $msg) {
		$this->bnet->raw($id, $msg);
	}
	
	function route($id, $target, $msg) {
		$this->bnet->route($id, $target, $msg);
	}
}

//$botson = Array(); // array of bots currently on bnet
//$ppl = Array();
/*
  nick
    host = their hostname.
    authserv = authserv name
    user = BN username
    bot = Array() bots that provided data;
    chans = channels in common with BN (in order of most active in?)
            "Bot-02:#bots Bot-06:#pandabears" etc
    flags = BN flags
*/
/*
$ppl_fld = Array();
$ponged = time() + 30;
$time = time();
$timers[$time + 30] = 'rejoin($irc, $channels, $timers);';
$timers[$time + 301] = 'checkapps();';

$bnet = new bnet_hub($bnip, $bnport);

function resettimers(&$timers) {
  $time = time();
  aclear($timers);
  $timers[$time + 30] = 'rejoin($irc, $channels, $timers);';
}

while(1) {
  //usleep(25000); //sleep for 25 milisec might help cpu
  $sox->select();
  $irc->process();
  $bnet->read();
  $bnet->pingz();
  $bnet->checkauths();
  $bnet->cleanbuffers();
  while($bnet->hasdata()) {
    $data = $bnet->getdata(); // $data = Array('user', 'id', 'text')
    $arg2 = $data['text'];
    echo "(BotNet-In) User: $data[user] Id: $data[id] Text: $data[text]\n";
    $data['text'] = trim($data['text']);
    $arg = explode(' ', $data['text']);
    $arg = argClean($arg);
    if(empty($arg)) continue;
    if(strtolower($arg[0]) != 'auth') {// lets not steal our own passes kthx
      $bnet->route('-1', '&raw', ":BotNetwork!BotNetwork@hidden PRIVMSG &raw :(BotNet-In) Id: $data[id] User: $data[user] Text: $data[text]");
    } else {
      $irc->msg('#botstaff', "$data[user] connected to botnet.");
      $bnet->route('-1', '&raw', ":BotNetwork!BotNetwork@hidden PRIVMSG &raw :(AUTHED) Id: $data[id] User: $data[user]");
    }  // That shouldn't be triggered anyway checkauths clears it
    switch(strtolower($arg[0])) {
      case 'list'; // channel list
       $arg = array_shift($arg);
       $bnet->cmd_list($data['id'], $data['user'], $arg);
       break;
      case 'notice':
        $bnet->route($data['id'], $arg[1], ":$data[user]!$data[user]@hidden " . $arg2);
        break;
      case 'privmsg': // PRIVMSG #channel :hi u r fgt
        $bnet->route($data['id'], $arg[1], ":$data[user]!$data[user]@hidden " . $arg2);
        if(($arg[1]{0} == '&' || $arg[1]{0} == '#') && $arg[2]{1} == '?') {
          //commands for the hub to respond to
          $arg[2] = ridfirst(ridfirst($arg[2]));
          switch(strtolower(trim($arg[2]))) {
            case 'ping':
              $bnet->privmsg($arg[1], '-1', "\2$data[user]:\2 Pong!");
              break;

       case 'ppladd':
         if(!array_key_exists($arg[1], $ppl))
           $ppl[$arg[1]] = Array('host' => $arg[2], 'bot' => Array($data['user'] => $data['user']));
         else
           $ppl[$arg[1]]['bot'][$data['user']] = $data['user'];
         break;
       case 'ppldel':
         //check if other bots still see nick
         if(!array_key_exists($arg[1], $ppl)) continue;
         if(count($ppl[$arg[1]]['bot']) > 1 && array_search($data['user'], $ppl[$arg[1]]['bot']) !== FALSE)
           unset($ppl[$arg[1]]['bot'][$data['user']]);
          if(count($ppl[$arg[1]]['bot']) == 1 && array_search($data['user'], $ppl[$arg[1]]['bot']) !== FALSE)
            unset($ppl[$arg[1]]);
         break;
       case 'pplrename': // newnick oldnick
         if(!array_key_exists($arg[2], $ppl)) continue;
         if(count($ppl[$arg[2]]['bot']) > 1 && array_search($data['user'], $ppl[$arg[2]]['bot']) !== FALSE) {
           unset($ppl[$arg[2]]['bot'][$data['user']]);
           if(!array_key_exists($arg[1], $ppl))
             $ppl[$arg[1]] = Array('host' => $ppl[$arg[2]]['host'], 'bot' => Array($data['user'] => $data['user']));
           else
             $ppl[$arg[1]]['bot'][$data['user']] = $data['user'];
         }
         if(count($ppl[$arg[2]]['bot']) == 1 && array_search($data['user'], $ppl[$arg[2]]['bot']) !== FALSE) {
           if(!array_key_exists($arg[1], $ppl))
             $ppl[$arg[1]] = Array('host' => $ppl[$arg[2]]['host'], 'bot' => Array($data['user'] => $data['user']));
           else
             $ppl[$arg[1]]['bot'][$data['user']] = $data['user'];
           unset($ppl[$arg[2]]);
         }
         break;
       case 'lostirc':
         $botson[$data['user']]['quited'] = 'unhandled';
         break;
       case 'gotirc':
         unset($botson[$data['user']]['quited']);
      case 'ping':
        $bah = $arg;unset($bah[0]);$bah = implode(' ', $bah);
        $bnet->raw($data['id'], ":BotNetwork PONG BotNetwork :$bah");
       break; 
      case 'names':
        $bnet->cmd_names($data['id'], trim($arg[1]));
        break;
      case 'join':
        if($arg[1]{0} != '&') {
          $bnet->notice($data['user'], '-1', 'You can only join channels that start with a & for now');
          break;
        }
        $bnet->cmd_join($data['id'], strtolower($arg[1]));
        break;
      case 'part':
        if($arg[1]{0} != '&') {
          $bnet->notice($data['user'], '-1', 'You can only join/part channels that start with a & for now');
          break;
        }
        $bnet->cmd_part($data['id'], strtolower($arg[1]));
        break;
      case 'quit':
        $bnet->cmd_quit($data['id']);
        break;
    }
  }

  $keys = array_keys($botson);
  foreach($keys as &$key) {
    if(array_key_exists('quited', $botson[$key]) && $botson[$key]['quited'] == 'unhandled') {
      $irc->msg('#bots-dev', "Cleaning up data from $key");
      //clean up data provided to us by said bot
      //first additions to $ppl
      $ks = array_keys($ppl);
      foreach($ks as $k) {
        if(count($ppl[$k]['bot']) > 1 && array_search($key, $ppl[$k]['bot']) !== FALSE)
          unset($ppl[$k]['bot'][$key]);
        if(count($ppl[$k]['bot']) == 1 && array_search($key, $ppl[$k]['bot']) !== FALSE)
          unset($ppl[$k]);
      }
      $botson[$key]['quited'] = 'handled';
      if(array_key_exists('gone', $botson[$key]) && $botson[$key]['gone'] == TRUE)
        unset($botson[$key]);
    }
  }
*/