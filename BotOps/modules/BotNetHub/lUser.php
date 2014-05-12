<?PHP
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
 * BotNetHub/lUser.php
 *   Class for botnet users
 ***************************************************************************/

class lUser {
    public $parent;
    public $ip;
    public $sock;
    public $name;
    public $lChans = Array();
    public $ctime;
    public $ptime;
    public $pinged;
    public $rXb;
    public $tXb;
    public $pSockets;
    public $closed = false;

    public function  __construct($ip, $sock, $parent, $sockets) {
        $this->parent = $parent;
        $this->pSockets = $sockets;
        $this->ip = $ip;
        $this->sock = $sock;
        $this->ctime = time();
        $this->ptime = time();
        $this->raw('NOTICE AUTH :*** Please authenticate to use this service.');
	$this->raw('NOTICE AUTH :*** Usage: AUTH <username> <password>');
        echo "New user created $sock\n";
    }

    public function raw($data) {
        $this->pSockets->send($this->sock, $data . "\r\n");
    }

    public function isSock($sock) {
        if(intval($this->sock) == intval($sock)) {
            return true;
        }
        return false;
    }

    public function lineIn($line) {
        if(!$this->closed) {
            $this->destroy();
        }
        echo "[BotNetHub/lUser] lineIn: $line\n";
        $this->ptime = time();
        $line = trim($line);
        $line = str_replace("\r", '', $line);
        if($line == '') return;
        $arg = argClean(explode(' ', $line));
        $arg[0] = strtolower($arg[0]);
        if(!$this->isAuthed() && ($arg[0] != 'auth' && $arg[0] != 'nick' && $arg[0] != 'user')) {
            $this->raw('NOTICE AUTH :*** Please authenticate first to use this service.');
            $this->raw('NOTICE AUTH :*** Usage: AUTH <username> <password>');
            return;
        }
        if(!$this->isAuthed() && $arg[0] == 'auth') {
            if(empty($arg[1]) || empty($arg[2])) {
                $this->raw('NOTICE AUTH :*** Please provide both username and password.');
                $this->raw('NOTICE AUTH :*** Usage: AUTH <username> <password>');
                return;
            }
            if(!$this->parent->gM('user')->checkPass($arg[1], $arg[2])) {
                $this->raw(':BotNetwork NOTICE ' . $this->name . ' :*** INVALID USER/PASS!');
		$this->quit('Invalid user/pass');
            }
        }
    }

    public function logic() {
        if(!$this->closed) {
            if($this->closed +15 > time()) {
                $this->destroy();
                return;
            }
        }
        if($this->ptime + 60 < time() && !$this->pinged) {
            $this->raw("PING :" . time());
            $this->pinged = true;
        }
        if($this->ptime + 90 < time()) {
            $this->quit("Ping Timeout.");
        }
        //check auths
        if(!$this->isAuthed()) {
            if($this->ctime + 30 < time()) {
                $this->quit("Failed to auth.");
            }
        }
    }

    public function getName() {
        return $this->name;
    }

    public function quit($msg) {
        $id = intval($this->sock);
        $name = $this->getName();
        //send quit msg to user
        $this->raw(":$name!$name@hidden QUIT :$msg");
        //$this->parent->uClose($this->sock);
        $this->closed = time();
        //TODO send quit msg to channel
    }

    public function destroy() {
        $this->parent->uClose($this->sock);
    }

    public function isAuthed() {
        if($name != null) {
            return true;
        }
        return false;
    }
}

?>
