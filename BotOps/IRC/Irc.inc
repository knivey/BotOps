<?php

require_once 'Tools/Tools.php';
require_once __DIR__ . '/IrcFilters.php';
require_once __DIR__ . '/../KEvent/KEventServer.php';
require_once __DIR__ . '/IrcEvent.php';
require_once __DIR__ . '/Nicks.php';
require_once __DIR__ . '/State.php';

require_once __DIR__ . '/../../vendor/autoload.php';
use Amp\ByteStream;
use Amp\Loop;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\EncryptableSocket;
use League\Uri;
use function Amp\Socket\connect;

/**
 * Interface to IRC Server
 */
class Irc implements \Psr\Log\LoggerAwareInterface {
    protected \Psr\Log\LoggerInterface $log;

	/**
	 * IRC server to connect to
	 */
    public string $server;
    
    /**
     * Port of the IRC server
     */
    public int $port = 6667;
    
    /**
     * Password for the IRC server, If no pass leave blank
     */
    public ?string $pass;
    
    /**
     * How long until the connecting to the server times out
     */
    public int $connectTimeout = 30;
    
    /**
     * Name of the bot and IRC nickname to try to get
     */
    public string $nick;
    
    /**
     * Actual bot nickname on IRC (our nick might have been taken)
     */
    private string $curNick;
    
    /**
     * The line to be sent for USER during connection
     */
    public string $user = "bots localhost localhost :IRC Bot Services #bots";
    
    /**
     * What length to start word wrapping at
     */
    public int $wraplen = 400;
    
    /**
     * Contains the last line we've recieved from IRC
     */
    public ?string $last_in;
    
    /**
     * How long to go without any received data after pinging the server before timing out
     */
    public int $pingTimeout = 30;

    /**
     * Interval to ping server
     */
    public int $pingInterval = 30;
    
    /**
     * Line to send for auth after connecting
     */
    public ?string $authserv;
    
    /**
     * Usermodes to set after connecting
     */
    public ?string $usermodes;
    
    /**
     * Name of network received by 005 
     */
    public ?string $network;

    /**
     * How long to wait between reconnect attempts
     */
    public int $reconnectInterval = 60;

    /**
     * Used to filter outgoing text so we don't get G-Lined for rule breaking text
     */
    public IrcFilters $ircFilters;

    public KEventServer $eventServer;

    protected ?ConnectContext $connectContext;
    protected ?EncryptableSocket $socket;
    protected \Amp\ByteStream\LineReader $reader;
    /**
     * State of the IRC connection
     * Use consts from \IRC\State
     */
    public int $state;

    public Nicks $Nicks;

    /**
     * Last time a ping was sent
     */
    protected $pingTime;

    /**
     * Interval to keep trying to regain our nickname
     */
    public int $nickCheckInterval = 15;

    /**
     * Set when we have finished disconnecting and are ready to shutdown
     */
    public bool $canDie = false;
    
    /**
     * Set when the bot should be allowed to shutdown after disconnect
     */
    public bool $tryDie = false;

    /**
     * Array of lines waiting to be sent to irc
     */
    public array $sendQ = Array();

    /**
     * Used as a calculation on when we can send again
     * @var float $msg_since
     */
    public ?float $msg_since;

    /**
     * How to do throttling
     * 0 - none
     * 1 - ircu (GameSurge)
     */
    public int $throttleMethod = 1;

    protected ?string $sendWatcher;
    protected ?string $reconnectWatcher;
    protected ?string $nickCheckWatcher;


    //TODO remove all this, I don't want the irc lib accessing a module
    public ModuleManager $pMM;
    /**
     * Wrapper to get the class object for the module name
     * @codeCoverageIgnore
     */
    public function gM(string $mod) : Module {
        return $this->pMM->modules[$mod]['class'];
    }

    /**
     * Create Irc Class
     * @param string $nick 		Bot nickname
     * @param string $server 	Server to connect to
     * @param string $pass		Server Password or NULL
     */
    function __construct(string $nick, $server, $pass = null) {
        $this->server      = $server;
        $this->pass        = $pass;
        $this->nick        = $nick;

        $this->ircFilters  = new IrcFilters();
        $this->Nicks       = new Nicks();
        $this->eventServer = new KEventServer();
        $this->connectContext = new ConnectContext;
        $this->log = new \Psr\Log\NullLogger;
    }

