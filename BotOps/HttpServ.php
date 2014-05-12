<?php
/*
 ***************************************************************************
 * HttpServ.inc
 *  Made to replace our http.so, uses our socket class to serv website data
 *  Supports XML-RPC
 ***************************************************************************
 */

/*
 * Basically All this should do is make a simple http server
 * all i really need it to support is xml-rpc right now...
 * so for xml-rpc it needs to
 * support post requests
 * if the requested procedure has been added
 * call the class-func and then return in xml-rpc encoded what that returns
 * close connection
 *  HEADER we should return
HTTP/1.0 200 OK
Date: Fri, 11 May 2012 05:32:49 GMT
Content-Length: 133
Content-Type: text/xml
Server: TwistedWeb/10.1.0
 * Header we might get
POST / HTTP/1.0
Host: localhost:13379
Content-length: 260
Content-type: text/xml
 */

class HttpServ {
    //The socket we create to listen for connections
    public $sock;
    //The sockets class we are givin
    public $sockets;
    public $lPort;
    public $lHost;
    
    private $rawData;

    /*
     * List of procedures added
     * $procs = Array(
     *      'procname' => Array(c'cbClass' => class,'cbFunc'=>func)
     *  )
     */
    public $procs = Array();

    /*
     * Array of clients connected
     * Array(
     *      intval($sock) = Array(
     *          'sock',     socket for connection
     *          'iBuffer',  data read so far
     *          'header',   null if not read yet otherwise header array
     *          'ip',       ip connection is from
     *          'rDone',    finished reading all data
     *          'oBuffer',  data to send back
     *          'closeMe',  if true close the socket when oBuffer is empty
     *      )
     * )
     */
    public $clients = Array();
    
    //are we serving for xmlrpc
    public $XMLPRC = true;
    function __construct(&$sockets, $lHost, $lPort, $xmlRpc = true) {
        $this->sockets = &$sockets;
        $this->sock = NULL;
        $this->lHost = $lHost;
        $this->lPort = $lPort;
        $this->XMLRPC = $xmlRpc;
    }
    
    function stop() {
        $this->sockets->destroy($this->sock);
    }

    function  __destruct() {
        foreach($this->clients as $c) {
            $this->sockets->close($c['sock']);
        }
        unset($this->clients);
        unset($this->procs);
        echo "HTTPSERV OBJECT IS BEING DESTROYED yay?!\n";
    }
    
    function setRPC($method, &$cbClass, $cbFunc) {
        $this->procs[$method] = Array('cbClass' => &$cbClass, 'cbFunc' => $cbFunc);
    }
    
    function chgClass(&$old, &$new) {
        if($old == null) return;
        foreach ($this->procs as $idx => $val) {
            if(get_class($val['cbClass']) == get_class($old)) {
                $this->procs[$idx]['cbClass'] = &$new;
            }
        }
    }
    
    function init() {
        $this->sock = $this->sockets->createTCPListen($this, 'sRead', 'sErr', 'sCon', $this->lHost, $this->lPort, $style = 0);
    }
    
    function sErr($sock, $err) {
        //remove the client
        if(isset($this->clients[intval($sock)])) {
            unset($this->clients[intval($sock)]);
            echo "HttpServ connection for " . intval($sock) . " closed ($err) removing\n";
        } else {
            echo "HttpServ connection for " . intval($sock) . " closed ($err) but not in client list?\n";
        }
        
    }
    
    function sCon($sock, $ip) {
        $this->clients[intval($sock)] = Array(
            'sock' => $sock,
            'iBuffer' => '',
            'header' => null,
            'ip' => $ip,
            'rDone' => false,
        );
    }
    
