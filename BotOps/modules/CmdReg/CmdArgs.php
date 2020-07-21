<?php
//EXPORT

require_once __DIR__ . "/../../Tools/Tools.php";

//TODO use namespaces instead of Cmd...
class CmdSyntaxException extends \Exception {

};

class CmdParseException extends \Exception {

};

/**
 * Parse syntax and store arguments to command
 * Syntax rules:
 *  <arg> is a required arg
 *  <arg>... required multiword arg, must be last in list
 *  [arg] is an optional arg, all optionals must be at the end and no multiword arguments preceed
 *  [arg]... optional multiword arg, must be last in list
 * The arg name must not contain []<> or whitespace
 */
class CmdArgs implements ArrayAccess
{
    public string $syntax;
    /**
     * @var CmdArg[] $args
     */
    public array $args = Array();

    /**
     * CmdArgs constructor.
     * @param string $syntax
     * @throws CmdSyntaxException Will throw if syntax isnt valid
     */
    function __construct(string $syntax)
    {
        $this->syntax = $syntax;
        list($argc, $argv) = niceArgs($syntax);
        if ($argc == 0) {
            return;
        }
        foreach ($argv as $k => $a) {
            $matched = false;
            if (preg_match('/<([^>]+)>(\.\.\.)?/', $a, $m)) {
                if($m[0] != $a) {
                    throw new CmdSyntaxException("Invalid syntax: problem with $a");
                }
                //check that the last arg wasn't optional
                if($k != 0 && !$this->args[$k-1]->required) {
                    throw new CmdSyntaxException("Invalid syntax: required argument given after optional");
                }
                //check that the last arg wasn't multiword
                if($k != 0 && $this->args[$k-1]->multiword) {
                    throw new CmdSyntaxException("Invalid syntax: required argument given after multiword");
                }

                $mw = isset($m[2]) ? true : false;
                $this->args[$k] = new CmdArg(true, $m[1], $mw);
                $matched = true;
            }

            if (preg_match('/\[([^>]+)\](\.\.\.)?/', $a, $m)) {
                if($m[0] != $a) {
                    throw new CmdSyntaxException("Invalid syntax: problem with $a");
                }
                //check that the last arg wasn't multiword
                if($k != 0 && $this->args[$k-1]->multiword) {
                    throw new CmdSyntaxException("Invalid syntax: optional argument given after multiword");
                }

                $mw = isset($m[2]) ? true : false;
                $this->args[$k] = new CmdArg(false, $m[1], $mw);
                $matched = true;
            }
            if (!$matched) {
                throw new CmdSyntaxException("Invalid syntax: unknown syntax for $a");
            }
        }
    }

    /**
     * @param string $msg
     * @throws CmdParseException throws exception if required args arent provided
     */
    public function parse(string $msg) {
        foreach ($this->args as &$arg) {
            if($arg->required && trim($msg) == '') {
                throw new CmdParseException("Missing a required arg: $arg->name");
            }
            if($arg->multiword) {
                $arg->val = $msg;
                $msg = '';
            } else {
                if(preg_match('/ ?+([^ ]+) ?+/', $msg, $m)) {
                    $msg = substr($msg, strlen($m[0]));
                    $arg->val = $m[1];
                }
            }
        }
    }

    public function getArg(string $name): ?CmdArg {
        foreach ($this->args as &$arg) {
            if ($arg->name == $name) {
                if($arg->val == null) {
                    return null;
                }
                return $arg;
            }
        }
        return null;
    }

    //Readonly
    public function offsetSet($offset, $value) {
        return;
    }
    public function offsetExists($offset) {
        if(is_numeric($offset)) {
            return isset($this->args[$offset]) && $this->args[$offset]->val != null;
        }
        foreach ($this->args as &$arg) {
            if ($arg->name == $offset && $arg->val != null) {
                return true;
            }
        }
        return false;
    }
    public function offsetUnset($offset) {
        return;
    }
    public function offsetGet($offset): ?string {
        if(is_numeric($offset)) {
            if(isset($this->args[$offset]) && $this->args[$offset]->val != null) {
                return $this->args[$offset]->val;
            }
            return null;
        }
        $arg = $this->getArg($offset);
        if($arg) {
            return $arg->val;
        }
        return null;
    }
}

class CmdArg {
    public bool $required;
    public bool $multiword;
    public string $name;
    public ?string $val = "";
    public function __construct(bool $required, string $name, bool $multiword)
    {
        $this->required = $required;
        $this->name = $name;
        $this->multiword = $multiword;
    }

    public function __toString()
    {
        return $this->val;
    }
}