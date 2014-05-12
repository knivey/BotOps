<?php

/*
 * This files gives lower level things to hopefully make communication with source/hlds easier
 * 
 * rcon_source will handle rcon communication to SOURCE servers
 * rcon_old will handle rcon communitaction to hlds servers
 */

class tfcLog_hlds {

    public $bip; //our ip
    public $bport; //our port
    public $ip;  //servers ip
    public $port; //servers port
    public $pass; //rcon pass
    public $rcon;   //rcon connection
    public $chal; //rcon challenge number
    public $sock; //socket given to us by SCOREBOT
    public $incomp = Array();
    /*
     * packets that are split up get put back together here
     */
    public $outbuf = Array();
    public $funk; // -1 as little-endian long
    //Array of request ID's we have sent to rcon
    public $rcon_reqIDs = Array();

    /*
     * Hold an array of the players on the server
     * indexed by uid
     */
    public $players = Array();
    public $playerTemplate = Array(
        'steamid' => null,
        'name' => null,
        'uid' => null,
        'team' => null,
        'kills' => null,
        'deaths' => null
    );

    function __construct($ip, $port, $pass, $sock, &$rcon, $bip, $bport) {
        $this->funk = chr(255) . chr(255) . chr(255) . chr(255);
        $this->ip = $ip;
        $this->port = $port;
        $this->pass = $pass;
        $this->sock = $sock;
        $this->rcon = &$rcon;
        $this->bip = $bip;
        $this->bport = $bport;
    }

    function rehash($old) {
        
    }

    //return true if the id is from us
    function hasReqID($id) {
        return array_key_exists($id, $this->rcon_reqIDs);
    }

    function rcon_in($id, $data) {
        //handle return from our rcon requests
        $cmd = $this->rcon_reqIDs[$id];
        if ($cmd == 'status') {
            $data = explode("\n", $data);
            foreach ($data as $line) {
                $line = trim($line);
                //#115 "ThatGuy" BOT active
                //uid   name   steamid state
                //or for real players..
                //# 18 "Evilpablo" STEAM_0:1:34042716 44:39 93 0 active
                //# uid  name       steamid    connect time ping loss state
                $player = '/# *(?P<uid>[0-9]+) *"(?P<name>[^"]+?)" *(?P<steamid>[^ ]+) *(?P<time>[^ ]+) *(?P<ping>[^ ]+) *(?P<loss>[^ ]+) *(?P<state>[^ ]+)/';
                $bot = '/# *(?P<uid>[0-9]+) *"(?P<name>[^"]+?)" *(?P<steamid>BOT) *(?P<state>[^ ]+)/';
                if (preg_match($player, $line, $m)) {
                    $this->players[$m['uid']] = $this->playerTemplate;
                    $this->players[$m['uid']]['name'] = $m['name'];
                    $this->players[$m['uid']]['steamid'] = $m['steamid'];
                    $this->players[$m['uid']]['uid'] = $m['uid'];
                }
                if (preg_match($bot, $line, $m)) {
                    $this->players[$m['uid']] = $this->playerTemplate;
                    $this->players[$m['uid']]['name'] = $m['name'];
                    $this->players[$m['uid']]['steamid'] = $m['steamid'];
                    $this->players[$m['uid']]['uid'] = $m['uid'];
                }
            }
        }
    }

    function getPlayers() {
        return $this->players;
    }

