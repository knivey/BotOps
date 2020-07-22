<?php
class lastfm extends Module {
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
            throw new CmdException($err);
        }
        return $res;
    }

    public function cmd_lastfm(CmdRequest $r) {
        list($error, $key) = $this->pGetConfig('key');
        if ($error) {
            throw new CmdException($error);
        }
        $user = urlencode(htmlentities($r->args['user']));
        $url = "http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=$user&api_key=$key&format=json&limit=1";
        $res = $this->curl($url);
        $res = json_decode($res, true);
        if(!isset($res['recenttracks']['track'][0])) {
            throw new CmdException("Failed to find any recent tracks.");
        }
        //Fix case :)
        var_dump($res);
        $user = $res['recenttracks']['@attr']['user'] ?? $user;
        $track = $res['recenttracks']['track'][0];
        $title = $track['name'] ?? 'No Title';
        $artist = $track['artist']['#text'] ?? 'Unknown Artist';
        $album = $track['album']['#text'] ?? 'Unknown Album';
        $time = '';
        if(isset($track['date']['uts'])) {
            $ago = time() - $track['date']['uts'];
            $time = ' ' .Duration_toString($ago) . " ago";
        }
        $r->reply("\2last.fm:\2 $user last scrobbled$time: $title - $album - $artist");
    }
}