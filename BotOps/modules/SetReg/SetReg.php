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
 * SetReg.php
 *   Lets you register settings your module will use on chans or users
 *   Gives access to these settings, and provides set command
 ***************************************************************************/
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

    /**
     * Dev command to move settings from under one module to under another module
     * <Account|Chan> <OldMod.OldSetName> <NewMod.NewSetName>
     * @param string $nick
     * @param string $target
     * @param string $txt
     * @return number
     */
    function cmd_moveset($nick, $target, $txt) {
    	list($argc, $argv) = niceArgs($txt);
    	if($argc < 3) {
    		return $this->BADARGS;
    	}
    	$type = strtolower($argv[0]);
    	if($type != 'account' && $type != 'chan') {
    		$this->pIrc->notice($nick, "$type is not a valid setting type use either account or chan");
    		return $this->ERROR;
    	}
    	$old = explode('.', $argv[1]);
    	if(count($old) != 2) {
    		$this->pIrc->notice($nick, "$argv[1] is an invalid setting name, Use Module.Setting format");
    		return $this->ERROR;
    	}
    	$new = explode('.', $argv[2]);
    	if(count($new) != 2) {
    		$this->pIrc->notice($nick, "$argv[2] is an invalid setting name, Use Module.Setting format");
    		return $this->ERROR;
    	}

    	if($type == 'account') {
    		list($error, $changed) = $this->movesetAccount($old, $new);
    		if(!$error) {
    			$this->pIrc->notice($nick, "$changed records altered");
    		} else {
    			$this->pIrc->notice($nick, "moveset error: $error");
    			return $this->ERROR;
    		}
    	}
    	
    	/*
    	 * I'm going to put off rename of channel sets until we actually need this
    	 */
    	if($type == 'chan') {
    		$this->pIrc->notice($nick, "Not implemented yet.");
    	}
    }
    
    /**
     * Move/rename an account setting
     * @param Array $old [0] for module [1] for setname
     * @param Array $new [0] for module [1] for setname
     * @return Array [0] error (false if none, string otherwise) [1] changed amount
     */
    function movesetAccount($old, $new) {
    	$changed = 0;
    	$error = false;
    	
    	$users = $this->gM('user')->allUsers();
    	
    	if(empty($users)) {
    		$error = 'Unable to get list of users';
    		return Array($error, $changed);
    	}
    	
    	foreach($users as $user) {
    		$sets = $this->gM('user')->getSet($user, 'SetReg', 'sets');
    		if(!is_array($sets)) {
    			continue;
    		}
    		$key = get_akey_nc($old[0], $sets);
    		if($key != null) {
    			$keyb = get_akey_nc($old[1], $sets[$key]);
    			if($keyb != null) {
    				$sets[$new[0]][$new[1]] = $sets[$key][$keyb];
    				unset($sets[$key][$keyb]);
    				$changed++;
    				$this->gM('user')->set($user, 'SetReg', 'sets', $sets);
    			}
    		}
    	}
    	
    	return Array($error, $changed);
    }

    function cmd_cset($nick, $target, $args) {
        $arg = explode(' ', $args);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $hflags = $this->gM('user')->flags($hand);
        $chan = strtolower($target);
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
		$module = '';
	} else {
            $module = $arg[0];
        }
        if(empty($arg[1])) {
		$setting = '';
	} else {
            $setting = $arg[1];
        }

        $rv = $this->OK;
        $module = strtolower($module);
        $setting = strtolower($setting);

        $args = arg_range($arg, 2, -1);
        if($module == '') {
            $out = $this->getCHelp($access, $hand); // Get the modules with settings user has access for
            if(count($out) == 1) {
                $this->pIrc->notice($nick, "No settings found for your lvl $access access.");
                return $this->ERROR;
            }
            foreach ($out as $line) {
                $this->pIrc->notice($nick, implode('', $line));
            }
            $this->pIrc->notice($nick, "Use set <module> for details");
            return;
        }
        if($setting == '' && array_key_exists($module, $this->channelSets)) {
            //show help
            $out = $this->getCSetHelp($module, $access, $hand);// Get the help for settings user has access for
            if(count($out) == 1) {
                //search for sets then for extra sets (using $module as set name)
                //if set is found display current setting
                $this->pIrc->notice($nick, "No settings found for your lvl $access access.");
                return $this->ERROR;
            }
            foreach ($out as $line) {
                $this->pIrc->notice($nick, implode('', $line));
            }
            return $this->ERROR;
        }

        //first check if $module is a real module name so we know what to set things to
        if(!array_key_exists($module, $this->channelSets)) { //found
            $mods = $this->searchCset($access, $hand, $module);
            if(!empty($mods)) {
                if(count($mods) > 1) {
                    $this->pIrc->notice($nick, "no mod: $module. Mutiple mods with setting please choose: " . implode(' ', $mods));
                    return $this->ERROR;
                } else {
                    //$this->pIrc->notice($nick, "Selecting $module under module " . implode('', $mods));
                    $setting = $module;
                    $module = implode('', $mods);
                    $args = arg_range($arg, 1, -1);
                }
            } else { //TODO search for cExtras
                $this->pIrc->notice($nick, "No settings by that name.");
                return $this->ERROR;
            }
        } else {
            if(!array_key_exists($setting, $this->channelSets[$module])) {
                $this->pIrc->notice($nick, "No settings by that name.");
                return $this->ERROR;
            }
        }
        if(array_key_exists('aliasfor', $this->channelSets[$module][$setting])) {
            $setting = $this->channelSets[$module][$setting]['aliasfor'];
        }
        //check up access
        $at = $this->gM('CmdReg')->hasAxs($access, $hand, $this->channelSets[$module][$setting]['access']);
        if(!is_numeric($at)) {
            $this->pIrc->notice($nick, $at);
            return $this->ERROR;
        }
        if($at == -1) {
            $rv =  $this->OVERRIDE;//handle overide?
        }
        if($args == '') { // show current setting
            $sets = $this->gM('channel')->getSet($chan, 'SetReg', 'sets');
            $set = '';
            if($sets == null) {
                $sets = Array();
            }
            if(array_key_exists($module, $sets) && array_key_exists($setting, $sets[$module])) {
                $set = $sets[$module][$setting];
            }
            if($set == '') { //nothing is set show the default
                $set = $this->channelSets[$module][$setting]['default'];
            }
            $this->pIrc->notice($nick, "$module $setting is currently set to: $set", true, true);
            return $rv;
        }
        if(empty($this->channelSets[$module][$setting]['options'])) {
            $sets = $this->gM('channel')->getSet($chan, 'SetReg', 'sets');
            $sets[$module][$setting] = $args;
            $this->gM('channel')->chgSet($chan, 'SetReg', 'sets', $sets);
            $this->pIrc->notice($nick, "Setting $module $setting updated!");
            return $rv;
        }
        if(array_search(strtolower($args), $this->channelSets[$module][$setting]['options']) !== FALSE) {
            $sets = $this->gM('channel')->getSet($chan, 'SetReg', 'sets');
            $sets[$module][$setting] = $args;
            $this->gM('channel')->chgSet($chan, 'SetReg', 'sets', $sets);
            $this->pIrc->notice($nick, "Setting $module $setting updated!");
            return $rv;
        } else {
            $opts = '';
            foreach ($this->channelSets[$module][$setting]['options'] as $o) {
                $opts .= $o . ' ';
            }
            $this->pIrc->notice($nick, "Please choose from: " . trim($opts));
            return $this->ERROR;
        }
    }

    function getCSet($module, $chan, $setting) {
        $sets = $this->gM('channel')->getSet($chan, 'SetReg', 'sets');
        $set = '';
        if($sets == null) {
            $sets = Array();
        }
        if(array_key_exists($module, $sets) && array_key_exists($setting, $sets[$module])) {
            $set = $sets[$module][$setting];
        }
        if($set == '') { //nothing is set show the default
            $set = $this->channelSets[$module][$setting]['default'];
        }
        return $set;
    }

    //TODO in the future look into adding hooks to let modules know
    //      if a setting with hook defined has been changed
    function cSet($module, $chan, $setting, $args) {
        $sets = $this->gM('user')->getSet($chan, 'SetReg', 'sets');
        $sets[$module][$setting] = $args;
        $this->gM('channel')->chgSet($chan, 'SetReg', 'sets', $sets);
    }

    function cmd_aset($nick, $target, $args) {
        $arg = explode(' ', $args);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        //$target = strtolower($target);
        $access = $this->gM('user')->flags($hand);
        if(empty($arg[0])) {
		$module = '';
	} else {
            $module = $arg[0];
        }
        if(empty($arg[1])) {
		$setting = '';
	} else {
            $setting = $arg[1];
        }

        $module = strtolower($module);
        $setting = strtolower($setting);

        $args = arg_range($arg, 2, -1);
        if($module == '') {
            $out = $this->getAHelp($access); // Get the modules with settings user has access for
            if(count($out) == 1) {
                $this->pIrc->notice($nick, "No settings found.");
                return;
            }
            foreach ($out as $line) {
                $this->pIrc->notice($nick, implode('', $line));
            }
            $this->pIrc->notice($nick, "Use set <module> for details");
            return;
        }
        if($setting == '' && array_key_exists($module, $this->accountSets)) {
            //show help
            $out = $this->getASetHelp($module, $access);// Get the help for settings user has access for
            if(count($out) == 1) {
                //search for sets then for extra sets (using $module as set name)
                //if set is found display current setting
                $this->pIrc->notice($nick, "No settings found.");
                return;
            }
            foreach ($out as $line) {
                $this->pIrc->notice($nick, implode('', $line));
            }
            return;
        }

        //first check if $module is a real module name so we know what to set things to
        if(!array_key_exists($module, $this->accountSets)) { //found
            $mods = $this->searchAset($access, $hand, $module);
            if(!empty($mods)) {
                if(count($mods) > 1) {
                    $this->pIrc->notice($nick, "no mod: $module. Mutiple mods with setting please choose: " . implode(' ', $mods));
                    return;
                } else {
                    //$this->pIrc->notice($nick, "Selecting $module under module " . implode('', $mods));
                    $setting = $module;
                    $module = implode('', $mods);
                    $args = arg_range($arg, 1, -1);
                }
            } else { //TODO search for cExtras
                $this->pIrc->notice($nick, "No settings by that name.");
                return;
            }
        } else {
            if(!array_key_exists($setting, $this->accountSets[$module])) {
                $this->pIrc->notice($nick, "No settings by that name.");
                return;
            }
        }
        if(array_key_exists('aliasfor', $this->accountSets[$module][$setting])) {
            $setting = $this->accountSets[$module][$setting]['aliasfor'];
        }
        //check up access
        $at = $this->gM('CmdReg')->hasAxs($access, $hand, $this->accountSets[$module][$setting]['access']);
        if(!is_numeric($at)) {
            $this->pIrc->notice($nick, $at);
            return;
        }
        if($at == -1) {
            ;//handle overide?
        }
        if($args == '') { // show current setting
            $sets = $this->gM('user')->getSet($hand, 'SetReg', 'sets');
            $set = '';
            if($sets == null) {
                $sets = Array();
            }
            if(array_key_exists($module, $sets) && array_key_exists($setting, $sets[$module])) {
                $set = $sets[$module][$setting];
            }
            if($set == '') { //nothing is set show the default
                $set = $this->accountSets[$module][$setting]['default'];
            }
            $this->pIrc->notice($nick, "$module $setting is currently set to: $set", true, true);
            return;
        }
        if(empty($this->accountSets[$module][$setting]['options'])) {
            $sets = $this->gM('user')->getSet($hand, 'SetReg', 'sets');
            $sets[$module][$setting] = $args;
            $this->gM('user')->set($hand, 'SetReg', 'sets', $sets);
            $this->pIrc->notice($nick, "Setting $module $setting updated!");
            return;
        }
        if(array_search(strtolower($args), $this->accountSets[$module][$setting]['options']) !== FALSE) {
            $sets = $this->gM('user')->getSet($hand, 'SetReg', 'sets');
            $sets[$module][$setting] = $args;
            $this->gM('user')->set($hand, 'SetReg', 'sets', $sets);
            $this->pIrc->notice($nick, "Setting $module $setting updated!");
        } else {
            $opts = '';
            foreach ($this->accountSets[$module][$setting]['options'] as $o) {
                $opts .= $o . ' ';
            }
            $this->pIrc->notice($nick, "Please choose from: " . trim($opts));
        }
    }

    function getASet($hand, $module, $setting) {
        $sets = $this->gM('user')->getSet($hand, 'SetReg', 'sets');
        $set = '';
        if($sets == null) {
            $sets = Array();
        }
        if(array_key_exists($module, $sets) && array_key_exists($setting, $sets[$module])) {
            $set = $sets[$module][$setting];
        }
        if($set == '') { //nothing is set show the default
            $set = $this->accountSets[$module][$setting]['default'];
        }
        return $set;
    }
    
    function aSet($module, $setting, $args) {
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
        foreach($this->accountSets[strtolower($mod)] as $s) {
            $out[] = Array($s['name'], $s['desc']);
        }
        return multi_array_padding($out);
    }

    function getCSetHelp($mod, $caxs, $hand) {
        $out = Array(Array('Setting', 'Description'));
        $sets = $this->cModAxs($caxs, $hand, $mod);
        foreach($sets as $s) {
            //$this->channelSets[strtolower($mod)][$s];
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
        $info = $this->pMM->getRegistry($args['name'], 'SetReg');
        if($info == null) return;
        //Handle our section of registry.conf here
        if(array_key_exists('account', $info) && is_array($info['account'])) {
            foreach($info['account'] as $a) {
                $name = array_shift($a);
                $access = array_shift($a);
                $desc = array_shift($a);
                $default = array_shift($a);
                if(count($a) > 0) {
                    $options = $a;
                } else {
                    $options = null;
                }
                echo "SetReg adding account set $mod, $name\n";
                $this->addAccountSet($mod, $name, $options, $desc, $default, $access);
                unset($options);
            }
        }
        if(array_key_exists('channel', $info) && is_array($info['channel'])) {
            foreach($info['channel'] as $a) {
                $name = array_shift($a);
                $access = array_shift($a);
                $desc = array_shift($a);
                $default = array_shift($a);
                if(count($a) > 0) {
                    $options = $a;
                } else {
                    $options = null;
                }
                echo "SetReg adding channel set $mod, $name\n";
                $this->addChanSet($mod, $name, $options, $desc, $default, $access);
                unset($options);
            }
        }
        if(array_key_exists('channel_alias', $info) && is_array($info['channel_alias'])) {
            foreach($info['channel_alias'] as $a) {
                $target = $a[0];
                $alias = $a[1];
                echo "SetReg adding channel alias set $mod, $target, $alias\n";
                $this->addChanAlias($mod, $target, $alias);
            }
        }
        if(array_key_exists('account_alias', $info) && is_array($info['account_alias'])) {
            foreach($info['account_alias'] as $a) {
                $target = $a[0];
                $alias = $a[1];
                echo "SetReg adding account alias set $mod, $target, $alias\n";
                $this->addAccountAlias($mod, $target, $alias);
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
