<?
class MLStudioClient extends BaseMLStudioClient {

	protected $modelName;

	function __construct($url, $modelName, $systemPrompt) {
		parent::__construct($url, $systemPrompt);
        $this->modelName = $modelName;
        $status = file_get_contents($this->url.'/models');
        if (!$status) 
			echo "Статус ML Studio: Ошибка\n";
    }

    public function Send() {

    	$inputData = [
		    'model' => $this->modelName,
		    'messages' => $this->getHistory(),
		    'temperature' => 0.7,
		    'max_tokens' => 500
		];

		//print_r($inputData);

		$ch = curl_init($this->url);
		curl_setopt_array($ch, [
		    CURLOPT_POST => true,
		    CURLOPT_RETURNTRANSFER => true,
		    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
		    CURLOPT_POSTFIELDS => json_encode($inputData),
		    CURLOPT_TIMEOUT => 60,          // Общий лимит
		    CURLOPT_CONNECTTIMEOUT => 5,    // Лимит подключения
		    CURLOPT_FORBID_REUSE => true,   // Закрывать соединение после запроса
		    CURLOPT_FRESH_CONNECT => true
		]);

		$result = json_decode(curl_exec($ch), true);
		$error = curl_error($ch);
		
		curl_close($ch); // Явное закрытие соединения
		
		if ($error) {
		    die("cURL Error: " . $error);
		}

		if (isset($result['choices'][0]['message'])) {
		    $aiReply = $result['choices'][0]['message']['content'];
		    $this->addMessage('assistant', $aiReply);
		} else {
		    echo "Ошибка: " . print_r($result, true);
		}

		return $result;
	}
}
?>