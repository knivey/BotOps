<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class registry {
    var $author = 'kNiVeY';
    var $version = '1.0';
    var $description = "Keep track of all commands";

    var $require = Array('user');

    var $slots = Array(
        Array('loaded', 'ModuleManager', 'load'),
        Array('reloaded', 'ModuleManager', 'reload'),
        Array('unloaded', 'ModuleManager', 'unload')
    );

    //[colname] [type] [null] [key] [default] [extra])
    var $logs = Array(
        Array('date', 'varchar(255)'),
        Array('cmd', 'varchar(255)'),
        Array('override', 'bool'),
        Array('nick', 'varchar(255)'),
        Array('hand', 'varchar(255)'),
        Array('target', 'varchar(255)'),
        Array('host', 'text'),
        Array('msg', 'text'),
        Array('bot', 'varchar(255)'),
    );

    var $ParseUtil = Array(
        'vars' => Array(
            Array('args', 'v_args', "full text passed to command"),
            Array('cmd', 'v_cmd', "command name being used")
        )
    );

    var $leaf = Array(
        'hooks' => Array(
            Array('inmsg', 'msg', "")
        )
    );
    
    var $XMLRPC = Array(
        Array('unbind', 'rpc_unbind'),
        Array('bind', 'rpc_bind'),
        Array('modcmd', 'rpc_modcmd'),
    );

    var $CmdReg = Array(
        'funcs' => Array(
            Array('command', 'cmd_command', "<chan|pm> <command>", "Show information about a command.", 'chan'),
            Array('tbind', 'cmd_bind', "<chan|pm> <bind> <module> <function> [args]", "Test command to create/update bindings.", 'chan'),
            Array('tunbind', 'cmd_unbind', "<chan|pm> <bind>", "Test command to remove bindings.", 'chan'),
            Array('modcmd', 'cmd_modcmd', "<chan|pm> <bind> <setting> <value>", "Test command to modify bindings.", 'chan'),
            Array('showfuncs', 'cmd_showfuncs', "[mod]", "Show functions provided by a module.", 'chan'),
            Array('cmdhistory', 'cmd_cmdhistory', "", "Show short history of commands used in channel", 'chan'),
            Array('gag', 'cmd_gag', "<hostmask> <duration> <reason>", "Keep hostmask from using commands", 'chan'),
            Array('isgag', 'cmd_isgag', "<host>", "Check if a host is gaged", 'chan'),
        ),
        'binds' => Array(
            Array('command', '0', 'command', "", "", "", '0', 'chan'),
            Array('showfuncs', '0', 'showfuncs', "", "", "", '0', 'chan'),
            Array('tbind', 'D', 'tbind', "", "", "", '3', 'chan'),
            Array('tunbind', 'D', 'tunbind', "", "", "", '3', 'chan'),
            Array('modcmd', 'D', 'modcmd', "", "", "", '3', 'chan'),
            Array('cmdhistory', '4', 'cmdhistory', "", "", "", '0', 'chan'),
            Array('gag', 'O', 'gag', "", "", "", '3', 'chan'),
            Array('isgag', 'O', 'isgag', "", "", "", '0', 'chan'),
        )
    );
}
?>