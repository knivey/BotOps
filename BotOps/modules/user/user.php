<?PHP
require_once __DIR__ . '/../CmdReg/CmdRequest.php';

require_once('modules/Module.inc');

class user extends Module
{

    function cmd_logout(CmdRequest $r)
    {
        if ($r->account == '') {
            throw new CmdException("You are not authed.");
        }

        $stmt = $this->pMysql->prepare("UPDATE `users` SET `host` = NULL WHERE `name` = :hand");
        $stmt->execute(Array(':hand' => $r->account));
        $stmt->closeCursor();

        $r->notice("User $r->account has been logged out.");
    }

    function cmd_cookie(CmdRequest $r)
    {
        if ($r->account != '') {
            throw new CmdException("You are already authed to account $r->account");
        }

        $hand   = $r->args['username'];
        $cookie = $r->args['cookie'];

        $stmt = $this->pMysql->prepare("SELECT `cookie`,`name` FROM `users` WHERE `name` = :hand");
        $stmt->execute(Array(':hand' => $hand));
        if ($stmt->rowCount() == 0) {
            throw new CmdException("Account\2 $hand \2has not been registered.");
        }
        $row = $stmt->fetch();
        $stmt->closeCursor();

        ($row["cookie"]) ? $row["cookie"] = explode('.', $row["cookie"]) : '';
        if (empty($row["cookie"])) {
            throw new CmdException("There is no cookie issued for this account.");
        }
        if ($row["cookie"][0] && (time() - $row["cookie"][0] > 86400)) {
            throw new CmdException("Cookie has expired. Please use the resetpass command to issue another one.");
        }
        if ($cookie == $row["cookie"][1]) {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `host` = :host, `cookie` = NULL WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand, ':host' => $host));
            $stmt->closeCursor();

