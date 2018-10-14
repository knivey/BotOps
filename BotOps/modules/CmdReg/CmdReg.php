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
 * CmdReg.php
 *  Keep track of our commands from modules and bindings to them
 ***************************************************************************/
require_once('modules/Module.inc');


class CmdReg extends Module {
    public $funcs;
	/* Store the funcs section
	 * $funcs[mod_name][name] = Array();
	 * [class] = reference to the class
         * [class_name] = modified name of class
	 * [name] = our name for the func
	 * [func] = name of the func in the class
	 * [syntax]
	 * [description]
	 */

    //    name access func "syntax" "description" "args"
    public $binds = Array();
	/* $binds[bindname] = Array()
	 * [bname] = name of bind
	 * [class_name] = name of the class
	 * [func] = name of the function ($this->funcs[classname][name])
	 * [args]
	 * [log] = log level
	 * [access]
	 * [syntax]
	 * [description]
	 */

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

     function cmd_showfuncs($nick, $target, $args) {
        $arg = explode(' ', $args);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $hflags = $this->gM('user')->flags($hand);
        $chan = strtolower($target);
        $access = $this->gM('user')->access($hand, $chan);
        if(!empty($arg[0])) {
            $mod = $arg[0];
            if(!array_key_exists($mod, $this->funcs)) {
                return $this->rV['ERROR'];;
            }
            $funcs = implode(' ', array_keys($this->funcs[$mod]));
            $this->pIrc->notice($nick, "Functions for $mod: $funcs");
        } else {
            $out[] = Array('module', 'funcs');
            foreach($this->funcs as $m => $d) {
                $chan = Array();
                $pm = Array();
                foreach(array_keys($d) as $lol) {
                    if(substr($lol, 0, 4) == 'chan') {
                        $chan[] = substr($lol, 4);
                    }
                    if(substr($lol, 0, 2) == 'pm') {
                        $pm[] = substr($lol, 2);
                    }
                }
                $out[] = Array($m,'Chan: ' . implode(' ', $chan) . ' Pm: ' . implode(' ', $pm));
            }
            $out = multi_array_padding($out);
            foreach($out as &$line) {
                $this->pIrc->notice($nick, implode('', $line));
            }
            return $this->rV['OK'];
        }
     }
     
     function cmd_cmdhistory($nick, $chan, $msg) {
         list($argc, $argv) = niceArgs($msg);
         //maybe customize search
         $rv = $this->OK;
         if($argc == 1) {
             $chan = $argv[0];
             $hand = $this->gM('user')->byNick($nick);
             $caccess = $this->gM('user')->access($hand, $chan);
             $hasaxs = $this->hasAxs($caccess, $hand, 4);
             if(!is_numeric($hasaxs)) {
                 $this->pIrc->notice($nick, $hasaxs);
             } else {
                 if($hasaxs == -1) {
                     $rv = $this->OVERRIDE;
                 }
             }
         }
         $list = $this->gM('logs')->getLogs('CmdReg', Array('target' => Array('=', $chan)));
         if(empty($list)) {
             $this->pIrc->notice($nick, "No results found");
             return $rv;
         }
         $list = array_reverse($list);
         foreach($list as $i) {
             $d = strftime('%D %T', $i['date']);
             $this->pIrc->notice($nick, "[$d] ($i[bot]:$i[target]) [$i[nick]:$i[hand]]: $i[cmd] $i[msg]", 0, 1);
         }
         return $rv;
     }
     
