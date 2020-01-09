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
 * NicksTest.php Author knivey <knivey@botops.net>
 *   Description here
 * ************************************************************************* */

include_once('BotOps/IRC/Nicks.php');
/**
 * 
 * @author knivey <knivey@botops.net>
 */
class NicksTest extends PHPUnit\Framework\TestCase {
    static protected ?Nicks $Nicks;
    static protected int $timemin;
    static protected int $timemax;
    
    protected function setUp(): void {
        self::$Nicks = new Nicks();
        self::$timemin = time() -2;
        self::$timemax = time() +2;
    }
    
    function testJoin() {
        self::$Nicks->join('knivey', 'lol@user', '#bots');
        self::$Nicks->join('knivey', 'lol@user', '#zen');
        self::$Nicks->join('kurizu', 'hil@user', '#bots');
        self::$Nicks->join('kurizu', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#corn');
        self::$Nicks->join('kurizu2', 'hi@user', '#poop');
        $this->stdChecksA();
    }
    
    function testClearAll() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->tppl('knivey', 'test@host');
    	self::$Nicks->clearAll();
    	$this->assertEmpty(self::$Nicks->ppl);
    	$this->assertEmpty(self::$Nicks->tppl);
    }
    
    function testNick() {
        self::$Nicks->join('knivey-lol', 'lol@user', '#bots');
        self::$Nicks->join('knivey-lol', 'lol@user', '#zen');
        self::$Nicks->join('kurizu-lol', 'hil@user', '#bots');
        self::$Nicks->join('kurizu-lol', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2-lol', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2-lol', 'hi@user', '#corn');
        self::$Nicks->join('kurizu2-lol', 'hi@user', '#poop');
        self::$Nicks->nick('knivey-lol', 'knivey');
        self::$Nicks->nick('kurizu-lol', 'kurizu');
        self::$Nicks->nick('kurizu2-lol', 'kurizu2');
        $this->stdChecksA();
    }
    
    function testUnknownNick() {
    	self::$Nicks->join('kurizu2-lol', 'hi@user', '#poop');
    	self::$Nicks->nick('knivey-lol', 'knivey');
    	$this->assertArrayNotHasKey('knivey', self::$Nicks->ppl);
    	$this->assertArrayNotHasKey('knivey-lol', self::$Nicks->ppl);
    }
    
    function testPart() {
        self::$Nicks->join('knivey', 'lol@user', '#bots');
        self::$Nicks->join('knivey', 'lol@user', '#zen');
        self::$Nicks->join('kurizu', 'hil@user', '#bots');
        self::$Nicks->join('kurizu', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#corn');
        self::$Nicks->join('kurizu2', 'hi@user', '#poop');
        self::$Nicks->join('kurizu2', 'hi@user', '#bots');
        self::$Nicks->part('kurizu2', '#bots');
        $this->stdChecksA();
    }
    
    function testPartLastChan() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->part('knivey', '#bots');
    	$this->assertEmpty(self::$Nicks->ppl);
    }
    
