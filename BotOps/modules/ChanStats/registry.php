<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Channel Stats Page";

    var $require = Array('CmdReg');

    /*
     * Later we can add settings for channels to
     * customise their stats page http://pisg.sourceforge.net/documentation
     */
    //var $SetReg = Array(
      //  'channel' => Array(
      //  //can't have option with more then 1 bot on chan
            //Array('genstats', '1', "Keep stats up to date", "yes", "yes", "no"),
            //Array('onjoin', 'S', "Cmd to send when joining", "*")
        //),
    //);


    var $CmdReg = Array(
        'funcs' => Array(
            Array('cstats', 'cmd_cstats', "", "Show link for channel stats"),
            Array('forcestats', 'cmd_forcestats', "", "Force channel stats to generate"),
        ),
        'binds' => Array(
            Array('cstats', '0', 'cstats', "", "", "", '0'),
            Array('forcestats', 'S', 'forcestats', "", "", "", '3'),
        )
    );
}
?>