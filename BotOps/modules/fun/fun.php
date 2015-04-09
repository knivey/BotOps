<?php

require_once('Tools/simple_html_dom.php');
require_once('modules/Module.inc');
require_once('Http.inc');

class fun extends Module {

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

    public function cmd_define($nick, $target, $arg2) {
        $lol   = new Http($this->pSockets, $this, 'googleDefine');
        $query = urlencode(htmlentities($arg2));
        $lol->getQuery('http://www.google.com/dictionary/json?callback=a&q=' . $query . '&sl=en&tl=en&restrict=pr,de&client=te', $target);
    }

    public function googleDefine($data, $target) {
        if (is_array($data)) {
            $this->pIrc->msg($target, "\2Google Define:\2 Error ($data[0]) $data[1]");
            return;
        }

        $data = substr($data, 2, -10);
        //gets rid of hex codes? probably try a html decode later
        // json_decode seems to convert these BEFORE it parses the string
        // which is a problem when you have \x22 as it is "
        $data = preg_replace_callback("/\\\x[0-9a-f]{2}/", create_function(
                '$matches', '
                        if($matches[0] == \'\x22\') return \'\"\';
                        return chr(hexdec($matches[0]));'
            ), $data);

        $json    = json_decode($data);
        $entries = Array();

        if (!empty($json->primaries)) {
            $src     = 'primary';
            $entries = $json->primaries[0]->entries;
        } else {
            if (!empty($json->webDefinitions)) {
                $src     = 'web';
                $entries = $json->webDefinitions[0]->entries;
            }
        }

        $def = Array();

        foreach ($entries as $ent) {
            if ($ent->type == 'meaning') {
                $def[] = $ent->terms[0]->text;
            }
        }

        $cnt = 1;

        if (count($def) < 2) {
            $this->pIrc->msg($target, "\2Google Define:\2 Found " . count($def) . " $src entries for " . $json->query);
        } else {
            $this->pIrc->msg($target, "\2Google Define:\2 Found " . count($def) . " $src entries for " . $json->query . ', only showing 2');
        }

        foreach ($def as $d) {
            $d = str_replace('<em>', "\2", $d);
            $d = str_replace('</em>', "\2", $d);
            $d = htmlspecialchars_decode($d);
            $d = preg_replace_callback("/(&#(?P<num>[0-9]+);)/", function($m) {
                    return chr(intval($m['num']));
                }, $d);

            $this->pIrc->msg($target, "\2Google Define($cnt):\2 $d");

            if ($cnt == 2) {
                break;
            }

            $cnt++;
        }
    }

    public function cmd_google($nick, $target, $arg2) {
        $lol = new Http($this->pSockets, $this, 'googleSearch');
        $lol->getQuery("http://ajax.googleapis.com/ajax/services/search/web?v=1.0&q=" . urlencode(htmlentities($arg2)), $target);
    }

    public function cmd_gcalc($nick, $target, $arg2) {
        $lol = new Http($this->pSockets, $this, 'googleCalc');
        $lol->getQuery("http://www.google.com/ig/calculator?hl-en&q=" . urlencode(htmlentities($arg2)), $target);
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

    public function v_gcalc($args) {
        $lol = new Http($this->pSockets, $this, 'googleCalc');
        $lol->getQuery("http://www.google.com/search?q=" . urlencode(htmlentities($args[0])), $args);
        return Array('pause' => 'pause');
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

    public function googleCalc($data, $target) {
        if (is_array($data) && !is_array($target)) {
            $this->pIrc->msg($target, "\2Google Calc:\2 Error ($data[0]) $data[1]");
            return;
        }

        if (is_array($data) && is_array($target)) {
            $c = $target['cbClass'];
            $f = $target['cbFunc'];
            $c->$f("\Google Define:\2 Error ($data[0]) $data[1]");
            return;
        }

        $data = str_replace('rhs:', '"rhs":', $data);
        $data = str_replace('lhs:', '"lhs":', $data);
        $data = str_replace('error:', '"error":', $data);
        $data = str_replace('icc:', '"icc":', $data);
        $data = json_decode($data, true);

        if ($data == NULL) {
            $res = NULL;
        } else {
            if ($data['error'] != '') {
                $res = "Invalid Calculation!";
            } else {
                $res = "$data[lhs] = $data[rhs]";
            }
        }

        //I beleive this is for $var compatibility
        if (!is_array($target)) {
            $this->pIrc->msg($target, "\2GCalc:\2 $res");
            return;
        }

        $c = $target['cbClass'];
        $f = $target['cbFunc'];

        //we only want answers damnit
        if ($res != NULL) {
            $resb = explode('=', $res);
        } else {
            $resb = 'CalcError';
        }

        if (array_key_exists(1, $resb)) {
            $c->$f(trim($resb[1]));
        } else {
            $c->$f($res);
        }
    }

    public function googleSearch($data, $target) {
        if (is_array($data)) {
            $this->pIrc->msg($target, "\2Google:\2 Error ($data[0]) $data[1]");
            return;
        }

        $crap = json_decode($data);

        if (count($crap->responseData->results) == 0) {
            $this->pIrc->msg($target, "\2Google Result:\2 No documents found.");
            return;
        }

        $url   = urldecode($crap->responseData->results[0]->url);
        $url   = str_replace(' ', '%20', $url);
        $title = htmlspecialchars_decode($crap->responseData->results[0]->titleNoFormatting, ENT_QUOTES);
        $desc  = htmlspecialchars_decode(strip_tags($crap->responseData->results[0]->content), ENT_QUOTES);
        $desc  = str_replace("\n", '', $desc);
        $num   = $crap->responseData->cursor->estimatedResultCount;

        $this->pIrc->msg($target, "\2Google Results ($num total):\2  $url \2Title:\2 $title \2Content:\2 $desc");
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

    public function cmd_steamrep($nick, $chan, $query) {
        if (empty($query)) {
            return $this->BADARGS;
        }
        
        $q            = urlencode(htmlentities($query));
        $steamrepHttp = new Http($this->pSockets, $this, 'steamrepRead');
        $steamrepHttp->getQuery("http://steamrep.com/search?q=$q", $chan);
    }

    public function steamrepRead($data, $chan)
    {
        if (is_array($data)) {
            $this->pIrc->msg($chan, "\2SteamRep:\2 Error ($data[0]) $data[1]");
            return;
        }

        $doc = str_get_html($data);

        $name = @$doc->getElementById('steamname')->plaintext;
        if ($name == NULL) {
            $this->pIrc->msg($chan, "\2:SteamRep:\2 Sorry, the specified ID " .
                "was not found");
            return;
        }
        $name = trim($name);

        $membersince  = trim($doc->getElementById('membersince')->plaintext);
        $privacystate = trim($doc->getElementById('privacystate')->plaintext);
        $tradeban     = trim($doc->getElementById('tradebanstatus')->plaintext);
        $vacbanned    = trim($doc->getElementById('vacbanned')->plaintext);
        $sids         = trim($doc->getElementById('steamids')->plaintext);
        $sids         = explode("|", $sids);
        $steamids     = Array();

        foreach ($sids as $sid) {
            $ss = explode(': ', trim($sid));
            if (array_key_exists(1, $ss)) {
                $steamids[$ss[0]] = $ss[1];
            }
        }

        $out = "\2:SteamRep:\2 $name \2Joined:\2 $membersince \2VAC:\2 " .
            "$vacbanned \2TradeBan:\2 $tradeban \2Privacy State:\2 " .
            "$privacystate \2SteamID32:\2 $steamids[steamID32]";


        $bannedfriends = @$doc->getElementById('scammerfriendsline')->plaintext;
        if ($bannedfriends != NULL) {
            $bannedfriends = str_replace('Banned Friends:', '', $bannedfriends);
            $bannedfriends = trim($bannedfriends);
            $out .= " \2Banned Friends:\2 $bannedfriends";
        }

        $out = str_replace("\n", ' ', str_replace("\r", ' ', $out));
        $out = html_entity_decode($out);
        $this->pIrc->msg($chan, $out);
    }

    public function cmd_steamid_kurizu($nick, $target, $query) {
        $this->pIrc->msg($target, "SteamID search is down");
        return;

        $s = NULL;

        if (empty($query)) { // No Arguments - Return
            return $this->gM('CmdReg')->rV['BADARGS'];
        }

        //$pos2 = strpos(':', $query);
        if (preg_match('#:#i', $query)) {
            $s = explode(':', $query, 3); // Pre-explode SteamID
        }

        if ($s[0] == NULL || $s[1] == NULL || $s[2] == NULL) {
            $q = mb_convert_encoding($query, 'HTML-ENTITIES', "UTF-8");

            $steamidHttp = new Http($this->pSockets, $this, 'steamidRead');
            $steamidHttp->getQuery("http://steamid.esportsea.com/index.php?action=search&type=single&key=alias&query=" . $q . "&output=xml&version=extended", $target);
        } elseif ($s[0] != NULL && is_numeric($s[0]) || $s[1] != NULL && is_numeric($s[1]) || $s[2] != NULL && is_numeric($s[2])) { // Numbers only and must have a value
            $q1 = mb_convert_encoding($s[0], 'HTML-ENTITIES', "UTF-8");
            $q2 = mb_convert_encoding($s[1], 'HTML-ENTITIES', "UTF-8");
            $q3 = mb_convert_encoding($s[2], 'HTML-ENTITIES', "UTF-8");

            $steamidHttp = new Http($this->pSockets, $this, 'steamidRead');
            $steamidHttp->getQuery('http://steamid.esportsea.com/index.php?action=search&type=single&key=steam_id&query=' . $q1 . '%3A' . $q2 . '%3A' . $q3 . '&output=xml&version=extended', $target);
        }
    }

    public function steamidRead($data, $chan) {
        if (is_array($data)) {
            $this->pIrc->msg($chan, "\2SteamID:\2 Error ($data[0]) $data[1]");
            return;
        }

        var_dump($data);

        $xml = simplexml_load_string($data);

        if ($xml->query_info->total_results != 0) {
            $this->pIrc->msg($chan, "\2SteamID:\2 " . $xml->result->player_steam_id . " \2Name:\2 " . $xml->result->player_name_first . " \"" . $xml->result->player_alias . "\" " . $xml->result->player_name_last . " \2Team:\2 " . $xml->result->team_name . " \2(\2" . $xml->result->team_record->wins . "-" . $xml->result->team_record->losses . "-" . $xml->result->team_record->ties . "\2)\2 \2Game:\2 " . strtoupper($xml->result->team_game) . " \2League:\2 " . strtoupper($xml->result->team_league) . "-" . $xml->result->team_division);
            $this->pIrc->msg($chan, "\2" . $xml->query_info->total_results . "\2 result(s) found only showing 1 in" . $xml->query_info->query_time . " seconds | SteamID provided by ESEA");
        } else {
            $this->pIrc->msg($chan, "Sorry, no information available for that SteamID.");
        }
    }

}

?>
