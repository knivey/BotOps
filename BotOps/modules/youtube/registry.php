<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "YouTube links scanner";

    var $require = Array('CmdReg');

    var $SetReg = Array(
        'channel' => Array(
            Array('scan', "", "YouTube link scanning", "on", "on", "off"),
            Array('date', "", "Date format https://www.php.net/manual/en/function.date.php", "M j, Y",),
            Array('theme', "", 'Customize the output, variables: $yt (YouTube '.
                  'or YouTubeHD), $title, $channel, $length, $date, $views, '.
                  '$likes, $hates', "\2\$yt:\2 \$title \2Channel:\2 \$channel ".
                  "\2Length:\2 \$length \2Date:\2 \$date \2Views:\2 \$views ▲ \$likes ▼ \$hates")
        )
    );
    
    var $leaf = Array(
        'hooks' => Array(
            Array('inmsg', 'msg', "")
        )
    );
}
?>
