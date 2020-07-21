<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';
require_once('modules/Module.inc');

class MMControl extends Module {
    function cmd_reload(CmdRequest $r) {
        $rv = $this->pMM->reloadModule($r->args['module']);
        if($rv === true) {
            $r->reply('Done.. maybe');
        }
        if($rv === -3) {
            $r->reply("Module {$r->args['module']} does not exist. (case sensitive)");
        }
        if($rv === -2) {
            $r->reply('Module code hasnt changed, eval() skipped');
        }
        if($rv === -1) {
            $r->reply('Reload failed because of null registry');
        }
    }

     function cmd_loadmod(CmdRequest $r) {
        if($this->pMM->isLoaded($r->args['module'])) {
            throw new CmdException("That module is already loaded, use reload instead");
        }
        if(!file_exists('modules/' . $r->args['module'])) {
            throw new CmdException("Cannot find module files.");
        }
        $this->pMM->MLoader->needModule($r->args['module']);
        $this->pMM->checkDep($r->args['module']);
        $this->pMM->loadModule($r->args['module'], true);
        $r->notice("Module loaded (maybe)");
     }

     function cmd_addmodule(CmdRequest $r) {
        if($this->pMM->isLoaded($r->args['module'])) {
            throw new CmdException("That module is already loaded, use reload instead");
        }
        if(!file_exists('modules/' . $r->args['module'])) {
            throw new CmdException("Cannot find module files.");
        }
        $this->pMM->addModule($r->args['module']);
        $r->notice("Module added to mysql and loaded.");
     }

     function cmd_delmodule(CmdRequest $r) {
        if(!$this->pMM->isListed($r->args['module'])) {
            throw new CmdException("That module is not listed, case-sensitive.");
        }
        $this->pMM->delModule($r->args['module']);
        $r->notice("Module removed from mysql and not unloaded. (unloading unsupported)");
     }

    function cmd_modules(CmdRequest $r) {
        $listed = $this->pMM->getModules();
        $loaded = '';
        foreach($this->pMM->modules as $n => $m) {
            $loaded .= $n . '_' . $this->pMM->MLoader->getItr($n) . ' ';
        }
        $loaded = trim($loaded);
        $r->notice("Modules in list.conf: " . implode(' ', $listed['list.conf']));
        $r->notice("Modules in Mysql: " . implode(' ', $listed['mysql']));
        $r->notice("Modules loaded: $loaded");
    }

}
?>