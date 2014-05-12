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
 * KEvent.php Author knivey <knivey@botops.net>
 *   Provides a base KEvent object
 * ************************************************************************* */

/**
 * This class is meant to be inherited from
 * @author knivey <knivey@botops.net>
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
