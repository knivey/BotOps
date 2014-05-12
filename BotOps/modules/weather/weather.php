<?php

require_once('modules/Module.inc');
require_once('Http.inc');

class weather extends Module {
	public function cmd_weather($nick, $chan, $arg2) {
		$arg = explode(' ', $arg2);
		$host = $this->pIrc->n2h($nick);
		$hand = $this->gM('user')->byHost($host);
		$userpref = $this->gM('SetReg')->getASet($hand, 'weather', 'wservice');
		$units = $this->gM('SetReg')->getASet($hand, 'weather', 'units');
		$via = 'wu';
		if($userpref == 'wu') {
			$via = 'wu';
		}
		if($userpref == 'noaa') {
			$via = 'noaa';
		}
		if($userpref == 'accu') {
			$via = 'accu';
		}
		if ($arg2 == '') {
			$query = $this->gM('user')->getzip($hand);
		} else {
			$wul = strpos($arg2, '-wu');
			if($wul !== false) {
				$arg2 = str_replace('-wu', '', $arg2);
				$via = 'wu';
			}
			$noaal = strpos($arg2, '-noaa');
			if($noaal !== false) {
				$arg2 = str_replace('-noaa', '', $arg2);
				$via = 'noaa';
			}
			$noaal = strpos($arg2, '-accu');
			if($noaal !== false) {
				$arg2 = str_replace('-accu', '', $arg2);
				$via = 'accu';
			}
			$metricl = strpos($arg2, '-metric');
			if($metricl !== false) {
				$arg2 = str_replace('-metric', '', $arg2);
				$units = 'metric';
			}
			$metricl = strpos($arg2, '-imperial');
			if($metricl !== false) {
				$arg2 = str_replace('-imperial', '', $arg2);
				$units = 'imperial';
			}
			$query = $arg2;
		}
		if($query == '') {
			$query = $this->gM('user')->getzip($hand);
		}
		if ($query == '') {
			$this->pIrc->notice($nick, "Usage: weather <location> [-wu|-noaa|-accu]");
			$this->pIrc->notice($nick, "\2NOTE\2: You can set a default location /msg BotOps set zip <location>");
			$this->pIrc->notice($nick, "\2NOTE\2: You can set a default service /msg BotOps set wservice <wu|noaa|accu>");
			return $this->ERROR;
		}
		$varz = Array('chan' => $chan, 'query' => $query, 'units' => $units);
		if($via == 'wu') {
			$varz['weather'] = 'wundergroundRead';
		}
		if($via == 'noaa') {
			$varz['weather'] = 'noaaRead';
		}
		if($via == 'accu') {
			$varz['weather'] = 'accuRead';
		}
		if(isset($varz['weather'])) {
			$locHttp = new Http($this->pSockets, $this, 'wlocRead', $varz);
			$locHttp->getQuery("http://maps.googleapis.com/maps/api/geocode/xml?address=" . urlencode(htmlentities($query)) . "&sensor=false", $varz);
		} else {
			$this->pIrc->msg($chan, "Weather has encountered a strange error, please contact #bots");
		}
	}
	
	function wlocRead($body, $varz) {
		if(is_array($body)) {
			$this->pIrc->msg($varz['chan'], "\2Geolocation:\2 Error ($body[0]) $body[1]");
			return;
		}
		$info = Array();
		$w = simplexml_load_string($body);
		//var_dump($w);
		$info['name'] = $w->result->formatted_address;
		$long = (float)$w->result->geometry->location->lng;
		$lat = (float)$w->result->geometry->location->lat;
		$info['long'] = $long;
		$info['lat'] = $lat;
	
		$varz['info'] = $info;
	
		$elat = urlencode($lat);
		$elng = urlencode($long);
	
		list($error, $gn_user) = $this->pGetConfig('gn_user');
		if($error) {
			$this->pIrc->msg($varz['chan'], "\2TZDB:\2 $error");
			return;
		}
	
		$gn_url = "http://api.geonames.org/timezoneJSON?lat=$elat&lng=$elng&username=$gn_user";
		$lol = new Http($this->pSockets, $this, 'gnRead', $varz);
		$lol->getQuery($gn_url, $varz);
	}
	
