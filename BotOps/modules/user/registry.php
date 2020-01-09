<?php

class registry {
    var $version = '1.0';
    var $author = 'knivey';
    var $description = "User system module";

    var $require = Array('CmdReg');

    var $ParseUtil = Array(
        'vars' => Array(
            Array('hand', 'v_hand', "users account name")
        )
    );

    var $SetReg = Array(
        'account' => Array(
            Array('birthday', "", "Your date of birth", ""),
            Array('zip', "", "Your zipcode", "")
        )
    );

    var $CmdReg = Array(
        'funcs' => Array(
            Array('access', 'cmd_access', "[nick|*account]", "See someone's access"),
            Array('clvl', 'cmd_clvl', "<nick|*account> <new access>", "Change user's access in channel."),
            Array('adduser', 'cmd_adduser', "<nick|*account> <new access>", "Give user access in channel."),
            Array('deluser', 'cmd_deluser', "<nick|*account>", "Remove user's access in channel."),
            Array('users', 'cmd_users', "", "See list of users with access in channel."),
            Array('god', 'cmd_god', "[on|off]", "Enable or Disable Security Override."),
            Array('oset', 'cmd_oset', "<nick|*account> <osetting> [value]", "Set special things on account"),
            Array('whoami', 'cmd_whoami', "", "Show your botnetwork username.", 'pm'),
            Array('auth', 'cmd_auth', "<username> <password>", "Auth to your account.", 'pm'),
            Array('register', 'cmd_register', "<username> <password> [email]", "Register an account", 'pm'),
            Array('cookie', 'cmd_cookie', "<username> <cookie>", "Use a cookie on your account", 'pm'),
            Array('resetpass', 'cmd_resetpass', "<username>", "Send a cookie to your email to set a new pass.", 'pm'),
            Array('pass', 'cmd_pass', "<new password>", "Change your password", 'pm'),
            Array('email', 'cmd_email', "<new email>", "Change your email", 'pm'),
            Array('logout', 'cmd_logout', "", "Log you out of your account.", 'pm')
        ),
        'binds' => Array(
            Array('access', '0', 'access', "", "", "", '0'),
            Array('clvl', '1', 'clvl', "", "", "", '1'),
            Array('adduser', '1', 'adduser', "", "", "", '1'),
            Array('deluser', '1', 'deluser', "", "", "", '1'),
            Array('users', '0', 'users', "", "", "", '0'),
            Array('god', 'O', 'god', "", "", "", '3'),
            Array('oset', 'S', 'oset', "", "", "", '0'),
            Array('whoami', '0', 'whoami', "", "", "", '0', 'pm'),
            Array('auth', '0', 'auth', "", "", "", '0', 'pm'),
            Array('register', '0', 'register', "", "", "", '0', 'pm'),
            Array('resetpass', '0', 'resetpass', "", "", "", '3', 'pm'),
            Array('pass', '0', 'pass', "", "", "", '0', 'pm'),
            Array('email', '0', 'email', "", "", "", '0', 'pm'),
            Array('cookie', '0', 'cookie', "", "", "", '3', 'pm'),
            Array('logout', '0', 'logout', "", "", "", '0', 'pm')
        )
    );
}
?>