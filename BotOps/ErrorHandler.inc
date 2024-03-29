<?PHP
/*
 *  Handle PHP errors and such and log them usually..
 */

class ErrorHandler {
	public $Irc;
	public $Bnet;
	public $name;

	public $errorType;
	public $writeLog;
	function __construct($name, &$irc, &$bnet, $writeLog) {
		set_error_handler(array($this, 'handler'));
		$this->Irc = $irc;
		$this->Bnet = $bnet;
		$this->errorType = array (
		E_ERROR              => 'Error',
		E_WARNING            => 'Warning',
		E_PARSE              => 'Parsing Error',
		E_NOTICE            => 'Notice',
		E_CORE_ERROR        => 'Core Error',
		E_CORE_WARNING      => 'Core Warning',
		E_COMPILE_ERROR      => 'Compile Error',
		E_COMPILE_WARNING    => 'Compile Warning',
		E_USER_ERROR        => 'User Error',
		E_USER_WARNING      => 'User Warning',
		E_USER_NOTICE        => 'User Notice',
		E_STRICT            => 'Runtime Notice',
		E_RECOVERABLE_ERROR => 'Catchable Fatal Error'
		);
		$this->writeLog = $writeLog;
	}

	public function handler($errno, $errmsg, $filename, $linenum, $vars) {
		$dt = date("Y-m-d H:i:s (T)");
		$err = "\2" . $this->errorType[$errno] . "\2($errno) $errmsg \2file\2 $filename \2line\2 $linenum";
		if(is_object($this->Irc) && property_exists($this->Irc, 'last_in')) {
			$err .= " \2Last_in(IRC):\2 " . $this->Irc->last_in;
		}
		if(is_object($this->Bnet) && property_exists($this->Bnet, 'last_in')) {
			$err .= " \2Last_in(BN):\2 " . $this->Bnet->last_in;
		}
		
		if(is_object($this->Bnet) && method_exists($this->Bnet, 'msg')) {
			$this->Bnet->msg('&debug', $err);
		}
		
		if($this->writeLog) {
			error_log(str_replace("\2", '', "$name $err"), 3, "logs/error.log");
		}
		
	}
}

