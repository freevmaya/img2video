<?php
namespace App\Services\API;

use App\Services\API\cycle\MjCycle;
use \Telegram\Bot\FileUpload\InputFile;

class LeonardoApi extends BaseApi
{
    private $apiKey;
    protected $modelTask;
    protected $modelReply;

    public function __construct($apiKey, $bot = null, $modelTask = null, $modelReply = null)
    {
        parent::__construct('leonardo', $bot);
        $this->apiKey       = $apiKey;
        $this->modelTask    = $modelTask;
        $this->modelReply   = $modelReply;
    }

    public function defaultUrl() {
        return 'https://cloud.leonardo.ai/api/rest/v1/generations';
    }

    public function generateImage($prompt, $options=[])
    {
        $models = $this->getModels();

        $prompt = checkRusAndTranslate($prompt);

        $model = false;
        if (isset($options['model'])) {
            $model = $options['model'];
            unset($options['model']);
        }

        $url = $this->defaultUrl();

        if (!empty($model) && isset($models[$model])) {
            if ($models[$model]['enabled'] && ($data = $this->setModelPrompt($model, $prompt))) {
                $data = array_merge($data, $options);
                $url = $this->getModelUrl($model);
            }
            else return false;
        } else {
            $data = array_merge([
                'alchemy'       => false,
                "height"        => 1080,
                'prompt'        => $prompt,
                'modelId'       => "7b592283-e8a7-4c5a-9ba6-d18c31f258b9",
                'contrast'      => 3.5,
                'num_images'    => 1,
                "styleUUID"     => '111dc692-d470-4eec-b791-3475abac4c46',
                "width"         => 1920,
                "ultra"         => false
            ], $options);
        }

        return $this->makeRequest($url, $data);
    }

    public function generateImageFromImage($imagePath, $prompt, $options=[]) {

    }

    public function generateVideoFromImage($imagePath, $prompt, $options=[]) {

    }

    protected function curl($url, $data, $callbackError = null /* ($error, $response) */) {
        $ch = curl_init($url);
                
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => is_string($data) ? $data : json_encode($data),
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'accept: application/json',
                'authorization: Bearer '.$this->apiKey
            ]
        ]);

        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            curl_close($ch);
        }

        $response = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        if ($httpCode !== 200) {
            if ($callbackError)
                $callbackError($error, $response);
            else trace_error("Error response: ".$error);
            $response = null;
        }

        return $response;
    }

    private function getPresignedUrl($extension = 'jpg') {
        $payload = json_encode(["extension" => $extension]);
        $data    = $this->curl("https://cloud.leonardo.ai/api/rest/v1/init-image", $payload);

        if ($data)
            return [
                'id' => $data['uploadInitImage']['id'],
                'url' => $data['uploadInitImage']['url'],
                'fields' => json_decode($data['uploadInitImage']['fields'], true)
            ];
        return false;
    }

    private function getMimeType($filePath) {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }
        
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp'
        ];
        
        return $mimeTypes[$extension] ?? 'image/jpeg';
    }

    private function uploadImage($presignedData, $imagePath) {
        if (!file_exists($imagePath)) {
            throw new Exception("Файл изображения не найден: " . $imagePath);
        }
        
        // Определяем MIME-тип
        $mimeType = $this->getMimeType($imagePath);
        
        // Подготавливаем данные для отправки
        $postData = $presignedData['fields'];
        
        // Добавляем файл
        $postData['file'] = new \CURLFile(
            $imagePath,
            $mimeType,
            basename($imagePath)
        );
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $presignedData['url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            // Важно: не передаем заголовки для presigned URL!
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            curl_close($ch);
        }
        
        if ($error) {
            trace_error("Ошибка при загрузке изображения: " . $error);
        } else {
        
            // Для presigned URL успешный код обычно 204 (No Content)
            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success'   => true,
                    'image_id'  => $presignedData['id'],
                    'http_code' => $httpCode
                ];
            } else {
                trace_error("Ошибка загрузки. HTTP код: " . $httpCode . " Ответ: " . $response);
            }
        }

        return false;
    }

    public function Upload($imagePath) {

        if (!file_exists($imagePath)) {
            throw new Exception("Файл изображения не найден: " . $imagePath);
        }

        $extension = pathinfo($imagePath, PATHINFO_EXTENSION);
        if ($presignedData = $this->getPresignedUrl($extension)) {
            return $this->uploadImage($presignedData, $imagePath);
        }

        return false;
    }

    public function uploadMultiple($imagePaths) {
        $results = [];
        
        foreach ($imagePaths as $index => $imagePath) {
            //echo "\n=== Загрузка изображения " . ($index + 1) . " из " . count($imagePaths) . " ===\n";
            
            $result = $this->upload($imagePath);
            $results[] = [
                'path' => $imagePath,
                'result' => $result
            ];
            
            // Небольшая задержка между запросами
            if ($index < count($imagePaths) - 1) {
                sleep(1);
            }
        }
        
        return $results;
    }

    private function makeRequest($url, $data)
    {

        if (PRODUCTION) {
            try {
                $response   = $this->curl($url, $data);
            } catch (Exception $e) {
                trace_error('Caught exception: ',  $e->getMessage());
            }
        }
        else {
            echo "DEV Leonardo AI REQUEST!";
            $response = [
                'sdGenerationJob' => [
                    'generationId' => md5(strtotime('now'))
                ]
            ];
        }

        $logstr = "Endpoint: {$url}\nResponse: ".json_encode($response, JSON_FLAGS).".\nSend data:".json_encode($data, JSON_FLAGS);

        if (!$response || isset($response['error']))
            trace_error($logstr);
        else {

//{"generate":{"apiCreditCost":140,"generationId":"1f0e854a-f871-6d90-bd02-8c15f93ff666"}}.

            $hash = false;
            if (isset($response['sdGenerationJob'])) {
                $job = $response['sdGenerationJob'];
                $hash = isset($job['generationId']) ? trim($job['generationId']) : false;
            } else  if (isset($response['generate'])) {
                $generate = $response['generate'];
                $hash = isset($generate['generationId']) ? trim($generate['generationId']) : false;
            }

            if ($hash && $this->modelTask && $this->bot) {
        
                trace($logstr);

                $this->modelTask->Update([
                    'user_id'=>$this->bot->getUserId(),
                    'chat_id'=>$this->bot->getCurrentChatId(),
                    'service'=>'leo',
                    'hash'=>$response['hash'] = $hash,
                    'request_data'=> json_encode(array_merge($data, ['url'=>$url]), JSON_FLAGS),
                    'response_data'=> json_encode($response, JSON_FLAGS)
                ]);

                $this->Answer(Lang("The task has been accepted"));
                return $hash;

            } else trace_error($logstr.".\nSend data:".json_encode($data, JSON_FLAGS));
        }
        $this->Wrong(Lang("Something wrong"));

        return false;
    }
}