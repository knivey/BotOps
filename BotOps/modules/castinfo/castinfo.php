<?php

require_once('modules/Module.inc');
require_once('Http.inc');

class castinfo extends Module {
	public function cmd_castinfo($nick, $target, $arg2) {
		//$this->pIrc->msg($target, 'Disabled until shoutcast v2 is added ;[');
		//return;
		if(stripos($arg2, 'http://') === FALSE) {
			$arg2 = 'http://' . $arg2;
		}
		$url = parse_url($arg2);
		if($url === false || !isset($url['host'])) {
			$this->pIrc->msg($target, "The url was not recognised.");
			return;
		}
		if(!isset($url['path']) || $url['path'] == '/') {
			$path = '/index.html';
		} else {
			$path = $url['path'];
		}
		if(!isset($url['port']) && $url['host'] != 'yp.shoutcast.com') {
			$port = '8000';
		} else {
			$port = $url['port'];
		}
		if(!isset($url['query'])) {
			$q = '';
		} else {
			$q = '?' . $url['query'];
		}
		$moo = 'http://' .$url['host'] . ':' . $port . $path . $q;
		//$this->pIrc->msg($target, "Attempting query to $moo");
		echo "CastInfo Query: $moo\n";
		$lol = new Http($this->pSockets, $this, 'castinfoRecv');
		$lol->getQuery($moo, Array($moo, $target));
	}
	
	public function castinfoRecv($data, $info) {
		if(is_array($data)) {
			$this->pIrc->msg($info[1], "\2Castinfo:\2 Error ($data[0]) $data[1]");
			return;
		}
		$ip = $info[0];
		$target = $info[1];
		//check if what we got was a playlist file...
		if (strpos($data, '[playlist]') !== FALSE) {
	
			//for now we just gonna grab first File1 entry
			$startText = 'File1=';
			$endText = 'Title1=';
			$start = strpos($data, $startText) + strlen($startText);
			$end = strpos($data, $endText, $start);
			$res = trim(substr($data, $start, $end - $start));
			$lol = new Http($this->pSockets, $this, 'castinfoRecv');
			$url = parse_url($res);
			if(!isset($url['port'])) {
				$url['port'] = '8000';
			}
			$moo = 'http://' . $url['host'] . ':' . $url['port'] . '/index.html';
			$info[0] = $res;
			$lol->getQuery($moo, $info);
			return;
		}
	
		if (strpos($data, "<title>Icecast Streaming Media Server</title>") === FALSE) {
			if(strpos($data, "<title>SHOUTcast DNAS Summary</title>") !== FALSE || strpos($data, "SHOUTcast Server v2") !== FALSE) {
				$cast = 'ShoutCast2';
				//$this->pIrc->msg($target, "Got response from $ip looks like $cast");
			} else {
				$cast = 'ShoutCast';
				//$this->pIrc->msg($target, "Got response from $ip looks like $cast");
			}
		} else {
			$cast = 'IceCast';
			//$this->pIrc->msg($target, "Got response from $ip looks like $cast");
		}
		if ($cast == 'IceCast') {
			//not fetching status2.xsl because it looks to be broken
			$url = parse_url($ip);
			if(!isset($url['query'])) {
				$url['query'] = '?sid=1';
			}
			if(!isset($url['port'])) {
				$url['port'] = '8000';
			}
			$moo = $url['host'] . ':' . $url['port'] . '/status.xsl' . $url['query'];
			$lol = new Http($this->pSockets, $this, 'iceRecv');
			$lol->getQuery($moo, $info);
		}
	
		if ($cast == 'ShoutCast2') {
			$url = parse_url($ip);
			if(!isset($url['query'])) {
				$url['query'] = 'sid=1';
			}
			if(!isset($url['port'])) {
				$url['port'] = '8000';
			}
			$moo = $url['host'] . ':' . $url['port'] . '/stats' . '?' . $url['query'];
			//$this->pIrc->msg($target, "Sending query to $moo");
			$lol = new Http($this->pSockets, $this, 'sc2Recv');
			$lol->getQuery($moo, $info);
		}
	
		if ($cast == 'ShoutCast') {
			$url = parse_url($ip);
			if(!isset($url['port'])) {
				$url['port'] = '8000';
			}
			$moo = $url['host'] . ':' . $url['port'] . '/7.html';
			$info[0] = $url['host'] . ':' . $url['port'];
			$lol = new Http($this->pSockets, $this, 'scRecv');
			$lol->getQuery($moo, $info);
		}
	}
	
