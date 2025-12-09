<?
	require __DIR__ . '/vendor/autoload.php';
	require __DIR__ . '/src/Vmaya/engine.php';

	define("LOG_FILE", LOGPATH.'webhook.log');
	define("LOG_ERROR_FILE", LOGPATH.'webhook_error.log');
	define("LOG_UNKNOWN_FILE", LOGPATH.'webhook_unknown.log');
	define('RESULT_PATH', BASEPATH.'downloads'.DS.'results'.DS);
	define('PROCESS_PATH', BASEPATH.'downloads'.DS.'progress'.DS);
	define("ISLOG", true);

	if (!file_exists(RESULT_PATH))
		mkdir(RESULT_PATH, 0755, true);
	if (!file_exists(PROCESS_PATH))
		mkdir(PROCESS_PATH, 0755, true);

	// webhook.php

	function Main($headers, $input) {
		GLOBAL $dbp;

		// Включаем логирование
		if (ISLOG)
			file_put_contents(LOG_FILE, 
			    date('Y-m-d H:i:s') . " - Webhook вызван\n", 
			    FILE_APPEND
			);

		// Логируем заголовки
		if (ISLOG)
			file_put_contents(LOG_FILE, 
			    "Headers: " . json_encode($headers, JSON_PRETTY_PRINT) . "\n", 
			    FILE_APPEND
			);

		// Логируем тело запроса
		if (ISLOG)
			file_put_contents(LOG_FILE, 
			    "Raw body: " . $input . "\n---\n", 
			    FILE_APPEND
			);

		// Проверяем, есть ли данные
		if (empty($input)) {
		    http_response_code(400);
		    file_put_contents(LOG_ERROR_FILE, 'ERROR: Empty request body'. "\n", FILE_APPEND);
		    exit;
		}

		// Парсим JSON
		$data = json_decode($input, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
		    http_response_code(400);
		    file_put_contents(LOG_ERROR_FILE, 'ERROR: Invalid JSON, '.json_last_error_msg(). "\n", FILE_APPEND);
		    exit;
		}

		// Проверяем подпись (если настроен секретный токен)
		if (isset($headers['X-UserApi-Signature'])) {
		    $signature = $headers['X-UserApi-Signature'];
		    $expected_signature = hash_hmac('sha256', $input, MJ_TOKEN);
		    
		    if (!hash_equals($expected_signature, $signature)) {
		        http_response_code(401);
		        file_put_contents(LOG_ERROR_FILE, 'ERROR: Invalid signature'. "\n", FILE_APPEND);
		        exit;
		    }
		}

		// Отвечаем, что все OK
		http_response_code(200);
		header('Content-Type: application/json');



		$dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);
		// Обрабатываем данные
		processWebhookData(new MJModel(), $data);
		$dbp->Close();

		echo json_encode(['status' => 'ok']);
	}

	// Функция обработки данных
	function processWebhookData($model, $data) {

	    $model->Update($data);

	    switch ($data['status']) {
	    	case 'done':
	    		handleResult($data);
	    		break;
	    	case 'progress':
	    		handleProgress($data);
	    		break;
	    }
	    
	    // Определяем тип события
	    $event_type = $data['type'] ?? 'unknown';	    

	    if (count(explode('.', $event_type)) > 1) {
		    switch ($event_type) {
		        case 'message.new':
		            handleNewMessage($data);
		            break;
		            
		        case 'message.status':
		            handleMessageStatus($data);
		            break;
		            
		        case 'connection.status':
		            handleConnectionStatus($data);
		            break;
		            
		        default: 
		        	handleUnknown($event_type, $data);
		            break;
		    }
		}
	}

	function handleResult($data) {

		$result = $data['result'];
		if (isset($result['url']) && $result['url']) {
			$info = pathinfo($result['filename']);
			downloadFile($result['url'], RESULT_PATH.$data['hash'].'.'.$info['extension']);
		}
	}

	function handleProgress($data) {

		$result = $data['result'];
		if (isset($result['url']) && $result['url']) {
			$info = pathinfo($result['filename']);
			downloadFile($result['url'], PROCESS_PATH.$data['hash'].'.'.$data['progress'].'.'.$info['extension']);
		}
	}

	function handleMessageStatus($data) {
		file_put_contents(LOGPATH.'webhook_message_status.log', json_encode($data)."\n---\n", FILE_APPEND);
	}

	function handleConnectionStatus($data) {
		file_put_contents(LOGPATH.'webhook_connection_status.log', json_encode($data)."\n---\n", FILE_APPEND);
	}

	function handleUnknown($event_type, $data) {
		file_put_contents(LOG_UNKNOWN_FILE, 
            "Unknown event type: {$event_type}\n". 
            json_encode($data, JSON_PRETTY_PRINT)."\n---\n",
            FILE_APPEND
        );
	}

	function handleNewMessage($data) {
	    $message = $data['message'] ?? [];
	    $service = $data['service'] ?? 'unknown';
	    $chat_id = $message['chat_id'] ?? null;
	    $text 	= $message['text'] ?? '';
	    $sender = $message['from'] ?? [];
	    
	    file_put_contents(LOGPATH.'webhook_messages.log', 
	        date('Y-m-d H:i:s') . " - New message from {$service}:\n" .
	        "Chat ID: {$chat_id}\n" .
	        "Sender: " . json_encode($sender) . "\n" .
	        "Text: {$text}\n" .
	        "Full message: " . json_encode($message, JSON_UNESCAPED_UNICODE) . "\n---\n",
	        FILE_APPEND
	    );
	    
	    // Автоответ
	    if (!empty($text) && stripos($text, 'привет') !== false) {
	        sendAutoReply($service, $chat_id, "И тебе привет! 👋");
	    }
	}

	function sendAutoReply($service, $chat_id, $text) {
	    $api_key = MJ_APIKEY;
	    $url = "https://api.userapi.ai/{$service}/send";
	    
	    $data = [
	        'chat' => $chat_id,
	        'text' => $text
	    ];
	    
	    $options = [
	        'http' => [
	            'header'  => [
	                "Authorization: Bearer {$api_key}",
	                "Content-Type: application/json"
	            ],
	            'method'  => 'POST',
	            'content' => json_encode($data),
	        ],
	    ];
	    
	    $context = stream_context_create($options);
	    $result = file_get_contents($url, false, $context);
	    
	    file_put_contents('replies.log', 
	        date('Y-m-d H:i:s') . " - Auto-reply sent:\n" .
	        "Service: {$service}\n" .
	        "To: {$chat_id}\n" .
	        "Text: {$text}\n" .
	        "Result: {$result}\n---\n",
	        FILE_APPEND
	    );
	}


	// Получаем сырые данные
	if (DEV) {
		Main('{
		    "Host": "vmaya.ru",
		    "X-Server-Addr": "87.236.16.76",
		    "X-Forwarded-Proto": "https",
		    "X-Real-IP": "209.38.192.184",
		    "Content-Length": "429",
		    "content-type": "application\/json",
		    "accept-encoding": "gzip",
		    "user-agent": "Go-http-client\/2.0"
		}', '{
    "account_hash": "4318ceab-7987-4771-837f-f4fec3b7938e",
    "hash": "abd18130-6e70-4cb2-b652-4804f474d01f",
    "parent_hash": "",
    "webhook_url": "https:\/\/vmaya.ru\/img2video\/mj_hook.php",
    "webhook_type": "progress",
    "callback_id": null,
    "prompt": "A majestic white wolf with blue eyes standing on a cliff under the aurora borealis",
    "type": "imagine",
    "progress": 0,
    "status": "waiting",
    "result": null,
    "status_reason": null,
    "created_at": "2025-12-08T16:19:14Z"
}');
	} else Main(getallheaders(), file_get_contents('php://input'));