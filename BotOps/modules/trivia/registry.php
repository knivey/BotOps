<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Trivia game";

    var $require = Array('CmdReg');
    var $SetReg = Array(
        'channel' => Array(
            Array('enabled', 1, "Enable or disable trivia", "no", "yes", "no")
        )
    );
    
    var $CmdReg = Array(
        'funcs' => Array(
            Array('trivia', 'cmd_trivia', "[category]", "Start a trivia game"),
            Array('categories', 'cmd_categories', "", "View categories"),
            Array('strivia', 'cmd_strivia', "", "Stop a trivia game"),
            Array('hint', 'cmd_hint', "", "Give a hint"),
            Array('skip', 'cmd_skip', "", "Skip question"),
            Array('triviainfo', 'cmd_triviainfo', "", "Information about the game"),
            Array('triviastats', 'cmd_triviastats', "[nick]", "Show stats for you or someone")
        ),
        'binds' => Array(
            Array('trivia', 0, 'trivia', "", "", "", 1),
            Array('categories', 0, 'categories', "", "", "", 1),
            Array('strivia', 0, 'strivia', "", "", "", 1),
            Array('hint', 0, 'hint', "", "", "", 1),
            Array('skip', 0, 'skip', "", "", "", 1),
            Array('triviainfo', 0, 'triviainfo', "", "", "", 0),
            Array('triviastats', 0, 'triviastats', "", "", "", 0)
        )
    );
    
    var $leaf = Array(
        'hooks' => Array(
            Array('h_msg', 'msg', "")
        )
    );
}

?>
