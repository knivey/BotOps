<?PHP
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
 * ModuleManager.inc
 *  Manage our modules, find them, load them parse the registry.conf
 *  add their callbacks to parts of the bot
 ***************************************************************************/

require_once 'Tools/Tools.php';
require_once 'Module.inc';


/* Work flowish
 * 1) read the list.conf file to get a list of modules to load
 * 2) read registry.conf for each mod into mem
 * 3) make sure all requires will be met (no need for load order)
 *    also make sure all mods we definatly need will be loaded (User, CmdReg)
 * 3) parse modules php code
 * 4) register all irc hooks
 * 5) process all signals (CmdReg module should catch signals for 
 *    each modloaded so it can process their cmds from registry.conf)
 * Hopefully this flow will work ;)
 */
/*
 * going to move loading, eval()
 * and tracking classnames
 * of modules to ModuleLoader
 * ModuleManager will just tell
 * ModuleLoader the modules it needs
 * loaded or reloaded and get the names
 * from it...
 * ModuleLoader will be created in a
 * global scope so all bots can share
 * the same eval()ed code....
 * 
 */


class ModuleLoader {
    public $unique; //unique string
    /*
     * $modules['modname'] = Array();
     * 'info' - information from registry.conf
     * 'classname' - fixed name of class
     * 'classes' - Array() of extra classes fixed names
     * 'otherFiles' - Array() of other files names in module
     * 'date' - date it was loaded
     * 'fileTime' - time file was last modify at point of loading
     * 'iteration' - counter of how many times its been loaded
     * 'regiteration'
     */
    public $modules = Array();

    function __construct() {
        for ($i = 0; $i < 10; $i++) {
            $this->unique .= chr(rand(ord('a'), ord('z')));
        }
    }
    
    function getInfo($mod) {
        if(!array_key_exists($mod, $this->modules) ||
                !array_key_exists('info', $this->modules[$mod])) {
            return false;
        }
        return $this->modules[$mod]['info'];
    }
    
    function getName($mod) {
        return $this->modules[$mod]['classname'];
    }
    
    function getDesc($mod) {
        if(isset($this->modules[$mod]['info']->description)) {
            return $this->modules[$mod]['info']->description;
        } else {
            return 'Unknown module.';
        }
    }
    
    /*
     * called on bot init will not reeval module
     * code if its already evaled()
     */
    function needModule($mod) {
        if(array_key_exists($mod, $this->modules)) {
            return;
        }
        $this->loadModule($mod);
    }

    function getDep($mod) {
        if(!isset($this->modules[$mod]['info']->require)) {
            return Array();
        }
        return $this->modules[$mod]['info']->require;
    }
    
    function loadRegistry($name) {
    //load modules registry
        $code = file_get_contents('./modules/' . $name . '/registry.php');
        if (!preg_match("/class[\s]+?".'registry'."[\s]+?{/", $code)) {
            echo "Error: Could not find valid registry class in $name";
            die();
        }
        
        $itr = 0;
        if(array_key_exists($name, $this->modules) && array_key_exists('regiteration', $this->modules[$name])) {
            $this->modules[$name]['regiteration']++;
            $itr = $this->modules[$name]['regiteration'];
        }
        $newName = $this->unique . '_' . $name . "_registry_" . $itr;
        $newcode = preg_replace("/(class[\s]+?)registry([\s]+?{)/", "\\1" . $newName . "\\2", $code);

        $tempFile = './modules/' . $name . '/' . $newName . '.php';
        
        if(file_put_contents($tempFile, $newcode) === false) {
        	die("Could not write to temporary file");
        }
        /*
        if(eval('?>' . $newcode . '<?') === false) {
            echo "PHP Error in code file ./modules/$name/registry.php\n";
            die();
        }
        */
        
        include_once $tempFile;
        if(unlink($tempFile) === false) {
        	echo "WARNING: could not delete a temporary file $tempFile!!!\n";
        }
        
        $this->modules[$name]['regiteration'] = $itr;
        $info = new $newName();
        return $info;
    }

