<?php
/* 
 * Helper classes for our chunk parsing
 */


class C_Parse {
    public $chunks;
    public $sc;
    public $vc;
    function __construct($parent, $vars, $cbFunc, $cbClass, $string, $extra, $pkey) {
        $this->parent = $parent;
        $this->cbFunc = $cbFunc;
        $this->cbClass = $cbClass;
        $this->vars = $vars;
        $this->names = array_keys($this->vars);
        arsort($this->names); //put names in right order for searching
        $this->string = $string;
        $this->chunks = new C_List();
        $this->extra = $extra;
        $this->pkey = $pkey;
    }
    
    function __destruct() {
        //$this->parent->pIrc->msg('#scorebots', 'C_Parse destructor has been called', true, true);
        $this->cleanup();
    }

    //hopefully will destroy all references to everything everywhere
    function cleanup() {
        unset($this->sc);
        unset($this->vc);
        if(isset($this->chunks) && is_object($this->chunks)) {
            $this->chunks->cleanup();
        }
        unset($this->chunks);
    }
    
    public $pause = false; //lets us know if a pause was called

    function pause() { //called from a chunk
        $this->pause = true;
    }

    function resume() { //called from the chunk after it got its info
        $this->pause = false;
        return;
    }

    public $processing = 'string';
    public $sbuf = '';
    public $escaped = false;
    public $expecting = Array();
    public $lvl = 0;
    public $k = 0;
    public $escapable = Array('$' => '$');

    function parse() {
    //back slash is chr(92)
        //$this->parent->pIrc->msg('#bots', $this->lvl);
        for(;$this->k < strlen($this->string); $this->k++) {
            if($this->pause) {
                //$this->parent->pIrc->msg('#bots', $this->lvl);
                return;
            }
            $this->c = $this->string{$this->k};
            switch($this->processing) {
                case 'string':
                    if(!empty($this->expecting)) { //for when we either get a certain char or error
                        if($this->c == ' ') break;
                        if(array_key_exists($this->c, $this->expecting) === FALSE) {
                            $this->chunks->abortVar();
                            //aborted var should set out to its raw and stop accepting chunks
                            //continue processing string
                            $this->expecting = Array();
                        //break;
                        } else {
                            if($this->expecting[$this->c] == 'startargs') {
                                $this->escapable[')'] = ")";
                                $this->escapable[','] = ",";
                                $this->lvl++;
                            }
                            $this->expecting = Array();
                            break;
                        }
                    }

                    if($this->escaped) {// $ and \
                        if(array_search($this->c, $this->escapable) !== false) {
                        //add a $c to string
                            $this->sbuf .= $this->c;
                        } else {
                        //add a 92 and $c to string
                            $this->sbuf .= chr(92) . $this->c;
                        }
                        $this->escaped = false;
                        break;
                    }
                    if($this->c == chr(92) && !$this->escaped) {
                        $this->escaped = true;
                        break;
                    }

                    if($this->c == '$') {
                        $this->processing = 'prevar';
                        break;
                    }
                    if($this->lvl == 0) {
                        $this->sbuf .= $this->c;
                    } else {
                        if($this->c == ',') {
                            if(!empty($this->sbuf)) {
                            //dump sbuf
                                $this->sc = new C_String($this->sbuf);
                                $this->chunks->addChunk($this->sc);
                                $this->sbuf = '';
                            }
                            $this->rv = $this->chunks->nextArg();
                            if($this->rv == -1) {
                                $this->sbuf .= $this->c;
                            }
                        } elseif($this->c == ')') {
                            if(!empty($this->sbuf)) {
                            //dump sbuf
                                $this->sc = new C_String($this->sbuf);
                                $this->chunks->addChunk($this->sc);
                                $this->sbuf = '';
                            }
                            $this->chunks->closeArgs();
                            $this->lvl--;
                            if($this->lvl == 0) {
                                unset($this->escapable[')']);
                                unset($this->escapable[',']);
                            }
                        } else {
                            $this->sbuf .= $this->c;
                        }
                    }
                    break;
                case 'prevar':
                    $this->vs = $this->k;
                    //look for matching variables
                    $this->found = false;
                    foreach($this->names as $this->name) {
                        $this->t = substr($this->string, $this->vs, strlen($this->name));
                        if(strtolower($this->t) == $this->name && !$this->found) {
                        //match found! skip k to end of varname
                            $this->found = true;
                            $this->k = $this->vs + strlen($this->name) -1;
                            //dump sbuf
                            if($this->sbuf != null) {
                                $this->sc = new C_String($this->sbuf);
                                $this->chunks->addChunk($this->sc);
                                $this->sbuf = '';
                            }
                            if(!isset($this->vars[$this->name]['narg']) || $this->vars[$this->name]['narg'] == null) $this->narg = 0; else $this->narg = $this->vars[$this->name]['narg'];
                            $this->vc = new C_Var($this->name, $this->narg, $this->parent->gM($this->vars[$this->name]['mod']), $this->vars[$this->name]['func'], $this->vars[$this->name]['args'], $this, $this->vars[$this->name]['store']);
                            $this->chunks->addChunk($this->vc);

                            //check if we have args and update expecting //processing
                            if($this->vars[$this->name]['args'] != null) {
                                $this->expecting['('] = 'startargs';
                            }
                            $this->processing = 'string';
                        }
                    }
                    if(!$this->found) {
                        $this->sbuf .= '$' . $this->c;
                        $this->processing = 'string';
                    }
                    break;
            }
        }
        //var_dump($chunks);
        if(!empty($this->sbuf)) {
            //$this->parent->pIrc->msg('#bots', $sbuf);
        //dump sbuf
            $this->sc = new C_String($this->sbuf);
            $this->chunks->addChunk($this->sc);
            $this->sbuf = '';
        }
        //echo "lvl $lvl proc $processing sbuf $sbuf\n";
        return $this->chunks->getOut();
    }
}

