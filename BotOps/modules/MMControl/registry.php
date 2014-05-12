<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Module Control Module";

    var $require = Array('CmdReg');

    var $CmdReg = Array(
        'funcs' => Array(
            Array('reload', 'cmd_reload', "<module>", "Try to reload a module"),
            Array('loadmod', 'cmd_loadmod', "<module>", "Try to load a module not in list.conf"),
            Array('modules', 'cmd_modules', "", "List modules"),
            Array('addmodule', 'cmd_addmodule', "<module>", "Add a new bot specific module.", 'chan'),
            Array('delmodule', 'cmd_delmodule', "<module>", "Remove a bot specific module.", 'chan'),
            Array('svn', 'cmd_svn', "<command>", "Execute a subversion command")
        ),
        'binds' => Array(
            Array('reload', 'D', 'reload', "", "", "", '3'),
            Array('loadmod', 'D', 'loadmod', "", "", "", '3'),
            Array('modules', '0', 'modules', "", "", "", '0'),
            Array('addmodule', 'D', 'addmodule', "", "", "", '3'),
            Array('delmodule', 'D', 'delmodule', "", "", "", '3'),
            Array('svn', 'D', 'svn', "", "", "", '3')
        )
    );
}
?>