    function testKick() {
        self::$Nicks->join('knivey', 'lol@user', '#bots');
        self::$Nicks->join('knivey', 'lol@user', '#zen');
        self::$Nicks->join('kurizu', 'hil@user', '#bots');
        self::$Nicks->join('kurizu', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#corn');
        self::$Nicks->join('kurizu2', 'hi@user', '#poop');
        self::$Nicks->join('kurizu2', 'hi@user', '#bots');
        self::$Nicks->kick('kurizu2', '#bots');
        $this->stdChecksA();
    }
    
    function testQuit() {
        self::$Nicks->join('knivey', 'lol@user', '#bots');
        self::$Nicks->join('knivey', 'lol@user', '#zen');
        self::$Nicks->join('kurizu', 'hil@user', '#bots');
        self::$Nicks->join('kurizu', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#corn');
        self::$Nicks->join('kurizu2', 'hi@user', '#poop');
        self::$Nicks->join('kurizu3', 'hi@user', '#bots');
        self::$Nicks->join('kurizu3', 'hi@user', '#corn');
        self::$Nicks->join('kurizu3', 'hi@user', '#poop');
        self::$Nicks->join('kurizu3', 'hi@user', '#pomg');
        self::$Nicks->quit('kurizu3');
        $this->stdChecksA();
        $this->assertEquals('', self::$Nicks->n2h('kurizu3'), 'kurizu3 quit but still has host');
        $this->assertCount(0, self::$Nicks->nickChans('kurizu3'), 'kurizu3 quit but still has chans');
    }
    
    function stdChecksA($modes = true) {
        $this->assertEquals('lol@user', self::$Nicks->n2h('knivey'), 'wrong host for knivey');
        $this->assertEquals('hi@user', self::$Nicks->n2h('kurizu'), 'wrong host for kurizu');
        $this->assertEquals('hi@user', self::$Nicks->n2h('kurizu2'), 'wrong host for kurizu2');
        $this->assertEquals('hi@user', self::$Nicks->n2h('KURIZU2'), 'nick2host CASE insensitive fail');
        $this->assertEquals('', self::$Nicks->n2h('noone'), '"noone" returned host');
        $this->assertCount(2, self::$Nicks->h2n('hi@user'), 'wrong count of nick for hi@user');
        $this->assertCount(1, self::$Nicks->h2n('lol@user'), 'wrong count of nicks for lol@user');
        $this->assertCount(0, self::$Nicks->h2n('poop@user'), 'wrong count of nicks for lol@user');
        $this->assertContains('kurizu', self::$Nicks->h2n('hi@user'), 'hi@user wrong nicks');
        $this->assertContains('kurizu2', self::$Nicks->h2n('hi@user'), 'hi@user wrong nicks');
        $this->assertContains('knivey', self::$Nicks->h2n('lol@user'), 'lol@user wrong nicks');
        $this->assertCount(2, self::$Nicks->nickChans('knivey'), 'wrong num of chans for knivey');
        $this->assertCount(2, self::$Nicks->nickChans('kurizu'), 'wrong num of chans for kurizu');
        $this->assertCount(3, self::$Nicks->nickChans('kurizu2'), 'wrong num of chans for kurizu2');
        $this->assertCount(2, self::$Nicks->nickChans('KNIVEY'), 'wrong num of chans for knivey CASE');
        $this->assertCount(0, self::$Nicks->nickChans('lol'), 'wrong num of chans for lol');
        $this->assertArrayHasKey('#bots', self::$Nicks->nickChans('knivey'), 'knivey wrong chans');
        $this->assertArrayHasKey('#zen', self::$Nicks->nickChans('knivey'), 'knivey wrong chans');
        $this->assertArrayHasKey('#bots', self::$Nicks->nickChans('kurizu'), 'kurizu wrong chans');
        $this->assertArrayHasKey('#beer', self::$Nicks->nickChans('kurizu'), 'kurizu wrong chans');
        $this->assertArrayHasKey('#beer', self::$Nicks->nickChans('kurizu2'), 'kurizu2 wrong chans');
        $this->assertArrayHasKey('#corn', self::$Nicks->nickChans('kurizu2'), 'kurizu2 wrong chans');
        $this->assertArrayHasKey('#poop', self::$Nicks->nickChans('kurizu2'), 'kurizu2 wrong chans');
        $timea = self::$Nicks->nickChans('knivey')['#bots']['jointime'];
        $timeb = self::$Nicks->nickChans('kurizu')['#bots']['jointime'];
        $timec = self::$Nicks->nickChans('kurizu2')['#corn']['jointime'];
        $this->assertTrue($timea > self::$timemin && $timea < self::$timemax, 'Join time invalid');
        $this->assertTrue($timeb > self::$timemin && $timeb < self::$timemax, 'Join time invalid');
        $this->assertTrue($timec > self::$timemin && $timec < self::$timemax, 'Join time invalid');
        if($modes) {
            $this->stdChecksAmodes();
        }
    }
    
    function stdChecksAmodes() {
        $this->assertCount(0, self::$Nicks->nickChans('knivey')['#bots']['modes'], 'knivey bad modes');
        $this->assertCount(0, self::$Nicks->nickChans('kurizu')['#bots']['modes'], 'kurizu bad modes');
        $this->assertCount(0, self::$Nicks->nickChans('kurizu2')['#corn']['modes'], 'kurizu2 bad modes');
    }
    
    function testOp() {
        self::$Nicks->join('knivey', 'lol@user', '#bots');
        self::$Nicks->join('knivey', 'lol@user', '#zen');
        self::$Nicks->join('kurizu', 'hil@user', '#bots');
        self::$Nicks->join('kurizu', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#corn');
        self::$Nicks->join('kurizu2', 'hi@user', '#poop');
        self::$Nicks->Op('knivey', '#bots');
        self::$Nicks->Op('kurizu', '#bots');
        self::$Nicks->Op('kurizu', '#beer');
        self::$Nicks->Op('kurizu2', '#beer');
        self::$Nicks->Op('kurizu2', '#poop');
        self::$Nicks->Op('kurizu2', '#corn');
        self::$Nicks->DeOp('kurizu2', '#corn');
        $this->stdChecksA(false);
        $this->assertCount(0, self::$Nicks->nickChans('kurizu2')['#corn']['modes'], 'kurizu2 #corn bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('kurizu2')['#poop']['modes'], 'kurizu2 bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('kurizu2')['#beer']['modes'], 'kurizu2 bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('kurizu')['#beer']['modes'], 'kurizu bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('kurizu')['#bots']['modes'], 'kurizu bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('knivey')['#bots']['modes'], 'knivey bad modes');
        $this->assertCount(0, self::$Nicks->nickChans('knivey')['#zen']['modes'], 'knivey bad modes');
        $this->assertTrue(self::$Nicks->isOp('knivey', '#bots'));
        $this->assertTrue(self::$Nicks->isOp('kurizu', '#bots'));
        $this->assertTrue(self::$Nicks->isOp('kurizu2', '#beer'));
        $this->assertFalse(self::$Nicks->isOp('kurizu2', '#corn'));
        $this->assertFalse(self::$Nicks->isOp('knivey', '#corn'));
        $this->assertFalse(self::$Nicks->isOp('lol', '#lol'));
        $this->assertFalse(self::$Nicks->isOp('lol', '#bots'));
        $this->assertFalse(self::$Nicks->isOp('kurizu2', '#bots'));
        self::$Nicks->DeOp('knivey', '#bots');
        self::$Nicks->DeOp('kurizu', '#bots');
        self::$Nicks->DeOp('kurizu', '#beer');
        self::$Nicks->DeOp('kurizu2', '#beer');
        self::$Nicks->DeOp('kurizu2', '#poop');
        self::$Nicks->DeOp('kurizu2', '#corn');
        $this->stdChecksAmodes();
    }
    
    function testVoice() {
        self::$Nicks->join('knivey', 'lol@user', '#bots');
        self::$Nicks->join('knivey', 'lol@user', '#zen');
        self::$Nicks->join('kurizu', 'hil@user', '#bots');
        self::$Nicks->join('kurizu', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#beer');
        self::$Nicks->join('kurizu2', 'hi@user', '#corn');
        self::$Nicks->join('kurizu2', 'hi@user', '#poop');
        self::$Nicks->Voice('knivey', '#bots');
        self::$Nicks->Voice('kurizu', '#bots');
        self::$Nicks->Voice('kurizu', '#beer');
        self::$Nicks->Voice('kurizu2', '#beer');
        self::$Nicks->Voice('kurizu2', '#poop');
        self::$Nicks->Voice('kurizu2', '#corn');
        self::$Nicks->DeVoice('kurizu2', '#corn');
        $this->stdChecksA(false);
        $this->assertCount(0, self::$Nicks->nickChans('kurizu2')['#corn']['modes'], 'kurizu2 #corn bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('kurizu2')['#poop']['modes'], 'kurizu2 bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('kurizu2')['#beer']['modes'], 'kurizu2 bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('kurizu')['#beer']['modes'], 'kurizu bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('kurizu')['#bots']['modes'], 'kurizu bad modes');
        $this->assertCount(1, self::$Nicks->nickChans('knivey')['#bots']['modes'], 'knivey bad modes');
        $this->assertCount(0, self::$Nicks->nickChans('knivey')['#zen']['modes'], 'knivey bad modes');
        $this->assertTrue(self::$Nicks->isVoice('knivey', '#bots'));
        $this->assertTrue(self::$Nicks->isVoice('kurizu', '#bots'));
        $this->assertTrue(self::$Nicks->isVoice('kurizu2', '#beer'));
        $this->assertFalse(self::$Nicks->isVoice('kurizu2', '#corn'));
        $this->assertFalse(self::$Nicks->isVoice('knivey', '#corn'));
        $this->assertFalse(self::$Nicks->isVoice('lol', '#lol'));
        $this->assertFalse(self::$Nicks->isVoice('lol', '#bots'));
        $this->assertFalse(self::$Nicks->isVoice('kurizu2', '#bots'));
        self::$Nicks->DeVoice('knivey', '#bots');
        self::$Nicks->DeVoice('kurizu', '#bots');
        self::$Nicks->DeVoice('kurizu', '#beer');
        self::$Nicks->DeVoice('kurizu2', '#beer');
        self::$Nicks->DeVoice('kurizu2', '#poop');
        self::$Nicks->DeVoice('kurizu2', '#corn');
        $this->stdChecksAmodes();
    }
    
    function testOpNotOnChan() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->join('kniveyb', 'lol@user', '#botstaff');
    	self::$Nicks->Op('knivey', '#bots');
    	self::$Nicks->Op('knivey', '#botstaff');
    	$this->assertFalse(self::$Nicks->isOp('knivey', '#botstaff'));
    	$this->assertArrayNotHasKey('#botstaff', self::$Nicks->nickChans('knivey'));
    }
    
    function testOpChanNotExists() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->Op('knivey', '#bots');
    	self::$Nicks->Op('knivey', '#botst');
    	$this->assertArrayNotHasKey('#botst', self::$Nicks->nickChans('knivey'));
    }
    
    function testVoiceNotOnChan() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->join('kniveyb', 'lol@user', '#botstaff');
    	self::$Nicks->Voice('knivey', '#bots');
    	self::$Nicks->Voice('knivey', '#botstaff');
    	$this->assertFalse(self::$Nicks->isVoice('knivey', '#botstaff'));
    	$this->assertArrayNotHasKey('#botstaff', self::$Nicks->nickChans('knivey'));
    }
    
    function testVoiceChanNotExists() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->Voice('knivey', '#bots');
    	self::$Nicks->Voice('knivey', '#botst');
    	$this->assertArrayNotHasKey('#botst', self::$Nicks->nickChans('knivey'));
    }
    
    function testDeOpNotOnChan() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->join('kniveyb', 'lol@user', '#botstaff');
    	self::$Nicks->Op('knivey', '#botstaff');
    	self::$Nicks->DeOp('knivey', '#botstaff');
    	$this->assertFalse(self::$Nicks->isOp('knivey', '#botstaff'));
    	$this->assertArrayNotHasKey('#botstaff', self::$Nicks->nickChans('knivey'));
    }
    
    function testDeOpChanNotExists() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->DeOp('knivey', '#bots');
    	self::$Nicks->DeOp('knivey', '#botst');
    	$this->assertArrayNotHasKey('#botst', self::$Nicks->nickChans('knivey'));
    }
    
