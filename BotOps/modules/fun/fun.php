<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';
require_once('Tools/simple_html_dom.php');
require_once('modules/Module.inc');
require_once('Http.inc');

class fun extends Module {
    function curl(string $url): string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        $res = curl_exec($ch);

        if($res === FALSE) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception($err);
        }
        return $res;
    }

    public function cmd_ddg(CmdRequest $r)
    {
        $srv = "\2DDG:\2";
        try {
            $res = $this->curl("https://duckduckgo.com/html/?q=" . urlencode(htmlentities($r->args['query'])));
        } catch (Exception $e) {
            throw (new CmdException($e->getMessage()))->asReply();
        }

        $s = str_get_html($res);
        if (empty($s->find('a[class=result__snippet]'))) {
            throw (new CmdException('No results.'))->asReply();
        }
        $res = $s->find('a[class=result__snippet]')[0];
        $url = str_replace('/l/?kh=-1&uddg=', '', html_entity_decode(urldecode($res->href))) . "\n";
        $url = str_replace(' ', '%20', $url); //A little hack but what you gonna do
        $blurb = htmlspecialchars_decode(html_entity_decode(strip_tags($res), ENT_QUOTES)) . "\n";
        $url = str_replace("\n", '', $url);
        $blurb = str_replace("\n", '', $blurb);
        $r->reply("$srv $url - $blurb", 0, 1);
    }
    
    public function cmd_yandex(CmdRequest $r) {
        $srv = "\2Yandex:\2";
        list($error, $key) = $this->pGetConfig('yandex_key');
        if ($error) {
            throw new CmdException($error);
        }
        $url = "https://yandex.com/search/xml?user=knivey&key=$key&query=" . urlencode(htmlentities($r->args['query'])) .
            "&l10n=en&sortby=rlv&filter=none&maxpassages=1&groupby=attr%3D%22%22.mode%3Dflat.groups-on-page%3D10.docs-in-group%3D1";
        try {
            $body = $this->curl($url);
        } catch (Exception $e) {
            throw (new CmdException($e->getMessage()))->asReply();
        }

        $res = simplexml_load_string($body);

        if(property_exists($res->response, 'error')) {
            throw (new CmdException($res->response->error))->asReply();
        }
        $resNum = $res->response->{'found-human'};
        $rr = $res->response->results->grouping->group[0];
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
        $r->reply("$srv ($resNum) $url - $blurb", 0, 1);
    }

    public function cmd_cal(CmdRequest $r)
    {
        $cal = trim(`cal --color=always`);
        $cal = str_replace(chr(27) . '[7m', chr(22), $cal);
        $cal = str_replace(chr(27) . '[27m', chr(22), $cal);
        $r->reply($cal, 1, 1);
    }

    //TODO this was for BotOps staff to tweet to our twitter page, but it would be nice to make a module where channels or users can use it to tweet
    public function cmd_tweet(CmdRequest $r) {
        list($error, $consumerKey) = $this->pGetConfig('twitter_consumerKey');
        if ($error) {
            throw new CmdException("Couldn't load key: $error");
        }

        list($error, $consumerSecret) = $this->pGetConfig('twitter_consumerSecret');
        if ($error) {
            throw new CmdException("Couldn't load key: $error");
        }

        list($error, $oAuthToken) = $this->pGetConfig('twitter_oAuthToken');
        if ($error) {
            throw new CmdException("Couldn't load key: $error");
        }

        list($error, $oAuthSecret) = $this->pGetConfig('twitter_oAuthSecret');
        if ($error) {
            throw new CmdException("Couldn't load key: $error");
        }

        // create a new instance
        $tweet    = new TwitterOAuth($consumerKey, $consumerSecret, $oAuthToken, $oAuthSecret);
        $msg      = $r->args[0];
        //send a tweet
        $response = $tweet->post('statuses/update', array('status' => $msg));

        if ($response->truncated == false) {
            $r->reply("Tweeted!");
        } else {
            $r->reply("Tweet Failed!");
        }
    }

    //TODO update these to webscrape or something, the API is long dead
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

    public function cmd_qball(CmdRequest $r) {
            $stmt = $this->pMysql->query("select * from qball order by rand() limit 1");
            $resp = $stmt->fetch();
            $stmt->closeCursor();
            $r->reply("\2$r->nick:\2 $resp[txt]");
    }

    public function cmd_qballadd(CmdRequest $r) {
        $stmt = $this->pMysql->prepare("INSERT INTO `qball` (`who`,`txt`) VALUES(:hand,:text)");
        $stmt->execute(Array(':hand' => $r->account, ':text' => $r->args[0]));
        $stmt->closeCursor();
        $r->notice("Qball response added.");
    }

    public function cmd_qballdel(CmdRequest $r) {
        $stmt = $this->pMysql->prepare("SELECT * FROM `qball` WHERE `id` = :id");
        $stmt->execute(Array(':id' => $r->args['id']));

        if ($stmt->rowCount() == 0) {
            $stmt->closeCursor();
            throw new CmdException("Qball response ID {$r->args['id']} does not exist.");
        }
        $stmt->closeCursor();
        $stmt = $this->pMysql->prepare("DELETE FROM `qball` WHERE `id` = :id LIMIT 1");
        $stmt->execute(Array(':id' => $r->args['id']));
        $stmt->closeCursor();
        $r->notice("Qball response ID {$r->args['id']} deleted.");
    }

    public function cmd_qballsearch(CmdRequest $r) {
        $search = $r->args['search'];
        $search = str_replace('*', '%', $search);
        $stmt = $this->pMysql->prepare("SELECT * FROM `qball` WHERE `txt` LIKE :txt");
        $stmt->execute(Array(':txt' => $search));

        if ($stmt->rowCount() == 0) {
            $r->notice("Qball no results for $search found.");
            $stmt->closeCursor();
            return;
        }

        $res = $stmt->fetchAll();
        $ids = Array();
        $stmt->closeCursor();

        foreach ($res as $re) {
            $ids[] = $re['id'];
        }

        $ids = implode(', ', $ids);
        $r->notice("Qball results: $ids.");
    }

    public function cmd_qballinfo(CmdRequest $r) {
        $stmt = $this->pMysql->prepare("SELECT * FROM `qball` WHERE `id` = :id");
        $stmt->execute(Array(':id' => $r->args['id']));
        $res = $stmt->fetch();

        if ($stmt->rowCount() == 0) {
            $stmt->closeCursor();
            throw new CmdException("Qball response ID {$r->args['id']} does not exist.");
        }

        $stmt->closeCursor();
        $r->notice("Qball response ID $res[id] added by $res[who] txt: $res[txt]");
    }

    var $bashdb = Array();

    public function cmd_bash(CmdRequest $r)
    {
        try {
            $this->populateBash();
        } catch (Exception $e) {
            throw (new CmdException($e->getMessage()))->asReply();
        }
        $id = array_rand($this->bashdb);
        $quote = $this->bashdb[$id];
        unset($this->bashdb[$id]);
        $r->reply("\2Bash(\2$id\2):\2 $quote");
    }

    public function populateBash() {
        if (!empty($this->bashdb)) {
            return;
        }
        $res = $this->curl("http://www.bash.org/?random");

        $html = str_get_html($res);
        $ids = Array();
        $quotes = Array();

        foreach($html->find("p.quote") as $i) {
            $ids[] = $i->find("a", 0)->plaintext;
        }

        foreach($html->find("p.qt") as $i) {
            $quote = $i->innertext;
            $quote = str_replace('<br />', ' |', $quote);
            $quote = str_replace("\n", '', $quote);
            $quote = str_replace("\r", '', $quote);
            $quote = htmlspecialchars_decode($quote, ENT_QUOTES | ENT_HTML5);
            $quotes[] = $quote;
        }
        $db = array_combine($ids, $quotes);
        foreach ($db as $k => $v) {
            if (strlen($v) > 800) {
                unset($db[$k]);
            }
        }
        if (count($db) == 0) {
            throw new Exception("Couldn't extract any quotes from site.");
        }
        $this->bashdb = $db;
    }

    public function cmd_txts(CmdRequest $r) {
        $lol = new Http($this->pSockets, $this, 'txts');
        $lol->getQuery("http://www.textsfromlastnight.com/Random-Texts-From-Last-Night.html", $r);
    }

    public function txts($data, CmdRequest $r) {
        if (is_array($data)) {
            throw (new CmdException("($data[0]) $data[1]"))->asReply();
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

        $r->reply("\2TEXTS:\2 $res");
    }

    public function cmd_ping(CmdRequest $r) {
        $r->reply("\2$r->nick\2: Pong!");
    }

    public function cmd_spell(CmdRequest $r) {
        $word = $r->args['word'];
        $tag   = 'en_US';
        $r     = enchant_broker_init();
        $suggs = '';

        if (enchant_broker_dict_exists($r, $tag)) {
            $d = enchant_broker_request_dict($r, $tag);
            enchant_dict_quick_check($d, $word, $suggs);
        }

        if ($suggs == null) {
            $r->reply("No spelling suggestions for '$word'");
        } else {
            $suggs = implode(', ', $suggs);
            $r->reply("Spelling suggestions for '$word': $suggs");
        }
    }

    public function cmd_time(CmdRequest $r) {
        $time = new DateTime();
        $r->reply("Current server time: " . $time->format('r'));
    }

}

?>
