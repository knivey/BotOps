<?php

include_once('BotOps/IRC/IrcFilters.php');

class IrcTestFitlers extends PHPUnit\Framework\TestCase {
    protected static ?IrcFilters $ircFilters;
    protected static array $filters;

    protected function setUp(): void {
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
    
    protected function tearDown(): void {
        self::$ircFilters = NULL;
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
        $mock = $this->createMock(FilterHandler::class);

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

class FilterHandler {
    public function cb($a, $b) {return;}
}

?>