    public function setLogger(\Psr\Log\LoggerInterface $log)
    {
        $this->log = $log;

        if(get_class($log) == "Monolog\Logger") {
            $this->log->pushProcessor(function ($record) {
                $record['extra']['IRC Nick'] = $this->nick;
                return $record;
            });
        }
    }

    /**
     * Set the address to make connections from
     * @param string|null $bindIP
     */
    function setBindIP(?string $bindIP) {
        $this->connectContext = $this->connectContext->withBindTo($bindIP);
    }

    function setSSL(bool $ssl, bool $verify = true) {
        if($ssl) {
            if (!$verify) {
                $tls = (new ClientTlsContext($this->server))->withoutPeerVerification();
            } else {
                $tls = new ClientTlsContext($this->server);
            }
            $this->connectContext = $this->connectContext->withTlsContext($tls);
        } else {
            $this->connectContext = $this->connectContext->withoutTlsContext();
        }
    }

    /**
     * Initiate connection to IRC server
     */
    function connect() {
        if ($this->state != \IRC\State::DISCONNECTED) {
            throw new Exception("Connect called but IRC is already connected");
        }
        $this->log->info("Connecting");
        $this->state = \IRC\State::CONNECTING;
        $this->reconnectWatcher = null;
        try {
            $this->socket = yield connect($this->server . ':' . $this->port, $this->connectContext);
        } catch (\Amp\Socket\ConnectException $e) {
            $this->log->notice("Connection failed", [$e]);
            $this ->reconnectWatcher = Loop::delay($this->reconnectInterval * 1000, [$this, 'connect']);
            $this->log->info("Scheduled reconnect", ['reconnectInterval' => $this->reconnectInterval]);
            return;
        } catch (\Amp\CancelledException $e) {
            $this->log->info("Connection canceled", [$e]);
            return;
        }
        if($this->connectContext->getTlsContext() != null) {
            try {
                yield $this->socket->setupTLS();
            } catch (\Amp\Socket\TlsException $e) {
                $this->log->notice("TLS setup failed", [$e]);
                $this ->reconnectWatcher = Loop::delay($this->reconnectInterval * 1000, [$this, 'connect']);
                $this->log->info("Scheduled reconnect", ['reconnectInterval' => $this->reconnectInterval]);
                return;
            } catch (Exception $e) {
                $this->log->warning("Exception setting up TLS, not scheduling a reconnect", [$e]);
                return;
            }
        }
        $this->log->info("TLS Established");
        //should already be done, but making sure..
        $this->Nicks->clearAll();
        $this->resetSendQ();
        $this->log->info("Connected to IRC server");
        $this->state = \IRC\State::REGISTERING;
        if($this->pass != null) {
            $this->raw("PASS $this->pass");
        }
        $this->raw("NICK $this->nick");
        $this->curNick = $this->nick;
        $this->raw("USER $this->user");

        $this->reader = new \Amp\ByteStream\LineReader($this->socket);
        \Amp\asyncCall([$this, 'readLoop']);
        \Amp\asyncCall([$this, 'processSendQ']);
    }
    
    /**
     * Begin bot shutdown process
     * @param string $quitmsg
     */
    function killBot($quitmsg) {
        $this->eventServer->sendEvent(new IrcEvent('killbot', Array($quitmsg)));
        $this->raw('QUIT :' . $quitmsg);
        $this->tryDie = true;
    }

    /**
     * Handle socket errors
     * @param Exception $e
     */
    function handleError(Exception $e) {
        $this->log->info("Disconnected", [$e]);
        \Amp\Loop::cancel($this->sendWatcher);
        \Amp\Loop::cancel($this->nickCheckWatcher);
        //TODO ping watcher?
        //\Amp\Loop::cancel($this->);
        $this->Nicks->clearAll();
        $this->resetSendQ();
        $this->state = \IRC\State::DISCONNECTED;
        $this->eventServer->sendEvent(new IrcEvent('disconnect'));
        if($this->tryDie) {
            $this->canDie = true;
        }
    }