//holds a list of chunks to be parsed
//used for each arg on vars
class C_List {
    public $chunks = Array();
    public $cC = -1;
    public $type = 'C_List';

    function __destruct() {
        $this->cleanup();
    }
    
    function cleanup() {
        if(!isset($this->chunks) || !is_array($this->chunks)) {
            return;
        }
        foreach($this->chunks as $key => $val) {
            unset($this->chunks[$key]);
            unset($val);
        }
        unset($this->chunks);
    }
    
    function addChunk($chunk) {
    // this is probably where we should test the current chunk and see
    // if it's able to accept a new chunk (var args)
        if(empty($this->chunks)) {
            $this->cC++;
            $this->chunks[$this->cC] = &$chunk;
            //var_dump($this->chunks);
            return;
        }
        if($this->chunks[$this->cC]->acceptChunk()) {
            $this->chunks[$this->cC]->addChunk($chunk);
        } else {
            $this->cC++;
            $this->chunks[$this->cC] = &$chunk;
        }
       /* foreach($this->chunks as $k => $c) {
            $cc = $c->class;
            $c->class = null;
            echo "CHUNK $k\n";
            print_r($c);
            $c->class = $cc;
        }*/
    }

    function getType() {
        return $this->type;
    }

    function curType() {
        return $this->chunks[$this->cC]->getType();
    }

    function cur() {
        return $this->chunks[$this->cC];
    }

    function canCloseArg() {
        if($this->cC == -1) return false;
        return $this->chunks[$this->cC]->canCloseArg();
    }

    function canNextArg() {
        if($this->cC == -1) return false;
        return $this->chunks[$this->cC]->canNextArg();
    }

    function canAbortVar() {
        if($this->cC == -1) return false;
        return $this->chunks[$this->cC]->canAbortVar();
    }

    function closeArgs() {
        $this->chunks[$this->cC]->closeArgs();
    }

    function abortVar() {
        $this->chunks[$this->cC]->abortVar();
    }

    function nextArg() {
        return $this->chunks[$this->cC]->nextArg();
    }

    function deep() {
    //return reference to deepest chunk that still accepts
    //or a reference to us, probably not used might not work lol

        $c = &$this->chunks[$this->cC];
        if(!$c->acceptChunk()) {
            return $this;
        }
        $lc = null;
        while($c->acceptChunk()) {
            if($c->cur() != null) {
                $lc = &$c;
                $c = &$c->cur();
                if(!$c->acceptChunk()) {
                    $c = &$lc;
                    break;
                }
            } else {
                break;
            }
        }
        return $c;
    }

    //return the final output
    function getOut() {
    //var_dump($this);
        $out = '';
        foreach($this->chunks as &$c) {
            $out .= $c->getOut();
        }
        return $out;
    }
}

class C_Base {
    public $type; // chunk type
    public $raw; // Raw data before being parsed
    public $out; // Set to what the chunk evals to
    public $parent; // parser parent

    //to be overidden
    function process() {

    }

    function acceptChunk() {
        return false;
    }

    function canCloseArg() {
        return false;
    }