    function loadModule($name, $firstStart = false) {
        if($firstStart == true) {
            //ugly fix to keep from loading several times
            //this is temp until i implement using filehash or filemtime
            return;
        }
        $info = $this->loadRegistry($name);
        $this->modules[$name]['info'] = $info;

        /*
         * Load the modules php code
         * change the name of the class eval it and keep track of
         * its evaled class name and last modify times for "rehash"
         * next go through loaded config and make binds and register sets
         */


        if(!is_object($info)) {
            die("loadModule givin invalid registry\n");
        }

        /*
         * search the folder for other php files to load...
         * read files then replace any classnames
         * eval the fixed code
         * store indexes of original name and replaced name in
         * module's array
         *
         * search modules code and replace calls to new Class
         * with fixed names
         */
        $otherFiles = $this->searchDir("./modules/$name/", '*.php');
        $filename = "./modules/$name/$name.php";
        
        foreach($otherFiles as $k => $fn) {
            if($fn == $name.'.php') {
                unset($otherFiles[$k]);
            }
            if($fn == 'registry.php') {
                unset($otherFiles[$k]);
            }
        }
        
        $goahead = false;
        if(!array_key_exists('fileHash', $this->modules[$name]) || $this->modules[$name]['fileHash'] != md5_file($filename)) {
            $goahead = true;
        }
        foreach($otherFiles as $fk => $f) {
            $hash = md5_file("./modules/$name/$f");
            if(!array_key_exists('otherFilesHash', $this->modules[$name])
                    || $hash != $this->modules[$name]['otherFilesHash'][$f]) {
                $goahead = true;
            }
        }
        if(!$goahead) {
            echo "No files changed not loading $name\n";
            return;
        }

        $code = file_get_contents($filename);
        if ($code === false) {
            echo "Error opening $filename not loading module\n";
            return;
        }

		/*
		 * Thanks to PHP-IRC for giving us the idea for this
		 * using their regular expressions here
		 */
        if (!preg_match("/class[\s]+?".$name."[\s]+?extends[\s]+?Module[\s]+?{/", $code)) {
            echo "Error: Could not find valid classdef in $filename";
            return false;
        }

        $reload = false;
        $itr = 0;
        if(array_key_exists($name, $this->modules) && array_key_exists('iteration', $this->modules[$name])) {
        //we are reloading
        //if(filemtime($filename) > $this->modules[$name]['fileTime']) {
            $this->modules[$name]['iteration']++;
            $itr = $this->modules[$name]['iteration'];
            $reload = true;
        //}
        }

        $newName = $this->unique . '_' . $name . "_" . $itr;
        $mainTempFile = './modules/' . $name . '/' . $newName . '.php';

        $newcode = preg_replace("/(class[\s]+?)".$name."([\s]+?extends[\s]+?Module[\s]+?{)/", "\\1" . $newName . "\\2", $code);

        $subcode = Array();
        $classes = Array();
        //go through all other php files in module dir
        foreach($otherFiles as $f) {
            $subcode[$f] = file_get_contents("./modules/$name/$f");
            preg_match_all('/class[\s]+?(\w+)[\s]+?(extends|{)/', $subcode[$f], $matches);
            //var_dump($matches);
            foreach ($matches[1] as $c) {
                $classes[$c] = $this->unique . '_' . $c . "_" . $itr;
                $subcode[$f] = preg_replace("/(class[\s]+?)".$c."([\s]+?extends|[\s]+?{)/", "\\1" . $this->unique . '_' . $c . "_" . $itr . "\\2", $subcode[$f]);
            }
        }
        $this->modules[$name]['otherFiles'] = $otherFiles;
        $this->modules[$name]['classes'] = $classes;
        foreach($otherFiles as $f) {
            foreach ($classes as $c => $newcName) {
               $subcode[$f] = preg_replace("/(=[\s]+?new[\s]+?)$c([\s]*?;|[\s]*?\()/", "\\1" . $newcName . "\\2", $subcode[$f]);
               $subcode[$f] = preg_replace("/(extends[\s]+?)$c([\s]*?{)/", "\\1" . $newcName . "\\2", $subcode[$f]);
            }
            //var_dump($subcode[$f]);
            $newfName = $this->unique . '_' . $f . "_" . $itr;
            $tempFile = './modules/' . $name . '/' . $newfName . '.php';
            
            if(file_put_contents($tempFile, $subcode[$f]) === false) {
            	die("Could not write temporary file $tempFile");
            }
            
            echo "Loading file $tempFile\n";
            include_once $tempFile;
            unlink($tempFile);
            /*
            echo "Running eval for: ./modules/$name/$f";
            if(eval("?>" . $subcode[$f] . "<?php ") === false) {
                echo "PHP Error in code file ./modules/$name/$f\n";
                return;
            }
            */
        }
        foreach ($classes as $c => $newcName) {
            $newcode = preg_replace("/(=[\s]+?new[\s]+?)$c([\s]*?;|[\s]*?\()/", "\\1" . $newcName . "\\2", $newcode);
        }
        
        if(file_put_contents($mainTempFile, $newcode) === false) {
        	die("Could not write temporary file $mainTempFile");
        }
        
        echo "Loading file $mainTempFile\n";
        include_once $mainTempFile;
        unlink($mainTempFile);
        /*
        echo "Running eval for: ./modules/$name/$filename\n";
        if(eval("?>" . $newcode . "<?php ") === false) {
            echo "PHP Error in code file ./modules/$name/$filename\n";
            return;
        }
		*/
        //everything parsed ok

        $this->modules[$name]['iteration'] = $itr;
        $this->modules[$name]['date'] = time();
        $this->modules[$name]['fileTime'] = filemtime($filename);
        $this->modules[$name]['fileHash'] = md5_file($filename);
        foreach($otherFiles as $f) {
            $this->modules[$name]['otherFilesHash'][$f] = md5_file("./modules/$name/$f");
        }
        $this->modules[$name]['classname'] = $newName;
        
        return $newName;
    }
    
