<?php
/**
 * hub_bnet.inc
 * - Base class for botnet hub
 * - handles low level communication
 * - keeps track of basic channels and users info for routing
 */

function makenice($s) {
	$s = explode("\n", $s);
	foreach ($s as &$l) {
		if(!empty($l)) {
			if($l{strlen($l)-1} == "\r") continue; // wewt its \r\n
			$l .= "\r";
		}
	}
	return implode("\n", $s);
}

class hub_bnet { // What everything connects to
	public $port; // The port we listen on
	public $bind; // The adress we listen on

	public $listen; // Keeps track of our listening socket
	public $sockets; // An array of all our sockets and the listen socket
	private $read; // Filled by socket select with activity sockets

	public $glines = Array();
	/*
	 * ['ip'] = Array();
	 * * ['reason']
	 * * ['by']
	 * * ['expire']
	 */
	
	public $chans;
	/* an array of channels that have local users
	 * this should only contain basic info needed for routing
	 * $chans['name'] = Array()
	 * - 'name' = our channel name again
	 * - 'users = Array()
	 *   - 'name' = name of user
	 *   - 'userid' = usersid
	 */

	public $data; // array users, ['socket_id']['option']
	/* Components for $data[]
	'name'	Users username blank if not authed yet
	'buffer'	A buffer that is an array of lines ending with \r if complete
	'ping'	How long it has been since a line has been recieved
	'pined'	true if we have sent user a ping that has not be replied to
	'id'	Socket ID
	'nick'	mirc like to have its nickname fucking shit
	'isbot'	bot or user
    'isweb' website user (or cpanel)
	'status'	connection ok? 0 for fine 1 for broke
	*/

	// little Wrapper to make notices easier
	function notice($target, $fromid, $message) {
		if($fromid == '-1')
		$n = 'BotNetwork';
		else
		$n = $this->data[$fromid]['name'];
		$this->route($fromid, $target, ":$n!$n@hidden NOTICE $target :$message");
	}

	// little Wrapper to make privmsgs easier
	function privmsg($target, $fromid, $message) {
		if($fromid == '-1')
		$n = 'BotNetwork';
		else
		$n = $this->data[$fromid]['name'];
		$this->route($fromid, $target, ":$n!$n@hidden PRIVMSG $target :$message");
	}

	function __construct($bind = '192.168.0.212', $port = '4502') {
		$this->sockets = array();
		$this->bind = $bind;
		$this->port = $port;
		if (!$this->listen = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
			echo 'Failed to create listen socket' . "\n";
			exit(1);
		}
		if(!socket_set_option($this->listen, SOL_SOCKET, SO_REUSEADDR, 1))
		exit(1);
		if(!socket_bind($this->listen, $this->bind, $this->port)) {
			echo "Failed to bind listen socket\n";
			exit(1);
		}
		socket_listen($this->listen); //, 32); the 32 just screwed everything up
		$this->sockets[] = $this->listen;
		$this->data = Array();
		//Join our hub to some channels
		$this->chans['&bots']['users'][] = Array('name' => 'BotNetwork', 'id' => '-1');
		$this->chans['&appsraw']['users'][] = Array('name' => 'BotNetwork', 'id' => '-1');
		$this->chans['&debug']['users'][] = Array('name' => 'BotNetwork', 'id' => '-1');
		$this->chans['&raw']['users'][] = Array('name' => 'BotNetwork', 'id' => '-1');
		$this->chans['&botchan']['users'][] = Array('name' => 'BotNetwork', 'id' => '-1');
		$this->chans['&logs']['users'][] = Array('name' => 'BotNetwork', 'id' => '-1');
		$this->chans['&scrims']['users'][] = Array('name' => 'BotNetwork', 'id' => '-1');
	}

