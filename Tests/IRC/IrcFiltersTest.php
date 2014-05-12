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
 * IrcFiltersTest.php Author knivey <knivey@botops.net>
 *   Description here
 * ************************************************************************* */

/**
 * 
 * @author knivey <knivey@botops.net>
 */
include_once('BotOps/IRC/IrcFilters.php');

class IrcTestFitlers extends PHPUnit_Framework_TestCase {

    /**
     * @var IrcFilters $ircFilters
     */
    protected static $ircFilters;
    
    protected static $filters;

    public function setUp() {
        self::$ircFilters = new IrcFilters();
        self::$filters = array(
            0 =>
            array(
                'id' => 3,
                'made' => 1217586078,
                'who' => 'linuxsniper',
                'text' => '*dcc*send*',
                'caught' => 12,
            ),
            1 =>
            array(
                'id' => 2,
                'made' => 1217584100,
                'who' => 'linuxsniper',
                'text' => '*myminicity.com*',
                'caught' => 25,
            ),
        );
    }
    
    public function tearDown() {
        self::$ircFilters = NULL;
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testloadFiltersError() {
        self::$ircFilters->loadFilters('error');
    }
    
    public function testloadFilters() {
        $this->assertEquals(Array(), self::$ircFilters->getFilters(), "Filters not initialized to empty array");
        self::$ircFilters->loadFilters(self::$filters);
        $this->assertEquals(self::$filters, self::$ircFilters->getFilters(), "Failed to load filters");
    }
    
    public function testpassFilter() {
        self::$ircFilters->loadFilters(self::$filters);
        $this->assertTrue(self::$ircFilters->passFilter('lol hi there'));
        $this->assertFalse(self::$ircFilters->passFilter('myminicity.com for stupid shit'));
        $this->assertFalse(self::$ircFilters->passFilter('lol dcc this send'));
        $this->assertFalse(self::$ircFilters->passFilter('myminicity.com'));
    }
    
    public function testFilterHandler() {
        $mock = $this->getMock('stdClass', Array('cb'));
        $mock->expects($this->once())
                 ->method('cb')
                 ->with($this->equalTo(self::$filters[1]),
                         $this->equalTo('myminicity.com'));

        self::$ircFilters->setFilterHandler($mock, 'cb');
        self::$ircFilters->loadFilters(self::$filters);
        self::$ircFilters->passFilter('hi there lol');
        self::$ircFilters->passFilter('myminicity.com');
    }
}


?>
