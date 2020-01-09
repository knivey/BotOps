<?php
/*
 ***************************************************************************
 * ParseUtil.php
 *   Provides tools for parsing strings and gives us our simple scripting
 *   stuff for $vars on outgoing text.
 ***************************************************************************/

require_once 'modules/Module.inc';
require_once 'modules/ParseUtil/Chunks.php';

class ParseUtil extends Module {

    public $vars = Array();
    /*
     * Store all our $vars() from loaded registry.conf
     * $vars[strtolower(name)] = Array(
     * desc
     * args[num][name]
     * mod
     * func (better take the same args)
     * store
     */

    function clear() {
        foreach($this->vars as $v => $vv) {
            $this->vars[$v]['store'] = '';
        }
    }
    
    function set($vname, $val) {
        $vname = strtolower($vname);
        if(!array_key_exists($vname, $this->vars)) {
            return;
        }

        $this->vars[$vname]['store'] = $val;
    }

    function getV($name) {
        $name = strtolower($name);
        return $this->vars[$name]['store'];
    }

    function getUsed($name) {

    }

    function incUsed($name) {

    }

    function cmd_say($nick, $target, $arg2) {
        $this->msg($target, $arg2);
    }
    
    function cmd_act($nick, $target, $arg2) {
        $this->act($target, $arg2);
    }

    function msg($target, $msg) {
        $this->parse($msg, 'Pmsg', $this, $target);
    }
    
    function act($target, $msg) {
        $this->parse($msg, 'Pact', $this, $target);
    }

    function Pmsg($parsedString, $target) {
        $this->pIrc->msg($target, $parsedString, true, true);
    }
    
    function Pact($parsedString, $target) {
        $this->pIrc->act($target, $parsedString, true, true);
    }

    function v_nick($args, $store) { return $store; }
    function v_target($args, $store) { return $store; }
    function v_host($args, $store) { return $store; }

    function v_trig() {
        $chan = $this->getV('chan');
        return $this->gM('channel')->getTrig($chan);
    }
    
    function v_na($args, $store) {
        $arr = trim(implode(' ', $this->getArgy()));
        if($arr != '') {
            return $arr;
        }
        return trim($this->getV('nick'));
    }
    
    function v_rnick($args, $store) {
        $chan = trim($this->getV('chan'));
        $nicks = array_keys($this->gM('channel')->chanNickHosts($chan));
        return $nicks[array_rand($nicks)];
    }
    
    function v_date() {
        return strftime("%D %T");
    }

    public $argy;

    function setArgy($a) {
        $this->argy = $a;
    }

    function getArgy() {
        $a = $this->argy;
        //$this->argy = '';
        return $a;
    }

    function v_a($args) {
        if(!array_key_exists(0, $args)) {
            return;
        }
        $n = $args[0];
        $toend = false;
        if($n{strlen($n)-1} == '-') {
            $toend = true;
            $n = substr($n, 0, strlen($n) -1);
        }
        $a = $this->getArgy();
        if(!array_key_exists($n -1,$a)) {
            return;
        }
        if(!is_numeric($n)) {
            return;
        }
        $n = $n -1;
        $out = Array();
        if(!$toend) {
            return $a[$n];
        } else {
            for(; $n <= count($a); $n++) {
                $out[] = $a[$n];
            }
            return implode(' ', $out);
        }
    }

    function v_rand($args) {
        //echo "v_rand with args: " . implode(',', $args) . "\n";
        return rand((int)$args[0], (int)$args[1]);
    }
    
    public $mdir = './modules/ParseUtil/';
    function v_rnoun($args) {
        $words = file($this->mdir . 'nouns.txt');
        $word = trim($words[array_rand($words)]);
        return $word;
    }
    
    function v_rverb($args) {
        $words = file($this->mdir . 'verbs.txt');
        $word = trim($words[array_rand($words)]);
        return $word;        
    }
    
    function v_radj($args) {
        $words = file($this->mdir . 'adjectives.txt');
        $word = trim($words[array_rand($words)]);
        return $word;        
    }

    function v_bar($args) {
        $n = (float)$args[0]; $d = $args[1];
        $out = chr(22);
        if($d < 10) {
            return chr(15) . "[Error: (\$bar) arg 2 must be > 10]";
        }
        if($d > 50) {
            return chr(15) . "[Error: (\$bar) arg 2 must be < 50]";
        }
        if($d < $n) {
            return chr(15) . "[Error: (\$bar) overfull]";
        }
        $text = str_pad((int)(($n / $d) * 100), 3, ' ', STR_PAD_LEFT) . '%';
        $textpos = (($d / 2) - ((strlen($text)) / 2));
        $ended = false;
        for($i = 0; $i <= $d; $i++) {
            if($i < $textpos || $i > ($textpos + strlen($text))) {
                $out .= ' ';
            } else {
                $out .= $text{$i - $textpos};
            }
            if($i >= $n && !$ended) {
                $out .= chr(22);
                $ended = true;
            }
        }
        return $out;
    }

