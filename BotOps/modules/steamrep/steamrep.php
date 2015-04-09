<?php

require_once('Tools/simple_html_dom.php');
require_once('modules/Module.inc');
require_once('Http.inc');

class steamrep extends Module
{

    public function cmd_steamrep($nick, $chan, $query)
    {
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
            $this->pIrc->msg($chan,
                             "\2:SteamRep:\2 Sorry, the specified ID " .
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

}

?>