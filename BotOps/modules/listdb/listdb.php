<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';
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
    public ?string $listTimer = null;
    
    function rehash(&$old) {
        $this->llist = $old->llist;
        $this->nlist = $old->nlist;
        $this->init = $old->init;
        $this->lkeys = $old->lkeys;
        $this->newchans = $old->newchans;
        $this->deadchans = $old->deadchans;
        $this->delay = $old->delay;
        $this->qm = $old->qm;
        Amp\Loop::cancel($old->listTimer);
        $this->setTimer($this->delay);
    }

    function __destruct()
    {
        if($this->listTimer != null)
            Amp\Loop::cancel($this->listTimer);
    }

    function init() {
        $this->setTimer(350);
        $this->init = true;
    }
    
    function reqList() {
            $this->pIrc->raw("LIST >0");
    }

    function setTimer($delay) {
        $this->delay = $delay;
        if($this->listTimer != null)
            Amp\Loop::cancel($this->listTimer);
        $this->listTimer = Amp\Loop::repeat($delay * 1000, [$this, 'reqList']);
    }
    
    function cmd_csearch(CmdRequest $r) {
        $r->notice("Searching not implemented yet.");
    }
    
    function cmd_listinfo(CmdRequest $r) {
        $r->reply("I have " . count($this->nlist) . " Channels in the listdb");
    }
    
    function cmd_listquiet(CmdRequest $r) {
        if($this->qm) {
            $this->qm = false;
            $this->delay = 7;
            $this->setTimer($this->delay);
            $r->reply("List display ON, db update set to 7s", 0, 1);
        } else {
            $this->qm = true;
            $this->delay = 350;
            $this->setTimer($this->delay);
            $r->reply("List display off, db update set to 5m", 0, 1);
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
