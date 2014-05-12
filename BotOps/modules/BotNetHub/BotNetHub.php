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
 * BotNetHub/BotNetHub.php
 *   Module for the TCP IRC protocol botnet hub
 *   One hub per botnet (for now..) hub bot loads
 *   this module all bots can load BotNet, BotNet should
 *   detect if this module is loaded and disable?
 ***************************************************************************/
require_once('modules/Module.inc');

class BotNetHub extends Module {
    public $sock; //our listen socket
    public $bind;
    public $port;
    public $lUsers;
    public $lChans;
    public $iUsers;
    public $iChans;
    public $lGlines;
    public $buffer = Array();
    /*
     * $buffer[intval(sock)] = array of lines
     */

    function init() {
        $cf = $this->pMM->pConfig->getInfo();
        $this->bind = $cf['botnet']['host'][0];
        $this->port = $cf['botnet']['port'][0];
        $this->sock = $this->pSockets->createTCPListen($this, 'sRead', 'sError', 'sConnected', $cf['botnet']['host'][0], $cf['botnet']['port'][0], "\n");
    }

    function sRead ($sock, $line) {
        $id = intval($sock);
        //$data = makenice($line);
        //$temp = explode("\n", $data);
        //$last = array_pop($this->buffer[$id]);
        //$barf = array_shift($temp); // strlen check is needed
        //if($barf != '') {
           // if(strpos($last, "\r") === FALSE) { // Last line wasn't finished
            //    array_push($this->buffer[$id], $last . $barf);
            //} else {
            //    array_push($this->buffer[$id], $last);
            //    if(strpos($barf, "\r") !== FALSE)
            //        array_push($this->buffer[$id], $barf);
            //}
            //foreach($temp as &$line) {
                $this->lUsers[$id]->lineIn($line);
                //array_push($this->buffer[$id], $line);
            //}
       // }

    }

    public function logic() {
/*        $this->cleanbuffers();
        while($this->hasdata()) {
            $data = $this->getdata();
            $id = $data['id'];
            $line = $data['line'];
            $this->lUsers[$id]->lineIn($line);
        }
 *
 */
        if(is_array($this->lUsers)) {
            foreach($this->lUsers as $u) {
                $u->logic();
            }
        }
    }
 /*
    public function hasdata() {
        foreach($this->buffer as $id => $d) {
            //if($this->uhasdata($id)) return TRUE;
            if(!empty($this->buffer[$id])) return true;
        }
    }

    public function uhasdata($id) {
        $user = $this->buffer[$id];
        if(count($user) > 1) return TRUE;
        if(count($user) < 1) return FALSE;
        $i = 0;
        foreach($user as $l) {
            if($i == 0 && strpos($l, "\r") !== FALSE)
                return TRUE;
            $i++;
            if($i > 1) break;
        }
        return FALSE;
    }

    public function getdata() { // returns a line for parsing
        foreach($this->buffer as $id => $d) {
            if($this->uhasdata($id)) {
                return Array('id' => $id, 'line' => array_shift($this->buffer[$id]));
            }
        }
    }

    public function cleanbuffers() {
        $keys = array_keys($this->buffer);
        foreach($keys as &$id) {
            if(!is_array($this->buffer[$id])) {
                continue;
            }
            $k2 = array_keys($this->buffer[$id]);
            foreach($k2 as &$k) {
                if($this->buffer[$id][$k] == "") {
                    unset($this->buffer[$id][$k]);
                }
            }
        }
    }
*/
    function sError($sock, $error) {
        $ip = $this->pSockets->getIP($sock);
        $this->pIrc->msg('#bots', "Errr! \2IP:\2 $ip \2S:\2 $sock \2E:\2 $error");
    }

    function sConnected($sock, $ip) {
        $this->buffer[intval($sock)] = Array();
        $user = new lUser($ip, $sock, $this, $this->pSockets);
        $this->lUsers[intval($sock)] = $user;
    }

    function uClose($sock) {
        echo "[BotNetHub] uClose on $sock\n";
        $id = intval($sock);
        $this->pSockets->destroy($sock);
        unset($this->lUsers[$id]);
        unset($this->buffer[intval($sock)]);
    }
}

?>
