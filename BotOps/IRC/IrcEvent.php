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
 * IrcEvent.php Author knivey <knivey@botops.net>
 *   Generic event for irc, had hoped to make an event class for all event types later
 * ************************************************************************* */
require_once 'KEvent/KEvent.php';
/**
 * 
 * @author knivey <knivey@botops.net>
 */
class IrcEvent extends KEvent {
    public $type;
    public $param;
    public function __construct($type, $param) {
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
