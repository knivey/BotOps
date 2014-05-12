<?PHP
class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "Urban Dictionary";

    var $require = Array('CmdReg');
    
    var $CmdReg = Array(
        'funcs' => Array(
            Array('ud', 'cmd_ud', "<term>", "Look up urban dictionary"),
            ),
        'binds' => Array(
            Array('ud', '0', 'ud', "", "", "", '0'),
            )
        );
    
}
?>
