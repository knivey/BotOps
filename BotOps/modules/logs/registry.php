<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Log module";

    var $slots = Array(
        Array('loaded', 'ModuleManager', 'load')
    );
}
?>