    /**
     * Handle read from socket
     */
    function readLoop() {
        while ($this->state != \IRC\State::DISCONNECTED) {
            try {
                $line = yield $this->reader->readLine();
            } catch (Exception $e) {
                $this->handleError($e);
                return;
            }
            if ($line == null) {
                $this->log->debug("Reader returned null");
                $this->handleError(new Exception("Read returned null"));
                return;
            }
            $line = rtrim($line, "\r");
            $this->parseLine($line);
        }
    }

    /**
     * Get the bots current nickname on irc
     * @return string
     */
    public function currentNick() {
        return $this->curNick;
    }
    
    /**
     * Called when the Bots nick needs changed
     * @param string $newnick
     */
    public function chgNick($newnick) {
        $this->log->info("Changing nickname", ['old' => $this->nick, 'new' => $newnick]);
        $this->raw("NICK $newnick");
        $this->nick = $newnick;
    }
    
	/**
	 * Word wrap a line by inserting \n, if the result is over the
	 * limited number of lines replace all with error
	 * @param string $target Target of message
	 * @param string $string String to process
	 * @param bool $no_f If true errors for exceeding limit
	 * @return array
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
     * @param string $no_f If true error for exceeding number of line limit
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
     * @param string $no_f If true error for exceeding number of line limit
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
                if($this->Nicks->isOp($this->currentNick(), $k) || $this->Nicks->isVoice($this->currentNick(), $k)) {
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
     * @param string $no_f If true error for exceeding number of line limit
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
     * @param string $no_f If true error for exceeding number of line limit
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
     * Sends a chanserv command for a channel
     * @param string $t The channel
     * @param string $m The message
     * @param bool $no_p If true no parseutil parsing
     */
    public function chanserv($t, $m, $no_p = false) {
        if(!$no_p) {
            $this->gM('ParseUtil')->parse($m, 'Pchanserv', $this, Array('t' => $t));
        } else {
            $this->Pchanserv($m, Array('t' => $t));
        }
    }
    
    public function Pchanserv($m, $ex)
    {
        $t = $ex['t'];
        $mm = $this->wrappy($t, $m, false);
        if(!empty($mm[0])) {
            $this->raw("CS $t $mm[0]");
        }
    }
    
