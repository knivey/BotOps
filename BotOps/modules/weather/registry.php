<?php

class registry {

    var $version     = '1.0';
    var $author      = 'knivey';
    var $description = "Weather conditions & forecast";
    var $require = Array('CmdReg');
    var $SetReg = Array(
        'account' => Array(
            Array('wservice', "", "Weather service provider", "wu", "wu", "noaa"),
            Array('units', "", "Units to display results in", "imperial", "imperial", "metric"),
        )
    );
    var $CmdReg = Array(
        'funcs' => Array(
            Array('weather', 'cmd_weather', "[zip]", "Lookup weather info"),
        ),
        'binds' => Array(
            Array('weather', '0', 'weather', "", "", "", '0'),
        )
    );

}

?>