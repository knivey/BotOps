<?php

require_once('modules/Module.inc');

class weather extends Module {

    public $units = Array(
        'si'  => Array(
            'windSpeed' => 'm/s',
            'temp'      => '°C',
            'pressure'  => 'hPa',
            'vis'       => 'km'
        ),
        'us'  => Array(
            'windSpeed' => 'mph',
            'temp'      => '°F',
            'pressure'  => 'mbar',
            'vis'       => 'miles'
        ),
        'ca'  => Array(
            'windSpeed' => 'kph',
            'temp'      => '°C',
            'pressure'  => 'hPa',
            'vis'       => 'km'
        ),
        'uk2' => Array(
            'windSpeed' => 'mph',
            'temp'      => '°C',
            'pressure'  => 'hPa',
            'vis'       => 'miles'
        )
    );

    public function cmd_weather($nick, $chan, $arg2) {
        $host  = $this->pIrc->n2h($nick);
        $hand  = $this->gM('user')->byHost($host);
        $units = $this->getUnits($hand, $arg2);
        $query = $arg2;
        if ($query == '') {
            $query = $this->gM('user')->getzip($hand);
        }
        if ($query == '') {
            $this->pIrc->msg($chan, "\2Weather Error (Location):\2 You dont have a location set.");
            return;
        }

        list($err, $lat, $lon, $location) = $this->getLatLon($query);
        if ($err) {
            $this->pIrc->msg($chan, "\2Weather Error (Location):\2 $err");
            return;
        }

        list($err, $dkey) = $this->pGetConfig('darksky_key');
        if ($err) {
            return Array($err, 0, 0);
        }

        $ch  = curl_init("https://api.darksky.net/forecast/$dkey/$lat,$lon?exclude=minutely,hourly&units=$units");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res === FALSE) {
            $this->pIrc->msg($chan, "\2Weather Error (Darksky):\2 " . curl_error($ch));
            return;
        }
        $w = json_decode($res, true);
        if(!$w || @$w['error']) {
            $this->pIrc->msg($chan, "\2Weather Error (Darksky):\2 " . @!$w ? 'Unknown Data' : $w['error']);
            return;
        }
        $tz = $w['timezone'];

        list($err, $sunrise, $sunset, $timenow) = $this->getTimes($lat, $lon, $tz);
        if ($err) {
            $this->pIrc->msg($chan, "\2Weather Error (Timezone):\2 $err");
            return;
        }

        $cond      = @$w['currently']['summary'];
        if ($windSpeed = @$w['currently']['windSpeed']) {
            $windDir = $this->windArrow($w['currently']['windBearing']);
        } else {
            $windDir = '*';
        }
        $windSpeed .= $this->units[$units]['windSpeed'];
        if ($windGust  = @$w['currently']['windGust']) {
            $windSpeed .= "($windGust" . $this->units[$units]['windSpeed'] . ' Gusts)';
        }
        if ($feelslike = @$w['currently']['apparentTemperature']) {
            $feelslike .= $this->units[$units]['temp'];
        }
        $temp = @$w['currently']['temperature'] . $this->units[$units]['temp'];
        if ($feelslike != $temp && $feelslike) {
            $temp = "$temp (Feels Like $feelslike)";
        }
        $humd       = @$w['currently']['humidity'];
        ($cloudCover = @$w['currently']['cloudCover']) ? $cloudCover = $cloudCover * 100 . '%' : $cloudCover = '0%';

        $fc = '';

        $max = 0;
        foreach ($w['daily']['data'] as $f) {
            if (++$max == 4) {
                break;
            }
            $out = '';
            $dt = DateTime('@' . $f['time']);
            $dt->setTimeZone(new DateTimeZone($tz));
            $day = $dt->format('%D');
            $fcond = $f['summary'];
            $ftempH = $f['temperatureHigh'] . $this->units[$units]['temp'];
            $ftempL = $f['temperatureLow'] . $this->units[$units]['temp'];
            $out .= "\2$day:\2 $fcond $ftempH/$ftempL ";
            if (array_key_exist('precipType', $f)) {
                $out .= $f['precipProbability'] * 100 . "% chance " . $f['precipType'] . ' ';
            }
            if ($f['windSpeed'] != 0) {
                $out .= $this->windArrow($f['windBearing']) . ' ';
                $out .= $f['windSpeed'] . $this->units[$units]['windSpeed'] . ' ';
            }
            $fc .= $out;
        }

        $this->pIrc->msg($chan, "\2(\2$location\2)\2 $timenow $tz \21Currently:\2 $cond $cloudCover Cloud Cover $temp \2Humidity:\2 $humd \2Wind:\2 $windDir $windSpeed \2Sunrise:\2 $sunrise \2Sunset:\2 $sunset");
        $this->pIrc->msg($chan, "\2(\2Forecast\2)\2 $fc");
    }

    //returns error, lat, lon
    function getLatLon($query) {
        list($err, $lkey) = $this->pGetConfig('lkey');
        if ($err) {
            return Array($err, 0, 0);
        }

        $ch  = curl_init("https://locationiq.org/v1/search.php?q=" . urlencode(htmlentities($query)) . "&format=json&key=$lkey");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res === FALSE) {
            return Array(curl_error($ch), 0, 0);
        }

        $w = json_decode($res);

        //On error $w may be a class with ->error
        //Otherwise is always an array even with only 1 item
        if (!is_array($w)) {
            return Array('Location not found', 0, 0);
        }

        return Array(false, $w[0]->lat, $w[0]->lon, $w[0]->display_name);
    }

    // error, $sunrise, $sunset, $timenow
    function getTimes($lat, $lon, $tz) {
        try {
            $dt      = new DateTime(null, new DateTimeZone($tz));
            $timeNow = $dt->format('M d g:i:sa');
            $si      = date_sun_info($dt->format('U'), $lat, $lon);
            foreach ($si as &$sii) {
                $dtt = new DateTime(null, new DateTimeZone($tz));
                $dtt->setTimestamp($sii);
                $sii = $dtt->format('g:i:sa');
            }
        } catch (Exception $e) {
            return Array("DateTime Exception " . $e->getMessage(), '');
        }

        return Array(false, $si[sunrise], $si[sunset], $timeNow);
    }

    function getUnits($hand, &$arg2) {
        $units = $this->gM('SetReg')->getASet($hand, 'weather', 'units');
        $flagl = strpos($arg2, '-metric');

        foreach (['auto', 'metric', 'imperial', 'ca', 'uk2', 'us', 'si'] as $unit) {
            $flagl = strpos($arg2, '-' . $unit);
            if ($flagl !== false) {
                $arg2  = str_replace('-' . $unit, '', $arg2);
                $units = $unit;
            }
        }

        //Backwards compat with older version
        if ($units == 'imperial') {
            $units = 'us';
        }
        if ($units == 'metric') {
            $units = 'si';
        }
        trim($arg2);
        return $units;
    }

    function windArrow($bearing) {
        $idx = (int) ((($bearing % 360) - 22.5) / 45);
        return '↑↗→↘↓↙←↖'[$idx];
    }

}

?>
