<?php
/**
 *  Keep track of channel information, Including users modes topic bans
 *  Flood information
 */
 
class Channel {
	public $name;
	public $names = Array(); // Nicks joined channel
	public $modes = Array();
	/* 'mode' => '+/-/val' */
	public $bans = Array();
	/*
	 * [] = Array('mask', 'time', 'by')
	 */
	public $topic;
	public $topicTime;
	public $createTime;
	
	public $inFloodAge; // How long do messages stay in the next array
	public $inFlood = Array();
	/*
	 * Keep track of users flooding channel (basically just a log of the channel)
	 * 
	 * $inFlood[] = Array(
	 * 'time' Time message was sent
	 * 'host' Hostmask of user sending
	 * 'nick' Nick of user
	 * 'msg' Message sent
	 *   * if kick then msg = whonick@whohost :msg
	 *   * mode = (+/-)modeLtr val
	 * 'type' Type of message (notice/part/topic/privmsg/kick/mode) 
	 */
	public function __construct($name, $inFloodAge = 3600) {
		$this->name = $name;
	}
	
	public function input($nick, $host, $msg, $type) {
		$this->inFlood[] = Array(
		    'time' => time(),
			'host' => $host,
			'nick' => $nick,
			'msg' => $msg,
			'type' => $type);
		foreach($this->inFlood as $key => $data) {
			if(($data['time'] + $this->inFloodAge) < time()) {
				unset($this->inFlood[$key]);
				continue;
			}
		}
	}
	
	public function checkFloodLinesSec($lines, $sec, $type = '', &$class, $func) {
		/*
		 * Scans for the specified flood then calls the
		 * class->func for each person found to have flooded
		 */
		foreach($this->inFlood as $key => $data) {
			if(($data['time'] + $this->inFloodAge) < time()) {
				unset($this->inFlood[$key]);
				continue;
			}
		}
	}
}