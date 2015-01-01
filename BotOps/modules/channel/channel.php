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
 * Channel.php
 *   Gives us functions for accessing channels part of database
 *   Store topic bans users modes etc
 ***************************************************************************/
 require_once('modules/Module.inc');
 require_once('Tools/Tools.php');
 
class channel extends Module {
    public $chans = Array();
    /*
     * Array of channels bot has joined on irc and irc data
     * $chans[#chan] = Array(...)
     * name = Channel name
     * modes = Array(mode => args?, 'l' => 32, 'm' etc)
     * nicks[nick] = Array('@' => '@', '+' => '+')
     * topic = channel topic
     * topicTime = time topic was set
     * topicNick = nick who set topic
     * bans = Array('mask' => array('by', 'time'))
     * createTime = time channel was created
     */

     public $dChans = Array();

     /*
      * Array of channels the bot is assigned to
      * Holds all data from db
      * loadChan($chan) (re)loads  db data
      * $dChans[strtolower(#chan)] = Array(...)
      * trig = (trigger)command duh
      * settings = Array() Various settings from all modules
      * rejoinTrys = times we tryed to rejoin
      */

    /*
     * First we have hooks and stuff
     */

     public $iscon = true;
     
     function rehash(&$old) {
         $this->chans = $old->chans;
         $this->dChans = $old->dChans;
         $this->iscon = $old->iscon;
         $this->rejoinTimer = $old->rejoinTimer;
         echo "Channel rehash finished\n";
     }
     
    function init() {
        $this->rejoinTimer = time() + 60;
    }
    
    public $rejoinTimer;
    function logic() {
        if ($this->rejoinTimer < time()) {
            $this->rejoinTimer = time() + 20;
            foreach($this->dChans as $chan => $blah) {
                if(!array_key_exists($chan, $this->chans)) {
                    if ($this->getSet($chan, 'channel', 'suspend') == null) {
                        $this->pIrc->raw("join $chan");
                    }
                }
            }
        }
    }


     function h_disconnected() {
         $this->chans = Array(); // Clear our chans
         $this->iscon = false;
         foreach ($this->dChans as &$c) {
             $c['rejoinTrys'] = 0;
         }
     }

    function h_join($nick, $chan) {
        if ($nick == 'OpServ') {
            $logins = Array(
                'date' => microtime_float(),
                'action' => 'OpServ',
                'target' => $chan,
                'nick' => $nick,
                'hand' => '',
                'bot' => $this->pIrc->nick,
                'host' => $this->pIrc->n2h($nick),
                'targetb' => '',
                'msg' => "$nick has joined $chan"
            );
            $this->gM('logs')->log('channel', $logins);
        }
    //Handle us joining, create chan in array
        if($nick == $this->pIrc->currentNick()) {
            $this->chans[strtolower($chan)] = Array(
                'name' => $chan,
                'modes' => Array(),
                'nicks' => Array(),
                'topic' => null,
                'topicTime' => null,
                'topicBy' => null,
                'bans' => Array(),
                'createTime' => null
            );
            if(array_key_exists(strtolower($chan), $this->dChans)) {
                $this->dChans[strtolower($chan)]['rejoinTrys'] = 0;
            }
            $oj = $this->gM('SetReg')->getCSet('channel', $chan, 'onjoin');
            if($oj != '*') {
                $this->pIrc->raw($oj);
            }
        } else {
            //Do channel greetings
            //add an ignore list for greetings OR keep track of hosts we've greeted and don't flood them

            $g = $this->gM('SetReg')->getCSet('channel', $chan, 'greeting');
            $gt = $this->gM('SetReg')->getCSet('channel', $chan, 'gtype');
            if($g != '*' && $g != '') {
                if($gt == 'notice') {
                    $this->pIrc->notice($nick, "[$chan] $g");
                }
                if($gt == 'pm') {
                    $this->pIrc->msg($nick, "[$chan] $g");
                }
                if($gt == 'chan') {
                    $this->pIrc->msg($chan, $g);
                }
            }
            $g = $this->gM('SetReg')->getCSet('channel', $chan, 'greeting2');
            if($g != '*' && $g != '') {
                if($gt == 'notice') {
                    $this->pIrc->notice($nick, "[$chan] $g");
                }
                if($gt == 'pm') {
                    $this->pIrc->msg($nick, "[$chan] $g");
                }
                if($gt == 'chan') {
                    $this->pIrc->msg($chan, $g);
                }
            }

        }
        $this->chans[strtolower($chan)]['nicks'][$nick] = Array();
    }

    function h_354($line) {
    //custom who reply
    //:server 354 ourname customnum channel ident host nick flags
        $arg = explode(' ', $line);
        if($arg[3] != 777) {
            return; // wasn't our number
        }
        //process the rest of their channel mode (@+)
        //4 - chan 7 - nick 8 - mode
        $mode = Array();
        if(cisin($arg[8], '@')) $mode['@'] = '@';
        if(cisin($arg[8], '+')) $mode['+'] = '+';
        $this->chans[strtolower($arg[4])]['nicks'][$arg[7]] = $mode;
    }
    function h_part($nick, $chan, $text) {
        if(array_key_exists($nick, $this->chans[strtolower($chan)]['nicks'])) {
            unset($this->chans[strtolower($chan)]['nicks'][$nick]);
        }
        if($nick == $this->pIrc->currentNick()) {
            unset($this->chans[strtolower($chan)]);
        }
        if($nick == 'ChanServ' && $text != 'Going off-channel.') {
            $this->suspend(strtolower($chan), "AutoSuspend ChanServ has parted ($text)", $this->pIrc->nick);
        }
    }
    
