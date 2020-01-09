<?php

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

}
?>