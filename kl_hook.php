<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Vmaya/engine.php';

define("LOG_FILE", LOGPATH.'kl_webhook.log');
define("LOG_ERROR_FILE", LOGPATH.'kl_webhook_error.log');
define("LOG_UNKNOWN_FILE", LOGPATH.'kl_webhook_unknown.log');
define("ISLOG", true);

if (!file_exists(RESULT_PATH))
    mkdir(RESULT_PATH, 0755, true);
if (!file_exists(PROCESS_PATH))
    mkdir(PROCESS_PATH, 0755, true);

function Main($headers, $input) {
    GLOBAL $dbp;

    // Включаем логирование
    if (ISLOG)
        file_put_contents(LOG_FILE, 
            date('Y-m-d H:i:s') . " - Kling Webhook вызван\n", 
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
        echo "EMPTY";
        exit;
    }

    // Парсим JSON
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        file_put_contents(LOG_ERROR_FILE, 'ERROR: Invalid JSON, '.json_last_error_msg(). "\n", FILE_APPEND);
        echo "EMPTY";
        exit;
    }

    // Проверяем подпись (если настроен секретный токен)
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        // Здесь можно добавить проверку JWT токена, если требуется
        // $expected_token = 'Bearer ' . $expected_token;
        // if (!hash_equals($expected_token, $authHeader)) {
        //     http_response_code(401);
        //     file_put_contents(LOG_ERROR_FILE, 'ERROR: Invalid authorization token'. "\n", FILE_APPEND);
        //     echo "EMPTY";
        //     exit;
        // }
    }

    // Отвечаем, что все OK
    http_response_code(200);
    header('Content-Type: application/json');

    $dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);
    
    // Обрабатываем данные
    processKlingWebhookData($data);
    
    $dbp->Close();

    echo json_encode(['status' => 'ok']);
}

// Функция обработки данных Kling
function processKlingWebhookData($data) {
    
    // Определяем тип события по структуре данных Kling
    if (isset($data['task_id'])) {
        handleKlingTaskUpdate($data);
    } else if (isset($data['event_type'])) {
        handleKlingEvent($data);
    } else {
        handleUnknownKlingData($data);
    }
}

function handleKlingTaskUpdate($data) {
    file_put_contents(LOG_FILE, 
        date('Y-m-d H:i:s') . " - Kling Task Update:\n" . 
        json_encode($data, JSON_PRETTY_PRINT)."\n---\n",
        FILE_APPEND
    );
    
    // Сохраняем в базу данных
    saveKlingTaskToDB($data);
    
    // Обрабатываем статус задачи
    $status = $data['status'] ?? 'unknown';
    
    switch ($status) {
        case 'success':
        case 'completed':
            handleKlingSuccess($data);
            break;
            
        case 'processing':
        case 'in_progress':
            handleKlingProgress($data);
            break;
            
        case 'failed':
        case 'error':
            handleKlingError($data);
            break;
            
        default:
            handleUnknownKlingStatus($data);
            break;
    }
}

function saveKlingTaskToDB($data) {
    GLOBAL $dbp;
    
    // Определяем структуру таблицы для Kling задач
    // Создайте таблицу если её нет:
    // CREATE TABLE IF NOT EXISTS kling_tasks (
    //     id INT AUTO_INCREMENT PRIMARY KEY,
    //     task_id VARCHAR(255) UNIQUE,
    //     user_id INT,
    //     chat_id BIGINT,
    //     prompt TEXT,
    //     status VARCHAR(50),
    //     result_url TEXT,
    //     progress INT DEFAULT 0,
    //     error_message TEXT,
    //     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    //     updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    // );
    
    $task_id = $dbp->safeVal($data['task_id'] ?? '');
    $status = $dbp->safeVal($data['status'] ?? '');
    $result_url = $dbp->safeVal($data['video_url'] ?? $data['image_url'] ?? '');
    $progress = isset($data['progress']) ? intval($data['progress']) : 0;
    $error_message = $dbp->safeVal($data['error_message'] ?? '');
    
    // Проверяем, существует ли уже запись
    $existing = $dbp->line("SELECT id FROM kling_tasks WHERE task_id = '{$task_id}'");
    
    if ($existing) {
        // Обновляем существующую запись
        $query = "UPDATE kling_tasks SET 
                  status = '{$status}', 
                  result_url = '{$result_url}', 
                  progress = {$progress}, 
                  error_message = '{$error_message}',
                  updated_at = NOW()
                  WHERE task_id = '{$task_id}'";
    } else {
        // Вставляем новую запись
        // Предполагаем, что user_id и chat_id передаются в callback_data или сохраняются отдельно
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        $chat_id = isset($data['chat_id']) ? intval($data['chat_id']) : 0;
        $prompt = $dbp->safeVal($data['prompt'] ?? '');
        
        $query = "INSERT INTO kling_tasks 
                  (task_id, user_id, chat_id, prompt, status, result_url, progress, error_message, created_at, updated_at) 
                  VALUES ('{$task_id}', {$user_id}, {$chat_id}, '{$prompt}', '{$status}', '{$result_url}', {$progress}, '{$error_message}', NOW(), NOW())";
    }
    
    $dbp->query($query);
    
    // Также сохраняем в общую таблицу API коммуникаций, если она существует
    if ($dbp->isTableExists('api_comm')) {
        $hash = $task_id;
        $webhook_type = 'result';
        $type = 'kling_video'; // или 'kling_image'
        $result_data = json_encode($data);
        
        $api_query = "INSERT INTO api_comm 
                      (hash, webhook_type, prompt, type, status, result, created_at, processed) 
                      VALUES ('{$hash}', '{$webhook_type}', '{$prompt}', '{$type}', '{$status}', '{$result_data}', NOW(), 0)
                      ON DUPLICATE KEY UPDATE 
                      status = '{$status}', 
                      result = '{$result_data}', 
                      processed = 0";
        
        $dbp->query($api_query);
    }
}

