<?PHP
/***************************************************************************
 * BotNetwork Bots IRC Framework
 * Http://www.botnetwork.org/
 * Contact: irc://irc.gamesurge.net/bots 
 *************************************************************************** 
 * Copyright (C) 2009 BotNetwork
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 ***************************************************************************
 * Http.inc
 *  Made to replace our http.so, uses our socket class to fetch website data
 *  then sends results back through a callback function
 ***************************************************************************/


class Http {
    private $sock;
    private $sockets;

    private $rawData;

    private $pClass;
    private $pFunc;

    private $qtype;
    private $pquery;
    
    private $XMLRPC = false;
    private $OMGAllDone = false;
    
    private $finished;
    public $vstore; //store variables to send to callback

    function __construct(&$sockets, &$pClass, $pFunc, $v = null, $timeout = 5) {
        $this->sockets = $sockets;
        $this->sock = NULL;
        $this->pClass = &$pClass;
        $this->pFunc = $pFunc;
        $this->timeout = $timeout;
        $this->vstore = $v;

        $this->finished = false;
    }

    function  __destruct() {
        echo "HTTP OBJECT IS BEING DESTROYED yay?!\n";
    }

    public $XMLPRC = false;
    //query should just be an array
    function xmlrpcQuery($url, $v, $method, $parameters) {
        $this->XMLRPC = true;
        $xml = xmlrpc_encode_request($method , $parameters);
        $this->postQuery($url, $v, $xml);
    }
    
    function postQuery($url, $v, $query) {
        $this->qtype = 'post';
        $this->pquery = $query;
        if($this->sock != NULL) return;

        $this->sock = $this->sockets->createTCP($this, 'readData', 'sockErr', 0, 'sockCon', false);

        if($this->sock === FALSE) die("couldn't make http sock.");

        $this->vstore = $v;

        $url = parse_url($url);

        if (!array_key_exists('path', $url) || $url['path'] == '') {
            $url['path'] = "/";
        }

        if (!array_key_exists('port', $url)) {
            $url['port'] = 80;
        }

        if(array_key_exists('query', $url)) {
            $postQuery = "POST " . $url['path'] . '?' . $url['query'] . " HTTP/1.0\r\n";
        } else {
            $postQuery = "POST " . $url['path'] . " HTTP/1.0\r\n";
        }
        $postQuery .= "Host: " . $url['host'] . ':' . $url['port'] . "\r\n";
        
        $postQuery .= "Content-length: " . strlen($query) . "\r\n";
        
        if($this->XMLRPC) {
            $postQuery .= "Content-type: text/xml\r\n";
        } else {
            $postQuery .= "Content-type: application/x-www-form-urlencoded\r\n";
        }
        //$query = urlencode($query);

        $postQuery .= "\r\n" . $query;

        file_put_contents('httppostsend', $postQuery);
        
        $this->sockets->connect($this->sock, $url['host'], $url['port'], $this->timeout, $this->timeout);
        $this->query = $postQuery;
        
    }

    public $query;
    function getQuery($url, $v) {
        $this->qtype = 'get';
        if($this->sock != NULL) return;

        $this->sock = $this->sockets->createTCP($this, 'readData', 'sockErr', 0, 'sockCon', false);

        if($this->sock === FALSE) die("couldn't make http sock.");

        $this->vstore = $v;

        $url = parse_url($url);

        if (!array_key_exists('path', $url) || $url['path'] == '') {
            $url['path'] = "/";
        }

        if (!array_key_exists('port', $url)) {
            $url['port'] = 80;
        }

        if(array_key_exists('query', $url)) {
            if($url['query'] != '') {
                $urlquery = '?' . $url['query'];
            } else {
                $urlquery = '';
            }
            $getQuery = "GET " . $url['path'] . $urlquery . " HTTP/1.1\r\nUser-Agent: Mozilla\r\n";
        } else {
            $getQuery = "GET " . $url['path'] . " HTTP/1.1\r\nUser-Agent: Mozilla\r\n";
        }
        if($url['port'] == 80) {
            $getQuery .= "Host: " . $url['host'] . "\r\n";
        } else {
            $getQuery .= "Host: " . $url['host'] . ':' . $url['port'] . "\r\n";
        }
        $getQuery .= "Connection: close\r\n";
        $getQuery .= "\r\n";

        $this->sockets->connect($this->sock, $url['host'], $url['port'], $this->timeout, $this->timeout);
        $this->query = $getQuery;
    }

