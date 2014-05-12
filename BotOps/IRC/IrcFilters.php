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
 * IrcFilters.php Author knivey <knivey@botops.net>
 *   Description here
 * ************************************************************************* */
require_once 'Tools/Tools.php';
/**
 * 
 * @author knivey <knivey@botops.net>
 */
class IrcFilters {
    public $filterHandler = Array();
    public $filters = Array();
    
    public function getFilters() {
        return $this->filters;
    }

    public function loadFilters(Array $filters = Array()) {
        $this->filters = $filters;
    }

    public function setFilterHandler(&$class, $func) {
        $this->filterHandler = Array(&$class, $func);
    }

    public function passFilter($txt) { // Does our string pass the rules
        if(empty($this->filters)) {
            return true;
        }
        foreach($this->filters as $filt) {
            if(pmatch($filt['text'], $txt)) {
                if (!empty($this->filterHandler) && is_callable($this->filterHandler)) {
                    list($c, $f) = $this->filterHandler;
                    $c->$f($filt, $txt);
                }
                return FALSE;
            }
        }
        return TRUE;
    }
}

?>
