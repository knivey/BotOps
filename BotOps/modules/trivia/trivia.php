<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';

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
     * @param CmdRequest    $r
     * @param Array 		$rpl 	one of the $this->RPL*
     * @param Array|string 	$args	string if only one otherwise use array of args
     */
    function msgRpl(CmdRequest $r, $rpl, $args) {
    	if(!is_array($args)) {
    		$args = Array($args);
    	}
    	$f = $rpl[array_rand($rpl)];
        $r->reply(vsprintf($f, $args));
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

    function cmd_trivia(CmdRequest $r) {
        if(array_key_exists($r->chan, $this->running)) {
            $this->msgRpl($r, $this->RPL_RUNNING, $r->nick);
            return;
        }
        if(isset($r->args[0])) {
        	throw new CmdException("Sorry, category selection not supported at this time ;(");
        }
        
        $this->running[$r->chan] = Array(
            'current' => $this->getQuestion(),
            'count' => 0,
            'scores' => Array()
        );
        $this->msgRpl($r, $this->RPL_STARTED, $r->nick);
        $this->showQuestion($r->chan);
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
    function cmd_categories(CmdRequest $r) {
        $r->reply("EVERYTHING! kthx");
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

    function cmd_strivia(CmdRequest $r) {
        $cur = $this->getCurrent($r->chan);
        if(!$cur) {
        	$this->msgRpl($r, $this->RPL_NOTRUNNING, $r->nick);
        	return;
        }
        $this->msgRpl($r, $this->RPL_NOANSWER, $cur->answers->toString());
        $this->msgRpl($r, $this->RPL_STOPPED, $r->nick);
        $this->showScores($r->chan);
        unset($this->running[$r->chan]);
    }

    function cmd_skip(CmdRequest $r) {
    	$cur = $this->getCurrent($r->chan);
    	if(!$cur) {
    		$this->msgRpl($r, $this->RPL_NOTRUNNING, $r->nick);
    		return;
    	}
        $a = $cur->answers->toString();
        $this->msgRpl($r, $this->RPL_SKIPPED, Array($r->nick, $a));
        $this->nextQuestion($r->chan);
    }

    function cmd_triviainfo(CmdRequest $r) {
        $r->notice("Coming soon.");
    }

    function cmd_triviastats(CmdRequest $r) {
        $r->reply("Stats coming soon.");
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


