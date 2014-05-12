<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class xnet extends Module {
    
    function rpc_ping($params) {
        return $params;
    }
    
    public $botsOnReqs = Array();
    public $botsOnReqIDs = 0;
    /**
     * Returns the bots currently accesable via xmlrpc
     * @param class $cbClass class for callback
     * @param string $cbFunc function in class for callback
     * @param mixed $extra store callback data
     * @return Array array of botnames
     */
    function botsOnline($cbClass, $cbFunc, $extra = null) {
        //echo "botsOnline Called!!!!!\n";
        //var_dump($extra);
        try {
            $stmt = $this->pMysql->query("SELECT name FROM bots");
            $row = $stmt->fetchAll();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        $id = $this->botsOnReqIDs++;
        $bots = Array();
        foreach ($row as $bot) {
            $bots[] = $bot['name'];
            $ex = Array(
                'id' => $id,
                'bot' => $bot['name'],
            );
            $this->sendRPC($this, 'botsOnlineCB', $bot['name'], 'ping', 1, $ex);
        }
        $this->botsOnReqs[$id] = Array(
            'bots' => $bots,
            'online' => Array(),
            'offline' => Array(),
            'extra' => $extra,
            'cbClass' => $cbClass,
            'cbFunc' => $cbFunc
        );
    }
    
    function botsOnlineCB($resp, $extra) {
        //echo "botsOnlineCB ($extra[id]) Called!!!!!\n";
        //var_dump($extra['extra']);
        if(!array_key_exists($extra['id'], $this->botsOnReqs)) {
            echo "botsOnlineCB got resp with unknown ReqID\n";
            return;
        }
        if(array_key_exists('error', $resp)) {
            $this->botsOnReqs[$extra['id']]['offline'][] = $extra['bot'];
        }
        if(array_key_exists('resp', $resp)) {
            $this->botsOnReqs[$extra['id']]['online'][] = $extra['bot'];
        }
        if(count($this->botsOnReqs[$extra['id']]['online'])
                + count($this->botsOnReqs[$extra['id']]['offline'])
                == count($this->botsOnReqs[$extra['id']]['bots'])) {
            $cbFunc = $this->botsOnReqs[$extra['id']]['cbFunc'];
            $cbClass = $this->botsOnReqs[$extra['id']]['cbClass'];
            $ex = $this->botsOnReqs[$extra['id']]['extra'];
            $bots = $this->botsOnReqs[$extra['id']]['bots'];
            $on = $this->botsOnReqs[$extra['id']]['online'];
            $off = $this->botsOnReqs[$extra['id']]['offline'];
            if(is_object($cbClass) && method_exists($cbClass, $cbFunc)) {
                $cbClass->$cbFunc($bots, $on, $off, $ex);
            }
            unset($this->botsOnReqs[$extra['id']]);
        }
    }
    
    
    public $sendToAllReqs = Array();
    public $sendToAllID = 0;
    
    /**
     * Sends an xmlrpc request to all bots
     * @param class $cbClass class for callback
     * @param string $cbFunc function in class for callback
     * @param string $bot name of bot
     * @param string $method method to call
     * @param mixed $params method parameters
     * @param mixed $extra store callback data
     * calls calback function
     * $resp = Array['bot'] = Array('error' => set if failed, 'resp' set to response, 'bot' set to bot))
     * $ex = extra info
     */
    function sendToAll($cbClass, $cbFunc, $method, $params, $extra = null) {
        //echo "sendToAll Called!!!!!\n";
        //var_dump($extra);
        $id = $this->sendToAllID++;
        $this->sendToAllReqs[$id] = Array(
            'extra' => $extra,
            'cbClass' => $cbClass,
            'cbFunc' => $cbFunc,
            'data' => Array()
        );
        $ex = Array(
            'id' => $id
        );
        $bots = Array();
        $lol = Array();
        try {
            $stmt = $this->pMysql->query("SELECT name,xmlip,xmlport FROM bots");
            $row = $stmt->fetchAll();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        foreach ($row as $bot) {
            $ex['bot'] = $bot['name'];
            $bots[] = $bot['name'];
            if ($bot['name'] == '') {
                echo "WARNING! ! ! ! ! ! sendToAll got null botname?~\n";
                continue;
            }
            $botaddr = $bot['xmlip'] . ':' . $bot['xmlport'];
            //echo "SENDING TO BOT $bot[name]\n";
            $lol[$bot['name']] = new Http($this->pSockets, $this, 'sendToAllrecv');
            $lol[$bot['name']]->xmlrpcQuery($botaddr, $ex, $method, $params);
        }
        $this->sendToAllReqs[$id]['bots'] = $bots;
    }
    
    function sendToAllrecv($data, $ex, $resp) {
        //echo "sendToAllrecv Called!!!!!\n";
        //var_dump($ex);
        $id = $ex['id'];
        $bot = $ex['bot'];
        if(!array_key_exists($id, $this->sendToAllReqs)) {
            echo "sendToAllrecv got resp with unknown ReqID\n";
            return;
        }
        $this->sendToAllReqs[$id]['data'][$bot]['bot'] = $bot;
        if(is_array($data)) {
            $this->sendToAllReqs[$id]['data'][$bot]['error'] = $data;
        } else {
            $this->sendToAllReqs[$id]['data'][$bot]['resp'] = $resp;
        }
        if(count($this->sendToAllReqs[$id]['data'])
                == count($this->sendToAllReqs[$id]['bots'])) {
            $cbClass = $this->sendToAllReqs[$id]['cbClass'];
            $cbFunc = $this->sendToAllReqs[$id]['cbFunc'];
            $extra = $this->sendToAllReqs[$id]['extra'];
            $data = $this->sendToAllReqs[$id]['data'];
            if(!is_object($cbClass) || !method_exists($cbClass, $cbFunc)) {
                unset($this->sendToAllReqs[$id]);
                return;
            }
            $cbClass->$cbFunc($data, $extra);
            unset($this->sendToAllReqs[$id]);
        }
    }
    
    /**
     * Sends an xmlrpc request to the botname
     * @param class $cbClass class for callback
     * @param string $cbFunc function in class for callback
     * @param string $bot name of bot
     * @param string $method method to call
     * @param mixed $params method parameters
     * @param mixed $extra store callback data
     * calls calback function
     * $resp = Array('error' => set if failed, 'resp' set to response, 'bot' set to bot)
     * $ex = extra info
     */
    function sendRPC($cbClass, $cbFunc, $bot, $method, $params, $extra = null) {
        //echo "sendRPC Called!!!!!\n";
        //var_dump($extra);
        $ex = Array(
            'bot' => $bot,
            'extra' => $extra,
            'cbClass' => $cbClass,
            'cbFunc' => $cbFunc
        );
        try {
            $stmt = $this->pMysql->prepare("SELECT `name`,`xmlip`,`xmlport` FROM `bots` WHERE `name` = :bot");
            $stmt->execute(Array(':bot'=>$bot));
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
        if($row['name'] == '') {
            return;
        }
        $botaddr = $row['xmlip'] . ':' . $row['xmlport'];
        $lol = new Http($this->pSockets, $this, 'sendRPCrecv');
        $lol->xmlrpcQuery($botaddr, $ex, $method, $params);
    }
    
    function sendRPCrecv($data, $ex, $resp) {
        //echo "sendRPCrecv Called!!!!!\n";
        //var_dump($ex['extra']);
        $out = Array(
            'bot' => $ex['bot']
        );
        $extra = $ex['extra'];
        $cbClass = $ex['cbClass'];
        $cbFunc = $ex['cbFunc'];
        if(!is_object($cbClass) || !method_exists($cbClass, $cbFunc)) {
            return;
        }
        if(is_array($data)) {
            $out['error'] = $data;
            $cbClass->$cbFunc($out, $extra);
            return;
        }
        $out['resp'] = $resp;
        $cbClass->$cbFunc($out, $extra);
    }
}
?>