    //Try to parse the header and get datalen
    var $chunkInfo = Array();
    var $completed;
    function readData($sock, $data) {
        //file_put_contents('http', $data, FILE_APPEND);
        $this->rawData .= $data;
        if(strlen($this->rawData) > 1024) {
            $this->checkIfStream();
        }
        if(strlen($this->rawData) > 9000000) {
            $this->closeConnection();
        }
    }

    
    /*
     * ShoutCast streams don't send HTTP headers
     * ICY 200 OK
      icy-notice1:<BR>This stream requires <a href="http://www.winamp.com/">Winamp</a><BR>
      icy-notice2:SHOUTcast Distributed Network Audio Server/posix(linux x86) v2.0.0.29<BR>
      icy-name:Loveline Hosted by HostFace.net
      icy-genre:Talk
      icy-url:http://www.HostFace.net
      content-type:audio/mpeg
      icy-pub:1
      icy-br:64

     */
    function checkIfStream() {
        $headerData = explode("\n", $this->rawData);
        $n = 0;
        $header = Array();
        foreach($headerData as $line) {
            $line = trim($line);
            if($line == '') {
                break;
            }
            if($n == 0) {
                $line = explode(' ', $line);
                $header['http-version'] = $line[0];
                $header['status'] = $line[1];
                $header['message'] = trim($line[2]);
            } else {
                $line = explode(':', $line);
                $line[1] = trim($line[1]);
                $header[$line[0]] = $line[1];
            }
            $n++;
        }
        if($header['http-version'] == 'ICY') {
            echo "UH OH HTTP is getting an ICY\n";
            var_dump($header);
            echo "Terminating connection. . .\n";
            $this->closeConnection();
        }
    }
    
    function closeConnection() {
        $this->sockets->close($this->sock);
    }
    
    function sockCon($sock) {
        $this->sockets->send($this->sock, $this->query);
    }

    function sockErr($sock, $error) {
        if($error == 0) {
            $this->parseData();
            return;
        }
        $f = $this->pFunc;
        if ($this->XMLRPC) {
            $this->pClass->$f(Array(0=>$error,1=>socket_strerror($error),'HTTPHASERROR'=>'HTTPERROR'), $this->vstore, null);
        } else {
            $this->pClass->$f(Array(0=>$error,1=>socket_strerror($error),'HTTPHASERROR'=>'HTTPERROR'), $this->vstore);
        }
        echo "HTTP GOT ERROR $error ".socket_strerror($error)."\n";
    }
    //for the moment i split this up so we can read all data
    //this is bad if you try to get huge files needs fixed

    
    function parseData() {
        $data = $this->rawData;
        file_put_contents('weather3', $data);
        echo "HTTP closed datalen: " . strlen($data) . "\n";
        if($this->finished) return;
        
        //Check if we have the whole header yet
        $p = strpos($this->rawData, "\r\n\r\n");
        if($p !== FALSE && empty($this->header)) {
            $headerData = substr($this->rawData, 0, $p);
            $this->parseHeader($headerData);
            var_dump($this->header);
            $this->rawData = substr($this->rawData, $p + 4);
        }

        if(empty($this->header)) {
            echo "HTTP says OMG no header\n";
            return; //Do nothing until we have the headers
        }
        $status = $this->header['status'];
        $message = $this->header['message'];
        // 200 = OK
        // 301 Moved Permanently
        // 302 Moved Temporarily
        // 303 See Other (HTTP 1.1 only) The resource has moved to another URL (given by the Location: response header)
        if($status != '200' && $status != '302' && $status != '301' && $status != '303') {
            $f = $this->pFunc;
            if ($this->XMLRPC) {
                $this->pClass->$f(Array(0=>$status, 1=>$message,'HTTPHASERROR'=>'HTTPERROR'), $this->vstore, null);
            } else {
                $this->pClass->$f(Array(0=>$status, 1=>$message,'HTTPHASERROR'=>'HTTPERROR'), $this->vstore);
            }
            $data_dump = str_replace("\r", '\r', $this->query);
            $data_dump = str_replace("\n", '\n'."\n", $data_dump);
            echo "HTTP GOT ERROR $status $message\nQuery Sent:\n" . $data_dump;
            $this->closeConnection();
            return;
        }

        //if transfer-encoding is chunked recv chunks until zero len chunk
        // chunks come in like hex-len(in text)\r\ndata\r\n

        if(array_key_exists('Transfer-Encoding', $this->header)) {
            if($this->header['Transfer-Encoding'] == 'chunked') {
                if(!array_key_exists('chunkLen', $this->chunkInfo)) {
                    $this->chunkInfo['chunkLen'] = 0;
                }
                $done = false;
                while(!$done) {
                    if($this->chunkInfo['chunkLen'] == 0) { //get a chunkLen
                        $this->rawData = ltrim($this->rawData, "\r\n");
                        $p = strpos($this->rawData, "\r\n");
                        if($p !== FALSE) {
                            $hex = substr($this->rawData, 0, $p);
                            $this->chunkInfo['chunkLen'] = hexdec($hex);
                            $this->rawData = substr($this->rawData, $p + 2);
                            if($this->chunkInfo['chunkLen'] == 0) {
                                //finished reading data
                                $done = true;
                                $this->finished = true;
                            }
                        } else {
                            $done = true; // not enough data
                        }
                    }
                    if(strlen($this->rawData) > 0) {
                        if(strlen($this->rawData) >= $this->chunkInfo['chunkLen']) {
                            $this->completed .= substr($this->rawData, 0, $this->chunkInfo['chunkLen']);
                            $this->rawData = substr($this->rawData, $this->chunkInfo['chunkLen']);
                            $this->chunkInfo['chunkLen'] = 0;
                        } else {
                        //decrease amount left on chunk
                            $this->chunkInfo['chunkLen'] =  $this->chunkInfo['chunkLen'] - strlen($this->rawData);
                            $this->completed .= $this->rawData;
                            $this->rawData = '';
                            $done = true;
                        }
                    }
                    if($this->finished) {
                        $this->finished($this->completed);
                    }
                }
            }
        } else {
            if(array_key_exists('Content-Length', $this->header)) {
                if(strlen($this->rawData) >= $this->header['Content-Length']) {
                    $this->finished($this->rawData);
                    $this->rawData = '';
                }
            } else {
                $this->finished($this->rawData);
                $this->rawData = '';
            }
        }
        
        //Hopefully this will help keep things clean
        if($this->OMGAllDone) {
            $this->closeConnection();
        }
    }

