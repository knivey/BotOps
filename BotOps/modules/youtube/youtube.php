<?php

require_once('modules/Module.inc');
require_once('Tools/Tools.php');

class youtube extends Module {
    function inmsg($nick, $target, $text) {
	return;
        $chanpref = $this->gM('SetReg')->getCSet('youtube', $target, 'scan');
        if($chanpref != 'on') {
            return;
        }
        $words = explode(' ', $text);
        foreach($words as $w) {
            $loc = strpos($w, 'http://youtube.com');
            if ($loc !== FALSE) {
                //i guess uhh... just load the page
                $lol = new Http($this->pSockets, $this, 'yt');
                $lol->getQuery($w, Array($nick, $target, false));
            }
            
            $loc = strpos($w, 'http://www.youtube.com');
            if ($loc !== FALSE) {
                //i guess uhh... just load the page
                $lol = new Http($this->pSockets, $this, 'yt');
                $lol->getQuery($w, Array($nick, $target, false));
            }
            
            $loc = strpos($w, 'http://youtu.be');
            if ($loc !== FALSE) {
                //i guess uhh... just load the page
                $lol = new Http($this->pSockets, $this, 'yt');
                $lol->getQuery($w, Array($nick, $target, false));
            }
            
            $loc = strpos($w, 'https://youtube.com');
            if ($loc !== FALSE) {
                //i guess uhh... just load the page
                $lol = new Http($this->pSockets, $this, 'yt');
                $lol->getQuery($w, Array($nick, $target, false));
            }
            
            $loc = strpos($w, 'https://www.youtube.com');
            if ($loc !== FALSE) {
                //i guess uhh... just load the page
                $lol = new Http($this->pSockets, $this, 'yt');
                $lol->getQuery($w, Array($nick, $target, false));
            }
            
            $loc = strpos($w, 'https://youtu.be');
            if ($loc !== FALSE) {
                //i guess uhh... just load the page
                $lol = new Http($this->pSockets, $this, 'yt');
                $lol->getQuery($w, Array($nick, $target, false));
            }
            
            $loc = strpos($w, 'http://tiny.cc');
            if ($loc !== FALSE) {
                //i guess uhh... just load the page
                $lol = new Http($this->pSockets, $this, 'yt');
                $lol->getQuery($w, Array($nick, $target, true));
            }
            
            $loc = strpos($w, 'http://tinyurl.com');
            if ($loc !== FALSE) {
                //i guess uhh... just load the page
                $lol = new Http($this->pSockets, $this, 'yt');
                $lol->getQuery($w, Array($nick, $target, true));
            }
        }
    }
    
    function yt($data, $x) {
        list($nick, $chan, $tiny) = $x;
        if(is_array($data)) {
            if (!$tiny) {
                $this->pIrc->msg($chan, "\2YouTube:\2 Error ($data[0]) $data[1]");
            }
            return;
        }
        $isyoutube = strpos($data, '<meta property="og:site_name" content="YouTube">');
        if($isyoutube === FALSE) {
            return;
        }
        
        $startText = '<meta itemprop="name" content="';
        $endText = '">';
        $start = strpos($data, $startText) + strlen($startText);
        $end = strpos($data, $endText, $start);
        $title = substr($data, $start, $end - $start);
        
        $startText = '<meta itemprop="duration" content="';
        $endText = '">';
        $start = strpos($data, $startText) + strlen($startText);
        $end = strpos($data, $endText, $start);
        $duration = substr($data, $start, $end - $start);
        $duration = strtolower(substr($duration, 2));
        
        $title = strip_tags(html_entity_decode($title));
        $title = htmlspecialchars_decode($title, ENT_QUOTES);
        $duration = strip_tags(html_entity_decode($duration));
        
        if(strlen($duration) > 15) {
            return;
        }
        
        $this->pIrc->msg($chan, "\2[YouTube] Title:\2 $title \2Length:\2 $duration");
    }
}


?>
