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
 * BotNetHub/lChan.php
 *   Class for botnet channels. pretty much barebones iChan holds channels
 *   that relay/store IRC channels
 ***************************************************************************/

class adventure extends Module {
    public $chans = Array(); //proc fd's indexed by chans currently playing

    public function cmd_startadv($nick, $chan, $txt) {
        if($this->isRunning($chan)) {
            $this->pIrc->msg($chan, "adventure game is already runnning");
            return $this->gM('CmdReg')->rV['OK'];
        }
        $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
                1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
                2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
        );

        $cwd = '/home/bots/adventure/';

        if(!file_exists($cwd . md5($chan))) {
            $process = proc_open('adventure', $descriptorspec, $pipes, $cwd);
        } else {
            $process = proc_open('adventure ' . md5($chan), $descriptorspec, $pipes, $cwd);
        }

        if(!is_resource($process)) {
            $this->pIrc->msg($chan, "adventure game had an error..");
            return $this->gM('CmdReg')->rV['ERROR'];
        }

        $this->chans[$chan]['proc'] = $process;
        $this->chans[$chan]['pipes'] = $pipes;
        // $pipes now looks like this:
        // 0 => writeable handle connected to child stdin
        // 1 => readable handle connected to child stdout
        // Any error output will be appended to /tmp/error-output.txt
        //$lol = stream_get_contents($pipes[1]);
        //fwrite($pipes[0], "no\n");
    }

    function cmd_stopadv($nick, $chan, $txt) {
        if(!$this->isRunning($chan)) {
            $this->pIrc->msg($chan, "adventure game is not runnning");
            return $this->gM('CmdReg')->rV['OK'];
        }
        fwrite($pipes[0], "suspend\nyes\n" . md5($chan) . "\n");

        fclose($this->chans[$chan]['pipes'][0]);
        fclose($this->chans[$chan]['pipes'][1]);

        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        $return_value = proc_close($process);

        $this->pIrc->msg($chan, "adventure game killed.");
    }

    public function cmd_adv($nick, $chan, $txt) {
        if(!$this->isRunning($chan)) {
            $this->pIrc->msg($chan, "adventure game is not runnning");
            return $this->gM('CmdReg')->rV['OK'];
        }
        fwrite($this->chans[$chan]['pipes'][0], $txt . "\n");
    }

    public function logic() {
        //ob_flush();
        //flush();
        //$read = Array();
        foreach ($this->chans as $ch => $d) {
            //$read[] = $d['pipes'][1];
            $r = $d['pipes'][1];
            $ln = trim(stream_get_contents($r));
            if($ln != '') {
                $this->pIrc->msg($ch, $ln);
            }
        }
        /*$write = array();
        $except = array();
        stream_select($read, $write, $except, 0,  20);
        foreach ($read as $r) {
            $ch = $this->getRChan($r);
            $ln = trim(stream_get_line($r, 512));
            if($ln != '') {
                $this->pIrc->msg($ch, $ln);
            }
        }*/
    }

    public function getRChan($read) {
        foreach ($this->chans as $ch => $d) {
            if(intval($read) == intval($d['pipes'][1])) {
                return $ch;
            }
        }
    }

    public function isRunning($chan) {
        if (!array_key_exists($chan, $this->chans)) {
            return false;
        }
        $status = proc_get_status($this->chans[$chan]['proc']);
        return $status["running"];
    }

}

?>
