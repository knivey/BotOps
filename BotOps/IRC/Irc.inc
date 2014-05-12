<?php
/***************************************************************************
 * BotNetwork Bots IRC Framework
 * Http://www.botnetwork.org/
 * Contact: irc://irc.gamesurge.net/bots 
 *************************************************************************** 
 * Copyright (C) 2009 BotNetwork
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 ***************************************************************************
 * IRC/Irc.inc
 *  Maintain our connection to an IRC server
 ***************************************************************************/

require_once 'Tools/Tools.php';
require_once 'IRC/IrcFilters.php';
require_once 'KEvent/KEventServer.php';
require_once 'IRC/IrcEvent.php';
require_once 'IRC/Nicks.php';

/**
 * Interface to IRC Server
 * @author knivey
 *
 */
class Irc {
	/**
	 * IRC server to connect to
	 * @var string $server
	 */
    public $server;
    
    /**
     * Port of the IRC server
     * @var int $port
     */
    public $port;
    
    /**
     * Password for the IRC server, If no pass leave blank
     * @var string $pass
     */
    public $pass;
    
    /**
     * How long until the connecting to the server times out 
     * @var int $timeout
     */
    public $timeout;
    
    /**
     * Our IP to use for connecting from
     * @var string $bind
     */
    public $bind;
    
    /**
     * Name of the bot and IRC nickname to try to get
     * @var string $nick
     */
    public $nick;
    
    /**
     * Actual bot nickname on IRC (our nick might have been taken)
     * @var string $curNick
     */
    private $curNick;
    
    /**
     * The line to be sent for USER during connection
     * @var string $user
     */
    public $user;
    
    /**
     * What length to start word wrapping at
     * @var int $wraplen
     */
    public $wraplen;
    
    /**
     * Contains the last line sent to the IRC server
     * @var string $last_out
     */
    public $last_out;
    
    /**
     * Contains the last line we've recieved from IRC
     * @var string $last_in
     */
    public $last_in;
    
    /**
     * contains info on last line (nick host chan w/e)
     * @var array $last_info
     */
    public $last_info = Array();
    
    /**
     * Ping timeout
     * @var int $RTO
     */
    public $RTO;
    
    /**
     * Line to send for auth after connecting
     * @var string $authserv
     */
    public $authserv;
    
    /**
     * Usermodes to set after connecting
     * @var string $usermodes
     */
    public $usermodes;
    
    /**
     * 
     * @var unknown
     */
    public $bncuser;
    
    /**
     * Name of network received by 005 
     * @var unknown
     */
    public $network;

    /**
     * @var IrcFilters $ircFilters
     */
    public $ircFilters;
    
    /**
     * @var KEventServer $eventServer
     */
    public $eventServer;
    
    public $sent; // keep track of the rate we are sending for gs should not exceed 512b/10 sec
    
    /**
     * @var Sockets $pSockets
     */
    private $pSockets; // pointner to socket manager
    
    /**
     * Our Socket
     * @var resource $sock
     */
    public $sock;
    
    /**
     * State of the IRC connection
     * Possible values:
     * 'disconnected'
	 * 'connecting'
	 * 'registering' (sending NICK USER)
	 * 'connected'   (got 001)
	 * 'authed'      (authed with services)
	 * 
     * @var string $state
     */
    public $state;

    /**
     * @var Nicks $Nicks
     */
    public $Nicks;

    /**
     * Keep track of users on netsplit
     * Not Currently Implemented
     * 
     * $netspliters['splitIdx'] = Array(
     * 'ppl' => Array('nick' => &$ppl['nick'])
     * 'backTime' => time a user was seen returning
     * 'splitTime' => time users left
     * 'count' => number of users that left
     *
     * The idea is when we see one person return from a netsplit we know its either over
     * or that user reconnected to another server. By using count to determin the percent
     * of users that returned will help determine this.
     * Also there should be a timeout on netsplits where we just forget the users.
     *
     * All times should have like a 5 second leeway so they mostly fall into the same split.
     * @var array $netsplitters
     */
    public $netspliters;

    /**
     * Track a ping to keep sbnc connected and maybe later to tell us lagtime
     * @var int $pingtime
     */
    public $pingtime;
    
    /**
     * Timer for checking if our nick is taken
     * @var int $nickCheck
     */
    public $nickCheck;
    
