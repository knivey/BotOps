<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "MyAnimeList interface";

    var $require = Array('CmdReg');

    var $CmdReg = Array(
        'funcs' => Array(
            Array('mal', 'cmd_mal', "<search>", "Search for info on anime"),
        ),
        'binds' => Array(
            Array('mal', '0', 'mal', "", "", "", '0'),
        )
    );
}
?>