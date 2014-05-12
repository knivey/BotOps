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
 * Config.inc
 *  Load our configuration file
 * ************************************************************************* */

require_once 'Tools/Tools.php';

/**
 * Load and access our block config format
 * @author knivey
 *
 */
class Config {

    public $info = Array();
    private $data; //buffer
    private $fn; // filename

    /**
     * Filename must be a configuration file to load
     * @param string $filename
     */
    function __construct($filename) {
        $this->fn = $filename;
        if (!$this->readFile($filename)) {
        	// @codeCoverageIgnoreStart
            die("Error reading $filename\n");
            // @codeCoverageIgnoreEnd
        }
        $pos = Array();
        $num = 0;
        foreach ($this->data as $line) {
            $num++;
            $line = trim($line);
            if ($line == NULL || $line{0} == '#') {
                continue; // line was a comment
            }

            $args = makeArgs(trim($line, "{}"));
            $last = $line{strlen($line) - 1};

            if ($last == '{') {
                $pos[] = $args[0];
            }
            if ($last == '}') {
                array_pop($pos);
                continue;
            }
            //echo "$num pos = " . implode(' ', $pos) . "\n$num $line\n";
            if (!isset($pos[0]) || !isset($args[1]))
                continue;
            $this->info[$pos[0]][array_shift($args)] = $args;
        }
    }

    public function getInfo() {
        return $this->info;
    }

    function readFile($file) {
        $this->data = Array();
        if (file_exists($file)) {
            $fp = fopen($file, 'r');
            while (!feof($fp)) {
                $this->data[] = trim(fgets($fp));
            }
            return true;
        }
        return false;
    }
}