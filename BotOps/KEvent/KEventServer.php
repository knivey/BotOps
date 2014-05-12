<?php

/* * *************************************************************************
 * BotOps IRC Framework
 * Http://www.botops.net/
 * Contact: irc://irc.gamesurge.net/bots
 * **************************************************************************
 * Copyright (C) 2013 BotOps
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * **************************************************************************
 * KEventServer.php Author knivey <knivey@botops.net>
 *   Dispatches KEvents
 * ************************************************************************* */
include_once 'KEvent/KEvent.php';
/**
 * 
 * @author knivey <knivey@botops.net>
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
