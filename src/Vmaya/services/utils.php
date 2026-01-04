<?

function YaTranslate($ru_text, $target="en", $baseUrl = 'https://translate.api.cloud.yandex.net/translate/v2/', $endpoint = 'translate') {

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

    return false;
}

function hasRussianWords(string $text): bool {
    return preg_match('/[а-яё]/iu', $text) === 1;
}

function checkRusAndTranslate($text) {
	if (!empty($text) && hasRussianWords($text))
		$text = YaTranslate($text);

	return $text;
}