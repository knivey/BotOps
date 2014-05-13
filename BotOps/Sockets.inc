<?php

/*
 * **************************************************************************
 * Sockets.inc
 *  Provides a class to manage all our socket connections.
 *  TODO add throttling support and maxbuffer
 *  TODO add reconnect wait time
 * **************************************************************************
 */

class Sockets {

    /**
     * Total bytes received
     * @var int $rBytes
     */
    public $rBytes;

    /**
     * Totale bytes sent
     * @var int $sBytes
     */
    public $sBytes;

    /**
     * Timeout for select in seconds
     * @var int $timeout
     */
    public $timeout = 0;

    /**
     * Timeout for select in uSeconds
     * @var int $timeoutu
     */
    public $timeoutu = 100;

    /**
     * indexed by socket: $this->sockets[$sock] = Array(...
     * 'sock' = The socket
     * 'connectTime' = time connection was established, or time connection last attempted if not connected
     * 'connectTimeout' = how long until connection fails with timeout
     * 'readTO' = seconds till readTOFunc is called, null to disable
     * 'readTOFunc' = see above, gets called if socket hasn't had data
     * 'lastReadTime' = time we last received from socket
     * 'lastWriteTime' = time we last wrote to socket
     * 'class' = class using the socket
     * 'rFunc' = class->function to call when new data is read
     * 'style' = how data is to be read into rFunc, 0 for binary, 1 for \r\n sections
     * 'eFunc' = class->function to call when socket gets an error
     * 'outq' = buffer for outgoing data (should only be used internally)
     * 'inq' = buffer of received data (should only be used internally)
     * 'state' connection state (TCP)
     *  -1 = UDP socket
     *   0 = disconnected
     *   1 = connected
     *   2 = listening
     *   3 = accepting
     *   4 = connecting (not even used?)
     * 'rBytes' = stats on bytes we've read 
     * 'sBytes' = stats on bytes we've sent
     * 'persist' = reconnect automagically
     *
     * @var arrray $sockets
     * @todo Because of the array complexity maybe make an object for the socket
     */
    public $sockets = Array();

    /**
     * @param number $to Select timeout Seconds
     * @param number $tou Select timeout uSeconds
     */
    function __construct($to = 5, $tou = 100) {
        $this->timeout  = $to;
        $this->timeoutu = $tou;
    }

    /**
     * Add data to a sockets outq
     * @param resource $sock The socket
     * @param mixed $data The Data
     */
    function send($sock, $data) {
        if (array_key_exists(intval($sock), $this->sockets)) {
            $this->sockets[intval($sock)]['outq'] .= $data;
        } else {
            echo "Sockets Error: trying to send to $sock (doesn't exist)";
        }
    }

