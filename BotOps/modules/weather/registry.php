<?php

class registry {

    var $version     = '1.0';
    var $author      = 'knivey';
    var $description = "Weather conditions & forecast";
    var $require = Array('CmdReg');
    var $SetReg = Array(
        'account' => Array(
            Array('units', "", "Units to display results in", "auto", "auto", "us", "uk2", "ca", "si", "imperial", "metric"),
        )
    );
    var $CmdReg = Array(
        'funcs' => Array(
            Array('weather', 'cmd_weather', "[location] [-auto|-us|-uk2|-ca|-si]", "Lookup weather info"),
        ),
        'binds' => Array(
            Array('weather', '0', 'weather', "", "", "", '0'),
        )
    );

}

?>