    /**
     * Set when we have finished disconnecting and are ready to shutdown
     * @var bool $canDie
     */
    public $canDie = false;
    
    /**
     * Set when the bot should be allowed to shutdown after disconnect
     * @var bool $tryDie
     */
    public $tryDie = false;
    
    /*****************************************************************
     * User/Chan send quota system
     *****************************************************************
     * We will make new hooks for when a limit is broken
     * then a module can decide what to do about it
     * need to be provided with limit rules and keep them updated
     * so i think setreg will need an option to call a function
     * when settings are updated... (for perchan)
     * and the rules will need to be loaded up on bot start
     * 
     * the CmdReg will set the quotasrc by default and the quotasrc
     * will be cleared on the next read.
     * things that aren't done in the same execution loop (callbacks)
     * NEED TO SET SRC MANUALLY if you want the limits applied
     * 
     * We might be able to keep statistics in the module that handles
     * the limits being broken?
     *****************************************************************
     */
    //public $quotaSrc = Array();
    /*
     * Store the user/chan that should be charged for sending data to irc.
     * If empty no charges made
     * Array(
     * 'nick'
     * 'chan'
     * 'staff' true|false
     * 'priority' true|false just makes the messages go at top of sendq
     * )
     */
    /*
    function setQuotaSrc() {
        
    }
    
    function clearQuotaSrc() {
        $this->quotaSrc = Array();
    }
    */
    
    /**
     * Wrapper to get the class object for the module name
     * @param string $mod
     * @return Module
     * @codeCoverageIgnore
     */
    public function gM($mod) {
        return $this->pMM->modules[$mod]['class'];
    }

    /**
     * Create Irc Class
     * @param Sockets $sockets
     * @param string $nick 		Bot nickname
     * @param string $bind 		Our IP to connect from
     * @param string $server 	Server to connect to
     * @param number $ipv 		4 for IPv4, 6 for IPv6
     * @param number $port		Server Port
     * @param string $pass		Server Password or NULL
     * @param number $timeout		Connection Timeout
     * @param number $pingtimeout	Ping Timeout
     * @return Irc
     */
    function __construct(&$sockets, $nick, $bind, $server, $ipv, $port = 6667, $pass = '', $timeout = 30, $pingtimeout = 170) {
        $this->user = "bots localhost localhost :IRC Bot Services #Bots";
        $this->server = $server;
        if($ipv != 4 && $ipv != 6) {
            $ipv = 4;
        }
        $this->ipv = $ipv;
        $this->port = $port;
        $this->pass = $pass;
        $this->timeout = $timeout;
        $this->bind = $bind;
        $this->nick = $nick;
        $this->pSockets = $sockets;
        $this->RTO = $pingtimeout;
        $this->ircFilters = new IrcFilters();

        $this->nickCheck = time() + 10;

        $this->Nicks = new Nicks();
        
        $this->eventServer = new KEventServer();
        /*
        $this->hooks = Array(
            'connected' => Array(),
            'disconnect' => Array(),
            'join' => Array(),
            'part' => Array(),
            '+mode' => Array(),
            '-mode' => Array(),
            '+ban' => Array(),
            '-ban' => Array(),
            '+op' => Array(),
            '-op' => Array(),
            '+voice' => Array(),
            '-voice' => Array(),
            'kick' => Array(),
            'topic' => Array(),
            'quit' => Array(),
            'msg' => Array(),
            'authed' => Array(),
            'nick' => Array(),
            'notice' => Array(),
            'out' => Array(),
            'raw' => Array(), //raw data read in
            'chanevent' => Array(), //any event you would see in an irc client channel
            'killbot' => Array(), //called when bot asked to die
        );
        //chanevent function will recv args ('#chan', 'rawline')
        */
    }

    /**
     * Initiate connection to IRC server
     */
    function connect() {
        $this->state = 'connecting';
        if($this->ipv == 4) {
            $ipv = 4;
            $this->sock = $this->pSockets->createTCP($this, 'handleRead', 'handleError', 1, 'handleConnected', 1, $this->bind);
        } else {
            $ipv = 6;
            $this->sock = $this->pSockets->createTCPv6($this, 'handleRead', 'handleError', 1, 'handleConnected', 1, $this->bind);
        }
        if(!$this->sock) {
            die("IRC Couldn't create socket");
        }
        //TODO Make this config variable
        $this->pSockets->setReconTime($this->sock, 65);
        socket_getsockname($this->sock, $a, $p);
        echo "IRC Local IP/PORT: $a/$p\n";
        echo "IRC Connecting to Host: $this->server Port: $this->port IPv$ipv\n";

        if(!$this->pSockets->connect($this->sock, $this->server, $this->port, $this->timeout)) {
            $err = socket_last_error($this->sock);
            echo "IRC Error connecting [$err] (ignoring)\n";
        }
        $this->pSockets->setRTO($this->RTO, $this->sock);
    }
    
