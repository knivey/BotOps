<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "HTTP links scanner";

    var $require = Array('CmdReg');

    var $SetReg = Array(
        'channel' => Array(
            Array('scan', "", "Scans messages for links and reports what they are", "on", "on", "off")
        )
    );

    var $leaf = Array(
        'hooks' => Array(
            Array('inmsg', 'msg', "")
        )
    );
}
?>
