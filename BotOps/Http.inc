<?PHP

/* * *************************************************************************
 * BotNetwork Bots IRC Framework
 * Http://www.botnetwork.org/
 * Contact: irc://irc.gamesurge.net/bots 
 * ************************************************************************** 
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
 * **************************************************************************
 * Http.inc
 *  Made to replace our http.so, uses our socket class to fetch website data
 *  then sends results back through a callback function
 * ************************************************************************* */

require_once 'Tools/Tools.php';

class Http
{

    /**
     * Socket resource
     * @var Resoruce
     */
    private $sock;

    /**
     * Sockets class
     * @var Sockets
     */
    private $sockets;

    /**
     * If we will allow data streaming.
     * @var bool
     */
    private $streaming = false;

    /**
     * Array of headers to be sent to the remote host
     * @var Array
     */
    private $sendHeaders = Array();

    /**
     * The header recieved from the remote host
     * @var Array
     */
    public $header = Array();

    /**
     * The data received to be parsed
     * @var string
     */
    private $rawData;

    /**
     * Callback class
     * @var Object
     */
    private $pClass;

    /**
     * Callback function
     * @var string
     */
    private $pFunc;

    /**
     * Type of query performed
     * @var string
     */
    private $qtype;

    /**
     * Query data sent from POST request
     * @var string
     */
    private $pquery;

    /**
     * If we are doing a XMLRPC call
     * @var bool
     */
    private $XMLRPC = false;

    /**
     * If the connection should be closed when we enter parseData again?
     * @var bool
     */
    private $OMGAllDone = false;

    /**
     * Looks like its if we've finished reading data
     * @var bool
     */
    private $finishedReading;

    /**
     * Variable storage to be passed back to the callback function
     * @var mixed
     */
    public $vstore; //store variables to send to callback
    
    /**
     * Variable storage for initernal use to track cookies and redirects
     * @var Array
     */
    public $internals; //store variables to send to callback

    /**
     * Keeps track of chunk length when we are recieving in chunked mode
     * @var mixed
     */
    private $chunkLen = false;

    /**
     * Filled with data when recieving in chunked mode
     * @var string
     */
    private $completed;

    /**
     * Query to send upon socket connection
     * @var string
     */
    private $query;
    
    /**
     * Hostname and port (if applicable) of the website being queried
     * @var string
     */
    private $host;

    function __construct(&$sockets, &$pClass, $pFunc, $v = null, $timeout = 5)
    {
        $this->sockets = $sockets;
        $this->sock    = NULL;
        $this->pClass  = &$pClass;
        $this->pFunc   = $pFunc;
        $this->timeout = $timeout;
        $this->vstore  = $v;

        $this->finishedReading = false;
    }

    function __destruct()
    {
        echo "HTTP OBJECT IS BEING DESTROYED yay?!\n";
    }

    /**
     * Called if this connection should act as a stream
     */
    public function SetStreaming()
    {
        $this->streaming = true;
    }

    /**
     * Set a header to be sent in the http query
     * @param string $name
     * @param string $val
     */
    public function SetHeader($name, $val)
    {
        $n = get_akey_nc($name, $this->sendHeaders);
        if ($n != null) {
            $name = $n;
            echo "Http: Warning: header $name already set updating it";
        }
        $this->sendHeaders[$name] = $val;
    }

    /**
     * Specify a default setting for a header, if it was already created, then
     * it will be ignored
     * @param string $name
     * @param string $val
     */
    private function defaultHeader($name, $val)
    {
        $n = get_akey_nc($name, $this->sendHeaders);
        if ($n != null) {
            return;
        }
        $this->sendHeaders[$name] = $val;
    }

    //parameters should just be an array
    public function xmlrpcQuery($url, $v, $method, $parameters)
    {
        $this->XMLRPC = true;
        $xml          = xmlrpc_encode_request($method, $parameters);
        $this->postQuery($url, $v, $xml);
    }

