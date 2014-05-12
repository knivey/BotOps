<?php

/* * *************************************************************************
 * BotOps IRC Framework
 * Http://www.botops.net/
 * Contact: irc://irc.gamesurge.net/bots
 * **************************************************************************
 * Copyright (C) 2013 BotOps
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
 * KEventServerTest.php Author knivey <knivey@botops.net>
 *   Description here
 * ************************************************************************* */
require_once 'BotOps/KEvent/KEventServer.php';
/**
 * 
 * @author knivey <knivey@botops.net>
 */
class KEventServerTest extends PHPUnit_Framework_TestCase {
    /**
     * @var KEventServer $es
     */
    protected static $es;
    /**
     * @var TestEvent $te
     */
    protected static $te;
    
    function setUp() {
        self::$es = new KEventServer();
        self::$te = new TestEvent(Array('test', 'args'));
    }
    
    function testaddListener() {
        $mock = $this->getMock('stdClass', Array('cb'));
        $this->assertTrue(self::$es->addListener('TestEvent', $mock, 'cb'));
    }
    
    function testlistenerExists() {
        $mock = $this->getMock('stdClass', Array('cb'));
        $this->assertTrue(self::$es->addListener('TestEvent', $mock, 'cb'));
        $listener = Array($mock, 'cb');
        $this->assertTrue(self::$es->listenerExists('TestEvent', $listener));
        $this->assertFalse(self::$es->listenerExists('NoEvent', $listener));
    }
    
    function testsendEvent() {
        $mock = $this->getMock('stdClass', Array('cb'));
        $mock->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        self::$es->addListener('TestEvent', $mock, 'cb');
        self::$es->sendEvent(self::$te);
    }
    
    function testsamelistenerSendEvent() {
        $mock = $this->getMock('stdClass', Array('cb'));
        $mock->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        $this->assertTrue(self::$es->addListener('TestEvent', $mock, 'cb'));
        $this->assertFalse(self::$es->addListener('TestEvent', $mock, 'cb'));
        self::$es->sendEvent(self::$te);
    }
    
    function testdoubleSendEvent() {
        $mock = $this->getMock('stdClass', Array('cb'));
        $mock->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        $mockb = $this->getMock('stdClass', Array('cb'));
        $mockb->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        $this->assertTrue(self::$es->addListener('TestEvent', $mock, 'cb'));
        $this->assertTrue(self::$es->addListener('TestEvent', $mockb, 'cb'));
        self::$es->sendEvent(self::$te);
    }
    
    function testdoubleListenerSendEvent() {
        $mock = $this->getMock('stdClass', Array('cb'));
        $mock->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo('test'),
                         $this->equalTo('args'));
        $mockb = $this->getMock('stdClass', Array('cb'));
        $mockb->expects($this->never())->method('cb');
        self::$es->addListener('TestEvent', $mock, 'cb');
        self::$es->addListener('NotTestEvent', $mockb, 'cb');
        self::$es->sendEvent(self::$te);
    }
    
    function testdelListener() {
        $mock = $this->getMock('stdClass', Array('cb'));
        $mock->expects($this->never())->method('cb');
        $mockb = $this->getMock('stdClass', Array('cb'));
        $mockb->expects($this->once())->method('cb');
        self::$es->addListener('TestEvent', $mock, 'cb');
        self::$es->addListener('TestEvent', $mockb, 'cb');
        self::$es->delListener($mock);
        self::$es->sendEvent(self::$te);
    }
    
    function testbadListener() {
        $mock = $this->getMock('stdClass', Array('cb'));
        self::$es->addListener('TestEvent', $mock,'cb');
        unset($mock);
        @self::$es->sendEvent(self::$te);
    }
    
    /**
     * @expectedException PHPUnit_Framework_Error
     */
    function testbadListenerWarning() {
        $mock = $this->getMock('stdClass', Array('cb'));
        self::$es->addListener('TestEvent', $mock, 'cb');
        $mock = NULL;
        unset($mock);
        self::$es->sendEvent(self::$te);
    }
    
    function tearDown() {
        self::$es = NULL;
    }
}

class TestEvent extends KEvent {
    public $type = 'TestEvent';
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