	function tzdbFallback($varz) {
		list($error, $key) = $this->pGetConfig('tzdb_key');
		if($error) {
			$this->pIrc->msg($varz['chan'], "\2TZDB:\2 $error");
			return;
		}
		
		$elat = urlencode($varz['info']['lat']);
		$elng = urlencode($varz['info']['long']);
		
		$tzdb_url = "http://api.timezonedb.com/?key=$key&lat=$elat&lng=$elng";
		
		$lol = new Http($this->pSockets, $this, 'tzdbRead', $varz);
		$lol->getQuery($tzdb_url, $varz);
	}
	
	function gnRead($body, $varz) {
		if(is_array($body)) {
			$this->tzdbFallback($varz);
			return;
		}
		 
		$info = $varz['info'];
		 
		$tzi = json_decode($body, true);
	
		try { //stupid exceptions
			$dt = new DateTime (null, new DateTimeZone ($tzi ['timezoneId']));
			$info['timef'] = $dt->format('M d g:i:sa');
			$info['zone'] = $tzi['timezoneId'];
	
			$si = date_sun_info($dt->format ('U'), $info['lat'], $info['long']);
				
			foreach ($si as &$sii) {
				$dtt = new DateTime(null, new DateTimeZone($tzi['timezoneId']));
				$dtt->setTimestamp($sii);
				$sii = $dtt->format('g:i:sa');
			}
		} catch (Exception $e) {
			//TODO replace this when we have an error logger system
			$this->pIrc->msg('#botstaff', "TZDB Error: " . $e->getMessage());
			$this->tzdbFallback($varz);
			return;
		}
	
		$info['sun'] = "\2Sunrise:\2 $si[sunrise] \2Sunset:\2 $si[sunset] \2TimeNow:\2 $info[timef] $info[zone]";
		$varz['info'] = $info;
		 
		if($varz['weather'] == 'wundergroundRead') {
			list($error, $wkey) = $this->pGetConfig('wu_key');
			if($error) {
				$this->pIrc->msg($varz['chan'], "\2Weather:\2 $error");
				return;
			}
			 
			// http://www.wunderground.com/weather/api/d/docs?d=data/index
			// http://api.wunderground.com/api/182a32371e6a69bf/conditions/q/26.1420358,-81.7948103.xml
			$lol = new Http($this->pSockets, $this, 'wundergroundRead', $varz);
			$query = urlencode($varz['info']['lat'].','.$varz['info']['long']);
			$lol->getQuery("http://api.wunderground.com/api/$wkey/forecast/conditions/q/$query.xml", $varz);
			return;
		}
		if($varz['weather'] == 'noaaRead') {
			$lol = new Http($this->pSockets, $this, 'noaaRead', $varz);
			$lat = $varz['info']['lat'];
			$long = $varz['info']['long'];
			$lol->getQuery("http://forecast.weather.gov/MapClick.php?lat=$lat&lon=$long&unit=0&lg=english&FcstType=dwml", $varz);
		}
		if($varz['weather'] == 'accuRead') {
			$lol = new Http($this->pSockets, $this, 'accuRead', $varz);
			$lat = $varz['info']['lat'];
			$long = $varz['info']['long'];
			$lol->getQuery("http://thale.accu-weather.com/widget/thale/weather-data.asp?slat=$lat&slon=$long", $varz);
		}
	}
	
