<?php
require_once(__DIR__ . '/../Module.inc');
//require_once 'CmdArgs.php';
//require_once 'CmdBind.php';
//require_once 'CmdFunc.php';
require_once 'CmdRequest.php';

use \Ayesh\CaseInsensitiveArray\Strict as CIArray;

/**
 * Keep track of our commands from modules and bindings to them
 */
class CmdReg extends Module {
    /**
     * @var CIArray ["module" => ["func" => CmdFunc]]
     */
    public CIArray $funcs;

    public CIArray $binds;


	public function __construct()
    {
        $this->funcs = new CIArray();
        $this->binds = new CIArray();
    }

    /*
     * Possible return values
     */
    public $rV = array(
    'OK' => 1,          //everything went fine
    'BADARGS' => 2,     //syntax used wrong
    'OVERRIDE' => 4,    //Overide was used
    'ERROR' => 8        //Problem while running we should do nothing
    );

    /*
     * show info about a command
     */

     public $lastCmdInfo = array('nick','target','args','access','hand','cmd','host');
     /*
      * has info from the last cmd being parsed
      * used for our $vars
      *
      */


    function v_args() { return $this->lastCmdInfo['args']; }
    function v_cmd() { return $this->lastCmdInfo['cmd']; }

    function rehash(&$old) {
         $this->binds = $old->binds;
         $this->funcs = $old->funcs;
         $this->catchers = $old->catchers;
         echo "CmdReg rehash finished\n";
     }

    //I might not want this as an IRC command due to large output, but keeping for now
    function cmd_showfuncs(CmdRequest $r)
    {
        if (isset($r->args['mod'])) {
            $mod = $r->args['mod'];
            if (!isset($this->funcs[$mod])) {
                throw new CmdException("$mod doesnt exist");
            }
            $keys = [];
            foreach ($this->funcs[$mod] as $k => $v) $keys[] = $k;
            $funcs = implode(' ', array_keys($keys));
            $r->notice("Functions for $mod: $funcs");
            return;
        }
        $out[] = Array('module', 'funcs');
        foreach ($this->funcs as $m => $d) {
            $keys = [];
            foreach ($d as $k => $v) $keys[] = $k;
            $out[] = Array($m, implode(' ', $keys));
        }
        $out = multi_array_padding($out);
        foreach ($out as &$line) {
            $r->notice(implode('', $line), 0, 1);
        }
    }

    function cmd_cmdhistory(CmdRequest $r)
    {
        //In future maybe customize search
        $list = $this->gM('logs')->getLogs('CmdReg', Array('target' => Array('=', $r->chan)));
        if (empty($list)) {
            $r->notice("No results found");
            return;
        }
        $list = array_reverse($list);
        foreach ($list as $i) {
            $d = strftime('%D %T', $i['date']);
            $r->notice("[$d] ($i[bot]:$i[target]) [$i[nick]:$i[hand]]: $i[cmd] $i[msg]", 0, 1);
        }
    }

    function cmd_unbind(CmdRequest $r)
    {
        $bind = $r->args['bind'];
        if (!isset($this->binds[$bind])) {
            throw new CmdException("binding $bind not found.");
        }

        //TODO make sure bind is in DB then mark it as deleted. Should prevent it from returning on next bot start
        $stmt = $this->pMysql->prepare("DELETE FROM `Binds` WHERE `bname` = :bind");
        $stmt->bindValue(':bind', $bind);
        $stmt->execute();
        $stmt->closeCursor();

        unset($this->binds[$bind]);
        $this->gM('xnet')->sendToAll(null, null, 'unbind', Array($bind));
        $r->notice("Binding $bind removed.");
    }