	public function read() { //read data
		global $traceback;
		$this->read = $this->sockets;
		$e = NULL;$w = NULL; //TODO use sox_master for our sock select needs
		if(socket_select($this->read, $w, $e, 0, 300) == 0) // If nothing new return
		return;

		if(in_array($this->listen, $this->read)) { // Check for new connection
			$new = socket_accept($this->listen);
			$this->sockets[] = $new;
			unset($this->read[array_search($this->listen, $this->read)]);
			$id = array_search($new, $this->sockets);
			$ip = '';
			socket_getpeername($new, $ip);
			if(array_key_exists($ip, $this->glines)) {
				$this->raw($id, 'ERROR: G-Lined: ' . $this->glines[$ip]['reason']);
			}
			$this->data[$id] = Array('name' => '', 'buffer' => Array(), 'ping' => time(), 'id' => $id, 'nick' => '', 'pinged' => FALSE, 'ip', $ip); // add to the users array
			$this->raw($id, 'NOTICE AUTH :*** Please authenticate to use this service.');
			$this->raw($id, 'NOTICE AUTH :*** Usage: AUTH <username> <password>');
			$this->route(-1, '&logs', ":BotNetwork!BotNetwork@BotNetwork PRIVMSG &logs :New connection from $ip");
		}

		$keys = array_keys($this->read);
		foreach($keys as $key) { // Read data
			$traceback = 'bnet_read';
			$data = socket_read($this->read[$key], 4096, PHP_NORMAL_READ);
			$traceback = '';
			if($data === false) {
				$this->data[array_search($this->read[$key], $this->sockets)]['status'] = 1; //TODO alternative to ['status']
				$this->close(array_search($this->read[$key], $this->sockets));
				continue;
			}
			if(!empty($data)) {
				$data = makenice($data); // makesnice ensures our lines are \r\n
				$k = array_search($this->read[$key], $this->sockets);
				$temp = explode("\n", $data);
				$last = array_pop($this->data[$k]['buffer']);
				$barf = array_shift($temp); // strlen check is needed
				if($barf != '') {
					if(strpos($last, "\r") === FALSE) { // Last line wasn't finished
						array_push($this->data[$k]['buffer'], $last . $barf);
					} else {
						array_push($this->data[$k]['buffer'], $last);
						if(strpos($barf, "\r") !== FALSE)
						array_push($this->data[$k]['buffer'], $barf);
					}
					foreach($temp as &$line) {
						array_push($this->data[$k]['buffer'], $line);
					}
				}
			} // TODO Might need to put something here, empty data usually means error
		}
	}

	public function raw($id, $msg) {
		global $irc, $traceback;
		if($id == -1) return;
		$traceback = 'bnet_write';
		if($this->sockets[$id] != NULL)
		$num = socket_write($this->sockets[$id], $msg . "\r\n");
		else
		$num = FALSE;
		$traceback = '';
		if($num === FALSE) {
			$this->data[$id]['status'] = 1;
			$this->close($id);
		}
		//$irc->msg('#bots-dev', "Returned false when writing to $id");
	}

	public function is_real_webauth($id) {
		if(mysql_num_rows(mysql_query("SELECT * from webauths where id = '" . mysql_escape_string($id) . "'")) > 0) {
			return true;
		} else {
			return false;
		}
	}
	