	public function iceRecv($read, $info) {
		if(is_array($read)) {
			$this->pIrc->msg($info[1], "\Castinfo:\2 Error ($read[0]) $read[1]");
			return;
		}
		$ip = $info[0];
		$target = $info[1];
		$temp_array = array();
	
		$search_for = "<td class=\"streamdata\">(.*?)<\/td>";
		$search_td = array('<td class="streamdata">','</td>');
	
		if(preg_match_all("/$search_for/i",$read,$matches)) {
			foreach($matches[0] as $match) {
				$to_push = str_replace($search_td,'',$match);
				$to_push = strip_tags($to_push);
				$to_push = trim(html_entity_decode($to_push, ENT_QUOTES, 'UTF-8'));
				array_push($temp_array,$to_push);
			}
		}
	
		if(count($temp_array)) {
			//sort our temp array into our ral array
			$title = $temp_array[0];
			$desc = $temp_array[1];
			$content_type = $temp_array[2];
			$mount_start = $temp_array[3];
			$bitrate = $temp_array[4];
			$listeners = $temp_array[5];
			$peakListeners = $temp_array[6];
			$genre = $temp_array[7];
			$urll = $temp_array[8];
			$song = $temp_array[9];
			if(isset($temp_array[9])) {
				$x = explode(" - ",$temp_array[9]);
				$now_playing_artist = $x[0];
				$now_playing_track = $x[1];
			}
		}
		$url = parse_url($ip);
		if (!isset($url['query'])) {
			$url['query'] = '?sid=1';
		}
		if (!isset($url['port'])) {
			$url['port'] = '8000';
		}
	
		$startText = '<td><h3>Mount Point ';
		$endText = '</h3>';
		$start = strpos($read, $startText) + strlen($startText);
		$end = strpos($read, $endText, $start);
		$res = strip_tags(trim(substr($read, $start, $end - $start)));
		$ip = 'http://' . $url['host'] . ':' . $url['port'] . $res . '.m3u';
	
		$this->pIrc->msg($target, "\2IceCast2 (\2$title\2): Genre:\2 $genre \2Listen:\2 $ip \2Bitrate:\2 $bitrate \2Listeners:\2 $listeners \2Peak:\2 $peakListeners \2Song:\2 $song");
	}
	
	public function sc2Recv($read, $info) {
		if(is_array($read)) {
			$this->pIrc->msg($info[1], "\2Castinfo:\2 Error ($read[0]) $read[1]");
			return;
		}
		$ip = $info[0];
		$url = parse_url($ip);
		if (!isset($url['query'])) {
			$url['query'] = '?sid=1';
		}
		if (!isset($url['port'])) {
			$url['port'] = '8000';
		}
		$ip = 'http://' . $url['host'] . ':' . $url['port'] . '/listen.pls' . $url['query'];
		$moo = $url['host'] . ':' . $url['port'] . '/stats' . $url['query'];
		$target = $info[1];
		$info = simplexml_load_string($read);
		if (isset($info->STREAMSTATUS)) {
			if ($info->STREAMSTATUS == 1) {
				$state = "Up";
			} else {
				$state = "Down";
			}
		}
		$this->pIrc->msg($target, "\2ShoutCast2 (\2$info->SERVERTITLE\2) Status:\2 $state \2Connect:\2 $ip \2Listeners:\2 $info->CURRENTLISTENERS/$info->MAXLISTENERS \2Peak:\2 $info->PEAKLISTENERS \2Hits:\2 $info->STREAMHITS \2Genre:\2 $info->SERVERGENRE \2BitRate:\2 $info->BITRATE \2Now Playing:\2 $info->SONGTITLE \2Next Song:\2 $info->NEXTTITLE");
	}
	
	public function scRecv($read, $info) {
		if(is_array($read)) {
			$this->pIrc->msg($info[1], "\2Castinfo:\2 Error ($read[0]) $read[1]");
			return;
		}
		$ip = $info[0];
		$target = $info[1];
		$start = strpos($read, "<body>") + 6;
		$end = strpos($read, "</body>");
		$text = explode(',', substr($read, $start, $end - $start));
		if (array_key_exists(1, $text)) {
			if ($text[1] == 1) {
				$state = "Up";
			} else {
				$state = "Down";
			}
		}
		if(stripos($ip, 'http://') === FALSE) {
			$ip = "http://$ip";
		} else {
			$ip = "$ip";
		}
		if(stripos($ip, '.pls') === FALSE) {
			//take a guess i think listen.pls is default
			$ip = "$ip/listen.pls";
		}
		if (array_key_exists(6, $text)) {
			$this->pIrc->msg($target, "\2 ShoutCast Server Status:\2 $state \2Connect:\2 $ip \2Listeners:\2 $text[0]/$text[3] \2Peak:\2 $text[2] \2BitRate:\2 $text[5] \2Now Playing:\2 $text[6]");
		} else {
			$this->pIrc->msg($target, "Did not recognise response from $ip");
		}
	}
}
?>
