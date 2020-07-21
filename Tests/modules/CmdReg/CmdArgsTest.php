<?php

include('BotOps/modules/CmdReg/CmdArgs.php');

class CmdArgsTest extends PHPUnit\Framework\TestCase
{
    function testNoArgs()
    {
        $args = new CmdArgs('');
        $this->assertEmpty($args->args);
        $args->parse("arst tsra moo");
        $this->assertEmpty($args->args);
    }

    function testReqArg()
    {
        $args = new CmdArgs('<foo>');
        $args->parse('moo boo poo');
        $this->assertEquals('moo', $args->getArg('foo')->val);
        $this->assertEquals('moo', $args->args[0]->val);

        $args = new CmdArgs('<foo>');
        $args->parse('m)_M@"');
        $this->assertEquals('m)_M@"', $args->args[0]->val);
        $this->assertEquals('m)_M@"', $args->getArg('foo')->val);

        $this->expectException('Exception');
        $args = new CmdArgs('<foo>');
        $args->parse('');
    }

    function testLeadingSpaces()
    {
        $args = new CmdArgs('<foo>');
        $args->parse('   moo boo poo');
        $this->assertEquals('moo', $args->getArg('foo'));

        $args = new CmdArgs('<foo> <shoe>');
        $args->parse('  moo  boo poo');
        $this->assertEquals('moo', $args->getArg('foo'));
        $this->assertEquals('boo', $args->getArg('shoe'));
    }

    function testReqAndOpt()
    {
        $args = new CmdArgs('<foo> [bar]');
        $args->parse('moo boo poo');
        $this->assertEquals('moo', $args->getArg('foo'));
        $this->assertEquals('moo', $args->args[0]);
        $this->assertEquals('boo', $args->getArg('bar'));
        $this->assertEquals('boo', $args->args[1]);
        $this->assertCount(2, $args->args);

        $args = new CmdArgs('<foo> [bar]');
        $args->parse('moo');
        $this->assertEquals('moo', $args->getArg('foo'));
        $this->assertEquals('', $args->getArg('bar'));

        $this->expectException('Exception');
        $args = new CmdArgs('<foo> [bar]');
        $args->parse('');
    }

    function testArrayAccess()
    {
        $args = new CmdArgs("<Account> <barf> [a_r]");
        $args->parse('moo boo poo');
        $this->assertEquals('moo', $args['Account']);
        $this->assertEquals('boo', $args[1]);
        $this->assertEquals('poo', $args['a_r']);
        $this->assertEquals(null, $args['a']);
        $this->assertEquals(null, $args[5]);
        $this->assertTrue(isset($args['a_r']));
        $this->assertFalse(isset($args['f']));
        $this->assertFalse(isset($args[9]));
        $args = new CmdArgs("<Account> <barf> [a_r]");
        $args->parse('moo boo');
        $this->assertFalse(isset($args['a_r']));
        $this->assertEquals(null, $args['a_r']);
        $this->assertFalse(isset($args[2]));
        $this->assertEquals(null, $args[2]);
    }

    function testReqMultiword()
    {
        $args = new CmdArgs('<foo>...');
        $args->parse('moo boo poo');
        $this->assertEquals('moo boo poo', $args->getArg('foo'));

        $args = new CmdArgs('<foo>...');
        $args->parse('moo');
        $this->assertEquals('moo', $args->getArg('foo'));

        $this->expectException('Exception');
        $args = new CmdArgs('<foo>...');
        $args->parse('');
    }

    function testOptMultiword()
    {
        $args = new CmdArgs('[foo]...');
        $args->parse('moo boo poo');
        $this->assertEquals('moo boo poo', $args->getArg('foo'));

        $args = new CmdArgs('[foo]...');
        $args->parse('moo');
        $this->assertEquals('moo', $args->getArg('foo'));

        $args = new CmdArgs('[foo]...');
        $args->parse('');
        $this->assertEquals('', $args->getArg('foo'));
    }

    function testOptbeforeReq()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('[bar] <foo>');
    }

    function testOptbeforeReqMulti()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('[bar] <foo>...');
    }

    function testOptbeforeOpt()
    {
        $args = new CmdArgs('[bar] [foo]');
        $args->parse('test');
        $this->assertFalse(isset($args['foo']));
        $this->assertEquals('test', $args['bar']);
        $args->parse('test moo');
        $this->assertEquals('test', $args['bar']);
        $this->assertEquals('moo', $args['foo']);
    }

    function testOptbeforeOptMulti()
    {
        $args = new CmdArgs('[bar] [foo] [moo]...');
        $args->parse('test');
        $this->assertFalse(isset($args['foo']));
        $this->assertFalse(isset($args['moo']));
        $this->assertEquals('test', $args['bar']);
        $args->parse('test moo');
        $this->assertEquals('test', $args['bar']);
        $this->assertEquals('moo', $args['foo']);
        $this->assertFalse(isset($args['moo']));
        $args->parse('test moo blah blah blah');
        $this->assertEquals('test', $args['bar']);
        $this->assertEquals('moo', $args['foo']);
        $this->assertEquals('blah blah blah', $args['moo']);
    }

    function testOptbeforeOptMultiReq()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('[bar] [foo]... <moo>');
    }

    function testMultibefore()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('[foo]... [bar]');
    }

    function testMultibefore2()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('<bar>... [foo]');
    }

    function testMultibefore3()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('<bar>... <foo>');
    }

    function testInvalidSyntax()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('<foo>>');
    }

    function testInvalidSyntax2()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('foo');
    }

    function testInalidSyntax3()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('<[arst]>');
    }

    function testInvalidSyntax4()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('[<arst>]');
    }

    function testInvalidSyntax5()
    {
        $this->expectException('Exception');
        $args = new CmdArgs('<moo lol>');
    }

    function testValidSyntax1()
    {
        $args = new CmdArgs("<Account|Chan> <OldMod.OldSetName> <NewMod.NewSetName> [a_r]");
        $args->parse('moo boo poo woo');
        $this->assertEquals('moo', $args->getArg('Account|Chan'));
        $this->assertEquals('boo', $args->args[1]);
        $this->assertEquals('poo', $args->getArg('NewMod.NewSetName'));
        $this->assertEquals('woo', $args->getArg('a_r'));
        $this->assertCount(4, $args->args);
    }

    function testArgWhenNotReq()
    {
        $args = new CmdArgs("");
        $args->parse('moo boo poo woo');
        $this->assertCount(0, $args->args);
    }

}

?>