    function getItr($name) {
        if(array_key_exists($name, $this->modules) && array_key_exists('iteration', $this->modules[$name])) {
            return $this->modules[$name]['iteration'];
        }
    }

    function searchDir($dir, $search) {
        $d = opendir($dir);
        $out = Array();
        while($file = readdir($d)) {
            if(pmatch($search, $file)) {
                $out[] = $file;
            }
        }
        return $out;
    }
}

class ModuleManager {
    public $pIrc;
    public $pBotNet;
    public $pSockets;
    public $pMysql; // Mysql class
    public $pXMLRPC;
    public $MLoader; // ModuleLoader

    /**
     * Configuration info from main.conf file
     * @var Array $cinfo
     */
    public $cinfo;
    
    /* Array() indexed by name of modules
     * 'class' - pointer to loaded class
     * 'lastClass' - pointer to last class before rehash
     */
    public $modules = Array();
    
    public $mlist = Array();
    /*
     * list of modules from mysql to load
     */
    public $list = Array();
	/*
	 *  List of modules to load
	 *  from the list.conf
	 */

    private $data; // temp buffer for our read file

    /**
     * Constructor.
     * @param Irc $Irc
     * @param Sockets $Sockets
     * @param unknown $BotNet
     * @param PDO $Mysql
     * @param HttpServ $XMLRPC
     * @param ModuleLoader $MLoader
     * @param Array $cinfo
     */
    function __construct(Irc &$Irc, Sockets &$Sockets, &$BotNet, PDO &$Mysql, &$XMLRPC, ModuleLoader &$MLoader, $cinfo) {
        $this->pIrc = &$Irc;
        $this->pSockets = &$Sockets;
        $this->pBotNet = &$BotNet;
        $this->pMysql = &$Mysql;
        $this->pXMLRPC = &$XMLRPC;
        $this->MLoader = &$MLoader;
        $this->cinfo = $cinfo;
    }
    
    function stop() {
        foreach ($this->modules as $key => $mod) {
            $this->modules[$key]['class']->cleanup();
            unset($this->modules[$key]['class']);
            unset($this->modules[$key]['lastClass']);
            unset($this->modules[$key]);
        }
        unset($this->modules);
    }

    function getDesc($mod) {
        return $this->MLoader->getDesc($mod);
    }

    function init() {
        $this->searchMods();
        $list = $this->mlist;
        foreach($this->list as $m) {
            $list[] = $m;
        }
        
        foreach($list as $m) {
            $this->MLoader->needModule($m);
        }
        
        foreach($list as $m) {
            $this->checkDep($m);
        }

        foreach($list as $m) {
            $this->loadModule($m, true);
        }
        
        $this->processSignals();
    }

    public $slots = Array();
	/*
	 * $slots['fromMod'] = Array(
	 *  'name' = (name of slot) Array(
	 *    'class' = class to call
	 *    'func' = func to call
	 *  )) 
	 *  
	 */

