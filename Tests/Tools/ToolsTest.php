<?php
include_once 'BotOps/Tools/Tools.php';

class ToolsTest extends PHPUnit_Framework_TestCase {
	
	public function testMakenice() {
		$inputa = "hi\nsup";
		$outputa = "hi\r\nsup";
		$inputb = "hi\r\nsup";
	    $outputb = "hi\r\nsup";
	    $inputc = "hi\rsup\n";
	    $outputc = "hi\r\nsup\r\n";
	    $this->assertEquals($outputa, makenice($inputa));
	    $this->assertEquals($outputb, makenice($inputb));
	    $this->assertEquals($outputc, makenice($inputc));
	}
	
	public function testStr2int() {
		$this->assertEquals(2, str2int("\x02"));
	}
	
	public function testConvert() {
		$this->assertEquals('10b', convert(10));
		$this->assertEquals('1kb', convert(1024));
		$this->assertEquals('1mb', convert(pow(1024,2)));
		$this->assertEquals('1gb', convert(pow(1024,3)));
		$this->assertEquals('1tb', convert(pow(1024,4)));
		$this->assertEquals('1pb', convert(pow(1024,5)));
	}
	
	public function testRevbo() {
		$input = "abcd";
		$output = "dcba";
		$this->assertEquals($output, revbo($input));
	}
	
	public function testHexdump() {
		$input = "\x01\x03\xFF";
		$output = "01 03 FF";
		$this->assertEquals($output, hexdump($input));
	}
	
	public function testRidfirst() {
		$input = "abcde";
		$output = "bcde";
		$this->assertEquals($output, ridfirst($input));
		$this->assertEquals("", ridfirst(""));
	}
	
	public function testValidNick() {
		//Make this a little more thourough
		$this->assertFalse(validNick('0kni'));
		$this->assertTrue(validNick('kni'));
	}
	
	public function testArgClean() {
		$input = Array('hi', '', '', 'there');
		$output = Array('hi', 'there');
		$this->assertEquals($output, argClean($input));
	}
	
	public function testNiceArgs() {
		$input = "hi   there knivey";
		$output = Array(3, Array('hi', 'there', 'knivey'));
		$this->assertEquals($output, niceArgs($input));
		$input = "";
		$output = Array(0, Array());
		$this->assertEquals($output, niceArgs($input));
	}
	
	public function testGet_akey_nc() {
		$ar = Array('HI' => 'HI', 'lol' => 'lol');
		$this->assertEquals('HI', get_akey_nc('Hi', $ar));
		$this->assertEquals(NULL, get_akey_nc('i', $ar));
		$this->assertEquals('lol', get_akey_nc('LOL', $ar));
		$this->assertEquals('lol', get_akey_nc('lol', $ar));
	}
	
	public function testCisin() {
		$input = 'abcde';
		$this->assertTrue(cisin($input, 'a'));
		$this->assertTrue(cisin($input, 'edcba'));
		$this->assertFalse(cisin($input, 'f'));
		$this->assertFalse(cisin($input, 'fa'));
	}
	
	public function testAclear() {
		$input = Array(1,2,3,4);
		aclear($input);
		$this->assertEmpty($input);
	}
	
	public function testArray_padding() {
		$input = Array('a','ab','abc ');
		$o = array_padding($input);
		$this->assertEquals('a    ', $o[0]);
		$this->assertEquals('ab   ', $o[1]);
		$this->assertEquals('abc  ', $o[2]);
	}
	
	public function testMulti_array_pading() {
		$input = Array(
			Array('a', 'abcd', 'ab'),
			Array('abc', 'ab', 'abcdef'),
		);
		$o = multi_array_padding($input);
		$this->assertEquals(Array('a   ', 'abcd ', 'ab     '), $o[0]);
		$this->assertEquals(Array('abc ', 'ab   ', 'abcdef '), $o[1]);
	}
	
	public function testBar_meter() {
		$this->assertEquals(chr(22)."    50".chr(22)."%   ", bar_meter(5, 10));
		$this->assertEquals(chr(22)."    ".chr(22)."30%   ", bar_meter(3, 10));
		$this->assertEquals("Error: bar overfull", bar_meter(15, 10));
		$this->assertEquals("Error: bar too small", bar_meter(1, 9));
	}
	
