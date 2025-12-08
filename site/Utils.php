<?
define('EARTHRADIUS', 6378.137);

function Lang($strIndex) {
	GLOBAL $lang;
	if (isset($lang[$strIndex]))
		return $lang[$strIndex];
	return $strIndex;
}


function getGUID() {
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }
    else {
        mt_srand(strtotime('now'));
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12);// "}"
        return $uuid;
    }
}

function roundv($v, $n) {
    $p = pow(10, $n);
    return round($v * $p) / $p;
}

function CalcAngle($p1, $p2) {
    if ($p1 && $p2)
        return atan2($p2['lng'] - $p1['lng'], ($p2['lat'] - $p1['lat']) * 1.5)  / pi() * 180;
    else return 0;
}


function Distance($lat1, $lng1, $lat2, $lng2) {  // generally used geo measurement function

    $pi = pi();
    $dLat = $lat2 * $pi / 180 - $lat1 * $pi / 180;
    $dLon = $lng2 * $pi / 180 - $lng1 * $pi / 180;

    $a = sin($dLat/2) * sin($dLat/2) +
            cos($lat1 * $pi / 180) * cos($lat2 * $pi / 180) *
            sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $d = EARTHRADIUS * $c;

    return $d * 1000; // meters
}

function latLngToString($latLng) {
    if (isset($latLng['lat']))
        return roundv($latLng['lat'], 6).' '.roundv($latLng['lng'], 6);

    return json_encode($latLng);
}
?>