    /**
     * Find the key for the sockets in our array
     * @param resource $sock
     * @return int|boolean The array key for the socket
     */
    function findSock($sock) {
        foreach ($this->sockets as $key => $info) {
            if ($sock === $info['sock']) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Connect the socket, true on success false on failure
     * @param resource $sock
     * @param string $host
     * @param number $port
     * @param number $timeout Connect Timeout
     * @param string $rto Read Timeout
     * @return boolean
     */
    function connect($sock, $host, $port, $timeout = 15, $rto = NULL) {
        $this->sockets[intval($sock)]['host']   = $host;
        $this->sockets[intval($sock)]['port']   = $port;
        $this->sockets[intval($sock)]['to']     = $timeout;
        $this->sockets[intval($sock)]['readTO'] = $rto;
        $this->sockets[intval($sock)]['recon']  = false; //false until timeout

        if ($this->findSock($sock) === false) {
            return false;
        }

        $this->sockets[intval($sock)]['connectTimeout'] = $timeout;

        //Make sure our socket is non-blocking
        socket_set_nonblock($sock);

        $this->dbg("state was " . $this->sockets[intval($sock)]['state'] . " state=4,connectTime=time(),readTime=time()");

        $this->sockets[intval($sock)]['state']       = 4; //set state to connecting
        $this->sockets[intval($sock)]['connectTime'] = time(); //update last attempt time
        $this->sockets[intval($sock)]['readTime']    = time(); //Keep from timing out later

        $good = @socket_connect($sock, $host, $port);

        $this->dbg("socket_connect $sock return ahead");
        $this->dbg_dump($good);

        if ($good === true) {
            return true;
            $this->mConnected($sock); //TODO: Check on this :(
            return true;
        } else {
            $this->dbg("connect return was false\n");
            $error = socket_last_error($sock);
            socket_clear_error($sock);

            if ($error != '' && $error != 11 && $error != 114 && $error != 115 && $error != 106) {
                $this->dbg("error after socket_connect: [$error] " . socket_strerror($error));
                $this->mDead($sock, $error);
                return false;
            } else {
                $this->dbg("Connection still trying state=4,connectTime=time()\n");
                $this->sockets[intval($sock)]['state']       = 4;
                $this->sockets[intval($sock)]['connectTime'] = time();
                return true;
            }
        }
    }

    /**
     * Set the read timeout for a socket
     * @param number $time New timeout time
     * @param resource $sock
     * @return boolean looks like always false right now.
     */
    function setRTO($time, $sock) {
        $k = $this->findSock($sock);

        if ($k === false || is_nan($time)) {
            $this->dbg("$sock not found");
            return false;
        }

        $this->dbg("Setting readTO=$time on $sock");
        $this->sockets[$k]['readTO'] = $time;
    }

    /**
     * create a listening socket
     * when the socket accepts a new connection cFunc will be called
     * rFunc,eFunc,$style etc is assigned to created socket from accepting connection
     * 
     * @param object   $class Class the callback functions are on
     * @param function $rFunc Read callback
     * @param function $eFunc Error callback
     * @param function $cFunc Connected callback
     * @param string   $ip    Ip to bind to
     * @param number   $port  Port to bind to
     * @param number   $style read style: 0 for binary, 1 for \r\n sections
     * @return boolean|resource
     */
    function createTCPListen(&$class, $rFunc, $eFunc, $cFunc, $ip, $port, $style = 0) {
        $fp = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$fp) {
            $this->dbg("Error creating socket: " . socket_strerror(socket_last_error()));
            return FALSE;
        }

        if (!socket_set_option($fp, SOL_SOCKET, SO_REUSEADDR, 1)) {
            $this->dbg("Error setting socket option: " . socket_strerror(socket_last_error()));
            return false;
        }
        if (!socket_bind($fp, $ip, $port)) {
            $this->dbg("Error binding socket: " . socket_strerror(socket_last_error()));
            return false;
        }

        socket_listen($fp); //, 32); the 32 just screwed everything up
        //read socket doesn't really need all these things but i'm adding them anyway
        $this->sockets[intval($fp)] = Array(
            'host'         => NULL,
            'port'         => NULL,
            'to'           => NULL,
            'readTO'       => NULL,
            'recon'        => false,
            'sock'         => $fp,
            'class'        => $class,
            'rFunc'        => $rFunc,
            'style'        => $style,
            'eFunc'        => $eFunc,
            'cFunc'        => $cFunc,
            'lastReadTime' => time(),
            'outq'         => '',
            'inq'          => '',
            'state'        => 2, // 2 means socket is listening
            //'persist'    => $persist,
            'rBytes'       => 0,
            'sBytes'       => 0
        );

        $this->dbg("$fp created");
        return $fp;
    }

    /**
     * Change the callback class assigned to a socket
     * @param object $old
     * @param object $new
     */
    function chgClass(&$old, &$new) {
        if ($old == null)
            return;

        foreach ($this->sockets as $idx => $val) {
            if (get_class($val['class']) == get_class($old)) {
                $this->sockets[$idx]['class'] = &$new;
            }
        }
    }

    /**
     * Accepts a new connection on TCP listen socket
     * and sets up its array
     * @param resource $sock
     */
    function acceptTCP($sock) {
        $fp = socket_accept($sock);
        $ip = '';
        socket_getpeername($fp, $ip);

        $ps = $this->sockets[intval($sock)];

        $this->sockets[intval($fp)] = Array(
            'host'         => $ip,
            'port'         => NULL,
            'to'           => NULL,
            'readTO'       => NULL,
            'recon'        => false,
            'sock'         => $fp,
            'class'        => $ps['class'],
            'rFunc'        => $ps['rFunc'],
            'style'        => $ps['style'],
            'eFunc'        => $ps['eFunc'],
            'cFunc'        => $ps['cFunc'],
            'lastReadTime' => time(),
            'outq'         => '',
            'inq'          => '',
            'state'        => 1,
            'persist'      => false,
            'rBytes'       => 0,
            'sBytes'       => 0
        );

        $ps['class']->{$ps['cFunc']}($fp, $ip);
        $this->dbg("New socket connection from $ip on $sock accepted to $fp");
    }

    /**
     * Creats a new UDP socket
     * 
     * @param object $class		callback class
     * @param function $rFunc	callback function for reads
     * @param function $eFunc	callback funtions for errors
     * @param string $host		host to connect to
     * @param number $port		port to connect to
     * @param number $style		read style: 0 for binary, 1 for \r\n sections
     * @return boolean|resource
     */
    function createUDP(&$class, $rFunc, $eFunc, $host, $port, $style = 0) {
        $fp = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if (!$fp) {
            $this->dbg("Error creating socket: " . socket_strerror(socket_last_error()));
            return FALSE;
        }

        if (!socket_bind($fp, $host, $port)) {
            $this->dbg("Error binding sock: " . socket_strerror(socket_last_error($fp)));
            return false;
        }

        socket_getsockname($fp, $host, $port);
        $this->sockets[intval($fp)] = Array(
            'host'         => $host,
            'port'         => $port,
            'to'           => NULL,
            'readTO'       => NULL,
            'recon'        => false,
            'sock'         => $fp,
            'class'        => $class,
            'rFunc'        => $rFunc,
            'style'        => $style,
            'eFunc'        => $eFunc,
            'lastReadTime' => time(),
            'outq'         => '',
            'inq'          => '',
            'state'        => -1,
            'persist'      => false,
            'rBytes'       => 0,
            'sBytes'       => 0
        );

        $this->dbg("$fp created");
        return $fp;
    }

    /**
     * Create a IPv4 tcp socket,
     * 
     * @param object   $class Class the callback functions are on
     * @param function $rFunc Read callback
     * @param function $eFunc Error callback
     * @param number   $style read style: 0 for binary, 1 for \r\n sections
     * @param function $cFunc Connected callback
     * @param bool     $persist Try reconnecting on disconnect
     * @param string   $bind  IP to bind to
     * @return boolean|resource
     */
    function createTCP(&$class, $rFunc, $eFunc, $style, $cFunc, $persist = false, $bind = null) {
        return $this->createTCPb($class, $rFunc, $eFunc, $style, $cFunc, AF_INET, $persist, $bind);
    }

    /**
     * Create a IPv6 tcp socket,
     * style:
     *  0 - binary
     *  1 - data by line
     *
     * @param object   $class Class the callback functions are on
     * @param function $rFunc Read callback
     * @param function $eFunc Error callback
     * @param number   $style read style: 0 for binary, 1 for \r\n sections
     * @param function $cFunc Connected callback
     * @param bool     $persist Try reconnecting on disconnect
     * @param string   $bind  IP to bind to
     * @return boolean|resource
     */
    function createTCPv6(&$class, $rFunc, $eFunc, $style, $cFunc, $persist = false, $bind = null) {
        return $this->createTCPb($class, $rFunc, $eFunc, $style, $cFunc, AF_INET6, $persist, $bind);
    }

    function createTCPb(&$class, $rFunc, $eFunc, $style, $cFunc, $ipv, $persist = false, $bind = null) { // Makes a new TCP socket and returns it or false on error
        $fp = socket_create($ipv, SOCK_STREAM, SOL_TCP);

        if ($bind != null) {
            socket_bind($fp, $bind);
        }

        if (!$fp) {
            $this->dbg("Error creating socket: " . socket_strerror(socket_last_error()));
            return FALSE;
        }

        $ourAddr = null;
        $ourPort = null;

        socket_getsockname($fp, $ourAddr, $ourPort);

        $this->sockets[intval($fp)] = Array(
            'host'           => NULL,
            'bind'           => $bind,
            'ipv'            => $ipv,
            'port'           => NULL,
            'to'             => NULL,
            'readTO'         => NULL,
            'recon'          => false,
            'reconTime'      => time(),
            'disconnectTime' => time(),
            'sock'           => $fp,
            'class'          => $class,
            'rFunc'          => $rFunc,
            'style'          => $style,
            'eFunc'          => $eFunc,
            'cFunc'          => $cFunc,
            'lastReadTime'   => time(),
            'outq'           => '',
            'inq'            => '',
            'state'          => false,
            'persist'        => $persist,
            'rBytes'         => 0,
            'sBytes'         => 0,
            'ourAddr'        => $ourAddr,
            'ourPort'        => $ourPort,
        );

        $this->dbg("$fp created");
        return $fp;
    }

    /*
     * WHOOPS!? Already made a socket? add it using this function
     */

    function add($fp, $state, &$class, $rFunc, $eFunc, $style, $cFunc, $persist = false) {
        $this->sockets[intval($fp)] = Array(
            'host'           => NULL,
            'port'           => NULL,
            'to'             => NULL,
            'readTO'         => NULL,
            'recon'          => false,
            'reconTime'      => time(),
            'disconnectTime' => time(),
            'sock'           => $fp,
            'class'          => $class,
            'rFunc'          => $rFunc,
            'style'          => $style,
            'eFunc'          => $eFunc,
            'cFunc'          => $cFunc,
            'outq'           => '',
            'inq'            => '',
            'state'          => $state,
            'persist'        => $persist,
            'rBytes'         => 0,
            'sBytes'         => 0
        );

        $this->dbg("$fp added");
        return $fp; // return it back for good measure ;D
    }

    /* states:
     * 	 *  -1 = UDP socket
     *   0 = disconnected
     *   1 = connected
     *   2 = listening
     *   3 = accepting
     *   4 = connecting (not even used?)
     */

    /**
     * Run the select and call callbacks or write outq to sockets as needed
     */
    private function mSelect() {
        //check if socket should closeAfterSend
        foreach ($this->sockets as &$info) {
            if (isset($info['closeAfterSend']) && $info['closeAfterSend'] == true) {
                if (strlen($info['outq']) == 0) {
                    $this->mDead($info['sock'], 0);
                }
            }
        }

        //check for reconections
        foreach ($this->sockets as &$info) {
            if ($info['recon'] && time() > $info['reconTime'] + $info['disconnectTime']) {
                $info['recon'] = false;

                $this->dbg("reconnecting $info[sock] recon=false creating new socket");

                $this->destroy($info['sock']);
                $sock = $this->createTCPb($info['class'], $info['rFunc'], $info['eFunc'], $info['style'], $info['cFunc'], $info['ipv'], $info['persist'], $info['bind']);
                $this->setReconTime($sock, $info['reconTime']);
                $r    = $this->connect($sock, $info['host'], $info['port'], $info['to'], $info['readTO']);
                $this->dbg("this->connect returned $r");
                //$info['lastReadTime'] = time();
                //$this->mConnected($sock);
            }
        }

        //Check for connection timeouts
        foreach ($this->sockets as &$info) {
            if ($info['state'] == 4) {
                if (($info['connectTimeout'] + $info['connectTime']) < time()) {
                    $this->dbg("Inside select calling mDead for connectTimeout\n");
                    $this->mDead($info['sock'], SOCKET_ETIMEDOUT);
                }
            }
            if ($info['state'] == 1) {
                if ($info['readTO'] != NULL && ($info['readTO'] + $info['lastReadTime']) < time()) {
                    $info['lastReadTime'] = time(); // keep from spamming the func
                    $this->dbg("readTimeout on $info[sock] lastReadTime=time()");
                    $this->mDead($info['sock'], SOCKET_ETIMEDOUT);
                }
            }
        }

        $readsocks = Array();
        //Go through each socket look for data in its OutQ and add it to a write request array
        $writesocks = Array();
        $e = Array();

        foreach ($this->sockets as &$info) {
            if (!is_resource($info['sock'])) {
                $this->dbg("$info[sock] not a resource");
                continue;
            }

            $e[] = $info['sock'];
            if (!empty($info['outq']) || $info['state'] == 4) {
                $this->dbg("Adding writesock $info[sock] state==$info[state],outq==" . strlen($info['outq']));
                $writesocks[] = $info['sock'];
                /*
                 * The socket is non-blocking and the connection cannot be completed immediately.
                 * It is possible to select(2) or poll(2) for completion by selecting the socket
                 * for writing. After select indicates  writability,  use  getsockopt(2)  to read
                 * the SO_ERROR option at level SOL_SOCKET to determine whether connect completed
                 * successfully (SO_ERROR is zero) or unsuccessfully (SO_ERROR is one of the usual
                 * error codes listed here, explaining the reason for the failure).
                 */
            }

            if ($info['state'] == 1 || $info['state'] == 4 || $info['state'] == 2 || $info['state'] == -1) {
//                $this->dbg("Adding readsock $info[sock] state==$info[state],outq==" . strlen($outq));
                $readsocks[] = $info['sock'];
            }
        }

        if (empty($readsocks) && empty($writesocks)) {
            $this->dbg("select arrays empty sleep(1),return time");
            sleep($this->timeout); //We shouldn't ever run out of sockets..
            return;
        }

        $r = socket_select($readsocks, $writesocks, $e, $this->timeout, $this->timeoutu);

        $this->dbg("Selected " . count($readsocks) . " Readsocks, " . count($writesocks) . " Writesocks and " . count($e) . " Exceptions");

        if ($r == 0) {
            $this->dbg("Select returned 0");
            return;
        }

        if ($r === FALSE) {
            $this->dbg("socket_select() failed, reason: " . socket_strerror(socket_last_error()));
            sleep($this->timeout);
            return;
        }

//        foreach($this->sockets as &$info) {
//            $error = socket_last_error($info['sock']);
//            $this->dbg("Socket: $info[sock] err: [$error] " . socket_strerror($error) . "\n");
//        }
        //check if we were polling connection
        foreach ($writesocks as $idx => $sock) {
            $this->sockets[intval($sock)]['lastReadTime'] = time();

            if ($this->sockets[intval($sock)]['state'] == 4) {
                //remove from write
                unset($writesocks[$idx]);
                $error = socket_last_error($sock);
                $this->dbg("writesocks $sock state==4 sock_last_error = [$error] " . socket_strerror($error));

                if ($error == 0 || $error == SOCKET_EISCONN /* || $error == SOCKET_EINPROGRESS */) {
                    $this->mConnected($sock);
                } else {
                    $this->mDead($sock, $error);
                }
            }
        }

        //check if any listen sockets picked up a new connection
        foreach ($readsocks as $idx => $sock) {
            if ($this->sockets[intval($sock)]['state'] == 2) {
                $this->acceptTCP($sock);
                unset($readsocks[$idx]);
            }
        }

        //Now for some real fun, reading data!
        foreach ($readsocks as $idx => $sock) {
            $this->sockets[intval($sock)]['lastReadTime'] = time();
            //$this->dbg( "Processing read for $sock\n");
            //$this->dbg_dump($this->sockets);

            switch ($this->sockets[intval($sock)]['state']) {
                case -1:
                    $this->mReadFromSock($sock);
                    break;
                case 1: //connected
                    $this->dbg("running mReadSock on $sock");
                    $this->mReadSock($sock);
                    break;
                case 2: //listening
                    break;
                case 4: //connecting
                    $error = socket_last_error($sock);
                    $this->dbg("readsocks $sock state==4 sock_last_error = [$error] " . socket_strerror($error));

                    if ($error == 0 || $error == SOCKET_EISCONN /* || $error == SOCKET_EINPROGRESS */) {
                        $this->mConnected($sock);
                        unset($readsocks[$idx]);
                    } else {
                        $this->mDead($sock, $error);
                    }

                    $this->dbg("readsocks $sock connected");
                    break;
                default:
                    break;
            }
        }

        foreach ($writesocks as $sock) {
            $this->dbg("running mSendQ on $sock");
            $this->mSendQ($sock);
        }

        foreach ($e as $badsock) {
            $error = socket_last_error($badsock);
            socket_clear_error($sock);

            if ($error) {
                $this->dbg("exceptions $sock [$error] " . socket_strerror($error));
                $this->mDead($badsock, $error);
            }
        }

        $connected = 0;
        foreach ($this->sockets as &$info) {
            if ($info['state'] == 1)
                $connected++;
        }

        if ($connected == 0) {
            sleep(2);
        }
        //sleep(1);
    }

    /**
     * Helper function for writing debug messages
     * @param string $msg
     */
    private function dbg($msg) {
        return; //comment to see the msgs
        $fstack = xdebug_get_function_stack();
        $fs     = '';
        $d      = count($fstack);
        $i      = 0;

        foreach ($fstack as $f) {
            $i++;

            if (($f['class'] == 'Sockets' && $f['function'] == 'dbg')) {
                trim($fs, ',');
                break;
            }

            if (!array_key_exists('class', $f) || $f['class'] != 'Sockets')
                continue;

            if (array_key_exists('class', $f)) {
                $fs .= "$f[class]->";
            }

            $fs .= $f['function'] . ', ';
        }

        $fs = trim($fs);
        echo "sdbg (" . $fstack[($d - 2)]['function'] . "): " . trim($msg) . " From: $fs\n";
    }

    /**
     * Wrapper around var_dump so debugging messages can be disabled
     * @param unknown $msg
     */
    private function dbg_dump($msg) {
        return;
        var_dump($msg);
    }

    /**
     * Mark a socket as connected and call the callback
     * @param resource $sock
     */
    private function mConnected($sock) {
        socket_clear_error($sock);
        $this->dbg("setting connected, calling cFunc");

        $this->sockets[intval($sock)]['state']        = 1;
        $this->sockets[intval($sock)]['recon']        = false;
        $this->sockets[intval($sock)]['connectTime']  = time();
        $this->sockets[intval($sock)]['lastReadTime'] = time();

        //$this->dbg( "Socket $sock has connected!\n");
        $cFunc = $this->sockets[intval($sock)]['cFunc'];

        if (method_exists($this->sockets[intval($sock)]['class'], $cFunc)) {
            $this->sockets[intval($sock)]['class']->$cFunc($sock);
        } else {
            echo "Error $cFunc not in " . get_class($this->sockets[intval($sock)]['class']) . "\n";
        }

        //   $r = socket_read($sock, 4012, PHP_BINARY_READ);
        //   $this->dbg("dumping socket_read");
        //  $this->dbg_dump($r);
    }

    /**
     * Returns the port for our side of connection
     * @param resource $sock
     * @return string
     */
    public function getOurPort($sock) {
        $key = $this->findSock($sock);

        if ($key === false) {
            $this->dbg("$sock not found");
            return false;
        }

        if (!is_resource($sock)) {
            $this->dbg("$sock is not resource");
            return false;
        }

        if (array_key_exists('ourPort', $this->sockets[$key])) {
            if ($this->sockets[$key]['ourPort'] != null) {
                return $this->sockets[$key]['ourPort'];
            }
        }

        $addr = null;
        $port = null;

        socket_getsockname($sock, $addr, $port);

        $this->sockets[$key]['ourPort'] = $port;

        return $port;
    }

    /**
     * Returns the address for our side of connection
     * @param resource $sock
     * @return string
     */
    public function getOurAddr($sock) {
        $key = $this->findSock($sock);

        if ($key === false) {
            $this->dbg("$sock not found");
            return false;
        }

        if (!is_resource($sock)) {
            $this->dbg("$sock is not resource");
            return false;
        }

        if (array_key_exists('ourAddr', $this->sockets[$key])) {
            if ($this->sockets[$key]['ourAddr'] != null) {
                return $this->sockets[$key]['ourAddr'];
            }
        }

        $addr = null;
        $port = null;

        socket_getsockname($sock, $addr, $port);

        $this->sockets[$key]['ourAddr'] = $addr;

        return $addr;
    }

    /**
     * Returns the last error for the socket
     * @param resource $sock
     * @return Array Array($errno, $errstr)
     */
    public function getLastErr($sock) {
        $key = $this->findSock($sock);

        if ($key === false) {
            $this->dbg("$sock not found");
            return false;
        }

        if (!is_resource($sock)) {
            $this->dbg("$sock is not resource");
            return false;
        }

        $errno  = socket_last_error($sock);
        $errstr = socket_strerror($errno);

        return Array($errno, $errstr);
    }
    
    /**
     * Returns the IP/Host of the connection
     * @param resource $sock
     * @return string
     */
    public function getIP($sock) {
        if (array_key_exists(intval($sock), $this->sockets)) {
            return $this->sockets[intval($sock)]['host'];
        }
    }

    /**
     * Read from UDP socket and send directly to callback
     * @param resource $sock
     */
    private function mReadFromSock($sock) {
        if ($this->sockets[intval($sock)]['state'] != -1) {
            return;
        }

        $from_ip   = '';
        $from_port = '';
        $pack      = '';
        socket_recvfrom($sock, $pack, 4096, 0, $from_ip, $from_port);
        $rFunc     = $this->sockets[intval($sock)]['rFunc'];
        $this->sockets[intval($sock)]['class']->$rFunc($sock, $from_ip, $from_port, $pack);
    }

    /**
     * Read data waiting on a socket into its buffer, and then call its callback functions
     * @param resource $sock
     */
    private function mReadSock($sock) {
        //make sure its connected.
        if ($this->sockets[intval($sock)]['state'] == -1 || $this->sockets[intval($sock)]['state'] == 1 || $this->sockets[intval($sock)]['state'] == 4) {
            $data  = @socket_read($sock, 32096, PHP_BINARY_READ);
            $bytes = strlen($data);

            if ($data === '') {
                $this->dbg("data === '' :( socket_read, socket_write data ahead");
                $this->dbg_dump($data);
                //FUCKKKKK!
                //We aren't getting any error for some reason...
                $this->dbg_dump(@socket_write($sock, "\r\n"));
                //Lets make one ^
                $error = socket_last_error($sock);
                socket_clear_error($sock);
                $this->dbg("Error in mReadSock bytes: $bytes sock: $sock [$error] " . socket_strerror($error) . "\n");
                $this->mDead($sock, $error);
            } else {
                $this->dbg("socket_read data ahead");
                $this->dbg_dump($data);
                $this->sockets[intval($sock)]['lastReadTime'] = time();
                $this->sockets[intval($sock)]['inq'] .= $data;
                $this->sockets[intval($sock)]['rBytes'] += $bytes;
                $this->rBytes += $bytes;

                //if($bytes == 0) return;
                //Now Send our data to its class
                $rFunc = $this->sockets[intval($sock)]['rFunc'];

                if ($this->sockets[intval($sock)]['style'] == 0) { //binary
                    //just dump all that data
                    $this->sockets[intval($sock)]['class']->$rFunc($sock, $this->sockets[intval($sock)]['inq']);
                    $this->sockets[intval($sock)]['inq'] = '';
                    return;
                }

                if ($this->sockets[intval($sock)]['style'] == 1) { //split up by line
                    $split = "\r\n";
                } else {
                    $split = $this->sockets[intval($sock)]['style'];
                }

                $end = strpos($this->sockets[intval($sock)]['inq'], $split);

                while ($end !== false) {
                    $line = substr($this->sockets[intval($sock)]['inq'], 0, $end);

                    $this->sockets[intval($sock)]['inq'] = substr($this->sockets[intval($sock)]['inq'], $end + strlen($split));
                    $this->sockets[intval($sock)]['class']->$rFunc($sock, $line);

                    $end = strpos($this->sockets[intval($sock)]['inq'], $split);
                }
            }
        } else {
            $this->dbg("mRead called on non-connected socket\n");
        }
    }

    /**
     * Attept to send the data we have in a sockets buffer to it
     * @param resource $sock
     */
    private function mSendQ($sock) {
        $bytes = @socket_write($sock, $this->sockets[intval($sock)]['outq']);

        if ($bytes === false) {
            //error!
            $error = @socket_last_error($sock);
            @socket_clear_error($sock);
            $this->dbg("socket_write got error [$error] " . socket_strerror($error) . "\n");

            //Apparently some errors don't mean death, I use the linux error values here for the check
            // EAGAIN = 11 (try again)
            // EALREADY = 114 (operation already in progess)
            // EINPROGRESS = 115 (operation now in progess)
            if ($error != 11 && $error != 114 && $error != 115) {
                $this->mDead($sock, $error);
            }
            // EISCONN = 106 (already connected)
        } else {
            $this->sockets[intval($sock)]['lastWriteTime'] = time();
            $this->sockets[intval($sock)]['sBytes'] += $bytes;
            $this->sBytes += $bytes;

            //take off the outq the amount we wrote
            $this->sockets[intval($sock)]['outq'] = substr($this->sockets[intval($sock)]['outq'], $bytes);
            $this->dbg("wrote $bytes to $sock");
        }
    }

    /**
     * Run our select read data send data call callbacks etc..
     */
    public function process() {
        //First things first look for new data. . .
        $this->mSelect();
    }

    /**
     * Marks a socket to close after its outq is empty
     * @param resource $sock
     * @return boolean
     */
    function closeAfterSend($sock) {
        $sockkey = $this->findSock($sock);

        if ($sockkey === false) {
            $this->dbg("$sock not found");
            return false;
        }

        if (is_resource($sock)) {
            $this->dbg("socket_close_aftersend $sock");
            $this->sockets[$sockkey]['closeAfterSend'] = true;
        } else {
            $this->dbg("$sock is not resource");
        }
    }

    /**
     * just disconnects a socket
     * @param resource $sock
     * @return boolean
     */
    function close($sock) {
        if ($this->findSock($sock) === false) {
            $this->dbg("$sock not found");
            return false;
        }

        if (is_resource($sock)) {
            $this->dbg("socket_close $sock state=0 outq=''");
            socket_clear_error($sock);
            socket_close($sock);
        } else {
            $this->dbg("$sock is not resource");
        }

        $this->sockets[intval($sock)]['state'] = 0;
        $this->sockets[intval($sock)]['outq']  = '';
    }

    /**
     * completely removes the socket
     * @param resource $sock
     */
    function destroy($sock) {
        $this->close($sock);
        $this->dbg("unseting $sock from this->sockets");
        unset($this->sockets[intval($sock)]);
    }

    /**
     * Returns recon setting for socket
     * @param resource $sock
     * @return boolean
     */
    function getRecon($sock) {
        return $this->sockets[intval($sock)]['recon'];
    }

    /**
     * Set the reconTime for a socket
     * @param resource $sock
     * @param unknown $value
     * @return boolean|unknown
     */
    function setReconTime($sock, $value) {
        $key = $this->findSock($sock);

        if ($key === false) {
            return false;
        }

        $this->sockets[$key]['reconTime'] = $value;
        return $value;
    }

    /**
     * Sets the sockets persist
     * @param resource $sock
     * @param boolean $value
     * @return boolean
     */
    function setPersist($sock, $value) {
        $key = $this->findSock($sock);

        if ($key === false) {
            return false;
        }

        $this->sockets[$key]['persist'] = $value;
        return $value;
    }

    /**
     * call a socket dead
     * @param resource $sock
     * @param number $error
     */
    function mDead($sock, $error = 0) {
        if (!array_key_exists(intval($sock), $this->sockets)) {
            //Socket must have been removed by other means?
            return;
        }

        //$r = socket_write($sock, "\r\n");
        //$this->dbg("socket_write $sock return $r");
        //Report to our socket owner that its died
        $eFunc = $this->sockets[intval($sock)]['eFunc'];
        $class = $this->sockets[intval($sock)]['class'];

        if (is_object($class) && method_exists($class, $eFunc)) {
            $class->$eFunc($sock, $error);
        }

        if ($this->sockets[intval($sock)]['persist']) {
            //flag for reconnect
            $this->sockets[intval($sock)]['state']         = 0; // set state as disconnected first
            $this->sockets[intval($sock)]['outq']          = '';
            $this->sockets[intval($sock)]['recon']         = true;
            $this->sockets[intval($sock)]['diconnectTime'] = time();
            //$r = socket_read($sock, 4012, PHP_BINARY_READ);
            $this->dbg("clearing err");
            socket_clear_error($sock);
            $this->dbg("set $sock for reconnect state=0,outq='',recon=true\n");
        } else {
            $this->dbg("calling destroy $sock\n");
            $this->destroy($sock);
        }
    }

}

