<?php
namespace App\Services\API;

use App\Services\API\cycle\MjCycle;
use \Telegram\Bot\FileUpload\InputFile;

class MidjourneyAPI implements APIInterface
{
    private $apiKey;
    private $baseUrl = 'https://api.userapi.ai';
    private $webhook_url;
    private $account_hash;
    protected $modelTask;
    protected $modelReply;
    protected $bot;

    public function __construct($apiKey, $webhook_url, $account_hash, 
                                $bot, $modelTask, $modelReply)
    {
        $this->apiKey       = $apiKey;
        $this->webhook_url  = $webhook_url;
        $this->account_hash = $account_hash;
        $this->modelTask    = $modelTask;
        $this->modelReply   = $modelReply;
        $this->bot          = $bot;
    }

    public function generateImage($prompt, $options=[])
    {
        $data = array_merge([
            'prompt'        => $prompt,
            'webhook_url'   => $this->webhook_url,
            'webhook_type'  => "progress",
            'account_hash'  => $this->account_hash,
            "is_disable_prefilter" => false
        ], $options);

        return $this->makeRequest('/midjourney/v2/imagine', $data);
    }

    public function generateImageFromImage($imagePath, $prompt, $options = [])
    {
        $data = [
            'image' => base64_encode(file_get_contents($imagePath)),
            'prompt' => $prompt,
            'options' => $options
        ];

        return $this->makeRequest('/generate/image-from-image', $data);
    }

    public function generateVideoFromImage($imagePath, $prompt, $options = [])
    {
        // Midjourney может не поддерживать видео
        throw new \Exception("Video generation not supported by Midjourney API");
    }

    public function Select($chatId, $hash, $choice) {
        GLOBAL $dbp;

        $response = $dbp->line("SELECT * FROM mj_tasks WHERE `hash`='$hash' AND `type`='imagine' AND `status`='progress' AND `result` IS NOT NULL ORDER BY id DESC");

        if (($result = json_decode($response['result'], true)) &&
            ($matches = MjCycle::parseUrl($result['url']))) {

            $filename = $hash.'_'.$choice.'.png';
            $url = MJ_BASE_URL.$matches[1].'/0_'.$choice.'.png';

            $file_path = RESULT_PATH.$filename;

            if (!file_exists($file_path)) {
                if (!scraperDownload($url, $file_path)) {
                    trace_error("Fail download file url: {$url}, hash: $hash");
                    $this->bot->Answer($chatId, Lang('Fail download image'));
                    return;
                }
            }

            $params = [
                'chat_id' => $chatId,
                'photo' => InputFile::create($file_path, $filename),
                'caption' => Lang('Your photo is ready'),
                'parse_mode' => 'HTML'
            ];

            $photoMessage = $this->bot->Api()->sendPhoto($params);

        } else {
            trace_error("Fail download file url: {$url}, hash: $hash");
            $this->bot->Answer($chatId, Lang('Fail download image'));
        }
        /*
        $result = json_decode($response['result'], true);
        $hash = $task['hash'];

        if ($file_path = MjCycle::prepareFile($task, $response, RESULT_PATH, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            if ($result = $this->bot->sendAnimation($task['chat_id'], $file_path, $filename, '🎬 '.Lang("Your video is ready"), [
                    'width' => $result['width'],
                    'height' => $result['height']
                ])) {

                (new TransactionsModel())->PayUpscale($task['user_id'], [
                    'response_id'=>$response['id'],
                    'hash'=>$hash
                ]);
            }

            return $result;
        }
        return false;
        */
    }

    public function Upscale($hash, $choice)
    {
        $data = [
            'hash'          => $hash,
            'choice'        => $choice,
            'webhook_url'   => $this->webhook_url,
            'webhook_type'  => 'result'
        ];

        return $this->makeRequest('/midjourney/v2/upscale', $data);
    }

    public function Animate($hash, $choice='high') // or low
    {
        $data = [
            'hash'          => $hash,
            'choice'        => $choice,
            'webhook_url'   => $this->webhook_url,
            'webhook_type'  => 'result'
        ];

        return $this->makeRequest('/midjourney/v2/animate', $data);
    }

    public function Describe($imageUrl) // or low
    {
        $data = [
            'url'           => $imageUrl,
            'webhook_url'   => $this->webhook_url,
            'webhook_type'  => 'result',
            'account_hash'  => $this->account_hash
        ];

        return $this->makeRequest('/midjourney/v2/describe', $data);
    }

    protected function error($error) {

    }

    private function makeRequest($endpoint, $data)
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'api-key:'.$this->apiKey,
                'Authorization: Bearer '.$this->apiKey,
                'Content-Type: application/json'
            ]
        ]);

        if (PRODUCTION) $response = json_decode(curl_exec($ch), true);
        else {
            echo "DEV MJ REQUEST!";
            $response = [
                'hash'=>md5(strtotime('now'))
            ];
        }
        
        trace($response);

        curl_close($ch);

        if (isset($response['error']))
            $this->error($endpoint.': '.$response['error']);
        else {

            $hash = isset($response['hash']) ? $response['hash'] : false;

            if ($hash && $this->modelTask) {

                $chat_id = @$this->bot->CurrentUpdate()->getMessage()->getChat()->getId();
                $this->modelTask->Update([
                    'user_id'=>$this->bot->getUserId(),
                    'chat_id'=>$chat_id,
                    'hash'=>$response['hash'] = $hash,
                    'request_data'=> json_encode(array_merge($data, ['endpoint'=>$endpoint]), JSON_FLAGS)
                ]);
                $this->bot->Answer($chat_id, ['text' => Lang("The task has been accepted")]);
            }

            return $hash;
        }

        return false;
    }
}