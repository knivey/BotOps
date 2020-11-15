<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';

class admin extends Module {
    function cmd_dnr($nick, $chan, $msg) {
        /*
         * if dnr is #* then chan else account
         * will delete chans|accounts matching
         * will show error if more then 1 chan|account is added matching
         * use FORCE to overide
         * will not delete staff accounts or nodelete chans
         */
        
    }
    
    function cmd_undnr($nick, $chan, $msg) {
        
    }
    
    function cmd_dnrsearch($nick, $chan, $msg) {
        
    }
    
    function rpc_loadfilters($p) {
        try {
            $filters = Array();
            foreach($this->pMysql->query("SELECT * FROM `filters`") as $row) {
                $filters[$row['id']] = $row;
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->pIrc->ircFilters->loadFilters($filters);
    }
    
    function cmd_addfilter(CmdRequest $r) {
        $stmt = $this->pMysql->prepare("INSERT INTO `filters` (`made`,`who`,`text`,`caught`)".
                " VALUES(:time,:user,:msg,0)");
        $stmt->bindValue(':time', time());
        $stmt->bindValue(':user', $r->account);
        $stmt->bindValue(':msg', $r->args['mask']);
        $stmt->execute();
        $id = $this->pMysql->lastInsertId();
        $stmt->closeCursor();
        $r->notice("New filter rule has been added, ID: $id");
    }
    
    function cmd_delfilter(CmdRequest $r) {
        $stmt = $this->pMysql->prepare("SELECT * FROM `filters` WHERE `id` = :id");
        $stmt->bindValue(':id', $r->args['id']);
        $stmt->execute();
        $resp = $stmt->fetch();
        $stmt->closeCursor();
        if(!empty($resp) && array_key_exists('text', $resp)) {
            $stmtd = $this->pMysql->prepare("DELETE FROM `filters` WHERE `id` = :id");
            $stmtd->bindValue(':id', $r->args['id']);
            $stmtd->execute();
            $stmtd->closeCursor();
            $this->gM('xnet')->sendToAll(null, null, 'loadfilters', Array());
            $r->notice("Filter ID: {$r->args['id']} is now removed.");
        } else {
            $r->notice("No filters by that id: {$r->args['id']}");
        }
    }
    
    function cmd_listfilters(CmdRequest $r) {
        $r->notice("The filter list can be viewed at: Http://botops.net/filters.php");
    }
    
    function cmd_setbot(CmdRequest $r) {
        $name = $this->botExists($r->args['name']);
        if (!$name) {
            throw new CmdException("Bot {$r->args['name']} doesn't exist in my database!");
        }

        $stmt = $this->pMysql->prepare("SELECT * FROM `bots` WHERE `name` = :name");
        $stmt->bindValue(':name', $name);
        $stmt->execute();
        $results = $stmt->fetch();
        $stmt->closeCursor();
        
        $editable = Array();
        foreach($results as $f => $v) {
            switch($f) {
                case 'id':
                case 'authserv':
                case 'chans':
                case 'pid':
                    continue;
                default:
                    $editable[$f] = $v;
            }
        }
        
        if(!isset($r->args['val'])) {
            //show settings..
            //make a neat table..
            $userline = $editable['userline'];
            unset($editable['userline']);
            $out = Array();
            $c1 = 0;
            $c2 = 0;
            foreach($editable as $f => $v) {
                $out[$c1][$c2] = "\2$f:\2 $v";
                //$out[$c1] = "\2$f:\2 $v";
                $c2++;
                if($c2 == 4) {
                    $c2 = 0;
                    $c1++;
                }
            }
            //$out = multi_array_padding($out);
            foreach($out as &$line) {
                $r->notice(trim(implode(' ', $line)));
            }
            $r->notice("\2userline:\2 $userline");
            return;
        }
        list($argc, $argv) = niceArgs($r->args[$r->args['val']]);
        $wut = strtolower($argv[0]);
        $newval = arg_range($argv, 1, -1);
        if($wut == 'authserv') {
            throw new CmdException("Modification of authserv line from IRC forbidden");
        }
        if(!array_key_exists($wut, $editable)) {
            throw new CmdException("That option doesn't exist or is not editable");
        }
        if($newval == '') {
            $r->notice("\2$wut:\2 $editable[$wut]");
            return;
        }
        //try to change the setting
        if($wut == 'name') {
            //check if the botname is a valid irc nick
            if(!validNick($newval)) {
                throw new CmdException("$newval is not a valid IRC nickname");
            }
            //rename the bot's table
            $qname = $this->mq($name);
            $qnewval = $this->mq($newval);
            $this->pMysql->query("RENAME TABLE `$qname` TO `$qnewval`");

            //If bot is online tell it the new name
            $this->gM('xnet')->sendRPC(null, null, $name, 'rename', Array($newval));
        }

        $wut = $this->mq($wut);
        $stmt = $this->pMysql->prepare("UPDATE `bots` SET `$wut` = :newval WHERE `name` = :name");
        $stmt->bindValue(':newval', $newval);
        $stmt->bindValue(':name', $name);
        $stmt->execute();
        $stmt->closeCursor();
        $r->notice("Setting $wut for $name changed to $newval");
        $r->notice("Botset changes may require the bot to restart.");
    }
    
    public function cmd_switchbot(CmdRequest $r) {
        $oldbot = $this->botExists($r->args['oldbot']);
        $newbot = $this->botExists($r->args['newbot']);
        if (!$oldbot) {
            throw new CmdException("Bot $r->args['oldbot'] doesn't exist in my database!");
        }
        if (!$newbot) {
            throw new CmdException("Bot $r->args['newbot'] doesn't exist in my database!");
        }
        $bots = $this->gM('channel')->botsOnChan($r->chan);
        $bots = array_flip($bots);
        if(count($bots) == 0) {
            throw new CmdException("Channel {$r->chan} doesn't exist in my database!");
        }
        //check if chan exists with oldbot
        $old = get_akey_nc($oldbot, $bots);
        if(!$old) {
            throw new CmdException("Channel {$r->chan} is not registered to $oldbot");
        }
        //check if newbot is already added to chan
        $new = get_akey_nc($newbot, $bots);
        if($new) {
            throw new CmdException("Channel {$r->chan} is already registered to $newbot");
        }

        //copy data from oldbot table to newbot table
        $newb = $this->mq($newbot);
        $oldb = $this->mq($oldbot);
        $stmta = $this->pMysql->prepare("INSERT INTO `$newb` (name,settings,trig) SELECT name,settings,trig FROM `$oldb` WHERE `name` = :chan");
        $stmta->execute(Array(':chan' => strtolower($r->chan)));
        $stmta->closeCursor();
        //update bots table to add to new bot
        $stmtb = $this->pMysql->prepare("SELECT `chans` FROM `bots` WHERE `name` = :name");
        $stmtb->execute(Array(':name' => $newbot));
        $row = $stmtb->fetch();
        $chans = trim(trim($row['chans']) . " 1:" . strtolower($r->chan));
        $stmtb->closeCursor();
        $stmtc = $this->pMysql->prepare("UPDATE `bots` SET `chans` = :chans WHERE `name` = :newbot");
        $stmtc->execute(Array(':newbot' => $newbot, ':chans' => $chans));
        $stmtc->closeCursor();

        //tell oldbot to channel.delchan with switch message
        //   (should send signal for other mods (scorebot)
        //    but not remove access since newbot has chan)
        $this->gM('xnet')->sendRPC($this, 'switchBotCB', $oldbot, 'delchan', Array(strtolower($r->chan),$r->nick,$newbot), Array($oldbot,strtolower($r->chan)));
        //tell newbot to load channel and join
        $this->gM('xnet')->sendRPC(null, null, $newbot, 'loadjoin', Array(strtolower($r->chan)));
        $r->notice("Bots switched!");
    }
    
    public function switchBotCB($d, $extra) {
        list($bot, $chan) = $extra;
        if (array_key_exists('error', $d)) {
            //remove old bots channel manually
            try {
                $bnick = $this->mq($bot);
                $stmt = $this->pMysql->prepare("DELETE FROM `$bnick` WHERE `name` = :chan");
                $stmt->execute(Array(':chan'=>$chan));
                $stmt->closeCursor();
                $stmt = $this->pMysql->prepare("SELECT `chans` FROM `bots` WHERE `name` = :bot");
                $stmt->execute(Array(':bot'=>$bot));
                $row = $stmt->fetch();
                $stmt->closeCursor();
            } catch (PDOException $e) {
                $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
                echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
                $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            }
            $chans = explode(' ', $row['chans']);
            $newchans = Array();
            foreach ($chans as &$ch) {
                $ch = explode(':', $ch);
                $join = $ch[0];
                unset($ch[0]);
                $ch = implode(':', $ch);
                $ch = strtolower($ch);
                //preg_match("/([0-9]*)\:(#[ -z]*)/", $chan, $matches);
                if ($chan != $ch) {
                    array_push($newchans, "$join:$ch");
                }
            }
            $newchans = implode(' ', $newchans);
            try {
                $stmt = $this->pMysql->prepare("UPDATE `bots` SET `chans` = :newchans WHERE `name` = :bot");
                $stmt->execute(Array(':newchans'=>$newchans,':bot'=>$bot));
                $stmt->closeCursor();
            } catch (PDOException $e) {
                $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
                echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
                $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            }
        }
    }

    public function rpc_rename($p) {
        $this->pIrc->chgNick($p[0]);
        //not sure what else to update
    }
    
    public array $whois = Array();
    function cmd_whois(CmdRequest $r) {
        if(!validNick($r->args['nick'])) {
            throw new CmdException("\2{$r->args['nick']}\2 is not a valid irc nickname.");
        }
        $this->whois[$r->args['nick']]['extra'] = $r;
        $this->pIrc->raw("whois {$r->args['nick']} {$r->args['nick']}");
    }
    
    //:bots.phuzion.net 311 knivey Daniel ~Daniel noorys.bedroom * :Daniel
    function h_311($msg) {
        list($argc, $argv) = niceArgs($msg);
        $who = $argv[3];
        $key = get_akey_nc($who, $this->whois);
        if($key == '') return;
        $this->whois[$key]['host'] = $argv[4] . '@' . $argv[5];
        $argv[7] = substr($argv[7],1);
        $this->whois[$key]['userinfo'] = arg_range($argv, 7, -1);
    }
    //:bots.phuzion.net 319 knivey Daniel :@#♫ #phuzion @#b 
    function h_319($msg) {
        list($argc, $argv) = niceArgs($msg);
        $who = $argv[3];
        $key = get_akey_nc($who, $this->whois);
        if($key == '') return;
        $argv[4] = substr($argv[4],1);
        if(!isset($this->whois[$key]['channels'])) {
            $this->whois[$key]['channels'] = '';
        } else {
            $this->whois[$key]['channels'] .= ' ';
        }
        $this->whois[$key]['channels'] .= arg_range($argv, 4, -1);
    }
    //:bots.phuzion.net 301 knivey Daniel :Auto Away/Idle Since Tue Sep 11 11:09:50 2012
    function h_301($msg) {
        list($argc, $argv) = niceArgs($msg);
        $who = $argv[3];
        $argv[4] = substr($argv[4],1);
        $key = get_akey_nc($who, $this->whois);
        if($key == '') return;
        $this->whois[$key]['away'] = arg_range($argv, 4, -1);
    }
    //:irc.phuzion.net 317 knivey Daniel 8859 1347376979 :seconds idle, signon time
    function h_317($msg) {
        list($argc, $argv) = niceArgs($msg);
        $who = $argv[3];
        $key = get_akey_nc($who, $this->whois);
        if($key == '') return;
        $this->whois[$key]['idle'] = Duration_toString($argv[4]);
        $this->whois[$key]['signon'] = strftime('%T %D', $argv[5]);
    }
    //:bots.phuzion.net 330 knivey Daniel DanielRJ :is logged in as
    function h_330($msg) {
        list($argc, $argv) = niceArgs($msg);
        $who = $argv[3];
        $key = get_akey_nc($who, $this->whois);
        if($key == '') return;
        $this->whois[$key]['auth'] = $argv[4];
    }
    //:bots.phuzion.net 318 knivey daniel :End of /WHOIS list.
    function h_318($msg) {
        list($argc, $argv) = niceArgs($msg);
        $who = $argv[3];
        $key = get_akey_nc($who, $this->whois);
        if($key == '') return;
        $whois = $this->whois[$key];
        /**
         * @var CmdRequest $r
         */
        $r = $whois['extra'];
        
        $out = "\2Whois\2 for \2$who\2 ($whois[host]) $whois[userinfo]\n";
        //go through each channel and hide +s
        $chans = explode(' ', $whois['channels']);
        $display_chans = Array();
        foreach ($chans as $c) {
            $chk = ltrim(strtolower($c), '@+');
            if($this->gM('channel')->hasMode($chk, 's') == false) {
                $display_chans[] = $c;
            }
        }
        $display_chans = implode(' ', $display_chans);
        $out .= "\2Channels:\2 $display_chans\n";
        if(array_key_exists('away', $whois)) {
            $out .= "\2Away:\2 $whois[away]\n";
        }
        if(!array_key_exists('auth', $whois)) {
            $whois['auth'] = 'Not Authed';
        }
        $out .= "\2AuthServ:\2 $whois[auth] \2Idle:\2 $whois[idle] \2Signon:\2 $whois[signon]";
        $out = explode("\n", $out);
        foreach($out as $o) {
            $r->reply($o);
        }
        unset($this->whois[$key]);
    }
    
    // FAILED :bots.phuzion.net 402 knivey lol :No such server
    function h_402($msg) {
        list($argc, $argv) = niceArgs($msg);
        $who = $argv[3];
        $key = get_akey_nc($who, $this->whois);
        if($key == '') return;
        /**
         * @var CmdRequest $r
         */
        $r = $this->whois[$key]['extra'];
        $r->reply("Whois for $who returned no result.");
        unset($this->whois[$key]);
    }
    
    function cmd_clonescan(CmdRequest $r) {
        if(!$this->gM('channel')->onChan($r->chan)) {
            throw new CmdException("I'm not in that channel. ({$r->chan})");
        }
        $nhs = $this->gM('channel')->chanNickHosts($r->chan);
        $hosts = Array();
        foreach($nhs as $n => $h) {
            list($i,$h) = explode('@', $h);
            $hosts[$h][] = $n;
        }
        $clones = Array();
        foreach($hosts as $h => $ns) {
            if(count($ns) > 1) {
                $clones[$h] = $ns;
            }
        }
        $out = '';
        foreach($clones as $h => $cs) {
            $out .= '(' . implode(',', $cs) . ') ';
        }
        if($out == '') {
            $r->notice("No clones detected in $r->chan");
        } else {
            $r->notice("Clones in $r->chan: $out");
        }
    }

    function cmd_startbot(CmdRequest $r) {
        $this->gM('xnet')->sendToAll($this, 'startbotCB', 'botinfo', null, $r);
    }
    
    function startbotCB($data, CmdRequest $r) {
        $botson = Array();
        foreach($data as $d) {
            if(array_key_exists('error', $d)) {
                continue;
            } else {
                $botson[] = $d['bot'];
            }
        }
        list($argc, $argv) = niceArgs($r->args['bots']);
        $newpid = false;
        if(array_search('-newpid', $argv) !== FALSE) {
            $newpid = true;
        }
        $startbots = Array();
        foreach($argv as $a) {
            if($a == '-newpid') {
                continue;
            }
            $name = $this->botExists($a);
            if(!$name) {
                throw new CmdException("Bot $a doesn't exist in my database!");
            }
            if(array_search($name, $botson) !== false) {
                throw new CmdException("Bot $a is already online!");
            }
            $startbots[] = $name;
        }
        if(!$newpid) {
            $r->notice("Attempting to start " . implode(', ', $startbots));
            startbots($startbots);
        } else {
            $r->notice("Attempting to start " . implode(', ', $startbots) . ' In a new process');
            exec("php leaf.php " . escapeshellcmd(implode(' ', $startbots)) . " > /dev/null");
        }
    }
    
    function cmd_delbot(CmdRequest $r) {
        if($bot = $this->botExists($r->args['name']) === false) {
            throw new CmdException("That bot {$r->args['name']} does not exist");
        }
        //need to delchan all channels on bot
        //which should correctly remove chan access
        //also require FORCE if the bot is in >2 chans
        
        //for now we will use the cleanup function
        //until logs are all setup or something?
        $this->gM('xnet')->sendRPC(null, null, $bot, 'killbot', Array("Bot Deleted by $r->nick"));

        $stmt = $this->pMysql->prepare("DELETE FROM `bots` WHERE `name` = :name");
        $stmt->execute(Array(':name'=>$bot));
        $stmt->closeCursor();
        $bnick = $this->mq($bot);
        $this->pMysql->query("DROP TABLE `$bnick`");

        $del = $this->cleanaccess();
        $r->notice("Bot $bot deleted, access $del cleaned up", 0);
    }
    
    function cmd_addbot(CmdRequest $r) {
        if(!validNick($r->args['name'])) {
            throw new CmdException("{$r->args['name']} is not a valid IRC nickname");
        }

        $stmt = $this->pMysql->query("SELECT MAX(xmlport) FROM `bots`");
        $row = $stmt->fetch();
        $stmt->closeCursor();

        $name = $r->args['name'];
        if($this->botExists($name)) {
            throw new CmdException("That bot already exists!");
        }
        
        $ip = $r->args['ip'];
        $xmlport = $row['MAX(xmlport)'] + 1;
        $chans = "1:#bots 1:#botstaff";
        // A bit ugly but it works for now
        $csets = 'a:1:{s:7:"channel";a:3:{s:8:"registar";s:11:"linuxsniper";s:6:"regged";i:1337163258;s:7:"suspend";N;}}';

        $stmta = $this->pMysql->prepare("INSERT INTO `bots` " .
            "(name, ip, xmlport, chans) VALUES(:name,:ip,:xmlport,:chans)");
        $stmta->execute(Array(
            ':name'=>$name,':ip'=>$ip,':xmlport'=>$xmlport,':chans'=>$chans));
        $stmta->closeCursor();
        $bname = $this->mq($name);
        $ourname = $this->mq($this->pIrc->nick);
        $this->pMysql->query("CREATE TABLE `$bname` LIKE `$ourname`");
        $stmtc = $this->pMysql->prepare("INSERT INTO `$bname` (name,settings) VALUES(:chan, :csets)");
        $stmtc->execute(Array(':csets'=>$csets, ':chan'=>'#bots'));
        $stmtc->execute(Array(':csets'=>$csets, ':chan'=>'#botstaff'));
        $stmtc->closeCursor();

        $r->notice("Bot $name added with ip $ip and default values, use startbot to start it.");
    }
    
    function botExists($name): ?string {
        try {
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `bots` WHERE `name` = :name");
            $stmt->execute(Array(':name'=>$name));
            $row = $stmt->fetch();
            if ($stmt->rowCount() > 0) {
                $stmt->closeCursor();
                return $row['name'];
            } else {
                $stmt->closeCursor();
                return null;
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            return null;
        }
    }
    
    function cleanaccess() {
        try {
            $allchans = Array();
            foreach ($this->pMysql->query("SELECT `chans` FROM `bots`") as $r) {
                $cs = explode(' ', $r['chans']);
                foreach ($cs as $css) {
                    $allchans[substr($css, 2)] = substr($css, 2);
                }
            }
            $deleted = '';
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :chans WHERE `name` = :name");
            foreach ($this->pMysql->query("SELECT `name`,`chans` FROM `users`") as $u) {
                $chans = unserialize($u['chans']);
                if (!is_array($chans)) {
                    continue;
                }
                foreach ($chans as $c => $d) {
                    if (!array_key_exists($c, $allchans)) {
                        unset($chans[$c]);
                        $deleted .= "$c ";
                    }
                }
                $chans = serialize($chans);
                $stmt->execute(Array(':chans'=>$chans,':name'=>$u['name']));
            }
            $stmt->closeCursor();
            return $deleted;
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }
    
    //needed this to cleanup old access into
    function cmd_cleanaccess(CmdRequest $r) {
        $allchans = Array();
        foreach ($this->pMysql->query("SELECT `chans` FROM `bots`") as $r) {
            $cs = explode(' ', $r['chans']);
            foreach ($cs as $css) {
                $allchans[substr($css, 2)] = substr($css, 2);
            }
        }
        $deleted = '';
        $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :chans WHERE `name` = :name");
        foreach ($this->pMysql->query("SELECT `name`,`chans` FROM `users`") as $u) {
            $chans = unserialize($u['chans']);
            if (!is_array($chans)) {
                continue;
            }
            foreach ($chans as $c => $d) {
                if (!array_key_exists($c, $allchans)) {
                    unset($chans[$c]);
                    $deleted .= "$c ";
                }
            }
            $chans = serialize($chans);
            if ($r->args['CONFIRM'] == 'CONFIRM') {
                $stmt->execute(Array(':chans'=>$chans,':name'=>$u['name']));
            }
        }
        if ($r->args['CONFIRM'] == 'CONFIRM') {
            $r->notice("Deleted access for: $deleted");
        } else {
            $r->notice("Would delete access for: $deleted");
        }
        $stmt->closeCursor();
    }
    
    function cmd_info(CmdRequest $r) {
        var_dump($r->pm);
        var_dump($r->args);
        var_dump($r->chan);

        $msg = trim($r->args[0]);
        var_dump($msg);
        $q = '';
        $qt = 'chan';
        if($msg == null) {
            if(!$r->pm || $r->chan[0] == '#') {
                $q = strtolower($r->chan);
                $qt = 'chan';
            } else { //could be pm
                $q = $r->nick;
                $qt = 'nick';
            }
        } else {
            if($msg[0] == '*') {
                $qt = 'account';
                $q = substr($msg, 1);
            }
            if($msg[0] == '#') {
                $qt = 'chan';
                $q = $msg;
            }
            if($msg[0] != '*' && $msg[0] != '#') {
                $qt = 'nick';
                $q = $msg;
            }
        }
        if($qt == 'chan') {
            $this->gM('xnet')->sendToAll($this, 'chanInfoRecv', 'chaninfo', $q, Array($r, $q));
            return;
        }
        if($qt == 'account' && !$this->gM('user')->hand_exists($q)) {
            throw new CmdException("Account \2$q\2 has not been registered.");
        }
        if($qt == 'account' || $qt == 'nick') {
            $this->gM('xnet')->sendToAll($this, 'nickhandInfoRecv', 'nickhandinfo', Array($q, $qt), Array($r, $q, $qt));
            return;
        }
    }
    
    function nickhandInfoRecv($data, $extra) {
        /**
         * @var CmdRequest $r
         */
        list($r, $q, $qt) = $extra;
        $nicks = Array();
        $chans = Array();
        $account = null;
        $host = null;
        if($qt == 'account') {
            $account = $q;
        }
        foreach($data as $d) {
            if(array_key_exists('error', $d)) {
                continue;
            }
            if($d['resp']['network'] != $this->pIrc->network) {
                continue;
            }
            $i = $d['resp'];
            if($i['host'] == null) {
                continue;
            }
            $host = $i['host'];
            foreach($i['nicks'] as $n) {
                if(array_search($n, $nicks) === false) {
                    $nicks[] = $n;
                }
            }
            foreach($i['chans'] as $c => $cd) {
                if(!array_key_exists($c, $chans) || !is_array($chans[$c])) {
                    $chans[$c] = Array();
                }
                $chans[$c] = array_merge($chans[$c], $cd);
            }
        }
        if($account == null) {
            $account = $this->gM('user')->byHost($host);
        }
        //if its still null no account found
        $out = '';
        if($account == null) {
            $out .= "\2$q\2 is not authed to an account\n";
        } else {
            try {
                $stmt = $this->pMysql->prepare("SELECT * FROM `users` WHERE `name` = :account");
                $stmt->execute(Array(':account'=>$account));
                $row = $stmt->fetch();
                $stmt->closeCursor();
            } catch (PDOException $e) {
                $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
                echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
                $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            }
            $actime = strftime('%D %T', $row['datemade']);
            if($row['lastseen'] != 'now'){
                $row['lastseen'] = strftime('%D %T', $row['lastseen']);
            }
            $chanz = unserialize($row['chans']);
            $access = '';
            if(!is_array($chanz)) {
                $access = '';
            } else {
                foreach($chanz as $c => $a) {
                    $access .= "$a[access]:$c ";
                }
            }
            if($access == '') {
                $access = 'None';
            }
            $out .= "\2Username:\2 $account \2Created:\2 $actime ".
                    "\2Flags:\2 $row[flags] \2Lastseen:\2 $row[lastseen] ".
                    "\2Host:\2 $row[host] \2Access:\2 $access\n";
        }
        if($host == null) {
            $out .= "\2$q\2 is not visible to any bots.";
        } else {
            $fchans = '';
            foreach($chans as $c => $m) {
                $fchans .= implode('', $m['modes']) . $c . ' ';
            }
            if($fchans == '') {
                $fchans = 'None';
            }
            $out .= "\2Nicknames:\2 " . implode(', ', $nicks) .
                " \2Channels:\2 $fchans";
        }
        $r->notice($out, 1, 1);
    }
    
    function rpc_killbot($params) {
        $this->pIrc->killBot($params[0]);
    }
    
    function rpc_nickhandinfo($params) {
        list($query, $type) = $params;
        //account info can be discovered by any bot
        //all we want to do here is get the channels
        //and nicknames the account or nick's host is in
        //and the bots that can see it
        $out = Array();
        $host = null;
        //first lets try to get a host to go by
        if($type == 'nick') {
            $host = $this->pIrc->n2h($query);
        }
        if($type == 'account') {
            $host = $this->gM('user')->hand_host($query);
        }
        if($host == null) {
            //the account has no host
            //or the nick is not seen by us
            $out['host'] = null;
            $out['network'] = $this->pIrc->network;
            return $out;
        }
        
        $out = Array(
            'chans' => Array(),
            'host' => $host,
            'network' => $this->pIrc->network,
            'nicks' => $this->pIrc->h2n($host)
        );
        
        foreach($out['nicks'] as $n) {
            $nchans = $this->pIrc->nickChans($n);
            foreach($nchans as $c => $d) {
                if(!array_key_exists($c, $out['chans'])) {
                    $out['chans'][$c] = Array();
                }
                if(array_key_exists('modes', $out['chans'][$c])) {
                    $out['chans'][$c]['modes'] = array_merge($out['chans'][$c]['modes'], $d['modes']);
                } else {
                    $out['chans'][$c]['modes'] = $d['modes'];
                }
            }
        }
        return $out;
    }
    
    function chanInfoRecv($data, $extra) {
        /**
         * @var CmdRequest $r
         */
        list($r, $q) = $extra;
        $wehave_dChan = false;
        $wehave_chan = false;
        $dChan_bot = null;
        $chan_bot = null;
        $dChan = Array();
        $chan = Array();
        
        $botsadded = Array();
        $botson = Array();
        /*
         * run throgh the responses and build out data
         */
        foreach($data as $d) {
            if(array_key_exists('error', $d)) {
                continue;
            }
            if($d['resp']['network'] != $this->pIrc->network) {
                continue;
            }
            $i = $d['resp'];
            //If the channel is on this bot we should show its info
            if($i['chanAdded'] && $d['bot'] == $this->pIrc->nick) {
                $wehave_dChan = true;
            }
            if($i['onChan'] && $d['bot'] == $this->pIrc->nick) {
                $wehave_chan = true;
            }
        }

        foreach($data as $d) {
            if(array_key_exists('error', $d)) {
                continue;
            }
            if($d['resp']['network'] != $this->pIrc->network) {
                continue;
            }
            $i = $d['resp'];
            if($i['chanAdded']) {
                $botsadded[] = $d['bot'];
                if($wehave_dChan && $d['bot'] == $this->pIrc->nick) {
                    if (empty($dChan)) {
                        $dChan = $i['dChan'];
                        $dChan_bot = $d['bot'];
                    }
                }
                if(!$wehave_dChan) {
                    if (empty($dChan)) {
                        $dChan = $i['dChan'];
                        $dChan_bot = $d['bot'];
                    }
                }
            }
            if($i['onChan']) {
                $botson[] = $d['bot'];
                if($wehave_chan && $d['bot'] == $this->pIrc->nick) {
                    if (empty($chan)) {
                        $chan = $i['chan'];
                        $chan_bot = $d['bot'];
                    }
                }
                if(!$wehave_chan) {
                    if (empty($chan)) {
                        $chan = $i['chan'];
                        $chan_bot = $d['bot'];
                    }
                }
            }
        }
        $network = $this->pIrc->network;
        $dChan_out = "No running bots have been registered to $q on $network.";
        if(!empty($dChan)) {
            if(count($botsadded) > 1) {
                $dChan_out = "\2$q:\2 Online info from $dChan_bot (Bots registered: " . implode(', ', $botsadded) . ")\n";
            } else {
                $dChan_out = "\2$q:\2 Online info from $dChan_bot\n";
            }
            $regged = $dChan['settings']['channel']['regged'];
            $regdate = strftime('%D', $regged);
            $registrar = $dChan['settings']['channel']['registar'];
            if(isset($dChan['settings']['SetReg']['sets']['channel']['trig'])) {
            	$trig = $dChan['settings']['SetReg']['sets']['channel']['trig'];
            } else {
            	$trig = $this->gM('SetReg')->channelSets['channel']['trig']['default'];
            }
            $users = $this->gM('user')->chan_users($q);
            $dChan_out .= "\2Registrar:\2 $registrar \2On:\2 $regdate \2Trig:\2 $trig ";
            $dChan_out .= "\2Users:\2 $users";
            $suspend = $dChan['settings']['channel']['suspend'];
            if($suspend != null) {
                $suspend_date = strftime('%D', $suspend['date']);
                $dChan_out .= "\n\2$q\2 was suspended by $suspend[by] on $suspend_date ($suspend[reason])";
            }
        }
        $chan_out = "No running bots have joined $q on $network.";
        if(!empty($chan)) {
            if(count($botson) > 1) {
                $chan_out = implode(', ', $botson) . " have joined $q ";
            } else {
                $chan_out = implode(', ', $botson) . " has joined $q ";
            }
            $modesA = '';
            $modesB = '';
            foreach ($chan['modes'] as $n => $m) {
                $modesA .= $n;
                if ($m != null) {
                    $modesB .= "$m ";
                }
            }
            $modes = "$modesA $modesB";
            $idlers = count($chan['nicks']);
            $createtime = strftime('%D %T', $chan['createTime']);
            $chan_out .= "\2Channel Created:\2 $createtime \2Modes:\2 $modes \2Idlers:\2 $idlers";
        }
        $r->notice($dChan_out,1,1);
        $r->notice($chan_out,1,1);
    }
    
    function rpc_chaninfo($chan) {
        $chan = strtolower($chan[0]);
        $out = Array();
        $out['chanAdded'] = $this->gM('channel')->chanExists($chan);
        if($out['chanAdded']) {
            $out['dChan'] = $this->gM('channel')->dChans[$chan];
        } else {
            $out['dChan'] = null;
        }
        $out['onChan'] = $this->gM('channel')->onChan($chan);
        if($out['onChan']) {
            $out['chan'] = $this->gM('channel')->chans[$chan];
        } else {
            $out['chan'] = null;
        }
        $out['network'] = $this->pIrc->network;
        return $out;
    }
    
    function cmd_forceauth(CmdRequest $r) {
        $this->pIrc->raw($this->pIrc->authserv);
    }
    
    function cmd_quit(CmdRequest $r) {
        $qmsg = "Bot shutdown by $r->nick";
        if($r->args['reason'] != null) {
            $qmsg .= " ({$r->args['reason']})";
        }
        $this->pIrc->killBot($qmsg);
    }
    
    function cmd_global(CmdRequest $r) {
        $this->gM('xnet')->sendToAll(null, null, 'globalmsg', Array($r->nick, $r->args['msg']));
    }
    
    function cmd_bots(CmdRequest $r) {
        $this->gM('xnet')->sendToAll($this, 'botsCB', 'botinfo', null, $r);
    }
    
    function botsCB($data, CmdRequest $r) {
        $on = Array();
        $off = Array();
        $on_out = '';
        foreach($data as $d) {
            if(array_key_exists('error', $d)) {
                $off[] = $d['bot'];
            } else {
                $on[$d['resp']['pid']]['bots'][] = $d['bot'];
                $on[$d['resp']['pid']]['mem'] = $d['resp']['mem'];
            }
        }
        foreach($on as $pid => $info) {
            $on_out .= " \2Pid($pid, $info[mem]):\2 " . implode(' ', $info['bots']);
        }
        $off = implode(', ', $off);
        $r->notice("\2Online:\2$on_out");
        $r->notice("\2Offline:\2 $off");
    }
    
    function cmd_bnstats(CmdRequest $r) {
        $this->gM('xnet')->sendToAll($this, 'bnstatsCB', 'botinfo', null, $r);
    }
    
    function bnstatsCB($data, CmdRequest $r) {
        $nicks = Array();
        $chans = Array();
        foreach($data as $d) {
            if(!array_key_exists('error', $d)) {
                foreach($d['resp']['nicks'] as $n) {
                    $nicks[$n] = $n;
                }
                foreach($d['resp']['chans'] as $c) {
                    $chans[$c] = $c;
                }
            }
        }
        try {
            $stmt = $this->pMysql->query("SELECT count(*) FROM `users`");
            $uc = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $uc= $uc['count(*)'];
        $r->reply("\2Total unique channels joined:\2 " . count($chans) . " \2Total unique nicks seen:\2 " . count($nicks) . " \2Registered Accounts:\2 $uc");
    }
    
    function cmd_botinfo(CmdRequest $r) {
        $in = convert($this->pSockets->sockets[intval($this->pIrc->sock)]['rBytes']);
        $out = convert($this->pSockets->sockets[intval($this->pIrc->sock)]['sBytes']);
        $tin = convert($this->pSockets->rBytes);
        $tout = convert($this->pSockets->sBytes);
        $contime = Duration_toString(time() - $this->pSockets->sockets[intval($this->pIrc->sock)]['connectTime']);
        $pid = getmypid();
        $r->reply("Connected: $contime IRC-Recv: $in IRC-Sent: $out Total-Recv: $tin Total-out: $tout Pid: $pid Memory usage: " . convert(memory_get_usage(true)));
    }

    function rpc_botinfo($params) {
        $bi['contime'] = Duration_toString(time() - $this->pSockets->sockets[intval($this->pIrc->sock)]['connectTime']);
        $bi['in'] = convert($this->pSockets->sockets[intval($this->pIrc->sock)]['rBytes']);
        $bi['out'] = convert($this->pSockets->sockets[intval($this->pIrc->sock)]['sBytes']);
        $bi['tin'] = convert($this->pSockets->rBytes);
        $bi['tout'] = convert($this->pSockets->sBytes);
        $bi['mem'] = convert(memory_get_usage(true));
        $bi['pid'] = getmypid();
        $bi['chans'] = $this->gM('channel')->joinedChans();
        $bi['nicks'] = array_keys($this->pIrc->Nicks->ppl);
        return $bi;
    }
}

