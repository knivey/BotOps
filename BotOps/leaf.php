<?php

/*
 * **************************************************************************
 * leaf.php
 *  Main file to run for leaf bots.
 * **************************************************************************
 */


date_default_timezone_set('EST5EDT');

declare(ticks = 1);

$pid = pcntl_fork();

if ($pid == -1) {
    die("could not fork");
} else if ($pid) {
    exit();
} else {
    echo "Forked into the background\n";
}

//ob_end_clean(); // Discard the output buffer and close
fclose(STDIN);    // Close all of the standard
//fclose(STDOUT); // file descriptors as we
//fclose(STDERR); // are running as a daemon.


$pid = posix_getpid();

if (!posix_setsid()) {
    die("could not detach from terminal");
}

include_once 'IRC/Irc.inc';
include_once 'modules/ModuleManager.inc';
include_once 'Tools/Duration.inc';
include_once 'Tools/OAuth.php';
include_once 'Tools/twitteroauth.php';

require __DIR__ . '/../vendor/autoload.php';
use Amp\Loop;
use Nette\Neon\Neon;


$dead = false;

$signalWatcher = Loop::onSignal(SIGTERM, function () {
    global $dead;
    allmsg("#bots,#botstaff", "Received SIGTERM lol guess i quit now ;[");
    allquit("SIGTERM");
    $dead = true;
});

function allmsg($target, $message) {
    global $bots;
    foreach ($bots as &$bot) {
        $bot->Irc->raw("PRIVMSG $target :$message");
    }
}

function allquit($message) {
    global $bots;
    foreach ($bots as &$bot) {
        $bot->Irc->killBot($message);
    }
}

/*
 * Load the config file
 */
$configFile = file_get_contents("main.conf");
if($configFile === false) {
    die("Please make a main.conf first\n");
}
try {
    $config = Neon::decode($configFile);
} catch (Nette\Neon\Exception $e) {
    die("Error reading config: " . $e->getMessage() . "\n");
}

if(!isset($config['database']) || count(array_diff(['driver', 'user', 'pass', 'database'], array_keys($config['database']))) > 0) {
    die("main.conf needs a full database section\n");
}
$dbc = $config['database'];

$bots = Array();

$usage = "Usage php leaf.php [botnick1] [botnick2]...
    Config file is always main.conf most options are in mysql
    If no botnicks are givin starts all active bots.\n";

$dbc = $config['database'];
$my_options = Array(
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
);
try {
    $Mysql = new PDO($dbc['driver'].':host=' . $dbc['host'] . ';dbname=' . $dbc['database'] . ';charset=latin1',
        $dbc['user'], $dbc['pass'], $my_options);
} catch (PDOException $e) {
    die("Exception while connecting to MySQL: " . $e->getMessage() . "\n".
        "Make sure main.conf is properly setup with database connection info\n");
}

try {
    $botnames = Array();
    foreach ($Mysql->query('select name,active from bots where active = 1') as $bot) {
        $botnames[] = $bot['name'];
    }
} catch (PDOException $e) {
    echo "Exception while selecting active bots list: " . $e->getMessage() . "\n";
    die();
}

$MySQL_LastPing = time();

try {
    $MySQL_TimeOut = 3600;
    $res           = $Mysql->query("show global variables like 'wait_timeout'")->fetch();
    $MySQL_TimeOut = (int) $res['Value'] * (0.9); // set it to a little less for a safe margin
} catch (PDOException $e) {
    echo "Exception while getting mysql timeout: " . $e->getMessage() . "\n";
    die();
}

$startbots = Array();

$arf = $argv;
unset($arf[0]); //command name

if (empty($arf)) {
    echo "Populating startbots from mysql\n";
    //search mysql list for active bots...
    $startbots = $botnames;
} else {
    foreach ($arf as $b) {
        if ($b[0] == '-' && $b[1] == '-') {
            $b = substr($b, 2);
            if ($b == 'help') {
                die($usage);
            }
            if (substr($b, 0, strlen('config')) == 'config') {
                die($usage);
            }
        } else {
            if (array_search($b, $botnames) === false) {
                die("There is no configuration for that bot: $b\n");
            }
            $startbots[] = $b;
        }
    }
}

echo "Starting bots: " . implode(', ', $startbots) . "\n";
$MLoader = new ModuleLoader();
function startbots(array $startbots) {
    global $bots, $config, $sockets, $Mysql;
    foreach ($startbots as $b) {
        $bots[$b] = new Bot($b, $config, $sockets, $Mysql);
        echo "Starting Bot $b\n";
        if (!$bots[$b]) {
            die("FAILED TO CREATE BOT\n");
        }
        $bots[$b]->init();
        $bots[$b]->Irc->connect();
    }
}
startbots($startbots);