    function h_kick($by, $chan, $who, $text) {
    //Handle us getting kicked, clear our array
    //rejoin can be handled by checking if we are in channels we should be in on timer
        if($who == $this->pIrc->currentNick()) {
            $nick = $by;
            unset($this->chans[strtolower($chan)]);
            $hand = $this->gM('user')->byNick($nick);
            $host = $this->pIrc->n2h($nick);
            $logins = Array(
                'date' => microtime_float(),
                'action' => 'kick',
                'target' => $chan,
                'nick' => $nick,
                'hand' => $hand,
                'bot' => $this->pIrc->nick,
                'host' => $host,
                'targetb' => $who,
                'msg' => $text
            );
            $this->gM('logs')->log('channel', $logins);
            $this->pIrc->raw("JOIN $chan");
            return;
        }
        if(array_key_exists($who, $this->chans[strtolower($chan)]['nicks'])) {
            unset($this->chans[strtolower($chan)]['nicks'][$who]);
        }
    }

    function h_quit($nick, $text) {
        foreach($this->chans as &$chan) {
            if(array_key_exists($nick, $chan['nicks'])) {
                unset($chan['nicks'][$nick]);
            }
        }
    }

    function h_topicChange($nick, $chan, $topic) {
        $this->chans[strtolower($chan)]['topic'] = $topic;
        $this->chans[strtolower($chan)]['topicBy'] = $nick;
        $this->chans[strtolower($chan)]['topicTime'] = time();
    }

    function h_gotTopic($line) {
    //split line by spaces
        $arg = explode(' ', $line);
        //get line after :
        $text = arg_range($arg, 4, -1);
        if($text{0} == ':') {
            $text = substr($text, 1);
        }

        //:TechConnect.NL.EU.GameSurge.net 332 knivey #bots :[ Welcome blahblah..
        $chan = $arg[3];
        $nick = $arg[2];
        $this->chans[strtolower($chan)]['topic'] = $text;
    }

    function h_gotTopicTime($line) {
    //:TechConnect.NL.EU.GameSurge.net 333 knivey #bots TechConnect.NL.EU.GameSurge.ne 1247835427
        $arg = explode(' ', $line);
        $chan = $arg[3];
        $this->chans[strtolower($chan)]['topicBy'] = $arg[4];
        $this->chans[strtolower($chan)]['topicTime'] = $arg[5];
    }

    function h_noTopic($line) {
    //:clone.GameSurge.net 331 GothLinux #botstaff :No topic is set.
    //just ignore it i guess
    }

    function h_329($line) {
    //:Prothid.CA.US.GameSurge.net 329 knivey #bots-dev 1139714918
        $arg = explode(' ', $line);
        $this->chans[strtolower($arg[3])]['createTime'] = $arg[4];
    }

    function h_324($line) {
        //:TechConnect.NL.EU.GameSurge.net 324 knivey #bots +tnCzl 22
        //reply to /mode #chan
        $arg = explode(' ', $line);
        $chan = strtolower($arg[3]);
        if(isset($arg[4])) {
            $mode_arg = explode(' ', arg_range($arg, 5, -1));
        }
        while($arg[4] != '') {
            switch($arg[4]{0}) { // handle modes with args
                case '+':
                    break;
                case 'l':
                case 'k':
                    $this->chans[$chan]['modes'][$arg[4]{0}] = array_shift($mode_arg);
                    break;
                default:
                    $this->chans[$chan]['modes'][$arg[4]{0}] = NULL;
            }
            $arg[4] = substr($arg[4], 1);
        }
    }
    
    function h_modeAdd($nick, $chan, $mode, $arg) {
        $chan = strtolower($chan);
        $this->chans[$chan]['modes'][$mode] = $arg;
        if ($mode == 'i' || $mode == 'k' || $mode == 'z') {
            $logins = Array(
                'date' => microtime_float(),
                'action' => 'mode',
                'target' => $chan,
                'nick' => $nick,
                'hand' => $this->gM('user')->byNick($nick),
                'bot' => $this->pIrc->nick,
                'host' => $this->pIrc->n2h($nick),
                'targetb' => '',
                'msg' => trim("+$mode $arg")
            );
            $this->gM('logs')->log('channel', $logins);
        }
    }
    
    function h_modeDel($nick, $chan, $mode, $arg) {
        $chan = strtolower($chan);
        unset($this->chans[$chan]['modes'][$mode]);
        if ($mode == 'i' || $mode == 'k' || $mode == 'z') {
            $logins = Array(
                'date' => microtime_float(),
                'action' => 'mode',
                'target' => $chan,
                'nick' => $nick,
                'hand' => $this->gM('user')->byNick($nick),
                'bot' => $this->pIrc->nick,
                'host' => $this->pIrc->n2h($nick),
                'targetb' => '',
                'msg' => trim("-$mode $arg")
            );
            $this->gM('logs')->log('channel', $logins);
        }
        if ($mode == 'z') {
            $this->suspend($chan, 'AutoSuspend for mode -z', $this->pIrc->nick);
        }
    }

    function failJoin($chan, $msg) {
         $logins = Array(
            'date' => microtime_float(),
            'action' => 'failedjoin',
            'target' => $chan,
            'nick' => '',
            'hand' => '',
            'bot' => $this->pIrc->nick,
            'host' => '',
            'targetb' => '',
            'msg' => $msg
        );
        $this->gM('logs')->log('channel', $logins);
        if (array_key_exists($chan, $this->dChans)) {
            $this->dChans[$chan]['rejoinTrys']++;
            if ($this->dChans[$chan]['rejoinTrys'] > 2) {
                $this->suspend($chan, 'AutoSuspend for failure to join', $this->pIrc->nick);
            }
        }
    }
    
