<?php

require_once 'Tools/Tools.php';

class IrcFilters {
    public $filterHandler = Array();
    public $filters = Array();
    
    public function getFilters() {
        return $this->filters;
    }

    public function loadFilters(Array $filters = Array()) {
        $this->filters = $filters;
    }

    public function setFilterHandler(&$class, $func) {
        $this->filterHandler = Array(&$class, $func);
    }

    public function passFilter($txt) { // Does our string pass the rules
        if(empty($this->filters)) {
            return true;
        }
        foreach($this->filters as $filt) {
            if(pmatch($filt['text'], $txt)) {
                if (!empty($this->filterHandler) && is_callable($this->filterHandler)) {
                    list($c, $f) = $this->filterHandler;
                    $c->$f($filt, $txt);
                }
                return FALSE;
            }
        }
        return TRUE;
    }
}

?>
