<?php

require_once('modules/Module.inc');
require_once('Tools/Tools.php');

class listdb extends Module {
    public $llist = Array();
    public $lkeys = Array();
    public $nlist = Array();
    public $newchans = Array();
    public $deadchans = Array();
    public $qm = true;
    public $delay = 350;
    public $ntime;
    
    function rehash(&$old) {
         $this->llist = $old->llist;
         $this->nlist = $old->nlist;
         $this->ntime = $old->ntime;
         $this->init = $old->init;
         $this->lkeys = $old->lkeys;
         $this->newchans = $old->newchans;
         $this->deadchans = $old->deadchans;
         $this->delay = $old->delay;
         $this->qm = $old->qm;
    }
    
    function init() {
        $this->ntime = time() + 30;
        $this->init = true;
    }
    
    function logic() {
        if($this->ntime < time()) {
            $this->pIrc->raw("LIST >0");
            $this->ntime = time() + $this->delay;
        }
    }
    
    function cmd_csearch($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
    }
    
    function cmd_listinfo($nick, $chan, $msg) {
        $this->pIrc->msg($chan, "I have " . count($this->nlist) . " Channels in the listdb");
    }
    
    function cmd_listquiet($nick, $chan, $msg) {
        if($this->qm) {
            $this->qm = false;
            $this->delay = 7;
            $this->ntime = time() + 4;
            $this->pIrc->msg($chan, "List display ON, db update set to 7s");
        } else {
            $this->qm = true;
            $this->delay = 350;
            $this->ntime = time() + 350;
            $this->pIrc->msg($chan, "List display off, db update set to 5m");
        }
    }
    
    public $init;
    //>> :bots.phuzion.net 321 knivey Channel :Users  Name
    function h_321($msg) {
        list($argc, $argv) = niceArgs($msg);
        $this->llist = $this->nlist;
        if(!$this->qm) {
            $this->lkeys = array_keys($this->llist);
        }
        $this->nlist = Array();
    }
    //>> :bots.phuzion.net 322 knivey #bots 6 :http://botops.net
    function h_322($msg) {
        list($argc, $argv) = niceArgs($msg);
        $argv[5] = substr($argv[5], 1);
        $this->nlist[$argv[3]] = Array($argv[4], arg_range($argv, 5, -1));
        if(!$this->qm && array_search($argv[3], $this->lkeys) === false) {
            $this->newchans[] = $argv[3];
        }
    }
    //>> :bots.phuzion.net 323 knivey :End of /LIST
    function h_323($msg) {
        list($argc, $argv) = niceArgs($msg);
        if (!$this->qm) {
            $nkeys = array_keys($this->nlist);
            if (!$this->init) {
                foreach ($this->llist as $k => $v) {
                    if (array_search($k, $nkeys) === false) {
                        $this->deadchans[] = $k;
                    }
                }
            }
            if (!empty($this->newchans) && !$this->init) {
                $this->pIrc->msg('#h4x', "(" . count($this->newchans) . ") new chans " . implode(' ', $this->newchans));
            }
            if (!empty($this->deadchans) && !$this->init) {
                $this->pIrc->msg('#h4x', "(" . count($this->deadchans) . ") removed chans " . implode(' ', $this->deadchans));
            }
        }
        $this->deadchans = Array();
        $this->newchans = Array();
        if($this->init && !empty($this->llist)) {
            $this->init = false;
        }
    }
    
}

?>
