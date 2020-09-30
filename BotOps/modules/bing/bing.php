<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';
require_once('modules/Module.inc');

class bing extends Module
{
    function curl(string $url, $headers = []): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        if(!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $res = curl_exec($ch);

        if ($res === FALSE) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception($err);
        }
        return $res;
    }

    public function cmd_bing(CmdRequest $r)
    {
        list($error, $bingEP) = $this->pGetConfig('bingEP');
        if ($error) {
            throw new CmdException($error);
        }
        list($error, $bingKey) = $this->pGetConfig('bingKey');
        if ($error) {
            throw new CmdException($error);
        }
        $query = urlencode(htmlentities($r->args['query']));
        $url = $bingEP . "search?q=$query&mkt=en-US&setLang=en-US";
        try {
            $res = $this->curl($url, ["Ocp-Apim-Subscription-Key: $bingKey"]);
        } catch (Exception $e) {
            throw (new CmdException($e->getMessage()))->asReply();
        }

        $j = json_decode($res, true);

        if (!array_key_exists('webPages', $j)) {
            $r->reply("\2Bing:\2 No Results");
            return;
        }
        $results = $j['webPages']['totalEstimatedMatches'];
        $res = $j['webPages']['value'][0];
        $r->reply("\2Bing (\2$results Results\2):\2 $res[url] ($res[name]) -- $res[snippet]", 0, 1);
    }
}