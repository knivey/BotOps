<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once('modules/Module.inc');
require_once('Http.inc');

class urbandict extends Module {
    function cmd_ud($nick, $chan, $msg) {
        if($msg == '') {
            return $this->BADARGS;
        }
        $lol = new Http($this->pSockets, $this, 'ud');
        $lol->getQuery('http://www.urbandictionary.com/define.php?term=' . urlencode(htmlentities($msg)), Array($nick, $chan));
    }
    
    function ud($data, $x) {
        list($nick, $chan) = $x;
        if(is_array($data)) {
            $this->pIrc->msg($chan, "\2UrbanDorcktinairy:\2 Error ($data[0]) $data[1]");
            return;
        }
        
        $startText = "<td class='word'>";
        $endText = '</td>';
        $start = strpos($data, $startText) + strlen($startText);
        $end = strpos($data, $endText, $start);
        $word = str_replace("\r", '', substr($data, $start, $end - $start));
        
        $startText = '<div class="definition">';
        $endText = '</div>';
        $start = strpos($data, $startText) + strlen($startText);
        $end = strpos($data, $endText, $start);
        $definition = str_replace("\r", ' ', substr($data, $start, $end - $start));
        
        $startText = '<div class="example">';
        $endText = '</div>';
        $start = strpos($data, $startText) + strlen($startText);
        $end = strpos($data, $endText, $start);
        $example = str_replace("\r", ' ', substr($data, $start, $end - $start));
        
        $definition = strip_tags(html_entity_decode(str_replace("<br/>", " ", $definition), ENT_QUOTES));
        $example = strip_tags(html_entity_decode(str_replace("<br/>", " | ", $example), ENT_QUOTES));
        $word = strip_tags(html_entity_decode(str_replace("<br/>", " ", $word)));
        
        $word = str_replace("\n", '', $word);
        $definition = str_replace("\n", '', $definition);
        $example = str_replace("\n", '', $example);
        
        if(strpos($data, "</i> isn't defined <a href=\"/add.php?") !== FALSE) {
            $this->pIrc->msg($chan, "\2UrbanDict:\2 Your query hasn't been defined yet.", 1, 1);
            return;
        } 
        
        $this->pIrc->msg($chan, "\2UrbanDict:\2 $word", 1, 1);
        $this->pIrc->msg($chan, "\2Definition:\2 $definition", 1, 1);
        $this->pIrc->msg($chan, "\2Example:\2 $example", 1, 1);
    }
}
?>
