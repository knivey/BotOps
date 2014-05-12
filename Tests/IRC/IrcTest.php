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
    
    public function testKillBot() {
        //just putting something here for now
        $this->assertNull(self::$Irc->killBot('testing'));
    }
    
    public function tearDown() {
        self::$sockets = null;
    }
}
?>
