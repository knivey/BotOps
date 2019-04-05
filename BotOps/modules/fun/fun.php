<?php

require_once('Tools/simple_html_dom.php');
require_once('modules/Module.inc');
require_once('Http.inc');

class fun extends Module {

    public function cmd_search($nick, $chan, $msg) {
        if($msg == '') {
            $this->pIrc->msg($chan, "\2Search:\2 You must provide a query.");
            return;
        }
        $this->cmd_yandex($chan, $msg);
        $this->cmd_ddg($chan, $msg);
    }
    
    public function cmd_ddg($nick, $chan, $msg) {
        $srv = "\2DDG:\2";
        if($msg == '') {
            $this->pIrc->msg($chan, "$srv You must provide a query.");
            return;
        }
        $ch = curl_init("https://duckduckgo.com/html/?q=" . urlencode(htmlentities($msg)));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        $res = curl_exec($ch);

        if($res === FALSE) {
            $this->pIrc->msg($chan, "$srv Error: " . curl_error($ch));
            curl_close($ch);
            return;
        }

        var_dump($res);
        
        try {
            $s = str_get_html($res);
            if (empty($s->find('a[class=result__snippet]'))) {
                throw new Exception('No results.');
            }
            $res = $s->find('a[class=result__snippet]')[0];
            $url = str_replace('/l/?kh=-1&uddg=', '', html_entity_decode(urldecode($res->href))) . "\n";
            $url = str_replace(' ', '%20', $url); //A little hack but what you gonna do
            $blurb = htmlspecialchars_decode(html_entity_decode(strip_tags($res), ENT_QUOTES)) . "\n";
            $url = str_replace("\n", '', $url);
            $blurb = str_replace("\n", '', $blurb);
            $this->pIrc->msg($chan, "$srv $url - $blurb");
        } catch  (Exception $e) {
            $this->pIrc->msg($chan, "$srv Error: Exception Raised: " . $e->getMessage());
        }
        curl_close($ch);
    }
    
