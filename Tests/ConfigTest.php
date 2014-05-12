<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
include('BotOps/Config.inc');

class ConfigTest extends PHPUnit_Framework_TestCase {
    protected static $testFile = 'Tests/main.conf';

    public function testReadFileFails() {
    	$config = new Config(self::$testFile);
    	$this->assertFalse($config->readFile(''));
    	$this->assertFalse($config->readFile('nonexistingfile'));
    	$this->assertTrue($config->readFile(self::$testFile));
    }
    
    public function testConstruct() {
    	$config = new Config(self::$testFile);
    	$this->assertNotEmpty($config->info);
    }
    
    public function testgetInfo() {
    	$config = new Config(self::$testFile);
        $info = $config->getInfo();
        $this->assertInternalType('array', $info, "getInfo() did not return Array");
        
        $this->assertArrayHasKey('mysql', $info, "missing [mysql]");
        $this->assertArrayHasKey('user', $info['mysql'], "missing [mysql][user]");
        $this->assertArrayHasKey(0, $info['mysql']['user'], "missing [mysql][user][0]");
        $this->assertEquals(1, count($info['mysql']['user']), "[mysql][user] count() not equal 1 got: " . serialize($info['mysql']['user']));
        $this->assertEquals('mysqluser', $info['mysql']['user'][0], "[mysql][user][0] does not contain expected string");
        
        $this->assertArrayHasKey('irc', $info, "missing [irc]");
        $this->assertArrayHasKey('authserv', $info['irc'],"missing [irc][authserv]");
        $this->assertArrayHasKey(0, $info['irc']['authserv'], "missing [irc][authserv][0]");
        $this->assertEquals(1, count($info['irc']['authserv']),"[irc][authserv] count() not equal 1 got: " . serialize($info['irc']['authserv']));
        $this->assertEquals('as auth username password', $info['irc']['authserv'][0], "[irc][authserv][0] does not contain expected string");
    }
}

?>