    /**
     * Begin bot shutdown process
     * @param string $quitmsg
     */
    function killBot($quitmsg) {
        $this->eventServer->sendEvent(new IrcEvent('killbot', Array($quitmsg)));
        $this->raw('QUIT :' . $quitmsg);
        $this->tryDie = true;
        $this->pSockets->setPersist($this->sock, false);
    }

    /**
     * Handle socket errors
     * @param resource $sock
     * @param int $error
     */
    function handleError($sock, $error) {
        echo "IRC SOCKET ERROR [$error] " . socket_strerror($error) . "\r\n";
        //if($this->pSockets->getRecon($this->sock)) {
        //	echo "Socket is reconecting, not calling disconnect hooks\n";
        //	return;
        //}
        $this->Nicks->clearAll();
        $this->state = 'disconnected';
        $this->eventServer->sendEvent(new IrcEvent('disconnect', Array()));
        if($this->tryDie) {
            $this->canDie = true;
        }
    }

	/**
	 * Handle socket connect
	 * @param resource $sock
	 */
    function handleConnected($sock) {
        $this->Nicks->clearAll();
        echo "IRC Connection established\n";
        $this->state = 'registering';
        $this->sock = $sock;
        if($this->pass != null) {
            $this->pSockets->send($this->sock, "PASS $this->pass\r\n");
        }
        $this->pSockets->send($this->sock, "NICK $this->nick\r\n");
        $this->curNick = $this->nick;
        $this->pSockets->send($this->sock, "USER $this->user\r\n");
    }

    /**
     * Handle read from socket
     * @param resource $sock
     * @param string $line
     */
    function handleRead($sock, $line) {
        $line = trim($line);
        $this->parseLine($sock, $line);
    }

    /**
     * Get the bots current nickname on irc
     * @return string
     */
    public function curNick() {
        return $this->curNick;
    }

    /**
     * Get the bots current nickname on irc
     * @return string
     * @todo remove this and use above function
     */
    public function currentNick() {
        return $this->curNick;
    }
    
    /**
     * Called when the Bots nick needs changed
     * @param string $newnick
     */
    public function chgNick($newnick) {
        $this->raw("NICK $newnick");
        $this->nick = $newnick;
    }
    
	/**
	 * Worp wrap a line by inserting \n, if the result is over the
	 * limited number of lines replace all with error
	 * @param string $target Target of message
	 * @param string $string String to process
	 * @param bool $no_f If true no errors for exceeding limit
	 * @return string
	 */
    private function wrappy($target, $string, $no_f) {
        $wraplen = &$this->wraplen;
        $length = strlen($string);
        if($wraplen < $length) {
            $string = wordwrap($string, $wraplen, "\n", 1);
            $out = explode("\n", $string);
        } else {
            $out = explode("\n", $string);
        }
        if($no_f && count($out) > 3) {
            //$bnet->msg("&bots", "\2Warning!!!\2 Flood exploit attempted, Target was $target");
            return Array(0 => "\2Notice:\2 Output exceeds 3 lines, message aborted.\n");
        }
        return $out;
    }

    /**
     * Send a notice
     * @param string $t The target
     * @param string $m The message
     * @param string $no_f If true no error for exceeding number of line limit
     * @param string $no_p If true no parseutil parsing
     */
    public function notice($t, $m, $no_f=true, $no_p = false) {
        if(!$no_p) {
            $this->gM('ParseUtil')->parse($m, 'P_notice', $this, Array('t' => $t, 'no_f' => $no_f));
        } else {
            $this->P_notice($m, Array('t' => $t, 'no_f' => $no_f));
        }
    }

    /**
     * Send a notice without using parseutil
     * @param string $t The target
     * @param string $m The message
     * @param string $no_f If true no error for exceeding number of line limit
     */
    public function rnotice($t, $m, $no_f=true) {
        $this->P_notice($m, Array('t' => $t, 'no_f' => $no_f));
    }