     function cmd_unbind($nick, $target, $args) {
        $arg = explode(' ', $args);
        //$host = $this->pIrc->n2h($nick);
        //$hand = $this->gM('user')->byHost($host);
        //$hflags = $this->gM('user')->flags($hand);
        //$access = $this->gM('user')->access($hand, $chan);
        // ^ later we may consider lowering access and we will need this to
        // prevent O from modifying A binds
        if(empty($arg[1])) {
            return $this->rV['BADARGS'];
        }
        $via = strtolower($arg[0]);
        if($via != 'pm' && $via != 'chan') {
            return $this->rV['BADARGS'];
        }
        $bind = strtolower($arg[1]);
        if(!$this->IsBound($via, $bind)) {
            $this->pIrc->notice($nick, "Warning! binding $via $bind not found.");
            return $this->rV['ERROR'];
        }
        
        try {
            $stmt = $this->pMysql->prepare("DELETE FROM `Binds` WHERE `bname` = :bind AND `onlyFrom` = :via");
            $stmt->bindValue(':bind', $bind);
            $stmt->bindValue(':via', $via);
            $stmt->execute();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        
        unset($this->binds[$via.$bind]);
        $this->gM('xnet')->sendToAll(null, null, 'unbind', Array($via.$bind));
        $this->pIrc->notice($nick, "Binding $via $bind removed.");
     }
     
     function cmd_modcmd($nick, $chan, $args) {
         list($argc, $argv) = niceArgs($args);
         //modcmd via bname [option] [value]
         if($argc < 2) {
             return $this->BADARGS;
         }
         $via = strtolower($argv[0]);
         $bind = strtolower($argv[1]);
         if(!$this->IsBound($via, $bind)) {
             $this->pIrc->notice($nick, "Warning! binding $via $bind not found.");
             return $this->rV['ERROR'];
         }
         if($argc < 3) {
             $this->modcmdShow($nick, $via . $bind);
             return $this->ERROR;
         }
         $option = strtolower($argv[2]);
         $aoptions = Array('args','log','access','syntax','description','class_name');
         if(!in_array($option, $aoptions)) {
             $this->pIrc->notice($nick, "Please choose from: ". implode(', ', $aoptions));
             return $this->ERROR;
         }
         if($argc < 4) {
             $this->pIrc->notice($nick, "Please specify a new value.");
             return $this->ERROR;
         }
         $value = arg_range($argv, 3, -1);
         $bi = $this->binds[$via.$bind];
         $bi[$option] = $value;
         $this->binds[$via.$bind] = $bi;
         $this->gM('xnet')->sendToAll(null, null, 'modcmd', Array($via.$bind, $this->binds[$via.$bind]));
         try {
             $stmtd = $this->pMysql->prepare("DELETE FROM `Binds` WHERE `bname` = :bind AND `onlyFrom` = :via");
             $stmtd->bindValue(':bind', $bind);
             $stmtd->bindValue(':via', $via);
             $stmtd->execute();
             $stmtd->closeCursor();
             $stmti = $this->pMysql->prepare("INSERT INTO `Binds` (bname,classname,used,access,args,log,func,syntax,description,onlyFrom)".
                     " VALUES(:bind,:class_name,0,:access,:args,:log,:func,:syntax,:description,:via)");
             $stmti->bindValue(':bind', $bind);
             $stmti->bindValue(':class_name', $bi['class_name']);
             $stmti->bindValue(':access', $bi['access']);
             $stmti->bindValue(':args', $bi['args']);
             $stmti->bindValue(':log', $bi['log']);
             $stmti->bindValue(':func', $bi['func']);
             $stmti->bindValue(':syntax', $bi['syntax']);
             $stmti->bindValue(':description', $bi['description']);
             $stmti->bindValue(':via', $via);
             $stmti->execute();
             $stmti->closeCursor();
         } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
     }
     
     function modcmdShow($nick, $vbname) {
         if(!array_key_exists($vbname, $this->binds)) {
             $this->pIrc->notice($nick, "That bind doesn't exist.");
             return;
         }
         $bind = $this->binds[$vbname];
         $this->pIrc->notice($nick, "\2Bind:\2 $bind[bname] \2Via:\2 $bind[onlyFrom] ".
                 "\2Mod:\2 $bind[class_name] \2Func:\2 $bind[func] \2Args:\2 $bind[args] \2Log:\2 $bind[log] ".
                 "\2Access:\2 $bind[access] \2Syntax:\2 $bind[syntax] \2Desc:\2 $bind[description]");
     }

     function rpc_unbind($p) {
         unset($this->binds[$p[0]]); 
     }
     
     function rpc_bind($p) {
         $this->binds[$p[0]] = $p[1];
     }
     
     function rpc_modcmd($p) {
         $this->binds[$p[0]] = $p[1];
     }
     
     function cmd_bind($nick, $target, $args) {
        $arg = explode(' ', $args);
        //$host = $this->pIrc->n2h($nick);
        //$hand = $this->gM('user')->byHost($host);
        //$hflags = $this->gM('user')->flags($hand);
        //$access = $this->gM('user')->access($hand, $chan);
        // ^ later we may consider lowering access and we will need this to
        // prevent O from modifying A binds
        if(empty($arg[3])) {
            return $this->rV['BADARGS'];
        }
        $via = strtolower($arg[0]);
        if($via != 'pm' && $via != 'chan') {
            return $this->rV['BADARGS'];
        }
        $bind = strtolower($arg[1]);
        $mod = $arg[2];
        $func = $arg[3];
        if(!empty($arg[4])) {
            $bargs = arg_range($arg, 4, -1);
        } else {
            $bargs = '';
        }

        $description = '';
        $syntax = '';
        $access = '0';
        $log = 0;

        if($this->IsBound($via, $bind)) {
            $this->pIrc->notice($nick, "Warning! binding $via $bind already found, (updating it) ($mod.$func)");
            try {
                $stmt = $this->pMysql->prepare("DELETE FROM `Binds` WHERE `bname` = :bind AND `onlyFrom` = :via");
                $stmt->bindValue(':bind', $bind);
                $stmt->bindValue(':via', $via);
                $stmt->execute();
                $stmt->closeCursor();
            } catch (PDOException $e) {
                $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
                echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
                $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            }
        }

        $this->binds[$via.$bind] = Array(
            'bname' => $bind,
            'class_name' => $mod,
            'func' => $func,
            'args' => $bargs,
            'log' => $log,
            'access' => $access,
            'syntax' => $syntax,
            'description' => $description,
            'onlyFrom' => $via
        );
        $this->gM('xnet')->sendToAll(null, null, 'bind', Array($via.$bind, $this->binds[$via.$bind]));
        try {
             $stmti = $this->pMysql->prepare("INSERT INTO `Binds` (bname,classname,used,access,args,log,func,syntax,description,onlyFrom)".
                     " VALUES(:bind,:class_name,0,:access,:args,:log,:func,:syntax,:description,:via)");
             $stmti->bindValue(':bind', $bind);
             $stmti->bindValue(':class_name', $mod);
             $stmti->bindValue(':access', $access);
             $stmti->bindValue(':args', $bargs);
             $stmti->bindValue(':log', $log);
             $stmti->bindValue(':func', $func);
             $stmti->bindValue(':syntax', $syntax);
             $stmti->bindValue(':description', $description);
             $stmti->bindValue(':via', $via);
             $stmti->execute();
             $stmti->closeCursor();
         } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $this->pIrc->notice($nick, "Finished binding $via $bind. ($mod.$func)");
        $this->modcmdShow($nick, $via. $bind);
    }

    function cmd_command($nick, $target, $args) {
        $arg = explode(' ', $args);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $hflags = $this->gM('user')->flags($hand);
        $chan = strtolower($target);
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[1])) {
            return $this->rV['BADARGS'];
        }
        $via = strtolower($arg[0]);
        if($via != 'pm' && $via != 'chan') {
            return $this->rV['BADARGS'];
        }
        $bind = strtolower($arg[1]);
        if(!$this->IsBound($via, $bind)) {
            $this->pIrc->notice($nick, "Command binding: $via $bind not found.");
            return $this->rV['ERROR'];
        }
        $info = $this->binds[$via.$bind];
        $if = $this->funcs[$info['class_name']][$via . $info['func']];
        $this->pIrc->rnotice($nick, "$via $bind is a binding of $info[class_name].$info[func], using args: $info[args]");
        $this->pIrc->notice($nick, "$via $bind has been used " . $this->getUsed($via, $bind) . " times. You need access $info[access] to use $info[func].");
        $this->pIrc->notice($nick, "Syntax for $info[func]: $if[syntax] Description: $if[description]");
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
    public $catchers = Array();
    function reloaded($args) {
        echo "CmdReg unloading module $args[name] for reload\n";
        $this->unloaded($args);
        $this->loaded($args);
    }

