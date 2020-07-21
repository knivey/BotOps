<?php

require_once __DIR__ . '/../Tools/Tools.php';
require_once 'Module.inc';
require __DIR__ . '/../../vendor/autoload.php';

use Nette\Neon\Neon;

class ModuleLoader {
    public $unique; //unique string
    /*
     * $modules['modname'] = Array();
     * 'info' - information from $name.neon
     * 'classname' - fixed name of class
     * 'classes' - Array() of extra classes fixed names
     * 'otherFiles' - Array() of other files names in module
     * 'date' - date it was loaded
     * 'fileTime' - time file was last modify at point of loading
     * 'iteration' - counter of how many times its been loaded
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
            throw new \Exception("Requested info for '$mod' doesn't exist.");
        }
        return $this->modules[$mod]['info'];
    }

    function getName($mod) {
        return $this->modules[$mod]['classname'];
    }

    function getDesc($mod) {
        return $this->modules[$mod]['info']['description'] ?? 'No description';
    }

    /*
     * called on bot init will not reeval module
     * code if its already evaled()
     *
     * TODO there can be infinite recursion
     */
    function needModule($mod) {
        if(array_key_exists($mod, $this->modules)) {
            return;
        }
        $this->loadModule($mod);
    }

    function getDep($mod) {
        return $this->modules[$mod]['info']['require'] ?? Array();
    }

    function loadRegistry($name) {
        $confFile = file_get_contents("./modules/$name/$name.neon");
        if (!$confFile) {
            echo "Error: Could not find valid config in modules/$name/$name.neon";
            die();
        }
        try {
            $conf = Neon::decode($confFile);
        } catch (Nette\Neon\Exception $e) {
            //TODO maybe not die
            echo "Error: exception while decoding in modules/$name/$name.neon\n";
            var_dump($e);
            die();
        }
        return $conf;
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

        //TODO just load all files not require one to have mod name?
        $otherFiles = $this->searchDir("./modules/$name/", '*.php');
        $filename = "./modules/$name/$name.php";

        foreach($otherFiles as $k => $fn) {
            if($fn == $name.'.php') {
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
        $exports = Array();
        $classes = Array();
        //TODO run php_check_syntax on everything
        //go through all other php files in module dir
        foreach($otherFiles as $f) {
            $subcode[$f] = file_get_contents("./modules/$name/$f");
            if(preg_match('/^\<\?([pP][hH][pP])?\s+\/\/EXPORT$/m', $subcode[$f])) {
                //This code is to be exported for use in other modules, don't change it
                $exports[] = $f;
                continue;
            }
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

            if(in_array($f, $exports)) {
                require_once './modules/' . $name . '/' . $f;
            } else {
                if (file_put_contents($tempFile, $subcode[$f]) === false) {
                    die("Could not write temporary file $tempFile");
                }

                echo "Loading file $tempFile\n";
                include_once $tempFile;
                unlink($tempFile);
            }
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