<?php

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

    
    function stop() {

    }

    function setRPC($method, &$cbClass, $cbFunc) {

    }
    
    function chgClass(&$old, &$new) {

    }
    
    function init() {
    }
    
    function sErr($sock, $err) {

    }
    
    function sCon($sock, $ip) {

    }
    
    function sRead($sock, $data) {

    }
    
    public function generateFault($code, $string) {

    }
    
    //Send a response back to the socket and mark it to close
    public function sendResponse($sock, $data) {

    }
    
    public function findRPC($method) {

    }
    
    public function processRequest($sock, $xml) {

    }
    
    function parseHeader($data) {

    }
}
