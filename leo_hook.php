<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Vmaya/engine.php';

define("LOG_FILE", LOGPATH.'leo_webhook.log');
define("LOG_ERROR_FILE", LOGPATH.'leo_webhook_error.log');
define("ISLOG", true);

if (!file_exists(RESULT_PATH))
    mkdir(RESULT_PATH, 0755, true);
if (!file_exists(PROCESS_PATH))
    mkdir(PROCESS_PATH, 0755, true);

define("ALLOW_IPS", ['35.173.108.170',
                    '34.239.69.60',
                    '52.73.75.186',
                    '3.229.99.26',
                    '44.218.0.197',
                    '174.129.230.221',
                    '127.0.0.1']);

function Main($headers, $input) {
    GLOBAL $dbp;

    if (!in_array(getClientIP(), ALLOW_IPS)) {
        http_response_code(405);
        echo "Not allowed";
        exit;
    }

    // Включаем логирование
    if (ISLOG)
        file_put_contents(LOG_FILE, 
            "\n-------------------------------------------------------\n".
            date('Y-m-d H:i:s') . " - Leo Webhook вызван\n", 
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

    /*
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
    }*/

    // Отвечаем, что все OK
    http_response_code(200);
    header('Content-Type: application/json');

    $dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);
    
    // Обрабатываем данные
    processLeoWebhookData($data);
    
    $dbp->Close();

    echo json_encode(['status' => 'ok']);
}

// Функция обработки данных Kling
function processLeoWebhookData($data) {
    $model = new LeoTasksModel();

    $images = isset($data['data']['object']['images']) ? $data['data']['object']['images'] : [];
    $result_url = count($images) > 0 ? $images[0]['url'] : '';

    $model->Update([
        'hash' => $data['data']['object']['id'],
        'type' => $data['type'],
        'status' => $data['data']['object']['status'],
        'object' => $data['object'],
        'result_url' => $result_url,
        'data' => json_encode($data['data'], JSON_FLAGS)
    ]);
}

$headers = getallheaders();