    //:bots.phuzion.net 473 knivey #scorebots :Cannot join channel (+i)
    function h_473($line) {
        list($argc, $argv) = niceArgs($line);
        $chan = strtolower($argv[3]);
        $msg = substr(arg_range($argv, 4, -1), 1);
        if(!array_key_exists($chan, $this->dChans)) {
            return;
        }
        $this->failJoin($chan, $msg);
    }
    
    //:bots.phuzion.net 475 knivey #scorebots :Cannot join channel (+k)
    function h_475($line) {
        list($argc, $argv) = niceArgs($line);
        $chan = strtolower($argv[3]);
        $msg = substr(arg_range($argv, 4, -1), 1);
        if(!array_key_exists($chan, $this->dChans)) {
            return;
        }
        $this->failJoin($chan, $msg);
    }
    
    //:Prothid.CA.US.GameSurge.net 471 kNiVeY- #bots-dev :Cannot join channel (+l)
    function h_471($line) {
        list($argc, $argv) = niceArgs($line);
        $chan = strtolower($argv[3]);
        $msg = substr(arg_range($argv, 4, -1), 1);
        if(!array_key_exists($chan, $this->dChans)) {
            return;
        }
        $this->failJoin($chan, $msg);
    }
    
    //:Prothid.CA.US.GameSurge.net 474 BotInquisitor #PandaBears :Cannot join channel (+b)
    function h_474($line) {
        list($argc, $argv) = niceArgs($line);
        $chan = strtolower($argv[3]);
        $msg = substr(arg_range($argv, 4, -1), 1);
        if(!array_key_exists($chan, $this->dChans)) {
            return;
        }
        $this->failJoin($chan, $msg);
    }
    
    function h_367($line) {
        //bans = Array('mask' => array('by', 'time')
        //:clone.GameSurge.net 367 GothLinux #botstaff asdf!*@* * 1212526587
        $arg = explode(' ', $line);
        $chan = strtolower($arg[3]);
        $this->chans[$chan]['bans'][$arg[4]] = Array('by' => $arg[5], 'time' => $arg[6]);
    }

    function h_ban($nick, $chan, $mask) {
        $this->chans[strtolower($chan)]['bans'][$mask] = Array('by' => $nick, 'time' => time());
        if (pmatch($mask, $this->pIrc->currentNick() . '!' . $this->pIrc->n2h($this->pIrc->currentNick()))) {
            $logins = Array(
                'date' => microtime_float(),
                'action' => 'ban',
                'target' => $chan,
                'nick' => $nick,
                'hand' => $this->gM('user')->byNick($nick),
                'bot' => $this->pIrc->nick,
                'host' => $this->pIrc->n2h($nick),
                'targetb' => $mask,
                'msg' => $mask
            );
            $this->gM('logs')->log('channel', $logins);
        }
    }

    function h_unban($nick, $chan, $mask) {
        unset($this->chans[strtolower($chan)]['bans'][$mask]);
        if (pmatch($mask, $this->pIrc->currentNick() . '!' . $this->pIrc->n2h($this->pIrc->currentNick()))) {
            $logins = Array(
                'date' => microtime_float(),
                'action' => 'unban',
                'target' => $chan,
                'nick' => $nick,
                'hand' => $this->gM('user')->byNick($nick),
                'bot' => $this->pIrc->nick,
                'host' => $this->pIrc->n2h($nick),
                'targetb' => $mask,
                'msg' => $mask
            );
            $this->gM('logs')->log('channel', $logins);
        }
    }

    function h_op($nick, $chan, $who) {
        $this->chans[strtolower($chan)]['nicks'][$who]['@'] = '@';
    }

    function h_deop($nick, $chan, $who) {
        unset($this->chans[strtolower($chan)]['nicks'][$who]['@']);
        if (strtolower($who) == strtolower($this->pIrc->currentNick())) {
            $by = $this->gM('user')->byNick($nick);
            $host = $this->pIrc->n2h($nick);
            $logins = Array(
                'date' => microtime_float(),
                'action' => 'deop',
                'target' => $chan,
                'nick' => $nick,
                'hand' => $by,
                'bot' => $this->pIrc->nick,
                'host' => $host,
                'targetb' => $who,
                'msg' => ''
            );
            $this->gM('logs')->log('channel', $logins);
        }
    }

    function h_voice($nick, $chan, $who) {
        $this->chans[strtolower($chan)]['nicks'][$who]['+'] = '+';
    }

    function h_devoice($nick, $chan, $who) {
        unset($this->chans[strtolower($chan)]['nicks'][$who]['+']);
    }

    function h_authed() {
    //Our bot got authed, lets make it join some channels

        $chans = $this->botChannels($this->pIrc->nick);
        var_dump($chans);
        $jchans = Array();
        foreach($chans as $c => $a) {
            if($a) {
                $jchans[] = $c;
            }
            $this->loadChan($c);
        }
        $jchans = implode(',', $jchans);
        $this->pIrc->raw("JOIN $jchans");
    }

    /*
     * Our commands and stuff
     */
    
