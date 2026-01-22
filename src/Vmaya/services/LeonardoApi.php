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

    protected function curl($url, $data, $callbackError = null /* ($error, $response) */) {
        $ch = curl_init($url);
                
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => is_string($data) ? $data : json_encode($data, JSON_FLAGS),
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
            trace_error("Файл изображения не найден: " . $imagePath);
            return false;
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
            trace_error("Файл изображения не найден: " . $imagePath);
            return false;
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

    public function prepareImage($file_id) {
        if (isUrl($file_id)) {

            /*
            if ($this->bot)
                $filePath = USER_PATH.$this->bot->getUserId().'_'.time().'_'.basename($file_id);
            else $filePath = USER_PATH.basename($file_id);
            */

            $filePath = ($this->bot ? $this->bot->getUserId().'_' : '').time().'_'.basename($file_id);

            if (!file_exists($filePath))
                downloadFile($file_id, $filePath);

            if ($result = $this->Upload($filePath))
                return $result['image_id'];
            else {
                trace_error("Error upload file: {$file_id}");
                return false;
            }
        }

        return $file_id;
    }

    private function setImagesKlingO1(&$options, $images) {
        $count = count($images);
        switch ($count) {
            case 1:
                unset($options['parameters']['guidances']['start_frame']);
                unset($options['parameters']['guidances']['end_frame']);
                $options['parameters']['guidances']['image_reference'][0]['image']['id'] = $images[0];
                $options['parameters']['guidances']['image_reference'][0]['image']['type'] = $images[0] ? 'UPLOADED' : 'GENERATED';
                break;
            case 2:
                $options['parameters']['guidances']['start_frame'][0]['image']['id'] = $images[0];
                $options['parameters']['guidances']['end_frame'][0]['image']['id'] = $images[1];
                $options['parameters']['guidances']['start_frame'][0]['image']['type'] = $images[0] ? 'UPLOADED' : 'GENERATED';
                $options['parameters']['guidances']['end_frame'][0]['image']['type'] = $images[1] ? 'UPLOADED' : 'GENERATED';
                unset($options['parameters']['guidances']['image_reference']);
                break;
            default:
                return false;
        }
        return true;
    }

    private function setImageGuidance(&$options, $images)
    {
        $count = count($images);
        if ($count < 1)
            return false;

        $controlnets = $options['controlnets'];
        for ($i=0; $i<$count; $i++) {
            $controlnets[$i] = array_merge(isset($controlnets[$i]) ? $controlnets[$i] : $controlnets[count($controlnets) - 1], [
                'initImageId'=>$images[$i]
            ]);
        }

        $options['controlnets'] = $controlnets;
        return true;
    }

    private function setImagesSeedream(&$options, $images)
    {
        $count = count($images);
        if ($count < 1)
            return false;

        $ref = [];
        for ($i=0; $i<$count; $i++) {
            $ref[] = [
                "image" => [
                    "id"    => $images[$i],
                    "type"  => "UPLOADED"
                ],
                "strength" => "MID"
            ];
        }
        $options['parameters']['guidances']['image_reference'] = $ref;
        return true;
    }

    protected function setImages($model_name, &$options, $images) {
        if (!empty($images))
            for ($i=0; $i<count($images); $i++)
                if (!$images[$i] = $this->prepareImage($images[$i]))
                    return false;

        if ($model_name == 'Kling O1') {
            return $this->setImagesKlingO1($options, $images);
        }

        if ($model_name == 'Image Guidance')
            return $this->setImageGuidance($options, $images);

        if ($model_name == 'Seedream')
            return $this->setImagesSeedream($options, $images);
        
        $info = $this->getModelInfo($model_name);
        if ($info && ($info['type'] == 'textToImage'))
            return true;

        return false;
    }

    protected function makeRequest($url, $data, $preset_name=null)
    {

        if (PRODUCTION) {
            try {
                $response   = $this->curl($url, $data);
            } catch (\Exception $e) {
                trace_error('Caught exception: ',  $e->getMessage());
                return false;
            }
        }
        else {
            if (DEV) {
                echo "DEV Leonardo AI REQUEST!\n";
                print_r($data);
            }
            $response = [
                'sdGenerationJob' => [
                    'generationId' => md5(strtotime('now'))
                ]
            ];
        }

        $logstr = "Endpoint: {$url}\n\nResponse: ".json_encode($response, JSON_FLAGS).".\n\nSend data:".json_encode($data, JSON_FLAGS);

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

            if ($hash) {
        
                trace($logstr);

                if ($this->modelTask) {

                    $this->modelTask->Update([
                        'user_id'=> $this->bot ? $this->bot->getUserId() : ADMIN_USERID,
                        'chat_id'=> $this->bot ? $this->bot->getCurrentChatId() : ADMIN_USERID,
                        'service'=>'leo',
                        'preset'=>$preset_name,
                        'hash'=>$response['hash'] = $hash,
                        'request_data'=> json_encode(array_merge($data, ['url'=>$url]), JSON_FLAGS),
                        'response_data'=> json_encode($response, JSON_FLAGS)
                    ]);
                }

                $this->Answer(Lang("The task has been accepted"));
                return $response;

            } else {
                $msg = $logstr.".\nSend data:".json_encode($data, JSON_FLAGS);
                trace_error($msg);
                $this->SendToAdmin(['text' => $msg]);
            }
        }

        $this->Wrong();

        return false;
    }
}