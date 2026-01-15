<?php
namespace App\Services\API;

use App\Services\API\cycle\MjCycle;
use \Telegram\Bot\FileUpload\InputFile;


function ParseUrl($url) {
    $paterns = [ 
        '/\/([a-z\d-]+)_grid_([\d]+)/',
        '/_([a-z\d-]+).png\?/',
        '/within_a__([\w\d-]+)\.webp/'
    ];
    foreach ($paterns as $pattern) {
        if (preg_match($pattern, $url, $matches) && (count($matches) > 1)) 
            return $matches;
    }
    return null;
}

class MidjourneyApi extends BaseApi
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
        parent::__construct();
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

    protected function preparePresetImages(&$presetOptions, $images) {
        
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
            ($matches = ParseUrl($result['url']))) {

            $filename = $hash.'_'.$choice.'.png';
            $url = MJ_BASE_URL.$matches[1].'/0_'.$choice.'.png';

            $file_path = RESULT_PATH.$filename;

            $this->bot->downloadClient->AddTask(function($record, $data) {

                resizeImageIfTooLarge($record['path']);

                if ($record['state'] == 'failure')
                    $data['this']->bot->Answer($chatId, Lang('Fail download image'));
                else {
                    $params = [
                        'chat_id' => $data['chat_id'],
                        'photo' => InputFile::create($record['path'], $data['filename']),
                        'caption' => Lang('Your photo is ready'),
                        'parse_mode' => 'HTML'
                    ];

                    $photoMessage = $data['this']->bot->Api()->sendPhoto($params);
                }

            }, $url, $file_path, [
                'this' => $this,
                'chat_id' => $chatId,
                'filename' => $filename
            ]);

        } else {
            trace_error("Fail download file url: {$url}, hash: $hash");
            $this->bot->Answer($chatId, Lang('Fail download image'));
        }
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

    protected function makeRequest($endpoint, $data)
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

        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            curl_close($ch);
        }

        $logstr = "Endpoint: {$endpoint}\nResponse: ".json_encode($response, JSON_FLAGS);

        if (isset($response['error']) || (isset($response['status']) && ($response['status'] === false))) {
            trace_error($logstr.".\nSend data:".json_encode($data, JSON_FLAGS));
        }
        else {
        
            trace($logstr);

            $hash = isset($response['hash']) ? $response['hash'] : false;

            if ($hash && $this->modelTask) {
                
                $this->modelTask->Update([
                    'user_id'=>$this->bot->getUserId(),
                    'chat_id'=>$this->bot->getCurrentChatId(),
                    'hash'=>$response['hash'] = $hash,
                    'request_data'=> json_encode(array_merge($data, ['endpoint'=>$endpoint]), JSON_FLAGS),
                    'response_data'=> json_encode($response, JSON_FLAGS)
                ]);
                $this->Answer(Lang("The task has been accepted"));
            }

            return $hash;
        }

        return false;
    }
}