    //called by SCOREBOT when we get new data from a sock read
    function datain($pack, $qm) {
        if ($qm == 'off') {
            $qm = false;
        } else {
            $qm = true;
        }
        $type = str2int(revbo(substr($pack, 0, 4)));
        $pack = substr($pack, 4);
        if ($type == -2) {
            //Handle split packets here
            return;
        }
        $pack = substr($pack, 1); //get rid of the ugly l
        $pack = explode("\n", $pack);
        /*
         * L 07/04/2011 - 04:36:47: World triggered "Round_Start"
         * L 07/04/2011 - 04:36:47: "The G-Man<13><BOT><Red>" joined team "Spectator"
         * L 07/04/2011 - 04:36:32: Team "Red" current score "0" with "3" players
         * L 07/04/2011 - 04:36:11: "The Combine<14><BOT><Blue>" killed "Hat-Wearing MAN<11><BOT><Red>" with "scattergun" (attacker_position "550 1553 64") (victim_position "270 1323 148")
         * L 07/04/2011 - 04:36:09: rcon from "50.56.34.229:52153": command "logaddress_list"
         * L 07/04/2011 - 04:34:10: server_cvar: "tf_bot_count" "6"
         * L 07/04/2011 - 04:32:18: "kNiVeY<16><STEAM_0:0:1562053><Red>" changed role to "sniper"
         * L 07/04/2011 - 04:32:15: "kNiVeY<16><STEAM_0:0:1562053><Unassigned>" joined team "Red"
         * L 07/04/2011 - 04:31:39: "Totally Not A Bot<15><BOT><Red>" changed name to "Pow!"
         * L 07/04/2011 - 04:31:39: "<15><BOT><>" entered the game
         * L 07/04/2011 - 04:31:39: "kNiVeY<8><STEAM_0:0:1562053><Spectator>" disconnected (reason "Kicked by Console")
         * L 07/04/2011 - 04:31:50: "kNiVeY<16><STEAM_0:0:1562053><>" connected, address "74.5.161.201:21979"
         * L 07/04/2011 - 04:31:51: "kNiVeY<16><STEAM_0:0:1562053><>" STEAM USERID validated
         * L 07/04/2011 - 04:31:57: "kNiVeY<16><STEAM_0:0:1562053><>" entered the game
         * L 07/06/2011 - 10:31:44: Team "Red" triggered "pointcaptured" (cp "0") (cpname "#koth_viaduct_cap") (numcappers "2") (player1 "Hat-Wearing MAN<658><BOT><Red>") (position1 "-143 161 296") (player2 "The G-Man<660><BOT><Red>") (position2 "212 142 296") 
         * http://developer.valvesoftware.com/wiki/HL_Log_Standard
         */
        foreach ($pack as $line) {
            $line = trim($line);
            if ($line == '') {
                continue;
            }
            $arg = explode(' ', $line);
            //Leading L
            array_shift($arg);
            $date = array_shift($arg);
            //dash between date and time
            array_shift($arg);
            $time = trim(array_shift($arg), ':');

            /*
             * Points Awarded:
             * http://tf2wiki.net/wiki/Points
             * (customkill "headshot")  - sniper headshots
             */

            /*
             * need to filter out things like rcon commands and useless lines
             * need to create an array indexed by uid for players on server
             *  store points in the array as well and initialize it with an
             *  rcon command?
             * it may be possible to parse out players into array in their
             *  order on the line then parse the actions in between then form
             *  a better output (might be possible to check if another line
             *  of data is recved already to prevent lag of kill reporting)
             * Need to figure a good way to display Kill Assists, probably
             *  by sotring all kills until next line is recieved and checked?
             * 
             */

            $line = implode(' ', $arg);

            $rconfrom = '/rcon from "' . $this->bip . ':[0-9]+":.*/';
            if (preg_match($rconfrom, $line)) {
                $logstart = '/rcon from "' . $this->bip . ':[0-9]+": command "logaddress_add ' . $this->bip . ':' . $this->bport . '"/';
                if (preg_match($logstart, $line)) {
                    //send an rcon command to get the current players
                    $id = $this->rcon->sendCmd('status');
                    $this->rcon_reqIDs[$id] = 'status';
                }
                continue;
            }

            $userevent = '/(?P<replace>"(?P<name>[^"]*?)<(?P<uid>[0-9]*)><(?P<steamid>.*?)><(?P<team>.*?)>").*/';
            $umatches = Array();
            while (preg_match($userevent, $line, $matches)) {
                $umatches[] = $matches;
                $color = '';
                if ($matches['team'] == 'Blue') {
                    $team = 'Blue';
                    $color = "\3" . "12";
                }
                if ($matches['team'] == 'Red') {
                    $team = 'Red';
                    $color = "\3" . "04";
                }
                $line = str_replace($matches['replace'], $color . $matches['name'] . "\3", $line);
            }

            //get attacker and victim positions to calc distance
            $pat = '/\(attacker_position "(?P<atk_x>[^ ]+) (?P<atk_y>[^ ]+) (?P<atk_z>[^ ]+)"\) \(victim_position "(?P<vic_x>[^ ]+) (?P<vic_y>[^ ]+) (?P<vic_z>[^ ]+)"\)/';
            if (preg_match($pat, $line, $m)) {
                $ax = floatval($m['atk_x']);
                $ay = floatval($m['atk_y']);
                $az = floatval($m['atk_z']);
                $vx = floatval($m['vic_x']);
                $vy = floatval($m['vic_y']);
                $vz = floatval($m['vic_z']);
                $i = floatval(($ax - $vx) * ($ax - $vx));
                $j = floatval(($ay - $vy) * ($ay - $vy));
                $k = floatval(($az - $vz) * ($az - $vz));
                $distance = round(sqrt($i + $j + $k) / 12, 2);
            } else {
                $distance = 0;
            }
            //remove positions from the line
            $positions = '/(?<replace>\(((attacker|assister|victim)_|)position .*?"\))/';
            while (preg_match($positions, $line, $matches)) {
                $line = str_replace($matches['replace'], '', $line);
            }
            $pat = '/(?P<who>[^"]+?) triggered "(?P<what>[^"]+?)" \(event "(?P<event>[^"]+?)"\)/';
            if (preg_match($pat, $line, $matches)) {
                if ($matches['what'] == 'flagevent') {
                    if ($matches['event'] == 'picked up') {
                        $line = "$matches[who] picked up the flag";
                    }
                    if ($matches['event'] == 'captured') {
                        $patb = '/(?P<who>[^"]+?) triggered "(?P<what>[^"]+?)" \(event "(?P<event>[^"]+?)"\) \(team_caps "(?P<teamcaps>[0-9]+)"\) \(caps_per_round "(?P<roundcaps>[0-9]+)"\)/';
                        preg_match($patb, $line, $matches);
                        $line = "$matches[who] captured the flag (" . $color . $team . " Caps $matches[teamcaps]/$matches[roundcaps]\3)";
                    }
                    if ($matches['event'] == 'dropped') {
                        $line = "$matches[who] dropped the flag";
                    }
                    if ($matches['event'] == 'defended') {
                        $line = "$matches[who] defended the flag";
                    }
                }
            }

            $killed = '/(?P<attacker>[^"]+?) killed (?P<victim>[^"]+?) with "(?P<what>[^"]+?)"/';
            if (preg_match($killed, $line, $matches)) {
                $line = "$matches[attacker] killed $matches[victim] with a $matches[what] from $distance feet";
            }

            // kNiVeY say "lol"
            // Kill Me disconnected (reason "Kicked by Console")
            // [unknown] disconnected (reason "Disconnect by user.")
            // Chucklenuts connected, address "none"
            // Chucklenuts joined team "Red"
            // L 08/28/2012 - 06:57:41: "Soldier<170><BOT><Blue>" disconnected (reason "Kicked from server")
            $say = '/(?P<who>[^"]+?) say "(?P<text>.*)"/';
            $dis = '/(?P<who>[^"]+?) disconnected \(reason "(?P<why>[^"]+?)"\)/';
            $con = '/(?P<who>[^"]+?) connected, address "(?P<addy>[^"]+?)"/';
            $jteam = '/(?P<who>[^"]+?) joined team "(?P<team>[^"]+?)"/';
            $qm_mat = false;
            if (preg_match($say, $line, $m)) {
                $line = "<$m[who]> $m[text]";
                $qm_mat = true;
            }
            if (preg_match($dis, $line, $m)) {
                $line = "$m[who] (" . $umatches[0]['steamid'] . ") diconnected $m[why]";
                if ($umatches[0]['steamid'] != 'BOT') {
                    $qm_mat = true;
                }
            }
            if (preg_match($con, $line, $m)) {
                $line = "$m[who] (" . $umatches[0]['steamid'] . ") is connecting";
                if ($m['addy'] != 'none') {
                    $qm_mat = true;
                }
            }
            if (preg_match($jteam, $line, $m)) {
                $line = "$m[who] has joined team $m[team]";
                $qm_mat = false;
            }
            if ($qm != true) {
                $this->outbuf[] = $line;
            } else {
                if ($qm_mat == true) {
                    $this->outbuf[] = $line;
                }
            }
        }
    }

