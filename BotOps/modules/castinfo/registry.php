<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Shoutcast & Icecast";

    var $require = Array('CmdReg');

    var $CmdReg = Array(
        'funcs' => Array(
            Array('castinfo', 'cmd_castinfo', "<ip:port>", "Get shoutcast or IceCast info"),
            Array('casttrack', 'cmd_casttrack', "<ip:port>", "Connect to a stream and update on changes."),
            Array('caststop', 'cmd_caststop', "<ip:port>", "Stop a casttrack"),
        ),
        'binds' => Array(
			Array('castinfo', '0', 'castinfo', "", "", "", '0'),
            Array('casttrack', '0', 'casttrack', "", "", "", '0'),
            Array('caststop', '0', 'caststop', "", "", "", '0'),
        )
    );
}
?>