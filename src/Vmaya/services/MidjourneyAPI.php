<?php
namespace App\Services\API;

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

    public function generateImage($prompt)
    {
        $data = [
            'prompt'        => $prompt,
            'webhook_url'   => $this->webhook_url,
            'webhook_type'  => "progress",
            'account_hash'  => $this->account_hash,
            "is_disable_prefilter" => true
        ];

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

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        trace($response);

        if (isset($response['error']))
            $this->error($endpoint.': '.$response['error']);
        else {

            $hash = isset($response['hash']) ? $response['hash'] : false;

            if ($hash && $this->modelTask)
                $this->modelTask->Update([
                    'user_id'=>$this->bot->getUserId(),
                    'chat_id'=>$this->bot->CurrentUpdate()->getMessage()->getChat()->getId(),
                    'hash'=>$response['hash']
                ]);

            return $hash;
        }

        return false;
    }
}