    function cmd_modcmd(CmdRequest $r)
    {
        $bind = $r->args['bind'];
        if (!isset($this->binds[$bind])) {
            throw new CmdException("binding $bind not found.");
        }
        if (!isset($r->args['setting'])) {
            $this->modcmdShow($r, $bind);
            return;
        }
        $option = strtolower($r->args['setting']);
        $aoptions = Array('args', 'loglvl', 'access', 'module', 'func', 'name');
        if (!in_array($option, $aoptions)) {
            throw new CmdException("Please choose from: " . implode(', ', $aoptions));
        }
        $value = $r->args['value'];
        if (!$value) {
            throw new CmdException("Please specify a new value.");
        }

        //TODO Need to do some validation here
        $this->binds[$bind]->$option = $value;
        /**
         * @var CmdBind $bi
         */
        $bi = $this->binds[$bind];
        //TODO can we send an object here?
        $this->gM('xnet')->sendToAll(null, null, 'modcmd', Array($bind, $this->binds[$bind]));

        $stmtd = $this->pMysql->prepare("DELETE FROM `Binds` WHERE `bname` = :bind");
        $stmtd->bindValue(':bind', $bind);
        $stmtd->execute();
        $stmtd->closeCursor();
        //do we really want to reset used counter?
        $stmti = $this->pMysql->prepare("INSERT INTO `Binds` (bname,classname,used,access,args,log,func)" .
            " VALUES(:bind,:class_name,0,:access,:args,:log,:func)");
        $stmti->bindValue(':bind', $bind);
        $stmti->bindValue(':class_name', $bi->module);
        $stmti->bindValue(':access', $bi->access ?? 0);
        $stmti->bindValue(':args', $bi->args ?? '');
        $stmti->bindValue(':loglvl', $bi->loglvl ?? 0);
        $stmti->bindValue(':func', $bi->func);
        $stmti->execute();
        $stmti->closeCursor();
        $r->notice("Bind $bind has been updated", 0, 1);
    }

    function modcmdShow(CmdRequest $r, $bname)
    {
        if (!isset($this->binds[$bname])) {
            throw new CmdException("That bind doesn't exist.");
        }
        /**
         * @var CmdBind $bind
         */
        $bind = $this->binds[$bname];
        /**
         * @var CmdFunc $func
         */
        $func = $this->funcs[$bind->module][$bind->func];
        $r->notice("\2Module:\2 $bind->module \2Bind:\2 $bind->name \2pmonly:\2 $func->pmonly " .
            "\2Func:\2 $bind->func \2Args:\2 $bind->args \2Loglvl:\2 $bind->loglvl " .
            "\2Access:\2 $bind->access \2Syntax:\2 $func->syntax \2Desc:\2 $func->desc", 0, 1);
    }

    function rpc_unbind($p)
    {
        unset($this->binds[$p[0]]);
    }

    function rpc_bind($p)
    {
        $this->binds[$p[0]] = $p[1];
    }

    function rpc_modcmd($p)
    {
        $this->binds[$p[0]] = $p[1];
    }

    function cmd_bind(CmdRequest $r)
    {
        $bind = $r->args['bind'];
        $mod = $r->args['module'];
        $func = $r->args['function'];
        $args = $r->args['function'];
        $access = '0';
        $loglvl = 0;

        if (isset($this->binds[$bind])) {
            $r->notice("Warning! binding $bind already found, will update it to $mod.$func");
            $stmt = $this->pMysql->prepare("DELETE FROM `Binds` WHERE `bname` = :bind");
            $stmt->bindValue(':bind', $bind);
            $stmt->execute();
            $stmt->closeCursor();
            unset($this->binds[$bind]);
        }

        $this->setBind($mod, $bind, $func, $access, $args, $loglvl);

        $this->gM('xnet')->sendToAll(null, null, 'bind', Array($bind, $this->binds[$bind]));

        $stmti = $this->pMysql->prepare("INSERT INTO `Binds` (bname,classname,used,access,args,log,func)" .
            " VALUES(:bind,:class_name,0,:access,:args,:log,:func)");
        $stmti->bindValue(':bind', $bind);
        $stmti->bindValue(':class_name', $mod);
        $stmti->bindValue(':access', $access);
        $stmti->bindValue(':args', $args);
        $stmti->bindValue(':log', $loglvl);
        $stmti->bindValue(':func', $func);
        $stmti->execute();
        $stmti->closeCursor();

        $r->notice("Finished binding $bind to $mod.$func");
        $this->modcmdShow($r, $bind);
    }

