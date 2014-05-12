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
 * MMControl.php
 *   Gives us accss to load/unload/rehash/etc modules
 ***************************************************************************/
require_once('modules/Module.inc');

class MMControl extends Module {
    function cmd_reload($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
		return $this->gM('CmdReg')->rV['BADARGS'];
	}
        $rv = $this->pMM->reloadModule($arg[0]);
        if($rv === true) {
            $this->pIrc->msg($chan, 'Done.. maybe');
        }
        if($rv === -3) {
            $this->pIrc->msg($chan, "Module $arg[0] does not exist. (case sensitive)");
        }
        if($rv === -2) {
            $this->pIrc->msg($chan, 'Module code hasnt changed, eval() skipped');
        }
        if($rv === -1) {
            $this->pIrc->msg($chan, 'Reload failed because of null registry');
        }
    }

     function cmd_loadmod($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
		return $this->gM('CmdReg')->rV['BADARGS'];
	}
        if($this->pMM->isLoaded($arg[0])) {
            $this->pIrc->notice($nick, "That module is already loaded, use reload instead");
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        if(!file_exists('modules/' . $arg2)) {
            $this->pIrc->notice($nick, "Cannot find module files.");
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $this->pMM->MLoader->needModule($arg2);
        $this->pMM->checkDep($arg2);
        $this->pMM->loadModule($arg2, true);
        $this->pIrc->notice($nick, "Module loaded (maybe)");
     }

     function cmd_addmodule($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
		return $this->gM('CmdReg')->rV['BADARGS'];
	}
        if($this->pMM->isLoaded($arg[0])) {
            $this->pIrc->notice($nick, "That module is already loaded, use reload instead");
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        if(!file_exists('modules/' . $arg2)) {
            $this->pIrc->notice($nick, "Cannot find module files.");
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $this->pMM->addModule($arg[0]);
        $this->pIrc->notice($nick, "Module added to mysql and loaded.");
     }

     function cmd_delmodule($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
		return $this->gM('CmdReg')->rV['BADARGS'];
	}
        if(!$this->pMM->isListed($arg[0])) {
            $this->pIrc->notice($nick, "That module is not listed, case-sensitive.");
            return $this->gM('CmdReg')->rV['ERROR'];
        }

        //if(!file_exists('modules/' . $arg2)) {
        //    $this->pIrc->notice($nick, "Cannot find module files.");
        //    return $this->gM('CmdReg')->rV['ERROR'];
        //}
        $this->pMM->delModule($arg[0]);
        $this->pIrc->notice($nick, "Module removed from mysql and not unloaded. (unloading unsupported)");
     }

    function cmd_modules($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        $listed = $this->pMM->getModules();
        $loaded = '';
        foreach($this->pMM->modules as $n => $m) {
            $loaded .= $n . '_' . $this->pMM->MLoader->getItr($n) . ' ';
        }
        $loaded = trim($loaded);
        $this->pIrc->notice($nick, "Modules in list.conf: " . implode(' ', $listed['list.conf']));
        $this->pIrc->notice($nick, "Modules in Mysql: " . implode(' ', $listed['mysql']));
        $this->pIrc->notice($nick, "Modules loaded: $loaded");
    }

    function cmd_svn($nick, $target, $arg2) {
        //Setup our normal variables..
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target); //Later on we might change this command for use via PM
        $access = $this->gM('user')->access($hand, $chan);
        if(empty($arg[0])) {
		return $this->gM('CmdReg')->rV['BADARGS'];
	}
        if(cisin($arg2, '`&;')) {
		$this->pIrc->notice($nick, "Invalid characters found.");
		return $this->gM('CmdReg')->rV['ERROR'];
	}
        if($target{0} != '#') return $this->gM('CmdReg')->rV['ERROR'];
        if($arg2 != 'update') {
            $this->pIrc->notice($nick, "Only update available thanks to \2KURIZU\2");
            return $this->gM('CmdReg')->rV['ERROR'];
        }
        $msg = trim(`svn $arg2`);
        $this->pIrc->msg($target, $msg, false);
    }
}
?>