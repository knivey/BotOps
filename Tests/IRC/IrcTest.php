<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

include_once('BotOps/IRC/Irc.inc');
include_once('BotOps/Sockets.inc');

class IrcTest extends PHPUnit_Framework_TestCase {
    /**
     *
     * @var Irc $Irc
     */
    protected static $Irc;
    /**
     *
     * @var Sockets $sockets
     */
    protected static $sockets;
    
    public function setUp() {
        self::$sockets = $this->getMock('Sockets');
        
        self::$Irc = new Irc(self::$sockets, 'UnitBot', 0, 'localhost', 4);
    }
    
    public function testConstruct() {
        $irc = new Irc(self::$sockets, 'UnitBot', '127.0.0.1', 'testserv', 4, 1234, 'unitpass', 50, 90);
        
        $this->assertAttributeEquals(
            'bots localhost localhost :IRC Bot Services #Bots',
            'user', $irc);
        $this->assertAttributeEquals('testserv', 'server', $irc);
        $this->assertAttributeEquals(4, 'ipv', $irc);
        $this->assertAttributeEquals(1234, 'port', $irc);
        $this->assertAttributeEquals('unitpass', 'pass', $irc);
        $this->assertAttributeEquals(50, 'timeout', $irc);
        $this->assertAttributeEquals('127.0.0.1', 'bind', $irc);
        $this->assertAttributeEquals('UnitBot', 'nick', $irc);
        $this->assertAttributeEquals(self::$sockets, 'pSockets', $irc);
        $this->assertAttributeEquals(90, 'RTO', $irc);
        $this->assertAttributeInstanceOf('IrcFilters', 'ircFilters', $irc);
        $this->assertAttributeGreaterThan(time()+8, 'nickCheck', $irc);
        $this->assertAttributeInstanceOf('Nicks', 'Nicks', $irc);
        $this->assertAttributeInstanceOf('KEventServer', 'eventServer', $irc);
        
        $irc = new Irc(self::$sockets, 'UnitBot', '127.0.0.1', 'testserv', 4);
        $this->assertAttributeEquals(6667, 'port', $irc);
        $this->assertAttributeEquals('', 'pass', $irc);
        $this->assertAttributeEquals(30, 'timeout', $irc);
        $this->assertAttributeEquals(170, 'RTO', $irc);
        
        $irc = new Irc(self::$sockets, 'UnitBot', '127.0.0.1', 'testserv', 8);
        $this->assertAttributeEquals(4, 'ipv', $irc);
    }
    
    public function testKillBot() {
        //just putting something here for now
        $this->assertNull(self::$Irc->killBot('testing'));
    }
    
    public function tearDown() {
        self::$sockets = null;
    }
}
?>
