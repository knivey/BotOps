<?PHP
/*
 *  Manage our modules, find them, load them parse the registry.conf
 *  add their callbacks to parts of the bot
 */

require_once __DIR__ . '/../Tools/Tools.php';
require_once 'Module.inc';
require_once 'ModuleLoader.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Nette\Neon\Neon;


class ModuleManager {
    public $pIrc;
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
    function __construct(Irc &$Irc, PDO &$Mysql, &$XMLRPC, ModuleLoader &$MLoader, $cinfo) {
        $this->pIrc = &$Irc;
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
                    $class = $this->modules[$slot['modName']]['class'];
                    $class->{$slot['func']}($s['args']);
                }
            }
        }
    }

    function sendSignal($name, $fromMod, $args) {
        $this->signalQ[] = Array('name' => $name, 'fromMod' => $fromMod, 'args' => &$args);

    }

    function addSlot($name, $fromMod, $func, $modName = '') {
        $this->slots[$fromMod][$name][] = Array('func' => $func, 'modName' => $modName);
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
            if ($line == NULL || $line[0] == '#') {
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
        
        $this->modules[$name]['class']->setVars($this->pIrc, $this->pSockets, $this->pBotNet, $this->pMysql, $this, $cinfo);
        $this->modules[$name]['class']->init();
        //register hooks
        if($reload) {
            $this->delModSlots($name);
        }
        if(isset($info['slots'])) {
            foreach($info['slots'] as $func => $slot) {
                $this->addSlot($slot['name'], $slot['from'], $func, $name);
            }
        }
        if($reload) {
            $this->sendSignal('reload', 'ModuleManager', Array('name' => $name));
        } else {
            $this->sendSignal('load', 'ModuleManager', Array('name' => $name));
        }

        if(isset($info['XMLRPC'])) {
            foreach($info['XMLRPC'] as $method => $func) {
                $this->pXMLRPC->setRPC($method, $this->modules[$name]['class'], $func);
            }
        }
        if(isset($info['irc'])) {
            if(isset($info['irc']['hooks']) && is_array($info['irc']['hooks'])) {
                foreach($info['irc']['hooks'] as $func => $hook) {
                    $this->pIrc->eventServer->addListener($hook['hook'], $this->modules[$name]['class'], $func);
                }
            }
        }
        return $rv;
    }

    function getConf($name, $section) {
        $info = $this->MLoader->getInfo($name);
        if(isset($this->modules[$name]) && isset($info['Modules'][$section])) {
            return $info['Modules'][$section];
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











