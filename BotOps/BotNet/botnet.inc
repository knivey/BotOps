<?php
/*
 * This file is used by the leaf bots to connect to the hub.
 */

/*
 * hub_bnet.inc
 * - Base class for botnet hub
 * - handles low level communication
 * - keeps track of basic channels and users info for routing
 * hub_bnet_core.inc

 * hub_functions.inc
 * - All callback functions for commands
 * - functions will compatible with both IRC and BN
 * 
 * botnet.inc.inc
 * - bnet_leaf_base
 * `- base class for leaf to connect to hub
 * - bnet_leaf
 * `- extends base class and gives slots 
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

class bnet_leaf_base {
	public $server;
	public $port;
	public $bind; // IP to connect from
	public $nick;
	public $last_out;
	public $last_in;
	public $connected;
	private $fp;
	private $sox;
	private $buffer;
	private $packet;
	private $errno;
	private $errstr;
	public $rcontime;

	public $reportecho = true;

	function _die() {
		socket_shutdown($this->fp, 2);
		socket_close($this->fp);
	}

	function __construct($s) {
		$this->buffer = Array();
		$this->sox = $s;
	}

	public function connect() {
		if($this->reportecho) {
			echo "BNET connecting to " . $this->server . ':' . $this->port . "\n";
		}
		//$this->fp = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$this->fp = $this->sox->create();
		if(!$this->fp) {
			if($this->reportecho) {
				echo "Error creating socket: " . socket_strerror(socket_last_error()) . "\n";
			}
			die();
		}
		//socket_set_option($this->fp, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 250));
		socket_bind($this->fp, $this->bind);
		$this->connected = socket_connect($this->fp, $this->server, $this->port);
		if($this->connected) {
			if($this->reportecho) {
				echo "BNET link established\n";
			}
			$this->rcontime = NULL;
			socket_write($this->fp, "NICK " . $this->nick . "\r\n");
			socket_write($this->fp, "USER Bots localhost localhost :Bots\r\n");
		}
	}

	public function reconnect() {
		global $irc, $traceback;
		if($this->rcontime == NULL)
		$this->rcontime = time();
		if($this->rcontime == NULL || ($this->rcontime + 60) < time()) { // only attempt every 30 sec
			$this->rcontime = time();
			echo "BNET Reconnecting!!!\n";
			if($this->connected) {
				$traceback = 'bnet_write';
				socket_write($this->fp, "quit Reconnecting\r\n");
				$traceback = '';
			} else {
				$irc->msg('#bots-dev', "Botnet link not established, Reconnecting");
			}
			//socket_close($this->fp);
			$this->sox->remove($this->fp);
			$this->fp = FALSE;
			$this->connected = FALSE;
			$this->buffer = Array();
			$this->connect();
		}
	}

	public function getpack() { // Don't use this as packets don't contain entire lines
		global $traceback, $irc, $webpage;
		$this->packet = '';
		$traceback = 'bnet_read';
		if($this->sox->needsread($this->fp)) $this->packet = socket_read($this->fp, 16384);
		$traceback = '';
		//For some reason we get Resource temporarily unavailable
		if($this->packet === FALSE && socket_last_error($this->fp) != 11) {
			if($this->reportecho) {
				echo socket_last_error($this->fp) . ' ' . socket_strerror(socket_last_error($this->fp)) . "\n";
			}
			$this->connected = FALSE;
			if(!$webpage) {
				$irc->msg('#bots-dev', "Unexpectedly lost BNET link");
			}
		}
		return $this->packet;
	}
	
	public function getline() {
		$last = array_pop($this->buffer);
		if(strpos($last, "\r") === FALSE && !array_key_exists(0, $this->buffer)) { // If we are on our last line and its not complete get more data
			$temp = explode("\n", $this->getpack()); // leave \r at the end so we know if line is complete
			array_push($this->buffer, $last . array_shift($temp));
			foreach($temp as &$line)
			array_push($this->buffer, $line);
		} else {
			array_push($this->buffer, $last);
		}

		if(array_key_exists(0, $this->buffer)) {
			$out = $this->buffer[0];
			if(strpos($out, "\r") === FALSE) // our line still isn't complete :(
			return;
			$this->last_in = str_replace("\r", '', array_shift($this->buffer));
			return $this->last_in;
		}
		return;
	}

	public function raw($s) {
		global $traceback;
		$traceback = 'bnet_write';
		socket_write($this->fp, $s . "\r\n");
		$traceback = '';
	}

	public function msg($t, $m) {
		$this->raw("PRIVMSG $t :$m");
	}

	public function notice($t, $m) {
		$this->raw("NOTICE $t :$m");
	}
}

class bnet_leaf extends bnet_leaf_base {
	//Keep track if we ping timeout
	public $pinged = 0;
	public $pingsent = FALSE;
	public $authed = false;
	
	/*
	 * Yay slots again!
	 * cmd	- Command slots (users or hub. privmsg, in channel to all bots or to our bot only)
	 * 		on	- matched to first word on a privmsg
	 * 		arg	- (&$bnet, $user, $chan, $arg, $arg2)
	 */

	public $cmd_slot = Array();
	
	function process() {
		global $irc;
		if($this->pinged == 0) {
			$this->pinged = time();
		}
		
		if($this->connected) {
			$line = $this->getline();
			if($line) {
				if($this->reportecho) {
					echo "BNET IN: $line\n";
				}
				$this->pinged = time();
				$this->pingsent = FALSE;
				$arg = explode(' ', $line);
				switch(strtolower($arg[0])) {
					case 'ping':
						$this->raw("PONG " . trim($arg[1]), ":");
						break;
				}
				$arg[0] = ridfirst($arg[0]);
				$nick = explode('!', $arg[0]);
				if(array_key_exists(1, $nick)) {
					$host = $nick[1];
				} else {
					$host = '';
				}
				$nick = $nick[0];
				$ident = explode('@', $host);
				if(array_key_exists(1, $ident) && $ident[1] != 'hidden') {
					//this isn't from a user from the botnet
					return;
				}
				switch(strtolower($arg[1])) {
					case 'auth':
						$this->raw("botauth " . $this->nick . " kMSjfI33nNkiiMlwSF");
						break;
					case 'quit': //Quit is probably good thing
						if($arg[0] == $this->nick . '!' . $this->nick . '@hidden') {
							//$this->reconnect();
							//global $irc;
							//$irc->msg('#bots-dev', "Unexpectedly got QUIT from BNET Reconnecting");
							$this->connected = false;
						}
						break;
					case '001':
						$this->authed = true;
						//Anything that needs done upon successful connect to botnet do here
						if($irc->connected) {
							$this->netjoin();
						}
						break;
					case 'privmsg':
						$chan = $arg[2];
						array_shift($arg); array_shift($arg); array_shift($arg);
						$com = ridfirst(strtolower(array_shift($arg)));
						if($com == strtolower($this->nick)) {
							$com = strtolower(array_shift($arg)); 
						}
						$arg2 = implode(' ', $arg);
						foreach($this->cmd_slot as $slot) {
							if(strtolower($slot['on']) == $com) {
								$slot['function']($this, $nick, $chan, $arg, $arg2);
							}
						}
						break;
				}
			}
			if((time() - $this->pinged) > 30 && $this->pingsent == false) {
				$this->raw('PING');
				$this->pingsent = true;
			}
			if((time() - $this->pinged) > 120) {
				global $irc;
				$irc->msg('#bots-dev', "Botnet link closed Ping TimeOut, Reconnecting");
				$this->pinged = time();
				$this->reconnect();
			}
		} else {
			$this->pinged = time();
			$this->reconnect(); //reconnect function checks if we just tried and automagically waits between tries
		}
	}
	
	function is_authed() {
		return $this->authed;
	}
	
	//register a slot
	function register($type, $function, $mask) {
		$arr = &$this->$type;
		$arr[$function] = Array('function' => $function, 'on' => $mask);
	}
	
	function isConnected() {
		return $this->connected;
	}
	
	//If we are reconnecting to the BNET or if our connection to the BNET was delayed this brings the hub up to date on our current status
	function netjoin() {
		global $channels, $irc;
		//First wipe out anything we have on the hub
		//In the future we might want to make a command for this instead of faking lostirc
		$this->raw(':lostirc');
		$this->raw(':gotirc');
		$this->raw(':gotauth');
		
		foreach($channels as $c) {
			$cn = $c->name;
			if($c->isin != 1) {
				continue;
			}
			$this->raw(":CREATE $cn");
			$modeline = ":MODE *.gamesurge.net $cn ";
			$modea = '+';
			$modeb = '';
			$namesline = ":NAMES $cn ";
			foreach($c->getnames() as $n => $a) {
				$namesline .= $n . ' ';
				$a = str_split($a);
				foreach($a as $b) {
					switch($b) {
						case '@':
							$modea .= 'o';
							$modeb .= $n . ' ';
							break;
						case '+':
							$modea .= 'o';
							$modeb .= $n . ' ';
							break;
					}
				}
			}
			$this->raw(trim($namesline));
			if($modeb != '') {
				$this->raw($modeline . $modea . ' ' . trim($modeb));
			}
			//until we store info better on leafs we just query it again
			$irc->raw("WHO $cn");
			$irc->raw("MODE $cn");
			$irc->raw("MODE $cn b");
			$irc->raw("TOPIC $cn");
		}
	}
}