    function cmd_names($nick, $chan, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $chan = strtolower($chan); //Later on we might change this command for use via PM
        if($arg[0] == '') {
            $arg[0] = $chan;
	}
        $c = strtolower($arg[0]);
        if(!$this->chanExists($c)) {
            $boc = $this->botsOnChan($c);
            if(!empty($boc)) {
                //just send to all in case one isn't one and chan has more then one bot
                $this->gM('xnet')->sendToAll($this, 'namesCB', 'names', Array($c), Array($c, $nick));
                return $this->OK;
            }
            $this->pIrc->notice($nick, 'That channel not registered to any bots.');
            return $this->ERROR;
        }
        if($this->getSet($c, 'channel', 'suspend') != null) {
            $s = $this->getSet($c, 'channel', 'suspend');
            $this->pIrc->notice($nick, "$c is suspended by $s[by] on " . strftime('%D %T', $s['date'] . " for $s[reason]"));
            return $this->OK;
        }
        $names = '';
        foreach($this->chans[$c]['nicks'] as $n => $m) {
            $h = $this->gM('user')->byNick($n);
            if($h != null) {
                $a = $this->gM('user')->access($h, $c);
                if ($a > 0) {
                    $names .= "$a:$n($h) ";
                }
            }
        }
        if($names == '') {
            $this->pIrc->notice($nick, "No users currently in $c");
        } else {
            $this->pIrc->notice($nick, "Users in \2$c\2: $names");
        }
        return $this->OK;
    }
    
    function namesCB($data, $ex) {
        list($c, $nick) = $ex;
        $out = "No bots replied for $c names";
        foreach($data as $d) {
            if(!array_key_exists('error', $d)) {
                if(array_key_exists('ok', $d['resp'])) {
                    $this->pIrc->notice($nick, $d['resp']['ok']);
                    return;
                } else {
                    if(array_key_exists('suspended', $d['resp'])) {
                        $out = $d['resp']['suspended'];
                    }
                }
            }
        }
        $this->pIrc->notice($nick, $out);
    }
    
    function rpc_names($p) {
        list($c) = $p;
        if(!$this->chanExists($c)) {
            return Array('notfound' => 'Channel lookup error :[');
        }
        if($this->getSet($c, 'channel', 'suspend') != null) {
            $s = $this->getSet($c, 'channel', 'suspend');
            return Array('suspended' => "$c is suspended by $s[by] on " . strftime('%D %T', $s['date']) . " for $s[reason]");
        }
        $names = '';
        foreach($this->chans[$c]['nicks'] as $n => $m) {
            $h = $this->gM('user')->byNick($n);
            if($h != null) {
                $a = $this->gM('user')->access($h, $c);
                if ($a > 0) {
                    $names .= "$a:$n($h) ";
                }
            }
        }
        if($names == '') {
            return Array('ok' => "No users currently in $c");
        } else {
            return Array('ok' => "Users in \2$c\2: $names");
        }
    }

     //TODO idlers group clones in () /maybe later highlight people matching certain hosts (bots etc)
    function cmd_peek($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
            return $this->gM('CmdReg')->rV['BADARGS'];
	}
        $c = strtolower($arg[0]);
        if(!$this->chanExists($c)) {
            $this->pIrc->notice($nick, 'That channel not registered to this bot.');
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        if($this->getSet($c, 'channel', 'suspend') != null) {
            $s = $this->getSet($c, 'channel', 'suspend');
            $this->pIrc->notice($nick, "$c is suspended by $s[by] on " . strftime('%D %T', $s['date'] . " for $s[reason]"));
            return $this->gM('CmdReg')->rV['OK'];
        }
        //not sure what this was for i guess return info to see if its right
        $nicks = '';
        foreach($this->chans[$c]['nicks'] as $n => $m) {
            $nicks .= implode('', $m) . "$n ";
        }
        $nicks = trim($nicks);
        $modesA = '';
        $modesB = '';
        foreach($this->chans[$c]['modes'] as $n => $m) {
            $modesA .= $n;
            if($m != null) {
                $modesB .= "$m ";
            }
        }
        $modes = "$modesA $modesB";
        $this->pIrc->notice($nick, "$c (" . strftime('%D %T', $this->chans[$c]['createTime']) . ") has " . count($this->chans[$c]['nicks']) . " idlers");
        $this->pIrc->notice($nick, "Idlers: $nicks");
        $this->pIrc->notice($nick, "Modes: $modes Topic: " . $this->chans[$c]['topic'] . " Set " . strftime('%D %T', $this->chans[$c]['topicTime']) . " by " . $this->chans[$c]['topicBy']);
        $this->pIrc->notice($nick, "Channel Trigger: "  . $this->getTrig($c) . " Registered by: " . $this->getSet($c, 'channel', 'registar') . " On: " . strftime('%D %T', $this->getSet($c, 'channel', 'regged')) . "");
        return $this->gM('CmdReg')->rV['OK'];
    }

