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

        $doc = file_get_html('http://www.urbandictionary.com/define.php?term=' . urlencode(htmlentities($msg)));

        if ($doc === false) {
            goto fuckoff;
        }

        if (strpos($doc->plaintext, "Sorry, we couldn't find: ") !== FALSE &&
            strpos($doc->plaintext, "There are no definitions for this word.") !== FALSE) {
            goto fuckoff;
        }

        $word = @$doc->find('a.word')[0]->plaintext;

        if (!$word) {
            goto fuckoff;
        }

        $by       = $doc->find('div.contributor')[0]->plaintext;
        $meaning  = $doc->find('div.meaning')[0]->plaintext;
        $example  = $doc->find('div.example')[0]->plaintext;
        $meaning = html_entity_decode($meaning, ENT_QUOTES | ENT_HTML5);
        $example = html_entity_decode($example, ENT_QUOTES | ENT_HTML5);
        $word    = html_entity_decode($word, ENT_QUOTES | ENT_HTML5);
        $by      = html_entity_decode($by, ENT_QUOTES | ENT_HTML5);

        $by = preg_replace("/^ by/", "\2By:\2", $by);

        $meaning = str_replace("\n", ' ', $meaning);
        $meaning = trim(str_replace("\r", ' ', $meaning));

        $example = str_replace("\r", "\n", $example);
        $example = explode("\n", $example);
        foreach ($example as $key => &$ex) {
            $ex = trim($ex);
            if ($ex == '') {
                unset($example[$key]);
            }
        }
        $example = implode(' | ', $example);

        $this->pIrc->msg($chan, "\2UrbanDict:\2 $word $by", 1, 1);
        $this->pIrc->msg($chan, "\2Meaning:\2 $meaning", 1, 1);
        $this->pIrc->msg($chan, "\2Example:\2 $example", 1, 1);
        return;

        fuckoff:
        $this->pIrc->msg($chan,"\2UrbanDictionary:\2 Site dead or nothing found :(");
    }

}

?>
