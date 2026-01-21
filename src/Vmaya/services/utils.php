<?

function YaTranslate($ru_text, $target="en", $baseUrl = 'https://translate.api.cloud.yandex.net/translate/v2/', $endpoint = 'translate') {

    if (!empty($ru_text) && is_string($ru_text)) {
    	$ch = curl_init($baseUrl . $endpoint);
                    
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
    								"folderId" => YA_FOLDER_ID,
    								"texts" => [$ru_text],
    								"targetLanguageCode" => $target
    							], JSON_FLAGS),
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'accept: application/json',
                "Authorization: Api-Key ".YA_APIKEY
            ]
        ]);

        $response   = json_decode(curl_exec($ch), true);
        $error      = curl_error($ch);

        if ($error)
        	trace_error($error);
        else {
    	    if (isset($response['translations']))
    	    	return $response['translations'][0]['text'];
    	    else trace_error($response);
    	}
    } else trace_error("Wrong type: $ru_text");

    return false;
}

function hasRussianWords(string $text): bool {
    return empty($text) ? false :preg_match('/[а-яё]/iu', $text) === 1;
}

function checkRusAndTranslate($text) {
	if (!empty($text) && hasRussianWords($text))
		$text = YaTranslate($text);

	return $text;
}

function getExt($filename) {
    return pathinfo($filename)['extension'];
}

function extractParenthesesContent($string) {
    // Проверяем наличие открывающей и закрывающей скобок
    if (preg_match('/^(.*?)\((.*?)\)(.*)$/', $string, $matches)) {
        return [$matches[1], $matches[2]];
    }
    
    return [false, false];
}

/**
 * Устанавливает значение в многоуровневом массиве по пути
 * 
 * @param array &$array Исходный массив (передается по ссылке)
 * @param string $path Путь в формате "field1/field2/field3"
 * @param mixed $value Значение для установки
 * @param string $delimiter Разделитель пути (по умолчанию "/")
 * @return array Возвращает измененный массив
 */
function setArrayValueByPath(array &$array, string $path, $value, string $delimiter = '/'): array
{
    $keys = explode($delimiter, $path);
    $current = &$array;
    
    foreach ($keys as $key) {
        if (!is_array($current)) {
            $current = [];
        }
        
        if (!array_key_exists($key, $current)) {
            $current[$key] = [];
        }
        
        $current = &$current[$key];
    }
    
    $current = $value;
    return $array;
}

function getGenderFromAPI($name) {
    $apis = [
        // Genderize.io API
        'https://api.genderize.io/?name=' . urlencode($name),
        
        // Agify.io (возраст + пол)
        'https://api.agify.io/?name=' . urlencode($name),
        
        // Nationalize.io (национальность + пол)
        'https://api.nationalize.io/?name=' . urlencode($name)
    ];
    
    foreach ($apis as $apiUrl) {
        $response = @file_get_contents($apiUrl);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['gender'])) {
                return $data['gender'];
            }
        }
    }
    
    return null;
}

/**
 * Определяет тип файла Telegram по его file_id
 * Основано на префиксах, которые использует Telegram
 * 
 * @param string $fileId file_id из Telegram
 * @return string Тип файла или 'unknown'
 */
function detectFileTypeByFileId(string $fileId): string
{
    // Приводим к строке и обрезаем пробелы
    $fileId = trim($fileId);
    
    if (empty($fileId)) {
        return 'unknown';
    }
    
    // Telegram использует определенные префиксы для разных типов файлов
    // Префиксы основаны на анализе реальных file_id
    
    $prefixPatterns = [
        // Видео файлы
        'video' => [
            '/^BAACAg[Q|I]/',           // Обычные видео
            '/^CgACAg[Q|I]/',           // Видео-сообщения (видео-кружочки)
            '/^DQACAg[Q|I]/',           // Видео с эффектами
        ],
        
        // Фотографии
        'photo' => [
            '/^AgACAg[Q|I]/',           // Обычные фото (AgACAg...)
            '/^CAACAg[Q|I]/',           // Стикеры, которые могут быть фото
        ],
        
        // Документы
        'document' => [
            '/^BQACAg[Q|I]/',           // Обычные документы (PDF, DOC, etc.)
            '/^AwACAg[Q|I]/',           // Документы из каналов
        ],
        
        // Аудио файлы
        'audio' => [
            '/^CQACAg[Q|I]/',           // Аудио файлы
        ],
        
        // Голосовые сообщения
        'voice' => [
            '/^AQACAg[Q|I]/',           // Голосовые сообщения
        ],
        
        // Видео-заметки (кружочки)
        'video_note' => [
            '/^CgACAg[Q|I]/',           // Видео-заметки
        ],
        
        // Анимации (GIF)
        'animation' => [
            '/^DgACAg[Q|I]/',           // Анимации/GIF
        ],
        
        // Стикеры
        'sticker' => [
            '/^CAACAg[Q|I]/',           // Стикеры
        ],
    ];
    
    // Проверяем каждый тип
    foreach ($prefixPatterns as $type => $patterns) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $fileId)) {
                return $type;
            }
        }
    }
    
    // Дополнительные проверки по длине и структуре
    return detectByLengthAndStructure($fileId);
}

function detectByLengthAndStructure(string $fileId): string
{
    $length = strlen($fileId);
    
    // Примерные диапазоны длин для разных типов файлов
    $lengthPatterns = [
        ['type' => 'photo', 'min' => 40, 'max' => 60],
        ['type' => 'video', 'min' => 40, 'max' => 70],
        ['type' => 'document', 'min' => 40, 'max' => 80],
        ['type' => 'audio', 'min' => 40, 'max' => 60],
        ['type' => 'voice', 'min' => 40, 'max' => 55],
        ['type' => 'video_note', 'min' => 40, 'max' => 60],
        ['type' => 'animation', 'min' => 40, 'max' => 65],
        ['type' => 'sticker', 'min' => 40, 'max' => 60],
    ];
    
    foreach ($lengthPatterns as $pattern) {
        if ($length >= $pattern['min'] && $length <= $pattern['max']) {
            return $pattern['type'];
        }
    }
    
    return 'unknown';
}