<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

include_once 'BotOps/Tools/Duration.inc';

class DurationTest extends PHPUnit_Framework_TestCase {
    public function teststring2seconds() {
    	global $Duration_periods;
        $this->assertEquals(1, string2Seconds('1s'));
        $this->assertEquals(60, string2Seconds('1m'));
        $this->assertEquals(3600, string2Seconds('1h'));
        $this->assertEquals(3600 * 24, string2Seconds('1d'));
        $this->assertEquals(3600 * 24 * 7, string2Seconds('1w'));
        $this->assertEquals($Duration_periods['M'], string2Seconds('1M'));
        $this->assertEquals($Duration_periods['y'], string2Seconds('1y'));
        $this->assertEquals($Duration_periods['y'], string2Seconds('12M'));
        $this->assertNotInternalType('int', string2Seconds('5z'));
    }
    
    public function testDuration_int2array() {
    	$expected = Array(
    			'y' => 1,
    			'M' => 2,
    			'w' => 3,
    			'h' => 5,
    			'm' => 6,
    			's' => 7,
    	);
    	$this->assertEquals($expected, Duration_int2array(string2Seconds('1y2M3w5h6m7s')));
    	$this->assertNull(Duration_int2array(0));
    }
    
    public function testDuration_array2string() {
    	$this->assertFalse(Duration_array2string('notanarray'));
    	$input = Array(
    			'y' => 1,
    			'M' => 2,
    			'w' => 3,
    			'h' => 5,
    			'm' => 6,
    			's' => 7,
    	);
    	$this->assertEquals("1y, 2M, 3w, 5h, 6m, 7s", Duration_array2string($input));
    }
    
    public function testDuration_toString() {
    	$this->assertEquals('1m', Duration_toString(60));
    }
}
?>
