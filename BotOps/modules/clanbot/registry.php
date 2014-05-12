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
            Array('bindinfo', 'cmd_bindinfo', "<bind>", "Show information about a bind")
        ),
        'binds' => Array(
            Array('bind', '1', 'bind', "", "", "", '1'),
            Array('unbind', '1', 'unbind', "", "", "", '1'),
            Array('bindtype', '1', 'bindtype', "", "", "", '1'),
            Array('binds', '1', 'binds', "", "", "", '0'),
            Array('bindinfo', '1', 'bindinfo', "", "", "", '0')
        )
    );
}
?>
