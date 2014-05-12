<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "General channel stuff";

    var $require = Array('CmdReg');
    
    var $XMLRPC = Array(
        Array('delchan', 'rpc_delchan'),
        Array('addchan', 'rpc_addchan'),
        Array('loadjoin', 'rpc_loadjoin'),
        Array('globalmsg', 'rpc_globalmsg'),
        Array('names', 'rpc_names'),
    );
    
    var $SetReg = Array(
        'channel' => Array(
            Array('greeting', '1', "Chan greet", "*"),
            Array('greeting2', '1', "Second chan greet", "*"),
            Array('nodelete', 'O', "Prevent delchan", "off"),
            Array('globalmsg', '1', "Allow global msgs", "on", "on", "off"),
            Array('gtype', '1', "Greeting Delivery method", "notice", "notice", "pm", "chan"),
            Array('trig', '1', "Chan trigger", "."),
            Array('onjoin', 'S', "Cmd to send when joining", "*")
        ),
        'channel_alias' => Array(
            Array('trig', 'trigger'),
        )
    );

    //[colname] [type] [null] [key] [default] [extra])
    var $logs = Array(
        Array('date', 'varchar(255)'),
        Array('action', 'varchar(255)'),
        Array('target', 'varchar(255)'),
        Array('nick', 'varchar(255)'),
        Array('hand', 'varchar(255)'),
        Array('bot', 'varchar(255)'),
        Array('host', 'text'),
        Array('targetb', 'text'),
        Array('msg', 'text'),
    );
    /*
     * ^ trying to keep fields as similar to CmdReg as i can
     * addchan #chan knivey
     * kick #chan botops lol
     * ban #chan botops!bots@botops
     * delchan #chan no idlers
     * 
     * date action      target  nick    targetb         msg
     * blah addchan     #chan   knivey  kyte            *
     * blah kick        #chan   knivey  botops          lol
     * blah ban         #chan   knivey  bot!bot@bot     *
     * blah delchan     #chan   knivey  chanowner       no idlers
     */

    var $CmdReg = Array(
        'funcs' => Array(
            Array('peek', 'cmd_peek', "<#chan>", "Peek at a channel"),
            Array('addchan', 'cmd_addchan', "<#chan> <owner>", "Add a channel"),
            Array('delchan', 'cmd_delchan', "<#chan> <reason>", "Remove a channel"),
            Array('unsuspend', 'cmd_unsuspend', "<#chan>", "Unsuspend a channel"),
            Array('suspend', 'cmd_suspend', "<#chan> <reason>", "Suspend a channel"),
            Array('channels', 'cmd_channels', "", "See a list of channels"),
            Array('movechan', 'cmd_movechan', "<#from> <#to>", "Move a channel"),
            Array('names', 'cmd_names', "[#chan]", "List users currently in channel"),
        ),
        'binds' => Array(
            Array('peek', 'O', 'peek', "", "", "", '0'),
            Array('addchan', 'O', 'addchan', "", "", "", '2'),
            Array('delchan', 'O', 'delchan', "", "", "", '2'),
            Array('unsuspend', 'O', 'unsuspend', "", "", "", '2'),
            Array('suspend', 'O', 'suspend', "", "", "", '2'),
            Array('channels', 'O', 'channels', "", "", "", '0'),
            Array('movechan', 'O', 'movechan', "", "", "", '2'),
            Array('names', '0', 'names', "", "", "", '0'),
        )
    );

    var $leaf = Array(
        'hooks' => Array(
            Array('h_authed', 'authed', ""),
            Array('h_gotTopic', '332', ""),
            Array('h_noTopic', '331', ""),
            Array('h_join', 'join', ""),
            Array('h_nick', 'nick', ""),
            Array('h_354', '354', ""),
            Array('h_part', 'part', ""),
            Array('h_kick', 'kick', ""),
            Array('h_quit', 'quit', ""),
            Array('h_topicChange', 'topic', ""),
            Array('h_gotTopicTime', '333', ""),
            Array('h_329', '329', ""),
            Array('h_324', '324', ""),
            Array('h_367', '367', ""),
            Array('h_471', '471', ''),
            Array('h_473', '473', ''),
            Array('h_474', '474', ''),
            Array('h_475', '475', ''),
            Array('h_ban', '+ban', ""),
            Array('h_unban', '-ban', ""),
            Array('h_op', '+op', ""),
            Array('h_deop', '-op', ""),
            Array('h_voice', '+voice', ""),
            Array('h_devoice', '-voice', ""),
            Array('h_modeAdd', '+mode', ""),
            Array('h_modeDel', '-mode', ""),
            Array('h_disconnected', 'disconnected', "")
        )
    );
}
?>