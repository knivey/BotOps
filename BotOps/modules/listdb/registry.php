<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Channel List database";

    var $require = Array('CmdReg');

    var $CmdReg = Array(
        'funcs' => Array(
            Array('csearch', 'cmd_csearch', "[#chan]", "Peek at a channel"),
            Array('listinfo', 'cmd_listinfo', "", "listdb info"),
            Array('listquiet', 'cmd_listquiet', "", "toggle listdb quitemode"),
        ),
        'binds' => Array(
            Array('csearch', '0', 'csearch', "", "", "", '0'),
            Array('listinfo', '0', 'listinfo', "", "", "", '0'),
            Array('listquiet', '0', 'listquiet', "", "", "", '0'),
        )
    );

    var $leaf = Array(
        'hooks' => Array(
            Array('h_321', '321', ""),
            Array('h_322', '322', ""),
            Array('h_323', '323', ""),
        )
    );
}
?>