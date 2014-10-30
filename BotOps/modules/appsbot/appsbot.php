<?PHP
/*
 * We're going to need to disable or fix channels module
 * and idealy several other modules?
 * so when apps bot joins its not freaking out trying to
 * get the channel data from mysql and shit
 * and doesn't waste its time on other silly commands
 * 
 * need to make sure all the commands done here dont affect
 * other things like the Irc class.
 */

class appsbot extends Module {
    /*
     * keep track of the apps we are currently doing
     * and how far their progress is
     */
    public $Apps = Array();
    
    function cmd_apply($nick, $chan, $args) {
        list($argc, $argv) = niceArgs($args);
        $hand = $this->gM('user')->byNick($nick);
        if($hand == '') {
            $this->pIrc->notice($nick, "You must be authed to BotOps to apply.");
            return $this->ERROR;
        }
        if($argc < 1) {
            return $this->BADARGS;
        }
        if($this->getEnabled() == 'disabled') {
            $this->pIrc->msg($chan, "Sorry $nick, but Applications are currently disabled.");
            return $this->ERROR;
        }
        
        $achan = strtolower($argv[0]);
        $dnr = $this->gM('channel')->isdnr($achan);
        if($dnr != false) {
            $dnr['date'] = strftime('%I:%M%p - %D', $dnr['date']);
            if ($dnr['expires'] == 0) {
                $dnr['expires'] = 'Never.';
            } else {
                $dnr['expires'] = strftime('%I:%M%p - %D', $dnr['expires']);
            }
            $this->pIrc->notice($nick, "That channel name conflicts with DNR $dnr[mask] set by $dnr[who] on $dnr[date] expiring $dnr[expires] for the reason: $dnr[reason]");
            return $this->ERROR;
        }
        if(cisin($chan, ',')) {
            $this->pIrc->notice($nick, "Invalid channel name. Do not use commas.");
            return $this->ERROR;
        }
        $bots = $this->gM('channel')->botsOnChan($achan);
        if(!empty($bots)) {
            $this->pIrc->msg($chan, "$nick, $achan has already been registered to ". implode(',', $bots));
        }
        if(count($bots) != 0) {
            try {
                $bnick = $this->mq($this->pIrc->nick);
                $stmt = $this->pMysql->prepare("SELECT * FROM `$bnick` WHERE `name` = :achan");
                foreach ($bots as $bot) {
                    $stmt->execute(Array(':achan'=>$achan));
                    $res = $stmt->fetch();
                    $stmt->closeCursor();
                    $sets = unserialize($res['settings']);
                    $suspend = $sets['channel']['suspend'];
                    if ($suspend != null) {
                        $suspend_date = strftime('%D', $suspend['date']);
                        $this->pIrc->msg($chan, "$bot on \2$achan\2 was suspended by $suspend[by] on $suspend_date ($suspend[reason]) To resolve this wait for a staff member to assist you. We will not help if you aren't here.");
                    }
                }
            } catch (PDOException $e) {
                $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
                echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
                $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            }
            return $this->ERROR;
        }
        if(array_key_exists($achan, $this->Apps)) {
            $this->pIrc->msg($chan, "$nick, Application already in progress for $achan");
            return $this->ERROR;
        }
        $this->Apps[$achan] = Array(
            'chan' => $achan,
            'nick' => $nick,
            'hand' => $hand
        );
        //first step whois the user and get authserv
        $this->whois[strtolower($nick)] = $achan;
        $this->pIrc->raw("WHOIS $nick");
    }
    
    public $whois = Array();

    //>> :bots.phuzion.net 330 knivey Bladerunner Bladerunner :is logged in as
    function h_330($line) {
        list($argc, $argv) = niceArgs($line);
        $nick = strtolower($argv[3]);
        if(!array_key_exists($nick, $this->whois)) {
            return;
        }
        $achan = $this->whois[$nick];
        $this->Apps[$achan]['authserv'] = $argv[4];
    }
    
