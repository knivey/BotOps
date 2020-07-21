<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';
require_once('modules/Module.inc');

class test extends Module {
    function cmd_sysinfo(CmdRequest $r) {
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
        
        $cpu_cores = `grep "cpu cores" /proc/cpuinfo`;
        $cpu_cores = explode("\n", $cpu_cores);
        $cpu_cores = explode(':', $cpu_cores[0]);
        $cpu_cores = trim($cpu_cores[1]);
        $cpu_cores = preg_replace('/\s+/', ' ', $cpu_cores);
        
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
        $df = explode("\n", `df /`);
        $pat = '/^.+ +(?P<tot>[0-9]+) +(?P<used>[0-9]+) +(?P<free>[0-9]+) +(?P<per>[0-9]+\%) +.+$/';
        preg_match($pat, $df[1], $matches);
        $df = convert($matches['used'] * 1024) .'/'. convert($matches['tot'] * 1024) . " ($matches[per])" ;
        
        $r->reply("\2Hostname:\2 $hostname \2OS:\2 $os \2Distro:\2 $distro" .
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
    
    function cmd_version(CmdRequest $r)
    {
        $ver_rev  = `git log --format=%h -1`;
        $ver_date = `git log --format=%cd -1`;
        $phpv     = phpversion();
        $uname    = php_uname('s');
        $uname .= ' ' . php_uname('r');
        $uname .= ' ' . php_uname('m');
        $version  = "BotOps version 2.1, Commit#: $ver_rev Last Modified: $ver_date Running on $uname PHP Version $phpv";
        $r->notice($version);
    }

    function cmd_eval(CmdRequest $r) {
        eval($r->args[0] . ';');
        $r->reply("Done...");
    }

    function cmd_ced(CmdRequest $r) {
        eval('$this->pIrc->msg($r->chan, var_export(' . $r->args[0] . ', true), false, true);' . ';');
        $r->reply("Done...");
    }

    function cmd_shell(CmdRequest $r) {
        $result = trim(`{$r->args[0]}`);
        $r->reply($result, 0, 1);
        $r->reply("Done...");
    }
}


