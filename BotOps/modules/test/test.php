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
 * test.php
 *  test module
 ***************************************************************************/

require_once('modules/Module.inc');

class test extends Module {

    function cmd_xmlrpc($nick, $chan, $msg) {
        $msg = explode(' ', $msg);
        $host = array_shift($msg);
        $method = array_shift($msg);
        $msg = implode(' ', $msg);
        eval('$msg = ' . $msg . ';');
        $lol = new Http($this->pSockets, $this, 'xmlresp');
        $lol->xmlrpcQuery($host, Array('chan' => $chan), $method, $msg);
    }
    
    public function xmlresp($data, $vars, $xmlrpc) {
        if(is_array($data)) {
            $this->pIrc->msg($vars['chan'], "\2XMLRPC Response:\2 Error ($data[0]) $data[1]");
            return;
        }
        $this->pIrc->msg($vars['chan'], "\2XMLRPC Response:\2 " . var_export($xmlrpc, true), false);
    }
    
    function cmd_sysinfo($nick, $chan, $msg) {
        /*
         * Hostname: Node01 - OS: Linux 2.6.18-308.4.1.el5/x86_64 - 
         * Distro: CentOS 5.8 - CPU: 24 x Intel Xeon (2926.097 MHz) - 
         * Processes: 350 - Uptime: 82d 19h 59m - Users: 1 - 
         * Load Average: 1.01 - Memory Usage: 40328.29MB/64448.64MB (62.57%) - 
         * Disk Usage: 82.43GB/661.71GB (12.46%)
         */
        $hostname = trim(`hostname`);
        $os = php_uname('s');
        $os .= ' ' . php_uname('r');
        $os .= ' ' . php_uname('m');
        $distro = trim(`cat /etc/issue`);
        $distro = str_replace(' \n \l', '', $distro);
        $cpu_model = `grep "model name" /proc/cpuinfo`;
        $cpu_model = explode("\n", $cpu_model);
        $cpu_model = explode(':', $cpu_model[0]);
        $cpu_model = trim($cpu_model[1]);
        $cpu_model = preg_replace('/\s+/', ' ', $cpu_model);
        
        $cpu_mhz = `grep "MHz" /proc/cpuinfo`;
        $cpu_mhz = explode("\n", $cpu_mhz);
        $cpu_mhz = explode(':', $cpu_mhz[0]);
        $cpu_mhz = trim($cpu_mhz[1]);
        $cpu_mhz = preg_replace('/\s+/', ' ', $cpu_mhz);
        
        $proc_num = trim(`ps ax | wc -l`) - 1;
        $uptime = explode(' ', trim(`cat /proc/uptime`));
        $uptime = Duration_toString($uptime[0]);
        $users = trim(`who | wc -l`);
        $load_avg = trim(`cat /proc/loadavg`);
        $load_avg = explode(' ', $load_avg);
        $load_avg = "$load_avg[0] $load_avg[1] $load_avg[2]";
        foreach(file('/proc/meminfo') as $ri)
            $m[strtok($ri, ':')] = trim(strtok(''));
        $mem_free = ($m['MemFree'] + $m['Buffers'] + $m['Cached']);
        $mem_used = $m['MemTotal'] - $mem_free;
        $mem_per = round(($mem_used / $m['MemTotal']) * 100);
        $mem_used = $mem_used * 1024;
        $mem_used = convert($mem_used);
        $mem_used = $mem_used + 0;
        $mem_total = $m['MemTotal'] * 1024;
        $mem_total = convert($mem_total);
        $df = explode("\n", `df`);
        $pat = '/(?P<dev>[^ ]+) *(?P<tot>[0-9]+) *(?P<used>[0-9]+) *(?P<free>[0-9]+) '.
                '*(?P<per>[0-9]+\%) *(?P<mount>.+)/';
        preg_match($pat, $df[1], $matches);
        $df = convert($matches['used'] * 1024) .'/'. convert($matches['tot'] * 1024) . " ($matches[per])" ;
        
        $this->pIrc->msg($chan, "\2Hostname:\2 $hostname \2OS:\2 $os \2Distro:\2 $distro" .
                " \2CPU:\2 $cpu_model (".$cpu_mhz."MHz) \2Processes:\2 $proc_num \2Uptime:\2 $uptime" .
                " \2Users:\2 $users \2Load Average:\2 $load_avg" .
                " \2Memory Usage:\2 $mem_used/$mem_total ($mem_per%) \2Disk Usage:\2 $df");
    }
    
    function rpc_test($params) {
        $this->pIrc->msg('#scorebots', "\2XMLRPC Request(test):\2 " . var_export($params, true), false);
    }
    
    function rpc_msg($params) {
        if(!is_array($params) || !isset($params[0]) || !isset($params[1])) {
            return 'Error msg takes two params target, msg';
        } 
        $target = $params[0];
        $msg = $params[1];
        $this->pIrc->msg($target, $msg);
    }
    
    function cmd_version($n, $c, $t)
    {
        $ver_rev  = `git log --format=%h -1`;
        $ver_date = `git log --format=%cd -1`;
        $phpv     = phpversion();
        $uname    = php_uname('s');
        $uname .= ' ' . php_uname('r');
        $uname .= ' ' . php_uname('m');
        $version  = "BotOps version 2.1, Commit#: $ver_rev Last Modified: $ver_date Running on $uname PHP Version $phpv";
        $this->pIrc->notice($n, $version);
    }

    function cmd_eval($nick, $target, $text) {
    //Setup our normal variables..
        $arg = explode(' ', $text);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
            return $this->gM('CmdReg')->rV['BADARGS'];
        }
        eval($text . ';');
        $this->pIrc->msg($target, "Done...");
    }

    function cmd_ced($nick, $target, $text) {
    //Setup our normal variables..
        $arg = explode(' ', $text);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
            return $this->gM('CmdReg')->rV['BADARGS'];
        }
        eval('$this->pIrc->msg($target, var_export(' . $text . ', true), false, true);' . ';');
        $this->pIrc->msg($target, "Done...");
    }

    function cmd_shell($nick, $target, $text) {
        if(empty($text)) {
            return $this->gM('CmdReg')->rV['BADARGS'];
        }
        $result = trim(`$text`);
        $this->pIrc->msg($target, $result, false);
        $this->pIrc->msg($target, "Done...");
    }
}

?>