    function cmd_addchan($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0]) || empty($arg[1])) {
            return $this->gM('CmdReg')->rV['BADARGS'];
        }
        //These might already be on DNR but I like to try and be safe
        if($arg[0]{0} != '#' || strpos(',', $arg[0]) !== FALSE) {
            return $this->gM('CmdReg')->rV['BADARGS'];
        }
        if($this->chanExists($arg[0])) {
            $this->pIrc->notice($nick, 'That channel is already registered to this bot.');
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $h = "cmd_addchan";
        $arg[1] = $this->gM('user')->na_arg($arg[1], $nick, $h);
        if($arg[1] == null) {
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $addchan = $this->addChan($arg[0], $arg[1], $hand);
        if($addchan != null) {
            if($addchan == 1) {
                $this->pIrc->notice($nick, 'That channel is already added.');
                return $this->gM('CmdReg')->rV['ERROR'];
            }
            if($addchan == 2) {
                $this->pIrc->notice($nick, 'That user doesn\'t exist.');
                return $this->gM('CmdReg')->rV['ERROR'];
            }
            $this->pIrc->notice($nick, $addchan);
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $this->pIrc->notice($nick, 'Channel added.');
        return $this->gM('CmdReg')->rV['OK'];
    }

    function cmd_delchan($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0]) || empty($arg[1])) {
		return $this->gM('CmdReg')->rV['BADARGS'];
	}
        if(!$this->chanExists($arg[0])) {
            $this->pIrc->notice($nick, 'That channel isn\'t registered to this bot.');
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $delchan = $this->delChan($arg[0], $hand, arg_range($arg, 1, -1));
        if($delchan != null) {
            $this->pIrc->notice($nick, 'That channel has nodelete set: ' . $delchan);
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $this->pIrc->notice($nick, 'Channel removed.');
        return $this->gM('CmdReg')->rV['OK'];
    }

    function cmd_suspend($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0]) || empty($arg[1])) {
		return $this->gM('CmdReg')->rV['BADARGS'];
	}
        if(!$this->chanExists($arg[0])) {
            $this->pIrc->notice($nick, 'That channel isn\'t registered to this bot.');
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $suspend = $this->suspend($arg[0], arg_range($arg, 1, -1), $hand);
        if($suspend != null) {
            if($suspend == 2) {
                $this->pIrc->notice($nick, 'That channel has already been suspended.');
                return $this->gM('CmdReg')->rV['ERROR'];
            }
            $this->pIrc->notice($nick, 'That channel has nodelete set: ' . $suspend);
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $this->pIrc->notice($nick, 'Channel suspended.');
        return $this->gM('CmdReg')->rV['OK'];
    }

    function cmd_unsuspend($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
		return $this->gM('CmdReg')->rV['BADARGS'];
	}
        if(!$this->chanExists($arg[0])) {
            $this->pIrc->notice($nick, 'That channel isn\'t registered to this bot.');
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $unsuspend = $this->unsuspend($arg[0], $hand);
        if($unsuspend != null) {
            if($unsuspend == 2) {
                $this->pIrc->notice($nick, 'That channel has not been suspended.');
                return $this->gM('CmdReg')->rV['ERROR'];
            }
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $this->pIrc->notice($nick, 'Channel unsuspended.');
        return $this->gM('CmdReg')->rV['OK'];
    }

    function cmd_channels($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $ch = strtolower($target); //Later on we might change this command for use via PM

        try {
            $stmt = $this->pMysql->prepare("SELECT `chans` FROM `bots` WHERE `name` = :nick");
            $stmt->execute(Array(':nick'=>$this->pIrc->nick));
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $chans = explode(' ', $row['chans']);
        $out1 = '';
        $out2 = '';
        $out3 = '';
        $num = 0;
        $snum = 0;
        $fnum = 0;
        foreach ($chans as &$chan) {
            $temp = explode(':', $chan);
            $matches[1] = $temp[0];
            unset($temp[0]);
            $temp = implode(':', $temp);
            $matches[2] = strtolower($temp);
            //preg_match("/([0-9]*)\:(#[ -z]*)/", $chan, $matches);
            if ($this->onChan($matches[2]) || $matches[1] == 0) {
                if ($matches[1] == 1) {
                    
                    //$names = count($this->chans[$matches[2]]['nicks']);
                    $unique = Array();
                    $clones = Array();
                    //later we will add a known bots db
                    //$bots = Array();
                    $achan = $matches[2];
                    $nhs = $this->chanNickHosts($achan);
                    $hosts = Array();
                    foreach ($nhs as $n => $h) {
                        list($i, $h) = explode('@', $h);
                        $hosts[$h][] = $n;
                    }
                    foreach ($hosts as $h => $ns) {
                        if (count($ns) > 1) {
                            $clones[$h] = $ns;
                        } else {
                            $unique[$h] = $ns;
                        }
                    }
                    $idlers = count($unique) + count($clones);
                    //update this v number when we scan bots
                    $names = $idlers - 2; //subtract 2 for chanserv and us
                    if ($names < 5) {
                        $c = chr(31) . $matches[2] . chr(31);
                    } else {
                        $c = $matches[2];
                    }
                    $num = $num + 1;
                    $curNick = $this->pIrc->curNick();
                    if(array_key_exists($curNick, $this->chans[$matches[2]]['nicks']) && is_array($this->chans[$matches[2]]['nicks'][$curNick])) {
                    	if (array_key_exists('@', $this->chans[$matches[2]]['nicks'][$curNick])) {
                        	$out1 .= "\3" . "9,1@" . $c . ' ';
                    	} else {
                        	if (array_key_exists('+', $this->chans[$matches[2]]['nicks'][$curNick])) {
                            	$out1 .= "\3" . "8,1+" . $c . ' ';
                        	} else {
                        		$out1 .= "\3" . "4,1" . $c . ' ';
                    		}
                    	}
                    } else {
                        $out1 .= "\3" . "4,1" . $c . ' ';
                    }
                } else {
                    $out2 .= "\3" . "13,1" . $matches[2] . ' ';
                    $snum = $snum + 1;
                }
            } else {
                $fnum++;
                $out3 .= $matches[2] . ' ';
            }
        }
        $total = $snum + $num;
        if (isset($arg[0]) && $arg[0] == 'total') {
            $irc->notice($nick, "\2Total:\2 $total");
            return;
        }
        $this->pIrc->notice($nick, "\2Total\2: $total \2Key\2: " . "\3" . "9,1Oped " . "\3" . "8,1Voiced " . "\3" . "4,1Not Oped or Voiced " . "\3" . "0,1" . chr(31) . "Lacks Idlers" . chr(31) . " (no color change) " . "\3" . "13,1Suspended");
        $this->pIrc->notice($nick, "\2Channels(\2$num\2)\2: $out1");
        $this->pIrc->notice($nick, "\2Suspended(\2$snum\2)\2: $out2");
        $this->pIrc->notice($nick, "\2Failed to join(\2$fnum\2):\2 $out3");
    }

    /*
     * Next some functions to access data
     */

    function rpc_loadjoin($p) {
        $this->loadChan($p[0]);
        $this->pIrc->raw("JOIN $p[0]");
    }
    
    /**
     * Load channel data from mysql
     * @param <string> $chan Channel to load
     */

    function loadChan($chan) {
        $chan = strtolower($chan);
        try {
            $bnick = $this->mq($this->pIrc->nick);
            $stmt = $this->pMysql->prepare("SELECT * FROM `$bnick` WHERE `name` = :chan");
            $stmt->execute(Array(':chan'=>$chan));
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        //$this->dChans[$chan]['trig'] = $row['trig']; //not used anymore
        $this->dChans[$chan]['settings'] = unserialize($row['settings']);
        $this->dChans[$chan]['rejoinTrys'] = 0;
    }

    /**
     * Save a channels info to mysql
     * @param <type> $chan Channel to save
     */
    function saveChan($chan) {
        $chan = strtolower($chan);
        if(!array_key_exists($chan, $this->dChans)) return;
        try {
            $bnick = $this->mq($this->pIrc->nick);
            //I think trig col is unused
            //$stmta = $this->pMysql->prepare("UPDATE `$bnick` SET `trig` = :trig WHERE `name` = :chan");
            //$stmta->execute(Array(':trig'=>$this->dChans[$chan]['trig'],':chan'=>$chan));
            //$stmta->closeCursor();
            $stmtb = $this->pMysql->prepare("UPDATE `$bnick` SET `settings` = :sets WHERE `name` = :chan");
            $stmtb->execute(Array(':sets'=>  serialize($this->dChans[$chan]['settings']),':chan'=>$chan));
            $stmtb->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    /**
     * Change a setting for a channel or add new setting
     * @param <string> $chan Channel to change setting
     * @param <string> $mod Module setting is under
     * @param <string> $name Name of setting
     * @param <any> $val Setting info
     */
    function chgSet($chan, $mod, $name, $val) {
        $chan = strtolower($chan);
        if(!$this->chanExists($chan)) return;
        $this->dChans[$chan]['settings'][$mod][$name] = $val;
        $this->saveChan($chan);
    }

    /**
     * Return a setting for $chan
     * @param <type> $chan Channel
     * @param <type> $mod Module setting is under
     * @param <type> $name Name of setting
     */
    function getSet($chan, $mod, $name) {
        $chan = strtolower($chan);
        if(!$this->chanExists($chan)) return;
        if(array_key_exists($mod, $this->dChans[$chan]['settings'])
                && array_key_exists($name, $this->dChans[$chan]['settings'][$mod])) {
            return $this->dChans[$chan]['settings'][$mod][$name];
        } else {
            return;
        }
    }

    function getTrig($chan) {
        $chan = strtolower($chan);
        if(!$this->chanExists($chan)) return;
        return $this->gM('SetReg')->getCSet('channel', $chan, 'trig');
    }
    
    function setTrig($chan, $trig) {
        $chan = strtolower($chan);
        if(!$this->chanExists($chan)) return;
        $this->gM('SetReg')->cSet('channel', $chan, 'trig', $trig{0});
    }

    function chanExists($chan) {
        $chan = strtolower($chan);
        if(!array_key_exists($chan, $this->dChans)) return FALSE; else return TRUE;
    }

    function addNote($chan, $by, $note) {

    }

    function getNotes($chan, $amt = 3, $page = 0) {

    }

    function expirednrs() {
        try {
            $stmt = $this->pMysql->prepare("DELETE FROM `dnr` WHERE `id` = :id");
            foreach ($this->pMysql->query("SELECT * FROM `dnr`") as $row) {
                if ($row['expires'] != 0 && $row['expires'] < time()) {
                    $stmt->execute(Array(':id'=>$row['id']));
                    $stmt->closeCursor();
                }
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function isdnr($mask) {
	$this->expirednrs();
        try {
            foreach ($this->pMysql->query("SELECT * FROM `dnr`") as $row) {
                if (pmatch($row['mask'], $mask)) {
                    return $row;
                }
            }
            return false;
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function isNodelete($chan) {
        $nd = $this->gM('SetReg')->getCSet('channel', $chan, 'nodelete');
        if($nd != 'off') {
            return $nd;
        }
        return;
    }

    function suspend($chan, $reason, $by) {
        $chan = strtolower($chan);
        if(!$this->chanExists($chan)) {
            return 1;
        }
        $nd = $this->isNodelete($chan);
        if($nd) {
            return $nd;
        }
        if($this->getSet($chan, 'channel', 'suspend') != null) {
            return 2;
        }
        $this->chgSet($chan, 'channel', 'suspend', Array('reason' => $reason, 'by' => $by, 'date' => time()));
        try {
            $stmt = $this->pMysql->prepare("SELECT `chans` FROM `bots` WHERE `name` = :nick");
            $stmt->execute(Array(':nick'=>$this->pIrc->nick));
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
	$chans = explode(' ', $row['chans']);
	$newchans = Array();
	foreach($chans as &$ch) { // This whole thing needs cleaned up - knives
		$ch = explode(':', $ch);
                $join = $ch[0];
		unset($ch[0]);
		$ch = implode(':', $ch);
		$ch = strtolower($ch);
		//preg_match("/([0-9]*)\:(#[ -z]*)/", $chan, $matches);
		if($chan != $ch) {
                    array_push($newchans, "$join:$ch");
		} else {
                    array_push($newchans, "0:$ch");
                }
        }
        $newchans = implode(' ', $newchans);
        try {
            $stmt = $this->pMysql->prepare("UPDATE `bots` SET `chans` = :newchans WHERE `name` = :nick");
            $stmt->execute(Array(':nick'=>$this->pIrc->nick,':newchans'=>$newchans));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->pMM->sendSignal('suspend', 'channel', array('chan' => $chan, 'by' => $by, 'reason' => $reason));
        $this->pIrc->raw("PART $chan :suspended by $by ($reason)");
        $logins = Array(
            'date' => microtime_float(),
            'action' => 'suspend',
            'target' => $chan,
            'nick' => '',
            'hand' => $by,
            'bot' => $this->pIrc->nick,
            'host' => '',
            'targetb' => '',
            'msg' => $reason,
        );
        $this->gM('logs')->log('channel', $logins);
        return;
    }

    function unsuspend($chan, $by) {
        $chan = strtolower($chan);
        if(!$this->chanExists($chan)) return 1;
        if($this->getSet($chan, 'channel', 'suspend') == null) {
            return 2;
        }
        $this->chgSet($chan, 'channel', 'suspend', NULL);
        $this->dChans[$chan]['rejoinTrys'] = 0;
        try {
            $stmt = $this->pMysql->prepare("SELECT `chans` FROM `bots` WHERE `name` = :nick");
            $stmt->execute(Array(':nick'=>$this->pIrc->nick));
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
	$chans = explode(' ', $row['chans']);
	$newchans = Array();
	foreach($chans as &$ch) { // This whole thing needs cleaned up - knives
		$ch = explode(':', $ch);
                $join = $ch[0];
		unset($ch[0]);
		$ch = implode(':', $ch);
		$ch = strtolower($ch);
		//preg_match("/([0-9]*)\:(#[ -z]*)/", $chan, $matches);
		if($chan != $ch) {
                    array_push($newchans, "$join:$ch");
		} else {
                    array_push($newchans, "1:$ch");
                }
        }
        $newchans = implode(' ', $newchans);
        try {
            $stmt = $this->pMysql->prepare("UPDATE `bots` SET `chans` = :newchans WHERE `name` = :nick");
            $stmt->execute(Array(':nick'=>$this->pIrc->nick,':newchans'=>$newchans));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->pMM->sendSignal('unsuspend', 'channel', array('chan' => $chan, 'by' => $by));
        $this->pIrc->raw("JOIN $chan");
        $logins = Array(
            'date' => microtime_float(),
            'action' => 'unsuspend',
            'target' => $chan,
            'nick' => '',
            'hand' => $by,
            'bot' => $this->pIrc->nick,
            'host' => '',
            'targetb' => '',
            'msg' => ''
        );
        $this->gM('logs')->log('channel', $logins);
    }

    function addChan($chan, $owner, $by) {
        $chan = strtolower($chan);
        if($this->chanExists($chan)) return 1;
        if(!$this->gM('user')->hand_exists($owner)) return 2;
        $dnr = $this->isdnr($chan);
	if($dnr) {
		$dnr['date'] = strftime('%I:%M%p - %D', $dnr['date']);
		if($dnr['expires'] == 0) {
			$dnr['expires'] = 'Never.';
		} else {
			$dnr['expires'] = strftime('%I:%M%p - %D', $dnr['expires']);
		}
		return "That channel name conflicts with DNR $dnr[mask] set by $dnr[who] on $dnr[date] expiring on $dnr[expires] for the reason: $dnr[reason]";
        }
        try {
            $bnick = $this->mq($this->pIrc->nick);
            $stmt = $this->pMysql->prepare("INSERT INTO `$bnick` (name,settings) VALUES(:chan, 'a:0:{}')");
            $stmt->execute(Array(':chan'=>$chan));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->dChans[$chan] = Array('trig' => '.', 'settings' => Array(
                'channel' => Array(
                    'registar' => $by,
                    'regged' => time(),
                    'suspend' => NULL
                    )),
            'rejoinTrys' => 0
        );
        $this->saveChan($chan);
        $this->gM('user')->setAccess($chan, $owner, 5);
        try {
            $stmt = $this->pMysql->prepare("SELECT `chans` FROM `bots` WHERE `name` = :nick");
            $stmt->execute(Array(':nick'=>$this->pIrc->nick));
            $row = $stmt->fetch();
            $stmt->closeCursor();
            $chans = trim(trim($row['chans']) . " 1:$chan");
            $stmt = $this->pMysql->prepare("UPDATE `bots` SET `chans` = :chans WHERE `name` = :nick");
            $stmt->execute(Array(':nick'=>$this->pIrc->nick,':chans'=>$chans));
            $stmt->closeCursor();
        }  catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        //Throw a signal letting other modules know to initialise new channel
        $this->pMM->sendSignal('addchan', 'channel', array('chan' => $chan, 'owner' => $owner, 'by' => $by));
        $this->pIrc->raw("JOIN $chan");
        $logins = Array(
            'date' => microtime_float(),
            'action' => 'addchan',
            'target' => $chan,
            'nick' => '',
            'hand' => $by,
            'bot' => $this->pIrc->nick,
            'host' => '',
            'targetb' => $owner,
            'msg' => ''
        );
        $this->gM('logs')->log('channel', $logins);
        return;
    }
    
    function rpc_delchan($p) {
        return $this->delChan($p[0], $p[1], $p[2], true);
    }
    
    function rpc_addchan($p) {
        return $this->addChan($p[0], $p[1], $p[2]);
    }
    
    function rpc_globalmsg($p) {
        list($from, $msg) = $p;
        $this->globalMsg("\2GlobalMsg\2 from \2$from:\2 $msg");
    }

    function delChan($chan, $by, $reason, $moved = false) {
        $chan = strtolower($chan);
        if(!$this->chanExists($chan)) return 1;
        $nd = $this->isNodelete($chan);
        if($nd && !$moved) return $nd;
        unset($this->dChans[$chan]);
        try {
            $bnick = $this->mq($this->pIrc->nick);
            $stmt = $this->pMysql->prepare("DELETE FROM `$bnick` WHERE `name` = :chan");
            $stmt->execute(Array(':chan'=>$chan));
            $stmt->closeCursor();
            $stmt = $this->pMysql->prepare("SELECT `chans` FROM `bots` WHERE `name` = :nick");
            $stmt->execute(Array(':nick'=>$this->pIrc->nick));
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
	$chans = explode(' ', $row['chans']);
	$newchans = Array();
	foreach($chans as &$ch) {
		$ch = explode(':', $ch);
                $join = $ch[0];
		unset($ch[0]);
		$ch = implode(':', $ch);
		$ch = strtolower($ch);
		//preg_match("/([0-9]*)\:(#[ -z]*)/", $chan, $matches);
		if($chan != $ch) {
			array_push($newchans, "$join:$ch");
		}
        }
        $newchans = implode(' ', $newchans);
        try {
            $stmt = $this->pMysql->prepare("UPDATE `bots` SET `chans` = :chans WHERE `name` = :nick");
            $stmt->execute(Array(':nick'=>$this->pIrc->nick,':chans'=>$newchans));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->pMM->sendSignal('delchan', 'channel', array('chan' => $chan, 'by' => $by, 'reason' => $reason));
        $this->gM('user')->delchan($chan);
        if (!$moved) {
            $this->pIrc->raw("PART $chan :delchan by $by ($reason)");
            $logins = Array(
                'date' => microtime_float(),
                'action' => 'delchan',
                'target' => $chan,
                'nick' => '',
                'hand' => $by,
                'bot' => $this->pIrc->nick,
                'host' => '',
                'targetb' => '',
                'msg' => $reason
            );
            $this->gM('logs')->log('channel', $logins);
        } else {
            $this->pIrc->raw("PART $chan :switchbot by $by to $reason");
            $logins = Array(
                'date' => microtime_float(),
                'action' => 'switchbot',
                'target' => $chan,
                'nick' => '',
                'hand' => $by,
                'bot' => $this->pIrc->nick,
                'host' => '',
                'targetb' => $reason,
                'msg' => ''
            );
            $this->gM('logs')->log('channel', $logins);
        }
        return;
    }

    /**
     * not sure how we want the user cmd to do global but heres the function to do it
     *
     * if we ever get botserver back or super long channel names might wanna mod this
     * to handle such long targets
     */
    function globalMsg($msg) {
        $target = Array();
        foreach ($this->chans as $c => $d) {
            if($this->gM('SetReg')->getCSet('channel', $c, 'globalmsg') == 'on') {
                $target[] = $c;
            }
        }
        $target = implode(',', $target);
        $this->pIrc->msg($target, $msg,true,true);
    }

    /**
     * Return If our bot is on said channel
     * @param <string> $chan Channel to check
     */
    function onChan($chan) {
        if(array_key_exists(strtolower($chan), $this->chans)) {
            return true;
        } else {
            return false;
        }
    }
    
    function joinedChans() {
        return array_keys($this->chans);
    }
    
    function hasMode($chan, $mode) {
        $chan = strtolower($chan);
        if(!$this->onChan($chan)) {
            return false;
        }
        if(array_key_exists($mode, $this->chans[$chan]['modes'])) {
            return true;
        }
        return false;
    }
    
    function chanNickHosts($chan) {
        if(!$this->onChan($chan)) {
            return Array();
        }
        $out = Array();
        foreach($this->chans[strtolower($chan)]['nicks'] as $n => $m) {
            $out[$n] = $this->pIrc->n2h($n);
        }
        return $out;
    }
    
    function h_nick($oldnick, $newnick) {
        foreach(array_keys($this->chans) as $c) {
            if(array_key_exists($oldnick, $this->chans[$c]['nicks'])) {
                $this->chans[$c]['nicks'][$newnick] = $this->chans[$c]['nicks'][$oldnick];
                unset($this->chans[$c]['nicks'][$oldnick]);
            }
        }
    }

    /**
     * Return an array of bots assigned to a channel
     * @param <string> $chan
     */
    function botsOnChan($channel) {
        $channel = strtolower($channel);
        try {
            $stmt = $this->pMysql->query("SELECT name,chans FROM bots");
            $bots = $stmt->fetchAll();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $out = Array();
        foreach ($bots as $row) {
            $chans = explode(" ", $row['chans']);
            foreach ($chans as &$chan) {
                $chan = explode(':', $chan);
                $active = $chan[0];
                unset($chan[0]);
                $chan = strtolower(implode(':', $chan));
                if($channel == $chan) {
                    $out[] = $row['name'];
                }
            }
        }
        return $out;
    }

    /**
     * Return channels for specified bot as an Array ['channel'] => active?
     * @param <string> $bot Name of bot
     */
    function botChannels($bot) {
        try {
            $stmt = $this->pMysql->prepare("SELECT `chans` FROM `bots` WHERE `name` = :bot");
            $stmt->execute(Array(':bot'=>$bot));
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $out = Array();
        $chans = explode(" ", $row['chans']);
        foreach($chans as &$chan) {
            $chan = explode(':', $chan);
            $active = $chan[0];
            unset($chan[0]);
            $chan = strtolower(implode(':', $chan));
            $out[$chan] = $active;
        }
        return $out;
    }
}

?>