	function tzdbRead($body, $varz) {
		if(is_array($body)) {
			$this->pIrc->msg($varz['chan'], "\2TZDB:\2 Error ($body[0]) $body[1]");
			return;
		}
	
		$info = $varz['info'];
	
		$tzi = simplexml_load_string($body);
		$info['time'] = (int)$tzi->timestamp;
		$info['timef'] = gmdate('M d g:i:sa', (int)$tzi->timestamp);
		$info['zone'] = $tzi->abbreviation;
		$info['zonename'] = $tzi->zonename;
		$info['zoneOff'] = $zo = (int)$tzi->gmtOffset;
	
		$ouroff = (int)date_offset_get(new DateTime);
		$diff = $zo - $ouroff;
	
		$si = date_sun_info($info['time'], $info['lat'], $info['long']);
	
		foreach ($si as &$sii) {
			$sii = gmdate('g:i:sa', $sii + $diff + $ouroff);
		}
		$info['sun'] = "\2Sunrise:\2 $si[sunrise] \2Sunset:\2 $si[sunset] \2TimeNow:\2 $info[timef] $info[zone]";
		$varz['info'] = $info;
	
		if($varz['weather'] == 'wundergroundRead') {
			list($error, $wkey) = $this->pGetConfig('wu_key');
			if($error) {
				$this->pIrc->msg($varz['chan'], "\2Weather:\2 $error");
				return;
			}
			 
			// http://www.wunderground.com/weather/api/d/docs?d=data/index
			// http://api.wunderground.com/api/182a32371e6a69bf/conditions/q/26.1420358,-81.7948103.xml
			$lol = new Http($this->pSockets, $this, 'wundergroundRead', $varz);
			$query = urlencode($varz['info']['lat'].','.$varz['info']['long']);
			$lol->getQuery("http://api.wunderground.com/api/$wkey/forecast/conditions/q/$query.xml", $varz);
			return;
		}
		if($varz['weather'] == 'noaaRead') {
			$lol = new Http($this->pSockets, $this, 'noaaRead', $varz);
			$lat = $varz['info']['lat'];
			$long = $varz['info']['long'];
			$lol->getQuery("http://forecast.weather.gov/MapClick.php?lat=$lat&lon=$long&unit=0&lg=english&FcstType=dwml", $varz);
		}
		if($varz['weather'] == 'accuRead') {
			$lol = new Http($this->pSockets, $this, 'accuRead', $varz);
			$lat = $varz['info']['lat'];
			$long = $varz['info']['long'];
			$lol->getQuery("http://thale.accu-weather.com/widget/thale/weather-data.asp?slat=$lat&slon=$long", $varz);
		}
	}
	
	public function wundergroundRead($body, $varz) {
		if(is_array($body)) {
			$this->pIrc->msg($varz['chan'], "\2Wunderground:\2 Error ($body[0]) $body[1]");
			return;
		}
		$chan = $varz['chan'];
		$xml = simplexml_load_string($body);
		if($xml->error->description != null) {
			$this->pIrc->msg($chan, $xml->error->description);
			return;
		}
		$cur = $xml->current_observation;
		$loc = $cur->display_location->full;
		$cond = $cur->weather;
		if($varz['units'] == 'metric') {
			$temp = $cur->temp_c . '°C';
		} else {
			$temp = $cur->temp_f . '°F';
		}
		$humd = $cur->relative_humidity;
		if($varz['units'] == 'metric') {
			$wind = $cur->wind_dir . " @ " . $cur->wind_kph .'KPH (' . $cur->wind_gust_kph . 'KPH Gusts)';
		} else {
			$wind = $cur->wind_dir . " @ " . $cur->wind_mph .'MPH (' . $cur->wind_gust_mph . 'MPH Gusts)';
		}
	
		$fcds = $xml->forecast->simpleforecast->forecastdays->forecastday;
		$fc = '';
		//C vs F will become user option
		foreach($fcds as $day) {
			$fc .= "\2[" . $day->date->weekday_short . "]:\2 ";
			if($varz['units'] == 'metric') {
				$fc .= $day->high->celsius . '°C/';
				$fc .= $day->low->celsius . '°C ';
			} else {
				$fc .= $day->high->fahrenheit . '°F/';
				$fc .= $day->low->fahrenheit . '°F ';
			}
			$fc .= $day->conditions . ' ';
		}
	
		$this->pIrc->msg($chan, "\2(\2$loc\2) Currently:\2 $cond".
				" $temp \2Humidity:\2 $humd \2Wind:\2 $wind "
				. $varz['info']['sun']);
		$this->pIrc->msg($chan, "\2(\2Forecast\2)\2 $fc");
		if(array_key_exists('gasinfo', $varz['info']) && $varz['info']['gasinfo'] != '') {
			$this->pIrc->msg($chan, $varz['info']['gasinfo']);
		}
	}
	