            $r->notice("You are now authed to account\2 $row[name]");
            $r->notice("Temporary cookie deleted. Remember to change your password!");
        } else {
            throw new CmdException("Cookie information is incorrect.");
        }
    }

    function cmd_resetpass(CmdRequest $r)
    {
        if ($r->account != '') {
            throw new CmdException("You are already authed to account $r->account");
        }
        $hand = $r->args['username'];

        $stmt = $this->pMysql->prepare("SELECT `cookie`,`name`,`id`,`email` FROM `users` WHERE `name` = :hand");
        $stmt->execute(Array(':hand' => $hand));
        if ($stmt->rowCount() == 0) {
            throw new CmdException("Account\2 $hand \2has not been registered.");
        }
        $row = $stmt->fetch();
        $stmt->closeCursor();

        if (empty($row["email"])) {
            throw new CmdException("There is no email set for account\2 $hand");
        }

        ($row["cookie"]) ? $row["cookie"] = explode('.', $row["cookie"]) : '';
        if ($row["cookie"][0] && (time() - $row["cookie"][0] < 86400)) {
            throw new CmdException("A cookie has recently been issue for this account. Please wait for it to expire.");
        }

        $row["cookie"] = chr(rand(97, 122)) . chr(rand(65, 90)) .
            chr(rand(97, 122)) . chr(rand(65, 90)) . rand(65, 90) .
            chr(rand(97, 122)) . chr(rand(97, 122));

        $stmt = $this->pMysql->prepare("UPDATE `users` SET `cookie` = :cookie WHERE `id` = :id");
        $stmt->execute(Array(':id' => $row['id'], ':cookie' => time() . "." . $row["cookie"]));
        $stmt->closeCursor();

        $postmail = array(
            $row["email"],
            "Login cookie",
            "This email is in reply to a request to login to your account. Please note that your password has not been changed." .
            "\n\nThe following command will allow you to alternatively login to your account." .
            "\n /msg BotOps cookie $hand " . trim($row['cookie']) .
            "\n\nYour cookie will expire in 24 hrs and may only be used once." .
            "\n*Note: If you did not request this service, you do not have to do anything." .
            "\n\n--" .
            "\nPlease do not reply to this email! Nothing will happen =/",
            "From: BotOps Login Services\n"
        );
        if (mail($postmail[0], $postmail[1], $postmail[2], $postmail[3])) {
            $r->notice("A temporary cookie has been generated and sent to your email address.");
        } else {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `cookie` = NULL WHERE `id` = :id");
            $stmt->execute(Array(':id' => $row['id']));
            $stmt->closeCursor();

            throw new CmdException("Failed to email cookie. Request additional support in #bots");
        }
    }

    function cmd_email(CmdRequest $r)
    {
        $email = $r->args['email'];
        if ($r->account == '') {
            throw new CmdException("You are not authed with BotOps, auth first.");
        }

        if (!isemail($email)) {
            throw new CmdException("$email is not a valid email address");
        }

        $rv = $this->email_inuse($email);
        if ($rv == -1) {
            throw new CmdException("An error occurred while executing your command, staff have been notified.");
        }
        if ($rv == 1) {
            throw new CmdException("$email has already been used to register an account. Please use a different email address.");
        }

        $this->setEmail($r->account, $email);
        $r->notice("Your email address has been set.");
    }

    function cmd_pass(CmdRequest $r)
    {
        if ($r->account == '') {
            throw new CmdException("You are not authed with BotOps, auth first.");
        }

        if (strlen($r->args[0]) < 5) {
            throw new CmdException("For security your password must be longer then 5 characters, password not updated.");
        }

        $pass = password_hash($r->args[0], PASSWORD_BCRYPT);

        $stmt = $this->pMysql->prepare("UPDATE `users` SET `pass` = :pass WHERE `name` = :hand");
        $stmt->execute(Array(':hand' => $r->account, ':pass' => $pass));
        $stmt->closeCursor();

        $r->notice("Your password has been updated, don't forget it!");
    }

    function email_inuse($email)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `users` WHERE `email`=:email");
            $stmt->execute(Array(':email' => $email));
            $stmt->closeCursor();
            if ($stmt->rowCount() > 0) {
                return 1;
            }
        } catch (PDOException $e) {
            $this->reportPDO($e);
            return -1;
        }
        return 0;
    }

    function cmd_register(CmdRequest $r)
    {
        if ($r->account != '') {
            throw new CmdException("You are already authed to account $r->account");
        }

        $hand  = $r->args[0];
        $pass  = $r->args[1];
        $email = $r->args[2];

        if ($email) {
            if (!isemail($email)) {
                throw new CmdException("$email is not a valid email address");
            }
        } else {
            $r->notice("Note without an email set you will " .
                "not be able to recover lost passwords, if you decide to set " .
                "an email later please /msg " . $this->pIrc->currentNick() .
                " EMAIL <new address>");
        }

        if ($this->hand_exists($hand)) {
            throw new CmdException("That username already exists.");
        }

        $stmt = $this->pMysql->prepare("SELECT `name` FROM `users` WHERE `email`=:email");
        $stmt->execute(Array(':email' => $email));
        $stmt->closeCursor();
        if ($stmt->rowCount() > 0) {
            throw new CmdException("$email has already been used to register an account. Please use a different email address or recover your existing accunt.");
        }

        $params = Array(
            ':name'   => $hand,
            ':pass'   => password_hash($pass, PASSWORD_BCRYPT),
            ':date'   => time(),
            ':laston' => time(),
            ':host'   => $r->host,
            ':email'  => $email,
            ':chans'  => 'a:0:{}',
        );
        $query  = "INSERT INTO `users` (`name`,`pass`,`datemade`,`laston`,`host`,`email`,`chans`)" .
            " VALUES(:name,:pass,:date,:laston,:host,:email,:chans)";

        $stmt = $this->pMysql->prepare($query);
        $stmt->execute($params);
        $stmt->closeCursor();

        $r->notice("You are now authed to account $hand");
        $this->pIrc->msg('#botstaff', "Account $hand has been regged by $r->nick.");
    }

    function checkPass($user, $pass)
    {
        $stmt = $this->pMysql->prepare("SELECT `pass` FROM `users` WHERE `name` = :hand");
        $stmt->execute(Array(':hand' => $user));
        if ($stmt->rowCount() == 0) {
            return false;
        }
        $row = $stmt->fetch();
        $stmt->closeCursor();

        return password_verify($pass, $row['pass']);
    }

    function cmd_auth(CmdRequest $r)
    {
        if ($r->account != '') {
            throw new CmdException("You are already authed to account $r->account");
        }

        $hand = $r->args[0];
        $pass = $r->args[1];

        $stmt = $this->pMysql->prepare("SELECT `pass`,`flags` FROM `users` WHERE `name` = :hand");
        $stmt->execute(Array(':hand' => $hand));
        if ($stmt->rowCount() == 0) {
            throw new CmdException("Failed to auth, the username $hand doesn't exist.");
        }
        $row = $stmt->fetch();
        $stmt->closeCursor();

        if ($this->checkPass($hand, $pass)) {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `host`=:host,`cookie`=NULL,`lastseen`='now' WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand, ':host' => $r->host));
            $stmt->closeCursor();
            $r->notice("You are now authed to account $hand");
            if ($this->hasflags($hand, 'T|O', $row['flags'])) {
                $this->pIrc->msg('#botstaff', "Notice $r->nick has authed to " . $this->staff_position($hand) . " account $hand");
            }
        } else {
            if ($this->hasflags($hand, 'T|O', $row['flags'])) {
                $this->pIrc->msg('#botstaff',"Failed AUTH attempt on " . $this->staff_position($hand) . " account $hand by $r->nick");
                throw new CmdException("Failed to auth to " . $this->staff_position($hand) . " account $hand, the password was incorrect, This incident will be reported.");
            } else {
                $this->pIrc->msg('#botstaff',"Failed AUTH attempt on account $hand by $r->nick");
                throw new CmdException("Failed to auth, the password for $hand was incorrect, This incident will be reported.");
            }
        }
    }

    function cmd_whoami(CmdRequest $r)
    {
        $hand = $r->account;
        if ($hand == '') {
            $hand = 'You are not authed.';
        }
        $r->notice($hand);
    }

    function v_hand($args, $store)
    {
        return $this->byHost($store);
    }

    function byNick($nick)
    {
        $host = $this->pIrc->n2h($nick);
        if ($nick == null) {
            return;
        }
        return $this->byHost($host);
    }

    function byHost($host)
    {
        /* looks like we used to store users in ppl table
         * probably will again later
          foreach($ppl as $p) {
          if(array_key_exists('host', $p) && $p['host'] == $host && array_key_exists('user', $p) && $p['user'] != '') {
          return $p['user'];
          }
          } */
        if ($host == '') {
            return null;
        }
        $matches = Array();
        if (!preg_match("/^~?([^@]+)@([^\.]+)\.[^\.]+\.(gamesurge|support)\$/", $host, $matches)) {
            try {
                $stmt = $this->pMysql->prepare("SELECT `name` FROM `users` WHERE `host` = :host");
                $stmt->execute(Array(':host' => $host));
                $row  = $stmt->fetch();
                $stmt->closeCursor();
                return $row['name'] ?? null;
            } catch (PDOException $e) {
                $this->reportPDO($e);
                return null;
            }
        }
        $ident  = preg_quote($matches[1], '/');
        $asUser = preg_quote($matches[2], '/');
        $ending = preg_quote($matches[3], '/');
        
        $myRegex = "^~?$ident@$asUser\.[^\.]+\.$ending\$";

        try {
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `users` WHERE `host` REGEXP :host");
            $stmt->execute(Array(':host' => $myRegex));
            $row  = $stmt->fetch();
            $stmt->closeCursor();
            return $row['name'];
        } catch (PDOException $e) {
            $this->reportPDO($e);
            return null;
        }
    }

    function hand_host($hand)
    {
        if ($hand == '') {
            return;
        }
        try {
            $stmt = $this->pMysql->prepare("SELECT `name`,`host` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            $stmt->closeCursor();
            return $row['host'];
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function access($hand, $chan, $ignoreSuspend = false)
    {
        if ($this->hasflags($hand, 'L') && !$ignoreSuspend) {
            return '0';
        }
        try {
            $stmt  = $this->pMysql->prepare("SELECT `chans` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row   = $stmt->fetch();
            $stmt->closeCursor();
            if(!$row || !isset($row['chans'])) {
                return "0";
            }
            $chans = unserialize($row['chans']);
            $c     = get_akey_nc($chan, $chans);
            if ($c != '') {
                return $chans[$c]['access'];
            }
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
        return "0";
    }

    function setAccess($chan, $hand, $access)
    {
        $chan                    = strtolower($chan);
        $hchans                  = $this->chans($hand);
        $hchans[$chan]['access'] = $access;
        $hchans                  = serialize($hchans);
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :hchans WHERE `name` = :hand");
            $stmt->execute(Array(':hchans' => $hchans, ':hand' => $hand));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function delchan($chan)
    {
        $bots = $this->gM('channel')->botsOnChan($chan);
        if (!empty($bots)) {
            //dont remove users if other bots are still on the channel
            return;
        }
        $chan  = strtolower($chan);
        $users = explode(' ', trim($this->chan_users($chan)));
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :hchans WHERE `name` = :user");
            foreach ($users as $user) {
                $user   = explode(':', $user);
                unset($user[0]);
                $user   = implode(':', $user);
                $hchans = $this->chans($user);
                unset($hchans[$chan]);
                $hchans = serialize($hchans);
                $stmt->execute(Array(':hchans' => $hchans, ':user' => $user));
                $stmt->closeCursor();
            }
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function getzip($hand)
    {
        return $this->gM('SetReg')->getASet($hand, 'user', 'zip');
    }

    function getEmail($hand)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `email` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            $stmt->closeCursor();
            return $row['email'];
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function setEmail($hand, $email)
    {
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `email` = :email WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand, ':email' => $email));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function chans($hand)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `chans` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            $stmt->closeCursor();
            return unserialize($row['chans']);
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function laston($hand)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `lastseen` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            $stmt->closeCursor();
            return $row['lastseen'];
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function flags($hand)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `flags` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            $stmt->closeCursor();
            return $row['flags'] ?? '';
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    /**
     * Check if a user has flags account flags O|F to see if they have O or F flag
     * 
     * @param string $hand Account handle
     * @param string $flags Flags to check for
     * @param string $hflags Optionaly, you may provide the flags for hand
     * @return int
     */
    function hasflags($hand, $flags, $hflags = FALSE)
    {
        if ($flags == '') {
            return true;
        }
        $flagz = explode('|', $flags);
        if ($hflags === FALSE) {
            $handflags = $this->flags($hand);
        } else {
            $handflags = $hflags;
        }
        $bits = 0;
        foreach ($flagz as $fs) {
            $fs  = str_split($fs);
            $res = true;
            foreach ($fs as &$flag) {
                if (strrpos($handflags, $flag) === FALSE) {
                    $res = false;
                }
            }
            if ($res) {
                $bits += 1;
            }
        }
        return $bits;
    }

    function hasOverride($hand)
    {
        if ($this->hasflags($hand, 'L')) {
            return false;
        }
        return $this->hasflags($hand, 'g');
    }

    function addflags($hand, $flags)
    {
        if ($this->hasflags($hand, $flags)) {
            return;
        }
        $cflags = $this->flags($hand);
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `flags` = :flags WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand, ':flags' => $cflags . $flags));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function delflags($hand, $flags)
    {
        if (!$this->hasflags($hand, $flags)) {
            return;
        }
        $cflags = $this->flags($hand);
        $flags  = str_split($flags);
        foreach ($flags as &$flag) {
            $cflags = implode('', explode($flag, $cflags));
        }
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `flags` = :flags WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand, ':flags' => $cflags));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function hand_exists($hand)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            if ($stmt->rowCount() > 0) {
                return true;
            }
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function gethandcase($hand)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            if ($stmt->rowCount() > 0) {
                return $row['name'];
            }
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function set($hand, $mod, $name, $val)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `settings` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            if ($stmt->rowCount() == 0) {
                return;
            }
            $stmt->closeCursor();
            $settings              = unserialize($row['settings']);
            $settings[$mod][$name] = $val;
            $stmt                  = $this->pMysql->prepare("UPDATE `users` SET `settings` = :sets WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand, ':sets' => serialize($settings)));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    //These settings are seperate from SetReg (.set command)
    function getSet($hand, $mod, $name)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `settings` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            if ($stmt->rowCount() == 0) {
                return;
            }
            $stmt->closeCursor();
            $settings = unserialize($row['settings']);
            if (!is_array($settings)) {
                return null;
            }
            if (array_key_exists($mod, $settings) && array_key_exists($name,
                                                                      $settings[$mod])) {
                return $settings[$mod][$name];
            }
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    /**
     * Return an array of every username in the database
     * @return Array
     */
    function allUsers()
    {
        $ret = Array();
        try {
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `users`");
            $stmt->execute();
            while ($row  = $stmt->fetch()) {
                $ret[] = $row['name'];
            }
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
        return $ret;
    }

    function staff_position($hand)
    {
        $level = '';
        if ($this->hasflags($hand, 'N|O|T|U')) {
            //if($this->hasflags($hand, 'U')) $level = "\2Owns the bot(s) " . ubots($hand) . "\2";
            if ($this->hasflags($hand, 'N')) {
                $level = "\2NOOB\2";
            }
            if ($this->hasflags($hand, 'T')) {
                $level = "\2Trial\2";
            }
            if ($this->hasflags($hand, 'O')) {
                $level = "\2Support Helper\2";
            }
            if ($this->hasflags($hand, 'G')) {
                $level = "\2Global Operator\2";
            }
            if ($this->hasflags($hand, 'A')) {
                $level = "\2Administrator\2";
            }
            if ($this->hasflags($hand, 'S')) {
                $level = "\2Senior Administrator\2";
            }
            if ($this->hasflags($hand, 'F')) {
                $level = "\2Founder\2";
            }
            if ($this->hasflags($hand, 'D')) {
                $level = "\2Developer\2";
            }
        }
        if ($this->hasflags($hand, 'L')) {
            $level .= " (Account Suspended)";
        }
        return $level;
    }

    /*
     * For now delayed lookup with whois has been removed
     */
    function getByNickOrAccount(string $arg)
    {
        $h = str_split($arg);
        if ($h[0] == '*') {
            unset($h[0]);
            $h = implode('', $h);
            if (!$this->hand_exists($h)) {
                throw new Exception("That account doesn't exist");
            }
        } else {
            $host = $this->pIrc->n2h($arg);
            if ($host == '') {
                throw new Exception("$arg isn't in any of my channels");
            }
            $h = $this->byHost($host);
        }
        if ($h == '') {
            throw new Exception("$arg is not authed");
        }
        return $h;
    }

    function chan_users($chan)
    {
        try {
            $stmt  = $this->pMysql->prepare("SELECT `name`,`chans` FROM `users` WHERE `chans` LIKE :chan");
            $stmt->execute(Array(':chan' => "%$chan%"));
            $rows  = $stmt->fetchAll();
            $users = '';
            foreach ($rows as $row) {
                $chans = unserialize($row['chans']);
                $c     = get_akey_nc($chan, $chans);
                if (!empty($c)) {
                    $users .= $chans[$c]['access'] . ":$row[name] ";
                }
            }
            return $users;
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function getEpithet($hand)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `epithet` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            $stmt->closeCursor();
            return $row['epithet'];
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function setEpithet($hand, $val)
    {
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `epithet` = :val WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand, ':val' => $val));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function setFlags($hand, $val)
    {
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `flags` = :val WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand, ':val' => $val));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function cmd_access(CmdRequest $r)
    {
        $chan = strtolower($r->chan);
        $hand = $r->account;
        $who = $r->args[0];
        if (!$who) {
            if ($hand == '') {
                throw new CmdException("You are not authed with BotOps. Use /msg " . $this->pIrc->currentNick() . " auth <username> <password>");
            }
            $who = $r->nick;
        } else {
            try {
                $hand = $this->getByNickOrAccount($who);
            } catch (Exception $e) {
                throw new CmdException($e->getMessage());
            }
        }
        $access = $this->access($hand, $chan);

        if ($this->hasflags($hand, 'N|O|T|U')) {
            $epithet = $this->getEpithet($hand);
            $level   = $this->staff_position($hand);
            $r->notice("$who is $epithet ($level)");
        }
        if ($access == 0) {
            if ($this->hasflags($hand, 'g')) {
                $r->notice("$who ($hand) lacks access to $chan but has \2Override\2 enabled.");
            } else {
                $r->notice("$who ($hand) lacks access to $chan");
            }
        } else {
            if ($this->hasflags($hand, 'g')) {
                $r->notice("$who ($hand) has access level\2 $access \2in $chan and has \2Override\2 enabled.");
            } else {
                $r->notice("$who ($hand) has access level\2 $access \2in $chan.");
            }
        }
    }

    function setOverride($hand, $val)
    {
        if ($val) {
            $this->addflags($hand, 'g');
            return 'Security Override ENABLED';
        } else {
            $this->delflags($hand, 'g');
            return 'Security Override DISABLED';
        }
    }

    function cmd_oset(CmdRequest $r)
    {
        $hand = $r->account;

        try {
            $h = $this->getByNickOrAccount($r->args[0]);
        } catch (Exception $e) {
            throw new CmdException($e->getMessage());
        }
        if ($this->ishigher($hand, $h) != $hand &&
            (!$this->hasflags($hand, 'D') && !$this->hasflags($hand, 'F'))) {
            throw new CmdException("$h has the same or more access then yourself");
        }
        $what = strtolower($r->args[1]);
        $val  = $r->args[2];
        if ($what == 'epithet') {
            if ($val == '') {
                $val = $this->getEpithet($h);
                $r->notice("$h's epithet is set to $val");
                return;
            }
            $this->setEpithet($h, $val);
            $r->notice("$h's epithet set to $val");
            return;
        }
        if ($what == 'flags') {
            if ($val == '') {
                $val = $this->flags($h);
                $r->notice("$h's flags are set to: $val");
                return;
            }
            if ($h == $hand && !$this->hasflags($hand, 'D') && !$this->hasflags($hand, 'F')) {
                throw new CmdException("You cannot set your own flags with this command");
            }

            if (!cisin($val, '+') && !cisin($val, '-')) {
                $this->setFlags($h, $val);
            } else {
                $flags = str_split($val);
                $add   = true;
                foreach ($flags as $f) {
                    if ($f == '+') {
                        $add = true;
                        continue;
                    }
                    if ($f == '-') {
                        $add = false;
                        continue;
                    }
                    if ($add) {
                        $this->addflags($h, $f);
                    } else {
                        $this->delflags($h, $f);
                    }
                }
            }
            $r->notice("$h's flags are now " . $this->flags($h));
            return;
        }
    }

    //(D)Developer, (F)Founder, (S)Senior Administrator, (A)Administrator, (G)Global Operator, (O)Support Helper, (T)Trial
    function ishigher($hand1, $hand2)
    {
        if ($this->saccess($hand1) == $this->saccess($hand2)) {
            return '*';
        }
        if ($this->saccess($hand1) > $this->saccess($hand2)) {
            return $hand1;
        }
        if ($this->saccess($hand1) < $this->saccess($hand2)) {
            return $hand2;
        }
    }

    function saccess($hand)
    {
        $f = $this->flags($hand);
        if (cisin($f, 'F')) {
            return '10';
        }
        if (cisin($f, 'S')) {
            return '9';
        }
        if (cisin($f, 'A')) {
            return '8';
        }
        if (cisin($f, 'G')) {
            return '7';
        }
        if (cisin($f, 'O')) {
            return '6';
        }
        if (cisin($f, 'T')) {
            return '1';
        }
        return '0';
    }

    function cmd_god(CmdRequest $r)
    {
        if (!isset($r->args[0])) {
            if ($r->hasoverride) {
                $r->notice($this->setOverride($r->account, false));
            } else {
                $r->notice($this->setOverride($r->account, true));
            }
            return;
        }
        if (strtolower($r->args[0]) == 'on') {
            $r->notice($this->setOverride($r->account, true));
        }
        if (strtolower($r->args[0]) == 'off') {
            $r->notice($this->setOverride($r->account, false));
        }
    }

    function cmd_clvl(CmdRequest $r)
    {
        try {
            $who = $this->getByNickOrAccount($r->args[0]);
        } catch (Exception $e) {
            throw new CmdException($e->getMessage());
        }

        if ($this->access($who, $r->chan, true) == "0") {
            throw new CmdException("$who lacks access to $r->chan.");
        }

        if (!is_numeric($r->args[1])) {
            throw new CmdException("{$r->args[1]} is an invalid access level.");
        }
        $newaccess = round($r->args[1], 2);
        $curaccess = $this->access($who, $r->chan, true);

        if ($newaccess >= $r->access || $curaccess >= $r->access) {
            if (!$r->hasoverride) {
                throw new CmdException('You cannot alter access greater then or equal to your own.');
            } else {
                $r->setOverride();
            }
        }

        if ($newaccess <= 0) {
            throw new CmdException("You cannot give someone 0 access; Use deluser instead.");
        }

        $hchans                 = $this->chans($who);
        $key                    = get_akey_nc($r->chan, $hchans); // Better safe than sorry
        $hchans[$key]['access'] = $newaccess;
        $hchans                 = serialize($hchans);

        $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :chans WHERE `name` = :hand");
        $stmt->execute(Array(':chans' => $hchans, ':hand' => $who));
        $stmt->closeCursor();

        $r->notice("$who now has $newaccess access to $r->chan.");
        return $r;
    }

    function cmd_adduser(CmdRequest $r)
    {
        if (!is_numeric($r->args[1]) || round($r->args[1], 2) <= 0) {
            throw new CmdException("{$r->args[1]} is an invalid access level. Must be a positive number.");
        }
        $newaccess = round($r->args[1], 2);

        if ($newaccess >= $r->access) {
            if (!$r->hasoverride) {
                throw new CmdException('You cannot give someone access greater then or equal to your own.');
            } else {
                $r->setOverride();
            }
        }

        try {
            $who = $this->getByNickOrAccount($r->args[0]);
        } catch (Exception $e) {
            throw new CmdException($e->getMessage());
        }

        if ($this->access($who, $r->chan) > 0) {
            throw new CmdException("$who already has access to $r->chan.");
        }

        $hchans                                 = $this->chans($who);
        $hchans[strtolower($r->chan)]['access'] = $newaccess;
        $hchans                                 = serialize($hchans);

        $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :chans WHERE `name` = :hand");
        $stmt->execute(Array(':chans' => $hchans, ':hand' => $who));
        $stmt->closeCursor();

        $r->notice("$who now has $newaccess access to $r->chan.");
        return $r;
    }

    function cmd_deluser(CmdRequest $r)
    {
        $chan = strtolower($r->chan);
        try {
            $who = $this->getByNickOrAccount($r->args[0]);
        } catch (Exception $e) {
            throw new CmdException($e->getMessage());
        }

        if ($this->access($who, $chan, true) >= $r->access) {
            if (!$r->hasoverride) {
                throw new CmdException('You cannot remove someone with access greater then or equal to your own.');
            } else {
                $r->setOverride();
            }
        }

        if ($this->access($who, $chan, true) == 0) {
            throw new CmdException("$who has no access to $chan.");
        }

        $hchans = $this->chans($who);
        unset($hchans[get_akey_nc($chan, $hchans)]);
        $hchans = serialize($hchans);

        $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :chans WHERE `name` = :hand");
        $stmt->execute(Array(':chans' => $hchans, ':hand' => $who));
        $stmt->closeCursor();

        $r->notice("$who's access has been removed from $chan.", 0, 1);
        return $r;
    }

    function cmd_users(CmdRequest $r)
    {
        $chan = strtolower($r->chan);

        $users  = explode(' ', trim($this->chan_users($chan)));
        $unsort = array();
        $r->notice("Showing (" . count($users) . ") users for $chan");
        if (count($users) > 1) {
            $out = array(array('Level', 'Username', '| Level', 'Username'));
        } else {
            $out = array(array('Level', 'Username'));
        }
        for ($i = 0; $i < count($users); $i++) {
            $user   = explode(':', $users[$i]);
            $level  = array_shift($user);
            $user   = implode('', $user);
            $unsort["$level $user"] = Array($level, $user);
        }
        arsort($unsort);
        $temp = Array();
        foreach ($unsort as $u) {
            $temp[] = $u;
        }
        $unsort = $temp;
        if (count($users) < 2) {
            for ($i = 0; $i < count($unsort); $i++) {
                $u     = $unsort[$i];
                $out[] = array($u[0], $u[1]);
            }
        } else {
            for ($i = 0; $i < count($unsort); $i++) {
                $u = $unsort[$i];
                $i++;
                if (array_key_exists($i, $unsort)) {
                    $u2 = $unsort[$i];
                } else {
                    $u2 = Array('', '');
                }
                $out[] = array($u[0], $u[1], '| ' . $u2[0], $u2[1]);
            }
        }
        $out = multi_array_padding($out);
        foreach ($out as &$line) {
            $r->notice(implode('', $line), 0, 1);
        }
    }

}