    public function postQuery($url, $v, $query, Array $internals = Array())
    {
        if ($this->sock != NULL) {
            echo "Http: Warning: Additional query request being ignored.";
            return;
        }
        $this->vstore = $v;
        $this->internals = $internals;
        $this->qtype  = 'post';
        $this->pquery = $query;

        $this->sock = $this->sockets->createTCP($this, 'readData', 'sockErr', 0,
                                                'sockCon', false);
        if ($this->sock === FALSE) {
            echo "Http: Error: Couldn't make http sock.";
            return;
        }

        $url = parse_url($url);

        if (!array_key_exists('path', $url) || $url['path'] == '') {
            $url['path'] = "/";
        }

        if (!array_key_exists('port', $url)) {
            $url['port'] = 80;
            $this->host = $url['host'];
        } else {
            $this->host = $url['host'] . ':' . $url['port'];
        }

        if (array_key_exists('query', $url)) {
            $postQuery = "POST " . $url['path'] . '?' . $url['query'] . " HTTP/1.0\r\n";
        } else {
            $postQuery = "POST " . $url['path'] . " HTTP/1.0\r\n";
        }

        $this->defaultHeader('Host', $url['host'] . ':' . $url['port']);
        $this->defaultHeader('Content-length', strlen($query));
        
        if ($this->XMLRPC) {
            $this->defaultHeader('Content-type', 'text/xml');
        } else {
            $this->defaultHeader('Content-type',
                                 'application/x-www-form-urlencoded');
        }
        //$query = urlencode($query);

        $postQuery .= $this->headerString();
        $postQuery .= "\r\n" . $query;

        //for debugging
        file_put_contents('httppostsend', $postQuery);

        $this->sockets->connect($this->sock, $url['host'], $url['port'],
                                $this->timeout, $this->timeout);
        $this->query = $postQuery;
    }

    public function getQuery($url, $v, Array $internals = Array())
    {
        if ($this->sock != NULL) {
            echo "Http: Warning: Additional query request being ignored.";
            return;
        }
        $this->vstore = $v;
        $this->internals = $internals;
        $this->qtype  = 'get';

        $this->sock = $this->sockets->createTCP($this, 'readData', 'sockErr', 0,
                                                'sockCon', false);
        if ($this->sock === FALSE) {
            echo "Http: Error: Couldn't make http sock.";
            return;
        }

        $this->defaultHeader('User-Agent', 'Mozilla');

        $url = parse_url($url);

        if (!array_key_exists('path', $url) || $url['path'] == '') {
            $url['path'] = "/";
        }

        if (!array_key_exists('port', $url)) {
            $url['port'] = 80;
            $this->host = $url['host'];
        } else {
            $this->host = $url['host'] . ':' . $url['port'];
        }

        if (array_key_exists('query', $url)) {
            if ($url['query'] != '') {
                $urlquery = '?' . $url['query'];
            } else {
                $urlquery = '';
            }
            $getQuery = "GET " . $url['path'] . $urlquery . " HTTP/1.1\r\n";
        } else {
            $getQuery = "GET " . $url['path'] . " HTTP/1.1\r\n";
        }

        if ($url['port'] == 80) {
            $this->defaultHeader('Host', $url['host']);
        } else {
            $this->defaultHeader('Host', $url['host'] . ':' . $url['port']);
        }
        $this->defaultHeader('Connection', 'close');

        if (array_key_exists('COOKIE', $this->internals)) {
            $this->defaultHeader('Cookie', $this->internals['COOKIE']);
        }
        if (array_key_exists('REFERER', $this->internals)) {
            $this->defaultHeader('Referer', $this->internals['REFERER']);
        }

        $getQuery .= $this->headerString();
        $getQuery .= "\r\n";

        $this->sockets->connect($this->sock, $url['host'], $url['port'],
                                $this->timeout, $this->timeout);
        $this->query = $getQuery;
        file_put_contents('httppostsend', $getQuery);
    }

    /**
     * Get the headers to sent as a string
     * @return string
     */
    private function headerString()
    {
        $output = '';
        foreach ($this->sendHeaders as $key => $val) {
            if ($val == null) {
                continue;
            }
            $output .= "$key: $val\r\n";
        }
        return $output;
    }

    public function readData($sock, $data)
    {
        //file_put_contents('http', $data, FILE_APPEND);
        $this->rawData .= $data;
        
        if (empty($this->header)) {
            $p = strpos($this->rawData, "\r\n\r\n");
            if ($p !== FALSE) {
                $this->parseHeader($p);
                if (!$this->streaming && !empty($this->header)) {
                    $this->checkIfStream();
                }
                //Debug line
                //var_dump($this->header);
            }
        } else {
            if($this->streaming) {
                $f = $this->pFunc;
                $this->pClass->$f($this->rawData, $this->vstore);
                $this->rawData = '';
            }
        }

        if (!$this->streaming && strlen($this->rawData) > 9000000) {
            $this->closeConnection();
        }
    }

