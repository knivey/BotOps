<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';
require_once('modules/Module.inc');
require_once('Tools/Tools.php');

 class SetReg extends Module {
    public $channelSets = array();
    public $accountSets = array();

    public $chanExtras = array();
    /* This is an array of module->callback funcs and modules->willset funcs
     * if setreg cant find a setting it will ask each willset function to
     * return true or false for if it will update a setting
     * If only one module will make a set then it goes through with the callback
     * otherwise it will error with multiple sets conflict.
     * $chanExtras['module'] = Array('cb', 'ws');
     */

    /* ^
     * For now i'll rely on all our loaded
     * registry.conf to tell us what settings we have
     * we will use this instead of whats stored to see
     * if a setting exists and its default values
     */
    
    /**
     * Dev function to display the current database contents for SetReg account settings.
     * Output will be dumped to the terminal.
     * Used for testing purposes to verify things are altered correctly.
     * Please DISABLE this in production environment 
     */
    function listasets() {
    	$users = $this->gM('user')->allUsers();
    	
    	echo "BEGINING SETREG ACCOUNT SETS DUMP:\n";
    	foreach($users as $user) {
    		echo " * User: $user\n";
    		$sets = $this->gM('user')->getSet($user, 'SetReg', 'sets');
    		if(!is_array($sets)) {
    			continue;
    		}
    		foreach ($sets as $mod => $settings) {
    			foreach($settings as $set => $val) {
    				echo "  * Setting: $mod.$set => $val\n";
    			}
    		}
    	}
    	echo "ENDING SETREG ACCOUNT SETS DUMP.\n";
    }

    function getCSet($module, $chan, $setting) {
        $sets = $this->gM('channel')->getSet($chan, 'SetReg', 'sets');
        return $sets[$module][$setting] ?? $this->channelSets[$module][$setting]['default'];
    }

    //TODO in the future look into adding hooks to let modules know
    //      if a setting with hook defined has been changed
    function cSet($module, $chan, $setting, $args) {
        $sets = $this->gM('user')->getSet($chan, 'SetReg', 'sets');
        $sets[$module][$setting] = $args;
        $this->gM('channel')->chgSet($chan, 'SetReg', 'sets', $sets);
    }

    function cmd_set(CmdRequest $r) {
        echo "$r->account $r->chan $r->access\n";
        if(!$r->chan) {
            $target = $r->account;
            $access = $this->gM('user')->flags($r->account);
        } else {
            $target = strtolower($r->chan);
            $access = $this->gM('user')->access($r->account, $target);
        }

        $module = strtolower($r->args['module']);
        $setting = strtolower($r->args['name']);
        $args = $r->args['value'];

        if($module == '') {
            if($target[0] == '#') {
                $out = $this->getCHelp($access, $r->account);
            } else {
                $out = $this->getAHelp($access);
            }
            if(count($out) == 1) {
                throw new CmdException("No settings found for your access level ($access)");
            }
            foreach ($out as $line) {
                $r->notice(implode('', $line), 0, 1);
            }
            $r->notice("Use set <module> for details", 0, 1);
            return;
        }

        //Get help only for module
        //Assumes $module is a module, if its not a module skip and later assume its a setting
        if($setting == '') {
            $out = [];
            if($target[0] == '#' && array_key_exists($module, $this->channelSets)) {
                $out = $this->getCSetHelp($module, $access, $r->account);
            }
            if($target[0] != '#' && array_key_exists($module, $this->accountSets)) {
                $out = $this->getASetHelp($module, $access);
            }
            if(!empty($out)) {
                if (count($out) == 1) {
                    throw new CmdException("No settings found for your access level ($access)");
                }
                foreach ($out as $line) {
                    $r->notice(implode('', $line), true, true);
                }
                return;
            }
        }

        //first check if $module is a real module name so we know what to set things to
        if($target[0] != '#') {
            $sets = $this->accountSets;
        } else {
            $sets = $this->channelSets;
        }
        if(!array_key_exists($module, $sets)) {
            if($target[0] == '#') {
                $mods = $this->searchCset($access, $r->account, $module);
            } else {
                $mods = $this->searchAset($access, $r->account, $module);
            }
            if(!empty($mods)) {
                if(count($mods) > 1) {
                    throw new CmdException("no mod: $module. Mutiple mods with setting please choose: " . implode(' ', $mods));
                } else {
                    $args = trim("$setting $args");
                    $setting = $module;
                    $module = implode('', $mods);
                }
            } else { //TODO search for cExtras
                throw new CmdException("No settings by that name ($module).");
            }
        } else {
            if(!array_key_exists($setting, $sets[$module])) {
                throw new CmdException("No settings by that name ($module $setting).");
            }
        }
        if(array_key_exists('aliasfor', $sets[$module][$setting])) {
            $setting = $sets[$module][$setting]['aliasfor'];
        }
        //check up access
        $at = $this->gM('CmdReg')->hasAxs($access, $r->account, $sets[$module][$setting]['access']);
        if(!is_numeric($at)) {
            throw new CmdException($at);
        }
        if($at == -1) {
            $r->setOverride();
        }
        if($args == '') { // show current setting
            $set = '';
            if($target[0] == '#') {
                $set = $this->getCSet($module, $target, $setting);
            } else {
                $set = $this->getASet($target, $module, $setting);
            }
            $r->notice("$target $module $setting is currently set to: $set", true, true);
            return $r;
        }
        if(empty($sets[$module][$setting]['options'])) {
            if($target[0] == '#') {
                $this->cSet($module, $target, $setting, $args);
            } else {
                $this->aSet($r->account, $module, $setting, $args);
            }
            $r->notice("Setting $module $setting updated!");
            return $r;
        }

        $opt = array_search(strtolower($args), $sets[$module][$setting]['options']);
        if($opt !== FALSE) {
            $opt = $sets[$module][$setting]['options'][$opt];
            if($target[0] == '#') {
                $this->cSet($module, $target, $setting, $opt);
            } else {
                $this->aSet($r->account, $module, $setting, $opt);
            }
            $r->notice("Setting $module $setting updated!");
            return $r;
        } elseif($args == "*") {
            $opt = $sets[$module][$setting]['default'];
            if($target[0] == '#') {
                $this->cSet($module, $target, $setting, $opt);
            } else {
                $this->aSet($r->account, $module, $setting, $opt);
            }
            $r->notice("Setting $module $setting updated!");
            return $r;
        } else {
            $opts = implode(', ', $sets[$module][$setting]['options']);
            throw new CmdException("Please choose from: $opts");
        }
    }

    function getASet($hand, $module, $setting) {
        $sets = $this->gM('user')->getSet($hand, 'SetReg', 'sets');
        return $sets[$module][$setting] ?? $this->accountSets[$module][$setting]['default'];
    }
    
    function aSet($hand, $module, $setting, $args) {
        $sets = $this->gM('user')->getSet($hand, 'SetReg', 'sets');
        $sets[$module][$setting] = $args;
        $this->gM('user')->set($hand, 'SetReg', 'sets', $sets);    
    }

    /*
     * print modules and their desc + # of sets user has access to
     */
    function getAHelp($access) {
        $out = Array(Array('Module', 'Description', '# of sets'));
        $mods = $this->getAModules($access);
        foreach($mods as $m) {
            $sets = $this->aModAxs($access, $m);
            $out[] = Array($m, $this->pMM->getDesc($m), count($sets));
        }
        return multi_array_padding($out);
    }

    function getCHelp($caxs, $hand) {
        $out = Array(Array('Module', 'Description', '# of sets'));
        $mods = $this->getCModules($caxs, $hand);
        foreach($mods as $m) {
            $sets = $this->cModAxs($caxs, $hand, $m);
            $out[] = Array($m, $this->pMM->getDesc($m), count($sets));
        }
        return multi_array_padding($out);
    }

    //return modules that have settings the user can access
    function getAModules($access) {
        $out = Array();
        foreach($this->accountSets as $m => $s) {
            $sets = $this->aModAxs($access, $m);
            if(count($sets) > 0) {
                $out[] = $m;
            }
        }
        return $out;
    }

    //returns an array of settings the user has access to under mod
    function aModAxs($access, $mod) {
        $out = array();
        foreach($this->accountSets[$mod] as $n => $s) {
            if($this->gM('user')->hasFlags('', $s['access'], $access)) {
                $out[$n] = $s;
            }
        }
        return $out;
    }

    function cModAxs($caxs, $hand, $mod) {
        $out = array();
        foreach($this->channelSets[$mod] as $n => $s) {
            $at = $this->gM('CmdReg')->hasAxs($caxs, $hand, $s['access']);
            if(is_numeric($at)) {
                $out[$n] = $s;
            }
        }
        return $out;
    }

    function searchAset($access, $hand, $set) {
        $matches = array(); //matches to our set search
        $ms = $this->getAModules($access);
        $mods = array();
        foreach($ms as $m) {
            $mods[$m] = $this->aModAxs($access, $m);
        }
        foreach($mods as $k => $m) {
            foreach($m as $s) {
                if(strtolower($s['name']) == strtolower($set)) {
                    $matches[$k] = $k;
                }
            }
        }
        return $matches;
    }

    function searchCset($caxs, $hand, $set) {
        $matches = array(); //matches to our set search
        $ms = $this->getCModules($caxs, $hand);
        $mods = array();
        foreach($ms as $m) {
            $mods[$m] = $this->cModAxs($caxs, $hand, $m);
        }
        foreach($mods as $k => $m) {
            foreach($m as $s) {
                if(strtolower($s['name']) == strtolower($set)) {
                    $matches[$k] = $k;
                }
            }
        }
        return $matches;
    }
    
    //return array of mods that willset as out[mod] = 'cbfunc'
    function cExtraWS($set) {
        $out = Array();
        foreach ($this->chanExtras as $mod => $ce) {
            if($this->gM($mod)->$ce['ws']($set)) {
                $out[$mod] = $ce['cb'];
            }
        }
        return $out;
    }

    function getCModules($caxs, $hand) {
        $out = Array();
        foreach($this->channelSets as $m => $s) {
            $sets = $this->cModAxs($caxs, $hand, $m);
            if(count($sets) > 0) {
                $out[] = $m;
            }
        }
        return $out;
    }
    /**
     * Regsister a new channel setting for a module
     *
     * @param string $module  The name of the module to register the setting for.
     * @param string $name    The name of the setting
     * @param string $idx     The array key under the account settings array it uses
     * @param Array $options  An array of possible settings, if empty anything is setable.
     * @param string $desc    Description of setting
     */
    function addChanSet($mod, $name, $options, $desc, $default, $access) {
        $this->channelSets[strtolower($mod)][strtolower($name)] = Array(
            'name' => $name,
            'options' => $options,
            'desc' => $desc,
            'default' => $default,
            'access' => $access
        );
    }
    
    function addChanAlias($mod, $target, $name) {
        if(!array_key_exists($mod, $this->channelSets)) {
            return;
        }
        if(!array_key_exists($target, $this->channelSets[$mod])) {
            return;
        }
        if(isset($this->channelSets[$mod][strtolower($name)])) {
            return;
        }
        $this->channelSets[strtolower($mod)][strtolower($name)] = Array(
            'name' => $name,
            'options' => $this->channelSets[$mod][$target]['options'],
            'desc' => "Alias for $target",
            'default' => $this->channelSets[$mod][$target]['default'],
            'access' => $this->channelSets[$mod][$target]['access'],
            'aliasfor' => $target
        );
    }
    
    function addAccountAlias($mod, $target, $name) {
        if(!array_key_exists($mod, $this->accountSets)) {
            return;
        }
        if(!array_key_exists($target, $this->accountSets[$mod])) {
            return;
        }
        if(isset($this->accountSets[$mod][strtolower($name)])) {
            return;
        }
        $this->accountSets[strtolower($mod)][strtolower($name)] = Array(
            'name' => $name,
            'options' => $this->accountSets[$mod][$target]['options'],
            'desc' => "Alias for $target",
            'default' => $this->accountSets[$mod][$target]['default'],
            'access' => $this->accountSets[$mod][$target]['access'],
            'aliasfor' => $target
        );     
    }
    
    /**
     * Regsister a new account setting for a module
     *
     * @param string $module  The name of the module to register the setting for.
     * @param string $name    The name of the setting
     * @param Array $options  An array of possible settings, if empty anything is setable.
     * @param string $desc    Description of setting
     */
    function addAccountSet($mod, $name, $options, $desc, $default, $access) {
        $this->accountSets[strtolower($mod)][strtolower($name)] = Array('name' => $name,
            'options' => $options,
            'desc' => $desc,
            'default' => $default,
            'access' => $access
        );
    }

    /*
     * Prints list of settings user has access to for module $mod
     */
    function getASetHelp($mod, $access) {
        $out = Array(Array('Setting', 'Description'));
        $sets = $this->aModAxs($access, $mod);
        foreach($sets as $s) {
            $out[] = Array($s['name'], $s['desc']);
        }
        return multi_array_padding($out);
    }

    function getCSetHelp($mod, $caxs, $hand) {
        $out = Array(Array('Setting', 'Description'));
        $sets = $this->cModAxs($caxs, $hand, $mod);
        foreach($sets as $s) {
            $out[] = Array($s['name'], $s['desc']);
        }
        return multi_array_padding($out);
    }

    //register with cExtra
    function cExtra($mod, $cb, $ws) {
        $this->chanExtras[$mod] = Array('mod' => $mod, 'cb' => $cb, 'ws' => $ws);
    }

    function loaded($args) {
        echo "SetReg loading module $args[name]\n";
        $mod = strtolower($args['name']);
        $info = $this->pMM->getConf($args['name'], 'SetReg');
        if($info == null) return;
        //Handle our section of registry.conf here
        if(isset($info['account']) && is_array($info['account'])) {
            foreach($info['account'] as $name => $set) {
                $access = $set['access'] ?? null;
                $desc = $set['desc'] ?? "No description";
                $default = null;
                if(isset($set['opts']['default'])) {
                    $default = $set['opts']['default'];
                    unset($set['opts']['default']);
                }
                if(empty($set['opts'])) {
                    $set['opts'] = null;
                }
                $this->addAccountSet($mod, $name, $set['opts'], $desc, $default, $access);
            }
        }
        if(isset($info['channel']) && is_array($info['channel'])) {
            foreach($info['channel'] as $name => $set) {
                $access = $set['access'] ?? 0;
                $desc = $set['desc'] ?? "No description";
                $default = null;
                if(isset($set['opts']['default'])) {
                    $default = $set['opts']['default'];
                    unset($set['opts']['default']);
                }
                if(empty($set['opts'])) {
                    $set['opts'] = null;
                }
                $this->addChanSet($mod, $name, $set['opts'], $desc, $default, $access);
            }
        }
        if(array_key_exists('channel_alias', $info) && is_array($info['channel_alias'])) {
            foreach($info['channel_alias'] as $name => $target) {
                $this->addChanAlias($mod, $target, $name);
            }
        }
        if(array_key_exists('account_alias', $info) && is_array($info['account_alias'])) {
            foreach($info['account_alias'] as $name => $target) {
                $this->addAccountAlias($mod, $target, $name);
            }
        }
    }

    //Slot for module unloaded
    function unloaded($args) {
        //cleanup our sets
        $args['name'] = strtolower($args['name']);
        unset($this->accountSets[$args['name']]);
        unset($this->channelSets[$args['name']]);
    }

    function reloaded($args) {
        echo "SetReg unloading module $args[name] for reload\n";
        $name = strtolower($args['name']);
        unset($this->accountSets[$name]);
        unset($this->channelSets[$name]);
        $this->loaded($args);
    }

    function rehash(&$old) {
        $this->accountSets = $old->accountSets;
        $this->channelSets = $old->channelSets;
        echo "SetReg rehashed\n";
    }
 }

?>