    //called by scorebot to see if we have anything to say
    function readout() {
        return array_shift($this->outbuf);
    }

    function hasData() {
        return count($this->outbuf);
    }

}

class rcon_hlds {

    public $ip;  //servers ip
    public $port; //servers port
    public $pass; //rcon pass
    public $chal; //rcon challenge number
    public $sock; //socket given to us by SCOREBOT
    public $incomp = Array();
    /*
     * packets that are split up get put back together here
     */
    public $outbuf = Array();
    public $funk; // -1 as little-endian long

    function __construct($ip, $port, $pass, $sockets) {
        $this->funk = chr(255) . chr(255) . chr(255) . chr(255);
        $this->pSockets = &$sockets;
        $this->ip = $ip;
        $this->port = $port;
        $this->pass = $pass;
        $this->sock = $sock;
    }

    function rehash($old) {
        //$this->incomp = $old->incomp;
        $this->chal = $old->chal;
        //$this->outbuf = $old->outbuf;
    }

    function init() {
        //First things first.. send rcon challenge
        $packout = $this->funk . "challenge rcon\0";
        socket_sendto($this->sock, $packout, strlen($packout), 0, $this->ip, $this->port);
    }

    //called by SCOREBOT when we get new data from a sock read
    function datain($pack) {
        $type = str2int(revbo(substr($pack, 0, 4)));
        $pack = substr($pack, 4);
        if ($type == -2) {
            //Handle split packets here
            return;
        }
        $arg = explode(' ', $pack);
        if ($arg[0] == 'challenge') {
            $this->chal = trim($arg[2]);
            $this->outbuf[] = "Received challenge number, testing password...";
            $this->runcmd("echo Verified rcon_password.");
            return;
        }
        $pack = substr($pack, 1); //get rid of the ugly l
        $pack = explode("\n", $pack);
        foreach ($pack as $line) {
            $line = trim($line);
            if ($line != '') {
                $this->outbuf[] = $line;
            }
        }
    }