    //>> :bots.phuzion.net 318 knivey bladerunner :End of /WHOIS list.
    function h_318($line) {
        list($argc, $argv) = niceArgs($line);
        $nick = strtolower($argv[3]);
        if(!array_key_exists($nick, $this->whois)) {
            return;
        }
        $achan = $this->whois[$nick];
        if(!array_key_exists($achan, $this->Apps)) {
            unset($this->whois[$nick]);
            return;
        }
        if(!array_key_exists('authserv', $this->Apps[$achan])) {
            $this->pIrc->msg('#bots', "$nick, You must first auth to AuthServ");
            unset($this->Apps[$achan]);
        } else {
            //next step get channel modes.
            $this->pIrc->raw("MODE $achan");
        }
        unset($this->whois[$nick]);
    }
    
    //:Prothid.CA.US.GameSurge.net 324 kNiVeY- #channel +tnCzl 16
    function h_324($line) {
        list($argc, $argv) = niceArgs($line);
        $achan = strtolower($argv[3]);
        if(!array_key_exists($achan, $this->Apps)) {
            return;
        }
        if (!cisin($argv[4], 'z')) {
            $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$argv[3]\002 has been \002DENIED\002. Your channel must first be registered with ChanServ.");
            unset($this->Apps[strtolower($argv[3])]);
            return;
        }
        if (cisin($argv[4], 'k') || cisin($argv[4], 'i')) {
            $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$argv[3]\002 has been \002DENIED\002. Your channel may NOT have modes +i or +k");
            unset($this->Apps[strtolower($argv[3])]);
            return;
        }
        //next step get user count / info for clone scan
        $this->pIrc->raw("JOIN $argv[3]");
    }

    function h_join($nick, $chan) {
        $achan = strtolower($chan);
        if(!array_key_exists($achan, $this->Apps)) {
            return;
        }
        //update Apps to show we are doing /who
        //next step wait for end of /who and clonescan/botscan/skiddiescan
    }

    //:Prothid.CA.US.GameSurge.net 315 BattleBotGalactica #bots :End of /WHO list.
    function h_315($line) {
        list($argc, $argv) = niceArgs($line);
        $achan = strtolower($argv[3]);
        if (!array_key_exists($achan, $this->Apps)) {
            return;
        }
        $unique = Array();
        $clones = Array();
        //later we will add a known bots db
        //$bots = Array();
        $nhs = $this->gM('channel')->chanNickHosts($achan);
        $hosts = Array();
        foreach($nhs as $n => $h) {
            list($i,$h) = explode('@', $h);
            $hosts[$h][] = $n;
        }
        foreach($hosts as $h => $ns) {
            if(count($ns) > 1) {
                $clones[$h] = $ns;
            } else {
                $unique[$h] = $ns;
            }
        }
        $cloneout = '';
        foreach($clones as $h => $cs) {
            $cloneout .= '(' . implode(',', $cs) . ') ';
        }
        if (!empty($clones)) {
            $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 I have found the following users to be clones in \002$argv[3],\002 $cloneout I will only count them once!");
        }
        $idlers = count($unique) + count($clones);
        //update this v number when we scan bots
        $idlers = $idlers - 2; //subtract 2 for chanserv and us
        if ($idlers < $this->getIdlers()) {
            $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$argv[3]\002 has been \002DENIED\002. Your channel needs at least \002" . $this->getIdlers() . "\002 idlers not including \002ChanServ, Clones, or Bots\002.");
            $nick = $this->Apps[$achan]['nick'];
            $this->pIrc->raw("PART $argv[3] :\002\00302A\003\00315pply:\003\002 \002$nick's\002 application for \002$argv[3]\002 has been \002\00304DENIED\003\002, \002$argv[3]\002 did not meet channel requierments, not enough channel idlers, $idlers, you need 5");
            unset($this->Apps[$achan]);
            return;
        }
        //next step check applicants access
        $this->pIrc->raw("CS $achan a *" . $this->Apps[$achan]['authserv']);
        return;
    }

