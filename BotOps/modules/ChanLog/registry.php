<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Store IRC logs by channel";

    var $leaf = Array(
        'hooks' => Array(
            Array('h_chanevent', 'chanevent', ""),
            Array('h_part', 'part', ""),
            Array('h_kick', 'kick', ""),
            Array('h_killbot', 'killbot', ''),
        )
    );
}
?>