	/*
	 * For now i think it best to leave IRC slots the way they are
	 * mostly because they have masks support.. and it would be
	 * confusing to add some events as signals
	 */

    public $signalQ = Array();

    function logic() {
        //var_dump(array_keys($this->modules));
        foreach($this->modules as &$m) {
            $m['class']->logic();
        }
    }

    function processSignals() {
        $sq = $this->signalQ;
        $this->signalQ = Array();
        foreach($sq as $s) {
            if(array_key_exists($s['fromMod'], $this->slots) && array_key_exists($s['name'], $this->slots[$s['fromMod']])) {
                foreach($this->slots[$s['fromMod']][$s['name']] as $slot) {
                //$class = &$slot['class'];
                    $class = $this->modules[$slot['modName']]['class'];
                    $class->$slot['func']($s['args']);
                }
            }
        }
    }

    function sendSignal($name, $fromMod, $args) {
        $this->signalQ[] = Array('name' => $name, 'fromMod' => $fromMod, 'args' => &$args);

    }

    function addSlot($name, $fromMod, &$class, $func, $modName = '') {
        $this->slots[$fromMod][$name][] = Array('class' => &$class, 'func' => $func, 'modName' => $modName);
    }

    function delModSlots($mod) {
        foreach($this->slots as $k => $s) {
            foreach($s as $k1 => $s1) {
                foreach($s1 as $k2 => $s2) {
                    if($s2['modName'] == $mod) {
                        unset($this->slots[$k][$k1][$k2]);
                    }
                }
            }
        }
    }

    function checkDep($m) {
        $depends = $this->MLoader->getDep($m);
        foreach ($depends as $needed) {
            if ($needed == $m) {
                echo "Of course your module ($m) requires itself\n";
            }
            $found = false;
            foreach ($this->list as $mm) {
                if ($mm == $needed) {
                    $found = true;
                }
            }
            foreach ($this->mlist as $mm) {
                if ($mm == $needed) {
                    $found = true;
                }
            }
            if (!$found) {
                die("Module $needed required by $m not found in list.\n");
            }
        }
    }

    function readFile($file) {
        $this->data = Array();
        if(file_exists($file)) {
            $fp = fopen($file, 'r');
            while (!feof($fp)) {
                $this->data[] = trim(fgets($fp));
            }
            fclose($fp);
            return true;
        }
        return false;
    }

    function searchMods() {
        $this->readFile('modules/list.conf');

        foreach ($this->data as $line) {
            if ($line == NULL || $line{0} == '#') {
                continue; // line was a comment
            }
            $this->list[] = trim($line);
        }
        $this->loadMysqlList($this->pIrc->nick);
    //for now just reads the listfile into an array
    }

    function reloadModule($name) {
        if(!array_key_exists($name, $this->modules)) {
            return -3;
        }
        $rv = $this->loadModule($name);
        $this->pSockets->chgClass($this->modules[$name]['lastClass'], $this->modules[$name]['class']);
        $this->pXMLRPC->chgClass($this->modules[$name]['lastClass'], $this->modules[$name]['class']);
        $this->modules[$name]['class']->rehash($this->modules[$name]['lastClass']);
        return $rv;
    }

    function getCname($name) {
        return get_akey_nc($name, $this->modules);
    }

    function isLoaded($name) {
        $rv = get_akey_nc($name, $this->modules);
        if($rv == '') {
            return false;
        } else {
            return true;
        }
    }

    function isListed($name) {
        foreach($this->list as $l) {
            if($name == $l) return true;
        }
        foreach($this->mlist as $l) {
            if($name == $l) return true;
        }
        return false;
    }