    function finished($data) {
        $moved = strpos($data, '<p>The document has moved <a href="');
        if($moved === FALSE && !empty($this->header['Location'])) {
            $loc = $this->header['Location'];
            $moved = true;
        }
        $status = $this->header['status'];
        if(($status == '302' || $status == '301' || $status == '303') && !$this->XMLPRC && $moved !== false) {
            //handle redirections
            //<p>The document has moved <a href="http://www.youtube.com/watch?v=nccpL3lPYYk&amp;feature=youtu.be">here</a>
            //i think we just need to handle that response for now..
            //basically we make a whole new http with same settings we have
            if(!$loc) {
                $startText = '<p>The document has moved <a href="';
                $endText = '">here</a>';
                $start = strpos($data, $startText) + strlen($startText);
                $end = strpos($data, $endText, $start);
                $url = substr($data, $start, $end - $start);
            } else {
                $url = $loc;
            }
            
            $redirect = new Http($this->sockets, $this->pClass, $this->pFunc, $this->vstore, $this->timeout);
            if($this->qtype == 'get') {
                $redirect->getQuery($url, $this->vstore);
            }
            if($this->qtype == 'post') {
                $redirect->postQuery($url, $this->vstore, $this->pquery);
            }
            $this->OMGALLDone = true;
            echo "Http Redirected to $url\n";
            return;
        }
        $f = $this->pFunc;
        echo "Http finished!!\n";
        $this->OMGAllDone = true;
        if ($this->XMLRPC) {
            $super = ReEncode($data);
            $xmlres = xmlrpc_decode($super);
            $xmlres = ArReDeEncode($xmlres);
            $this->pClass->$f($data, $this->vstore, $xmlres);
        } else {
            $this->pClass->$f($data, $this->vstore);
        }
    }

    public $header = Array();
    function parseHeader($headerData) {
        $headerData = explode("\n", $headerData);
        $n = 0;
        foreach($headerData as $line) {
            $line = trim($line, "\r");
            if($n == 0) {
                $line = explode(' ', $line);
                $this->header['http-version'] = $line[0];
                $this->header['status'] = $line[1];
                $this->header['message'] = trim(arg_range($line, 2, -1));
            } else {
                $line = explode(':', $line);
                $line[1] = trim($line[1]);
                $hname = array_shift($line);
                if($hname != 'status') {
                    $this->header[$hname] = trim(implode(':', $line));
                }
//                $this->header[$line[0]] = $line[1];
            }
            $n++;
        }
    }
}
 