    function cmd_command(CmdRequest $r) {
        $name = $r->args['command'];
        if(!isset($this->binds[$name])) {
            throw new CmdException("Command binding $name not found.");
        }
        /**
         * @var CmdBind $bind
         */
        $bind = $this->binds[$name];
        /**
         * @var CmdFunc $func
         */
        $func = $this->funcs[$bind->module][$bind->func];
        $r->notice("$bind->name is a binding of {$bind->module}.{$bind->func}, using args: $bind->args");
        $r->notice("$bind->name has been used " . $this->getUsed($bind->name) . " times. You need access $bind->access to use.");
        $r->notice("Syntax for $func->name: $func->syntax Description: $func->desc");
    }

    //Slot for module unloaded
    function unloaded($args) {
    //cleanup our binds here
        $name = $args['name'];
        unset($this->funcs[$name]);
        foreach($this->catchers as $key => $catch) {
            if($catch['module'] == $name) {
                unset($this->catchers[$key]);
            }
        }
    }

    //modules that catch unknown commands
    public array $catchers = Array();
    function reloaded($args) {
        echo "CmdReg unloading module {$args['name']} for reload\n";
        $this->unloaded($args);
        $this->loaded($args);
    }

    public bool $bindsLoaded = false;

    /**
     * Slot for module loaded
     * @param $args
     * @throws Exception
     */
    function loaded($args) {
        echo "CmdReg loading module {$args['name']}\n";
        $this->loadBinds();
        $info = $this->pMM->getConf($args['name'], 'CmdReg');
        $module = $args['name'];
        if($info == null) return;

        //check if module wants to catch unknown commands
        //notice the first module that returns true stop all other mods
        //from seeing that command
        if(array_key_exists('catch', $info) && $info['catch'] != null) {
            $this->catchers[] = Array('module' => $module, 'func' => $info['catch']);
        }
        
        if(array_key_exists('funcs', $info)) {
            if(!is_array($info['funcs'])) {
                throw new Exception("funcs section is not an array");
            } else {
                foreach ($info['funcs'] as $func => $f) {
                    $this->addFunc($module, $func, $f);
                }
            }
        }
        if(array_key_exists('binds', $info)) {
            if(!is_array($info['binds'])) {
                throw new Exception("binds section is not an array");
            }
            foreach($info['binds'] as $bind => $b) {
                if(!isset($b['func'])) {
                    throw new Exception('bind $bind is missing func');
                } else {
                    $access = (string) ($b['access'] ?? '0');
                    $args = $b['args'] ?? '';
                    $loglvl = (int) ($b['loglvl'] ?? 0);
                    $this->initialBind($module, $bind, $b['func'], $access, $args, $loglvl);
                }
            }
        }
    }

    /**
     * @param string $module
     * @param string $name
     * @param array $f
     * @throws Exception
     */
    function addFunc(string $module, string $name, array $f) {
        if(!isset($f['desc']) || $f['desc'] == '') {
            throw new Exception("Function given without description");
        }
        //We should get a TypeError if these are wrong
        $pmonly = $f['pmonly'] ?? false;
        $needchan = $f['needchan'] ?? false;
        $func = new CmdFunc($module, $name, $f['desc'], $pmonly, $needchan);
        $func->syntax = $f['syntax'] ?? '';

        echo "CmdReg adding func $name for $module\n";
        if(!isset($this->funcs[$module])) {
            $this->funcs[$module] = new CIArray();
        }
        $this->funcs[$module][$name] = $func;
    }

