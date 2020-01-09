<?php

class registry {
    var $author = 'kNiVeY';
    var $version = '1.0';
    var $description = 'Provide many internal tools and our $var system';

    var $require = Array('user');

    var $slots = Array(
        Array('loaded', 'ModuleManager', 'load'),
        Array('reloaded', 'ModuleManager', 'reload'),
        Array('unloaded', 'ModuleManager', 'unload')
    );
    
    var $ParseUtil = Array(
        'vars' => Array(
            Array('date', 'v_date', "Current date/time"),
            Array('rainbow', 'v_rainbow', "RainbowText", "text"),
            Array('arg', 'v_a', 'Command\'s args like mirc\'s $1- stuff', "<argnum>[-]"),
            Array('bar', 'v_bar', "Bar style meter", "<lowNum>", "<highNum>"),
            Array('rand', 'v_rand', "Random number", "<lowNum>", "<highNum>"),
            Array('random', 'v_rand', "Random number", "<lowNum>", "<highNum>"),
            Array('nick', 'v_nick', "nick using command"),
            Array('rnick', 'v_rnick', "random nickname in the channel"),
            Array('chan', 'v_target', "chan command is used in"),
            Array('na', 'v_na', "nick or args if set"),
            Array('colorcap', 'v_colorcap', "capitalize with color", "<text>", "<capc>", "<textc>"),
            Array('host', 'v_host', "users hostmask"),
            Array('rnoun', 'v_rnoun', "random noun"),
            Array('rverb', 'v_rverb', "random verb"),
            Array('radj', 'v_radj', "random adjective"),
            Array('trig', 'v_trig', "channel trigger"),
        )
    );

    var $CmdReg = Array(
        'funcs' => Array(
            Array('pstats', 'cmd_pstats', "", "Show statistics and info on our var parsing."),
            Array('say', 'cmd_say', "<text>", "Say a line of text after parsing it."),
            Array('act', 'cmd_act', "<text>", "/me a line of text after parsing it.")
        ),
        'binds' => Array(
            Array('pstats', '0', 'pstats', "", "", "", '0'),
            Array('say', '0', 'say', "", "", "", '0'),
            Array('act', '0', 'act', "", "", "", '0')
        )
    );
}
?>