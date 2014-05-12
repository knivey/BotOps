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
 * ChanLog.php
 *   Logs channel text to their own files
 *   Might later add a timer to do fflush if our files dont write on fail
 ***************************************************************************/
 require_once('modules/Module.inc');
 require_once('Tools/Tools.php');
 
class ChanLog extends Module {
    public $files = Array();
    
    function h_chanevent($chan, $line) {
        $chan = strtolower($chan);
        $microtime = round(microtime(true) * 1000);
        if(!array_key_exists($chan, $this->files)) {
            //try to open the file and get a lock
            $fd = fopen('./chanlogs/' . $chan . '.log', 'a+');
            //Here i'm hoping this will keep two bots from writing to a log at the same time
            if (flock($fd, LOCK_EX | LOCK_NB)) {
                $this->files[$chan] = $fd;
                $bn = $this->pIrc->nick;
                fwrite($fd, "$microtime *** Log Opened ($bn) ***\n");
            } else {
                fclose($fd);
                return;
            }
        } else {
            $fd = $this->files[$chan];
        }
        fwrite($fd, "$microtime $line\n");
    }
    
    function h_part($nick, $chan, $text) {
        $chan = strtolower($chan);
        if($nick == $this->pIrc->currentNick()) {
            $this->closeLog($chan, "Parted: $text");
        }
    }
    
    function h_killbot($msg) {
        foreach($this->files as $chan => $val) {
            $this->closeLog($chan, "Bot Killed: $msg");
        }
    }
    
    function h_kick($by, $chan, $nick, $text) {
        $chan = strtolower($chan);
        if($nick == $this->pIrc->currentNick()) {
            $this->closeLog($chan, "Kicked: $text");
        }
    }
    
    public $timer;
    function init() {
    	$this->timer = time() + 1800; //30 min
    }
    
    function logic() {
    	if(time() > $this->timer) {
    		$this->timer = time() + 1800;
    		$this->saveLogs();
    	}
    }
    
    function saveLogs() {
    	echo strftime("%D %T") . " Saving ChanLogs...\n";
    	
    	//I'm pretty sure just doing the fflush should make sure data will be saved
    	foreach($this->files as $chan => $fd) {
    		fflush($fd);
    	}
    	
    	echo strftime("%D %T") . " ChanLogs Saved!!!\n";
    }
    
    function closeLog($chan , $msg) {
        $chan = strtolower($chan);
        if(!array_key_exists($chan, $this->files)) {
            return;
        }
        $bn = $this->pIrc->nick;
        fwrite($this->files[$chan], round(microtime(true) * 1000) . " *** Closing log ($bn) ($msg) ***\n");
        fflush($this->files[$chan]);
        flock($this->files[$chan], LOCK_UN);
        fclose($this->files[$chan]);
        unset($this->files[$chan]);
    }
}

?>
