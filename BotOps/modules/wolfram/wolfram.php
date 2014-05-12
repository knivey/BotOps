<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


require_once('modules/Module.inc');
require_once('Http.inc');

class wolfram extends Module {
	
    public $apiurl = 'http://api.wolframalpha.com/v2/query?input=';
    
    public function cmd_calc($nick, $target, $arg2) {
    	list($error, $key) = $this->pGetConfig('key');
    	if($error) {
    		$this->pIrc->msg($target, "Problem Wolframing: $error");
    		return;
    	}
    	
        $lol = new Http($this->pSockets, $this, 'waCalc', null, 15);
        $lol->getQuery($this->apiurl . urlencode(htmlentities($arg2)) . $key . '&format=plaintext', $target);
    }
    
    public function waCalc($data, $target) {
        if(is_array($data) && !is_array($target)) {
            $this->pIrc->msg($target, "\2WolframAlpha:\2 Error ($data[0]) $data[1]");
            return;
        }
        if(is_array($data) && is_array($target)) {
            $c = $target['cbClass'];
            $f = $target['cbFunc'];
            $c->$f("\2WolframAlpha:\2 Error ($data[0]) $data[1]");
            return;
        }
        $xml = simplexml_load_string($data);
        //var_dump($xml);
        //ok lets /try/ to format a decent output...
        //i think the first two "pods" are what you really want
        $res = '';
        $resa = null;
        $resb = null;
        
        //first check if there was an error
        if($xml['success'] == 'false') {
            $res = $xml->tips->tip[0]['text'];
        } else {
            //the xml has things called pods so lets cycle through em
            //i decided to cycle here in case i want to look at more then 2 in future
            $count = 0;
            foreach($xml->pod as $pod) {
                //I'm pretty sure our input pod will always be called Input
                //Or will be the first pod
                if($count == 0) {
                    //input
                    $resa = $pod->subpod->plaintext;
                }
                if($count == 1) {
                    $resb = str_replace("\n", "\2;\2 ", $pod->subpod->plaintext);
                }
                $count++;
            }
            $res = "$resa = $resb";
        }
        $parsetime = $xml['parsetiming'];
        $outtatime = $xml['parsetimedout'];
        //we didn't have tips? try didyoumean
        if($res == '') {
            $res = 'Error: does not compute. Did you mean: ' . $xml->didyoumeans->didyoumean[0];
        }
        
        if($outtatime != 'false') {
            $res = "Error: not enough time to parse query.";
        }
        
        //I beleive this is for $var compatibility
        if (!is_array($target)) {
            $this->pIrc->msg($target, "\2WolframAlpha:\2 $res \2(ParseTime:\2 $parsetime\2)");
        } else {
            $c = $target['cbClass'];
            $f = $target['cbFunc'];
            if ($resb != null) {
                $c->$f(trim($resb));
            } else {
                $c->$f($res);
            }
        }
    }

    
    public function v_calc($args) {
    	list($error, $key) = $this->pGetConfig('key');
    	if($error) {
    		return $error;
    	}
    	
        $lol = new Http($this->pSockets, $this, 'waCalc');
        $lol->getQuery($this->apiurl . urlencode(htmlentities($args[0])) . $key . '&format=plaintext', $args);
        return Array('pause' => 'pause');
    }
}
?>
