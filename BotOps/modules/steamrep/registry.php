<?php

class registry
{

    var $version     = '1.0';
    var $author      = 'knivey';
    var $description = "Steam Reputation";
    var $require     = Array('CmdReg');
    var $CmdReg = Array(
        'funcs' => Array(
            Array('steamrep', 'cmd_steamrep', "<steamid|alias>", "Lookup steamrep info"),
        ),
        'binds' => Array(
            Array('steamrep', '0', 'steamrep', "", "", "", '0'),
        )
    );

}

?>