    //:Prothid.CA.US.GameSurge.net 471 kNiVeY- #bots-dev :Cannot join channel (+l)
    function h_471($line) {
        list($argc, $argv) = niceArgs($line);
        $achan = strtolower($argv[3]);
        if(!array_key_exists($achan, $this->Apps)) {
            return;
        }
        $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$achan\002 has been \002DENIED\002. Your channel could not be joined because it is full.");
        unset($this->Apps[$achan]);
    }
    
    //:Prothid.CA.US.GameSurge.net 403 kNiVeY #lolgiggles :No such channel
    function h_403($line) {
        list($argc, $argv) = niceArgs($line);
        $achan = strtolower($argv[3]);
        if(!array_key_exists($achan, $this->Apps)) {
            return;
        }
        $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$achan\002 has been \002DENIED\002. No such channel.");
        unset($this->Apps[$achan]);
    }
    
    //:Prothid.CA.US.GameSurge.net 474 BotInquisitor #PandaBears :Cannot join channel (+b)
    function h_474($line) {
        list($argc, $argv) = niceArgs($line);
        $achan = strtolower($argv[3]);
        if(!array_key_exists($achan, $this->Apps)) {
            return;
        }
        $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$achan\002 has been \002DENIED\002. Bot is banned.");
        unset($this->Apps[$achan]);
    }
    
    function h_kick($by, $chan, $who, $text) {
        $achan = strtolower($chan);
        if(!array_key_exists($achan, $this->Apps)) {
            return;
        }
        $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$achan\002 has been \002DENIED\002. Bot was kicked.");
        unset($this->Apps[$achan]);
    }
    
    function h_notice($nick, $target, $msg) {
        //$this->pIrc->msg('#bots', "NDBG $nick $msg");
        if($nick != 'ChanServ') {
            return;
        }
        //Milon has access 400 in #gamesurge and has security override enabled.
        //Roffle lacks access to #gamesurge.
        //DanielRJ has access 300 in #gamesurge.
        //zoot lacks access to #bots but has security override enabled.
        //[knivey] http://www.explos
        list($argc, $argv) = niceArgs($msg);
        $cl = arg_range($argv, 1, 2);
        if($msg[0] == '[') {
            //setinfo...
            return;
        }
        if($cl != 'lacks access' && $cl != 'has access') {
            return;
        }
        $account = $argv[0];
        if ($cl == 'lacks access') {
            $chan = strtolower($argv[4]);
            if ($argc == 5) {
                $chan = substr($chan, 0, strlen($chan) - 1);
            }
            if(!array_key_exists($chan, $this->Apps)) {
                return;
            } else {
                $anick = $this->Apps[$chan]['nick'];
                $aauth = $this->Apps[$chan]['authserv'];
            }
            if($account == 'BotOps') {
                $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$chan\002 has been \002DENIED\002. Please !addco *BotOps in your channel.");
                $this->pIrc->raw("PART $chan :\002\00302A\003\00315pply:\003\002 \002$anick's\002 application for \002$chan\002 has been \002\00304DENIED\003\002, \002$chan\002 did not meet channel requierments, I do not have enough channel access.");
            }
            if($account == $aauth) {
                $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$chan\002 has been \002DENIED\002. You need at least 401 access in the channel.");
                $this->pIrc->raw("PART $chan :\002\00302A\003\00315pply:\003\002 \002$anick's\002 application for \002$chan\002 has been \002\00304DENIED\003\002, \002$chan\002 did not meet channel requierments, not enough channel access.");
            }
            unset($this->Apps[$chan]);
        }
        if ($cl == 'has access') {
            $axs = trim($argv[3], "\2");
            $chan = strtolower($argv[5]);
            if ($argc == 6) {
                $chan = substr($chan, 0, strlen($chan) - 1);
            }
            if(!array_key_exists($chan, $this->Apps)) {
                return;
            } else {
                $anick = $this->Apps[$chan]['nick'];
                $aauth = $this->Apps[$chan]['authserv'];
            }
            if($account == 'BotOps') {
                if((int)$axs < 400) {
                    $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$chan\002 has been \002DENIED\002. Please !addco *BotOps in your channel.");
                    $this->pIrc->raw("PART $chan :\002\00302A\003\00315pply:\003\002 \002$anick's\002 application for \002$chan\002 has been \002\00304DENIED\003\002, \002$chan\002 did not meet channel requierments, I do not have enough channel access.");
                    unset($this->Apps[$chan]);
                } else {
                    //passed
                    $this->addchan($this->Apps[$chan]);
                    unset($this->Apps[$chan]);
                }
            }
            if($account == $aauth) {
                if ((int)$axs < 401) {
                    $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$chan\002 has been \002DENIED\002. You need at least 401 access in the channel, you only have $axs");
                    $this->pIrc->raw("PART $chan :\002\00302A\003\00315pply:\003\002 \002$anick's\002 application for \002$chan\002 has been \002\00304DENIED\003\002, \002$chan\002 did not meet channel requierments, you need 401 or more channel access.");
                    unset($this->Apps[$chan]);
                } else {
                    $this->pIrc->raw("cs $chan a *BotOps");
                }
            }
        }
    }
    