    /**
     * Send a privmsg
     * @param string $t The target
     * @param string $m The message
     * @param string $no_f If true error for exceeding number of line limit
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
     * Process sending our out queue respecting flood rules
     */
    public function processSendQ() {
        $this->sendWatcher = null;
        if(empty($this->sendQ)) {
            return;
        }
        try {
            if ($this->throttleMethod == 0) {
                foreach ($this->sendQ as $key => $msg) {
                    $this->log->info("Sending line", ['line'=>$msg]);
                    yield $this->socket->write($msg);
                    unset($this->sendQ[$key]);
                }
                return;
            }
            if ($this->throttleMethod == 1) {
                $time = microtime(true);
                if ($time > $this->msg_since) {
                    $this->msg_since = $time;
                }

                foreach ($this->sendQ as $key => $msg) {
                    if ($this->msg_since - microtime(true) >= 10) {
                        break;
                    }
                    $this->log->info("Sending line", ['line'=>$msg]);
                    yield $this->socket->write($msg);
                    $this->msg_since += 2 + ((strlen($msg) + 2) / 120);
                    unset($this->sendQ[$key]);
                }

                if (!empty($this->sendQ)) {
                    $next = $this->msg_since - 10 - microtime(true) + 0.1;
                    //echo "delay processingsendq for " . $next * 1000 . "ms\n";
                    $this->sendWatcher = Loop::delay($next * 1000, [$this, 'processSendQ']);
                }
                return;
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
        //TODO use a getter and setter to validate
        throw new Exception("Unknown throttle method set " . $this->throttleMethod . "\n");
    }

    public function resetSendQ() {
        $this->sendQ = [];
        $this->msg_since = null;
        if($this->sendWatcher) {
            \Amp\Loop::cancel($this->sendWatcher);
            $this->sendWatcher = null;
        }
    }

    /**
     * To be called every cycle, process all our logic
     */
    public function logic() {
        if($this->state == \IRC\State::AUTHED) {
            $pingInterval = $this->pingInterval;
            if ($pingInterval < 5) $pingInterval = 5;
            if($this->pingTime < time() - $pingInterval) {
                $this->pingTime = time();
                $this->raw('PING LAG' . time());
            }
        }

    }
    
    /**
     * Queue a raw message to be sent to the server. \r\n is appended.
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
            $this->sendQ[] = "$s\r\n";
        }
        if($this->sendWatcher == null) {
            $this->sendWatcher = \Amp\Loop::defer([$this, 'processSendQ']);
        }
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
        if ($arg[2][0] == ':') {
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
        if ($arg[2][0] == ':') {
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
        if ($arg[2][0] == '#') {
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
        if ($arg[2][0] == '#') {
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
        if ($this->state != \IRC\State::AUTHED && $nick == 'AuthServ' &&
            ($text == 'I recognize you.' || arg_range($arg, 3, 6) == ':You are already authed')) {
            $this->state = \IRC\State::AUTHED;
            $this->eventServer->sendEvent(new IrcEvent('authed', Array()));
        }
        if ($arg[2][0] == '#') {
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
        if ($text[0] == ':') {
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
        if ($arg[2][0] == '#') {
            $this->eventServer->sendEvent(new IrcEvent('chanevent', Array($arg[2], $this->last_in)));
        }
        //:ChanServ!ChanServ@Services.GameSurge.net MODE #bots-dev -oo knives Roffle
        if ($arg[2][0] != '#') {
            return;
        }
        if (isset($arg[4])) {
            $mode_arg = explode(' ', arg_range($arg, 4, -1));
        } else {
            $mode_arg = Array();
        }
        $add = true; //adding or removing the mode
        while ($arg[3] != '') {
            switch ($arg[3][0]) {
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
                        $this->eventServer->sendEvent(new IrcEvent('+mode', Array($nick, $arg[2], $arg[3][0], $marg)));
                    } else {
                        if ($arg[3][0] == 'k') {
                            $marg = array_shift($mode_arg);
                        }
                        $this->eventServer->sendEvent(new IrcEvent('-mode', Array($nick, $arg[2], $arg[3][0], $marg)));
                    }
                    break;
                default:
                    if ($add) {
                        $this->eventServer->sendEvent(new IrcEvent('+mode', Array($nick, $arg[2], $arg[3][0], '')));
                    } else {
                        $this->eventServer->sendEvent(new IrcEvent('-mode', Array($nick, $arg[2], $arg[3][0], '')));
                    }
                    break;
            }
            $arg[3] = substr($arg[3], 1);
        }
    }
    
    /**
     * Parse an incoming line from Irc
     * @param string $line
     */
    function parseLine($line) {
        $this->log->info("Parsing line", ['line', $line]);
        $this->gM('ParseUtil')->clear();
        $this->last_in = $line;
        $this->eventServer->sendEvent(new IrcEvent('raw', Array($line)));

        if($line[0] == ':') {
            $line = substr($line, 1);
        }
        //split line by spaces
        $arg = explode(' ', $line);
        //get line after :
        $text = arg_range($arg, 3, -1);
        if(strlen($text) > 0 && $text[0] == ':') {
            $text = substr($text, 1);
        }

        switch($arg[0]) {
            case 'PING':
                $this->raw("PONG $arg[1]");
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
        $this->state = \IRC\State::CONNECTED;
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
            $this->nickCheckWatcher = Loop::delay($this->nickCheckInterval * 1000, [$this, 'retryNick']);
        } elseif ($arg[3] == $this->nick) {
            $this->nickCheckWatcher = Loop::delay($this->nickCheckInterval * 1000, [$this, 'retryNick']);
        }
    }

    protected function retryNick() {
        $this->raw("NICK $this->nick");
        $this->nickCheckWatcher = null;
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