    public $bloaded = false;
    //Slot for module loaded
    function loaded($args) {
        echo "CmdReg loading module $args[name]\n";
        if(!$this->bloaded) {
            $this->bloaded = true;
            $this->loadBinds();
        }
        $info = $this->pMM->getRegistry($args['name'], 'CmdReg');
        $name = $args['name'];
        if($info == null) return;
        //Handle our section of registry.conf here
        
        //check if module wants to catch unknown commands
        //notice the first module that returns true stop all other mods
        //from seeing that command
        if(array_key_exists('catch', $info) && $info['catch'] != null) {
            $this->catchers[] = Array('module' => $name, 'func' => $info['catch']);
        }
        
        if(array_key_exists('funcs', $info) && is_array($info['funcs'])) {
            foreach($info['funcs'] as $f) {
                if(!array_key_exists(4, $f)) {
                    $f[4] = 'chan';
                }
                echo "CmdReg adding func: $name $f[0], $f[1], $f[2], $f[3], $f[4]\n";
                $this->addFunc($name, $f[0], $f[1], $f[2], $f[3], trim($f[4]));
            }
        }
        if(array_key_exists('binds', $info) && is_array($info['binds'])) {
            foreach($info['binds'] as $b) {
            //    name access func "syntax" "description" "args" loglvl
                if(!array_key_exists(7, $b)) {
                    $b[7] = 'chan';
                }
                //initialBind($bname, $class_name, $func, $args, $log, $access, $syntax, $description, $onlyFrom) {
                $this->initialBind($b[0], $name, $b[2], $b[5], $b[6], $b[1], $b[3], $b[4], $b[7]);
            }
        }
    }

