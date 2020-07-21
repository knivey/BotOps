<?php
//EXPORT

require_once __DIR__ . "/../../IRC/Irc.inc";
require_once 'CmdArgs.php';

/**
 * All the details for calling a command function
 */
class CmdRequest
{
    public string $nick;
    public string $host;
    public ?string $account;
    public ?string $chan;
    /**
     * Was the command called to the bot by a privmsg
     */
    public bool $pm;
    public CmdArgs $args;
    private Irc $irc;

    //variables used for return status
    /**
     * @var bool set if the command failed
     */
    public bool $failed = false;
    /**
     * @var bool if override was needed
     */
    public bool $override = false;

    public bool $hasoverride = false;

    public string $access;

    function __construct(Irc &$irc, string $nick, bool $pm, ?string $chan, CmdArgs $args, string $host, ?string $account, ?string $access, bool $hasoverride = false)
    {
        $this->irc = &$irc;
        $this->nick = $nick;
        $this->pm = $pm;
        $this->chan = $chan;
        $this->args = $args;
        $this->host = $host;
        $this->account = $account;
        $this->access = $access;
        $this->hasoverride = $hasoverride;
    }

    function reply($msg, $no_f = 1, $no_p = 0) {
        if ($this->pm) {
            $this->irc->msg($this->nick, $msg, $no_f, $no_p);
        } else {
            $this->irc->msg($this->chan, $msg);
        }
    }

    function notice($msg, $no_f = 1, $no_p = 0) {
        $this->irc->notice($this->nick, $msg, $no_f, $no_p);
    }

    function setOverride() {
        $this->override = true;
    }
}

/**
 * Class CmdException throw for general issues
 */
class CmdException extends Exception {
    /**
     * Should the error be displayed as a reply instead of notice
     */
    public bool $asReply = false;

    public function asReply()
    {
        $this->asReply = true;
    }
}

/**
 * Class CmdArgsException throw for issue with input and will display help
 */
class CmdArgsException extends Exception {
    /**
     * Should the error be displayed as a reply instead of notice
     */
    public bool $asReply = false;

    public function asReply()
    {
        $this->asReply = true;
    }
}
