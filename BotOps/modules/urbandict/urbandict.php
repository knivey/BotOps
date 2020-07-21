<?php

require_once __DIR__ . '/../CmdReg/CmdRequest.php';

require_once('Tools/simple_html_dom.php');
require_once('modules/Module.inc');
require_once('Http.inc');

class urbandict extends Module
{

    function cmd_ud(CmdRequest $r)
    {
        $doc = file_get_html('http://www.urbandictionary.com/define.php?term=' . urlencode(htmlentities($r->args['term'])));

        if ($doc === false) {
            goto fail;
        }

        if (strpos($doc->plaintext, "Sorry, we couldn't find: ") !== FALSE &&
            strpos($doc->plaintext, "There are no definitions for this word.") !== FALSE) {
            goto fail;
        }

        $word = @$doc->find('a.word')[0]->plaintext;

        if (!$word) {
            goto fail;
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

        $r->reply("\2UrbanDict:\2 $word $by", 0, 1);
        $r->reply("\2Meaning:\2 $meaning", 0, 1);
        $r->reply("\2Example:\2 $example", 0, 1);
        return;

        fail:
        $r->reply("\2UrbanDictionary:\2 Site dead or nothing found :(");
    }

}

?>
