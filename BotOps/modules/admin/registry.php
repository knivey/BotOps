<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "admin commands";

    var $XMLRPC = Array(
        Array('botinfo', 'rpc_botinfo'),
        Array('chaninfo', 'rpc_chaninfo'),
        Array('nickhandinfo', 'rpc_nickhandinfo'),
        Array('killbot', 'rpc_killbot'),
        Array('rename', 'rpc_rename'),
        Array('loadfilters', 'rpc_loadfilters'),
    );
    
    var $slots = Array(
        Array('loaded', 'ModuleManager', 'load'),
        Array('reloaded', 'ModuleManager', 'reload'),
    );
    
    var $CmdReg = Array(
        'funcs' => Array(
            Array('forceauth', 'cmd_forceauth', "", "force the bot to send its authserv line", 'pm'),
            Array('botinfo', 'cmd_botinfo', "", "show some info", 'chan'),
            Array('quit', 'cmd_quit', "", "Kill the bot", 'chan'),
            Array('bots', 'cmd_bots', "", "list bots", 'chan'),
            Array('info', 'cmd_info', "[#chan|nick|*account]", "show information", 'chan'),
            Array('startbot', 'cmd_startbot', "<bot1> [bot2] [-newpid]", "start bot(s)", 'chan'),
            Array('addbot', 'cmd_addbot', "<name> <ip>", "add a bot", 'chan'),
            Array('delbot', 'cmd_delbot', "<name>", "del a bot", 'chan'),
            Array('setbot', 'cmd_setbot', "<name> [val]", "bot settings", 'chan'),
            Array('global', 'cmd_global', "<msg>", "Message all channels", 'chan'),
            Array('switchbot', 'cmd_switchbot', "<chan> <oldbot> <newbot>", "change bot assigned to chan", 'chan'),
            Array('clonescan', 'cmd_clonescan', "[#chan]", "scan channel for clones", 'chan'),
            Array('whois', 'cmd_whois', "<nick>", "do a /whois on user", 'chan'),
            Array('cleanaccess', 'cmd_cleanaccess', "", "remove access for channels that dont exist", 'chan'),
            Array('addfilter', 'cmd_addfilter', "<message mask>", "Add a filter to prevent bot from sending messages matching the mask", 'chan'),
            Array('delfilter', 'cmd_delfilter', "<id>", "Delete a filter", 'chan'),
            Array('listfilters', 'cmd_listfilters', "", "Show the web link to view the filter list", 'chan'),
            Array('bnstats', 'cmd_bnstats', "", "Show some botnet stats", 'chan'),
        ),
        'binds' => Array(
            Array('forceauth', 'O', 'forceauth', "", "", "", '3', 'pm'),
            Array('botinfo', '0', 'botinfo', "", "", "", '0', 'chan'),
            Array('quit', 'D', 'quit', "", "", "", '3', 'chan'),
            Array('bots', 'O', 'bots', "", "", "", '0', 'chan'),
            Array('info', '0', 'info', "", "", "", '2', 'chan'),
            Array('startbot', 'S', 'startbot', "", "", "", '3', 'chan'),
            Array('addbot', 'S', 'addbot', "", "", "", '2', 'chan'),
            Array('delbot', 'S', 'delbot', "", "", "", '2', 'chan'),
            Array('setbot', 'S', 'setbot', "", "", "", '2', 'chan'),
            Array('global', 'S', 'global', "", "", "", '3', 'chan'),
            Array('switchbot', 'O', 'switchbot', "", "", "", '2', 'chan'),
            Array('clonescan', '0', 'clonescan', "", "", "", '0', 'chan'),
            Array('whois', '0', 'whois', "", "", "", '0', 'chan'),
            Array('cleanaccess', 'D', 'cleanaccess', "", "", "", '3', 'chan'),
            Array('addfilter', 'S', 'addfilter', "", "", "", '3', 'chan'),
            Array('delfilter', 'S', 'delfilter', "", "", "", '3', 'chan'),
            Array('listfilters', 'O', 'listfilters', "", "", "", '0', 'chan'),
            Array('bnstats', '0', 'bnstats', "", "", "", '0', 'chan'),
        )
    );
    var $leaf = Array(
        'hooks' => Array(
            Array('h_311', '311', ""),
            Array('h_319', '319', ""),
            Array('h_301', '301', ""),
            Array('h_317', '317', ""),
            Array('h_330', '330', ""),
            Array('h_318', '318', ""),
            Array('h_402', '402', "")
        )
    );
}

?>