    function canAbortVar() {
        return false;
    }

    function canNextArg() {
        return false;
    }

    function getType() {
        return $this->type;
    }

    function cur() {
        return null;
    }

    function getOut() {
        return $this->out;
    }
}

class C_String extends C_Base {
    function  __construct($string) {
        $this->type = 'C_String';
        //$this->raw = $string;
        $this->out = $string;
    }

    function setRaw($text) {
        $this->raw = $text;
    }

    function append($text) {
        $this->out .= $text;
    }

    function setText($text) {
        $this->out = $text;
    }
}

//since this is just simple stuff all var/func
//things return/eval to plain string probably?
class C_Var extends C_Base {
    public $args = Array();
    public $argi = Array(); // arg info
    public $name; //variable name
    public $class; //ref to class of var
    public $func; //function name in ref class to call
    public $argNum; //number of args we need
    public $cArg = 0; //current arg
    public $store;

    function  __construct($name, $argNum, $class, $func, $argi, $parent, $store) {
        $this->type = 'C_Var';
        $this->class = &$class;
        $this->name = $name;
        $this->func = $func;
        $this->argNum = $argNum;
        $this->argi = $argi;
        $this->parent = &$parent;
        $this->store = $store;

        if($this->argNum == 0) {
        //    $args = Array();
            $args['cbFunc'] = 'finishInfo';
            $args['cbClass'] = $this;
            $this->out = $this->class->$func($args, $store);
            if(is_array($this->out) && array_key_exists('pause', $this->out)) {
                $this->out = '';
                $this->paused = true;
                $this->parent->pause();
            }
            //$this->out = $this->class->$func();
        }
        $this->args[$this->cArg] = new C_List();
    }

    function addChunk($chunk) {
    //append the chunk to the current arg

        $this->args[$this->cArg]->addChunk($chunk);
    }

    function canAbortVar() {
        return true;
    }

    function abortVar() {
        if($this->args[$this->cArg]->canAbortVar()) {
            $this->args[$this->cArg]->abortVar();
            return;
        }
        $this->out = '[Error: ' . $this->name . ' Expected an opening \'(\']';
        $this->stopAccept = true;
    }

    public $stopAccept = false;

    function acceptChunk() {
        if($this->stopAccept) return false;
        if($this->cArg <= $this->argNum -1) {
            return true;
        }
    }

    function cur() {
        if($this->cArg <= $this->argNum -1) {
            return $this->args[$this->cArg]->cur();
        } else {
            return null;
        }
    }

    function canNextArg() {
        if($this->argNum == 0 || $this->cArg == $this->argNum) return false;
        if($this->args[$this->cArg]->canNextArg()) {
            return true;
        }
        return true;
    }

    function nextArg() {
    //check if current arg can nextarg
        if($this->args[$this->cArg]->canNextArg()) {
            $this->args[$this->cArg]->nextArg();
            return;
        }
        //advance to the next arg
        $this->cArg++;
        if($this->cArg > $this->argNum -1) {
            $this->cArg--;
            return -1;
        } else {
            $this->args[$this->cArg] = new C_List();
        }
    }

    function canCloseArg() {
        if($this->cArg == $this->argNum || $this->stopAccept) {
            return false;
        } else {
            if($this->args[$this->cArg]->canCloseArg()) {
                return true;
            } else {
                if($this->cArg <= $this->argNum -1) {
                    return true;
                }
            }
        }

        return false;
    }

    function finishInfo($info) { // this is the called by the var function
        $this->out = $info;
        $this->paused = false;
        $this->parent->resume();
    }

    function closeArgs() {
    //check if current arg can closearg
        if($this->args[$this->cArg]->canCloseArg()) {
            $this->args[$this->cArg]->closeArgs();
            return;
        }
        //make sure we got all our args
        if($this->cArg < $this->argNum -1) {
        //error or warning here!!
            $argi = implode(',', $this->argi);
            $this->out = '[Error: ' . $this->name . " Invalid args, expected $argi]";
            $this->stopAccept = true;
            return;
        }
        $this->cArg++;

        $fname = $this->func;
        $args = Array();
        foreach($this->args as &$a) {
            $args[] = $a->getOut();
        }
        $args['cbFunc'] = 'finishInfo';
        $args['cbClass'] = $this;
        $this->out = $this->class->$fname($args, $this->store);
        if(is_array($this->out) && array_key_exists('pause', $this->out)) {
            $this->out = '';
            $this->paused = true;
            $this->parent->pause();
        }
    //check for error somehow
    }
}

?>