    function isBindInMysql($bname) {
        try {
            $stmt = $this->pMysql->prepare("SELECT count(*) FROM `Binds` WHERE `bname` = :bname");
            $stmt->bindValue(':bname', $bname);
            $stmt->execute();
            $res = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        if((int)$res['count(*)'] > 0) {
            return true;
        }
        return false;
    }

    function setBind(string $module, string $name, string $func, string $access, string $args, int $loglvl) {
        $bind = new CmdBind($module, $name, $func);
        $bind->access = $access;
        $bind->args  = $args;
        $bind->loglvl = $loglvl;
        $this->binds[$name] = $bind;
    }

    //Binds loaded from reading module.neon
    function initialBind(string $module, string $name, string $func, string $access, string $args, int $loglvl) {
        if(isset($this->binds[$name])) {
            return;
        }

        //first check if a bind bname exists in our binds db
        if($this->isBindInMysql($name)) {
            $this->loadBinds();
            return;
        }

        $this->setBind($module, $name, $func, $access, $args, $loglvl);

        //TODO maybe dont save bind in mysql except when created/modded in irc
        //TODO rename the mysql cols to better match code
        try {
            $stmti = $this->pMysql->prepare("INSERT INTO `Binds` (bname,classname,used,access,args,log,func)" .
                    " VALUES(:bind,:class_name,0,:access,:args,:log,:func)");
            $stmti->bindValue(':bind', $name);
            $stmti->bindValue(':class_name', $module);
            $stmti->bindValue(':access', $access);
            $stmti->bindValue(':args', $args);
            $stmti->bindValue(':log', $loglvl);
            $stmti->bindValue(':func', $func);
            $stmti->execute();
            $stmti->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            //$this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    //Load all the binds from db into mem
    function loadBinds() {
        if(!$this->bindsLoaded) {
            $this->bindsLoaded = true;
        } else {
            return;
        }
        //load them all even if the functions dont exist
        //we can check if they do when attempting to call
        try {
            foreach($this->pMysql->query("SELECT * FROM `Binds`") as $row) {
                $name = strtolower($row['bname']);
                $this->setBind($row['classname'], $name, $row['func'], $row['access'], $row['args'], $row['log']);
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            //$this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function getUsed($bind) {
        if(!isset($this->binds[$bind])) {
            return 0;
        }
        
        try {
            $stmt = $this->pMysql->prepare("SELECT * FROM `Binds` WHERE `bname` = :bind");
            $stmt->bindValue(':bind', $bind);
            $stmt->execute();
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            return 0;
        }
        
        return $row['used'];
    }

    /**
     * Quick routine to check if access is met
     * 
     * This function will return a string with why access wasn't met
     * or it will return 0 for access being met normally and -1 if
     * the access was met using staff override
     * 
     * @param int $caxs Their access in the channel 
     * @param string $hand Account Handle
     * @param string|int $raxs Required Access
     * @return string|int
     */
    function hasAxs($caxs, $hand, $raxs) {
        $override = false; //was overide needed for this
        if(!is_numeric($raxs)) { // access is a flag, means staff access
            if($hand == '') {
                return 'You are not authed to BotOps, To auth do /msg ' . $this->pIrc->currentNick() .' AUTH username password';
            }
            if(!$this->gM('user')->hasflags($hand, $raxs)) {
                return "You do not have access to this BotOps staff command.";
            }
        } else { // chan access
            if(!($raxs <= 0)) {// everyone can use if axs <= 0
                if($hand == '') {
                    return 'You are not authed to BotOps, To auth do /msg ' . $this->pIrc->currentNick() . ' AUTH username password';
                }
                if($caxs < $raxs && !$this->gM('user')->hasOverride($hand)) {// no access
                    return "You need at least $raxs access.";
                } else {
                    if($caxs < $raxs) {
                        $override = true;
                    }
                }
            }
        }
        if($override) {
            return -1;
        } else {
            return 0;
        }
    }

    function SendToMods($cmd,$nick,$chan,$arg2) {
        foreach($this->catchers as $catch) {
            if(method_exists($this->gM($catch['module']), $catch['func'])) {
                $rv = $this->gM($catch['module'])->{$catch['func']}($cmd,$nick,$chan,$arg2);
                if($rv != null && $rv != false) {
                    return $rv;
                } else {
                    return false;
                }
            }
        }
        return false;
    }
    
    function cmd_gag(CmdRequest $r) {
        $ok = $this->gag($r->args['hostmask'], $r->args['duration'], $r->args['reason'], $r->account);
        if($ok != null) {
            throw new CmdException($ok);
        }
        $r->notice("{$r->args['hostmask']} is now gaged!");
    }
    
    function cmd_isgag(CmdRequest $r) {
        $active = $this->isGag($r->args['host']);
        if($active === false) {
            $r->notice("No gags found for that host (does not acces masks)");
            return;
        }
        foreach($active as $gag) {
            if($gag['expires'] != 0) {
                $left = Duration_toString($gag['expires'] - time());
            } else {
                $left = 'Never';
            }
            $r->notice("Gag[$gag[id]] \2HostMask:\2 $gag[host] \2Expires:\2 $left \2From:\2 $gag[from] \2Reason:\2 $gag[reason]");
        }
    }
    
    function gag($host, $duration, $why, $from) {
        if($duration && $duration != 0) {
            $duration = string2Seconds($duration);
            if(!is_numeric($duration)) {
                return $duration;
            }
            $expires = time() + $duration;
        } else {
            $expires = 0;
        }

        $stmt = $this->pMysql->prepare("INSERT INTO `CmdReg_gags` (`host`,`from`,`expires`,`reason`)".
                " VALUES(:host,:from,:expires,:why)");
        $stmt->bindValue(':host', str_replace('*', '%', $host));
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':expires', $expires);
        $stmt->bindValue(':why', $why);
        $stmt->execute();
        $stmt->closeCursor();
    }
    
    function ungag($id) {
        $stmt = $this->pMysql->prepare("DELETE FROM `CmdReg_gags` WHERE `id` = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $stmt->closeCursor();
    }
    
    function isGag($host) {
        $active = Array();
        $stmt = $this->pMysql->prepare("SELECT * FROM `CmdReg_gags` WHERE :host LIKE host");
        $stmt->bindValue(':host', $host);
        $stmt->execute();
        while ($gag = $stmt->fetch()) {
            if ($gag['expires'] != 0 && $gag['expires'] < time()) {
                $this->ungag($gag['id']);
            } else {
                $active[] = $gag;
            }
        }
        if(count($active) > 0) {
            return $active;
        } else {
            return false;
        }
    }

    /**
     * Checks if this counts as a command
     * commands start with botnick or trigger
     * @param $target
     * @param $args
     * @return string|null null if not cmd, otherwise cmd
     */
    function checkChannelCmd($target, &$args): ?string {
        $trig = $this->gM('channel')->getTrig($target);
        //Check if botnick is trigger
        if( strtolower($args[0]) != strtolower($this->pIrc->currentNick()) &&
            strtolower($args[0]) != strtolower($this->pIrc->currentNick()) . ',' &&
            strtolower($args[0]) != strtolower($this->pIrc->currentNick()) . ':'
        ) {
            if($trig == substr($args[0], 0, strlen($trig))) {
                $args[0] = substr($args[0], strlen($trig));
                return $args[0];
            }
        } else {
            array_shift($args);
            if (count($args) == 0 || empty($args[0])) {
                return null;
            }
            return $args[0];
        }
        return null;
    }
    
    public bool $wascmd = false;
    function inmsg($nick, $target, $text) {
        $host = $this->pIrc->n2h($nick);
        if($this->isGag("$nick!$host") !== false) {
            return;
        }
        $args = explode(' ', $text);
        if($text == NULL || count($args) == 0 || empty($args[0])) {
            return;
        }

        if($target[0] == '#') {
            $via = 'chan';
            $cmd = $this->checkChannelCmd($target, $args);
            if ($cmd == null) {
                $this->wascmd = false;
                return;
            }
        } else {
            $via = 'pm';
            $cmd = $args[0];
        }
        array_shift($args);
        if($cmd[0] == '#') {
            $target = $cmd;
            $cmd = $args[0];
            if($cmd == '') {
                return;
            }
            array_shift($args);
        }

        $text = implode(' ', $args);
        $this->wascmd = true;
        $this->lastCmdInfo = array(
            'args' => $text,
            'cmd' => $cmd,
        );
        if(!isset($this->binds[$cmd])) {
            //check if another module can do something for this (clanbot)
            $this->gM('ParseUtil')->setArgy(argClean(explode(' ', $text)));
            if(!$this->SendToMods($cmd, $nick, $target, $text)) {
                $this->wascmd = false;
            }
            return;
        }
        //Check if cmd can be come from via
        /**
         * @var CmdBind $bind
         */
        $bind = $this->binds[$cmd];
        if(!isset($this->funcs[$bind->module][$bind->func])) {
            echo "Bind $cmd called but missing function $bind->module $bind->func\n";
            return;
        }
        /**
         * @var CmdFunc $func
         */
        $func = $this->funcs[$bind->module][$bind->func];
        if($via != 'pm') {
            if($func->pmonly) {
                return;
            }
        }
        if($via == 'pm') {
            if($func->needchan && $target[0] != '#') {
                $this->pIrc->notice($nick, "$cmd requires a channel to operate.");
                return;
            }
        }

        $hand = $this->gM('user')->byNick($nick);
        $access = $this->gM('user')->access($hand, $target);
        $axs = $bind->access;
        $override = false; //was overide needed for this
        $hasoverride = $this->gM('user')->hasOverride($hand);
        if(!is_numeric($axs) && $axs != '') { // access is a flag, means staff access
            /*
             * access could be o - isopped or v -isvoiced
             */
            if(cisin($axs, 'v') && ((!$this->pIrc->isop($target, $nick)
                    || !$this->pIrc->isvoice($target, $nick)) ||
                    ($access < $axs && !$hasoverride))) {
                $this->pIrc->notice($nick, "You need access voice or higher in $target to use this command");
                return;
            } else {
                if((!$this->pIrc->isop($target, $nick)
                    || !$this->pIrc->isvoice($target, $nick)) && $access < $axs) {
                    $override = true;
                }
                $axs = str_replace('v', '', $axs);
            }
            if(cisin($axs, 'o') && (!$this->pIrc->isop($target, $nick)
                    || ($access < $axs && !$hasoverride))) {
                $this->pIrc->notice($nick, "You need access ops or higher in $target to use this command");
                return;
            } else {
                if(!$this->pIrc->isop($target, $nick) && $access < $axs) {
                    $override = true;
                }
                $axs = str_replace('o', '', $axs);
            }
            if($hand == '') {
                $this->pIrc->notice($nick, 'You are not authed to BotOps, To auth do /msg ' . $this->pIrc->currentNick() . ' AUTH username password');
                return;
            }
            if(!$this->gM('user')->hasflags($hand, $axs)) {
                $this->pIrc->notice($nick, "You do not have access to this BotOps staff command.");
                return;
            } else {
                $override = true;
            }
        } else { // chan access
            if(!($axs <= 0)) {// everyone can use if axs <= 0, handles ''
                if($hand == '') {
                    $this->pIrc->notice($nick, 'You are not authed to BotOps, To auth do /msg ' . $this->pIrc->currentNick() . ' AUTH username password');
                    return;
                }
                if($access < $axs && !$hasoverride) {// no access
                    $this->pIrc->notice($nick, "You need at least $axs access in $target to use $cmd.");
                    return;
                } else {
                    if($access < $axs) {
                        $override = true;
                    }
                }
            }
        }

        $class = $this->gM($this->pMM->getCname($bind->module));
        //TODO handle return values and log usage
        if(!method_exists($class, $func->name)) {
            $this->pIrc->notice($nick, "Error: failed to access $bind->module.$func->name");
            echo "CmdReg: failed to access $bind->module.$func->name\n";
            return;
        } else {

            try {
                $stmt = $this->pMysql->prepare("UPDATE Binds set used=used+1 where bname = :bname");
                $stmt->bindValue(':bname', strtolower($cmd));
                $stmt->execute();
                $stmt->closeCursor();
            } catch (PDOException $e) {
                $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
                echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
                $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            }
            $this->gM('ParseUtil')->setArgy(argClean(explode(' ', $text)));

            $pmed = false;
            if($via == 'pm') {
                $pmed = true;
            }
            if($target[0] != '#') {
                $target = null;
                $access = $this->gM('user')->flags($hand);
            }
            $calla = Array($class, $func, $nick, $host, $hand, $target, $bind, $override, $pmed, $access, $hasoverride);
            if($bind->args != '') {
                $this->gM('ParseUtil')->parse($bind->args, 'pucb', $this, $calla);
                return;
            }
            $this->pucb($text, $calla);
        }
    }

    function pucb($text, $x) {
        list($class, $func, $nick, $host, $hand, $target, $bind, $override, $pmed, $access, $hasoverride) = $x;
        $cmd = $bind->name;
        try {
            $args = new CmdArgs($func->syntax);
            $args->parse($text);
        } catch(CmdParseException $e) {
            $this->pIrc->notice($nick, "Internal Error {$e->getMessage()} for $bind->module.$func->name", 1, 1);
            return;
        } catch(CmdSyntaxException $e) {
            $this->pIrc->notice($nick, "Error: {$e->getMessage()} BindArgs: $bind->args FuncSyntax: $func->name $func->syntax", 1, 1);
            return;
        }

        //TODO most of this could be replaced with an object after we update user system and IRC system
        $r = new CmdRequest($this->pIrc, $nick, $pmed, $target, $args, $host, $hand, $access, $hasoverride);

        try {
            /**
             * @var CmdRequest $retval
             */
            $retval = $class->{$func->name}($r);
        } catch (CmdException $e) {
            if ($e->getMessage()) {
                $msg = "$bind->name Error: {$e->getMessage()}";
                if ($e->asReply) {
                    $this->pIrc->msg($target, $msg, 0, 1);
                } else {
                    $this->pIrc->notice($nick, $msg, 0, 1);
                }
            }
            return;
        } catch (CmdArgsException $e) {
            //TODO improve output if bindargs null
            $msg = "$bind->name Error: {$e->getMessage()} BindArgs: $bind->args FuncSyntax: $func->name $func->syntax";
            if($e->asReply) {
                $this->pIrc->msg($target, $msg, 0, 1);
            } else {
                $this->pIrc->notice($nick, $msg, 0, 1);
            }
            return;
        } catch (PDOException $e) {
            //TODO replace this when we start using a proper logger
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");

            $this->pIrc->notice($nick, "$bind->name MySQL Error: {$e->getMessage()}", 1, 1);
        }

        $logins = Array(
            'date' => microtime_float(),
            'cmd' => strtolower($cmd),
            'override' => (int)$override,
            'nick' => $nick,
            'hand' => $hand,
            'target' => $target,
            'host' => $host,
            'msg' => $text,
            'bot' => $this->pIrc->nick
        );

        if(is_object($retval) && get_class($retval) == 'CmdRequest' && $retval->override) {
            $logins['override']  = 1;
        }

        if($bind->loglvl > 0) {
            //global comand with first arg as target
            //TODO with the new system many cmds need to change their loglvl? because first arg may no longer be target
            //perhaps store the target in the CmdRequest returned
            //or just make a whole new log system
            if($bind->loglvl == 2) {
                if($args[0] != '') {
                    $logins['target'] = $args[0];
                }
            }
            //global command with no target
            if($bind->loglvl == 3 ) {
                $logins['target'] = '';
            }
            $this->gM('logs')->log('CmdReg', $logins);
        }

    }
}

