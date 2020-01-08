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
 * Module.inc
 *  Base class for Modules to extend from
 * ************************************************************************* */

/**
 * Class Module
 * @author knivey
 */
class Module
{

    /* classes */

    /**
     *  @var Irc
     */
    public $pIrc;

    /**
     *  @var Sockets
     */
    public $pSockets;

    /**
     *  @var BotNet
     */
    public $pBotNet;

    /**
     *  @var PDO
     */
    protected $pMysql;

    /**
     *  @var ModuleManager
     */
    public $pMM;

    /**
     * Configuration file section for this module
     * @var Array $pCInfo
     */
    public $pCInfo;
    //return values here for CmdReg commands
    public $OK       = 1; //everything went fine
    public $BADARGS  = 2; //syntax used wrong
    public $OVERRIDE = 4; //Overide was used
    public $ERROR    = 8; //Problem while running we should do nothing

    public function setVars(Irc $pIrc, Sockets $pSockets, $pBotNet, PDO $pMysql,
                            ModuleManager $pMM, $pCInfo)
    {
        $this->pIrc     = $pIrc;
        $this->pSockets = $pSockets;
        $this->pBotNet  = $pBotNet;
        $this->pMysql   = $pMysql;
        $this->pMM      = $pMM;
        $this->pCInfo   = $pCInfo;
    }

    public function init()
    {
        /* called when modules is loaded */
    }

    public function logic()
    { //called every loop
    }

    /**
     * Shortcut wrapper to get module
     * @param string $mod name of the module to get instance of
     * @return Module
     */
    public function gM($mod)
    {
        return $this->pMM->modules[$mod]['class'];
    }

    public function cleanup()
    {
        /* called if modules needs to be removed */
    }

    public function rehash(&$LastClass)
    {
        //called when module needs to copy into a new version of itself
        //some modules may just to just cleanup and start new
    }

    public function mq($value)
    {
        return substr($this->pMysql->quote($value), 1, -1);
    }

    /**
     * Get an element from the bot main.conf file, used mainly for api keys
     * Will return an array the first element is an error if not null
     * The second element is the returnable info
     * 
     * Each module may have a section in the main.conf file matching its name.
     * 
     * @param string $name
     * @return multitype:string mixed
     */
    public function pGetConfig(string $name): array
    {
        if (!isset($this->pCInfo[$name])) {
            return Array('Config item "'. $name .'" not found', null);
        }
        return Array(null, $this->pCInfo[$name]);
    }

    public function reportPDO($e, $nick = NULL)
    {
        $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
        echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
        $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        if ($n != NULL) {
            $this->pIrc->notice($nick,
                                "We're sorry but an unexpected error has occurred. Staff have been notified.");
        }
    }

}
