<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';
class clanbot extends Module
{

    public $bindTypes = Array(
        'default',
        'notice',
        'chan',
        'act',
        'chanserv',
    );

    function cmd_bindalias(CmdRequest $r)
    {
        $alias    = $r->args['alias'];
        $bind     = $r->args['bind'];
        $bindInfo = $this->getBind($r->chan, $bind);
        if (!$bindInfo) {
            throw new CmdException("No bind named $bind found.", 0, 1);
        }
        if (array_key_exists('alias', $bindInfo) && $bindInfo['alias']) {
            $r->notice("$bind is itself an alias, creating an alias to $bindInfo[value] instead.", 0, 1);
            $bind = $bindInfo['value'];
            $bindInfo = $this->getBind($r->chan, $bindInfo['value']);
            if (!$bindInfo) {
                throw new CmdException("An unexpected error occurred creating the alias.");
            }
        }
        if ($this->getBind($r->chan, $alias) != null) {
            throw new CmdException("There is already a bind named $alias in $r->chan, unbind it first.");
        }
        $newBind = Array(
            'value'  => strtolower($bind),
            'name'   => $alias,
            'by'     => $r->account,
            'date'   => time(),
            'type'   => 'default',
            'hidden' => true,
            'alias'  => true,
            'count'  => 0
        );
        $this->setBind($r->chan, $alias, $newBind);
        $r->notice("Alias added! $alias now points to $bind", 0, 1);
    }

    function cmd_hidebind(CmdRequest $r)
    {
        if (!$this->setBindHidden($r->chan, $r->args['bind'], true)) {
            throw new CmdException("No bind named {$r->args['bind']} found.");
        }
        $r->notice($r->args['bind'] . ' is now hidden from $binds and $tbinds', 1, 1);
    }

    function cmd_unhidebind(CmdRequest $r)
    {
        if (!$this->setBindHidden($r->chan, $r->args['bind'], false)) {
            throw new CmdException("No bind named {$r->args['bind']} found.");
        }
        $r->notice($r->args['bind'] . ' is no longer hidden in $binds and $tbinds', 1, 1);
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
    function checkInit(string $chan)
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

    function cmd_bind(CmdRequest $r)
    {
        $bind = $r->args['name'];
        $value = $r->args['value'];
        //check if the bind exists
        $newBind = $this->getBind($r->chan, $bind);
        if ($newBind == null) {
            $this->addBind($value, $bind, $r->account, $r->chan);
            $r->notice("Bind $bind has been added", 0, 1);
            return;
        }
        if (array_key_exists('alias', $newBind) && $newBind['alias']) {
            $a = $newBind['value'];
            $newBind = $this->getBind($r->chan, $newBind['value']);
            if ($newBind == null) {
                $this->addBind($value, $bind, $r->account, $r->chan);
                $r->notice("Bind $bind has been added", 0, 1);
                return;
            }
            $bind = $a;
        }
        $newBind['name']  = $bind;
        $newBind['value'] = $value;
        $newBind['by']    = $r->account;
        $newBind['date']  = time();
        if ($newBind['type'] == 'chanserv' && $r->access < 5) {
            if ($r->hasoverride) {
                $r->override = true;
            } else {
                throw new CmdException("You need level 5 access to modify chanserv bindtypes.");
            }
        }

        $this->setBind($r->chan, $bind, $newBind);
        $r->notice("Bind $bind has been updated");
        return $r;
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
        $this->setBind($chan, $bindInfo['name'], $bindInfo);

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

    function cmd_unbind(CmdRequest $r)
    {
        $bindInfo = $this->getBind($r->chan, $r->args['bind']);
        if ($bindInfo == null) {
            throw new CmdException("Bind {$r->args['bind']} does not exist");
        }
        $this->delBind($r->chan, $r->args['bind']);
        $r->notice("Bind {$r->args['bind']} has been removed", 0, 1);
    }

    function cmd_bindtype(CmdRequest $r)
    {
        $bind = $r->args['bind'];
        $bindInfo = $this->getBind($r->chan, $bind);
        if ($bindInfo == null) {
            throw new CmdException("Bind $bind does not exist");
        }

        if (array_key_exists('alias', $bindInfo) && $bindInfo['alias']) {
            $bind = $bindInfo['value'];
            $bindInfo = $this->getBind($r->chan, $bindInfo['value']);
            if ($bindInfo == null) {
                throw new CmdException("Bind $bind not found.");
            }
        }

        if (!isset($r->args[1])) {
            $r->notice("Bindtype for $bind is $bindInfo[type]", 0, 1);
            return;
        }

        $type = strtolower($r->args[1]);

        if (!in_array($type, $this->bindTypes)) {
            $types = implode(',', $this->bindTypes);
            throw new CmdException("Bind please choose a correct type: $types");
        }

        if ($type == 'chanserv' && $r->access < 5) {
            if ($r->hasoverride) {
                $r->override = true;
            } else {
                throw new CmdException("You need level 5 access to modify chanserv bindtypes.");
            }
        }

        $bindInfo['type'] = $type;
        $bindInfo['by']   = $r->account;
        $bindInfo['date'] = time();
        $this->setBind($r->chan, $bind, $bindInfo);

        $r->notice("Bindtype for $bind is now set to $type", 0, 1);
        return $r;
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

    function cmd_binds(CmdRequest $r)
    {
        $allbinds = $this->getAllBinds($r->chan);
        $binds    = Array();
        $aliases  = Array();
        foreach ($allbinds as $bind) {
            if (array_key_exists('alias', $bind) && $bind['alias'] == true) {
                $aliases[] = $bind['name'] . ' => ' . $bind['value'];
            } else {
                $binds[] = $bind['name'];
            }
        }
        $r->notice("\2Binds:\2 " . implode(', ', $binds), 0, 1);
        if (!empty($aliases)) {
            $r->notice("\2Aliases:\2 " . implode(', ', $aliases), 0, 1);
        }
    }

    function cmd_bindinfo(CmdRequest $r)
    {
        $bind = $r->args['bind'];
        $bindInfo = $this->getBind($r->chan, $bind);
        if ($bindInfo == null) {
            throw new CmdException("Bind $bind does not exist");
        }

        $extra = '';
        if (array_key_exists('hidden', $bindInfo) && $bindInfo['hidden']) {
            $extra = "Hidden: true ";
        }

        if (array_key_exists('alias', $bindInfo) && $bindInfo['alias']) {
            $r->notice("$bindInfo[name] is an alias for $bindInfo[value]", 0, 1);
            return;
        }

        $r->notice("Bind $bindInfo[name] of type $bindInfo[type]" .
            " last changed " . strftime('%D', $bindInfo['date']) .
            " by $bindInfo[by] and has been used $bindInfo[count] times $extra" .
            "Value: $bindInfo[value]", 1, 1);
    }

}


