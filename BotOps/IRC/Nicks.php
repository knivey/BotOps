<?php

/**
 * Track IRC Nicks and Hosts
 */
class Nicks {
    /**
     * Stores temporary hosts for PMed commands from no shared chan
     * stored as $tppl['nick'] = 'host'
     * @var Array
     */
    public $tppl = Array();
    /**
     * Stores all our known nicks
     * @var Array
     */
    public $ppl = Array();
    static protected $pplTemplate;
    
    function __construct() {
        self::$pplTemplate  = Array(
            'host' => NULL,
            'channels' => Array(),
            'lastMsgTime' => NULL,
            'lastMsg' => Array('target' => NULL, 'msg' => NULL),
            'connectTime' => NULL,
            'onSplit' => false,
            'splitIdx' => false,
        );
    }
    
    /**
     * Clears all data
     */
    function clearAll() {
        $this->ppl = Array();
        $this->tppl = Array();
    }
    
    /**
     * Set the temporary ppl array
     * @param string $nick
     * @param string $host
     */
    function tppl($nick, $host) {
    	$this->tpplClear();
    	//If nick already exists then update the host and don't add to tppl
    	$key = get_akey_nc($nick, $this->ppl);
    	if($key) {
    		$this->ppl[$key]['host'] = $host;
    		return;
    	}
        $this->tppl[$nick] = $host;
    }
    
    /**
     * Reset the temporary people array
     */
    function tpplClear() {
        $this->tppl = Array();
    }
    
    /**
     * Handle names reply
     * @param array $arg
     */
    function names($arg) {
        //:TechConnect.NL.EU.GameSurge.net 353 knivey = #bots :@Etrigan knivey eggplay01 @Reck @Oth @ChanServ iron
        $arg[5] = substr($arg[5], 1);
        $names = explode(' ', arg_range($arg, 5, -1));
        /*
         * names should be gotten before /who
         * currently there is a bug in gamesurge normally we would see both a @+
         * if user has both modes, but currently its only @ or +
         * this might NOT be a bug, however i will process the names
         * here anyway incase our who response takes too long
         * - this is why we send this to get ALL the info we want
         * - after our 366 (eof names)
         * << who #knivey %tnchu,777
         * >> (:server 354 ourname customnum channel ident host nick flags) 
         * >> :TechConnect.NL.EU.GameSurge.net 354 knivey 777 #bots ChanServ Services.GameSurge.net ChanServ H*@+d
         */
        foreach ($names as $n) {
            $mode = Array();
            if (cisin($n, '@'))
                $mode['@'] = '@';
            if (cisin($n, '+'))
                $mode['+'] = '+';
            $n = trim($n, '@+');
            $key = get_akey_nc($n, $this->ppl);
            if ($key == null) {
                $this->ppl[$n] = self::$pplTemplate;
                $this->ppl[$n]['channels'][$arg[4]] = array('modes' => $mode, 'jointime' => null);
                $this->ppl[$n]['connectTime'] = time();
            } else {
                $this->ppl[$key]['channels'][$arg[4]] = array('modes' => $mode, 'jointime' => null);
            }
        }
    }
    
    /**
     * Handle who reply 
     * @param array $arg
     */
    function who($arg) {
        //:server 354 ourname customnum channel ident host nick flags
        if ($arg[3] != 777)
            return; // wasn't our number
        $key = get_akey_nc($arg[7], $this->ppl);
        if ($key == null) {
            return; //Don't add the user we have no idea how they got here
            //TODO send warning to logger.
            //$key = $arg[7];
            //$this->ppl[$key] = self::$pplTemplate;
        }
        $ckey = get_akey_nc($arg[4], $this->ppl[$key]['channels']);
        if($ckey == null) {
        	//TODO send warning to logger.
            //$ckey = $arg[4];
            return; //Don't add information that we shouldn't be getting
        }
        $this->ppl[$key]['host'] = $arg[5] . '@' . $arg[6];
        //process the rest of their channel mode (@+)
        //4 - chan 7 - nick 8 - mode
        $mode = Array();
        if (cisin($arg[8], '@'))
            $mode['@'] = '@';
        if (cisin($arg[8], '+'))
            $mode['+'] = '+';
        $this->ppl[$key]['channels'][$ckey]['modes'] = $mode;
    }
    
    /**
     * Handle join
     * @param string $nick
     * @param string $host
     * @param string $chan
     */
    function join($nick, $host, $chan) {
        $key = get_akey_nc($nick, $this->ppl);
        if ($key != null) {
            //good idea to make sure host is correct
            $this->ppl[$key]['host'] = $host;
            $this->ppl[$key]['channels'][$chan] = Array(
                'modes' => Array(),
                'jointime' => time()
            );
        } else {
            //Create a whole new ppl entry
            $this->ppl[$nick] = self::$pplTemplate;
            $this->ppl[$nick]['host'] = $host;
            $this->ppl[$nick]['channels'][$chan] = Array(
                'modes' => Array(),
                'jointime' => time(),
            );
            $this->ppl[$nick]['connectTime'] = time();
        }
    }
    
    /**
     * Handle nick changes
     * @param string $oldnick
     * @param string $newnick
     */
    function nick($oldnick, $newnick) {
        $key = get_akey_nc($oldnick, $this->ppl);
        if($key == null) {
        	//TODO send warning to logger.
            return; //should never happen
        }
        $this->ppl[$newnick] = $this->ppl[$key];
        unset($this->ppl[$key]);
    }
    