    function testDeVoiceNotOnChan() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->join('kniveyb', 'lol@user', '#botstaff');
    	self::$Nicks->Voice('knivey', '#bots');
    	self::$Nicks->DeVoice('knivey', '#botstaff');
    	$this->assertFalse(self::$Nicks->isVoice('knivey', '#botstaff'));
    	$this->assertArrayNotHasKey('#botstaff', self::$Nicks->nickChans('knivey'));
    }
    
    function testDeVoiceChanNotExists() {
    	self::$Nicks->join('knivey', 'lol@user', '#bots');
    	self::$Nicks->DeVoice('knivey', '#bots');
    	self::$Nicks->DeVoice('knivey', '#botst');
    	$this->assertArrayNotHasKey('#botst', self::$Nicks->nickChans('knivey'));
    }
    
    function testNames() {
        self::$Nicks->names(explode(' ', ':TechConnect.NL.EU.GameSurge.net 353 knivey = #bots :@Etrigan knivey +Oth @ChanServ iron'));
        $this->assertTrue(self::$Nicks->isOp('etrigan', '#bots'));
        $this->assertFalse(self::$Nicks->isOp('knivey', '#bots'));
        $this->assertTrue(self::$Nicks->isVoice('Oth', '#bots'));
        $this->assertFalse(self::$Nicks->isOp('Oth', '#bots'));
        $this->assertFalse(self::$Nicks->isVoice('knivey', '#bots'));
        $this->assertCount(1, self::$Nicks->nickChans('knivey'));
    }
    
    function testWho() {
        self::$Nicks->names(explode(' ', ':TechConnect.NL.EU.GameSurge.net 353 knivey = #bots :@Etrigan knivey +Oth @ChanServ iron'));
        self::$Nicks->who(explode(' ', ':TechConnect.NL.EU.GameSurge.net 354 knivey 777 #bots kni test.lol knivey H*@+d'));
        $this->assertTrue(self::$Nicks->isOp('knivey', '#bots'));
        $this->assertTrue(self::$Nicks->isVoice('knivey', '#bots'));
        $this->assertEquals('kni@test.lol', self::$Nicks->n2h('knivey'));
        self::$Nicks->who(explode(' ', ':TechConnect.NL.EU.GameSurge.net 354 kniveyb 777 #bots kni test.lol kniveyb H*@+d'));
        $this->assertEquals(NULL, self::$Nicks->n2h('kniveyb'));
        self::$Nicks->who(explode(' ', ':TechConnect.NL.EU.GameSurge.net 354 knivey 777 #botstaff kni test.lol knivey H*@+d'));
        $this->assertArrayNotHasKey('#botstaff', self::$Nicks->nickChans('knivey'));
    }
    
    function testUsPart() {
        self::$Nicks->names(explode(' ', ':TechConnect.NL.EU.GameSurge.net 353 knivey = #bots :@Etrigan knivey +Oth @ChanServ'));
        self::$Nicks->names(explode(' ', ':TechConnect.NL.EU.GameSurge.net 353 knivey = #zen :@zb +knivey'));
        self::$Nicks->names(explode(' ', ':TechConnect.NL.EU.GameSurge.net 353 knivey = #lol :knivey @ChanServ iron'));
        self::$Nicks->who(explode(' ', ':TechConnect.NL.EU.GameSurge.net 354 knivey 777 #lol kni test.lol iron H*@+d'));
        $this->assertEquals('kni@test.lol', self::$Nicks->n2h('iron'));
        self::$Nicks->usPart('#lol');
        $this->assertTrue(self::$Nicks->isOp('ChanServ', '#bots'));
        $this->assertEquals(null, self::$Nicks->n2h('iron'));
    }
    
    function testTppl() {
        self::$Nicks->tppl('knivey', 'test@host');
        $this->assertEquals('test@host', self::$Nicks->n2h('knivey'));
        self::$Nicks->tpplClear();
        $this->assertEquals('', self::$Nicks->n2h('knivey'));
        self::$Nicks->join('knivey', 'blah@test', '#bots');
        self::$Nicks->tppl('knivey', 'test@host');
        $this->assertEquals('test@host', self::$Nicks->n2h('knivey'));
        $this->assertArrayNotHasKey('knivey', self::$Nicks->tppl);
        $this->assertEquals(Array('knivey'), self::$Nicks->h2n('test@host'));
        self::$Nicks->tpplClear();
        $this->assertEquals('test@host', self::$Nicks->n2h('knivey'));
        self::$Nicks->tppl('knivey', 'test@host');
        self::$Nicks->join('knivey', 'blah@test', '#bots');
        $this->assertEquals('blah@test', self::$Nicks->n2h('knivey'));
        self::$Nicks->tpplClear();
        $this->assertEquals('blah@test', self::$Nicks->n2h('knivey'));
        
        self::$Nicks->tppl('kniveyb', 'test@blah');
        $this->assertEquals(Array('kniveyb'), self::$Nicks->h2n('test@blah'));
    }
    
    function testNulls() {
        $this->assertEquals(null, self::$Nicks->isOp('knivey', null));
        $this->assertEquals(null, self::$Nicks->isOp(null, '#bots'));
        $this->assertEquals(null, self::$Nicks->isOp(null, null));
        $this->assertEquals(null, self::$Nicks->isVoice('knivey', null));
        $this->assertEquals(null, self::$Nicks->isVoice(null, '#bots'));
        $this->assertEquals(null, self::$Nicks->isVoice(null, null));
    }
    
    protected function tearDown(): void {
        self::$Nicks = null;
    }
}

?>
