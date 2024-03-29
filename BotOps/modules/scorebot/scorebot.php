<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';
require_once('modules/Module.inc');

class scorebot extends Module {
    function cmd_connect(CmdRequest $r) {
        $ip = gethostbyname($r->args['ip']);
        $port = $r->args['port'];
        $pass = $r->args['pass'];
        $inchan = $this->findChan($ip, $port);
        if($inchan == FALSE) {
            $this->startbot($r->chan, $ip, $port, $pass);
        } else {
            throw new CmdException("ScoreBot is already running for $ip:$port in $inchan");
        }
    }
    
    function cmd_startsb(CmdRequest $r) {
        if(!$this->chanRunning($r->chan)) {
            throw new CmdException("ScoreBot not connected in this channel.");
        }
        if(!array_key_exists('sourceRcon', $this->chans[strtolower($r->chan)])) {
            $this->chans[strtolower($r->chan)]['rcon']->runcmd("logaddress $this->bindip $this->bindport");
        } else {
            $this->chans[strtolower($r->chan)]['sourceRcon']->sendCmd("log 1");
            $this->chans[strtolower($r->chan)]['sourceRcon']->sendCmd("logaddress_add $this->bindip:$this->bindport");
        }
    }
    
    function cmd_stopsb(CmdRequest $r) {
        if(!$this->chanRunning($r->chan)) {
            throw new CmdException("ScoreBot not connected in this channel.");
        }
        if(!array_key_exists('sourceRcon', $this->chans[strtolower($r->chan)])) {
            $this->chans[strtolower($r->chan)]['rcon']->runcmd("logaddress_del $this->bindip $this->bindport");
        } else {
            $this->chans[strtolower($r->chan)]['sourceRcon']->sendCmd("logaddress_del $this->bindip:$this->bindport");
        }
    }
    
    function cmd_sbplayers(CmdRequest $r) {
        $chan = strtolower($r->chan);
        if(!$this->chanRunning($chan)) {
            throw new CmdException("ScoreBot not connected in this channel.");
        }
        $players = $this->chans[$chan]['logger']->getPlayers();
        if(empty($players)) {
            throw new CmdException('ScoreBot not running or no players');
        }
        $out = '';
        foreach($players as $p) {
            $out .= "$p[name]($p[uid]) $p[steamid] | ";
        }
        $out = trim($out, ' |');
        $r->reply("\2Players:\2 $out");
    }

    function cmd_rcon(CmdRequest $r) {
        $chan = strtolower($r->chan);
        if(!$this->chanRunning($chan)) {
            $r->reply("ScoreBot not connected in this channel.");
            return;
        }
        if(!array_key_exists('sourceRcon', $this->chans[$chan])) {
            $this->chans[$chan]['rcon']->runcmd($r->args[0]);
        } else {
            $cid = $this->chans[$chan]['sourceRcon']->sendCmd($r->args[0]);
            //if($cid == -1) {
            //    $this->chans[$chan]['sourceRcon']->init();
            //    $r->reply("Reconnecting to rcon try again.");
            //} else {
                $this->chans[$chan]['cids'][$cid] = $r->args[0];
            //}
        }
    }
    
    function ircmsg($nick, $target, $text) {
        $text = str_replace(';', '', $text);
        $chan = strtolower($target);
        if(!$this->chanRunning($chan)) {
            //$this->pIrc->msg($chan, "$nick $chan $text NOT RUNNING");
            return;
        }
        if(!array_key_exists('sourceRcon', $this->chans[$chan])) {
            //$this->pIrc->msg($chan, "$nick $chan $text NO RCON");
            return;
        }
        $iscmd = $this->gM('CmdReg')->wascmd;
        if($iscmd) {
            return;
        }
        $this->chans[$chan]['sourceRcon']->sendCmd("sbircsay ($nick) $text");
    }

    
    public $chans = Array();
    /*
	 * $chans['#chan'] = Array()
	 * * ip		= servers IP
	 * * port 	= servers port
	 * * pass	= rcon pass
	 * * game	= gametype (cs,tfc,css,etc..)
	 * * rcon	= RCON object
	 * * log	= Logger object
         * * cids        = command ids sent via cmd_rcon
	 * * rstatus	= status of our rcon "connection"
	 * ** NULL	= haven't heard anything from server
	 * ** chng	= got a channelge number for rcon
	 * ** pass	= rcon password was verified
	 * * lstatus	= status of our logger "connection"
	 * ** NULL	= Not received anything
	 * ** good	= received packets, so things must be workin
	 * * rtime	= time of last data recv from rcon (used when first establishing connection
	 * * rretry = number of times we have retried rcon connection
	 * * ltime	= time of last data recv from log
         * * qm         = quietmode, only show join/quit chat
    */

