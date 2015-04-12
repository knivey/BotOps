<?php

class clanbot extends Module
{

    public $bindTypes = Array(
        'default',
        'notice',
        'chan',
        'act',
        'chanserv',
    );

    function cmd_bindalias($nick, $chan, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
    }

    function cmd_hidebind($nick, $chan, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
        if ($argc < 1) {
            return $this->BADARGS;
        }
        $this->setBindHidden($chan, $argv[0], true);
        $this->pIrc->notice($nick,
                            $argv[0] . ' is now hidden from $binds and $tbinds',
                            1, 1);
    }

    function cmd_unhidebind($nick, $chan, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
        if ($argc < 1) {
            return $this->BADARGS;
        }
        $this->setBindHidden($chan, $argv[0], false);
        $this->pIrc->notice($nick,
                            $argv[0] . ' is no longer hidden in $binds and $tbinds',
                            1, 1);
    }

    function setBindHidden($chan, $bind, $val)
    {
        $bindInfo           = $this->getBind($chan, $bind);
        $bindInfo['hidden'] = (bool) $val;
        $this->setBind($chan, $bind, $bindInfo);
    }

    function cmd_bindmakeprivate($nick, $chan, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
    }

    function cmd_bindmakepublic($nick, $chan, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
    }

    /**
     * checks if our settings exist on the channel
     * if not then create them
     */
    function checkInit($chan)
    {
        $sets = $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
        if ($sets != null) {
            return;
        }
        $sets = Array();
        $this->gM('channel')->chgSet($chan, 'clanbot', 'binds', $sets);
    }

    function getBind($chan, $bind)
    {
        $chan = strtolower($chan);
        $bind = strtolower($bind);
        $this->checkInit($chan);
        $sets = $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
        if (!is_array($sets) || !array_key_exists($bind, $sets)) {
            return null;
        } else {
            return $sets[$bind];
        }
    }

    function getAllBinds($chan)
    {
        $this->checkInit($chan);
        return $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
    }

    function setBind($chan, $bind, $data)
    {
        $chan        = strtolower($chan);
        $bind        = strtolower($bind);
        $this->checkInit($chan);
        $sets        = $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
        $sets[$bind] = $data;
        $this->gM('channel')->chgSet($chan, 'clanbot', 'binds', $sets);
    }

    function delBind($chan, $bind)
    {
        $bind = strtolower($bind);
        $this->checkInit($chan);
        $sets = $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
        unset($sets[$bind]);
        $this->gM('channel')->chgSet($chan, 'clanbot', 'binds', $sets);
    }

    function cmd_bind($nick, $chan, $arg2)
    {
        $arg      = explode(' ', $arg2); // Need to preserve spacing of content
        $host     = $this->pIrc->n2h($nick);
        $hand     = $this->gM('user')->byHost($host);
        $access   = $this->gM('user')->access($hand, $chan);
        $override = $this->gM('user')->hasOverride($hand);

        if (count($arg) < 2) {
            return $this->BADARGS;
        }
        $bind    = array_shift($arg);
        $value   = implode(' ', $arg);
        //check if the bind exists
        $newBind = $this->getBind($chan, $bind);
        $rv      = $this->OK;
        if ($newBind == null) {
            $newBind = Array(
                'value' => $value,
                'name'  => $bind,
                'by'    => $hand,
                'date'  => time(),
                'type'  => 'default',
                'count' => 0
            );
            $reply   = "Bind $bind has been added";
        } else {
            $newBind['name']  = $bind;
            $newBind['value'] = $value;
            $newBind['by']    = $hand;
            $newBind['date']  = time();
            $reply            = "Bind $bind has been updated";
            if ($newBind['type'] == 'chanserv' && $access < 5) {
                if ($override) {
                    $rv = $this->OK | $this->OVERRIDE;
                } else {
                    $this->pIrc->notice($nick,
                                        "You need level 5 access to modify chanserv bindtypes.");
                    return $this->ERROR;
                }
            }
        }
        $this->setBind($chan, $bind, $newBind);
        $this->pIrc->notice($nick, $reply);
        return $rv;
    }

    public $bindvalue = '';

    function v_bindvalue()
    {
        $bv = $this->bindvalue;
        unset($this->bindvalue);
        return $bv;
    }

    function cmdOut($message, $extras)
    {
        list($cmd, $nick, $chan, $bindInfo, $type) = $extras;
        switch ($type) {
            default:
            case 'notice':
                $this->pIrc->notice($nick, $message);
                break;
            case 'chan':
                $this->pIrc->msg($chan, $message);
                break;
        }
        $bindInfo['count'] ++;
        $this->setBind($chan, $cmd, $bindInfo);
    }