    /**
     * ParseUtil callback for notice
     * @param string $m The message
     * @param array $ex Extra Info array('t' for target and 'no_f' for no flood)
     */
    public function P_notice($m, $ex) {
        $t = $ex['t'];
        $no_f = $ex['no_f'];
        $nchans = $this->Nicks->nickChans($t);
        $mm = $this->wrappy($t, $m, $no_f);
        if(count($nchans) > 0) {
            $keys = array_keys($nchans);
            $c = '';
            foreach($keys as $k) {
                if($this->Nicks->isOp($this->curNick(), $k) || $this->Nicks->isVoice($this->curNick(), $k)) {
                    $c = $k;
                }
            }
            if($c != '') {
                foreach($mm as $m) {
                    $this->raw("CNOTICE $t $c :\2\2$m");
                }
                return;
            }
        }
        foreach($mm as $m) {
            $this->raw("NOTICE $t :\2\2$m");
        }
    }

    /**
     * Send a notice using parseutil and CNOTICE
     * @param string $t The target
     * @param string $c Channel for CNOTICE
     * @param string $m The message
     * @param string $no_f If true no error for exceeding number of line limit
     */
    public function cnotice($t, $c, $m, $no_f=true) {
        $this->gM('ParseUtil')->parse($m, 'Pcnotice', $this, Array('t' => $t, 'c' => $c, 'no_f' => $no_f));
    }

    /**
     * ParseUtil callback for cnotice
     * @param string $m The message
     * @param array $ex Extra Info array('t' for target and 'no_f' for no flood)
     */    
    public function Pcnotice($m, $ex) {
        $t = $ex['t'];
        $c = $ex['c'];
        $no_f = $ex['no_f'];
        $mm = $this->wrappy($t, $m, $no_f);
        foreach($mm as $m) {
            $this->raw("CNOTICE $t $c :\2\2$m");
        }
    }

    /**
     * Send a /me type action
     * @param string $t The target
     * @param string $m The message
     * @param string $no_f If true no error for exceeding number of line limit
     */
    public function act($t, $m, $no_f=true) {
        $this->gM('ParseUtil')->parse($m, 'Pact', $this, Array('t' => $t, 'no_f' => $no_f));
    }

    /**
     * ParseUtil callback for act
     * @param string $m The message
     * @param array $ex Extra Info array('t' for target and 'no_f' for no flood)
     */
    public function Pact($m, $ex) {
        $t = $ex['t'];
        $no_f = $ex['no_f'];
        $mm = $this->wrappy($t, $m, $no_f);
        foreach($mm as $m) {
            $this->raw("PRIVMSG $t :\1ACTION $m\1");
        }
    }

    /**
     * Send a privmsg
     * @param string $t The target
     * @param string $m The message
     * @param string $no_f If true no error for exceeding number of line limit
     * @param string $no_p If true no parseutil parsing
     */
    public function msg($t, $m, $no_f=true, $no_p = false) {
        if(!$no_p) {
            $this->gM('ParseUtil')->parse($m, 'Pmsg', $this, Array('t' => $t, 'no_f' => $no_f));
        } else {
            $this->Pmsg($m, Array('t' => $t, 'no_f' => $no_f));
        }
    }

    /**
     * ParseUtil callback for msg
     * @param string $m The message
     * @param array $ex Extra Info array('t' for target and 'no_f' for no flood)
     */
    public function Pmsg($m, $ex) {
        $t = $ex['t'];
        $no_f = $ex['no_f'];
        $mm = $this->wrappy($t, $m, $no_f);
        foreach($mm as $m) {
            $this->raw("PRIVMSG $t :\2\2$m");
        }
    }

    /**
     * Array of lines waiting to be sent to irc
     * @var array $sendq
     */
    public $sendq = Array();

    public $maxSendBytes = 512;
    public $maxSendSec = 10;
    public function setThrottle($bytes, $seconds) {
        $this->maxSendBytes = $bytes;
        $this->maxSendSec = $seconds;
    }
    
    /*
     * ircu allows us to send 5 lines burst
     * after that it will throttle by 2 seconds until
     * 10 seconds have passed?
     */
    
