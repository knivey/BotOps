<?php

require_once 'BotOps/KEvent/KEventServer.php';

class KEventServerTest extends PHPUnit\Framework\TestCase {
    protected static ?KEventServer $es;
    protected static ?TestEvent $te;
    
    protected function setUp() : void {
        self::$es = new KEventServer();
        self::$te = new TestEvent(Array('test', 'args'));
    }
    
    function testaddListener() {
        $mock = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $this->assertTrue(self::$es->addListener('TestEvent', $mock, 'cb'));
    }
    
    function testlistenerExists() {
        $mock = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $this->assertTrue(self::$es->addListener('TestEvent', $mock, 'cb'));
        $listener = Array($mock, 'cb');
        $this->assertTrue(self::$es->listenerExists('TestEvent', $listener));
        $this->assertFalse(self::$es->listenerExists('NoEvent', $listener));
    }
    
    function testsendEvent() {
        $mock = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $mock->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        self::$es->addListener('TestEvent', $mock, 'cb');
        self::$es->sendEvent(self::$te);
    }
    
    function testsamelistenerSendEvent() {
        $mock = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $mock->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        $this->assertTrue(self::$es->addListener('TestEvent', $mock, 'cb'));
        $this->assertFalse(self::$es->addListener('TestEvent', $mock, 'cb'));
        self::$es->sendEvent(self::$te);
    }
    
    function testdoubleSendEvent() {
        $mock = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $mock->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        $mockb = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $mockb->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        $this->assertTrue(self::$es->addListener('TestEvent', $mock, 'cb'));
        $this->assertTrue(self::$es->addListener('TestEvent', $mockb, 'cb'));
        self::$es->sendEvent(self::$te);
    }
    
    function testdoubleListenerSendEvent() {
        $mock = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $mock->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        $mockb = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $mockb->expects($this->never())->method('cb');
        self::$es->addListener('TestEvent', $mock, 'cb');
        self::$es->addListener('NotTestEvent', $mockb, 'cb');
        self::$es->sendEvent(self::$te);
    }
    
    function testdelListener() {
        $mock = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $mock->expects($this->never())->method('cb');
        $mockb = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        $mockb->expects($this->once())->method('cb');
        self::$es->addListener('TestEvent', $mock, 'cb');
        self::$es->addListener('TestEvent', $mockb, 'cb');
        self::$es->delListener($mock);
        self::$es->sendEvent(self::$te);
    }
    
    function testbadListener() {
        $mock = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        self::$es->addListener('TestEvent', $mock,'cb');
        unset($mock);
        @self::$es->sendEvent(self::$te);
    }

    function testbadListenerWarning() {
        $this->expectError();
        $mock = $this->getMockBuilder('stdClass')->addMethods(['cb'])->getMock();
        self::$es->addListener('TestEvent', $mock, 'cb');
        $mock = NULL;
        unset($mock);
        self::$es->sendEvent(self::$te);
    }
    
    protected function tearDown(): void {
        self::$es = NULL;
    }
}

class TestEvent extends KEvent {
    public string $type = 'TestEvent';
    public $param;
    function __construct($param) {
        $this->param = $param;
    }
    function getType() {
        return $this->type;
    }
    function getParam() {
        return $this->param;
    }
}

?>
