<?php

include('BotOps/modules/trivia/answers.php');

class answersTest extends PHPUnit\Framework\TestCase {
	function testConstruct() {
		$a = new answers('answera*answerb');
	}
	
	function testNone() {
		$a = new answers('answera*answerb');
		$this->assertFalse($a->none());
		$a = new answers('');
		$this->assertTrue($a->none());
	}
	
	function testToString() {
		$a = new answers('answera*answerb');
		$this->assertEquals('answera | answerb', $a->toString());
		$a = new answers('answera');
		$this->assertEquals('answera', $a->toString());
	}
	
	function testIsCorrect() {
		$a = new answers('answerone*answertwo');
		$this->assertTrue($a->isCorrect('answerone'));
		$this->assertTrue($a->isCorrect('answertwo'));
		$this->assertFalse($a->isCorrect('notanswerone'));
		$a = new answers('Answerc');
		$this->assertTrue($a->isCorrect('ansWERC'));
		$this->assertTrue($a->isCorrect('ansWERb')); //leven
		$this->assertTrue($a->isCorrect('a.n,s W-E$R!b\'"%')); //punctuation no matter
		$a = new answers('a.n,s W-E$R!b\'"%');
		$this->assertTrue($a->isCorrect('ansWERb'));
	}
	
	function testAlphaNum() {
		$a = new answers('answera*answerb');
		$this->assertTrue($a->isAlphaNum('b'));
		$this->assertTrue($a->isAlphaNum('B'));
		$this->assertTrue($a->isAlphaNum('2'));
		$this->assertFalse($a->isAlphaNum('.'));
		$this->assertFalse($a->isAlphaNum(' '));
		$this->assertFalse($a->isAlphaNum('ü')); //not your english u
	}
	
	function testHintShown() {
		$a = new answers('answera*answerb');
		$a->hinter = Array(
				Array(0, 'l'),
				Array(1, 'l'),
				Array(1, 'l'),
				Array(0, 'l'),
				Array(0, 'l'),
		);
		$this->assertEquals(2, $a->hintShown());
		$a->hinter = Array(
				Array(0, 'l'),
				Array(1, 'l'),
				Array(1, 'l'),
				Array(1, ' '),
				Array(1, '.'),
				Array(0, 'l'),
		);
		$this->assertEquals(2, $a->hintShown()); //We don't count non-alphanum as part of the hint, it is always shown
	}
	
	function testHintLen() {
		$a = new answers('answera*answerb');
		$a->hinter = Array(
				Array(0, 'l'),
				Array(1, 'l'),
				Array(1, 'l'),
				Array(1, ' '),
				Array(1, '.'),
				Array(0, 'l'),
		);
		$this->assertEquals(4, $a->hintLen()); //We don't count non-alphanum as part of the hint, it is always shown
	}
	
	function testTxtHinter() {
		$a = new answers('answera*answerb');
		$a->hinter = Array(
				Array(0, 'l'),
				Array(1, 'l'),
				Array(1, 'l'),
				Array(0, ' '),
				Array(0, '.'),
				Array(0, 'l'),
		);
		$this->assertEquals('*ll .*', $a->txtHinter()); //We don't count non-alphanum as part of the hint, it is always shown
	}
	
	function testIncHinter() {
		$a = new answers('answer');
		$a->incHinter(2);
		$cnt = count_chars($a->txtHinter())[ord('*')];
		$this->assertEquals(4, $cnt);
		
		$a->incHinter(2);
		$cnt = count_chars($a->txtHinter())[ord('*')];
		$this->assertEquals(2, $cnt);
		
		$a = new answers('a n s . e r');
		$a->incHinter(2);
		$cnt = count_chars($a->txtHinter())[ord('*')];
		$this->assertEquals(3, $cnt);
		
		$a->incHinter(5);
		$cnt = count_chars($a->txtHinter())[ord('*')];
		$this->assertEquals(1, $cnt); //Make sure we never reveal whole answer
	}
}

?>