	public function testPmatch() {
		$this->assertTrue(pmatch('*kni*', 'hi knivey'));
		$this->assertTrue(pmatch('*kNi*', 'hi kNivey', FALSE));
		$this->assertFalse(pmatch('*kni*', 'hi kNivey', FALSE));
		$this->assertTrue(pmatch('k?i', 'kni'));
		$this->assertFalse(pmatch('k?i', 'knni'));
		$this->assertTrue(pmatch('k?i*', 'knivey'));
		$this->assertTrue(pmatch('knivey', 'kniVEY'));
		$this->assertFalse(pmatch('knivey', 'kniVEY', FALSE));
	}
	
	public function testIsemail() {
		$this->assertTrue(isemail('knivey@botops.net'));
		$this->assertFalse(isemail('knivey'));
	}
	
	public function testArg_range() {
		$input = Array('a', 'b', 'c', 'd');
		$this->assertEquals('a', arg_range($input, 0, 0));
		$this->assertEquals('a b', arg_range($input, 0, 1));
		$this->assertEquals('b', arg_range($input, 1, 1));
		$this->assertEquals('b c d', arg_range($input, 1, -1));
		$this->assertEquals('', arg_range($input, 10, -1));
		$this->assertEquals('', arg_range($input, 1, 0));
		$this->assertEquals('', arg_range(Array(), 0, -1));
		$this->assertEquals('', arg_range($input, 0, -2));
		$this->assertEquals('  ', arg_range(Array('','',''), 0, -1));
	}
	
	public function testReEncode() {
		$this->assertEquals('asdf', ReEncode('asdf'));
		$this->assertEquals('asdf(ReEncode)open(/ReEncode)asdf', ReEncode('asdf(ReEncode)asdf'));
		$this->assertEquals('asdf(ReEncode)close(/ReEncode)asdf', ReEncode('asdf(/ReEncode)asdf'));
		$this->assertEquals('(ReEncode)99(/ReEncode)', ReEncode('&#99;'));
	}
	
	public function testReDeEncode() {
		$this->assertEquals('asdf', ReDeEncode('asdf'));
		$this->assertEquals('(ReEncode)', ReDeEncode('(ReEncode)open(/ReEncode)'));
		$this->assertEquals('(/ReEncode)', ReDeEncode('(ReEncode)close(/ReEncode)'));
		$this->assertEquals('c', ReDeEncode('(ReEncode)'. ord('c') . '(/ReEncode)'));
	}
	
	public function testArReDeEncode() {
		$this->assertEquals('(/ReEncode)', ArReDeEncode('(ReEncode)close(/ReEncode)'));
		$this->assertEquals(Array('(/ReEncode)'=>'(ReEncode)'), ArReDeEncode(Array('(ReEncode)close(/ReEncode)' => '(ReEncode)open(/ReEncode)')));
		$this->assertEquals(Array('c'), ArReDeEncode(Array('(ReEncode)'. ord('c') . '(/ReEncode)')));
		$this->assertEquals((int)5, ArReDeEncode((int)5));
		$this->assertEquals(Array(Array('c')), ArReDeEncode(Array(Array('(ReEncode)'. ord('c') . '(/ReEncode)'))));
	}
	
	public function testMakeArgs() {
		$this->assertEquals(Array('a','b','c'), makeArgs('a b c'));
		$this->assertEquals(Array('a','b','c'), makeArgs('a b  c'));
		$this->assertEquals(Array('a','b','c'), makeArgs('a b "c"'));
		$this->assertEquals(Array('a','b c','d'), makeArgs('a "b c" d'));
		$this->assertEquals(Array('a','b "c','d'), makeArgs('a "b \"c" d'));
		$this->assertEquals(4, makeArgs('a ""b d'));
		$this->assertEquals(4, makeArgs('a b"oops" d'));
		$this->assertEquals(Array('a','','c'), makeArgs('a "" c'));
		
		$actual = makeArgs('authserv "as auth" "username" \"password');
		$expected = Array(
				'authserv',
				'as auth',
				'username',
				'"password',
		);
		$this->assertEquals($expected, $actual);
		
		$actual = makeArgs('authserv "as auth username password"');
		$expected = Array(
				'authserv',
				'as auth username password',
		);
		$this->assertEquals($expected, $actual);
		
		$actual = makeArgs('authserv "as auth" "username "password');
		$expected = strlen('authserv "as auth" "username "'); // should only take space after end "
		$this->assertEquals($expected, $actual);
	}
}



















?>