    public $bindip = '0';		//ip our log listener uses
    public $bindport = 0;	//port our log listener uses
    public $sock;		//We will use this for all our logging communications

    public $rbindip = '0';	//ip our rcon listener uses
    public $rbindport = 0;	//port our rcon listener uses
    public $rsock;		//We will use this for all our rcon communications

    function chanRunning($chan) {
        $chan = strtolower($chan);
        if(array_key_exists($chan, $this->chans)) {
            return true;
        }
        return false;
    }
    
    function rehash(&$old) {       
        $this->bindip = $old->bindip;
        $this->bindport = $old->bindport;
        $this->sock = $old->sock;
        $this->rbindip = $old->rbindip;
        $this->rbindport = $old->rbindport;
        $this->rsock = $old->rsock;
        $this->chans = $old->chans;
        foreach($this->chans as $c => $d) {
            //update class reference in socket manager
            if(array_key_exists('sourceRcon', $this->chans[$c])) {
                //NULL should be ok here since it's rehash better get info from old instance
                $newSourceRcon = new sourceRcon($this, 'sourceRconRead', NULL, NULL, NULL, NULL);
                $newSourceRcon->rehash($this, $old->chans[$c]['sourceRcon']);
                $this->pSockets->chgClass($old->chans[$c]['sourceRcon'], $this->chans[$c]['sourceRcon']);
                $this->chans[$c]['sourceRcon'] = $newSourceRcon;
                $newlog = new tfcLog_hlds($d['ip'], $d['port'], $d['pass'], $this->sock, $newSourceRcon, $this->bindip, $this->bindport);
                $newlog->rehash($d['logger']);
                $this->chans[$c]['logger'] = $newlog;
            } else {
                $newrc = new rcon_hlds($d['ip'], $d['port'], $d['pass'], $this->rsock);
                $newrc->rehash($d['rcon']);
                $this->chans[$c]['rcon'] = $newrc;
            }
        }
    }

    function init() {
        $this->sock = $this->pSockets->createUDP($this, 'logRead', 'logError', $this->bindip, $this->bindport, $style = 0);
        if(!$this->sock) {
            echo "ScoreBot failed to create LOG sock: " . socket_strerror(socket_last_error($this->sock));
            return;
        }
        socket_getsockname($this->sock, $this->bindip, $this->bindport);

        $this->rsock = $this->pSockets->createUDP($this, 'rconRead', 'rconError', $this->rbindip, $this->rbindport, $style = 0);
        if(!$this->rsock) {
            echo "ScoreBot failed to create LOG sock: " . socket_strerror(socket_last_error($this->rsock));
            return;
        }
        socket_getsockname($this->rsock, $this->rbindip, $this->rbindport);
    }
    
    function cleanup() {
        $this->pSockets->destroy($this->sock);
        $this->pSockets->destroy($this->rsock);
    }
    
    function v_gameinfo($args) {
        $server = $args[0];
        $arg = explode(' ', $server);
                if(cisin($arg[0], ':')) {
            $ip = explode(':', $arg[0]);
            $port = $ip[1];
            $ip = $ip[0];
        } else {
            $ip = $arg[0];
            if (isset($arg[1])) {
                $port = $arg[1];
            } else {
                $port = 27015;
            }
        }
        $sarg = escapeshellarg("$ip:$port");
        $xml = `quakestat -xml -utf8 -timeout 1 -a2s $sarg`;
        $xml = simplexml_load_string($xml);
        $xml = $xml->server;
        if ($xml->hostname == '' || $xml['status'] == 'DOWN') {
            return "Server not found $ip:$port";
        }
        return "\2Server:\2 $xml->name \2Game:\2 $xml->gametype \2Map:\2 $xml->map \2Players:\2 $xml->numplayers/$xml->maxplayers \2Ping:\2 $xml->ping";
    }

