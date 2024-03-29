<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';
require_once('modules/Module.inc');
require_once('Tools/Tools.php');

/**
 * Gives us functions for accessing channels part of database
 * Store topic bans users modes etc
 */
class ChanStats extends Module { 
    function cmd_cstats(CmdRequest $r) {
        ///home/botops/pisg-0.73/pisg --cfg PicLocation="gfx/" -ne GameSurge 
        //-ch \#pandabears -f pircbot -l /home/botops/BotNetwork/chanlogs/#pandabears.log
        //-o /home/botops/public_html/chanstats/pandabears.html
        
        //need to sanitize channel names for bash before can do this
        //also note if theres a cfg file in pisg dir it will run it
        //`/home/botops/pisg-0.73/pisg --cfg PicLocation="gfx/" -ne GameSurge -ch `;
        $c = urlencode(strtolower(ridfirst($r->chan)));
        $r->notice("Channels stats at http://botops.net/chanstats/$c.html");
    }
    
    function cmd_forcestats(CmdRequest $r) {
        $this->genStats();
    }
    
    public $nextGen; //Date for stats to next generate
    public $genShed; //Times to initiate a stats generation
    public $timeSpent; //How long the last generation took to run
    
    function setNext() {
        for($i =0; true; $i = $i + 86400) {
            foreach($this->genShed as $t) {
                if(strtotime($t)+$i -5 > time()) {
                    $this->nextGen = strtotime($t) +$i;
                    return;
                }
            }
        }
        $this->nextGen = strtotime('1/1/3020 00:00:00');
        $this->msg('#botstaff', "Stats Generator failed to update next runtime, setting to run on $this->nextGen");
    }
    
    function rehash(&$old) {
        $this->init();
    }
    
    function init() {
        //lets only have BotOps run the generator
        if($this->pIrc->nick == 'BotOps') {
            $this->genShed = Array(
                '00:00:00',
                '06:00:00',
                '12:00:00',
                '18:00:00',
            );
            $this->setNext();
        }
    }
    
    function logic() {
        //lets only have BotOps run the generator
        if($this->pIrc->nick == 'BotOps') {
            if(time() > $this->nextGen) {
                $this->genStats();
                $this->setNext();
            }
        }
    }
    
    function genStats() {
        $this->pIrc->msg('#botstaff', "Generating channel stats...");
        echo "*** ChanStats Writing pisg.cnf\n";
        //ok so... get a list of active channels from mysql...
        try {
            $stmt = $this->pMysql->query("SELECT `chans` FROM `bots` WHERE `active` = 1");
            $res = $stmt->fetchAll();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        
        $chans = Array();
        foreach ($res as $b) {
            $cs = explode(' ', $b['chans']);
            foreach($cs as $c) {
                $c = explode(':', $c);
                unset($c[0]);
                $c = strtolower(implode(':', $c));
                //If we could do options we would here
                if($c != '#botstaff' && file_exists("./chanlogs/" . $c . ".log")) {
                    $chans[$c] = $c;
                }
            }
        }
        //now lets write a pisg.cfg file
        $fd = fopen('pisg.cfg', 'w');
        fwrite($fd, "<set Network=\"GameSurge\"/>\n<set PicLocation=\"gfx/\"/>\n<set Maintainer=\"BotOps\"/>\n");
        foreach ($chans as $c) {
            $hc = ridfirst($c);
            fwrite($fd, "<channel=\"$c\">
   Logfile=\"/home/botops/BotNetwork/BotOps/chanlogs/$c.log\"
   Format=\"pircbot\"
   OutputFile=\"/home/botops/public_html/chanstats/$hc.html\"
</channel>\n");
        }
        fclose($fd);
        echo "*** ChanStats Running pisg\n";
        $start = microtime_float();
        var_dump(`/home/botops/pisg-0.73/pisg -co pisg.cfg`);
        $this->timeSpent = microtime_float() - $start;
        echo "*** ChanStats pisg finished in $this->timeSpent seconds\n";
        $this->pIrc->msg('#botstaff', "Channels stats generated in $this->timeSpent seconds...");
    }
}

?>
