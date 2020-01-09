<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Settings module";

    var $require = Array('CmdReg');

    var $slots = Array(
        Array('loaded', 'ModuleManager', 'load'),
        Array('reloaded', 'ModuleManager', 'reload'),
        Array('unloaded', 'ModuleManager', 'unload')
    );

    var $CmdReg = Array(
        'funcs' => Array(
            Array('cset', 'cmd_cset', "[mod] [name] [value]", "change a setting", 'chan'),
            Array('aset', 'cmd_aset', "[mod] [name] [valie]", "change a setting", 'pm'),
        	Array('moveset', 'cmd_moveset', "<Account|Chan> <OldMod.OldSetName> <NewMod.NewSetName>", "Move setting to new module", 'chan'),
        ),
        'binds' => Array(
            Array('set', '0', 'cset', "", "", "", '1', 'chan'),
            Array('set', '0', 'aset', "", "", "", '3', 'pm'),
        	Array('moveset', 'D', 'moveset', "", "", "", '1', 'chan'),
        ),
    );
}
?>