    function cmd_gameinfo(CmdRequest $r) {
        $ip = explode(':', $arg[0]);
        if(isset($ip[1])) {
            $port = $ip[1];
        } else {
            $post = "27015";
        }

        $sarg = escapeshellarg("$ip:$port");
        $xml = `quakestat -xml -utf8 -timeout 5 -a2s $sarg`;
        $xml = simplexml_load_string($xml);
        $xml = $xml->server;
        if ($xml->hostname == '' || $xml['status'] == 'DOWN') {
            $r->reply("Server not found $ip:$port");
            return;
        }
        $r->reply("\2Server:\2 $xml->name \2Game:\2 $xml->gametype \2Map:\2 $xml->map \2Players:\2 $xml->numplayers/$xml->maxplayers \2Ping:\2 $xml->ping");
    }

    function cmd_gameplayers(CmdRequest $r) {
        $ip = explode(':', $arg[0]);
        if(isset($ip[1])) {
            $port = $ip[1];
        } else {
            $post = "27015";
        }

        $sarg = escapeshellarg("$ip:$port");
        $xml = `quakestat -xml -utf8 -timeout 5 -P -a2s $sarg`;
        $xml = simplexml_load_string($xml);
        $xml = $xml->server;
        if ($xml->hostname == '' || $xml['status'] == 'DOWN') {
            $r->reply("Server not found $ip:$port");
            return;
        }
        
        $Players = '';
        
        foreach ($xml->players->player as $p) {
            $Players .= $p->name . '('. $p->score . ') ' . $p->time . ' | ';
        }
        $Players = trim($Players, ' |');
        $r->reply("\2Server:\2 $xml->name \2Game:\2 $xml->gametype \2Players($xml->numplayers/$xml->maxplayers):\2 $Players");
    }
    
    function startbot($chan, $ip, $port, $pass) {
        $ip = gethostbyname($ip);

        //TODO check if bot is already started.

        //first lets qstat the server and get gametype / make sure its up
        $xml = `quakestat -xml -utf8 -timeout 5 -a2s $ip:$port`;
        $xml = simplexml_load_string($xml);
        $xml = $xml->server;
        if(isset($xml->error) || $xml['status'] == 'DOWN') {
            $this->pIrc->msg($chan, "Error, the server at $ip:$port seems to be down.");
            return;
        }
        $isSource = false; //HLDS or Source
        switch($xml->gametype) {
            //case 'tfc':
            //    $logger = new tfcLog_hlds($ip, $port, $pass, $sock);
            //case 'cstrike':
            //    $this->pIrc->msg($chan, "Establishing RCON to $ip:$port Gametype: $xml->gametype Players: $xml->numplayers/$xml->maxplayers Map: $xml->map Name: $xml->name");
            //    $rcon = new rcon_hlds($ip, $port, $pass, $this->pSockets);
            //    $logger = new tfcLog_hlds($this, 'hldsRconRead', $ip, $port, $pass, $sock, $rcon, $this->bindip, $this->bindport);
            //    $rcon->init();
            //    break;
            //case 'css':
                //$this->pIrc->msg($chan, "Establishing RCON to $ip:$port Gametype: $xml->gametype Players: $xml->numplayers/$xml->maxplayers Map: $xml->map Name: $xml->name");
                //$rcon = new rcon_hlds($ip, $port, $pass);//update this for source rcon
                //break;
            case 'tf':
                $isSource = true;
                $this->pIrc->msg($chan, "Establishing RCON to $ip:$port Gametype: $xml->gametype Players: $xml->numplayers/$xml->maxplayers Map: $xml->map Name: $xml->name");
                $rcon = new sourceRcon($this, 'sourceRconRead', $ip, $port, $pass, $this->pSockets);
                $logger = new tfcLog_hlds($ip, $port, $pass, $this->sock, $rcon, $this->bindip, $this->bindport);
                $rcon->init();
                break;
            default:
                $this->pIrc->msg($chan, "The game $xml->gametype is not currently supported.");
                return;
        }

        $this->chans[strtolower($chan)] = Array(
                'ip' =>$ip,
                'port' => $port,
                'pass' => $pass,
                'game' => (string)$xml->gametype,
                //'rcon' => &$rcon,
                'logger' => &$logger,
                'log' => NULL
        );
        if($isSource) {
            $this->chans[strtolower($chan)]['sourceRcon'] = &$rcon;
        } else {
            $this->chans[strtolower($chan)]['rcon'] = &$rcon;
        }
    }
    