    /**
     * Used as a calculation on when we can send again
     * @var float $msg_since
     */
    public $msg_since;
    
    /**
     * Time we send the last message out
     * @var float $msg_lasttime
     */
    public $msg_lasttime;
    
    /**
     * Process sending our out queue respecting flood rules
     */
    public function processSendq() {
        if(empty($this->sendq)) {
            return;
        }
        
        //flood prevention disabled
        if($this->maxSendSec == 0) {
            $keys = array_keys($this->sendq);
            foreach($keys as &$key) {
                $this->pSockets->send($this->sock, $this->sendq[$key]);
                unset($this->sendq[$key]);
            }
            return;
        }
        
        $this->msg_lasttime = microtime_float();
        if($this->msg_lasttime > $this->msg_since) {
            $this->msg_since = $this->msg_lasttime;
        }
        $keys = array_keys($this->sendq);
        foreach($keys as &$key) {
            $line = $this->sendq[$key];
            if($this->msg_since - microtime_float() >= 10.1) {
                break;
            }
            $this->pSockets->send($this->sock, $this->sendq[$key]);
            unset($this->sendq[$key]);
            $this->msg_since += 2 + (strlen($line) / 120);
        }
    }
    
    /**
     * To be called every cycle, process all our logic
     */
    public function logic() {
        if($this->state == 'authed') {
            if($this->pingtime < time() - 30) {
                $this->pingtime = time();
                $this->raw('PING LAG' . time());
            }
            if($this->nickCheck < time() - 30 && $this->curNick != $this->nick) {
                $this->raw('NICK ' . $this->nick);
                $this->nickCheck = time();
            }
        }
        
        if($this->state == 'disconnected') {
            $this->sendq = Array();
            return;
        }
        $this->processSendq();
    }
    
    /**
     * Queue a raw message to be sent to the server.
     * @param string $s line to send
     */
    public function raw($s) {
        $this->eventServer->sendEvent(new IrcEvent('out', Array($s)));
        if(!$this->ircFilters->passFilter($s)) {
        //now some packet mangling
            $ss = explode(' ', $s);
            $s = '';
            if(strtolower($ss[0]) == 'privmsg' || strtolower($ss[0]) == 'notice' || strtolower($ss[0]) == 'cprivmsg' || strtolower($ss[0]) == 'cnotice') {
                $s = $ss[0] . ' ' . $ss[1] . ' ' . ':Message breaks AUP, unable to display.';
            }
        }
        if($s != '') {
            $this->sendq[] = "$s\r\n";
        }
    }

    /**
     * Send a line directly to the socket.
     * Probably should not be used outside Irc.
     * @param string $line
     */
    function dump($line) {
        $this->pSockets->send($this->sock, "$line\r\n");
    }

    /**
     * Get last line received from Irc
     * @return string
     */
    function getLastLine() {
        return $this->last_in;
    }
    