    function runcmd($cmd) {
        $packout = $this->funk . chr(2) . 'rcon ' . $this->chal . ' ' . $this->pass . " $cmd\0";
        socket_sendto($this->sock, $packout, strlen($packout), 0, $this->ip, $this->port);
    }

    //called by scorebot to see if we have anything to say
    function readout() {
        return array_shift($this->outbuf);
    }

    function hasData() {
        return count($this->outbuf);
    }

}

/*
 * Establish RCON connection to a Source server
 */

class sourceRcon {

    // The IP of the Source server
    public $ip;
    // The port of the Source server
    public $port;
    // RCON Password
    public $pass;
    /*
     * State of the connection
     * 0 - Not connected
     * 1 - TCP Connection established
     * 2 - Authenticated and ready
     */
    public $state;
    // Request counter increased for commands sent
    public $reqNum;
    //Reference to socket class
    public $pSockets;
    //The current socket being used
    public $pSock;
    //Callback information, called when we get any activity
    public $cbClass;
    public $cbFunc;
    //contains the reqId of our auth request
    public $authReqId;

    public function __construct($cbClass, $cbFunc, $ip, $port, $pass, $sockets) {
        $this->pSockets = &$sockets;
        $this->cbClass = &$cbClass;
        $this->cbFunc = $cbFunc;
        $this->ip = $ip;
        $this->port = $port;
        $this->pass = $pass;
        $this->state = 0;
    }

    public function rehash($cbClass, &$old) {
        $this->pSockets = &$old->pSockets;
        $this->pSock = $old->pSock;
        $this->ip = $old->ip;
        $this->port = $old->port;
        $this->pass = $old->pass;
        $this->cbClass = &$cbClass;
        $this->cbFunc = $old->cbFunc;
        $this->reqNum = $old->reqNum;
        $this->state = $old->state;
    }

    //don't call init if rehashing!
    public function init() {
        $this->pSock = $this->pSockets->createTCP($this, 'sockRead', 'sockError', 0, 'sockConnect', 0);
        //I'm assuming I can give this a 5 minute timeout... Its a starting point
        $this->pSockets->connect($this->pSock, $this->ip, $this->port, 350, 350);
        //$this->sendAuth();
    }

    public function sendAuth() {
        if ($this->state == 2 || $this->state == 0) {
            return;
        }
        //Form our AUTH packect
        //Auth just sends the password as the command
        // string using SERVERDATA_AUTH
        /*
         * packet size (int)
         * request id (int)
         * SERVERDATA_EXECCOMMAND(2) / SERVERDATA_AUTH(3) (int)
         * string1(null terminated) is the command to run.
         * string2(null terminated) must be null (""); 
         */
        $this->reqNum++;
        $this->authReqId = $this->reqNum;
        $data = pack("VV", $this->reqNum, 3) . $this->pass . chr(0) . '' . chr(0);
        $data = pack("V", strlen($data)) . $data;
        $this->pSockets->send($this->pSock, $data);
        return $this->reqNum;
    }

