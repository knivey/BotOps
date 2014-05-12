<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
require_once('modules/Module.inc');
require_once('Tools/Tools.php');

require_once('modules/trivia/question.php');

class trivia extends Module {
	/**
	 * RPL Array for msgRpl function
	 * @var string $RPL_CORRECT
	 */
	var $RPL_CORRECT = Array(
			"WOW %s YOU SO SMART (%s)",
			"Watch out guys %s is liek a genuis over here! (%s)",
	);
	var $RPL_RUNNING = Array(
			"You gotta be pretty stupid %s trivia is already running!",
			"You'll never win %s if you can't see trivia is already running!",
			"\2%s\2 YOU STUPID TROUTMONGER! TRIVIA IS RUNNING!",
	);
	var $RPL_NOTRUNNING = Array(
			"You gotta be pretty stupid %s trivia is not even running!",
			"You'll never win %s if you can't see trivia is not even running!",
			"\2%s\2 YOU STUPID TROUTMONGER! TRIVIA IS NOT RUNNING!",
	);
	var $RPL_STARTED = Array(
			"HOLD ON TO YOUR BUTTS! TRIVIA HAS STARTED!",
			"This trivia brough to you by %s, the fantastic!",
			"BRACE FOR IMPACT! INITIALIZING TRIVIA!!!",
	);
	var $RPL_STOPPED = Array(
			"thank god! somebody stopped me :D",
			"this trivia experience ruined by %s :|",
			"*blasts %s for stoping trivia*",
	);
	var $RPL_SKIPPED = Array(
			"LOL SUPER IDIOT %s DIDN\'T KNOW THE ANSWER (%s)",
			"lol %s that was an easy one dumbass (%s)",
			"ur NEVAR gonna win like that %s, correct answers were (%s)",
	);
	var $RPL_NOANSWER = Array(
			":( Nobody got the last question (I would have accepted: %s)"
	);


	/*
	 * Want to start getting questions into categories
	 * since questions can fall under multiple categories
	 * it seems odd to try to stick categories in seperate files.
	 * Don't want to use mysql yet because i like being able to parse simple files atm
	 * 
	 * I think we can set up categories to be parsed like this:
	 * Category: SubCategory: question*answer*answerb
	 * Category: SubCategory|Othercategory: Subcategory: question*answer*answerb
	 * Pipe(or) should only be valid while in the category parsing
	 * 
	 * I want to either setup a category index file, or parse the final file for a list of cats
	 * Because of keeping in one big file the drawback will be in searching for a question thats in the requested category
	 * we will probably need to generate a list of question indexes before starting each game.
	 */
	
	/**
	 * The directory that contains our questions files
	 * @var string qdir
	 */
    public $qdir = './modules/trivia/questions/';


    /**
     * Array indexed by channels that are currently running trivia
     * current	=> current question object
     * count	=> number of questions asked thus far
     * scores	=> array indexed by nick of scores
     * @var array $running
     */
    public $running = Array();
    
    /**
     * Get the current question object for $chan, false on failure
     * @param string $chan
     * @return boolean|multitype:
     */
    function getCurrent($chan) {
    	if(!array_key_exists($chan, $this->running)) {
    		return false;
    	}
    	//can't do instanceof here
    	if(!is_object($this->running[$chan]['current'])) {
    		return false;
    	}
    	return $this->running[$chan]['current'];
    }
    
    /**
     * Search a directory for files matching a pattern (using * and ?)
     * @param string $dir
     * @param string $search
     * @return multitype:string
     */
    function searchDir($dir, $search) {
        $d = opendir($dir);
        $out = Array();
        while ($file = readdir($d)) {
            if (pmatch($search, $file)) {
                $out[] = "$file";
            }
        }
        closedir($d);
        return $out;
    }

    
    /**
     * Get a random question from a random file in the qdir
     * @return multitype:multitype: mixed
     */
    function getQuestion() {
    	$tries = 0;
    	do {
    		//for now we are doing the easy thing and just getting random
        	$files = $this->searchDir($this->qdir, '*.txt');
        	$fileName = $this->qdir . $files[array_rand($files)];
        	$lines = file($fileName);
        	$lineNumber = array_rand($lines);
        	$line = trim($lines[$lineNumber]);
        	$q = new question($line, $lineNumber, $fileName);
        	$tries++;
    	} while($q->chkFailed() && $tries < 5);
		//what a lovely solution here
		if($q->chkFailed()) {
			return new question("ERROR: Is you trivia database working, I'll give you a hint the answer is NO*no", $lineNumber, $fileName);
		}
		return $q;
    }
    
    /**
     * Get and format a random reply for the $this->RPL*
     * @todo Once we switch to php 5.6 this might become variadic function 
     * 
     * @param string		$chan	channel to msg to
     * @param Array 		$rpl 	one of the $this->RPL*
     * @param Array|string 	$args	string if only one otherwise use array of args
     */
    function msgRpl($chan, $rpl, $args) {
    	if(!is_array($args)) {
    		$args = Array($args);
    	}
    	$f = $rpl[array_rand($rpl)];
        $this->pIrc->msg($chan, vsprintf($f, $args));
    }
    