    /*
     * ShoutCast streams send HTTP headers like this
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

    private function checkIfStream()
    {
        if ($this->getHeaderVal('http-version') == 'ICY') {
            echo "UH OH HTTP is getting an ICY\n";
            echo "Terminating connection. . .\n";
            $this->closeConnection();
        }
    }

    /**
     * Parse the headers from incoming data
     * @param int $p End position of header data
     */
    private function parseHeader($p)
    {
        $headerData = substr($this->rawData, 0, $p);
        $headerData = explode("\n", $headerData);
        $n          = 0;
        foreach ($headerData as $line) {
            $line = trim($line, "\r");
            if ($n == 0) {
                $line                         = explode(' ', $line);
                $this->header['http-version'] = $line[0];
                $this->header['status']       = $line[1];
                $this->header['message']      = trim(arg_range($line, 2, -1));
                $n++;
                continue;
            }
            $line    = explode(':', $line);
            $line[1] = trim($line[1]);
            $hname   = array_shift($line);
            $this->header[$hname] = trim(implode(':', $line));
        }
        $this->rawData = substr($this->rawData, $p + 4);
    }

    /**
     * Get the value of one of the headers received
     * @param string $key
     * @return string
     */
    public function getHeaderVal($key)
    {
        $k = get_akey_nc($key, $this->header);
        if ($k == null) {
            return null;
        }
        return $this->header[$k];
    }

    /**
     * Force the socket connection to be closed
     */
    public function closeConnection()
    {
        $this->sockets->close($this->sock);
    }

    /**
     * Our callback for socket connected
     * @param Resource $sock
     */
    public function sockCon($sock)
    {
        $this->sockets->send($this->sock, $this->query);
    }

    /**
     * Our callback for socket errors
     * @param Resource $sock
     * @param int $error
     * @return void
     */
    public function sockErr($sock, $error)
    {
        //Socket closed normally
        if ($error == 0) {
            $this->parseData();
            return;
        }

        $f = $this->pFunc;
        if ($this->XMLRPC) {
            $this->pClass->$f(Array(0 => $error, 1 => socket_strerror($error), 'HTTPHASERROR' => 'HTTPERROR'),
                                                                      $this->vstore,
                                                                      null);
        } else {
            $this->pClass->$f(Array(0 => $error, 1 => socket_strerror($error), 'HTTPHASERROR' => 'HTTPERROR'),
                                                                      $this->vstore);
        }
        echo "HTTP GOT ERROR $error " . socket_strerror($error) . "\n";
    }

    //for the moment i split this up so we can read all data
    //this is bad if you try to get huge files needs fixed

    /**
     * A huge mess
     * @return type
     */
    private function parseData()
    {
        //Debug line
        //file_put_contents('weather3', $data);
        echo "HTTP closed datalen: " . strlen($this->rawData) . "\n";
        if ($this->finishedReading) {
            return;
        }

        //Check if we have the whole header yet
        if (empty($this->header)) {
            echo "HTTP says OMG no header\n";
            return; //Do nothing until we have the headers
        }

        $status  = $this->getHeaderVal('status');
        $message = $this->getHeaderVal('message');
        // 200 = OK
        // 301 Moved Permanently
        // 302 Moved Temporarily
        // 303 See Other (HTTP 1.1 only) The resource has moved to another URL 
        //               (given by the Location: response header)
        if ($status != '200' && $status != '302' && $status != '301' && $status != '303') {
            $f = $this->pFunc;
            if ($this->XMLRPC) {
                $this->pClass->$f(Array(0 => $status, 1 => $message, 'HTTPHASERROR' => 'HTTPERROR'),
                                  $this->vstore, null);
            } else {
                $this->pClass->$f(Array(0 => $status, 1 => $message, 'HTTPHASERROR' => 'HTTPERROR'),
                                  $this->vstore);
            }
            $data_dump = str_replace("\r", '\r', $this->query);
            $data_dump = str_replace("\n", '\n' . "\n", $data_dump);
            echo "HTTP GOT ERROR $status $message\nQuery Sent:\n" . $data_dump;
            $this->closeConnection();
            return;
        }

        //if transfer-encoding is chunked recv chunks until zero len chunk
        $trEnc = $this->getHeaderVal('transfer-encoding');
        if ($trEnc != null && $trEnc == 'chunked') {
            $this->parseChunked();
        } else {
            $cLen = $this->getHeaderVal('content-length');
            if ($cLen != null) {
                if (strlen($this->rawData) >= $cLen) {
                    $this->finished($this->rawData);
                    $this->rawData = '';
                }
            } else {
                $this->finished($this->rawData);
                $this->rawData = '';
            }
        }

        //Hopefully this will help keep things clean
        if ($this->OMGAllDone) {
            $this->closeConnection();
        }
    }