	public function checkauths() { // goes through users that haven't finished loggin in and handles them
		global $version, $botson, $irc;
		foreach($this->data as &$user) { // go through every connection
			if($user['name'] == '') { // if they aren't authed
				$keys = array_keys($user['buffer']);
				foreach($keys as &$key) { // read their buffer lines
					$user['ping'] = time(); $user['ponged'] = FALSE;
					$line = $user['buffer'][$key];
					if($line == '')
					unset($this->data[$user['id']]['buffer'][$key]);
					//          if(strpos($line, "\r") === FALSE) // Line is not complete who cares
					//            continue; // thats all we can do for now
					$line = str_replace("\r", '', $line);
					$line = explode(' ', $line);
					if(strtolower($line[0]) == 'nick') {
						$user['nick'] = $line[1];
					} else {
						if(strtolower($line[0]) == 'auth') {
							if(empty($line[2])) { // No password given
								$this->raw($user['id'], ':BotNetwork NOTICE ' . $user['nick'] . ' :*** Error: No password given!');
								$this->raw($user['id'], ':BotNetwork NOTICE ' . $user['nick'] . ' :*** Usage: AUTH <username> <password>');
							} else {
								$row = mysql_fetch_array(mysql_query("SELECT pass FROM users WHERE name = '" . mysql_escape_string($line[1]) . "'"), MYSQL_ASSOC);
								if(md5(trim($line[2])) != $row['pass']) {
									$this->raw($user['id'], ':BotNetwork NOTICE ' . $user['nick'] . ' :*** INVALID PASSWORD!');
									$this->close($user['id']);
									continue;
								}
								$arf = $this->getid($line[1]); // make sure someone with that name isn't already on
								if($arf != NULL)
								$this->close($arf);
								$user['name'] = $line[1];
								$this->raw($user['id'], ':' . $user['nick'] .'!' . $user['name'] . '@hidden NICK :' . $user['name']);
								$user['nick'] = $user['name'];
								$this->raw($user['id'], ':BotNetwork 001 ' . $user['name'] . ' :Welcome to the BotNetwork BotNet, ' . $user['name']);
								$this->raw($user['id'], ':BotNetwork 002 ' . $user['name'] . ' :Your host is BotNetwork, version ' . $version);
								//:Lan.ID.net 004 kNiVeS Lan.ID.net u2.10.11.06-[FIRCu-0.3.0]-Dev dioswkgx biklmnopstvrcCN bklov
								//:xs4all.nl.quakenet.org 005 GothLinux WHOX WALLCHOPS WALLVOICES USERIP CPRIVMSG CNOTICE SILENCE=15 MODES=6 MAXCHANNELS=20 MAXBANS=45 NICKLEN=15 MAXNICKLEN=15 :are supported by this server
								//:xs4all.nl.quakenet.org 005 GothLinux TOPICLEN=250 AWAYLEN=160 KICKLEN=250 CHANTYPES=#& PREFIX=(ov)@+ CHANMODES=b,k,l,imnpstrDdcCNu CASEMAPPING=rfc1459 NETWORK=QuakeNet :are supported by this server
								$this->raw($user['id'], ':BotNetwork 005 ' . $user['name'] . ' WHOX WALLCHOPS WALLVOICES USERIP CPRIVMSG CNOTICE SILENCE=15 MODES=6 MAXCHANNELS=20 MAXBANS=45 NICKLEN=15 MAXNICKLEN=15 :are supported by this server');
								$this->raw($user['id'], ':BotNetwork 005 ' . $user['name'] . ' TOPICLEN=250 AWAYLEN=160 KICKLEN=250 CHANTYPES=#& PREFIX=(qaohv)~&@%+ CHANMODES=b,k,l,imnpstrDdcCNu CASEMAPPING=rfc1459 NETWORK=BotNetwork :are supported by this server');
								$user['buffer'][] = "authed\r";
							}
						} elseif(strtolower($line[0]) != 'user' && $line[0] != '' && strtolower($line[0]) != 'pong') {
							if($line[0] == 'botauth') {
								if($line[2] == 'kMSjfI33nNkiiMlwSF') {
									$arf = $this->getid($line[1]); // make sure someone with that name isn't already on
									if($arf != NULL) {
										$this->close($arf);
										$irc->msg('#bots-dev', "User by the name of $line[1] already on botnet, closing old connection.");
									}
									if(pmatch('BotWeb-ID-*', $line[1])) { // website attempt
										$id = explode('-', $line[1]);
										$id = $id[2];
										if(!$this->is_real_webauth($id)) {
											$this->route(-1, '&logs', ":BotNetwork!BotNetwork@BotNetwork PRIVMSG &logs :\2WARNING\2 attempted fake webauth");
											$this->raw($user['id'], ':BotNetwork NOTICE ' . $user['nick'] . ' :*** INVALID PASSWORD!');
											$this->close($user['id']);
											continue;
										} else {
											$user['name'] = $line[1];
											$user['nick'] = $line[1];
											$user['isweb'] = $id;
											$this->raw($user['id'], ':BotNetwork 001 ' . $user['name'] . ' :Welcome to the BotNetwork BotNet, ' . $user['name']);
											$user['buffer'][] = "authed\r";
										}
									} else {
										$user['name'] = $line[1];
										$botson[$line[1]] = Array();
										$user['isbot'] = true;
										$user['nick'] = $line[1];
										$this->raw($user['id'], ':BotNetwork 001 ' . $user['name'] . ' :Welcome to the BotNetwork BotNet, ' . $user['name']);
										$user['buffer'][] = "authed\r";
									}
								} else {
									$this->raw($user['id'], ':BotNetwork NOTICE ' . $user['nick'] . ' :*** INVALID PASSWORD!');
									$this->close($user['id']);
									continue;
								}
							} else {
								$this->raw($user['id'], ":BotNetwork NOTICE $user[name] :*** Error :Please auth first!");
								$this->raw($user['id'], ":BotNetwork NOTICE $user[name] :*** Error :Usage: AUTH <username> <password>");
							}
						}
					}
					unset($this->data[$user['id']]['buffer'][$key]);
				}
			}
		}
	}

