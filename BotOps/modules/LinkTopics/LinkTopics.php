<?php

require_once('Tools/simple_html_dom.php');
require_once('modules/Module.inc');
require_once('Tools/Tools.php');

class LinkTopics extends Module {
    var $URL = '/\b(https?):\/\/[\-A-Za-z0-9+&@#\/%?=~_|!:,.;]*[\-A-Za-z0-9+&@#\/%=~_|]/';
    var $Ignore = Array('www.youtube.com', 'youtube.com', 'youtu.be');

    function isIgnored($url) {
        foreach($this->Ignore as $i) {
            if(strcasecmp($url, $i) == 0) {
                return true;
            }
        }
        return false;
    }
    
    function inmsg($nick, $chan, $text) {
        $chanpref = $this->gM('SetReg')->getCSet('linktopics', $chan, 'scan');
        if($chanpref != 'on') {
            return;
        }

        foreach(explode(' ', $text) as $word) {
            if(!preg_match($this->URL, $word)) {
                continue;
            }

            echo "Looking up HTTP link $word\n";

            try {
                $host = parse_url($word, PHP_URL_HOST);
                if($this->isIgnored($host)) {
                    continue;
                }

                $ch = curl_init($word);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
                curl_setopt($ch, CURLOPT_ENCODING, "gzip");
                $res = curl_exec($ch);
                if($res === FALSE) {
                    throw new Exception(curl_error($ch));
                }
                $start = stripos($res, "<title>");
                if(!$start) {
                    throw new Exception('No Title.');
                }
                $end = stripos($res, "</title>", $start);
                if($start > $end) {
                    throw new Exception('End tag before start.');
                }
                $title = substr($res, $start, $end - $start);
                $title = strip_tags($title);
                $title = html_entity_decode($title,  ENT_QUOTES | ENT_XML1, 'UTF-8');
                $title = htmlspecialchars_decode($title);
                $this->pIrc->msg($chan, "[ $title ] - $host");
            } catch (Exception $e) {
                echo "HTTP Links Error: " . $e->getMessage() . "\n";
                //var_dump($res);
                //$this->pIrc->msg($chan, "\2HTTP Links Error:\2 " . $e->getMessage());
            }
            curl_close($ch);
        }
    }
}


?>