    private function parseChunked()
    {
        // chunks come in like hex-len(in text)\r\ndata\r\n
        if ($this->chunkLen === FALSE) {
            $this->chunkLen = 0;
        }
        $done = false;
        while (!$done) {
            if ($this->chunkLen == 0) { //get a chunkLen
                $this->rawData = ltrim($this->rawData, "\r\n");
                $p             = strpos($this->rawData, "\r\n");
                if ($p !== FALSE) {
                    $hex            = substr($this->rawData, 0, $p);
                    $this->chunkLen = hexdec($hex);
                    $this->rawData  = substr($this->rawData, $p + 2);
                    if ($this->chunkLen == 0) {
                        //finished reading data
                        $done                  = true;
                        $this->finishedReading = true;
                    }
                } else {
                    $done = true; // not enough data
                }
            }
            if (strlen($this->rawData) > 0) {
                if (strlen($this->rawData) >= $this->chunkLen) {
                    $this->completed .= substr($this->rawData, 0,
                                               $this->chunkLen);
                    $this->rawData  = substr($this->rawData, $this->chunkLen);
                    $this->chunkLen = 0;
                } else {
                    //decrease amount left on chunk
                    $this->chunkLen = $this->chunkLen - strlen($this->rawData);
                    $this->completed .= $this->rawData;
                    $this->rawData  = '';
                    $done           = true;
                }
            }
            if ($this->finishedReading) {
                $this->finished($this->completed);
            }
        }
    }

    private function finished($data)
    {
        $moved = strpos($data, '<p>The document has moved <a href="');
        if ($moved === FALSE && !empty($this->header['Location'])) {
            $loc   = $this->header['Location'];
            $moved = true;
        }
        $status = $this->header['status'];
        if (($status == '302' || $status == '301' || $status == '303') && !$this->XMLRPC && $moved !== false) {
            //handle redirections
            //<p>The document has moved <a href="http://www.youtube.com/watch?v=nccpL3lPYYk&amp;feature=youtu.be">here</a>
            //i think we just need to handle that response for now..
            //basically we make a whole new http with same settings we have
            if (!$loc) {
                $startText = '<p>The document has moved <a href="';
                $endText   = '">here</a>';
                $start     = strpos($data, $startText) + strlen($startText);
                $end       = strpos($data, $endText, $start);
                $url       = substr($data, $start, $end - $start);
            } else {
                $url = $loc;
            }
            
            $purl = parse_url($url);
            if(!array_key_exists('host', $purl)) {
                $url = 'Http://' . $this->host . $url;
            }
            
            $redirloop = false;
            if(array_key_exists('REDIRECTS', $this->internals)) {
                if($this->internals["REDIRECTS"] >= 5)
                    $redirloop = true;
                $this->internals["REDIRECTS"]++;
            } else {
                $this->internals = Array("REDIRECTS" => 0);
            }
            
            if(array_key_exists('Set-Cookie', $this->header)) {
                $this->internals['COOKIE'] = $this->header['Set-Cookie'];
            }
            
            $this->internals['REFERER'] = $this->host;
            if(!$redirloop) {
                $redirect = new Http($this->sockets, $this->pClass, $this->pFunc,
                                     $this->vstore, $this->timeout);
                if ($this->qtype == 'get') {
                    $redirect->getQuery($url, $this->vstore, $this->internals);
                }
                if ($this->qtype == 'post') {
                    $redirect->postQuery($url, $this->vstore, $this->pquery, $this->internals);
                }
                echo "Http Redirected to $url\n";
            } else {
                echo "Redirect loop dectected, stopped\n";
                $this->pClass->{$this->pFunc}(Array(0 => 0, 1 => "Too many redirects detected.", 'HTTPHASERROR' => 'HTTPERROR'), $this->vstore);
            }
            $this->OMGAllDone = true;
            return;
        }
        $f = $this->pFunc;
        echo "Http finished!!\n";
        $this->OMGAllDone = true;
        if ($this->XMLRPC) {
            $super  = ReEncode($data);
            $xmlres = xmlrpc_decode($super);
            $xmlres = ArReDeEncode($xmlres);
            $this->pClass->$f($data, $this->vstore, $xmlres);
        } else {
            $this->pClass->$f($data, $this->vstore);
        }
    }

}
