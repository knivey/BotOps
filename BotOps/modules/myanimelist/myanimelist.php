<?php

require_once('modules/Module.inc');
require_once('Tools/Tools.php');


class myanimelist extends Module {
    
    function cmd_mal($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        if($argc == 0) {
            return $this->BADARGS;
        }
        $query = urlencode($msg);
        $url = "http://mal-api.com/anime/search?q=$query&format=xml";
        $x = Array($nick, $chan, $msg);
        $lol = new Http($this->pSockets, $this, 'malRead', $x);
        $lol->getQuery($url, $x);
    }
    
    function malRead($data, $x) {
        list($nick, $chan, $msg) = $x;
        if(is_array($data)) {
            $this->pIrc->msg($chan, "\2MyAnimeList:\2 Error ($data[0]) $data[1]");
            return;
        }
        $xml = simplexml_load_string($data);
        $cnt = 0;
        foreach($xml->anime as $a) {
            $cnt++;
            if($cnt == 3) {
                break;
            }
            $title = $a->title;
            $desc = str_replace("\n", '|', $a->synopsis);
            $type = $a->type;
            $eps = $a->episodes;
            $start = $a->start_date;
            $end = $a->end_date;
            $score = $a->members_score;
            $class = $a->classification;
            $this->pIrc->msg($chan, "\2($cnt) Title:\2 $title ($start - $end) "
                    ."\2Type:\2 $type ($class) \2Eps:\2 $eps \2Score:\2 $score \2Synopsis:\2 $desc");
        }
    }
    
    
}

?>