// Получаем сырые данные
if (DEV || empty($headers)) {
    // Тестовые данные для разработки
    Main($headers, '{
  "type": "image_generation.complete",
  "object": "generation",
  "timestamp": 1767281928038,
  "api_version": "v1",
  "data": {
    "object": {
      "id": "7bdc4e2a-34f2-47c6-8b6e-9286e3f6e356",
      "createdAt": "2026-01-01T15:38:42.470Z",
      "updatedAt": "2026-01-01T15:38:47.974Z",
      "userId": "d1c3d90f-830d-4929-946d-4e9df5a7db9f",
      "public": false,
      "flagged": false,
      "nsfw": false,
      "status": "COMPLETE",
      "coreModel": "FLUX",
      "guidanceScale": 3.5,
      "imageHeight": 1080,
      "imageWidth": 1920,
      "inferenceSteps": 15,
      "initGeneratedImageId": null,
      "initImageId": null,
      "initStrength": null,
      "initType": null,
      "initUpscaledImageId": null,
      "modelId": "7b592283-e8a7-4c5a-9ba6-d18c31f258b9",
      "negativePrompt": "",
      "prompt": "The man in the photo smiles and then turns his back. In the background, lifts are working and moving.",
      "quantity": 1,
      "sdVersion": "KINO_2_1",
      "tiling": false,
      "imageAspectRatio": null,
      "tokenCost": 0,
      "negativeStylePrompt": "",
      "seed": 457753474,
      "scheduler": "EULER_DISCRETE",
      "presetStyle": null,
      "promptMagic": false,
      "canvasInitImageId": null,
      "canvasMaskImageId": null,
      "canvasRequest": false,
      "api": true,
      "poseImage2Image": false,
      "imagePromptStrength": null,
      "category": null,
      "poseImage2ImageType": null,
      "highContrast": false,
      "apiDollarCost": 17,
      "poseImage2ImageWeight": null,
      "alchemy": false,
      "contrastRatio": null,
      "highResolution": null,
      "expandedDomain": null,
      "promptMagicVersion": null,
      "unzoom": null,
      "unzoomAmount": null,
      "photoReal": false,
      "promptMagicStrength": null,
      "photoRealStrength": null,
      "imageToImage": false,
      "controlnetsUsed": false,
      "motionLora": null,
      "motionLoraAlpha": null,
      "motionFrameInterpolation": null,
      "motionNumInterpolations": null,
      "motionDurationSeconds": null,
      "motionModule": null,
      "motionOfficialModelId": null,
      "motionGenerationResolution": null,
      "motion": null,
      "motionHasAudio": null,
      "fantasyAvatar": null,
      "liveCanvas": null,
      "isStoryboard": false,
      "liveGen": null,
      "photoRealVersion": null,
      "imageToVideo": null,
      "textToVideo": null,
      "motionModel": null,
      "motionStrength": null,
      "universalUpscaler": null,
      "teamId": null,
      "styleUUID": "111dc692-d470-4eec-b791-3475abac4c46",
      "ultra": false,
      "source": "LEONARDO",
      "transparency": "disabled",
      "generation_notes": [
        
      ],
      "model": {
        "id": "7b592283-e8a7-4c5a-9ba6-d18c31f258b9",
        "createdAt": "2025-07-29T06:55:24.198Z",
        "updatedAt": "2025-07-29T06:55:24.198Z",
        "name": "Lucid Origin",
        "description": "Your go-to model for vibrant, diverse imagery in HD output. Excellent prompt adherence and text rendering.",
        "public": true,
        "userId": "384ab5c8-55d8-47a1-be22-6a274913c324",
        "flagged": false,
        "nsfw": false,
        "official": true,
        "status": "COMPLETE",
        "classPrompt": null,
        "coreModel": "FLUX",
        "initDatasetId": null,
        "instancePrompt": "",
        "sdVersion": "KINO_2_1",
        "trainingEpoch": null,
        "trainingSteps": null,
        "tokenCost": null,
        "batchSize": null,
        "learningRate": null,
        "type": "GENERAL",
        "modelHeight": 1024,
        "modelWidth": 1024,
        "leonardoInstancePrompt": null,
        "trainingStrength": "MEDIUM",
        "featured": false,
        "featuredImageId": "24108f22-5eff-4612-a350-18bf7288314f",
        "featuredPosition": null,
        "api": false,
        "favouriteCount": 0,
        "imageCount": 0,
        "enhancedModeration": false,
        "apiDollarCost": null,
        "modelLRN": "s3://leonardo-user-models/hf_home/leonardo/FLUX.1-schnell-kino-2.1/",
        "motion": false,
        "teamId": null
      },
      "images": [
        {
          "id": "0bd07610-20b0-433d-b5ff-d2f1476db8df",
          "createdAt": "2026-01-01T15:38:47.976Z",
          "updatedAt": "2026-01-01T15:38:47.976Z",
          "userId": "d1c3d90f-830d-4929-946d-4e9df5a7db9f",
          "url": "https://cdn.leonardo.ai/users/d1c3d90f-830d-4929-946d-4e9df5a7db9f/generations/7bdc4e2a-34f2-47c6-8b6e-9286e3f6e356/segments/1:1:1/Lucid_Origin_The_man_in_the_photo_smiles_and_then_turns_his_ba_0.jpg",
          "generationId": "7bdc4e2a-34f2-47c6-8b6e-9286e3f6e356",
          "nobgId": null,
          "nsfw": false,
          "likeCount": 0,
          "trendingScore": 0,
          "public": false,
          "motionGIFURL": null,
          "motionMP4URL": null,
          "teamId": null,
          "image_height": 1080,
          "image_width": 1920
        }
      ],
      "teams": null
    }
  }
}');
} else {
    Main($headers, file_get_contents('php://input'));
}