    function findChanBySock($sock) {
        foreach ($this->chans as $chan => $c) {
            if (array_key_exists('sourceRcon', $c)) {
                if (intval($c['sourceRcon']->getSock()) == intval($sock)) {
                    return $chan;
                }
            }
        }
        return FALSE;
    }

    function sourceRconRead($sock, $req, $event, $data) {
        $chan = $this->findChanBySock($sock);
        if($chan == FALSE) {
            return;
        }
        if($event == 'Connected') {
            $this->chans[$chan]['cids'][$data] = 'auth';
            return;
        }
        if($event == 'Error') {
            $this->pIrc->msg($chan, "Rcon expired. $data");
            return;
        }
        if($event == 'HEX') {
            //$this->pIrc->msg($chan, "\2HEX:\2 $data");
            return;
        }
        if($event == 'DEBUG') {
            //$this->pIrc->msg($chan, "\2DEBUG:\2 $data");
            return;
        }
        if($event == '') {
            if ($this->chans[$chan]['logger']->hasReqID($req)) {
                $this->chans[$chan]['logger']->rcon_in($req, $data);
            }
            $data = explode("\n", $data);
            foreach($data as $line) {
                $line = trim($line);
                if($line == '') continue;
                if(array_key_exists($req, $this->chans[$chan]['cids'])) {
                    $this->pIrc->msg($chan, "\2rcon:\2 $line");
                }
            }
            return;
        }
    }
    
    function cmd_qm(CmdRequest $r) {
        $chan = strtolower($r->chan);
        if(!$this->chanRunning($chan)) {
            throw new CmdException("ScoreBot not connected in this channel.");
        }
        if($r->args[0] != 'on' && $r->args[0] != 'off') {
            $r->reply("Quitemode is currently: " . $this->chans[$chan]['qm'] . " (use on|off to change)");
            return;
        }
        $this->chans[$chan]['qm'] = $r->args[0];
        $r->reply("Quitemode set to {$r->args[0]}");
    }
    
    function logRead($sock, $from_ip, $from_port, $data) {
        $chan = $this->findChan($from_ip, $from_port);
        if($chan) {
            if (!array_key_exists('qm', $this->chans[$chan])) {
                $this->chans[$chan]['qm'] = 'off';
            }
            $this->chans[$chan]['logger']->datain($data, $this->chans[$chan]['qm']);
        }
    }


    function rconRead($sock, $from_ip, $from_port, $data) {
        $chan = $this->findChan($from_ip, $from_port);
        if($chan) {
            $this->chans[$chan]['rcon']->datain($data);
        }
    }

    function logic() {
        //now send anything to channels if needed
        foreach($this->chans as $chan => $c) {
            while($this->chans[$chan]['logger']->hasData() > 0) {
                $data = $this->chans[$chan]['logger']->readout();
                $this->pIrc->msg($chan, $data);
            }
            if (!array_key_exists('sourceRcon', $c)) {
                while ($this->chans[$chan]['rcon']->hasData() > 0) {
                    $data = $this->chans[$chan]['rcon']->readout();
                    if ($data == 'Bad rcon_password.') {
                        $this->pIrc->msg($chan, "Stopping ScoreBot: $data");
                        //TODO put in things that stop the bot here
                        continue;
                    }
                    if ($data == 'Verified rcon_password.') {
                        $this->pIrc->msg($chan, "Starting ScoreBot: $data");
                        //TODO update status so we know the rcon pass was verified
                        continue;
                    }
                    $this->pIrc->msg($chan, $data);
                }
            } else {
                $this->chans[$chan]['sourceRcon']->logic();
            }
        }
    }

    //find the channel for the ip and port
    function findChan($ip, $port) {
        foreach($this->chans as $chan => $c) {
            if($c['ip'] == $ip && $c['port'] == $port) {
                return $chan;
            }
        }
        return FALSE;
    }
}

