<?php

class CmdBind {
    public string $module;
    public string $name;
    public string $access = "0";
    public string $func;
    public string $args = "";
    public int $loglvl = 0;

    public function __construct(string $module, string $name, string $func)
    {
        $this->module = $module;
        $this->name = $name;
        $this->func = $func;
        if (strpos($name, '#') !== false) {
            throw new Exception('Bind name cannot contain #');
        }
    }
}