    function loadMysqlList($nick) {
        try {
            $stmt = $this->pMysql->prepare("select `modules` from `bots` where name = :name");
            $stmt->bindValue(':name', $nick);
            $stmt->execute();
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            //$this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        
        $this->mlist = explode(' ', $row['modules']);
        foreach($this->mlist as $m => $l) {
            if($l == '') {
                unset($this->mlist[$m]);
            }
        }
        echo "Mysql Modules: " . implode(', ', $this->mlist);
    }

    function searchDir($dir, $search) {
        $d = opendir($dir);
        $out = Array();
        while($file = readdir($d)) {
            if(pmatch($search, $file)) {
                $out[] = $file;
            }
        }
        return $out;
    }

    function loadModule($name, $firstStart = false) {
        if(!$firstStart) {
            $newName = $this->MLoader->loadModule($name);
        } else {
            $newName = $this->MLoader->getName($name);
        }
        $info = $this->MLoader->getInfo($name);
        $rv = true;
        if($newName == null) {
            echo "LoadModule got a null classname for $name\n";
            $rv = -2;
            $newName = $this->MLoader->getName($name);
        }
        
        if($info == null) {
            echo "LoadModule got a null registry for $name\n";
            return -1;
        }
        
        $reload = false;
        if(array_key_exists($name, $this->modules)
                && is_array($this->modules[$name])
                && array_key_exists('class', $this->modules[$name])) {
            //we are reloading
            $reload = true;
        }

        if($reload) {
            echo "Reloading module: $name (maybe)\n";
        }

        echo "Loading $name as $newName...\n";
        if($reload) {
            if(array_key_exists('lastClass', $this->modules[$name])) {
                $this->modules[$name]['lastClass'] = NULL;
                unset($this->modules[$name]['lastClass']);
            }
            $this->modules[$name]['lastClass'] = &$this->modules[$name]['class'];
            $this->pIrc->eventServer->delListener($this->modules[$name]['class']);
            unset($this->modules[$name]['class']);
        }
        $this->modules[$name]['class'] = new $newName();
        
        $cinfo = null;
        $ckey = get_akey_nc($name, $this->cinfo);
        if($ckey != null) {
        	$cinfo = $this->cinfo[$ckey];
        }
        var_dump($this->cinfo);
        
        $this->modules[$name]['class']->setVars($this->pIrc, $this->pSockets, $this->pBotNet, $this->pMysql, $this, $cinfo);
        $this->modules[$name]['class']->init();
        //register hooks
        if($reload) {
            $this->delModSlots($name);
        }
        if(isset($info->slots)) {
            foreach($info->slots as $slot) {
                $this->addSlot($slot[2], $slot[1], $this->modules[$name]['class'], $slot[0], $name);
            }
        }
        if($reload) {
            $this->sendSignal('reload', 'ModuleManager', Array('name' => $name));
        } else {
            $this->sendSignal('load', 'ModuleManager', Array('name' => $name));
        }

        if(isset($info->XMLRPC)) {
            foreach($info->XMLRPC as $proc) {
                $this->pXMLRPC->setRPC($proc[0], $this->modules[$name]['class'], $proc[1]);
            }
        }
        if(isset($info->leaf)) {
            if(isset($info->leaf['hooks']) && is_array($info->leaf['hooks'])) {
                foreach($info->leaf['hooks'] as $hook) {
                    $this->pIrc->eventServer->addListener($hook[1], $this->modules[$name]['class'], $hook[0]);
                }
            }
        }
        return $rv;
    }

    function getRegistry($name, $section) {
        $info = $this->MLoader->getInfo($name);
        if(array_key_exists($name, $this->modules) && isset($info->$section)) {
            return $info->$section;
        }
    }

    //lolwut?
    function delModule($name) {
        unset($this->mlist[$name]);
        try {
            $stmt = $this->pMysql->prepare("update `bots` set `modules` = :modules where `name` = :name");
            $stmt->bindValue(':modules', implode(' ', $this->mlist));
            $stmt->bindValue(':name', $this->pIrc->nick);
            $stmt->execute();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function addModule($name) {
        try {
            $stmt = $this->pMysql->prepare("update `bots` set `modules` = :modules where `name` = :name");
            $stmt->bindValue(':modules', implode(' ', $this->mlist) . ' ' . $name);
            $stmt->bindValue(':name', $this->pIrc->nick);
            $stmt->execute();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        
        $this->modules[$name]['info'] = $this->loadRegistry($name);
        $this->checkDep($name);
        $this->loadModule($name, $this->modules[$name]['info']);
        $this->mlist[] = $name;
    }

    function getModules() {
        $out = Array('list.conf' => Array(), 'mysql' => Array());
        foreach ($this->list as $l) {
            $out['list.conf'][] = $l;
        }
        foreach ($this->mlist as $l) {
            $out['mysql'][] = $l;
        }
        return $out;
    }

    function unloadModule($name) {
    //Not supported yet
    }
}











