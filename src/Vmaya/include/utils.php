<?

function is_date($str){
    return is_numeric(strtotime($str));
}

function is_value($str) {
    if ($str)
        $result = array_filter(['null', 'не определено', 'не указано', 'неопределено', 'неизвестно'], function($item) use ($str) {
            return mb_strtolower($item, 'UTF-8') === mb_strtolower($str, 'UTF-8');
        });
        return empty($result);
    return false;
}

function toUTF($text) {
    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
    }, $text);
}

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

?>