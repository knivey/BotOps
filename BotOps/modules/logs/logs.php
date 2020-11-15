<?php

class logs extends Module {
    /*
     * Each module should describe their own log table in
     * their registry config. When we parse the registry
     * this module will make sure the mysql table will match
     * the description, only col addition changes will be made
     * other changes will become a little more complicated
     * maybe requiring some user interaction...
     *
     * Modules will be responsible for adding things such as
     * time() or anything of that nature HOWEVER all tables will
     * have a col id as autoinc prikey
     *
     * The module will provide functions to append to the logs
     * and functions to search the logs or retrive by date range.
     *
     * Searching could be simple as forming the right mysql query
     * to retrive the rows wanted.
     *
     * Information will probably be easiest to just pass by an
     * associative array.
     */
    
    /**
     * List of the table definitions
     * ([modname] => ([Field] => [Field] [Type] [Null] [Key] [Default] [Extra]))
     */
    public array $dbs = Array();


    function rehash(&$old) {
        $this->dbs = $old->dbs;
        echo "Logs rehashed";
    }

    //Slot for module unloaded
    function moduleUnloaded(string $name) {
        //cleanup our sets
        echo "Logs unloading module $name\n";
        unset($this->dbs[$name]);
    }

    function moduleReloaded(string $name) {
        echo "Logs unloading module $name for reload\n";
        unset($this->dbs[$name]);
        $this->moduleLoaded($name);
    }

    function moduleLoaded(string $name) {
        echo "logs loading module $name\n";
        $info = $this->pMM->getConf($name, 'logs');
        //var_dump($info);
        if ($info == null)
            return;
        //Handle our section of registry.conf here

        /*
         * See if table exists...
         * If not make and empty table
         *
         * Get table description..
         *
         * Read through registry.conf table and form an
         * alter table query based on the differences.
         */

        $ccols = Array();

        /*
         * TODO whats in the module configs should be trustable, maybe should validate or escape strings tho?
         * probably doesnt matter now since i plan to move to Doctrine
         */

        try {
            $qname = $this->mq("logs_$name");
            $stmt = $this->pMysql->query("show tables like '$qname'");
            $cnt = $stmt->rowCount();
            $stmt->closeCursor();
            if ($cnt == 0) {
                $this->pMysql->query("CREATE TABLE '$qname' (id int(11) NOT NULL auto_increment, primary key (id))");
            }

            foreach ($this->pMysql->query("DESCRIBE `$qname`") as $bc) {
                $ccols[$bc['Field']] = $bc;
            }

            foreach ($info as $colname => $i) {
                //[colname] [type] [null] [key] [default] [extra])
                if(!isset($i['type'])) {
                    throw new Exception("Logs entry missing type");
                }
                $i['null'] ??= 'NULL';
                $i['default'] ??= '';
                $i['key'] ??= '';
                $i['extra'] ??= '';
                $ak = get_akey_nc($colname, $ccols);
                if ($ak == '') {
                    $this->pMysql->query("ALTER TABLE `$qname` ADD COLUMN '$colname' $i[type] $i[null]");
                }
            }
            
            unset($this->dbs[$name]);
            foreach ($this->pMysql->query("DESCRIBE `$qname`") as $bc) {
                $this->dbs[$name][$bc['Field']] = $bc;
            }
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
            //TODO if it failed probably shouldnt allow logging to continue
        }
    }

    /*
     * $Search should be an array looking like:
     * $Search['Field'] = Array('LIKE', 'value')
     * $Search['Field'] = Array('=', 'value')
     * $Search['Field'] = Array('<=', 'value')
     * $Search['Field'] = Array('>', 'value')
     * so on..
     * 
     * we need to be able to search better actually
     * tell it to OR 
     */

    function getLogs($mod, $search, $limit = 5) {
        try {
            if (!array_key_exists($mod, $this->dbs)) {
                return -1;
            }
            $tname = $this->mq("logs_$mod");
            $query = "SELECT * FROM `$tname` WHERE ";
            $toapp = Array();
            foreach (array_keys($search) as $k) {
                //make sure the col exists
                if (!array_key_exists($k, $this->dbs[$mod])) {
                    return -1;
                }
                $toapp[] = '`' . $this->mq($k) . '`' . ' ' . $this->mq($search[$k][0]) . ' "' . $this->mq($search[$k][1]) . '" ';
            }
            $query .= implode(' AND ', $toapp);
            $query .= " ORDER BY `id` DESC LIMIT " . $this->mq($limit);
            $stmt = $this->pMysql->query($query);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

    function log($mod, $info) {
        try {
            $tname = $this->mq("logs_$mod");
            if (!array_key_exists($mod, $this->dbs)) {
                return -1;
            }
            $query_a = '';
            $query_b = '';
            foreach (array_keys($info) as $k) {
                //make sure the col exists
                if (!array_key_exists($k, $this->dbs[$mod])) {
                    return -1;
                }
                $query_a .= '`' . $this->mq($k) . '`,';
                $query_b .= '\'' . $this->mq($info[$k]) . '\',';
            }
            
            $query = "INSERT INTO `$tname` (" . trim($query_a, ',') . ") values(" . trim($query_b, ',') . ")";
            $this->pMysql->query($query);
            if ($mod == 'CmdReg' && !$info['override']) {
                return;
            }
            $out = '';
            foreach ($info as $k => $v) {
                $skip = false;
                switch ($k) {
                    case 'date':
                    case 'override':
                    case 'bot':
                    case 'host':
                        $skip = true;
                        break;
                    default:
                        $skip = false;
                }
                if ($v != '' && !$skip) {
                    $out .= "\2$k:\2 $v ";
                }
            }
            $out = trim($out);
            $this->pIrc->msg('#botstaff', "\2[LOGS:$mod]:\2 $out", 1, 0);
        } catch (PDOException $e) {
            $PDO_OUT = $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine();
            echo "PDO Exception: $PDO_OUT\n" . $e->getTraceAsString();
            $this->pIrc->msg('#botstaff', "PDO Exception: $PDO_OUT");
        }
    }

}

?>
