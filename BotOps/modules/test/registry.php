<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Tests and Development";

    var $XMLRPC = Array(
        Array('test', 'rpc_test'),
        Array('msg', 'rpc_msg')
    );
    
    var $CmdReg = Array(
        'funcs' => Array(
            Array('eval', 'cmd_eval', "<code>", "evaluate php code"),
            Array('ced', 'cmd_ced', "<code>", "evaluate php code and dump to chan"),
            Array('shell', 'cmd_shell', "bash command", "run stuff in tickers"),
            Array('version', 'cmd_version', "", "display version"),
            Array('xmlrpc', 'cmd_xmlrpc', "<host> <func> <Array('parm1','parm2', ...)>", "send an xmlrpc", 'chan'),
            Array('sysinfo', 'cmd_sysinfo', "", "show some sys info", 'chan')
        ),
        'binds' => Array(
            Array('eval', 'D', 'eval', "", "", "", '3'),
            Array('ced', 'D', 'ced', "", "", "", '3'),
            Array('shell', 'D', 'shell', "", "", "", '3'),
            Array('version', '0', 'version', "", "", "", '0'),
            Array('sysinfo', '0', 'sysinfo', "", "", "", '0', 'chan'),
            Array('xmlrpc', 'D', 'xmlrpc', "", "", "", '3', 'chan')
        )
    );
}

?>