	public function getid($name) {
		$keys = array_keys($this->data);
		foreach($keys as &$key) {
			if($this->data[$key]['name'] == $name)
			return $key;
		}
		return NULL;
	}

	public function isin($id, $c) {
		$c = strtolower($c);
		foreach($this->chans as $chan)
		if(array_key_exists('name', $chan) && $chan['name'] == $c)
		foreach($chan['users'] as $user)
		if($user['id'] == $id)
		return true;
		return false;
	}

	public function close($id, $msg = '') {
		if(!array_key_exists($id, $this->data)) {
			return;
		}
		global $botson;
		if($msg == '')
			$msg = socket_strerror(socket_last_error($this->sockets[$id]));
		$user = $this->data[$id];
		if(!array_key_exists('quit', $user) || $user['quit'] == false) { // we havne't processed out quit yet!
			$this->data[$id]['buffer'][] = 'QUIT :' . $msg . "\r";
		} else {
			// route quit to chans, remove from names list on chans
			$users = Array();
			foreach($this->chans as &$chan) {
				$keys = array_keys($chan['users']);
				foreach($keys as $key) {
					if($chan['users'][$key]['id'] == $id) {
						unset($this->chans[$chan['name']]['users'][$key]);
					} else {
						$users[$chan['users'][$key]['id']] = $chan['users'][$key]['id'];
					}
				}
			}
			foreach($users as $to) {
				$this->raw($to, ":$user[name]!$user[name]@hidden QUIT :$msg");
			}
			$chanys = $this->uchans($id);
			foreach($chanys as $c) {
				unset($this->chans[$c['ckey']]['users'][$c['ukey']]);
				if(count($this->chans[$c['ckey']]['users']) == 0) {
					unset($this->chans[$c['ckey']]);
				}
			}
			$arrOpt = array('l_onoff' => 1, 'l_linger' => 0);
			socket_set_block($this->sockets[$id]);
			socket_set_option($this->sockets[$id], SOL_SOCKET, SO_LINGER, $arrOpt);
			socket_close($this->sockets[$id]);
			unset($this->sockets[$id]);
			unset($this->data[$id]);
		}
	}

	public function uchans($id) { // returns list of chans for user on botnet
		//$out[0] = 'name' => channame, 'ckey' => channelkey,
		// ukey => userkey,
		$out = Array();
		$keys = array_keys($this->chans);
		foreach($keys as &$key) {
			$ukeys = array_keys($this->chans[$key]['users']);
			foreach($ukeys as $ukey)
			if($this->chans[$key]['users'][$ukey]['id'] == $id)
			$out[] = Array('name' => $chan['name'], 'ckey' => $key, 'ukey' => $ukey);
		}
		return $out;
	}

	public function pingz() { // goes through and kills users late on ping
		foreach($this->data as $user) {
			$id = $user['id'];
			if(($user['ping'] + 30) < time() && $user['pinged'] != TRUE) {
				$this->data[$id]['pinged'] = TRUE;
				$this->raw($id, "PING :" . time());
			}
			if(($user['ping'] + 60) < time()) {
				$this->close($id);
			}
		}
	}

	public function hasdata() {
		foreach($this->data as &$user) {
			if($this->user_hasdata($user['id'])) return TRUE;
		}
	}

