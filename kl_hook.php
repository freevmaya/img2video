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
}

function saveKlingTaskToDB($data) {

    $result_url = '';
    if ($task_result = @$data['task_result']) {

        if (isset($task_result['videos'][0]))
            $result_url = @$task_result['videos'][0]['url'];
        else if (isset($task_result['images'][0]))
            $result_url = @$task_result['images'][0]['url'];
        else if (isset($task_result['audios'][0]))
            $result_url = @$task_result['audios'][0]['url'];
    }
        
    (new KlingModel())->Update([
        'task_id' => $data['task_id'] ?? '',
        'status' => $data['task_status'] ?? '',
        'result_url'=>$result_url,
        'error_message'=>$data['error_message'] ?? ''
    ]);
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

$headers = getallheaders();

// Получаем сырые данные
if (DEV || empty($headers)) {
    // Тестовые данные для разработки
    Main('{
    "Accept-Encoding": "gzip,deflate",
    "User-Agent": "Apache-HttpClient\/4.5.13 (Java\/11.0.14-internal)",
    "Connection": "Keep-Alive",
    "Host": "vmaya.ru",
    "Content-Length": "1207",
    "Content-Type": "application\/json; charset=utf-8"
}','{"task_id":"828338674605228111","task_status":"succeed","task_info":{},"task_result":{"images":[],"videos":[{"id":"828338674655576157","url":"https://v15-kling-fdl.klingai.com/bs2/upload-ylab-stunt-sgp/muse/828300247523786832/VIDEO/20251212/99e04ae9253aac4e9920610dd9f3ef7b-0c0e4ce5-6f55-435e-9734-a8218e8b48de.mp4?cacheKey=ChtzZWN1cml0eS5rbGluZy5tZXRhX2VuY3J5cHQSsAEwd4TlaeLgxoTSHX1RjEOf6eVidKMOqheW7TnaHGLDgG4s5KzZY3cXoI6_9Q1JjhNzN3zZVKoKtPvVEP8Fu2GCNs4ChHjO5Kf5KrO8PG3qjCIUcDWtca-YOlT7NVfXzeOGNXgfShPxaSTZVLz6f_wqIOjpj3Inr-EioEqe4oht7tLl3JV5k2-n_HqgrDHXo87K_cMjnySDAdD55W13uH0Xw5E36QCV_l-o9Wb8Hw63tBoS09j_LL38Qv8Yv5Zh0thEYW8BIiCVKkZZs1dTJFJ5YR2rGkEICRxh9DPse3Iq3gnn56vqZigFMAE&x-kcdn-pid=112781&Expires=1768112080&Signature=iicF7LURaAXhHw-UsAi6CyOIgGbGwTxtxvRzIaWCbRXii7DVfHXoE92~d446sOjtyoS7XYJf2eFRQP903IacRIIEl2YozO0Vf6J2Aie79hPg4UaqleCMStz9L05BWUJlnl3z4pN8ctI5n7RDBrmml7oy7HOoWM9kZNkfWIfdBCW-2IOYDIp7t8NZNgMBkhKYTOFP4jWuAC1opcUVfLYgTCwsValasDilbyD7mMMMVzbcZCLgd4~gu3mJpzZu1Asb5eNnrJS7XijpZleUi8VkFhjNYY2HkjI7PRo9C25SZXkIfsp2G0FJxQyqfL3EzA~MB5PB7oN~JEzB-YsW8elsTA__&Key-Pair-Id=K1FG4T7LWJK0FU","duration":"5.1"}],"audios":[]},"task_status_msg":"","created_at":1765519848595,"updated_at":1765520080995}');
} else {
    Main($headers, file_get_contents('php://input'));
}