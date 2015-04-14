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
        $hand = $this->gM('user')->byNick($nick);
        list($argc, $argv) = niceArgs($msg);
        if ($argc < 2) {
            return $this->BADARGS;
        }
        $alias    = $argv[0];
        $bind     = $argv[1];
        $bindInfo = $this->getBind($chan, $bind);
        if (!$bindInfo) {
            $this->pIrc->notice($nick, "No bind named $bind found.", 0, 1);
            return $this->ERROR;
        }
        if (array_key_exists('alias', $bindInfo) && $bindInfo['alias']) {
            $this->pIrc->notice($nick,
                                "$bind is itself an alias, aliasing to $bindInfo[value] instead.",
                                0, 1);
            $bindInfo = $this->getBind($chan, $bindInfo['value']);
            if (!$bindInfo) {
                $this->pIrc->notice($nick,
                                    "An unexpected error occurred creating the alias.");
                $this->pIrc->msg('#botstaff',
                                 "An unexpected error occurred creating an alias in $chan.");
                return $this->ERROR;
            }
        }
        if ($this->getBind($chan, $alias) != null) {
            $this->pIrc->notice($nick,
                                "There is already a bind named $alias in $chan, unbind it first.",
                                0, 1);
            return $this->ERROR;
        }
        $newBind = Array(
            'value'  => strtolower($bind),
            'name'   => $alias,
            'by'     => $hand,
            'date'   => time(),
            'type'   => 'default',
            'hidden' => true,
            'alias'  => true,
            'count'  => 0
        );
        $this->setBind($chan, $alias, $newBind);
        $this->pIrc->notice($nick, "Alias added! $alias now points to $bind", 0,
                            1);
    }

    function cmd_hidebind($nick, $chan, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
        if ($argc < 1) {
            return $this->BADARGS;
        }
        if (!$this->setBindHidden($chan, $argv[0], true)) {
            $this->pIrc->notice($nick, "No bind named $argv[0] found.", 0, 1);
            return $this->ERROR;
        }
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
        if (!$this->setBindHidden($chan, $argv[0], false)) {
            $this->pIrc->notice($nick, "No bind named $argv[0] found.", 0, 1);
            return $this->ERROR;
        }
        $this->pIrc->notice($nick,
                            $argv[0] . ' is no longer hidden in $binds and $tbinds',
                            1, 1);
    }

    function setBindHidden($chan, $bind, $val)
    {
        $bindInfo = $this->getBind($chan, $bind);
        if (!$bindInfo) {
            return false;
        }
        $bindInfo['hidden'] = (bool) $val;
        $this->setBind($chan, $bind, $bindInfo);
        return true;
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
        if ($sets != null && is_array($sets)) {
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
        if (!array_key_exists($bind, $sets)) {
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
        $chan = strtolower($chan);
        $this->checkInit($chan);
        $sets = $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
        if (!array_key_exists($bind, $sets)) {
            return null;
        }

        foreach ($sets as $key => $info) {
            if (array_key_exists('alias', $info) && $info['alias'] &&
                strtolower($info['value']) == $bind) {
                unset($sets[$key]);
            }
        }

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
        if ($newBind == null) {
            $this->addBind($value, $bind, $hand, $chan);
            $reply = "Bind $bind has been added";
            $this->pIrc->notice($nick, $reply, 0, 1);
            return $this->OK;
        }
        if (array_key_exists('alias', $newBind) && $newBind['alias']) {
            $newBind = $this->getBind($chan, $newBind['value']);
            if ($newBind == null) {
                $this->addBind($value, $bind, $hand, $chan);
                $reply = "Bind $bind has been added";
                $this->pIrc->notice($nick, $reply, 0, 1);
                return $this->OK;
            }
        }
        $newBind['name']  = $bind;
        $newBind['value'] = $value;
        $newBind['by']    = $hand;
        $newBind['date']  = time();
        $reply            = "Bind $bind has been updated";
        $rv               = $this->OK;
        if ($newBind['type'] == 'chanserv' && $access < 5) {
            if ($override) {
                $rv = $this->OK | $this->OVERRIDE;
            } else {
                $this->pIrc->notice($nick,
                                    "You need level 5 access to modify chanserv bindtypes.");
                return $this->ERROR;
            }
        }

        $this->setBind($chan, $bind, $newBind);
        $this->pIrc->notice($nick, $reply);
        return $rv;
    }

    function addBind($value, $bind, $hand, $chan)
    {
        $newBind = Array(
            'value'  => $value,
            'name'   => $bind,
            'by'     => $hand,
            'date'   => time(),
            'type'   => 'default',
            'hidden' => false,
            'alias'  => false,
            'count'  => 0
        );
        $this->setBind($chan, $bind, $newBind);
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
    }

    function cmdCatch($cmd, $nick, $chan, $arg2)
    {
        $bindInfo = $this->getBind($chan, $cmd);
        if ($bindInfo == null) {
            return false;
        }
        if (array_key_exists('alias', $bindInfo) && $bindInfo['alias']) {
            $bindInfo = $this->getBind($chan, $bindInfo['value']);
            if ($bindInfo == null) {
                return false;
            }
        }
        
        $bindInfo['count'] ++;
        $this->setBind($chan, $cmd, $bindInfo);

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
        
        if (array_key_exists('alias', $bindInfo) && $bindInfo['alias']) {
            $bindInfo = $this->getBind($chan, $bindInfo['value']);
            if ($bindInfo == null) {
                $this->pIrc->notice($nick, "Bind $bind not found.", 0, 1);
                return $this->ERROR;
            }
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
        $list = implode(', ', array_keys($binds));
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
        $allbinds = $this->getAllBinds($chan);
        $binds = Array();
        $aliases = Array();
        foreach ($allbinds as $bind) {
            if (array_key_exists('alias', $bind) && $bind['alias'] == true) {
                $aliases[] = $bind['name'] . '=>' . $bind['value'];
            } else {
                $binds[] = $bind['name'];
            }
        }
        $this->pIrc->notice($nick, "Binds: " . implode(', ', $binds));
        $this->pIrc->notice($nick, "Aliases: " . implode(', ', $binds));
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

        $extra = '';

        if (array_key_exists('hidden', $bindInfo) && $bindInfo['hidden']) {
            $extra = "Hidden: true ";
        }

        if (array_key_exists('alias', $bindInfo) && $bindInfo['alias']) {
            $this->pIrc->notice($nick,
                                "$bindInfo[name] is an alias for $bindInfo[value]");
            return $this->OK;
        }

        $this->pIrc->notice($nick,
                            "Bind $bindInfo[name] of type $bindInfo[type]" .
            " last changed " . strftime('%D', $bindInfo['date']) .
            " by $bindInfo[by] and has been used $bindInfo[count] times $extra" .
            " Value: $bindInfo[value]", 1, 1);
    }

}

?>