    function v_rainbow($args) {
        $text = $args[0];
        $text = str_split($text);
        $out = '';
        foreach($text as $c) {
            $out .= "\003" . str_pad(rand(0,16), 2, 0, STR_PAD_LEFT) . $c;
        }
        return $out . "\003";
    }
    
    function v_colorcap($args) {
        $text = explode(' ', $args[0]);
        $color1 = "\003" . str_pad(trim($args[1]), 2 , 0, STR_PAD_LEFT);
        $color2 = "\003" . str_pad(trim($args[2]), 2 , 0, STR_PAD_LEFT);
        $out = array();
        foreach($text as $w) {
            if($w != '') {
                $out[] = $color1 . $w[0] . $color2 . substr($w, 1);
            }
        }
        return implode(' ', $out) . "\003";
    }

    function addVar($name, $desc, $args, $mod, $func) {
        $this->vars[strtolower($name)] = Array(
            'desc' => $desc,
            'mod' => $mod,
            'func' => $func,
            'args' => $args,
            'store' => ''
        );
        if($args != null) {
            $this->vars[strtolower($name)]['narg'] = count($args);
        }
    }

    function delVarByMod($mod) {
        foreach($this->vars as $k => $v) {
            if($v['mod'] == $mod) {
                unset($this->vars[$k]);
            }
        }
    }

    function loaded($args) {
        $info = $this->pMM->getRegistry($args['name'], 'ParseUtil');
        $mod = $args['name'];
        if($info == null) return;
        echo "ParseUtil loading module $args[name]\n";
        //Handle our section of registry.conf here
        if(array_key_exists('vars', $info) && is_array($info['vars'])) {
            foreach($info['vars'] as $f) {
                $name = array_shift($f);
                $func = array_shift($f);
                $desc = array_shift($f);
                if(count($f) > 0) {
                    $arg = $f;
                } else {
                    $arg = null;
                }
                echo "ParseUtil added $name - $desc to $func of $mod";
                $this->addVar($name, $desc, $arg, $mod, $func);
                unset($arg);
            }
        }
    }

    function reloaded($args) {
        echo "ParseReg: Cleaning up $args[name]\n";
        $this->delVarByMod($args['name']);
        $this->loaded($args);
    }

    function unloaded($args) {
        $this->delVarByMod($args['name']);
    }

    function rehash(&$old) {
        $this->vars = $old->vars;
    }

    public $parses = Array();



    /*
     * non-delay vars the rely on stored info like nick,chan
     * should (try to) keep those things updated with parseutil
     * 
     * When a parse is use that infofor that parse. If/when the
     * parse calls a non-delay var it provides that info to the
     * function.
     */

    function parse($string, $cbFunc, $cbClass, $extra = null) {
        $pkey = md5($string);
        $this->parses[$pkey] = new C_Parse($this, $this->vars, $cbFunc, $cbClass, $string, $extra, $pkey);
        $rval = $this->parses[$pkey]->parse();
        if($this->parses[$pkey]->pause == true) {
            return;
        } else {
            $cbClass->$cbFunc($rval, $this->parses[$pkey]->extra);
            if(array_key_exists($pkey, $this->parses)) {
            	if(is_object($this->parses[$pkey])) {
                	$this->parses[$pkey]->cleanup();
            	}
                unset($this->parses[$pkey]);
            }
            return;
        }
    }

    function logic() {
        foreach($this->parses as $pkey => $p) {
            if(!$p->pause) {
                $rval = $this->parses[$pkey]->parse();
                if($this->parses[$pkey]->pause == true) {
                    continue;
                } else {
                    $cbClass = $this->parses[$pkey]->cbClass;
                    $cbFunc = $this->parses[$pkey]->cbFunc;
                    $cbClass->$cbFunc($rval, $this->parses[$pkey]->extra);
                    if(is_object($this->parses[$pkey])) {
                        $this->parses[$pkey]->cleanup();
                    }
                    unset($this->parses[$pkey]);
                    continue;
                }
            }
        }
    }

    /*
     * old filter below
     *
     * whats left i need to add i guess
     */
    function filter($chan, $nick, $host, $account, $botnick, $channels, $c, $string, $arg = Array()) {


        $p[1] = '$cmds';     $r[1] = $cmds;
        $p[7] = '$trig';       $r[7] = '.';
        $p[8] = '$rnick';	    $r[8] = array_rand($tempy);
/*
        $pos = strpos($string, '$sc(');
        if($pos !== FALSE) {
            $endpos = strpos($string, ')', $pos + 4);
            $p[9] = substr($string, $pos, ($endpos - $pos) + 1); $r[9] = get_shoutcast(substr($string, $pos + 4, strpos($string, ')', $pos + 4) - ($pos + 4)));
        }

        $pos = strpos($string, '$query(');
        if($pos !== FALSE) {
            $endpos = strpos($string, ')', $pos + 7);
            $p[10] = substr($string, $pos, ($endpos - $pos) + 1); $r[10] = get_sinfo(substr($string, $pos + 7, strpos($string, ')', $pos + 7) - ($pos + 7)));
        }

*/
    }
};
?>
