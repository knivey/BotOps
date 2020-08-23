<?php

require_once 'KEvent/KEvent.php';
/**
 *   Generic event for irc, had hoped to make an event class for all event types later
 */
class IrcEvent extends KEvent {
    public $type;
    public $param;
    public function __construct($type, $param = []) {
        $this->type = $type;
        $this->param = $param;
    }
    
    public function getType() {
        return $this->type;
    }
    
    public function getParam() {
        return $this->param;
    }
}

?>
