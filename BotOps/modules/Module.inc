<?PHP

/**
 * Base class for Modules to extend from
 */
class Module
{
    /**
     * @var Irc
     */
    public $pIrc;

    /**
     * @var PDO
     */
    protected $pMysql;

    /**
     * @var ModuleManager
     */
    public $pMM;

    /**
     * Configuration file section for this module
     * @var Array $pCInfo
     */
    public $pCInfo;


    public function setVars(Irc $pIrc, PDO $pMysql, ModuleManager $pMM, $pCInfo)
    {
        $this->pIrc = $pIrc;
        $this->pMysql = $pMysql;
        $this->pMM = $pMM;
        $this->pCInfo = $pCInfo;
    }

    /**
     * Called after module is created and setVars complete
     */
    public function init()
    {
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

    /**
     * Called when this module is reloaded
     * @param $LastClass Module reference to old instance (typically used to copy data from)
     */
    public function rehash(Module &$LastClass)
    {
    }

    /**
     * Called when any module is loaded
     * @param $name string Name of module that was loaded
     */
    public function moduleLoaded(string $name)
    {
    }

    /**
     * Called when any module is unloaded
     * @param $name string Name of module that was loaded
     */
    public function moduleUnloaded(string $name)
    {
    }

    /**
     * Called when any module is reloaded
     * @param $name string Name of module that was loaded
     */
    public function moduleReloaded(string $name)
    {
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
            return Array('Config item "' . $name . '" not found', null);
        }
        return Array(null, $this->pCInfo[$name]);
    }


    //TODO Get rid of this?
    public function reportPDO($e, $nick = NULL)
    {
        $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
        echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
        $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        if ($nick != NULL) {
            $this->pIrc->notice($nick,
                "We're sorry but an unexpected error has occurred. Staff have been notified.");
        }
    }

}
