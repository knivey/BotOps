<?php

class registry {

    var $version     = '1.0';
    var $author      = 'knivey';
    var $description = "Fun module, toy commands";
    var $require = Array('CmdReg');

    var $CmdReg = Array(
        'funcs' => Array(
            Array('fml', 'cmd_fml', "", "Random FML stories"),
            Array('fmll', 'cmd_fmll', "", "Random FML stories Long output"),
            Array('ping', 'cmd_ping', "", "Ping/Pong"),
            Array('spell', 'cmd_spell', "<word>", "spellcheck"),
            Array('time', 'cmd_time', "", "Time"),
            Array('bash', 'cmd_bash', "", "View random Bash.org Quotes"),
            Array('mlib', 'cmd_mlib', "", "View random My Life Is Bro Quotes"),
            Array('txts', 'cmd_txts', "", "View random Texts From Last Night"),
            Array('gasinfo', 'cmd_gasinfo', "", "View local gas price info"),
            Array('reddit', 'cmd_reddit', "", "View top few reddit posts"),
            Array('tweet', 'cmd_tweet', "", "post a status update to twitter"),
            Array('qball', 'cmd_qball', "", "Ask the qball a question"),
            Array('qballadd', 'cmd_qballadd', "<new response>", "Add a qball response"),
            Array('qballdel', 'cmd_qballdel', "<response id>", "Remove a qball response"),
            Array('qballsearch', 'cmd_qballsearch', "*<search>*", "Show reponse IDs matching the search"),
            Array('qballinfo', 'cmd_qballinfo', "<id>", "Show info on the qball response ID"),
            Array('cal', 'cmd_cal', "", "Show a calendar"),
        ),
        'binds' => Array(
            Array('fml', '0', 'fml', "", "", "", '0'),
            Array('fmll', '0', 'fmll', "", "", "", '0'),
            Array('ping', '0', 'ping', "", "", "", '0'),
            Array('spell', '0', 'spell', "", "", "", '0'),
            Array('time', '0', 'time', "", "", "", '0'),
            Array('bash', '0', 'bash', "", "", "", '0'),
            Array('mlib', '0', 'mlib', "", "", "", '0'),
            Array('txts', '0', 'txts', "", "", "", '0'),
            Array('gasinfo', '0', 'gasinfo', "", "", "", '0'),
            Array('reddit', '0', 'reddit', "", "", "", '0'),
            Array('tweet', 'O', 'tweet', "", "", "", '0'),
            Array('qball', '0', 'qball', "", "", "", '0'),
            Array('qballadd', 'O', 'qballadd', "", "", "", '3'),
            Array('qballdel', 'O', 'qballdel', "", "", "", '3'),
            Array('qballsearch', 'O', 'qballsearch', "", "", "", '0'),
            Array('qballinfo', 'O', 'qballinfo', "", "", "", '0'),
            Array('cal', '0', 'cal', "", "", "", '0'),
        )
    );

}

?>