    /**
     * Handle Parts
     * @param string $nick
     * @param string $chan
     */
    function part($nick, $chan) {
        $key = get_akey_nc($nick, $this->ppl);
        if ($key != null) {
            //check their channels if they just parted last one delete them otherwise update chans
            if (count($this->ppl[$key]['channels']) == 1) {
                unset($this->ppl[$key]);
            } else {
                $ckey = get_akey_nc($chan, $this->ppl[$key]['channels']);
                unset($this->ppl[$key]['channels'][$ckey]);
            }
        }
    }
    
    /**
     * Handle ourself leaving a channel
     * @param string $chan
     */
    function usPart($chan) {
        //see if its us leaving
        foreach ($this->ppl as $n => &$i) {
            $ckey = get_akey_nc($chan, $i['channels']);
            if ($ckey != null) {
                //See if that was the only channel we saw them on
                if (count($i['channels']) == 1) {
                    unset($this->ppl[$n]);
                } else {
                    unset($i['channels'][$ckey]);
                }
            }
        }
    }
    
    /**
     * Handle Kicks
     * @param string $nick
     * @param string $chan
     */
    function kick($nick, $chan) {
        $this->part($nick, $chan);
    }
    
    /**
     * Handle Quits
     * @param string $nick
     */
    function quit($nick) {
        $key = get_akey_nc($nick, $this->ppl);
        if($key != null) {
            unset($this->ppl[$key]);
        }
    }
    
    /**
     * Get the proper case for $nick and $chan keys in the ppl array
     * on failure empty array returned, otherwise Array(nick, chan)
     * @param string $nick
     * @param string $chan
     * @return Array
     */
    function getChanNickKey($nick, $chan) {
        $key = get_akey_nc($nick, $this->ppl);
        if($key == null) {
            return Array();
        }
        $ckey = get_akey_nc($chan, $this->ppl[$key]['channels']);
        if($ckey == null) {
            return Array();
        }
        return Array($key, $ckey);
    }
    
    /**
     * Handle Op
     * @param string $nick
     * @param string $chan
     */
    function Op($nick, $chan) {
        $cnkeys = $this->getChanNickKey($nick, $chan);
        if(empty($cnkeys)) {
        	//TODO send warning to logger.
            return;
        }
        list($key, $ckey) = $cnkeys;
        $this->ppl[$key]['channels'][$ckey]['modes']['@'] = '@';
    }
    
    /**
     * Handle DeOp
     * @param string $nick
     * @param string $chan
     */    
    function DeOp($nick, $chan) {
        $cnkeys = $this->getChanNickKey($nick, $chan);
        if(empty($cnkeys)) {
        	//TODO send warning to logger.
            return;
        }
        list($key, $ckey) = $cnkeys;
        unset($this->ppl[$key]['channels'][$ckey]['modes']['@']);
    }

    /**
     * Handle Voice
     * @param string $nick
     * @param string $chan
     */
    function Voice($nick, $chan) {
        $cnkeys = $this->getChanNickKey($nick, $chan);
        if(empty($cnkeys)) {
        	//TODO send warning to logger.
            return;
        }
        list($key, $ckey) = $cnkeys;
        $this->ppl[$key]['channels'][$ckey]['modes']['+'] = '+';
    }

    /**
     * Handle DeVoice
     * @param string $nick
     * @param string $chan
     */
    function DeVoice($nick, $chan) {
        $cnkeys = $this->getChanNickKey($nick, $chan);
        if(empty($cnkeys)) {
        	//TODO send warning to logger.
            return;
        }
        list($key, $ckey) = $cnkeys;
        unset($this->ppl[$key]['channels'][$ckey]['modes']['+']);
    }
    
    /**
     * Check if a user is opped on channel, If so return true
     * @param string $nick
     * @param string $chan
     * @return boolean
     */
    function isOp($nick, $chan) {
        $cnkeys = $this->getChanNickKey($nick, $chan);
        if(empty($cnkeys)) {
            return false;
        }
        list($key, $ckey) = $cnkeys;
        return array_key_exists('@', $this->ppl[$key]['channels'][$ckey]['modes']);
    }
    
    /**
     * Check if a user is voiced on channel, If so return true
     * @param string $nick
     * @param string $chan
     * @return boolean
     */    
    function isVoice($nick, $chan) {
        $cnkeys = $this->getChanNickKey($nick, $chan);
        if(empty($cnkeys)) {
            return false;
        }
        list($key, $ckey) = $cnkeys;
        return array_key_exists('+', $this->ppl[$key]['channels'][$ckey]['modes']);
    }
    
    /**
     * Get the channels array for $nick or empty array on fail.
     * [$chan] = Array(
     *           'modes' => Array('+' => '+'),
     *           'jointime' => time(),
     *       );
     * @param unknown $nick
     * @return array
     */
    function nickChans($nick) {
        $key = get_akey_nc($nick, $this->ppl);
        if($key == null) {
            return Array();
        }
        return $this->ppl[$key]['channels'];
    }
    
    /**
     * Get the nicks belonging to host, empty array if none found
     * Array('nick1','nick2',...)
     * @param string $host
     * @return Array
     */
    function h2n($host) {
        $out = Array();
        foreach($this->ppl as $n => $p) {
            if(pmatch($host, $p['host'])) {
                $out[] = $n;
            }
        }
        foreach($this->tppl as $n => $p) {
            if(pmatch($host, $p)) {
                $out[] = $n;
            }
        }
        return $out;
    }
    
    /**
     * Get the host for $nick, null on failure
     * @param string $nick
     * @return string
     */
    function n2h($nick) {
        $key = get_akey_nc($nick, $this->ppl);
        if($key == null) {
            $key = get_akey_nc($nick, $this->tppl);
            if($key != null) {
                return $this->tppl[$key];
            }
            return;
        }
        return $this->ppl[$key]['host'];
    }
}

?>
