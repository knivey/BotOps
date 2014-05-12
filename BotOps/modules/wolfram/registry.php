<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Wolfram Alpha";

    var $require = Array('CmdReg');

    var $ParseUtil = Array(
        'vars' => Array(
            Array('calc', 'v_calc', "query", "WolframAlpha Calculator")
        )
    );

    var $CmdReg = Array(
        'funcs' => Array(
            Array('calc', 'cmd_calc', "", "Send a query to WolframAlpha")
        ),
        'binds' => Array(
            Array('calc', '0', 'calc', "", "", "", '0')
        )
    );
}
?>