    /**
     * Process JOIN
     * @param string $nick
     * @param string $host
     * @param array $arg
     */
    function pJoin($nick, $host, $arg) {
        //update $ppl
        //>> :knivey!knivey@im.srsly.like.verymad.net PART :#emo
        //>> :knivey!knivey@im.srsly.like.verymad.net JOIN :#emo
        //>> :knivey!knivey@im.srsly.like.verymad.net PART :#zen
        //>> :knivey!knivey@im.srsly.like.verymad.net JOIN #zen
        if ($arg[2]{0} == ':') {
            $arg[2] = substr($arg[2], 1);
        }
        $this->gM('ParseUtil')->set('chan', $arg[2]);
        $this->Nicks->join($nick, $host, $arg[2]);
        $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($arg[2], $this->last_in)));
        $this->eventServer->sendEvent(new IrcEvent('join', Array($nick, $arg[2])));
    }
    
    /**
     * Process NICK
     * @param string $nick
     * @param array $arg
     */
    function pNick($nick, $arg) {
        //:botopss!~bots@kyte.is-shaking-his-stick-at-you.gamesurge NICK :BotOps
        //Might need to check if exists
        //But don't see a need unless we lag, then other issues would arise too...
        //So in the mean time we just hope :)
        if ($arg[2]{0} == ':') {
            $arg[2] = substr($arg[2], 1);
        }

        $this->Nicks->nick($nick, $arg[2]);

        /*
         * when chgNick() changes $this->nick update curNick
         * if the /nick fails our curNick will stay accurate
         */
        if ($this->curNick == $nick) {
            $this->curNick = $arg[2];
        }
        foreach ($this->Nicks->nickChans($arg[2]) as $c => $val) {
            $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($c, $this->last_in)));
        }
        $this->eventServer->sendEvent(new IrcEvent('nick', Array($nick, $arg[2])));
    }

    /**
     * Process PART
     * @param string $nick
     * @param array $arg
     */
    function pPart($nick, $arg) {
        //first cleanup arg[2] take this example:
        //:BotOps!~bots@BotNetwork.users.gamesurge PART #bots :hi
        //:BotOps!~bots@BotNetwork.users.gamesurge PART :#bots
        if ($arg[2]{0} == '#') {
            $arg[2] = $arg[2];
            $text = arg_range($arg, 3, -1);
            $text = substr($text, 1);
        } else {
            $arg[2] = substr($arg[2], 1);
            $text = '';
        }
        $this->gM('ParseUtil')->set('chan', $arg[2]);
        $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($arg[2], $this->last_in)));
        $this->eventServer->sendEvent(new IrcEvent('part', Array($nick, $arg[2], $text)));

        $this->Nicks->part($nick, $arg[2]);
        //see if its us leaving
        if ($nick == $this->currentNick()) {
            $this->Nicks->usPart($arg[2]);
        }
    }
    
    /**
     * Process KICK
     * @param string $nick
     * @param array $arg
     */
    function pKick($nick, $arg) {
        //:ChanServ!ChanServ@Services.GameSurge.net KICK #bots-dev Roffle :(kNiVeS) test
        $this->gM('ParseUtil')->set('chan', $arg[2]);
        $text = substr(arg_range($arg, 4, -1), 1);
        $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($arg[2], $this->last_in)));
        $this->eventServer->sendEvent(new IrcEvent('kick', Array($nick, $arg[2], $arg[3], $text)));

        $this->Nicks->kick($arg[3], $arg[2]);
        if ($arg[3] == $this->currentNick()) {
            $this->Nicks->usPart($arg[2]);
        }
    }
    
    /**
     * Process PRIVMSG
     * @param string $nick
     * @param array $arg
     * @param string $text
     */
    function pPrivmsg($nick, $arg, $text) {
        $this->gM('ParseUtil')->set('chan', $arg[2]);
        //if (array_key_exists($nick, $this->ppl)) {
        //    $this->ppl[$nick]['lastMsgTime'] = time();
        //    $this->ppl[$nick]['lastMsg'] = Array('target', $arg[2], $text);
        //}
        if ($arg[2]{0} == '#') {
            $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($arg[2], $this->last_in)));
        }
        $this->eventServer->sendEvent(new IrcEvent('msg', Array($nick, $arg[2], $text)));
    }
    
    /**
     * Process NOTICE
     * @param string $nick
     * @param array $arg
     * @param string $text
     */
    function pNotice($nick, $arg, $text) {
        $this->gM('ParseUtil')->set('chan', $arg[2]);
        //if (array_key_exists($nick, $this->ppl)) {
        //    $this->ppl[$nick]['lastMsgTime'] = time();
        //    $this->ppl[$nick]['lastMsg'] = Array('target', $arg[2], $text);
        //}
        //:AuthServ!AuthServ@Services.GameSurge.net NOTICE BotOps :I recognize you.
        //:AuthServ!AuthServ@Services.GameSurge.net NOTICE knivey :You are already authed to account kyte; you must reconnect to auth to a different account.
        if ($this->state != 'authed' && $nick == 'AuthServ' && ($text == 'I recognize you.' || arg_range($arg, 3, 6) == ':You are already authed')) {
            $this->state = 'authed';
            $this->eventServer->sendEvent(new IrcEvent('authed', Array()));
        }
        if ($arg[2]{0} == '#') {
            $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($arg[2], $this->last_in)));
        }
        $this->eventServer->sendEvent(new IrcEvent('notice', Array($nick, $arg[2], $text)));
    }
    
    /**
     * Process TOPIC
     * @param string $nick
     * @param array $arg
     * @param string $text
     */
    function pTopic($nick, $arg, $text) {
        $this->gM('ParseUtil')->set('chan', $arg[2]);
        $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($arg[2], $this->last_in)));
        $this->eventServer->sendEvent(new IrcEvent('topic', Array($nick, $arg[2], $text)));
    }
    
    /**
     * Process QUIT
     * @param string $nick
     * @param array $arg
     */
    function pQuit($nick, $arg) {
        $text = arg_range($arg, 2, -1);
        if ($text{0} == ':') {
            $text = substr($text, 1);
        }
        foreach (array_keys($this->Nicks->nickChans($nick)) as $c) {
            $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($c, $this->last_in)));
        }
        $this->eventServer->sendEvent(new IrcEvent('quit', Array($nick, $text)));
        //remove after hooks probably best
        $this->Nicks->quit($nick);
    }
    
    /**
     * Process MODE
     * @param string $nick
     * @param array $arg
     */
    function pMode($nick, $arg) {
        $this->gM('ParseUtil')->set('chan', $arg[2]);
        if ($arg[2]{0} == '#') {
            $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($arg[2], $this->last_in)));
        }
        //:ChanServ!ChanServ@Services.GameSurge.net MODE #bots-dev -oo knives Roffle
        if ($arg[2]{0} != '#') {
            return;
        }
        if (isset($arg[4])) {
            $mode_arg = explode(' ', arg_range($arg, 4, -1));
        } else {
            $mode_arg = Array();
        }
        $add = true; //adding or removing the mode
        while ($arg[3] != '') {
            switch ($arg[3]{0}) {
                case '+':
                    $add = true;
                    break;
                case '-':
                    $add = false;
                    break;
                case 'b': //[un]ban
                    $mask = array_shift($mode_arg);
                    if ($add) {
                        $this->eventServer->sendEvent(new IrcEvent('+ban', Array($nick, $arg[2], $mask)));
                    } else {
                        $this->eventServer->sendEvent(new IrcEvent('-ban', Array($nick, $arg[2], $mask)));
                    }
                    break;
                case 'o':
                    //[de]oping a user
                    $user = array_shift($mode_arg);
                    if ($add) {
                        $this->Nicks->Op($user, $arg[2]);
                        $this->eventServer->sendEvent(new IrcEvent('+op', Array($nick, $arg[2], $user)));
                    } else {
                        $this->Nicks->DeOp($user, $arg[2]);
                        $this->eventServer->sendEvent(new IrcEvent('-op', Array($nick, $arg[2], $user)));
                    }
                    break;
                case 'v':
                    //[de]voicing a user
                    $user = array_shift($mode_arg);
                    if ($add) {
                        $this->Nicks->Voice($user, $arg[2]);
                        $this->eventServer->sendEvent(new IrcEvent('+voice', Array($nick, $arg[2], $user)));
                    } else {
                        $this->Nicks->DeVoice($user, $arg[2]);
                        $this->eventServer->sendEvent(new IrcEvent('-voice', Array($nick, $arg[2], $user)));
                    }
                    break;
                case 'k':
                case 'l':
                    /*
                     * modes k,l have an arg when setting
                     * only k has the arg when unsetting
                     */
                    $marg = '';
                    if ($add) {
                        $marg = array_shift($mode_arg);
                        $this->eventServer->sendEvent(new IrcEvent('+mode', Array($nick, $arg[2], $arg[3]{0}, $marg)));
                    } else {
                        if ($arg[3]{0} == 'k') {
                            $marg = array_shift($mode_arg);
                        }
                        $this->eventServer->sendEvent(new IrcEvent('-mode', Array($nick, $arg[2], $arg[3]{0}, $marg)));
                    }
                    break;
                default:
                    if ($add) {
                        $this->eventServer->sendEvent(new IrcEvent('+mode', Array($nick, $arg[2], $arg[3]{0}, '')));
                    } else {
                        $this->eventServer->sendEvent(new IrcEvent('-mode', Array($nick, $arg[2], $arg[3]{0}, '')));
                    }
                    break;
            }
            $arg[3] = substr($arg[3], 1);
        }
    }
    
    /**
     * Parse an incoming line from Irc
     * @param resource $sock
     * @param string $line
     */
    function parseLine($sock, $line) {
        $this->gM('ParseUtil')->clear();
        $this->last_in = $line;
        $this->eventServer->sendEvent(new IrcEvent('raw', Array($line)));
        $dt = strftime('%m/%d/%y %T');
        echo "DATA IN [$dt]: $line\r\n";
        if($line{0} == ':') {
            $line = substr($line, 1);
        }
        //split line by spaces
        $arg = explode(' ', $line);
        //get line after :
        $text = arg_range($arg, 3, -1);
        if(strlen($text) > 0 && $text{0} == ':') {
            $text = substr($text, 1);
        }
        //$text = substr($line, strpos($line, ':')); //this will break if channel name has :
        switch($arg[0]) {
            case 'PING':
                $this->pSockets->send($sock, "PONG $arg[1]\r\n");
                return;
            default:
                $nick = explode('!', $arg[0]);
                if(array_key_exists(1, $nick)) { //sometimes the irc server itself sets things
                    $host = $nick[1];
                } else {
                    $host = $nick[0];
                }
                $nick = $nick[0];
        }
        $this->gM('ParseUtil')->set('nick', $nick);
        $this->gM('ParseUtil')->set('access', $host);
        $this->gM('ParseUtil')->set('hand', $host);
        $this->gM('ParseUtil')->set('host', $host);
        
        $this->Nicks->tppl($nick, $host);
        
        switch($arg[1]) {
            case 'JOIN':
                $this->pJoin($nick, $host, $arg);
                break;
            case 'NICK':
                $this->pNick($nick, $arg);
                break;
            case 'PART':
                $this->pPart($nick, $arg);
                break;
            case 'KICK':
                $this->pKick($nick, $arg);
                break;
            case 'PRIVMSG':
                $this->pPrivmsg($nick, $arg, $text);
                break;
            case 'NOTICE':
                $this->pNotice($nick, $arg, $text);
                break;
            case 'TOPIC':
                $this->pTopic($nick, $arg, $text);
                break;
            case 'QUIT':
                $this->pQuit($nick, $arg);
                break;
            case 'MODE':  //TODO handle hook masks on all hooktype that use
                $this->pMode($nick, $arg);
                break;
            default:
                //check it its numeric
                if (!is_numeric($arg[1])) {
                    break;
                }
                $this->parseNumeric($arg, $line);
        }
    }

    function p001() {
        $this->state = 'connected';
        $this->eventServer->sendEvent(new IrcEvent('connected', Array()));
        $this->raw($this->authserv);
        $this->raw('MODE ' . $this->currentNick() . ' ' . $this->usermodes);
    }

    function p005($line) {
        $loc = strpos($line, 'NETWORK=');
        if ($loc !== FALSE) {
            $end = strpos($line, ' ', $loc);
            $start = $loc + strlen('NETWORK=');
            $this->network = substr($line, $start, $end - $start);
        }
    }

    function p443($arg) {
        //:Prothid.CA.US.GameSurge.net 433 * BotServ :Nickname is already in use.
        if ($arg[3] == $this->curNick) {
            $this->curNick .= '_';
            $this->raw("NICK $this->curNick");
        }
    }

    function p366($arg) {
        //:Bots.GameSurge.net 366 Roffle #botstaff :End of /NAMES list.
        $this->raw("MODE $arg[3]");
        $this->raw("WHO $arg[3] %tnchfu,777");
        $this->raw("MODE $arg[3] b");
        //$this->raw("TOPIC $arg[3]");
    }

    function p354($arg) {
        $this->Nicks->who($arg);
    }
    
    function p353($arg) {
        $this->Nicks->names($arg);
    }
    
    function parseNumeric($arg, $line) {
        switch ($arg[1]) {
            case '001':
                $this->p001();
                break;
            case '005':
                $this->p005($line);
                break;
            case '433':
                $this->p443($arg);
                break;
            case '366':
                $this->p366($arg);
                break;
            case '354': //custom who reply
                $this->p354($arg);
                break;
            case '353': //names reply
                $this->p353($arg);
                break;
        }
        $this->eventServer->sendEvent(new IrcEvent($arg[1], Array($line)));
    }
    
    /*
     * These are old functions to keep backwards compat
     */
    function isop($chan, $nick) {
        return $this->Nicks->isOp($nick, $chan);
    }
    
    function isvoice($chan, $nick) {
        return $this->Nicks->isVoice($nick, $chan);
    }

    function n2h($nick) {
        return $this->Nicks->n2h($nick);
    }
    
    function h2n($host) {
        return $this->Nicks->h2n($host);
    }
    
    function nickChans($nick) {
        return $this->Nicks->nickChans($nick);
    }
}
