<?php
require_once __DIR__ . '/../CmdReg/CmdRequest.php';

require_once('modules/Module.inc');

class weather extends Module {

    public $units = Array(
        'si'  => Array(
            'windSpeed' => 'm/s',
            'temp'      => "C",
            'pressure'  => 'hPa',
            'vis'       => 'km'
        ),
        'us'  => Array(
            'windSpeed' => 'mph',
            'temp'      => "F",
            'pressure'  => 'mbar',
            'vis'       => 'miles'
        ),
        'ca'  => Array(
            'windSpeed' => 'kph',
            'temp'      => "C",
            'pressure'  => 'hPa',
            'vis'       => 'km'
        ),
        'uk2' => Array(
            'windSpeed' => 'mph',
            'temp'      => "C",
            'pressure'  => 'hPa',
            'vis'       => 'miles'
        )
    );

    public function cmd_weather(CmdRequest $r) {
        $hand  = $r->account;
        $units = $this->getUnits($hand, $arg2);
        $query = $r->args[0];
        if ($query == '') {
            $query = $this->gM('user')->getzip($hand);
        }
        if ($query == '') {
            throw new CmdException("You dont have a location set. /msg \$bot set zip location");
        }

        list($err, $lat, $lon, $location) = $this->getLatLon($query);
        if ($err) {
            throw new CmdException("(Location): $err");
        }

        list($err, $dkey) = $this->pGetConfig('darksky_key');
        if ($err) {
            throw new CmdException("(Darksky): Config missing key");
        }

        $ch  = curl_init("https://api.darksky.net/forecast/$dkey/$lat,$lon?exclude=minutely,hourly&units=$units");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res === FALSE) {
            throw new CmdException("(Darksky): " . curl_error($ch));
        }
        $w = json_decode($res, true);
        if (!$w || @$w['error']) {
            throw new CmdException("(Darksky): " . @!$w ? 'Unknown Data' : $w['error']);
        }
        $tz = $w['timezone'];

        list($err, $sunrise, $sunset, $timenow) = $this->getTimes($lat, $lon, $tz);
        if ($err) {
            throw new CmdException("(Timezone): $err");
        }
        $c = $w['currently'];
        
        $units = $w['flags']['units'];

        $cond      = @$c['summary'];
        if ($windSpeed = @$c['windSpeed']) {
            $windDir = $this->windDir($c['windBearing']);
        } else {
            $windDir = '*';
        }
        $windSpeed .= $this->units[$units]['windSpeed'];
        if ($windGust  = @$c['windGust']) {
            $windSpeed .= " ($windGust" . $this->units[$units]['windSpeed'] . ' Gusts)';
        }
        if ($feelslike = (string) @$c['apparentTemperature']) {
            $feelslike .= $this->units[$units]['temp'];
        }
        $temp = @$c['temperature'] . $this->units[$units]['temp'];
        if ($feelslike != $temp && $feelslike) {
            $temp = "$temp (Feels Like $feelslike)";
        }
        ($humd       = @$c['humidity']) ? $humd       = $humd * 100 . '%' : $humd       = '0%';
        ($cloudCover = @$c['cloudCover']) ? $cloudCover = $cloudCover * 100 . '%' : $cloudCover = '0%';

        $fc = '';

        $max = 0;
        foreach ($w['daily']['data'] as $f) {
            if (++$max == 4) {
                break;
            }
            $out    = '';
            $dt     = new DateTime('@' . $f['time']);
            $dt->setTimeZone(new DateTimeZone($tz));
            $day    = $dt->format('D');
            $fcond  = $f['summary'];
            $ftempH = $f['temperatureHigh'] . $this->units[$units]['temp'];
            $ftempL = $f['temperatureLow'] . $this->units[$units]['temp'];
            $out    .= "\2[$day]\2 $fcond $ftempH/$ftempL ";
            if (array_key_exists('precipType', $f)) {
                $out .= 'With a ' . $f['precipProbability'] * 100 . "% chance of " . $f['precipType'] . ' ';
            }
            if ($f['windSpeed'] != 0) {
                $out .= 'Wind: ' . $this->windDir($f['windBearing']) . ' @ ';
                $out .= $f['windSpeed'] . $this->units[$units]['windSpeed'] . ' ';
            }
            $fc .= $out;
        }

        $r->reply("\2(\2$location\2)\2 $timenow \2Currently:\2 $cond, $cloudCover Cloud Cover $temp \2Humidity:\2 $humd \2Wind:\2 $windDir @ $windSpeed \2Sunrise:\2 $sunrise \2Sunset:\2 $sunset");
        $r->reply("\2(\2Forecast\2)\2 $fc");
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

        return Array(false, $si['sunrise'], $si['sunset'], $timeNow);
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

    function windDir($bearing) {
        //$arrows = '↑↗→↘↓↙←↖';
        //For some reason I'm having trouble with unicode charaters -_-
        $arrows = Array('N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW');
        $idx    = (int) ((($bearing % 360) - 22.5) / 45);
        return $arrows[$idx];
    }

}

?>
