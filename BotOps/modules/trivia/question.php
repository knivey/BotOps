<?php
require_once('modules/trivia/answers.php');

class question {
	/**
	 * The line from the trivia file
	 * @var string $line
	 */
	var $line = '';
	
	/**
	 * Filename this question came from
	 * @var string $fileName
	 */
	var $fileName = '';
	
	/**
	 * Line number of the file this question came from
	 * @var number $lineNumber
	 */
	var $lineNumber = -1;
	
	/**
	 * Will be set to true if there was a failure parsing the question
	 * @var boolean $failed
	 */
	var $failed = true;
	
	/**
	 * The answers object for this question
	 * @var answers $answers
	 */
	var $answers;
	
	/**
	 * Question part of the line parsed
	 * @var string $question
	 */
	var $question = '';
	
	/**
	 * Time this object was created
	 * @var number $askTime
	 */
	var $askTime;
	
	/**
	 * An array representing what category the question is under
	 * @var array $cats
	 */
	var $cats = Array();
	
	/**
	 * Regular Expression used to parse trivia database lines
	 * @var string $regex
	 */
	var $regex = '/([^\*]+\:)?([^\*\:]+)?\*(.+)/i';
	
	/**
	 * @param string $line		 	The whole line from trivia file
	 * @param number $lineNumber	The line number from trivia file
	 * @param string $fileName		The trivia file's filename
	 */
	function __construct($line, $lineNumber, $fileName) {
		$this->lineNumber = $lineNumber;
		$this->fileName = $fileName;
		$this->line = $line;
		$this->askTime = time();
		//just to be safe, intialize answers to correct object
		$this->answers = new answers('none');
		if(preg_match($this->regex, $line, $m)) {
			if($m[1] != '') {
				$m[1] = explode(':', trim($m[1], ':'));
				foreach($m[1] as $cat) {
					$this->cats[] = trim($cat);
				}
			}
			$this->question = $m[2];
			$this->answers = new answers($m[3]);
			if(!$this->answers->none() && !empty($this->question)) {
				$this->failed = false;
			}
		}
	}
	
	/**
	 * See if we failed to parse the question
	 * @return boolean
	 */
	function chkFailed() {
		return $this->failed;
	}
	
	/**
	 * Get the question string to print to irc
	 * @return string
	 */
	function toString() {
		$out = '';
		if(!empty($this->cats)) {
			$str = implode('>', $this->cats);
			$out = '(' . $str . ') ';
		}
		$out .= $this->question . "?";
		return $out;
	}
}

?>