    function sRead($sock, $data) {
        if(!isset($this->clients[intval($sock)])) {
            echo "HTTPServ got data from unknown socket";
            return;
        } else {
            $si = intval($sock);
            echo "HttpServ incoming data($si):\n";
            $data_dump = str_replace("\r", '\r', $data);
            $data_dump = str_replace("\n", '\n'."\n", $data_dump);
            echo "INCOMING:\n" . $data_dump . "\nEND.\n";
        }
        $this->clients[intval($sock)]['iBuffer'] .= $data;
        $iBuffer = $this->clients[intval($sock)]['iBuffer'];
        //we shouldn't need more then 9mb
        if (strlen($iBuffer) > 9000000) {
            $this->generateFault(13, "OH MY GOD!!! THE HEADER! ITS OVER 9000(kb)!!!\n");
            $this->sendResponse($sock, $fault);
        }
        //Check if header has finished
        if(strpos($iBuffer, "\r\n\r\n")) {
            $moo = $this->parseHeader($iBuffer);
            echo "HttpServ recv header!\n";
            var_dump($moo[0]);
            echo "Actual datalen of content: " . strlen($moo[1]) . "\n";
            $this->clients[intval($sock)]['iBuffer'] = $moo[1];
            $this->clients[intval($sock)]['header']= $moo[0];
        }
        $header = $this->clients[intval($sock)]['header'];
        if($header != null) {
            if(!isset($header['content-length'])) {
                $fault = $this->generateFault(10, 'Malformed header: please include Content-length');
                $this->sendResponse($sock, $fault);
            } else {
                if(strlen($this->clients[intval($sock)]['iBuffer']) >= $header['content-length']) {
                    $this->clients[intval($sock)]['rDone'] = true;
                    $this->processRequest($sock, $this->clients[intval($sock)]['iBuffer']);
                }
            }
        }
    }
    
    public function generateFault($code, $string) {
        $myArray = array ("faultCode"=> $code ,"faultString"=>$string);
        $xml = xmlrpc_encode($myArray);
        $xml = explode("\n", $xml);
        $xmlTag = array_shift($xml);
        $xml = implode("\n", $xml);
        $xml = "$xmlTag\n<methodResponse>\n$xml</methodResponse>\n";
        return $xml;
    }
    
    //Send a response back to the socket and mark it to close
    public function sendResponse($sock, $data) {
        //need to generate a header
        $header =
"HTTP/1.0 200 OK
Date: ".date('r')."
Content-Length: ".strlen($data)."
Content-Type: text/xml
Server: BotOps SimpleHttp\r\n\r\n";
        $this->sockets->send($sock, $header.$data);
        $this->sockets->closeAfterSend($sock);
        //the socket class should notify us after the socket has been closed
        //we can then unset it there
    }
    
    public function findRPC($method) {
        if(isset($this->procs[$method])) {
            return $this->procs[$method];
        } else {
            return false;
        }
    }
    
    public function processRequest($sock, $xml) {
        $xml = ReEncode($xml);
        $method = null;
        $params = xmlrpc_decode_request($xml, $method); 
        $method = ReDeEncode($method);
        $params = ArReDeEncode($params);
        $proc = $this->findRPC($method);
        if($proc == false) {
            //send back bad request
            $fault = $this->generateFault(14, "Unknown Function: that method doesn't exist");
            $this->sendResponse($sock, $fault);
        } else {
            $response = $proc['cbClass']->$proc['cbFunc']($params);
            $response = xmlrpc_encode_request(null, $response);
            $this->sendResponse($sock, $response);
        }
    }
    
    function parseHeader($data) {
        $data = explode("\r\n\r\n", $data);
        $headerData = array_shift($data);
        $content = implode("\r\n", $data);
        $headerData = explode("\n", $headerData);
        $header = Array();
        $n = 0;
        foreach($headerData as $line) {
            if($n == 0) {
                $line = explode(' ', $line);
                $header['http-action'] = $line[0];
                $header['path'] = $line[1];
                $header['http-version'] = trim($line[2]);
            } else {
                $line = explode(':', $line);
                $line[1] = trim($line[1]);
                $header[strtolower($line[0])] = $line[1];
            }
            $n++;
        }
        return Array($header, $content);
    }
}
 
?>