    function addFunc($className, $name, $func, $syntax, $description, $onlyFrom) {
        echo "CmdReg adding func $func for $className\n";
        $this->funcs[strtolower($className)][$onlyFrom . strtolower($name)] = Array(
            'class' => $this->gM($className),
        	//TODO Lets check ^ this for memory leak later
            'name' => strtolower($name),
            'func' => $func,
            'syntax' => $syntax,
            'description' => $description,
            'onlyFrom' => $onlyFrom
        );
    }

    function inMysql($via, $bname) {
        try {
            $stmt = $this->pMysql->prepare("SELECT count(*) FROM `Binds` WHERE `onlyFrom` = :via AND `bname` = :bname");
            $stmt->bindValue(':via', $via);
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
    
    //Binds loaded from reading registry.conf
    function initialBind($bname, $class_name, $func, $args, $log, $access, $syntax, $description, $onlyFrom) {
        $bname = strtolower($bname);
        $class_name = strtolower($class_name);
        $func = strtolower($func);
        //first check if a bind bname exists in our binds db
        $insert = true;
        if($this->inMysql($onlyFrom, $bname)) {
            $insert = false;
            $this->loadBinds();
            return;
        }
        //check if args,syntax,desc = '' if so fill with values
        if($args == '' || $args == '$1-') {
            if($syntax == '' && array_key_exists($class_name, $this->funcs) && array_key_exists($onlyFrom . $func, $this->funcs[$class_name])) {
                $syntax = $this->funcs[$class_name][$onlyFrom . $func]['syntax'];
            }
            if($description == '' && array_key_exists($class_name, $this->funcs) && array_key_exists($onlyFrom . $func, $this->funcs[$class_name])) {
                $description = $this->funcs[$class_name][$onlyFrom . $func]['description'];
            }
        } else {
            if($syntax == '') {
                $syntax = 'N/A';
            }
            if($description == '') {
                $description = 'N/A';
            }
        }

        $this->binds[$onlyFrom . $bname] = Array(
            'bname' => $bname,
            'class_name' => $class_name,
            'func' => $func,
            'args' => $args,
            'log' => $log,
            'access' => $access,
            'syntax' => $syntax,
            'description' => $description,
            'onlyFrom' => $onlyFrom
        );

        //insert into mysql
        if ($insert) {
            try {
                $stmti = $this->pMysql->prepare("INSERT INTO `Binds` (bname,classname,used,access,args,log,func,syntax,description,onlyFrom)" .
                        " VALUES(:bind,:class_name,0,:access,:args,:log,:func,:syntax,:description,:via)");
                $stmti->bindValue(':bind', $bname);
                $stmti->bindValue(':class_name', $class_name);
                $stmti->bindValue(':access', $access);
                $stmti->bindValue(':args', $args);
                $stmti->bindValue(':log', $log);
                $stmti->bindValue(':func', $func);
                $stmti->bindValue(':syntax', $syntax);
                $stmti->bindValue(':description', $description);
                $stmti->bindValue(':via', $onlyFrom);
                $stmti->execute();
                $stmti->closeCursor();
            } catch (PDOException $e) {
                $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
                echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
                //$this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            }
        }
    }

	/*
	 * MySQL table Binds
+-------------+--------------+------+-----+---------+----------------+
| Field       | Type         | Null | Key | Default | Extra          |
+-------------+--------------+------+-----+---------+----------------+
| id          | int(11)      | NO   | PRI | NULL    | auto_increment |
| bname       | varchar(32)  | YES  |     | NULL    |                |
| classname   | varchar(32)  | YES  |     | NULL    |                |
| used        | int(11)      | YES  |     | NULL    |                |
| access      | varchar(32)  | YES  |     | NULL    |                |
| args        | text         | YES  |     | NULL    |                |
| log         | varchar(256) | YES  |     | NULL    |                |
| func        | varchar(32)  | YES  |     | NULL    |                |
| syntax      | varchar(512) | YES  |     | NULL    |                |
| description | text         | YES  |     | NULL    |                |
+-------------+--------------+------+-----+---------+----------------+
10 rows in set (0.01 sec)
	 */
    //Load all the binds from db into mem
    function loadBinds() {
        //load them all even if the functions dont exist
        //we can check if they do when attempting to call
        try {
            foreach($this->pMysql->query("SELECT * FROM `Binds`") as $row) {
                $row['bname'] = strtolower($row['bname']);
                $oF = $row['onlyFrom'];
                $this->binds[$oF . $row['bname']]['onlyFrom'] = $row['onlyFrom'];
                $this->binds[$oF . $row['bname']]['bname'] = $row['bname'];
                $this->binds[$oF . $row['bname']]['class_name'] = $row['classname'];
                $this->binds[$oF . $row['bname']]['access'] = $row['access'];
                $this->binds[$oF . $row['bname']]['args'] = $row['args'];
                $this->binds[$oF . $row['bname']]['log'] = $row['log'];
                $this->binds[$oF . $row['bname']]['func'] = $row['func'];
                $this->binds[$oF . $row['bname']]['syntax'] = $row['syntax'];
                $this->binds[$oF . $row['bname']]['description'] = $row['description'];
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            //$this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function getUsed($from, $bind) {
        if(!$this->IsBound($from, $bind)) {
            return false;
        }
        
        try {
            $stmt = $this->pMysql->prepare("SELECT * FROM `Binds` WHERE `bname` = :bind AND `onlyFrom` = :via");
            $stmt->bindValue(':bind', $bind);
            $stmt->bindValue(':via', $from);
            $stmt->execute();
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        
        return $row['used'];
    }

    /**
     * Return if a $bind exists
     * @param <string> $bind
     * @return <bool>
     */
    function IsBound($from, $bind) {
        $bind = $from . strtolower($bind);
        if(array_key_exists($bind, $this->binds)) {
            return true;
        } else {
            return false;
        }
    }

    function funcExists($from, $mod, $name) {
        if(!array_key_exists($mod, $this->funcs)) return;
        if(array_key_exists($from . $name, $this->funcs[$mod])) {
            return true;
        } else {
            return false;
        }
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
        $access = $caxs;
        $axs = $raxs;
        $override = false; //was overide needed for this
        if(!is_numeric($axs)) { // access is a flag, means staff access
            if($hand == '') {
                return 'You are not authed to BotOps, To auth do /msg ' . $this->pIrc->currentNick() .' AUTH username password';
            }
            if(!$this->gM('user')->hasflags($hand, $axs)) {
                return "You do not have access to this BotOps staff command.";
            } else {
            //check if this could be used as custom bot owner command
                /*
                if (cisin($axs, 'U') && hasflags($hand, 'U')) {
                    //the user owns a custom bot make sure its this one
                    if (!$this->gM('user')->uhasbot($hand, $irc->nick)) {
                        // they don't own this bot lets make sure they aren't staff
                        // removed their U flag in the check then if they still have access it means they are staff
                        $hflags = implode('', explode('U', $this->gM('user')->flags($hand)));
                        if (!$this->gM('user')->hasflags($hand, $axs, $hflags)) {
                            $irc->notice($nick, "You do not have access to this BotNetwork staff command.");
                            return;
                        }
                    }
                }
                */
            }
        } else { // chan access
            if(!($axs <= 0)) {// everyone can use if axs <= 0
                if($hand == '') {
                    return 'You are not authed to BotOps, To auth do /msg ' . $this->pIrc->currentNick() . ' AUTH username password';
                }
                if($access < $axs && !$this->gM('user')->hasOverride($hand)) {// no access
                    return "You need at least $axs access.";
                } else {
                    if($access < $axs) {
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
    
    function cmd_gag($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        $hand = $this->gM('user')->byNick($nick);
        if($argc < 3) {
            return $this->BADARGS;
        }
        $host = $argv[0];
        $duration = $argv[1];
        $why = arg_range($argv, 2, -1);
        $ok = $this->gag($host, $duration, $why, $hand);
        if($ok != null) {
            $this->pIrc->notice($nick, $ok);
            return $this->ERROR;
        }
        $this->pIrc->notice($nick, "$host is now gaged!");
    }
    
    function cmd_isgag($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        if($argc < 1) {
            return $this->BADARGS;
        }
        $active = $this->isGag($argv[0]);
        if($active === false) {
            $this->pIrc->notice($nick, "No gags found for that host (does not acces masks)");
            return;
        }
        foreach($active as $gag) {
            if($gag['expires'] != 0) {
                $left = Duration_toString($gag['expires'] - time());
            } else {
                $left = 'Never';
            }
            $this->pIrc->notice($nick, "Gag[$gag[id]] \2HostMask:\2 $gag[host] \2Expires:\2 $left \2From:\2 $gag[from] \2Reason:\2 $gag[reason]");
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
        
        try {
            $stmt = $this->pMysql->prepare("INSERT INTO `CmdReg_gags` (`host`,`from`,`expires`,`reason`)".
                    " VALUES(:host,:from,:expires,:why)");
            $stmt->bindValue(':host', str_replace('*', '%', $host));
            $stmt->bindValue(':from', $from);
            $stmt->bindValue(':expires', $expires);
            $stmt->bindValue(':why', $why);
            $stmt->execute();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n". $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }
    
    function ungag($id) {
        try {
            $stmt = $this->pMysql->prepare("DELETE FROM `CmdReg_gags` WHERE `id` = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }
    
    function isGag($host) {
        try {
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
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        if(count($active) > 0) {
            return $active;
        } else {
            return false;
        }
    }
    
    public $wascmd = false;
    function inmsg($nick, $target, $text) {
        $host = $this->pIrc->n2h($nick);
        //if(pmatch("*@kurizu.*", $host)) {
        //    return;
        //}
        if($this->isGag("$nick!$host") !== false) {
            return;
        }
        $args = explode(' ', $text);
        if($text == NULL || count($args) == 0 || empty($args[0])) {
            return;
        }
        $via = null;
        if($target{0} == '#') {
            $trig = $this->gM('channel')->getTrig($target);
            if($trig == null) {
                $trig = '@';
            }
            //Check if botnick is trigger
            if( strtolower($args[0]) != strtolower($this->pIrc->currentNick()) &&
                strtolower($args[0]) != strtolower($this->pIrc->currentNick()) . ',' &&
                strtolower($args[0]) != strtolower($this->pIrc->currentNick()) . ':'
              ) {
                  $bntriggered = false;
              } else {
                  $bntriggered = true;
              }
            
            if($trig != $args[0]{0} && !$bntriggered) {
                $this->wascmd = false;
                return;
            }

            if($bntriggered) {
                array_shift($args);
                if (count($args) == 0 || empty($args[0])) {
                    return;
                }
                $cmd = 'chan' . strtolower($args[0]);
            } else {
                $args[0] = substr($args[0], 1);
                $cmd = 'chan' . strtolower($args[0]);
            }
            $via = 'chan';
        } else {
            //privmsg
            $cmd = 'pm' . strtolower($args[0]);
            $via = 'pm';
        }
        if(!$this->IsBound($via, $args[0])) {
            //check if another module can do something for this (clanbot)
            $text = $args;
            array_shift($text);
            $text = implode(' ', $text);
            $this->gM('ParseUtil')->setArgy(argClean(explode(' ', $text)));
            $this->wascmd = true;
            $this->lastCmdInfo = array(
                'args' => $text,
                'cmd' => $args[0],
            );
            if(!$this->SendToMods($args[0], $nick, $target, $text)) {
                $this->wascmd = false;
            }
            return;
        }
        $this->wascmd = true;

        $classname = $this->binds[$cmd]['class_name'];
        $bfunc = $this->binds[$cmd]['func'];
        if(!$this->funcExists($via, $classname, $bfunc)) {
            return;
        }
        $class = $this->gM($this->pMM->getCname($classname));
        //$class = &$this->funcs[$classname][$this->binds[$cmd]['func']]['class'];
        $func = $this->funcs[$classname][$via . $this->binds[$cmd]['func']]['func'];
        $bargs = $this->binds[$cmd]['args'];
        $text = $args;
        array_shift($text);
        $text = implode(' ', $text);

        $this->lastCmdInfo = array(
            'args' => $text,
            'cmd' => $args[0],
        );
        
        $hand = $this->gM('user')->byNick($nick);
        $access = $this->gM('user')->access($hand, $target);
        $axs = $this->binds[$cmd]['access'];
        $override = false; //was overide needed for this
        if(!is_numeric($axs)) { // access is a flag, means staff access
            /*
             * access could be o - isopped or v -isvoiced
             */
            if(cisin($axs, 'v') && ((!$this->pIrc->isop($target, $nick)
                    || !$this->pIrc->isvoice($target, $nick)) ||
                    ($access < $axs && !$this->gM('user')->hasOverride($hand)))) {
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
                    || ($access < $axs && !$this->gM('user')->hasOverride($hand)))) {
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
                //check if this could be used as custom bot owner command
                /*
                if (cisin($axs, 'U') && hasflags($hand, 'U')) {
                    //the user owns a custom bot make sure its this one
                    if (!$this->gM('user')->uhasbot($hand, $irc->nick)) {
                        // they don't own this bot lets make sure they aren't staff
                        // removed their U flag in the check then if they still have access it means they are staff
                        $hflags = implode('', explode('U', $this->gM('user')->flags($hand)));
                        if (!$this->gM('user')->hasflags($hand, $axs, $hflags)) {
                            $irc->notice($nick, "You do not have access to this BotNetwork staff command.");
                            return;
                        }
                    }
                }
                 */
            }
        } else { // chan access
            if(!($axs <= 0)) {// everyone can use if axs <= 0
                if($hand == '') {
                    $this->pIrc->notice($nick, 'You are not authed to BotOps, To auth do /msg ' . $this->pIrc->currentNick() . ' AUTH username password');
                    return;
                }
                if($access < $axs && !$this->gM('user')->hasOverride($hand)) {// no access
                    $this->pIrc->notice($nick, "You need at least $axs access in $target to use $args[0].");
                    return;
                } else {
                    if($access < $axs) {
                        $override = true;
                    }
                }
            }
        }
        
        //TODO handle return values and log usage
        if(!method_exists($class, $func)) {
            $this->pIrc->notice($nick, "Error: failed to access $classname.$bfunc");
            echo "CmdReg: failed to access $classname.$bfunc\n";
            return;
        } else {
            $logins = Array(
                'date' => microtime_float(),
                'cmd' => $this->binds[$cmd]['bname'],
                'override' => (int)$override,
                'nick' => $nick,
                'hand' => $hand,
                'target' => $target,
                'host' => $host,
                'msg' => $text,
                'bot' => $this->pIrc->nick
            );
            try {
                $stmt = $this->pMysql->prepare("UPDATE Binds set used=used+1 where bname = :bname");
                $stmt->bindValue(':bname', $args[0]);
                $stmt->execute();
                $stmt->closeCursor();
            } catch (PDOException $e) {
                $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
                echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
                $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            }
            $this->gM('ParseUtil')->setArgy(argClean(explode(' ', $text)));
            if($bargs != '') {
                $text = $this->gM('ParseUtil')->parse($bargs, 'pucb', $this, Array($class, $func, $nick, $target, $cmd, $args, $logins));
                return;
            }
            $retval = $class->$func($nick, $target, $text);
            echo "CmdReg: called " . get_class($class) . "->$func\n";
        }

        if($retval & $this->rV['ERROR']) {
            //Not sure what we need to do here for error yet
            //probably nothing ;)
            //Possibly get last error from error log
            //Right now modules return error when they did nothing
        }
        if($retval & $this->rV['BADARGS']) {
            $this->pIrc->notice($nick, "Syntax: $args[0] " . $this->binds[$cmd]['syntax']);
        }
        if($retval & $this->rV['OVERRIDE']) {
            $override = true;
        }
        if($retval & $this->rV['OK'] || $retval == NULL) {
            
            $logins['override'] = (int)$override;
            //everything went OK log if needed
            //get loglvl
            $loglvl = $this->binds[$cmd]['log'];
            if($loglvl > 0) {
                //global comand with first arg as target
                if($loglvl == 2 || $loglvl == '2') {
                    if(isset($args[1]) && $args[1] != '') {
                        $logins['target'] = $args[1];
                    }
                }
                //global command with no target
                if($loglvl == 3 || $loglvl == '3') {
                    $logins['target'] = '';
                }
                $this->gM('logs')->log('CmdReg', $logins);
            }
        }
    }

    function pucb($text, $x) {
        $logins = $x[6];
        $args = $x[5];
        $cmd = $x[4];
        $loglvl = $this->binds[$cmd]['log'];
        $retval = $x[0]->{$x[1]}($x[2], $x[3], $text);
        if($retval & $this->rV['ERROR']) {
        //Not sure what we need to do here for error yet
        //probably nothing ;)
        //Possibly get last error from error log
        }
        if($retval & $this->rV['BADARGS']) {
            $this->pIrc->notice($x[2], "Syntax: $x[4] " . $this->binds[$x[5] . $x[4]]['syntax']);
        }
        if($retval & $this->rV['OVERRIDE']) {
        //TODO If loglevel is right, log that override was used
            $logins['override'] = 1;
        }
        if($retval & $this->rV['OK'] || $retval == NULL) {
        //everything went OK log if needed
            if($loglvl > 0) {
                //global comand with first arg as target
                if($loglvl == 2 || $loglvl == '2') {
                    if($args[1] != '') {
                        $logins['target'] = $args[1];
                    }
                }
                //global command with no target
                if($loglvl == 3 || $loglvl == '3') {
                    $logins['target'] = '';
                }
                $this->gM('logs')->log('CmdReg', $logins);
            }
        }
    }
}

?>