class Bot {
    public Irc $Irc;
    public PDO $Mysql;
    public ModuleManager $ModuleManager;
    public $Config;
    public $XMLRPC;
    public ?string $bindIp;
    public $botInfo;
    public $name;

    function __construct($name, &$config, PDO &$mysql) {
        $this->name    = $name;
        $this->Mysql   = &$mysql;
        $this->Config  = &$config;
    }

    function stop() {
        $this->XMLRPC->stop();
        $this->ModuleManager->stop();
        unset($this->XMLRPC);
        unset($this->Irc);
        unset($this->ModuleManager);
    }

    function __destruct() {
        echo "BOT " . $this->name . " IS BEING DESTROYED!!!!\n";
    }

    function init() {
        global $MLoader;
        $cInfo = $this->Config;

        // Fetch info about this bot
        try {
            $stmt    = $this->Mysql->prepare("SELECT * FROM `bots` WHERE `name` = :name");
            $stmt->bindValue(':name', $this->name, PDO::PARAM_STR);
            $stmt->execute();
            $botinfo = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            echo "PDO Exception: " . $e->getMessage() . "\n";
            echo " - " . $e->getFile() . ':' . $e->getLine() . "\n";
            die();
        }

        if (empty($botinfo)) {
            Echo "BOTINFO WAS EMPTY!!! (wrong botnick given)\n";
            die();
        }

        echo "Info for $this->name\n";
        var_dump($botinfo);

        //Initialize its IRC connection
        $this->Irc = new Irc($this->name, $botinfo['ip'], $botinfo['server'], $botinfo['ipv'],
            $botinfo['port'], $botinfo['pass'], $cInfo['irc']['connect_timeout'] ?? 15,
            $cInfo['irc']['ping_timeout'] ?? 90);

        $this->Irc->wraplen   = $cInfo['irc']['wraplen'] ?? 400;
        $userline_user = $botinfo['user'] ?? 'bots';
        $userline = $botinfo['userline'] ?? 'localhost localhost :IRC Bot Services #Bots';
        $this->Irc->user      = "$userline_user $userline";
        $this->Irc->authserv  = $botinfo['authserv'];
        $this->Irc->usermodes = $botinfo['usermodes'];
        $this->bindIp         = $botinfo['ip'];
        $this->botInfo        = $botinfo;

        $this->loadFilters();
        //TODO replace with amp
        $this->XMLRPC = new HttpServ($botinfo['xmlip'], $botinfo['xmlport'], true);
        $this->XMLRPC->init();

        $this->ModuleManager = new ModuleManager($this->Irc,
            $this->Mysql, $this->XMLRPC, $MLoader, $cInfo);
        $this->ModuleManager->init();
        $this->Irc->pMM      = $this->ModuleManager;
    }

    function loadFilters() {
        $filters = Array();

        foreach ($this->Mysql->query('SELECT * FROM filters') as $row) {
            $filters[$row['id']] = $row;
        }

        $this->Irc->ircFilters->loadFilters($filters);
        $this->Irc->ircFilters->setFilterHandler($this, 'ircCaughtFilter');
    }

    function ircCaughtFilter($filt, $txt) {
        $ss = explode(' ', $txt);
        try {
            $stmt = $this->Mysql->prepare("update filters set caught=caught+1 where id = :id");
            $stmt->bindValue(':id', $filt['id'], PDO::PARAM_STR);
            $stmt->execute();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            echo "Exception while increasing filter caught count: " . $e->getMessage() . "\n";
            $this->Irc->msg('#botstaff', "Exception while increasing filter caught count: " . $e->getMessage());
        }
        $this->Irc->msg('#botstaff', "\2WARNING:\2 Irc filter [$filt[id]] rule broken in a $ss[0] to $ss[1]");
    }

    function go() {
        $this->ModuleManager->processSignals();
        $this->ModuleManager->logic();
        $this->Irc->logic();
    }

}

while (count($bots) > 0) {
    if ($MySQL_LastPing + $MySQL_TimeOut < time()) {
        $MySQL_LastPing = time();
        $Mysql->query("SELECT 'ping'")->closeCursor();
        echo "Sending MySQL Ping\n";
    }

    foreach ($bots as $key => &$bot) {
        $bot->go();

        if ($bot->Irc->canDie) {
            //bot is ready to be removed
            $bot->stop();
            unset($bots[$key]);
            gc_collect_cycles();
        }
    }
}