    function cmdCatch($cmd, $nick, $chan, $arg2)
    {
        $bindInfo = $this->getBind($chan, $cmd);
        if ($bindInfo == null) {
            return false;
        }
        $type            = strtolower($bindInfo['type']);
        $this->bindvalue = $bindInfo['value'];
        $theme           = $this->gM('SetReg')->getCSet('clanbot', $chan,
                                                        'theme');
        if ($type == 'default') {
            $type = $this->gM('SetReg')->getCSet('clanbot', $chan, 'bindtype');
        }
        $this->gM('CmdReg')->lastCmdInfo['cmd'] = $bindInfo['name'];
        if ($type == 'act') {
            $this->pIrc->act($chan, $bindInfo['value']);
            return true;
        }
        if ($type == 'chanserv') {
            $this->pIrc->chanserv($chan, $bindInfo['value']);
            return true;
        }
        $extras = Array($cmd, $nick, $chan, $bindInfo, $type);
        $this->gM('ParseUtil')->parse($theme, 'cmdOut', $this, $extras);
        return true;
    }

    function cmd_unbind($nick, $target, $arg2)
    {
        $arg  = explode(' ', $arg2);
        $chan = strtolower($target);
        if ($arg2 == '') {
            return $this->BADARGS;
        }
        $bindInfo = $this->getBind($chan, $arg[0]);
        if ($bindInfo == null) {
            $this->pIrc->notice($nick, "Bind $arg[0] does not exist");
            return $this->ERROR;
        }
        $this->delBind($chan, $arg[0]);
        $this->pIrc->notice($nick, "Bind $arg[0] has been removed");
    }

    function cmd_bindtype($nick, $chan, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
        $hand     = $this->gM('user')->byNick($nick);
        $access   = $this->gM('user')->access($hand, $chan);
        $override = $this->gM('user')->hasOverride($hand);
        if ($argc < 1) {
            return $this->BADARGS;
        }

        $bind = $argv[0];

        $bindInfo = $this->getBind($chan, $bind);
        if ($bindInfo == null) {
            $this->pIrc->notice($nick, "Bind $bind does not exist");
            return $this->ERROR;
        }

        if ($argc < 2) {
            $this->pIrc->notice($nick, "Bindtype for $bind is $bindInfo[type]");
            return $this->OK;
        }

        $type = strtolower($argv[1]);

        if (!in_array($type, $this->bindTypes)) {
            $types = implode(',', $this->bindTypes);
            $this->pIrc->notice($nick,
                                "Bind please choose a correct type: $types");
            return $this->ERROR;
        }

        $rv = $this->OK;
        if ($type == 'chanserv' && $access < 5) {
            if ($override) {
                $rv = $this->OK | $this->OVERRIDE;
            } else {
                $this->pIrc->notice($nick,
                                    "You need level 5 access to modify chanserv bindtypes.");
                return $this->ERROR;
            }
        }

        $bindInfo['type'] = $type;
        $bindInfo['by']   = $hand;
        $bindInfo['date'] = time();
        $this->setBind($chan, $bind, $bindInfo);

        $this->pIrc->notice($nick, "Bindtype for $bind is now set to $type");
        return $rv;
    }

    function v_binds()
    {
        $chan  = $this->gM('ParseUtil')->getV('chan');
        $binds = $this->getAllBinds($chan);
        foreach ($binds as $key => $val) {
            if (array_key_exists('hidden', $val) && $val['hidden'] == true) {
                unset($binds[$key]);
            }
        }
        $list  = implode(', ', array_keys($binds));
        return $list;
    }

    function v_tbinds()
    {
        $chan  = $this->gM('ParseUtil')->getV('chan');
        $binds = $this->getAllBinds($chan);
        foreach ($binds as $key => $val) {
            if (array_key_exists('hidden', $val) && $val['hidden'] == true) {
                unset($binds[$key]);
            }
        }
        $trig = $this->gM('channel')->getTrig($chan);
        $list = '';
        foreach (array_keys($binds) as $b) {
            $list .= $trig . $b . ' ';
        }
        return trim($list);
    }

    function cmd_binds($nick, $chan, $arg2)
    {
        $binds = $this->getAllBinds($chan);
        $list  = implode(', ', array_keys($binds));
        $this->pIrc->notice($nick, "Binds: $list");
    }

    function cmd_bindinfo($nick, $chan, $arg2)
    {
        $arg = explode(' ', $arg2);
        if ($arg2 == '') {
            return $this->BADARGS;
        }
        $bindInfo = $this->getBind($chan, $arg[0]);
        if ($bindInfo == null) {
            $this->pIrc->notice($nick, "Bind $arg[0] does not exist");
            return $this->ERROR;
        }
        $this->pIrc->notice($nick,
                            "Bind $bindInfo[name] of type $bindInfo[type]" .
            " last changed " . strftime('%D', $bindInfo['date']) .
            " by $bindInfo[by] and has been used $bindInfo[count] times" .
            " Value: $bindInfo[value]", 1, 1);
    }

}

?>