    public function cmd_yandex($nick, $chan, $msg) {
        $srv = "\2Yandex:\2";
        if($msg == '') {
            $this->pIrc->msg($chan, "$srv You must provide a query.");
            return;
        }
        list($error, $key) = $this->pGetConfig('yandex_key');
        if ($error) {
            $this->pIrc->msg($chan, "$srv $error");
            return;
        }
        
        $ch = curl_init("https://yandex.com/search/xml?user=knivey&key=$key&query=" . urlencode(htmlentities($msg)) . "&l10n=en&sortby=rlv&filter=none&maxpassages=1&groupby=attr%3D%22%22.mode%3Dflat.groups-on-page%3D10.docs-in-group%3D1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        $res = curl_exec($ch);

        if($res === FALSE) {
            $this->pIrc->msg($chan, "$srv Error: " . curl_error($ch));
            curl_close($ch);
            return;
        }

        var_dump($res);
        $r = simplexml_load_string($res);
        var_dump($r);
        
        try {
            if(property_exists($r->response, 'error')) {
                $this->pIrc->msg($chan, "$srv Error: " . $r->response->error);
                curl_close($ch);
                return;
            }
            $resNum = $r->response->{'found-human'};
            $rr = $r->response->results->grouping->group[0];
            $url = $rr->doc->url;
            $blurb = "N/A";
            if(property_exists($rr->doc, 'headline')) {
                $blurb = strip_tags($rr->doc->headline->asXML());
            } else {
                if(property_exists($rr->doc, 'passages')) {
                    if(is_array($rr->doc->passages->passage)) {
                        $blurb = strip_tags($rr->doc->passages->passage[0]->asXML());
                    } else {
                        $blurb = strip_tags($rr->doc->passages->passage->asXML());
                    }
                }
            }
            $this->pIrc->msg($chan, "$srv ($resNum) $url - $blurb");
        } catch (Exception $e) {
            $this->pIrc->msg($chan, "$srv Error: Exception Raised: " . $e->getMessage());
        }
        curl_close($ch);
    }

    public function cmd_cal($nick, $chan, $msg)
    {
        $cal = trim(`cal --color=always`);
        $cal = str_replace(chr(27) . '[7m', chr(22), $cal);
        $cal = str_replace(chr(27) . '[27m', chr(22), $cal);
        $this->pIrc->msg($chan, $cal, 0, 0);
    }

    public function cmd_tweet($nick, $target, $arg2) {
        list($error, $consumerKey) = $this->pGetConfig('twitter_consumerKey');
        if ($error) {
            $this->pIrc->msg($target, "Problem Tweeting: $error");
            return;
        }

        list($error, $consumerSecret) = $this->pGetConfig('twitter_consumerSecret');
        if ($error) {
            $this->pIrc->msg($target, "Problem Tweeting: $error");
            return;
        }

        list($error, $oAuthToken) = $this->pGetConfig('twitter_oAuthToken');
        if ($error) {
            $this->pIrc->msg($target, "Problem Tweeting: $error");
            return;
        }

        list($error, $oAuthSecret) = $this->pGetConfig('twitter_oAuthSecret');
        if ($error) {
            $this->pIrc->msg($target, "Problem Tweeting: $error");
            return;
        }

        // create a new instance
        $tweet    = new TwitterOAuth($consumerKey, $consumerSecret, $oAuthToken, $oAuthSecret);
        $msg      = "From $nick on IRC - $arg2";
        //send a tweet
        $response = $tweet->post('statuses/update', array('status' => $msg));

        if ($response->truncated == false) {
            $this->pIrc->msg($target, "Tweeted!");
        } else {
            $this->pIrc->msg($target, "Tweet Failed!");
        }
    }

    public function cmd_fml($nick, $target, $arg2) {
        list($error, $key) = $this->pGetConfig('fml_key');
        if ($error) {
            $this->pIrc->msg($target, "Problem FMLing: $error");
            return;
        }

        $lol = new Http($this->pSockets, $this, 'fmlRand');
        $lol->getQuery('http://api.betacie.com/view/random?key=' . $key . '&language=en', Array($target, false));
    }

    public function cmd_fmll($nick, $target, $arg2) {
        list($error, $key) = $this->pGetConfig('fml_key');
        if ($error) {
            $this->pIrc->msg($target, "Problem FMLing: $error");
            return;
        }

        $lol = new Http($this->pSockets, $this, 'fmlRand');
        $lol->getQuery('http://api.betacie.com/view/random?key=' . $key . '&language=en', Array($target, true));
    }

    public function fmlRand($data, $t) {
        list($target, $full) = $t;

        if (is_array($data)) {
            $this->pIrc->msg($target, "\2FML:\2 Error ($data[0]) $data[1]");
            return;
        }

        if (empty($data)) {
            $this->pIrc->msg($target, "\2FML\2: Error...");
            return;
        }

        $xml   = simplexml_load_string($data);
        $item  = $xml->items->item;
        $story = htmlspecialchars_decode($item->text, ENT_QUOTES); // just in case

        $this->pIrc->msg($target, "\2FML\2($item[id]): $story");

        if ($item->author == '') {
            $author = '?';
        } else {
            $author = $item->author;
        }

        if ($item->author['gender'] == 'none') {
            $gender = '?';
        } else {
            $gender = $item->author['gender'];
        }

        if ($item->author['region'] == '') {
            $region = '?';
        } else {
            $region = $item->author['region'];
        }

        if ($full) {
            $this->pIrc->msg($target, "Agree/Deserve: $item->agree/$item->deserved. Category: $item->category From: $author Gender: $gender Loc: $region");
        }
    }

    public function cmd_qball($nick, $target, $arg2) {
        try {
            $stmt = $this->pMysql->query("select * from qball order by rand() limit 1");
            $resp = $stmt->fetch();
            $stmt->closeCursor();
            $this->pIrc->msg($target, "\2$nick:\2 $resp[txt]");
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    public function cmd_qballadd($nick, $target, $arg2) {
        try {
            $host = $this->pIrc->n2h($nick);
            $hand = $this->gM('user')->byHost($host);
            if ($arg2 == '') {
                return $this->BADARGS;
            }
            $stmt = $this->pMysql->prepare("INSERT INTO `qball` (`who`,`txt`) VALUES(:hand,:text)");
            $stmt->execute(Array(':hand' => $hand, ':text' => $arg2));
            $stmt->closeCursor();
            $this->pIrc->notice($nick, "Qball response added.");
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    public function cmd_qballdel($nick, $target, $arg2) {
        if ($arg2 == '') {
            return $this->BADARGS;
        }

        try {
            $stmt = $this->pMysql->prepare("SELECT * FROM `qball` WHERE `id` = :id");
            $stmt->execute(Array(':id' => $arg2));

            if ($stmt->rowCount() == 0) {
                $this->pIrc->notice($nick, "Qball response ID $arg2 does not exist.");
                $stmt->closeCursor();
                return $this->ERROR;
            }

            $stmt->closeCursor();
            $stmt = $this->pMysql->prepare("DELETE FROM `qball` WHERE `id` = :id LIMIT 1");
            $stmt->execute(Array(':id' => $arg2));
            $stmt->closeCursor();
            $this->pIrc->notice($nick, "Qball response deleted.");
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    public function cmd_qballsearch($nick, $target, $arg2) {
        if ($arg2 == '') {
            return $this->BADARGS;
        }

        try {
            $arg2 = str_replace('*', '%', $arg2);
            $stmt = $this->pMysql->prepare("SELECT * FROM `qball` WHERE `txt` LIKE :txt");
            $stmt->execute(Array(':txt' => $arg2));

            if ($stmt->rowCount() == 0) {
                $this->pIrc->notice($nick, "Qball no results for $arg2 found.");
                $stmt->closeCursor();
                return;
            }

            $res = $stmt->fetchAll();
            $ids = Array();
            $stmt->closeCursor();

            foreach ($res as $r) {
                $ids[] = $r['id'];
            }

            $ids = implode(', ', $ids);
            $this->pIrc->notice($nick, "Qball results: $ids.");
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    public function cmd_qballinfo($nick, $target, $arg2) {
        if ($arg2 == '') {
            return $this->BADARGS;
        }

        try {
            $stmt = $this->pMysql->prepare("SELECT * FROM `qball` WHERE `id` = :id");
            $stmt->execute(Array(':id' => $arg2));
            $res  = $stmt->fetch();

            if ($stmt->rowCount() == 0) {
                $this->pIrc->notice($nick, "Qball response ID $arg2 does not exist.");
                $stmt->closeCursor();
                return $this->ERROR;
            }

            $stmt->closeCursor();
            $this->pIrc->notice($nick, "Qball response ID $res[id] added by $res[who] txt: $res[txt]");
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    public function cmd_bash($nick, $target, $arg2) {
        $lol = new Http($this->pSockets, $this, 'bash');
        if ($arg2 == '') {
            $lol->getQuery("http://www.bash.org/?random", $target);
        } else {
            $lol->getQuery("http://www.bash.org/?$arg2", $target);
        }
    }

    public function cmd_mlib($nick, $target, $arg2) {
        $lol = new Http($this->pSockets, $this, 'mlib');
        $lol->getQuery("http://mylifeisbro.com/random", $target);
    }

    public function cmd_txts($nick, $target, $arg2) {
        $lol = new Http($this->pSockets, $this, 'txts');
        $lol->getQuery("http://www.textsfromlastnight.com/Random-Texts-From-Last-Night.html", $target);
    }

    public function txts($data, $target) {
        if (is_array($data)) {
            $this->pIrc->msg($target, "\2Txts:\2 Error ($data[0]) $data[1]");
            return;
        }

        $startText = '<a href="/Text-Replies-';
        $endText   = '</a>';
        $start     = strpos($data, $startText) + strlen($startText);
        $end       = strpos($data, $endText, $start);
        $res       = substr($data, $start, $end - $start);
        $res       = html_entity_decode($res, ENT_QUOTES | ENT_HTML401, 'cp1251');

        $htmlEnts = Array('&#8230;', "\r", "\n");
        $htmlOuts = Array('…', ' ', ' ');

        $res = str_replace($htmlEnts, $htmlOuts, strip_tags($res));
        $res = explode('">', $res);
        $res = $res[1];

        $this->pIrc->msg($target, "\2TEXTS:\2 $res");
    }

    public function mlib($data, $target) {
        if (is_array($data)) {
            $this->pIrc->msg($target, "\2MLIB:\2 Error ($data[0]) $data[1]");
            return;
        }

        $startText = '<p>';
        $endText   = '</p>';
        $start     = strpos($data, $startText) + strlen($startText);
        $end       = strpos($data, $endText, $start);
        $res       = substr($data, $start, $end - $start);
        $res       = html_entity_decode($res, ENT_QUOTES | ENT_HTML401, 'cp1251');

        $htmlEnts = Array('&#8230;', "\r", "\n");
        $htmlOuts = Array('…', ' ', ' ');

        $res = str_replace($htmlEnts, $htmlOuts, strip_tags($res));

        $this->pIrc->msg($target, "\2MLIB:\2 $res");
    }

    public function bash($data, $target) {
        if (is_array($data)) {
            $this->pIrc->msg($target, "\2Bash:\2 Error ($data[0]) $data[1]");
            return;
        }

        $startText = '<p class="quote"><a href="?';
        $endText   = '" title';
        $start     = strpos($data, $startText) + strlen($startText);
        $end       = strpos($data, $endText, $start);
        $num       = substr($data, $start, $end - $start);

        $startText = '<p class="qt">';
        $endText   = '</p>';
        $start     = strpos($data, $startText) + strlen($startText);
        $end       = strpos($data, $endText, $start);
        $quote     = substr($data, $start, $end - $start);
        $quote     = str_replace('<br />', ' | ', $quote);
        $quote     = str_replace("\n", '', $quote);
        $quote     = str_replace("\r", '', $quote);
        $quote     = htmlspecialchars_decode($quote, ENT_QUOTES);

        $this->pIrc->msg($target, "\2Bash(\2$num\2):\2 $quote");
    }

    public function cmd_ping($nick, $chan, $msg) {
        $this->pIrc->msg($chan, "\2$nick\2: Pong!");
    }

    public function cmd_spell($nick, $chan, $msg) {
        $tag   = 'en_US';
        $r     = enchant_broker_init();
        $suggs = '';

        if (enchant_broker_dict_exists($r, $tag)) {
            $d = enchant_broker_request_dict($r, $tag);
            enchant_dict_quick_check($d, $msg, $suggs);
        }

        if ($suggs == null) {
            $this->pIrc->msg($chan, "No spelling suggestions for '$msg'");
        } else {
            $suggs = implode(', ', $suggs);
            $this->pIrc->msg($chan, "Spelling suggestions for '$msg': $suggs");
        }
    }

    public function cmd_time($nick, $chan, $msg) {
        $this->pIrc->msg($chan, "For the time look at a clock.");
    }

    public function cmd_gasinfo($nick, $chan, $arg2) {
        $arg  = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $aout = Array();
        $rnum = 3;
        $via  = 'mq';

        while (!empty($arg)) {
            $a = array_shift($arg);

            if (strtolower($a) == '-num') {
                $rnum = array_shift($arg);

                if (!is_numeric($rnum) || $rnum > 10) {
                    $this->pIrc->notice($nick, "Usage: -num must not be more then 10");
                    return $this->ERROR;
                }
            } elseif (strtolower($a) == '-gb') {
                $via = 'gb';
            } else {
                $aout[] = $a;
            }
        }

        $arg2 = trim(implode(' ', $aout));

        if ($arg2 == '') {
            $arg2 = $this->gM('user')->getzip($hand);
        }

        if ($arg2 == '') {
            $this->pIrc->notice($nick, "Usage: gasinfo <street zipcode> [-num results]");
            $this->pIrc->notice($nick, "\2NOTE\2: you can use just a zipcode but a streetname narrows the search");
            return $this->ERROR;
        }

        $varz = Array(
            'chan'  => $chan,
            'query' => $arg2,
            'rnum'  => $rnum,
        );

        $locHttp = new Http($this->pSockets, $this, 'glocRead', $varz);

        $locHttp->getQuery("http://maps.googleapis.com/maps/api/geocode/xml?address=" . urlencode(htmlentities($arg2)) . "&sensor=false", $varz);
    }

    function glocRead($data, $varz) {
        if (is_array($data)) {
            $this->pIrc->msg($varz['chan'], "\2Geolocation:\2 Error ($data[0]) $data[1]");
            return;
        }

        $w            = simplexml_load_string($data);
        $varz['long'] = (float) $w->result->geometry->location->lng;
        $varz['lat']  = (float) $w->result->geometry->location->lat;
        $varz['name'] = $w->result->formatted_address;

        $us = strpos($data, "<short_name>US</short_name>");

        if ($us === FALSE) {
            $this->pIrc->msg($varz['chan'], "\2Gasinfo:\2 Information only available for the US.");
            return;
        }

        //add a check for location error
        $locHttp = new Http($this->pSockets, $this, 'gasRead', $varz);
        $locHttp->getQuery('http://gasdata.web.mapquest.com/ajax/?ST=RE&R=5&CLL=' . $varz['lat'] . ',' . $varz['long'], $varz);
    }

    public function gasRead($data, $varz) {
        if (is_array($data)) {
            $this->pIrc->msg($varz['chan'], "\2Gasinfo:\2 Error ($data[0]) $data[1]");
            return;
        }

        $chan  = $varz['chan'];
        $rnum  = $varz['rnum'];
        $xml   = simplexml_load_string($data);
        $count = $xml->totalCount;

        if ($count > $rnum) {
            $count = $rnum;
        }

        $count = $count - 1;

        for ($index = 0; $index <= $count; $index++) {
            $st    = $xml->stationCollection->station[$index];
            $name  = str_replace('(discount Available)', '', ucwords(strtolower($st->name)));
            $addy  = ucwords(strtolower($st->address));
            $brand = $st->brand;
            $gps   = $st->priceCollection->gasPrice;
            $reg   = "\rReg:\2 ?.??";
            $prem  = '';

            foreach ($gps as $gp) {
                $price = str_pad(round((float) trim($gp->price, '$'), 2), 4, '0', STR_PAD_RIGHT);
                if ($price == '') {
                    $price = $gp->price;
                }
                if ($gp->typeid == 3) {
                    $reg = "\2Reg:\2 $price";
                }
                if ($gp->typeid == 5) {
                    $prem = "\2Prem:\2 $price";
                }
            }

            $prices       = trim("$reg $prem");
            $line[$index] = "\2(" . ($index + 1) . '/' . ($count + 1) . "):\2 $prices @ $name, $addy ";
        }

        $this->pIrc->msg($chan, "\2Lowest " . ($count + 1) . " gas prices within 5 miles of $varz[name]:\2");

        foreach ($line as $l) {
            $this->pIrc->msg($chan, $l);
        }
    }

    public function cmd_reddit($nick, $chan, $arg2) {
        $lol = new Http($this->pSockets, $this, 'redditRead');
        $lol->getQuery("http://xml.reddit.com/r/all/", $chan);
    }

    public function redditRead($data, $chan) {
        if (is_array($data)) {
            $this->pIrc->msg($chan, "\2Reddit:\2 Error ($data[0]) $data[1]");
            return;
        }

        $xml   = simplexml_load_string($data);
        $items = $xml->channel->item;

        for ($i = 0; $i < 5; $i++) {
            $t = $items[$i]->title;
            $l = $items[$i]->link;
            $this->pIrc->msg($chan, "\2Reddit:\2 $t - $l");
        }
    }

}

?>