    function addchan($apps) {
        //want to fetch a list of bots for the apps to pick from
        //fornow just using bot-01..
        $chan = $apps['chan'];
        $nick = $apps['nick'];
        $args = Array(
            $apps['chan'],
            $apps['hand'],
            'BotApps'
        );
        $bot = $this->selectBot();
        $this->gM('xnet')->sendRPC(null, null, $bot, 'addchan', $args);
        $this->pIrc->raw("PART $chan :\002\00302A\003\00315pply:\003\002 \002$nick's\002 application for \002$chan\002 has been \002\00309APPROVED\003\002, all requirements were met.");
        $this->pIrc->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$chan\002 has been \002APPROVED!\002 Congratulations, $bot should be joining the channel $nick");
    }
    
    function selectBot() {
        $max = $this->getMaxChans();
        try {
            $stmt = $this->pMysql->prepare("SELECT `chans` FROM `bots` WHERE `name` = :bot");
            foreach ($this->getBots() as $bot) {
                $stmt->execute(Array(':bot'=>$bot));
                $r = $stmt->fetch();
                $stmt->closeCursor();
                $chans = explode(' ', $r['chans']);
                if (count($chans) <= $max) {
                    return $bot;
                }
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }
    
    function getBots() {
        try {
            $stmt = $this->pMysql->query("select bots from AppsConf");
            $r = $stmt->fetch();
            $stmt->closeCursor();
            return unserialize($r['bots']);
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function getIdlers() {
        try {
            $stmt = $this->pMysql->query("select idlers from AppsConf");
            $r = $stmt->fetch();
            $stmt->closeCursor();
            return (int) $r['idlers'];
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function getMaxChans() {
        try {
            $stmt = $this->pMysql->query("select maxchans from AppsConf");
            $r = $stmt->fetch();
            $stmt->closeCursor();
            return (int) $r['maxchans'];
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function getEnabled() {
        try {
            $stmt = $this->pMysql->query("select enabled from AppsConf");
            $r = $stmt->fetch();
            $stmt->closeCursor();
            return $r['enabled'];
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function botExists($name) {
        try {
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `bots` WHERE `name` = :name");
            $stmt->execute(Array(':name'=>$name));
            $row = $stmt->fetch();
            $cnt = $stmt->rowCount();
            $stmt->closeCursor();
            if ($cnt >= 1) {
                return $row['name'];
            } else {
                return false;
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }
    
    function cmd_setbots($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        if($argc < 1) {
            $bots = $this->getBots();
            $this->pIrc->notice($nick, "Currently selecting from: ". implode(', ', $bots));
            return $this->ERROR;
        }
        foreach($argv as $bot) {
            if(!$this->botExists($bot)) {
                $this->pIrc->notice($nick, "The bot $bot does not exist in my database");
                return $this->ERROR;
            }
        }
        try {
            $bots = serialize($argv);
            $stmt = $this->pMysql->prepare("UPDATE `AppsConf` SET `bots` = :bots");
            $stmt->execute(Array(':bots'=>$bots));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->pIrc->notice($nick, "AppsBot Bot selection list updated.");
    }
    
    function cmd_setidlers($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        if($argc < 1) {
            $idlers = $this->getIdlers();
            $this->pIrc->notice($nick, "Current Idler Min: $idlers");
            return $this->ERROR;
        }
        $idlers = (int)$argv[0];
        try {
            $stmt = $this->pMysql->prepare("UPDATE `AppsConf` SET `idlers` = :idlers");
            $stmt->execute(Array(':idlers'=>$idlers));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->pIrc->notice($nick, "AppsBot Min Idler limit adjusted to $idlers.");
    }
    
    function cmd_setmaxchans($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        if($argc < 1) {
            $max = $this->getMaxChans();
            $this->pIrc->notice($nick, "Current Max Chans: $max");
            return $this->ERROR;
        }
        $max = (int)$argv[0];
        try {
            $stmt = $this->pMysql->prepare("UPDATE `AppsConf` SET `maxchans` = :max");
            $stmt->execute(Array(':max'=>$max));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->pIrc->notice($nick, "AppsBot Max Chans limit adjusted to $max.");
    }
    
    function cmd_setenabled($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        if($argc < 1) {
            $e = $this->getEnabled();
            $this->pIrc->notice($nick, "AppsBot is currently: $e");
            return $this->ERROR;
        }
        $e = strtolower($argv[0]);
        if($e != 'enabled' || $e != 'disabled') {
            $this->pIrc->notice($nick, "Choose from enabled or disabled");
            return $this->ERROR;
        }
        try {
            $stmt = $this->pMysql->prepare("UPDATE `AppsConf` SET `enabled` = :e");
            $stmt->execute(Array(':e'=>$e));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->pIrc->notice($nick, "AppsBot is now: $e.");
    }
    
}
/*
 //LOL could it be the application passed
 $this->msg('#bots', "\002\00302A\003\00315pply:\003\002 The application for \002$chan\002 has been \002APPROVED!\002 Congratulations, " . $this->chans[strtolower($chan)]['nick']);
 $email = get_hand_email($this->chans[strtolower($chan)]['hand']);
 if ($email) {
     $postmail = array($email, "Application for $chan", "Your application for $chan has been reviewed and a bot has been added to your channel.\n\nIf you need help with setup please visit http://www.botnetwork.org/tutorial and browse that page.\nFor any additional assistance visit our support forums http://www.botnetwork.org/forums/FDzAwMDQP or irc://irc.us.gamesurge.net/bots and let us know\n\n--\n\nPlease do not reply to this email! Nothing will happen =/", "From: BotNetwork Application Services\n");
     mail($postmail[0], $postmail[1], $postmail[2], $postmail[3]);
}
//TODO CHECK IF BOT IS ON NET
$bnet->route('-1', '&botchan', ':BotNetwork!BotNetwork@hidden PRIVMSG &botchan :' . select_bot() . " addchan $chan *" . $this->chans[strtolower($chan)]['hand']);
$appnick = $this->chans[strtolower($chan)]['nick'];
unset($this->chans[strtolower($chan)]);
$this->raw("PART $chan :\002\00302A\003\00315pply:\003\002 \002$appnick's\002 application for \002$chan\002 has been \002\00309APPROVED\003\002, all requirements were met.");
*/
?>
