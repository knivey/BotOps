<?php

include_once('BotOps/IRC/Irc.inc');
include_once('BotOps/Sockets.inc');

class IrcTest extends PHPUnit\Framework\TestCase {
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
    
    protected function setUp() : void {
        self::$sockets = $this->createMock(Sockets::class);
        
        self::$Irc = new Irc(self::$sockets, 'UnitBot', 0, 'localhost', 4);
    }
    
    public function testConstruct() {
        $irc = new Irc(self::$sockets, 'UnitBot', '127.0.0.1', 'testserv', 4, 1234, 'unitpass', 50, 90);

        $this->assertEquals('bots localhost localhost :IRC Bot Services #Bots', $irc->user);
        $this->assertEquals('testserv', $irc->server);
        $this->assertEquals(4, $irc->ipv);
        $this->assertEquals(1234, $irc->port);
        $this->assertEquals('unitpass', $irc->pass);
        $this->assertEquals(50, $irc->connectTimeout);
        $this->assertEquals('127.0.0.1', $irc->bindIP);
        $this->assertEquals('UnitBot', $irc->nick);
        //$this->assertEquals(self::$sockets, $irc->pSockets);
        $this->assertEquals(90, $irc->readTimeout);
        $this->assertInstanceOf('IrcFilters', $irc->ircFilters);
        $this->assertInstanceOf('Nicks', $irc->Nicks);
        $this->assertInstanceOf('KEventServer', $irc->eventServer);
        
        $irc = new Irc(self::$sockets, 'UnitBot', '127.0.0.1', 'testserv', 4);
        $this->assertEquals(6667,  $irc->port);
        $this->assertEquals('', $irc->pass);
        $this->assertEquals(30, $irc->connectTimeout);
        $this->assertEquals(170, $irc->readTimeout);
        
        $irc = new Irc(self::$sockets, 'UnitBot', '127.0.0.1', 'testserv', 8);
        $this->assertEquals(4, $irc->ipv);

        $irc = new Irc(self::$sockets, 'UnitBot', '127.0.0.1', 'testserv', 6);
        $this->assertEquals(6, $irc->ipv);
    }
    
    public function testKillBot() {
        //just putting something here for now
        $this->assertNull(self::$Irc->killBot('testing'));
    }
    
    protected function tearDown() : void {
        self::$sockets = null;
    }
}
?>