function handleKlingSuccess($data) {
    file_put_contents(LOG_FILE, 
        date('Y-m-d H:i:s') . " - Kling Task Success:\n" . 
        json_encode($data, JSON_PRETTY_PRINT)."\n---\n",
        FILE_APPEND
    );
    
    // Скачиваем результат, если есть URL
    if (isset($data['video_url']) || isset($data['image_url'])) {
        $url = $data['video_url'] ?? $data['image_url'];
        $task_id = $data['task_id'] ?? 'unknown';
        
        $info = pathinfo($url);
        $extension = $info['extension'] ?? 'mp4';
        $filename = $task_id . '.' . $extension;
        
        downloadFile($url, RESULT_PATH . $filename);
        
        // Уведомляем пользователя через Telegram бота
        notifyUserAboutKlingResult($data, RESULT_PATH . $filename);
    }
}

function handleKlingProgress($data) {
    file_put_contents(LOG_FILE, 
        date('Y-m-d H:i:s') . " - Kling Task Progress:\n" . 
        json_encode($data, JSON_PRETTY_PRINT)."\n---\n",
        FILE_APPEND
    );
    
    // Можно отправить уведомление о прогрессе пользователю
    if (isset($data['progress']) && isset($data['task_id'])) {
        $progress = intval($data['progress']);
        $task_id = $data['task_id'];
        
        // Сохраняем промежуточный файл, если есть preview
        if (isset($data['preview_url'])) {
            $filename = $task_id . '.progress.' . $progress . '.jpg';
            downloadFile($data['preview_url'], PROCESS_PATH . $filename);
        }
    }
}

function handleKlingError($data) {
    file_put_contents(LOG_ERROR_FILE, 
        date('Y-m-d H:i:s') . " - Kling Task Error:\n" . 
        json_encode($data, JSON_PRETTY_PRINT)."\n---\n",
        FILE_APPEND
    );
    
    // Уведомляем пользователя об ошибке
    if (isset($data['task_id']) && isset($data['error_message'])) {
        notifyUserAboutKlingError($data);
    }
}

function handleKlingEvent($data) {
    file_put_contents(LOG_FILE, 
        date('Y-m-d H:i:s') . " - Kling Event:\n" . 
        json_encode($data, JSON_PRETTY_PRINT)."\n---\n",
        FILE_APPEND
    );
    
    // Обработка других событий Kling API
}

function handleUnknownKlingData($data) {
    file_put_contents(LOG_UNKNOWN_FILE, 
        date('Y-m-d H:i:s') . " - Unknown Kling Data Format:\n" . 
        json_encode($data, JSON_PRETTY_PRINT)."\n---\n",
        FILE_APPEND
    );
}

function handleUnknownKlingStatus($data) {
    file_put_contents(LOG_UNKNOWN_FILE, 
        date('Y-m-d H:i:s') . " - Unknown Kling Status:\n" . 
        json_encode($data, JSON_PRETTY_PRINT)."\n---\n",
        FILE_APPEND
    );
}

function notifyUserAboutKlingResult($data, $file_path) {
    // Здесь должна быть логика уведомления пользователя через Telegram бота
    // Используйте существующую инфраструктуру бота из проекта
    
    // Примерная структура:
    // 1. Получить user_id и chat_id из базы данных по task_id
    // 2. Отправить сообщение через Telegram API
    // 3. Прикрепить видео/изображение
    
    file_put_contents(LOG_FILE, 
        date('Y-m-d H:i:s') . " - Should notify user about Kling result\n" . 
        "File: " . $file_path . "\n" .
        "Data: " . json_encode($data, JSON_PRETTY_PRINT)."\n---\n",
        FILE_APPEND
    );
}

function notifyUserAboutKlingError($data) {
    // Логика уведомления пользователя об ошибке
    
    file_put_contents(LOG_FILE, 
        date('Y-m-d H:i:s') . " - Should notify user about Kling error\n" . 
        "Error: " . ($data['error_message'] ?? 'Unknown error') . "\n" .
        "Task ID: " . ($data['task_id'] ?? 'Unknown') . "\n---\n",
        FILE_APPEND
    );
}

// Получаем сырые данные
if (DEV) {
    // Тестовые данные для разработки
    Main([
        'Host' => 'vmaya.ru',
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer test_token'
    ], json_encode([
        'task_id' => 'test_task_123',
        'status' => 'completed',
        'video_url' => 'https://example.com/video.mp4',
        'progress' => 100,
        'prompt' => 'Test prompt'
    ]));
} else {
    Main(getallheaders(), file_get_contents('php://input'));
}