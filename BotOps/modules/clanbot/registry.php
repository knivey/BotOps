<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Clanbot commands";

    var $require = Array('CmdReg');

    var $SetReg = Array(
        'channel' => Array(
            Array('bindtype', '1', "Default response for binds", "notice", "notice", "act", "chan"),
            Array('theme', '1', "Theme to use for showing binds", '$cmd: $bind')
        )
    );
    
    var $ParseUtil = Array(
        'vars' => Array(
            Array('bind', 'v_bindvalue', "bind value"),
            Array('binds', 'v_binds', 'list of binds'),
            Array('tbinds', 'v_tbinds', 'list of binds'),
        )
    );

    var $CmdReg = Array(
        'catch' => 'cmdCatch',
        'funcs' => Array(
            Array('bind', 'cmd_bind', "<name> <data>", "Add/set a new bind"),
            Array('unbind', 'cmd_unbind', "<name>", "Remove a bind"),
            Array('bindtype', 'cmd_bindtype', "<name> [default|notice|act|chan]", "Change how a bind responds"),
            Array('binds', 'cmd_binds', "", "Show binds"),
            Array('bindinfo', 'cmd_bindinfo', "<bind>", "Show information about a bind"),
            Array('hidebind', 'cmd_hidebind', "<bind>", "Make bind hidden from $\2\2binds and $\2\2tbinds"),
            Array('unhidebind', 'cmd_unhidebind', "<bind>", "Make bind visible in $\2\2binds and $\2\2tbinds"),
            Array('bindalias', 'cmd_bindalias', "<alias> <bind>", "Make <alias> an alias for <bind>"),
        ),
        'binds' => Array(
            Array('bind', '1', 'bind', "", "", "", '1'),
            Array('unbind', '1', 'unbind', "", "", "", '1'),
            Array('bindtype', '1', 'bindtype', "", "", "", '1'),
            Array('binds', '1', 'binds', "", "", "", '0'),
            Array('bindinfo', '1', 'bindinfo', "", "", "", '0'),
            Array('hidebind', '1', 'hidebind', "", "", "", '0'),
            Array('unhidebind', '1', 'unhidebind', "", "", "", '0'),
            Array('bindalias', '1', 'bindalias', "", "", "", '0'),
        )
    );
}
?>
