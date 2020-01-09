<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "ScoreBot Module";

    var $require = Array('CmdReg');

    var $leaf = Array(
        'hooks' => Array(
            Array('ircmsg', 'msg', "")
        )
    );
    
    var $ParseUtil = Array(
        'vars' => Array (
            Array('gameinfo', 'v_gameinfo', "Query gameserver", "<ip[:port]>")
        )
    );

    var $CmdReg = Array(
        'funcs' => Array(
            Array('connect', 'cmd_connect', "<ip> <port> <pass>", "Try to get rcon to a server."),
            Array('rcon', 'cmd_rcon', "<rcon command>", "Try to get rcon to a server."),
            Array('startsb', 'cmd_startsb', "", "Starts the scorebot."),
            Array('stopsb', 'cmd_stopsb', "", "Stops the scorebot."),
            Array('gameinfo', 'cmd_gameinfo', "", "Get gameserver info."),
            Array('gameplayers', 'cmd_gameplayers', "", "Get gamesserver player list info."),
            Array('sbplayers', 'cmd_sbplayers', "", "Get players tracked by scorebot."),
            Array('qm', 'cmd_qm', "", "Set quitemode")
        ),
        'binds' => Array(
            Array('connect', '1', 'connect', "", "", "", '1'),
            Array('rcon', '1', 'rcon', "", "", "", '1'),
            Array('startsb', '1', 'startsb', "", "", "", '1'),
            Array('stopsb', '1', 'stopsb', "", "", "", '1'),
            Array('gameinfo', '0', 'gameinfo', "", "", "", '0'),
            Array('gameplayers', '0', 'gameplayers', "", "", "", '0'),
            Array('sbplayers', '1', 'sbplayers', "", "", "", '0'),
            Array('qm', '1', 'qm', "", "", "", '1')
        )
    );
}

?>