	public function noaaRead($body, $varz) {
		if(is_array($body) || strpos($body, '<!DOCTYPE html') !== false || strpos($body, '<script ') !== false) {
			$this->pIrc->msg($varz['chan'], "\2(\2" .$varz['info']['name'] ."\2)\2 " . $varz['info']['sun']);
			if(!is_array($body)) {
				$this->pIrc->msg($varz['chan'], "\2Weather:\2 Error weather.gov has no information for those coordinates");
			} else {
				$this->pIrc->msg($varz['chan'], "\2Weather:\2 Error ($body[0]) $body[1]");
			}
			$this->pIrc->msg($varz['chan'], $varz['info']['gasinfo']);
			return;
		}
		$chan = $varz['chan'];
		$xml = simplexml_load_string($body);
		foreach($xml->data as $d) {
			if($d['type'] == 'forecast') {
				$fc = $d;
			}
			if($d['type'] == 'current observations') {
				$cur = $d;
			}
		}
		$loc = $fc->location->description;
		if($loc == '') {
			$loc = $fc->location->{'area-description'};
		}
		//var_dump($fc->location);
		$cur = $cur->parameters;
		$cond = $cur->weather->{'weather-conditions'}[0]['weather-summary'];
		foreach ($cur->temperature as $t) {
			if($t['type'] == 'apparent') {
				if($varz['units'] == 'metric') {
					// (°F  -  32)  x  5/9 = °C
					$temp = round(((float)$t->value - 32) * 5/9, 2) . '°C';
				} else {
					$temp = $t->value . '°F';
				}
			}
		}
		$humd = $cur->humidity->value .'%';
		$wind = $cur->direction->value . '° @ ';
		foreach ($cur->{'wind-speed'} as $cws) {
			//1 knot (kt) = 1.15077945 miles per hour (mph)
			//1 knot (kt) = 1.85200 kilometer per hour (kph)
	
			if($cws['type'] == 'sustained') {
				if($varz['units'] == 'metric') {
					$wind .= round((float)$cws->value * 1.852, 2) . 'KPH';
				} else {
					$wind .= round((float)$cws->value * 1.15077945, 2) . 'MPH';
				}
			}
		}
		/*
		 * the forecast info uses timekeys so lets parse those... uhg
		* i'll save them in $timel['timekey']
		* we dont really care about the time just the name
		*/
		$timel = Array();
		foreach($fc->{'time-layout'} as $tlay) {
			$tkey = $tlay->{'layout-key'};
			foreach($tlay->{'start-valid-time'} as $svt) {
				$timel[(string)$tkey][] = (string)$svt['period-name'];
			}
		}
	
		/*
		 * now we parse the info into each period
		* i'll start with <weather> because it
		* seems to use the long time layout
		* that way we popular the array in full order
		*/
		$periods = Array();
		$wtl = (string)$fc->parameters->weather['time-layout'];
		$cnt = 0;
		foreach($fc->parameters->weather->{'weather-conditions'} as $w) {
			$periods[$timel[$wtl][$cnt]]['cond'] = $w['weather-summary'];
			$cnt++;
		}
		$ttl = (string)$fc->parameters->temperature[0]['time-layout'];
		$cnt = 0;
		foreach($fc->parameters->temperature[0]->value as $t) {
			$periods[$timel[$ttl][$cnt]][(string)$fc->parameters->temperature[0]['type']] = $t;
			$cnt++;
		}
		$ttl = (string)$fc->parameters->temperature[1]['time-layout'];
		$cnt = 0;
		foreach($fc->parameters->temperature[1]->value as $t) {
			$periods[$timel[$ttl][$cnt]][(string)$fc->parameters->temperature[1]['type']] = $t;
			$cnt++;
		}
	
		$forecast = '';
		$cnt = 0;
		foreach($periods as $key => $val) {
			if($cnt > 4) {
				break;
			}
			if(isset($val['minimum'])) {
				$t = (string)$val['minimum'];
			} else {
				$t = (string)$val['maximum'];
			}
			if($varz['units'] == 'metric') {
				$forecast .= "\2[$key]:\2 " . round(((float)$t - 32) * 5/9, 2) . '°C ' . $val['cond'] . ' ';
			} else {
				$forecast .= "\2[$key]:\2 " . $t . '°F ' . $val['cond'] . ' ';
			}
			$cnt++;
		}
	
		$this->pIrc->msg($chan, "\2(\2$loc\2) Currently:\2 $cond".
				" $temp \2Humidity:\2 $humd \2Wind:\2 $wind "
				. $varz['info']['sun']);
		$this->pIrc->msg($chan, "\2(\2Forecast\2)\2 $forecast");
		if(array_key_exists('gasinfo', $varz['info']) && $varz['info']['gasinfo'] != '') {
			$this->pIrc->msg($chan, $varz['info']['gasinfo']);
		}
	}
	
