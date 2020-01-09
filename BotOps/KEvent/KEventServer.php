<?php

include_once 'KEvent/KEvent.php';
/**
 * Dispatches KEvents
 */
class KEventServer {
    /**
     * Indexed by event type, an array of callables
     * @var Array $listeners
     */
    public $listeners = Array();
    
    /**
     * Add a new listener for event to be sent to callable
     * @param string $eventType
     * @param stdClass $class
     * @param string $function
     * @return bool True on success
     */
    public function addListener($eventType, &$class, $function) {
        $cb = Array(&$class, $function);
        if(!array_key_exists($eventType, $this->listeners)) {
            $this->listeners[$eventType] = Array();
        }
        if($this->listenerExists($eventType, $cb)) {
            return false;
        }
        $this->listeners[$eventType][] = &$cb;
        return true;
    }
    
    public function listenerExists($eventType, &$cb) {
        if(!array_key_exists($eventType, $this->listeners)) {
            return false;
        }
        foreach ($this->listeners[$eventType] as &$cbr) {
            if ($cb === $cbr) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Remove all listeners for this class
     * @param stdClass $class
     */
    public function delListener(&$class) {
        foreach($this->listeners as $type => $listners) {
            foreach($listners as $key => &$val) {
                if($val[0] === $class) {
                    unset($this->listeners[$type][$key]);
                }
            }
        }
    }
    
    /**
     * Sends an event out to whoever will listen
     * @param KEvent $event
     * @return null
     */
    public function sendEvent(KEvent $event) {
        if(!array_key_exists($event->getType(), $this->listeners)) {
            return;
        }
        foreach($this->listeners[$event->getType()] as $call) {
            if(is_callable($call)) {
                call_user_func_array($call, $event->getParam());
            } else {
                $error = "KEvent: Unable to call listener for Event: " . $event->getType();
                $error .= " Callback: " . gettype($call[0]) . ':' . @get_class($call[0]) . '->' . (string)$call[1];
                trigger_error($error, E_USER_WARNING);
            }
        } 
    }
}

?>
