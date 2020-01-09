<?php

/**
 * This class is meant to be inherited from
 */
abstract class KEvent {
    /**
     * @return string type of event
     */
    abstract function getType();
    /**
     * @return array Array of parameters to pass to callback
     */
    abstract function getParam();
}

?>
