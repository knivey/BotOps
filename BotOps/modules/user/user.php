<?PHP

/* * *************************************************************************
 * BotNetwork Bots IRC Framework
 * Http://www.botnetwork.org/
 * Contact: irc://irc.gamesurge.net/bots
 * **************************************************************************
 * Copyright (C) 2009 BotNetwork
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * **************************************************************************
 * user.php
 *  User system module, provides irc commands to user system and functions
 *  to provide other modules access to the users.
 * ************************************************************************* */

require_once('modules/Module.inc');

class user extends Module
{

    function cmd_logout($nick, $target, $args)
    {
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        if ($hand == '') {
            $this->pIrc->notice($nick, "You are not authed.");
            return $this->ERROR;
        }
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `host` = NULL WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }
        $this->pIrc->notice($nick, "User $hand has been logged out.");
        return $this->OK;
    }

    function cmd_cookie($nick, $target, $args)
    {
        $arg  = explode(' ', $args);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        if ($hand != '') {
            $this->pIrc->notice($nick, "You are already authed to account $hand");
            return $this->ERROR;
        }
        if (empty($arg[1])) {
            return $this->BADARGS;
        }
        try {
            $stmt = $this->pMysql->prepare("SELECT `cookie`,`name` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $arg[0]));
            if ($stmt->rowCount() == 0) {
                $this->pIrc->notice($nick,
                                    "Account\2 $arg[0] \2has not been registered.");
                return $this->ERROR;
            }
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }

        ($row["cookie"]) ? $row["cookie"] = explode('.', $row["cookie"]) : '';
        if (empty($row["cookie"])) {
            $this->pIrc->notice($nick,
                                "There is no cookie issued for this account.");
            return $this->ERROR;
        }
        if ($row["cookie"][0] && (time() - $row["cookie"][0] > 86400)) {
            $this->pIrc->notice($nick,
                                "Cookie has expired. Please use the resetpass command to issue another one.");
            return $this->ERROR;
        }
        if ($arg[1] == $row["cookie"][1]) {
            try {
                $stmt = $this->pMysql->prepare("UPDATE `users` SET `host` = :host, `cookie` = NULL WHERE `name` = :hand");
                $stmt->execute(Array(':hand' => $arg[0], ':host' => $host));
                $stmt->closeCursor();
            } catch (PDOException $e) {
                $this->reportPDO($e, $nick);
                return $this->ERROR;
            }
            $this->pIrc->notice($nick,
                                "You are now authed to account $row[name]");
            $this->pIrc->notice($nick,
                                "Temporary cookie deleted. Remember to change your password!");
            return $this->OK;
        } else {
            $this->pIrc->notice($nick, "Cookie information is incorrect.");
            return $this->ERROR;
        }
    }

    function cmd_resetpass($nick, $target, $args)
    {
        $arg  = explode(' ', $args);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        if ($hand != '') {
            $this->pIrc->notice($nick, "You are already authed to account $hand");
            return $this->ERROR;
        }
        if (empty($arg[0])) {
            return $this->BADARGS;
        }
        try {
            $stmt = $this->pMysql->prepare("SELECT `cookie`,`name`,`id`,`email` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $arg[0]));
            if ($stmt->rowCount() == 0) {
                $this->pIrc->notice($nick,
                                    "Account\2 $arg[0] \2has not been registered.");
                return $this->ERROR;
            }
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }

        ($row["cookie"]) ? $row["cookie"] = explode('.', $row["cookie"]) : '';
        if (empty($row["email"])) {
            $this->pIrc->notice($nick,
                                "There is no email set for account " . chr(2) . $arg[0] . chr(2));
            return $this->ERROR;
        }
        if ($row["cookie"][0] && (time() - $row["cookie"][0] < 86400)) {
            $this->pIrc->notice($nick,
                                "A cookie has recently been issue for this account. Please wait for it to expire.");
            return $this->ERROR;
        }
        $row["cookie"] = chr(rand(97, 122)) . chr(rand(65, 90)) .
            chr(rand(97, 122)) . chr(rand(65, 90)) . rand(65, 90) .
            chr(rand(97, 122)) . chr(rand(97, 122));
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `cookie` = :cookie WHERE `id` = :id");
            $stmt->execute(Array(':id' => $row['id'], ':cookie' => time() . "." . $row["cookie"]));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }
        $postmail = array(
            $row["email"],
            "Login cookie",
            "This email is in reply to a request to login to your account. Please note that your password has not been changed." .
            "\n\nThe following command will allow you to alternatively login to your account." .
            "\n /msg BotOps cookie " . trim($arg[0]) . chr(32) . trim($row[cookie]) .
            "\n\nYour cookie will expire in 24 hrs and may only be used once." .
            "\n*Note: If you did not request this service, you do not have to do anything." .
            "\n\n--" .
            "\nPlease do not reply to this email! Nothing will happen =/",
            "From: BotOps Login Services\n"
        );
        if (mail($postmail[0], $postmail[1], $postmail[2], $postmail[3])) {
            $this->pIrc->notice($nick,
                                "A temporary cookie has been generated and sent to your email address.");
            return $this->OK;
        } else {
            try {
                $stmt = $this->pMysql->prepare("UPDATE `users` SET `cookie` = NULL WHERE `id` = :id");
                $stmt->execute(Array(':id' => $row['id']));
                $stmt->closeCursor();
            } catch (PDOException $e) {
                $this->reportPDO($e, $nick);
                return $this->ERROR;
            }
            $this->pIrc->notice($nick,
                                "Failed to email cookie. Request additional support in #bots");
            return $this->ERROR;
        }
    }

    function cmd_email($nick, $target, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
        if ($argc < 1) {
            return $this->BADARGS;
        }
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        if ($hand == '') {
            $this->pIrc->notice($nick,
                                "You are not authed with BotOps, auth first.");
            return $this->ERROR;
        }
        if (!isemail($argv[0])) {
            $this->pIrc->notice($nick, "$argv[0] is not a valid email address");
            return $this->ERROR;
        }
        //$curEmail = $this->getEmail($hand);
        //if($curEmail == '') {
        $this->setEmail($hand, $argv[0]);
        $this->pIrc->notice($nick, "Your email address has been set.");
        return $this->OK;
        //}
        //User already has an email address set
        //In the future we should send a cookie to the old+new addresses
    }

    function cmd_pass($nick, $target, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
        if ($argc < 1) {
            return $this->BADARGS;
        }
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        if ($hand == '') {
            $this->pIrc->notice($nick,
                                "You are not authed with BotOps, auth first.");
            return $this->ERROR;
        }
        /*
         * I think i want to add a few resitrictions to passwords
         * bassically make sure its longer then 5 characters for now i guess
         */
        if (strlen($argv[0]) < 5) {
            $this->pIrc->notice($nick,
                                "For security your password must be longer then 5 characters, password not updated.");
            return $this->ERROR;
        }
        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `pass` = :pass WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand, ':pass' => md5($argv[0])));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }
        $this->pIrc->notice($nick,
                            "Your password has been updated, don't forget it!");
    }

    function cmd_register($nick, $target, $args)
    {
        $arg  = explode(' ', $args);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);

        if ($hand != '') {
            $this->pIrc->notice($nick, "You are already authed to account $hand");
            return $this->ERROR;
        }

        if (empty($arg[0]) || empty($arg[1])) {
            return $this->BADARGS;
        }

        if ($this->hand_exists($arg[0])) {
            $this->pIrc->notice($nick, "That username already exists.");
            return $this->ERROR;
        }

        if (!ereg("^[a-zA-Z0-9_\-\+`<>\|]+$", $arg[0])) {
            $this->pIrc->notice($nick,
                                "Username may only contain alpha-numeric characters and the following _ - + < > ` |");
            return $this->ERROR;
        }

        $params = Array(
            ':name'   => $arg[0],
            ':pass'   => md5($arg[1]),
            ':date'   => time(),
            ':laston' => time(),
            ':host'   => $host,
            ':email'  => $arg[2],
            ':chans'  => 'a:0:{}',
        );
        $query  = "INSERT INTO `users` (`name`,`pass`,`datemade`,`laston`,`host`,`email`,`chans`)" .
            " VALUES(:name,:pass,:date,:laston,:host,:email,:chans)";

        if (empty($arg[2])) {
            $this->pIrc->notice($nick,
                                "Note without an email set you will " .
                "not be able to recover lost passwords if you decide to set " .
                "an email later please /msg " . $this->pIrc->currentNick() .
                " SET EMAIL <new address>");
            unset($params[':email']);
            $query = "INSERT INTO `users` (`name`,`pass`,`datemade`,`laston`,`host`,`chans`)" .
                " VALUES(:name,:pass,:date,:laston,:host,:chans)";
        } else {
            if (!isemail($arg[2])) {
                $this->pIrc->notice($nick,
                                    "$arg[2] is not a valid email address");
                return $this->ERROR;
            }
        }

        try {
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `users` WHERE `email`=:email");
            $stmt->execute(Array(':email' => $arg[2]));
            $stmt->closeCursor();
            if ($stmt->rowCount() > 0) {
                $this->pIrc->notice($nick,
                                    "$arg[2] has already been used to register an account. Please use a different email address.");
                return $this->ERROR;
            }

            $stmt = $this->pMysql->prepare($query);
            $stmt->execute($params);
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }

        $this->pIrc->notice($nick, "You are now authed to account $arg[0]");
        $this->pIrc->msg('#botstaff',
                         "Account $arg[0] has been regged by $nick.");
        return $this->OK;
    }

    function checkPass($user, $pass)
    {
        try {
            $stmt = $this->pMysql->prepare("SELECT `pass` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $user));
            if ($stmt->rowCount() == 0) {
                return false;
            }
            $row = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e);
            return $this->ERROR;
        }
        if (md5($pass) == $row['pass']) {
            return true;
        } else {
            return false;
        }
    }

    function cmd_auth($nick, $target, $args)
    {
        $arg  = explode(' ', $args);
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        if ($hand != '') {
            $this->pIrc->notice($nick, "You are already authed to account $hand");
            return $this->ERROR;
        }
        if (empty($arg[0]) || empty($arg[1])) {
            return $this->BADARGS;
        }
        try {
            $stmt = $this->pMysql->prepare("SELECT `pass`,`flags` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $arg[0]));
            if ($stmt->rowCount() == 0) {
                $this->pIrc->notice($nick,
                                    "Failed to auth, either the username $arg[0] doesn't exist.");
                return $this->ERROR;
            }
            $row = $stmt->fetch();
            $stmt->closeCursor();
            if (md5($arg[1]) == $row['pass']) {
                $stmt = $this->pMysql->prepare("UPDATE `users` SET `host`=:host,`cookie`=NULL,`lastseen`='now' WHERE `name` = :hand");
                $stmt->execute(Array(':hand' => $arg[0], ':host' => $host));
                $stmt->closeCursor();
                $this->pIrc->notice($nick,
                                    "You are now authed to account $arg[0]");
                if ($this->hasflags($arg[0], 'T|O', $row['flags'])) {
                    $this->pIrc->msg('#botstaff',
                                     "Notice $nick has authed to " . $this->staff_position($arg[0]) . " account $arg[0]");
                }
            } else {
                if ($this->hasflags($arg[0], 'T|O', $row['flags'])) {
                    $this->pIrc->notice($nick,
                                        "Failed to auth to " . $this->staff_position($arg[0]) . " account, either the username $arg[0] password $arg[1] was incorrect, This incident will be reported.");
                    $this->pIrc->msg('#botstaff',
                                     "Failed AUTH attempt on " . $this->staff_position($arg[0]) . " account $arg[0] by $nick");
                } else {
                    $this->pIrc->notice($nick,
                                        "Failed to auth, either the username $arg[0] doesn't exist or the password $arg[1] was incorrect");
                    $this->pIrc->msg('#botstaff',
                                     "Failed AUTH attempt on account $arg[0] by $nick");
                }
            }
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }
    }

    function cmd_whoami($nick, $target, $args)
    {
        $host = $this->pIrc->n2h($nick);
        $hand = $this->gM('user')->byHost($host);
        if ($hand == '') {
            $hand = 'You are not authed.';
        }
        $this->pIrc->notice($nick, $hand);
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
        try {
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `users` WHERE `host` = :host");
            $stmt->execute(Array(':host' => $host));
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
            $stmt = $this->pMysql->prepare("SELECT `name` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row  = $stmt->fetch();
            $stmt->closeCursor();
            return $row['host'];
        } catch (PDOException $e) {
            $this->reportPDO($e);
        }
    }

    function access($hand, $chan)
    {
        if ($this->hasflags($hand, 'L')) {
            return '0';
        }
        try {
            $stmt  = $this->pMysql->prepare("SELECT `chans` FROM `users` WHERE `name` = :hand");
            $stmt->execute(Array(':hand' => $hand));
            $row   = $stmt->fetch();
            $stmt->closeCursor();
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
            return $row['flags'];
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
     * For now delayed has been disabled, thinking of replaing
     * it with oneshot hooks. or something...
     */

    function na_arg($arg, $nick, $cmd = NULL)
    {
        $h = str_split($arg);
        if ($h[0] == '*') {
            unset($h[0]);
            $h = implode('', $h);
            if (!$this->hand_exists($h)) {
                $this->pIrc->notice($nick, "That account doesn't exist");
                return;
            }
        } else {
            $host = $this->pIrc->n2h($arg);
            if ($host == '') {
                /* $delayed[strtolower($arg)] = array (
                  "nick" => $nick,
                  "cmd"  => $cmd
                  );
                  $this->pIrc->raw("WHOIS $arg");
                  //			$this->pIrc->notice($nick, "$arg is not in any of my channels");
                 */
                $this->pIrc->notice($nick, "$arg isn't in any of my channels");
                return;
            }
            $h = $this->byHost($host);
        }
        if ($h == '') {
            $this->pIrc->notice($nick, "$arg is not authed");
            return;
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

    function cmd_access($nick, $target, $arg2)
    {
        list($argc, $argv) = niceArgs($arg2);
        $hand = $this->byNick($nick);
        $chan = strtolower($target);

        if ($argc < 1) {
            if ($hand == '') {
                $this->pIrc->notice($nick, "You are not authed with BotOps");
                $this->pIrc->notice($nick,
                                    "Syntax: /msg " . $this->pIrc->currentNick() . " auth <username> <password>");
                return $this->OK;
            }
            $who = $nick;
        } else {
            $who  = $argv[0];
            $hand = $this->na_arg($who, $nick);
            if ($hand == '') {
                return $this->OK;
            }
        }
        $access = $this->access($hand, $chan);

        if ($this->hasflags($hand, 'N|O|T|U')) {
            $epithet = $this->getEpithet($hand);
            $level   = $this->staff_position($hand);
            $this->pIrc->notice($nick, "$who is $epithet ($level)");
        }
        if ($access == 0) {
            if ($this->hasflags($hand, 'g')) {
                $this->pIrc->notice($nick,
                                    "$who ($hand) lacks access to $chan but has \2Override\2 enabled.");
            } else {
                $this->pIrc->notice($nick, "$who ($hand) lacks access to $chan");
            }
        } else {
            if ($this->hasflags($hand, 'g')) {
                $this->pIrc->notice($nick,
                                    "$who ($hand) has access level\2 $access \2in $chan and has \2Override\2 enabled.");
            } else {
                $this->pIrc->notice($nick,
                                    "$who ($hand) has access level\2 $access \2in $chan.");
            }
        }

        return $this->OK;
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

    function cmd_oset($nick, $chan, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
        $hand = $this->byNick($nick);
        if ($argc < 2) {
            return $this->BADARGS;
        }
        $h = $this->na_arg($argv[0], $nick);
        if ($h == null) {
            return $this->ERROR;
        }
        if ($this->ishigher($hand, $h) != $hand &&
            (!$this->hasflags($hand, 'D') && !$this->hasflags($hand, 'F'))) {
            $this->pIrc->notice($nick,
                                "$h has the same or more access then yourself");
            return $this->ERROR;
        }
        $what = strtolower($argv[1]);
        $val  = arg_range($argv, 2, -1);
        if ($what == 'epithet') {
            if ($val == '') {
                $val = $this->getEpithet($h);
                $this->pIrc->notice($nick, "$h's epithet is set to $val");
                return $this->ERROR; //no log on view
            }
            $this->setEpithet($h, $val);
            $this->pIrc->notice($nick, "$h's epithet set to $val");
            return;
        }
        if ($what == 'flags') {
            if ($val == '') {
                $val = $this->flags($h);
                $this->pIrc->notice($nick, "$h's flags are set to: $val");
                return $this->ERROR; //no log on view
            }
            if ($h == $hand && !$this->hasflags($hand, 'D') && !$this->hasflags($hand,
                                                                                'F')) {
                $this->pIrc->notice($nick,
                                    "You cannot set your own flags with this command");
                return $this->ERROR;
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
            $this->pIrc->notice($nick, "$h's flags are now " . $this->flags($h));
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

    function cmd_god($nick, $chan, $msg)
    {
        list($argc, $argv) = niceArgs($msg);
        $hand = $this->byNick($nick);
        if ($argc < 1) {
            if ($this->hasOverride($hand)) {
                $this->pIrc->notice($nick, $this->setOverride($hand, false));
            } else {
                $this->pIrc->notice($nick, $this->setOverride($hand, true));
            }
            return $this->OK;
        }
        if (strtolower($argv[0]) == 'on') {
            $this->pIrc->notice($nick, $this->setOverride($hand, true));
        }
        if (strtolower($argv[0]) == 'off') {
            $this->pIrc->notice($nick, $this->setOverride($hand, false));
        }
        return $this->OK;
    }

    function cmd_clvl($nick, $target, $arg2)
    {
        list($argc, $argv) = niceArgs($arg2);
        $hand   = $this->byNick($nick);
        $chan   = strtolower($target);
        $access = $this->access($hand, $chan);

        if ($argc < 2) {
            return $this->BADARGS;
        }

        $who = $this->na_arg($argv[0], $nick);
        if (empty($who)) {
            return;
        }

        if ($this->access($who, $chan) == 0) {
            $this->pIrc->notice($nick, "$who lacks access to $chan.");
            return $this->ERROR;
        }

        if (!is_numeric($argv[1])) {
            $this->pIrc->notice($nick, "$argv[1] is an invalid access level.");
            return $this->ERROR;
        }
        $newaccess = round($argv[1], 2);
        $curaccess = $this->access($who, $chan);

        $ret = $this->OK;
        if ($newaccess >= $access || $curaccess >= $access) {
            if ($this->hasflags($hand, 'g') == 0) {
                $this->pIrc->notice($nick,
                                    'You cannot alter access greater then or equal to your own.');
                return $this->ERROR;
            } else {
                $ret = $this->OVERRIDE | $this->OK;
            }
        }

        if ($newaccess == 0) {
            $this->pIrc->notice($nick,
                                "You cannot give someone 0 access; Use deluser instead.");
            return $this->ERROR;
        }

        $hchans                 = $this->chans($who);
        $key                    = get_akey_nc($chan, $hchans); // Better safe than sorry
        $hchans[$key]['access'] = $newaccess;
        $hchans                 = serialize($hchans);

        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :chans WHERE `name` = :hand");
            $stmt->execute(Array(':chans' => $hchans, ':hand' => $who));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }

        $this->pIrc->notice($nick, "$who now has $newaccess access to $chan.");
        return $ret;
    }

    function cmd_adduser($nick, $target, $arg2)
    {
        list($argc, $argv) = niceArgs($arg2);
        $hand   = $this->byNick($nick);
        $chan   = strtolower($target);
        $access = $this->access($hand, $chan);

        if ($argc < 2) {
            return $this->BADARGS;
        }

        if (!is_numeric($argv[1])) {
            $this->pIrc->notice($nick, "$argv[1] is an invalid access level.");
            return $this->ERROR;
        }
        $newaccess = round($argv[1], 2);

        $ret = $this->OK;
        if ($newaccess >= $access) {
            if ($this->hasflags($hand, 'g') == 0) {
                $this->pIrc->notice($nick,
                                    'You cannot give someone access greater then or equal to your own.');
                return $this->ERROR;
            } else {
                $ret = $this->OVERRIDE | $this->OK;
            }
        }

        $who = $this->na_arg($argv[0], $nick, NULL);
        if (empty($who)) {
            return $this->ERROR;
        }

        if ($this->access($who, $chan) > 0) {
            $this->pIrc->notice($nick, "$who already has access to $chan.");
            return $this->ERROR;
        }

        $hchans                  = $this->chans($who);
        $hchans[$chan]['access'] = $newaccess;
        $hchans                  = serialize($hchans);

        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :chans WHERE `name` = :hand");
            $stmt->execute(Array(':chans' => $hchans, ':hand' => $who));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }

        $this->pIrc->notice($nick, "$who now has $newaccess access to $chan.");
        return $ret;
    }

    function cmd_deluser($nick, $target, $arg2)
    {
        list($argc, $argv) = niceArgs($arg2);
        $hand   = $this->byNick($nick);
        $chan   = strtolower($target);
        $access = $this->access($hand, $chan);

        if ($argc < 1) {
            return $this->BADARGS;
        }

        $who = $this->na_arg($argv[0], $nick);
        if (empty($who)) {
            return $this->ERROR;
        }

        $ret = $this->OK;
        if ($this->access($who, $chan) >= $access) {
            if ($this->hasflags($hand, 'g') == 0) {
                $this->pIrc->notice($nick,
                                    'You cannot remove someone with access greater then or equal to your own.');
                return $this->ERROR;
            } else {
                $ret = $this->OK | $this->OVERRIDE;
            }
        }

        if ($this->access($who, $chan) == 0) {
            $this->pIrc->notice($nick, "$who has no access to $chan.");
            return $this->ERROR;
        }

        $hchans = $this->chans($who);
        unset($hchans[get_akey_nc($chan, $hchans)]);
        $hchans = serialize($hchans);

        try {
            $stmt = $this->pMysql->prepare("UPDATE `users` SET `chans` = :chans WHERE `name` = :hand");
            $stmt->execute(Array(':chans' => $hchans, ':hand' => $who));
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $this->reportPDO($e, $nick);
            return $this->ERROR;
        }

        $this->pIrc->notice($nick, "$who's access has been removed from $chan.");
        return $ret;
    }

    function cmd_users($nick, $target, $arg2)
    {
        $chan = strtolower($target);

        /*
         * Fix this later when we find what to do with chan_search
          if(!empty($arg[0])) {
          if(chan_search($arg[0]) != '') {
          $chan = $arg[0];
          } else {
          $this->pIrc->notice($nick, "$arg[0] is not registered.");
          return 1;
          }
          }
         */
        $users  = explode(' ', trim($this->chan_users($chan)));
        $unsort = array();
        $this->pIrc->notice($nick,
                            "Showing (" . count($users) . ") users for $chan");
        if (count($users) > 1) {
            $out = array(array('Level', 'Username', 'lastonline', '| Level', 'Username',
                    'lastonline'));
        } else {
            $out = array(array('Level', 'Username', 'lastonline'));
        }
        for ($i = 0; $i < count($users); $i++) {
            $user   = explode(':', $users[$i]);
            $level  = array_shift($user);
            $user   = implode('', $user);
            $online = '';
            $online = $this->laston($user);
            if ($online != 'now' && $online != '') {
                $online = strftime('%D %H:%M', $online);
            }
            $unsort["$level $user"] = Array($level, $user, $online);
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
                $out[] = array($u[0], $u[1], $u[2]);
            }
        } else {
            for ($i = 0; $i < count($unsort); $i++) {
                $u = $unsort[$i];
                $i++;
                if (array_key_exists($i, $unsort)) {
                    $u2 = $unsort[$i];
                } else {
                    $u2 = Array('', '', '');
                }
                $out[] = array($u[0], $u[1], $u[2], '| ' . $u2[0], $u2[1], $u2[2]);
            }
        }
        $out = multi_array_padding($out);
        foreach ($out as &$line) {
            $this->pIrc->notice($nick, implode('', $line));
        }
        return $this->OK;
    }

}

?>
