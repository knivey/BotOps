<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Store quotes";

    var $require = Array('CmdReg');
    var $SetReg = Array(
        'channel' => Array(
            Array('origin', 1, "your channels quotes or all quotes", "all", "all", "chan")
        )
    );
    var $CmdReg = Array(
        'funcs' => Array(
            Array('quote', 'cmd_quote', "[num]", "View a random quote, or quote num"),
            Array('quoteinfo', 'cmd_quoteinfo', "<num>", "View information about a specific quote"),
            Array('quotestats', 'cmd_quotestats', "<num>", "View information about the quote system"),
            Array('delquote', 'cmd_delquote', "<num>", "Delete a quote"),
            Array('undelquote', 'cmd_undelquote', "<num>", "UnDelete a quote"),
            Array('addquote', 'cmd_addquote', "<quote>", "Add a quote")
        ),
        'binds' => Array(
            Array('quote', 0, 'quote', "", "", "", 0),
            Array('quoteinfo', 0, 'quoteinfo', "", "", "", 0),
            Array('quotestats', 0, 'quotestats', "", "", "", 0),
            Array('delquote', 1, 'delquote', "", "", "", 1),
            Array('undelquote', 1, 'undelquote', "", "", "", 1),
            Array('addquote', 1, 'addquote', "", "", "", 1)
        )
    );
}

?>
