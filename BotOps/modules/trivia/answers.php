<?php
/**
 * Handles answers for the question currently asked
 * @author knivey
 *
 */
class answers {
	/**
	 * An array of correct answers
	 * @var array $answers
	 */
	var $answers = Array();
	/**
	 * What hint number we are on
	 * @var number $hintNum
	 */
	var $hintNum = 0;
	
	/**
	 * A crude array holding the information for our hint
	 * for example, lol could be:
	 * $hinter[0] = Array(0,'l') - Letter l has not been revealed
	 * $hinter[1] = Array(0,'o') - Letter o has not been revealed
	 * $hinter[2] = Array(1,'l') - Letter l has been revealed
	 * @var array $hinter
	 */
	var $hinter;
	
	/**
	 * @param string $str section of question line containing answers
	 */
	function __construct($str) {
		if($str == '') {
			$this->answers = Array();
			return;
		}
		$str = explode('*', $str);
		foreach($str as $ans) {
			$this->answers[] = trim($ans);
		}
		$this->hinter = Array();
		if(!empty($this->answers)) {
			foreach(str_split($this->answers[0]) as $l) {
				$this->hinter[] = Array(0,$l);
			}
		}
	}
	
	/**
	 * return true if we don't have any answers
	 * @return boolean
	 */
	function none() {
		if(empty($this->answers)) {
			return true;
		}
		return false;
	}
	
	/**
	 * Get the possible answers as a string
	 * @return string
	 */
	function toString() {
		return implode(' | ', $this->answers);
	}
	
	/**
	 * Check if an answer is correct
	 * @param string $msg
	 * @return boolean	true if yes, false if no
	 */
	function isCorrect($msg) {
		$msg = trim(strtolower($msg));
		$msg = preg_replace("/[\.\,\$\'\" \-\!\%]/", '', $msg);
		foreach($this->answers as $a) {
			$a = preg_replace("/[\.\,\$\'\" \-\!\%]/", '', $a);
			if(strtolower($a) == $msg) {
				return true;
			}
			$d = levenshtein(strtolower($a), $msg);
			if($d < strlen($a) / 5) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Increase our hint to the next level and return a string to be displayed
	 * Currently only 3 levels hard coded in
	 * @return string
	 */
	function getHint() {
		if($this->hintNum == 0) {
			$amt = round($this->hintLen() * 0.3);
			$this->incHinter($amt);
		}
		if($this->hintNum == 1) {
			$amt = round($this->hintLen() * 0.5) - $this->hintShown();
			$this->incHinter($amt);
		}
		if($this->hintNum == 2) {
			$amt = round($this->hintLen() * 0.7) - $this->hintShown();
			$this->incHinter($amt);
		}
		$this->hintNum++;
		return $this->txtHinter();
	}
	
	/**
	 * Check if a character is a-z A-Z or 0-9
	 * @param string $c	single character to check
	 * @return boolean	true if it is alphanum false otherwise
	 */
	function isAlphaNum($c) {
		if(preg_match('/[a-z]|[A-Z]|[0-9]/', $c)) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Return how many valid hintable characters have been revealed
	 * @return number
	 */
	function hintShown() {
		$cnt = 0;
		foreach($this->hinter as $h) {
			if($this->isAlphaNum($h[1]) && $h[0] == 1) {
				$cnt++;
			}
		}
		return $cnt;
	}
	
	/**
	 * Return the amount of valid hintable characters
	 * @return number
	 */
	function hintLen() {
		$cnt = 0;
		foreach($this->hinter as $h) {
			if($this->isAlphaNum($h[1])) {
				$cnt++;
			}
		}
		return $cnt;
	}
	
	/**
	 * Increase the revealed amount of hintable characters,
	 * but do not reveal the whole answer 
	 * @param number $amt The number of characters to reveal (not the total revealed when done)
	 */
	function incHinter($amt) {
		$left = Array();
		foreach($this->hinter as $idx => $val) {
			if($val[0] != 1 && $this->isAlphaNum($val[1])) {
				$left[] = $idx;
			}
		}
		
		$done = 0;
		while(count($left) > 1 && $done != $amt) {
			$t = array_rand($left);
			$this->hinter[$left[$t]][0] = 1;
			unset($left[$t]);
			$done++;
		}
		
	}
	
	/**
	 * Get the current hint as a text string 
	 * @return string
	 */
	function txtHinter() {
		$out = '';
		foreach($this->hinter as $h) {
			if($h[0] == 1 || !$this->isAlphaNum($h[1])) {
				$out .= $h[1];
			} else {
				$out .= '*';
			}
		}
		return $out;
	}
}
?>