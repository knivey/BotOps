<?php
/***************************************************************************
 * BotNetwork Bots IRC Framework
 * Http://www.botnetwork.org/
 * Contact: irc://irc.gamesurge.net/bots
 ***************************************************************************
 * Copyright (C) 2009 BotNetwork
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
 ***************************************************************************
 * BotNetHub/lChan.php
 *   Class for botnet channels. pretty much barebones iChan holds channels
 *   that relay/store IRC channels
 ***************************************************************************/

class lChan {
    public $users; //array of lUser objects on the channel
    public $userModes; //array of userModes on the channel indexed by nick

    function mode($from, $mode) {
        
    }

    function msg($from, $msg) { //message sent to channel

    }

    function notice($from, $msg) { //notice sent to channel

    }

    function topic($from, $topic) { //topic change on channel

    }
}


?>