	public function user_hasdata($id) {
		$user = $this->data[$id];
		if(count($user['buffer']) > 1) return TRUE;
		if(count($user['buffer']) < 1) return FALSE;
		$i = 0;
		foreach($user['buffer'] as $l) {
			if($i == 0 && strpos($l, "\r") !== FALSE)
			return TRUE;
			$i++;
			if($i > 1) break;
		}
		return FALSE;
	}

	public function getdata() { // returns a line for parsing
		foreach($this->data as &$user) {
			if($this->user_hasdata($user['id'])) {
				$this->data[$user['id']]['ping'] = time();
				$this->data[$user['id']]['pinged'] = FALSE;
				return Array('user' => $user['name'], 'id' => $user['id'], 'text' => array_shift($this->data[$user['id']]['buffer']));
			}
		}
	}

	public function netdie() {
		socket_shutdown($this->listen, 2);
		socket_close($this->listen);
		unset($this->sockets[array_search($this->listen, $this->sockets)]);
		$keys = array_keys($this->sockets);
		foreach($keys as $key) {
			$this->raw($key, "ERROR server restarting!");
			$this->close($key);
		} // TODO should unset too but for now this is only for hub dieing
	}

	public function route($id, $target, $text) {
		if($target == '') return;
		if($target{0} == '#' || $target{0} == '&') { // Good we are sending to channel users
			if(!array_key_exists(strtolower($target), $this->chans))
				return;
			$chan = $this->chans[strtolower($target)];
			foreach($chan['users'] as $u) {
				if($u['id'] != '-1') {// -1 is the hub
					if($u['id'] != $id)
					$this->raw($u['id'], $text);
				} else {
					// else append to hub's special buffer for received text
					// actually this isn't nessacary anymore
				}
			}
		} else {
			foreach($this->data as $u) {
				if(strtolower($u['name']) == strtolower($target))
				$this->raw($u['id'], $text);
			}
		}
	}

	//routes quits and nick changes etc
	public function uroute($fromid, $text) {
		$targets = Array();
		foreach(uchans($id) as $chan) {
			foreach($chan['users'] as $u) {
				$targets[$u['id']] = $u['id'];
			}
		}
		foreach($targets as $id) {
			$this->raw($id, $text);
		}
	}
	
	public function cleanbuffers() {
		$keys = array_keys($this->data);
		foreach($keys as &$id) {
			$k2 = array_keys($this->data[$id]['buffer']);
			foreach($k2 as &$k)
			if($this->data[$id]['buffer'][$k] == "") unset($this->data[$id]['buffer'][$k]);
		}
	}

	public function names($c) { // Return names for users on botnet for a channel
		$c = strtolower($c);
		// Check for exists
		$names = '';
		$chan = $this->chans[$c];
		foreach($chan['users'] as &$n) {
			$pre = '&';
			if($n['id'] == '-1') {
				$pre = '~';
			} elseif(array_key_exists('isbot', $this->data[$n['id']]) && $this->data[$n['id']]['isbot'] == true) {
				$pre = '%';
			}
			$names .= $pre . $n['name'] . ' '; // (qaohv)~&@%+
			// If non botnet chan get names for it here.
		}
		return $names;
	}
	
	public function join($user, $userid, $chan) { //adds user to channels for routing
		$this->chans[$chan]['users'][] = Array('id' => $userid, 'name' => $user);
		$this->chans[$chan]['name'] = $chan;
	}
	
	public function part($id, $chan) {
		$chan = strtolower($chan);
		$user = $this->data[$id];
		if(!$this->isin($id, $chan)) {
			return false; // user isn't in there
		}
		if(!empty($this->chans[$chan])) {
			$keys = array_keys($this->chans[$chan]['users']);
			foreach($keys as &$key) {
				if($this->chans[$chan]['users'][$key]['id'] == $id) {
					unset($this->chans[$chan]['users'][$key]);
					return true;
				}
			}
		}
	}
	
	public function isbot($id) {
		$user = $this->data[$id];
		if(array_key_exists('isbot', $user) && $user['isbot'] == true)
			return true;
		return false;
	}
	
	public function isweb($id) {
		$user = $this->data[$id];
		if(array_key_exists('isweb', $user) && $user['isweb'] == true)
			return $user['isweb'];
		return false;
	}
}






















