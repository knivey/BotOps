<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
require_once('modules/Module.inc');
require_once('Http.inc');

class smds extends Module {
    function cmd_smds($nick, $chan, $args) {
        $page = rand(1,6);
        $lol = new Http($this->pSockets, $this, 'smdsRead');
        $lol->getQuery("http://api.twitter.com/1/statuses/user_timeline/shitmydadsays.xml?trim_user=true&exclude_replies=true&count=27&page=$page", Array($chan));
    }
    
    function smdsRead($data, $t) {
        $chan= $t[0];
        if(is_array($data)) {
            $this->pIrc->msg($chan, "\2SMDS:\2 Error ($data[0]) $data[1]");
            return;
        }
        $xml = simplexml_load_string($data);
        var_dump($xml);
        $num = rand(0,count($xml->status));
        $rs = $xml->status[$num]->text;
        $this->pIrc->msg($chan, "\2SMDS:\2 $rs");
    }
}
?>