    /**
     * (non-PHPdoc) called at interupts
     * @see Module::logic()
     */
    function logic() {
        foreach($this->running as $chan => $d) {
        	$cur = $this->getCurrent($chan);
        	if(!$cur) {
        		continue;
        	}
        	$askTime = $cur->askTime;
        	$hintNum = $cur->answers->hintNum;
            if(time() - $askTime > 45) {
                $a = $cur->answers->toString();
                $this->msgRpl($chan, $this->RPL_NOANSWER, $a);
                $this->nextQuestion($chan);
                continue;
            }
            if(time() - $askTime > 34 && $hintNum < 3) {
                $this->showHint($chan);
                continue;
            }
            if(time() - $askTime > 24 && $hintNum < 2) {
                $this->showHint($chan);
                continue;
            }
            if(time() - $askTime > 14 && $hintNum < 1) {
                $this->showHint($chan);
                continue;
            }
        }
    }
    
    /**
     * cmd to start the game
     * @param string $nick
     * @param string $chan
     * @param string $msg
     */
    function cmd_trivia($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        if(array_key_exists($chan, $this->running)) {
            $this->msgRpl($chan, $this->RPL_RUNNING, $nick);
            return;
        }
        if($argc > 0) {
        	$this->pIrc->msg($chan, "Trivia Notice: category selection not supported at this time ;(");
        }
        
        $this->running[$chan] = Array(
            'current' => $this->getQuestion(),
            'count' => 0,
            'scores' => Array()
        );
        $this->msgRpl($chan, $this->RPL_STARTED, $nick);
        $this->showQuestion($chan);
    }

    /**
     * Message the channel the current question
     * @param string $chan
     */
    function showQuestion($chan) {
        $count = ++$this->running[$chan]['count'];
        $question = $this->running[$chan]['current']->toString();
        $this->pIrc->msg($chan, "[$count] $question");
    }

    /**
     * Message the channel a hint
     * @param string $chan
     */
    function showHint($chan) {
    	$cur = $this->getCurrent($chan);
    	if(!$cur) {
    		return;
    	}
        $txt = $cur->answers->getHint();
        $timeleft = 45 - (time() - $cur->askTime);
        $this->pIrc->msg($chan, "Time Left: $timeleft Hint: $txt");
    }
    
    /**
     * Proceed to a new question
     * @param string $chan
     */
    function nextQuestion($chan) {
        $this->running[$chan]['current'] = $this->getQuestion();
        $this->showQuestion($chan);
    }
    
    /**
     * Here we would browse possible categories..
     * @param string $chan
     */
    function cmd_categories($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        $this->pIrc->msg($chan, "EVERYTHING! kthx");
    }
    
    /**
     * Message the channel the current scores
     * @param string $chan
     */
    function showScores($chan) {
    	if(!array_key_exists($chan, $this->running)) {
    		return;
    	}
    	$scores = $this->running[$chan]['scores'];
        $out = '';
        arsort($scores);
        foreach ($scores as $n => $s) {
            $out .= "$n - $s, ";
        }
        $this->pIrc->msg($chan, 'Scores: '. trim($out));
    }
    
    /**
     * stop trivia
     * @param string $nick
     * @param string $chan
     * @param string $msg
     */
    function cmd_strivia($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        $cur = $this->getCurrent($chan);
        if(!$cur) {
        	$this->msgRpl($chan, $this->RPL_NOTRUNNING, $nick);
        	return;
        }
        $this->msgRpl($chan, $this->RPL_NOANSWER, $cur->answers->toString());
        $this->msgRpl($chan, $this->RPL_STOPPED, $nick);
        $this->showScores($chan);
        unset($this->running[$chan]);
    }
    
    /**
     * Not sure what this should do yet
     * @param string $nick
     * @param string $chan
     * @param string $msg
     */
    function cmd_hint($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
        if(!array_key_exists($chan, $this->running)) {
        	$this->msgRpl($chan, $this->RPL_NOTRUNNING, $nick);
        	return;
        }
    }

    /**
     * skip a question
     * @param string $nick
     * @param string $chan
     * @param string $msg
     */
    function cmd_skip($nick, $chan, $msg) {
    	$cur = $this->getCurrent($chan);
    	if(!$cur) {
    		$this->msgRpl($chan, $this->RPL_NOTRUNNING, $nick);
    		return;
    	}
        $a = $cur->answers->toString();
        $this->msgRpl($chan, $this->RPL_SKIPPED, Array($nick, $a));
        $this->nextQuestion($chan);
    }

    /**
     * get some information about trivia
     * @param string $nick
     * @param string $chan
     * @param string $msg
     */
    function cmd_triviainfo($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
    }

    /**
     * nothing?
     * @param string $nick
     * @param string $chan
     * @param string $msg
     */
    function cmd_triviastats($nick, $chan, $msg) {
        list($argc, $argv) = niceArgs($msg);
    }
    
    /**
     * Increase $nicks score on $chan by $amt
     * @param string $nick
     * @param string $chan
     * @param number $amt
     */
    function incScore($nick, $chan, $amt = 1) {
    	if(!array_key_exists($chan, $this->running)) {
    		return;
    	}
    	if(array_key_exists($nick, $this->running[$chan]['scores'])) {
    		$this->running[$chan]['scores'][$nick] += $amt;
    	} else {
    		$this->running[$chan]['scores'][$nick] = $amt;
    	}
    }

    /**
     * Hook for incoming messages
     * @param string $nick
     * @param string $chan
     * @param string $msg
     */
    function h_msg($nick, $chan, $msg) {
    	$cur = $this->getCurrent($chan);
        if(!$cur) {
            return;
        }
        
        if($cur->answers->isCorrect($msg)) {
        	$a = $cur->answers->toString();
        	$this->msgRpl($chan, $this->RPL_CORRECT, Array($nick, $a));
        	$this->incScore($nick, $chan);
        	$this->nextQuestion($chan);
        }
    }
}

?>
