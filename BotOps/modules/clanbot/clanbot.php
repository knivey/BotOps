<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class clanbot extends Module {
    
    /*
     * checks if our settings exist on the channel
     * if not then create them
     */
    function checkInit($chan) {
        $sets = $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
        if($sets != null) {
            return;
        }
        $sets = Array();
        $this->gM('channel')->chgSet($chan, 'clanbot', 'binds', $sets);
    }
    
    function getBind($chan, $bind) {
        $bind = strtolower($bind);
        $this->checkInit($chan);
        $sets = $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
        if(!is_array($sets) || !array_key_exists($bind, $sets)) {
            return null;
        } else {
            return $sets[$bind];
        }
    }
    
    function getAllBinds($chan) {
        $this->checkInit($chan);
        return $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
    }
    
    function setBind($chan, $bind, $data) {
        $bind = strtolower($bind);
        $this->checkInit($chan);
        $sets = $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
        $sets[$bind] = $data;
        $this->gM('channel')->chgSet($chan, 'clanbot', 'binds', $sets);
    }
    
    function delBind($chan, $bind) {
        $bind = strtolower($bind);
        $this->checkInit($chan);
        $sets = $this->gM('channel')->getSet($chan, 'clanbot', 'binds');
        unset($sets[$bind]);
        $this->gM('channel')->chgSet($chan, 'clanbot', 'binds', $sets);
    }
    
    function cmd_bind($nick, $target, $arg2) {
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target);
        if(count($arg) < 2) {
            return $this->BADARGS;
        }
        $bind = array_shift($arg);
        $value = implode(' ', $arg);
        //check if the bind exists
        $newBind = $this->getBind($chan, $bind);
        if($newBind == null) {
            $newBind = Array(
                'value' => $value,
                'name' => $bind,
                'by' => $hand,
                'date' => time(),
                'type' => 'default',
                'count' => 0
            );
            $reply = "Bind $bind has been added";
        } else {
            $newBind['name'] = $bind;
            $newBind['value'] = $value;
            $newBind['by'] = $hand;
            $newBind['date'] = time();
            $reply = "Bind $bind has been updated";
        }
        $this->setBind($chan, $bind, $newBind);
        $this->pIrc->notice($nick, $reply);
    }
    
    public $bindvalue = '';
    
    function v_bindvalue() {
        $bv = $this->bindvalue;
        unset($this->bindvalue);
        return $bv;
    }
    
    function cmdOut($message, $extras) {
        //$extras = Array($cmd, $nick, $chan, $bindInfo, $type);
        $cmd = $extras[0];
        $nick = $extras[1];
        $chan = $extras[2];
        $bindInfo = $extras[3];
        $type = $extras[4];
        switch($type) {
            default:
            case 'notice':
                $this->pIrc->notice($nick, $message);
                break;
            case 'act':
                $this->pIrc->act($chan, $message);
                break;
            case 'chan':
                $this->pIrc->msg($chan, $message);
                break;
        }
        $bindInfo['count']++;
        $this->setBind($chan, $cmd, $bindInfo);
    }
    
    function cmdCatch($cmd, $nick, $chan, $arg2) {
        $bindInfo = $this->getBind($chan, $cmd);
        if($bindInfo == null) {
            return false;
        }
        $type = strtolower($bindInfo['type']);
        $this->bindvalue = $bindInfo['value'];
        $theme = $this->gM('SetReg')->getCSet('clanbot', $chan, 'theme');
        if($type == 'default') {
            $type = $this->gM('SetReg')->getCSet('clanbot', $chan, 'bindtype');
        }
        $this->gM('CmdReg')->lastCmdInfo['cmd'] = $bindInfo['name'];
        if($type == 'act') {
            $this->pIrc->act($chan, $bindInfo['value']);
            return true;
        }
        $extras = Array($cmd, $nick, $chan, $bindInfo, $type);
        $this->gM('ParseUtil')->parse($theme, 'cmdOut', $this, $extras);
        return true;
    }
    
    function cmd_unbind($nick, $target, $arg2) {
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target);
        if($arg2 == '') {
            return $this->BADARGS;
        }
        $bindInfo = $this->getBind($chan, $arg[0]);
        if($bindInfo == null) {
            $this->pIrc->notice($nick, "Bind $arg[0] does not exist");
            return $this->ERROR;
        }
        $this->delBind($chan, $arg[0]);
        $this->pIrc->notice($nick, "Bind $arg[0] has been removed");
    }
    
    function cmd_bindtype($nick, $target, $arg2) {
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target);
        if($arg2 == '') {
            return $this->BADARGS;
        }
        $bindInfo = $this->getBind($chan, $arg[0]);
        if($bindInfo == null) {
            $this->pIrc->notice($nick, "Bind $arg[0] does not exist");
            return $this->ERROR;
        }
        if(!isset($arg[1])) {
            $this->pIrc->notice($nick, "Bindtype for $arg[0] is $bindInfo[type]");
            return $this->OK;
        }
        $type = strtolower($arg[1]);
        if($type != 'default' && $type != 'notice' && $type != 'chan' && $type != 'act') {
            $this->pIrc->notice($nick, "Bind please choose a correct type: default, notice, chan, act");
            return $this->ERROR;
        }
        $bindInfo['type'] = $type;
        $bindInfo['by'] = $hand;
        $bindInfo['date'] = time();
        $this->setBind($chan, strtolower($arg[0]), $bindInfo);
        $this->pIrc->notice($nick, "Bindtype for $arg[0] is now set to $type");
    }
    
    function v_binds() {
        $chan = $this->gM('ParseUtil')->getV('chan');
        $binds = $this->getAllBinds($chan);
        $list = implode(', ', array_keys($binds));
        return $list;
    }
    
    function v_tbinds() {
        $chan = $this->gM('ParseUtil')->getV('chan');
        $binds = $this->getAllBinds($chan);
        $trig = $this->gM('channel')->getTrig($chan);
        $list = '';
        foreach(array_keys($binds) as $b) {
            $list .= $trig . $b . ' ';
        }
        return trim($list);
    }
    
    function cmd_binds($nick, $chan, $arg2) {
        $binds = $this->getAllBinds($chan);
        $list = implode(', ', array_keys($binds));
        $this->pIrc->notice($nick, "Binds: $list");
    }
    
    function cmd_bindinfo($nick, $target, $arg2) {
        $arg = explode(' ', $arg2);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        $chan = strtolower($target);
        if($arg2 == '') {
            return $this->BADARGS;
        }
        $bindInfo = $this->getBind($chan, $arg[0]);
        if($bindInfo == null) {
            $this->pIrc->notice($nick, "Bind $arg[0] does not exist");
            return $this->ERROR;
        }
        $this->pIrc->notice($nick, "Bind $bindInfo[name] of type $bindInfo[type] last changed " . strftime('%D', $bindInfo['date']) . " by $bindInfo[by] and has been used $bindInfo[count] times Value: $bindInfo[value]",1,1);
    }
    
}

?>
