<?php

include_once('BotOps/IRC/IrcEvent.php');

class IrcEventTest extends PHPUnit\Framework\TestCase {
	public function testIrcEvent() {
		$ev = new IrcEvent('Test', Array('hi'));
		$this->assertEquals('Test', $ev->getType());
		$this->assertEquals(Array('hi'), $ev->getParam());
	}
}