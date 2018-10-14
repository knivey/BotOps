<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
require_once('modules/Module.inc');
require_once('Http.inc');

class quotes extends Module {

    function cmd_addquote($nick, $chan, $text) {
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        if ($text == '') {
            return $this->BADARGS;
        }
        try {
            //check if that text has already been added for the channel
            $stmt = $this->pMysql->prepare("SELECT `id` FROM `quotes` WHERE `quote` = :quote AND `chan` = :chan");
            $stmt->execute(Array(':quote'=>$text,':chan'=>$chan));
            if ($stmt->rowCount() > 0) {
                $this->pIrc->notice($nick, "That quote has already been added.");
                return $this->ERROR;
            }
            $stmt->closeCursor();
            //insert it!
            $stmt = $this->pMysql->prepare("INSERT INTO `quotes` (`quote`,`date`,`chan`,`account`,`state`,`host`)".
                    " VALUES(:quote,:date,:chan,:account,'active',:host)");
            $stmt->execute(Array(
                ':quote'=>$text,
                ':date'=>time(),
                ':chan'=>$chan,
                ':account'=>$hand,
                ':host'=>$host,
                    ));
            $id = $this->pMysql->lastInsertId();
            $stmt->closeCursor();
            $this->pIrc->notice($nick, "Quote $id has been added!");
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            return $this->ERROR;
        }
    }

    function cmd_delquote($nick, $chan, $text) {
        if ($text == '') {
            $this->pIrc->msg($chan, "Please give me a quote number.");
            return;
        }
        try {
            $stmt = $this->pMysql->prepare("SELECT * FROM `quotes` WHERE `state` = 'active' AND `id` = :id");
            $stmt->execute(Array(':id'=>$text));
            $resp = $stmt->fetch(); 
            $stmt->closeCursor();
            if ($resp != false && array_key_exists('quote', $resp) && $resp['quote'] != '') {
                if (strtolower($chan) != strtolower($resp['chan']) &&
                        !$this->gM('user')->hasOverride($this->gM('user')->byNick($nick))) {
                    $this->pIrc->notice($nick, "You do not have access to remove other channels quotes.");
                    return $this->ERROR;
                }
                $stmt = $this->pMysql->prepare("UPDATE `quotes` SET `state` = 'deleted' WHERE `state` = 'active' AND `id` = :id");
                $stmt->execute(Array(':id'=>$text));
                $stmt->closeCursor();
                $this->pIrc->msg($chan, "\2Quote[$resp[id]] deleted.");
            } else {
                $this->pIrc->msg($chan, "Quote not found or already deleted");
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            return $this->ERROR;
        }
    }

    function cmd_undelquote($nick, $chan, $text) {
        if ($text == '') {
            $this->pIrc->msg($chan, "Please give me a quote number.");
            return;
        }
        try {
            $stmt = $this->pMysql->prepare("SELECT * FROM `quotes` WHERE `state` = 'deleted' AND `id` = :id");
            $stmt->execute(Array(':id'=>$text));
            $resp = $stmt->fetch();
            $stmt->closeCursor();
            if ($resp != false && array_key_exists('quote', $resp) && $resp['quote'] != '' && (strtolower($resp['chan']) == strtolower($chan) || $this->gM('user')->hasOveride($this->gM('user')->byNick($nick)))) {
                $stmt = $this->pMysql->prepare("UPDATE `quotes` SET `state` = 'active' WHERE `state` = 'deleted' AND `id` = :id");
                $stmt->execute(Array(':id'=>$text));
                $stmt->closeCursor();
                $this->pIrc->msg($chan, "\2Quote[$resp[id]] restored.");
            } else {
                $this->pIrc->msg($chan, "Quote not found or isn't deleted or is from another channel");
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            return $this->ERROR;
        }
    }

    function cmd_quotestats($nick, $chan, $text) {
        try {
            $stmt = $this->pMysql->query('select count(distinct chan) from quotes');
            $chans = $stmt->fetch();
            $chans = $chans['count(distinct chan)'];
            $stmt->closeCursor();
            $stmt = $this->pMysql->query('select count(distinct account) from quotes');
            $users = $stmt->fetch();
            $users = $users['count(distinct account)'];
            $stmt->closeCursor();
            $stmt = $this->pMysql->query("select count(*) from quotes where state = 'active'");
            $num = $stmt->fetch();
            $num = $num['count(*)'];
            $stmt->closeCursor();
            $stmt = $this->pMysql->query("select count(*) from quotes where state != 'active'");
            $numdel = $stmt->fetch();
            $numdel = $numdel['count(*)'];
            $stmt->closeCursor();
            $stmt = $this->pMysql->prepare("select count(*) from quotes where state = 'active' and chan = :chan");
            $stmt->execute(Array(':chan'=>$chan));
            $numchan = $stmt->fetch();
            $numchan = $numchan['count(*)'];
            $stmt->closeCursor();
            $this->pIrc->msg($chan, "\2QuoteStats:\2 I have $num active quotes from $chans channels and $users users, $numchan quotes are from $chan, $numdel quotes are deleted");
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            return $this->ERROR;
        }
    }

    function cmd_quote($nick, $chan, $text) {
        $origin = $this->gM('SetReg')->getCSet('quotes', $chan, 'origin');
        try {
            $param = Array();
            if ($origin == 'chan') {
                $qapp = ' AND chan = :chan';
                $param = Array(':chan'=>$chan);
            } else {
                $qapp = '';
            }
            if ($text == '') {
                $stmt = $this->pMysql->prepare("select * from quotes where state = 'active' $qapp order by rand() limit 1");
            } else {
                $param[':id'] = $text;
                $stmt = $this->pMysql->prepare("select * from quotes where state = 'active' $qapp and id = :id");
            }
            $stmt->execute($param);
            $resp = $stmt->fetch();
            $stmt->closeCursor();
            if ($resp != false && array_key_exists('quote', $resp) && $resp['quote'] != '') {
                $this->pIrc->msg($chan, "\2Quote[$resp[id]]\2: $resp[quote]");
            } else {
                if ($text == '') {
                    $this->pIrc->msg($chan, "No quotes have been added. Get started with addquote.");
                } else {
                    $this->pIrc->msg($chan, "Quote not found");
                }
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            return $this->ERROR;
        }
    }

    function cmd_quoteinfo($nick, $chan, $text) {
        if ($text == '') {
            $this->pIrc->notice($nick, "Please give me a quote number.");
            return;
        }
        try {
            $stmt = $this->pMysql->prepare("select * from quotes where id = :id");
            $stmt->execute(Array(':id'=>$text));
            $resp = $stmt->fetch();
            $stmt->closeCursor();
            if ($resp != false && array_key_exists('quote', $resp) && $resp['quote'] != '') {
                $this->pIrc->notice($nick, "\2QuoteInfo[$resp[id]] By:\2 $resp[account] \2State:\2 $resp[state] \2Added:\2 " . strftime('%D', $resp['date']) . " \2Chan:\2 $resp[chan] \2Quote:\2 $resp[quote]");
            } else {
                $this->pIrc->notice($nick, "Quote not found");
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            return $this->ERROR;
        }
    }

}

?>
