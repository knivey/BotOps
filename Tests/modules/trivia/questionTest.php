<?php

include('BotOps/modules/trivia/question.php');

class questionTest extends PHPUnit\Framework\TestCase {
	function testConstruct() {
		$q = new question('category:subcategory:question stuff*answera*answerb', 1, 'TestTrivia.txt');
	}
	
	function testChkFailed() {
		$q = new question('category:subcategory:question stuff*answera*answerb', 1, 'TestTrivia.txt');
		$this->assertFalse($q->chkFailed());
		$q = new question('question stuff*answera', 1, 'TestTrivia.txt');
		$this->assertFalse($q->chkFailed());
		
		$q = new question('category:subcategory:question stuff', 1, 'TestTrivia.txt');
		$this->assertTrue($q->chkFailed());
		$q = new question('category:subcategory:*answera*answerb', 1, 'TestTrivia.txt');
		$this->assertTrue($q->chkFailed());
		$q = new question('', 1, 'TestTrivia.txt');
		$this->assertTrue($q->chkFailed());
	}
}

?>