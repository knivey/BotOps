<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Shoutcast & Icecast";

    var $require = Array('CmdReg');

    var $CmdReg = Array(
        'funcs' => Array(
            Array('castinfo', 'cmd_castinfo', "<ip:port>", "Get shoutcast or IceCast info"),
        ),
        'binds' => Array(
			Array('castinfo', '0', 'castinfo', "", "", "", '0'),
        )
    );
}
?>