	public function accuRead($body, $varz) {
		if (is_array($body)) {
			$this->pIrc->msg($varz['chan'], "\2AccuWeather:\2 Error ($body[0]) $body[1]");
			return;
		}
		$chan = $varz['chan'];
		$xml = simplexml_load_string($body);
		$loc = $xml->local->city . ', ' . $xml->local->state;
		$cc = $xml->currentconditions;
		$cond = $cc->weathertext;
		if ($varz['units'] == 'metric') {
			$temp = round(((float)$cc->temperature - 32) * 5/9, 2) . '°C';
			$humd = $cc->humidity;
			//Mph X 1.609344 = Kph
			$wind = $cc->winddirection . ' @ ' . round((float)$cc->windspeed * 1.609344,2) . 'KPH';
			$wind .= ' (' . round((float)$cc->windgusts * 1.609344,2) . 'KPH Gusts)';
		} else {
			$temp = $cc->temperature . '°F';
			$humd = $cc->humidity;
			$wind = $cc->winddirection . ' @ ' . $cc->windspeed . 'MPH';
			$wind .= ' (' . $cc->windgusts . 'MPH Gusts)';
		}
		$count = 1;
		$forecast = '';
		foreach($xml->forecast->day as $day) {
			if($count > 2) {
				break;
			}
			if ($varz['units'] == 'metric') {
				$forecast .= "\2[" . $day->daycode . "]:\2 " .
						round(((float)$day->dayttime->hightemperature - 32) * 5/9, 2) . '°C ' .
						$day->daytime->txtshort . ' ';
				$forecast .= "\2[" . $day->daycode . " Night]:\2 " .
						round(((float)$day->nighttime->lowtemperature - 32) * 5/9, 2) . '°C ' .
						$day->nighttime->txtshort . ' ';
			} else {
				$forecast .= "\2[" . $day->daycode . "]:\2 " .
						$day->daytime->hightemperature . '°F ' .
						$day->daytime->txtshort . ' ';
				$forecast .= "\2[" . $day->daycode . " Night]:\2 " .
						$day->nighttime->lowtemperature . '°F ' .
						$day->nighttime->txtshort . ' ';
			}
			$count++;
		}
	
		$this->pIrc->msg($chan, "\2(\2$loc\2) Currently:\2 $cond".
				" $temp \2Humidity:\2 $humd \2Wind:\2 $wind "
				. $varz['info']['sun']);
		$this->pIrc->msg($chan, "\2(\2Forecast\2)\2 $forecast");
		if(array_key_exists('gasinfo', $varz['info']) && $varz['info']['gasinfo'] != '') {
			$this->pIrc->msg($chan, $varz['info']['gasinfo']);
		}
	}
}
?>