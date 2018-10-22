<?php

require_once('modules/Module.inc');
require_once('Tools/Tools.php');

class youtube extends Module {
    var $URL = '/^((?:https?:)?\/\/)?((?:www|m)\.)?((?:youtube\.com|youtu.be))(\/(?:[\w\-]+\?v=|embed\/|v\/)?)([\w\-]+)(\S+)?$/';
    
    function inmsg($nick, $chan, $text) {
        $chanpref = $this->gM('SetReg')->getCSet('youtube', $chan, 'scan');
        if($chanpref != 'on') {
            return;
        }
        
        if(!preg_match($this->URL, $text, $m)) {
            return;
        }
        
        if(!array_key_exists(5, $m)) {
            return;
        }
       
        $id = $m[5];
        
        echo "Looking up youtube video $id\n";
        
        list($error, $key) = $this->pGetConfig('gkey');
        if ($error) {
            $this->pIrc->msg($chan, "\2YouTube Error:\2 $error");
            return;
        }
        $ch = curl_init("https://www.googleapis.com/youtube/v3/videos?id=$id&part=snippet%2CcontentDetails%2Cstatistics&key=$key");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        $res = curl_exec($ch);
        
        if($res === FALSE) {
            $this->pIrc->msg($chan, "\2YouTube Error:\2 " . curl_errno($ch));
            curl_close($ch);
            return;
        }

        $data = json_decode($res);
        if(!is_object($data)) {
            $this->pIrc->msg($chan, "\2YouTube Error:\2 Unknown data received.");
            curl_close($ch);
            return;
        }
        try {
            var_dump($data);
            $v = $data->items[0];
            $title = $v->snippet->title;

            $start = new DateTime('@0'); // Unix epoch
            $start->add(new DateInterval($v->contentDetails->duration));
            $dur = $start->format('H:i:s');

            $chanTitle = $v->snippet->channelTitle;
            $date = date("M j, Y", strtotime($v->snippet->publishedAt));
            $views = $v->statistics->viewCount;
            $likes = $v->statistics->likeCount;
            $hates = $v->statistics->dislikeCount;

            $lead = "\2YouTube:\2";
            if ($v->contentDetails->definition == 'hd') {
                $lead = "\2YouTubeHD:\2";
            }

            $this->pIrc->msg($chan, "$lead $title \2Channel:\2 $chanTitle \2Length:\2 $dur \2Date:\2 $date \2Views:\2 $views \2+/-:\2 $likes\2/\2$hates");
        } catch (Exception $e) {
            $this->pIrc->msg($chan, "\2YouTube Error:\2 Unknown data received.");
        }
        curl_close($ch);
    }
}


?>
