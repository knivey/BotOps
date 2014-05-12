<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Shit My Dad Says Twitter Feed";

    var $require = Array('CmdReg');

    var $CmdReg = Array(
        'funcs' => Array(
            Array('smds', 'cmd_smds', "", "View a random smds"),
        ),
        'binds' => Array(
            Array('smds', 0, 'smds', "", "", "", 0),
        )
    );
}

?>
