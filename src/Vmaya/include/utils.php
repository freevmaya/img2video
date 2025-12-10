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

function downloadFile($url, $savePath)
{
    $ch = curl_init($url);
    
    // Настройки cURL
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,      // Возвращать результат
        CURLOPT_FOLLOWLOCATION => true,      // Следовать редиректам
        CURLOPT_SSL_VERIFYPEER => false,     // Для HTTPS (в продакшене должно быть true)
        CURLOPT_SSL_VERIFYHOST => false,     // Для HTTPS (в продакшене должно быть true)
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', // User-Agent
        CURLOPT_TIMEOUT => 300,              // Таймаут 5 минут
        CURLOPT_CONNECTTIMEOUT => 30,        // Таймаут подключения
    ]);
    
    $fileContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($httpCode === 200 && $fileContent !== false) {
        // Сохраняем файл
        if (file_put_contents($savePath, $fileContent) !== false) {
            return [
                'success' => true,
                'path' => $savePath,
                'size' => filesize($savePath)
            ];
        } else {
            $msg = "Failed to save file ({$url}->{$savePath})";
            trace_error($msg);
            return [
                'success' => false,
                'error' => $msg
            ];
        }
    } else {
        $msg = "Error download file ({$url}). HTTP: $httpCode, cURL: ".json_encode($error);
        trace_error($msg);
        return [
            'success' => false,
            'error' => $msg
        ];
    }
}

function HoursDiffDate($dateString, $referenceDate = 'now') {
    $timestamp1 = strtotime($dateString);
    $timestamp2 = strtotime($referenceDate);
    
    // Разница в секундах
    $diffSeconds = $timestamp2 - $timestamp1;
    
    // Преобразуем в часы
    $diffHours = $diffSeconds / 3600;
    
    return $diffHours;
}

?>