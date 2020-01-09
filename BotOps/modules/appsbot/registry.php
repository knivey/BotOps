<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "application checker";

//    var $XMLRPC = Array(
//        Array('botinfo', 'rpc_botinfo'),
//    );

    var $CmdReg = Array(
        'funcs' => Array(
            Array('apply', 'cmd_apply', "<#chan>", "apply for a new channel", 'chan'),
            Array('setidlers', 'cmd_setidlers', "<idlers>", "Change Idlers limit", 'chan'),
            Array('setmaxchans', 'cmd_setmaxchans', "<max>", "Max number of chans AppsBot will give a bot", 'chan'),
            Array('setbots', 'cmd_setbots', "<bot1> [bot2] [bot3]...", "Set which bots the AppsBot gives chans", 'chan'),
            Array('setenabled', 'cmd_setenabled', "<enabled|disabled>", "Turn applications on or off", 'chan'),
        ),
        'binds' => Array(
            Array('apply', '0', 'apply', "", "", "", '2', 'chan'),
            Array('setidlers', 'A', 'setidlers', "", "", "", '3', 'chan'),
            Array('setmaxchans', 'A', 'setmaxchans', "", "", "", '3', 'chan'),
            Array('setbots', 'A', 'setbots', "", "", "", '3', 'chan'),
            Array('setenabled', 'A', 'setenabled', "", "", "", '3', 'chan'),
        )
    );
    var $leaf = Array(
        'hooks' => Array(
            Array('h_330', '330', ""),
            Array('h_318', '318', ""),
            Array('h_324', '324', ""),
            Array('h_315', '315', ""),
            Array('h_471', '471', ""),
            Array('h_403', '403', ""),
            Array('h_474', '474', ""),
            Array('h_kick', 'kick', ""),
            Array('h_notice', 'notice', ""),
            Array('h_join', 'join', ""),
        )
    );
}

?>
