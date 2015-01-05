<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once('Tools/simple_html_dom.php');
require_once('modules/Module.inc');
require_once('Http.inc');

class urbandict extends Module
{

    function cmd_ud($nick, $chan, $msg)
    {
        if ($msg == '') {
            return $this->BADARGS;
        }

        $lol = new Http($this->pSockets, $this, 'ud');
        $lol->getQuery('http://www.urbandictionary.com/define.php?term=' . urlencode(htmlentities($msg)), $chan);
    }

    function ud($data, $chan)
    {
        if (is_array($data)) {
            $this->pIrc->msg($chan, "\2UrbanDictionary:\2 Error ($data[0]) $data[1]");
            return;
        }

        if (strpos($data, "</i> isn't defined <a href=\"/add.php?") !== FALSE) {
            $this->pIrc->msg($chan, "\2UrbanDict:\2 Your query hasn't been defined yet.", 1, 1);
            return;
        }

        $doc = str_get_html($data);

        $word    = $doc->find('a.word')[0]->plaintext;
        $meaning = $doc->find('div.meaning')[0]->plaintext;
        $example = $doc->find('div.example')[0]->plaintext;

        $meaning = html_entity_decode($meaning, ENT_QUOTES);
        $example = html_entity_decode($example, ENT_QUOTES);
        $word    = html_entity_decode($word, ENT_QUOTES);

        $meaning = str_replace("\n", ' ', $meaning);
        $example = str_replace("\n", ' | ', $example);

        $this->pIrc->msg($chan, "\2UrbanDict:\2 $word", 1, 1);
        $this->pIrc->msg($chan, "\2Meaning:\2 $meaning", 1, 1);
        $this->pIrc->msg($chan, "\2Example:\2 $example", 1, 1);
    }

}

?>
