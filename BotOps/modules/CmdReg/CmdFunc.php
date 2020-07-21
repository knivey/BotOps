<?php

class CmdFunc
{
    public string $module;
    public string $name;
    public string $syntax = '';
    public string $desc;
    public bool $pmonly;
    public bool $needchan;

    public function __construct(string $module, string $name, string $desc, bool $pmonly, bool $needchan)
    {
        $this->module = $module;
        $this->name = $name;
        $this->desc = $desc;
        $this->pmonly = $pmonly;
        $this->needchan = $needchan;
    }
}