    public $sendQ = Array();

    public function logic() {
        if ($this->state == 2) {
            if (!empty($this->sendQ)) {
                foreach ($this->sendQ as $id => $cmd) {
                    $data = pack("VV", $id, 2) . $cmd . chr(0) . '' . chr(0);
                    $data = pack("V", strlen($data)) . $data;
                    $this->pSockets->send($this->pSock, $data);
                    $this->reqNum = $id;
                }
                $this->sendQ = Array();
            }
        }
    }

    public function qSendCmd($cmd) {
        $num = $this->reqNum + count($this->sendQ) + 2;
        $this->sendQ[$num] = $cmd;
        return $num;
    }

    public function sendCmd($cmd) {
        if ($this->state != 2) {
            if (count($this->sendQ) == 0) {
                $this->init();
            }
            return $this->qSendCmd($cmd);
        }
        $this->reqNum++;
        $data = pack("VV", $this->reqNum, 2) . $cmd . chr(0) . '' . chr(0);
        $data = pack("V", strlen($data)) . $data;
        $this->pSockets->send($this->pSock, $data);
        return $this->reqNum;
    }

    public function getSock() {
        return $this->pSock;
    }

    public function sockRead($sock, $data) {
        //If we aren't AUTHed this should be a reply to an auth request
        //especially since we should be sending any other request without auth
        /*
         * Return packet same as response? might be diff for spanning over packs
         * packet size (int)
         * request id (int)
         * command response (int) SERVERDATA_RESPONSE_VALUE = 0 SERVERDATA_AUTH_RESPONSE = 2
         * string1 (null delimited string)
         * string2 (null delimited string) 
         */
        //Supposedly we aren't connected so i'm going to ignore
        if ($this->state == 0) {
            return;
        }
        $udata = $data;
        $size = 0;
        $id = 0;
        $cmd = 0;
        $str1 = '';
        $str2 = '';
        $size = implode('', unpack('V', substr($data, 0, 4)));
        $data = substr($data, 4);
        $id = implode('', unpack('V', substr($data, 0, 4)));
        $data = substr($data, 4);
        $cmd = implode('', unpack('V', substr($data, 0, 4)));
        $data = substr($data, 4);
        $cbClass = &$this->cbClass;
        $cbFunc = $this->cbFunc;
        $cbClass->$cbFunc($this->pSock, $id, 'DEBUG', "Size: $size Id: $id Cmd: $cmd Data: $data");
        $cbClass->$cbFunc($this->pSock, $id, 'HEX', hexdump($udata));
        if ($this->state == 1) {
            if ($cmd == 2 && $id == $this->authReqId) {
                $this->state = 2;
                $cbClass->$cbFunc($this->pSock, $id, 'DEBUG', "Authenticated!");
                $cbClass->$cbFunc($this->pSock, $id, '', 'Rcon established');
            }
            if ($cmd == 2 && $id != $this->authReqId) {
                $cbClass->$cbFunc($this->pSock, $id, 'DEBUG', "Authentication FAILED!");
                $cbClass->$cbFunc($this->pSock, $id, '', 'Rcon Failed, invalid password?');
            }
            return;
        }
        //Connected and AUTHed deal with reponses
        if ($this->state == 2) {
            $cbClass->$cbFunc($this->pSock, $id, '', $data);
            return;
        }
    }

    public function sockError($sock, $error) {
        $this->state = 0;
        $cbClass = &$this->cbClass;
        $cbFunc = $this->cbFunc;
        $cbClass->$cbFunc($this->pSock, $id, 'DEBUG', "Socket Error: $error");
        $cbClass->$cbFunc($this->pSock, $id, 'Error', "($error)" . socket_strerror($error));
        //handle error
    }

    public function sockConnect($sock) {
        $this->state = 1;
        $cbClass = &$this->cbClass;
        $cbFunc = $this->cbFunc;
        //send AUTH request
        $num = $this->sendAuth();
        $cbClass->$cbFunc($this->pSock, $id, 'Connected', $num);
        $cbClass->$cbFunc($this->pSock, $id, 'DEBUG', "Socket Connected (sending auth)");
    }

}


?>