<?php

require_once('modules/Module.inc');
require_once('Http.inc');

class quotes extends Module
{

    function cmd_addquote(CmdRequest $r) {
        //check if that text has already been added for the channel
        $stmt = $this->pMysql->prepare("SELECT `id` FROM `quotes` WHERE `quote` = :quote AND `chan` = :chan");
        $stmt->execute(Array(':quote' => $r->args['quote'], ':chan' => $r->chan));
        if ($stmt->rowCount() > 0) {
            throw new CmdException("That quote has already been added.");
        }
        $stmt->closeCursor();
        //insert it!
        $stmt = $this->pMysql->prepare("INSERT INTO `quotes` (`quote`,`date`,`chan`,`account`,`state`,`host`)" .
            " VALUES(:quote,:date,:chan,:account,'active',:host)");
        $stmt->execute(Array(
            ':quote'   => $r->args['quote'],
            ':date'    => time(),
            ':chan'    => $r->chan,
            ':account' => $r->account,
            ':host'    => $r->host,
        ));
        $id = $this->pMysql->lastInsertId();
        $stmt->closeCursor();
        $r->notice("Quote $id has been added!");
    }

    function cmd_delquote(CmdRequest $r) {
        $stmt = $this->pMysql->prepare("SELECT * FROM `quotes` WHERE `state` = 'active' AND `id` = :id");
        $stmt->execute(Array(':id' => $r->args['num']));
        $resp = $stmt->fetch();
        $stmt->closeCursor();
        if ($resp != false && array_key_exists('quote', $resp) && $resp['quote'] != '') {
            if (strtolower($r->chan) != strtolower($resp['chan']) && !$r->hasoverride) {
                throw new CmdException("You do not have access to remove other channels quotes.");
            }
            $stmt = $this->pMysql->prepare("UPDATE `quotes` SET `state` = 'deleted' WHERE `state` = 'active' AND `id` = :id");
            $stmt->execute(Array(':id' => $r->args['num']));
            $stmt->closeCursor();
            $r->reply("\2Quote[{$r->args['num']}]\2 deleted.");
        } else {
            throw new CmdException("Quote not found or already deleted");
        }
    }

    function cmd_undelquote(CmdRequest $r) {
        $stmt = $this->pMysql->prepare("SELECT * FROM `quotes` WHERE `state` = 'deleted' AND `id` = :id");
        $stmt->execute(Array(':id' => $r->args['num']));
        $resp = $stmt->fetch();
        $stmt->closeCursor();
        if ($resp != false && array_key_exists('quote', $resp) && $resp['quote'] != '' && (strtolower($resp['chan']) == strtolower($r->chan) || $r->hasoverride)) {
            $stmt = $this->pMysql->prepare("UPDATE `quotes` SET `state` = 'active' WHERE `state` = 'deleted' AND `id` = :id");
            $stmt->execute(Array(':id' => $r->args['num']));
            $stmt->closeCursor();
            $r->reply("\2Quote[{$r->args['num']}]\2 restored.");
        } else {
            throw new CmdException("Quote not found or isn't deleted or is from another channel");
        }
    }

    function cmd_quotestats(CmdRequest $r) {
        $stmt  = $this->pMysql->query('select count(distinct chan) from quotes');
        $chans = $stmt->fetch();
        $chans = $chans['count(distinct chan)'];
        $stmt->closeCursor();
        $stmt  = $this->pMysql->query('select count(distinct account) from quotes');
        $users = $stmt->fetch();
        $users = $users['count(distinct account)'];
        $stmt->closeCursor();
        $stmt = $this->pMysql->query("select count(*) from quotes where state = 'active'");
        $num  = $stmt->fetch();
        $num  = $num['count(*)'];
        $stmt->closeCursor();
        $stmt   = $this->pMysql->query("select count(*) from quotes where state != 'active'");
        $numdel = $stmt->fetch();
        $numdel = $numdel['count(*)'];
        $stmt->closeCursor();
        $stmt = $this->pMysql->prepare("select count(*) from quotes where state = 'active' and chan = :chan");
        $stmt->execute(Array(':chan' => $r->chan));
        $numchan = $stmt->fetch();
        $numchan = $numchan['count(*)'];
        $stmt->closeCursor();
        $r->reply("\2QuoteStats:\2 I have $num active quotes from $chans channels and $users users, $numchan quotes are from $r->chan, $numdel quotes are deleted");
    }

    function cmd_quote(CmdRequest $r) {
        $origin = $this->gM('SetReg')->getCSet('quotes', $r->chan, 'origin');
        $param  = Array();
        if ($origin != 'all') {
            $chans = explode(' ', $origin);
            $chans = array_filter($chans);
            $ps = [];
            foreach ($chans as $k => $c) {
                $ps[] = "chan = :chan$k";
                $param[":chan$k"] = $c;
            }
            $qapp = ' AND (' . implode(' OR ', $ps) . ')';
        } else {
            $qapp = '';
        }
        if (!isset($r->args['num'])) {
            $stmt = $this->pMysql->prepare("select * from quotes where state = 'active' $qapp order by rand() limit 1");
        } else {
            $param[':id'] = $r->args['num'];
            $stmt         = $this->pMysql->prepare("select * from quotes where state = 'active' $qapp and id = :id");
        }
        $stmt->execute($param);
        $resp = $stmt->fetch();
        $stmt->closeCursor();
        if ($resp != false && array_key_exists('quote', $resp) && $resp['quote'] != '') {
            $r->reply("\2Quote[$resp[id]]\2: $resp[quote]");
        } else {
            if (!isset($r->args['num'])) {
                throw new CmdException("No quotes found. Get started with addquote, or make sure quotes origin is set correctly.");
            } else {
                throw new CmdException("Quote not found");
            }
        }
    }

    function cmd_quoteinfo(CmdRequest $r) {
        $stmt = $this->pMysql->prepare("select * from quotes where id = :id");
        $stmt->execute(Array(':id' => $r->args['num']));
        $resp = $stmt->fetch();
        $stmt->closeCursor();
        if ($resp != false && array_key_exists('quote', $resp) && $resp['quote'] != '') {
            $r->reply("\2QuoteInfo[$resp[id]] By:\2 $resp[account] \2State:\2 $resp[state] \2Added:\2 " . strftime('%D', $resp['date']) . " \2Chan:\2 $resp[chan] \2Quote:\2 $resp[quote]");
        } else {
            throw new CmdException("Quote not found");
        }
    }

}

?>
