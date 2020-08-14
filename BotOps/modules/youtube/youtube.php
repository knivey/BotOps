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

        foreach(explode(' ', $text) as $word) {
            if(!preg_match($this->URL, $word, $m)) {
                continue;
            }

            if(!array_key_exists(5, $m)) {
                continue;
            }

            $id = $m[5];
            // Get this with https://www.youtube.com/watch?time_continue=165&v=Bfdy5a_R4K4
            if($id == "watch") {
                $url = parse_url($word, PHP_URL_QUERY);
                foreach(explode('&', $url) as $p) {
                    list($lhs, $rhs) = explode('=', $p);
                    if ($lhs == 'v') {
                        $id = $rhs;
                    }
                }
            }

            echo "Looking up youtube video $id\n";

            list($error, $key) = $this->pGetConfig('gkey');
            if ($error) {
                $this->pIrc->msg($chan, "\2YouTube Error:\2 $error");
                continue;
            }
            $ch = curl_init("https://www.googleapis.com/youtube/v3/videos?id=$id&part=snippet%2CcontentDetails%2Cstatistics&key=$key");
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            $res = curl_exec($ch);

            if($res === FALSE) {
                $this->pIrc->msg($chan, "\2YouTube Error:\2 " . curl_error($ch));
                curl_close($ch);
                continue;
            }

            $data = json_decode($res);
            if(!is_object($data)) {
                $this->pIrc->msg($chan, "\2YouTube Error:\2 Unknown data received.");
                curl_close($ch);
                continue;
            }
            try {
                var_dump($data);
                $v = $data->items[0];
                $title = $v->snippet->title;

                $di = new DateInterval($v->contentDetails->duration);
                $dur = '';
                if($di->s > 0) {
                    $dur = "{$di->s}s";
                }
                if ($di->i > 0) {
                    $dur = "{$di->i}m $dur";
                }
                if ($di->h > 0) {
                    $dur = "{$di->h}h $dur";
                }
                if ($di->d > 0) {
                    $dur = "{$di->d}d $dur";
                }
                //Seems unlikely, months and years
                if ($di->m > 0) {
                    $dur = "{$di->m}M $dur";
                }
                if ($di->y > 0) {
                    $dur = "{$di->y}y $dur";
                }
                $dur = trim($dur);
                if($dur != '') {
                    $dur = 'LIVE';
                }

                $chanTitle = $v->snippet->channelTitle;
                $datef = $this->gM('SetReg')->getCSet('youtube', $chan, 'date');
                $date = date($datef, strtotime($v->snippet->publishedAt));
                $views = number_format($v->statistics->viewCount);
                $likes = number_format($v->statistics->likeCount);
                $hates = number_format($v->statistics->dislikeCount);

                $lead = "YouTube";
                if ($v->contentDetails->definition == 'hd') {
                    $lead = "YouTubeHD";
                }
                $patterns = Array('$yt' => $lead,
                                  '$title' => $title,
                                  '$channel' => $chanTitle,
                                  '$length' => $dur,
                                  '$date' => $date,
                                  '$views' => $views,
                                  '$likes' => $likes,
                                  '$hates' => $hates);
                $theme = $this->gM('SetReg')->getCSet('youtube', $chan, 'theme');
                foreach($patterns as $find => $replace) {
                    $theme = str_replace($find, $replace, $theme);
                }

                $this->pIrc->msg($chan, $theme, 0, 1);
            } catch (Exception $e) {
                //$this->pIrc->msg($chan, "\2YouTube Error:\2 Unknown data received.");
                echo "YouTube Error: Unknown data received.";
            }
            curl_close($ch);
        }
    }
}


?>
