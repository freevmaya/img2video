<?php
namespace App\Services\API;

class MidjourneyAPI implements APIInterface
{
    private $apiKey;
    private $baseUrl = 'https://api.userapi.ai';
    private $webhook_url;
    private $account_hash;
    private $modelTask;
    private $modelReply;

    public function __construct($apiKey, $webhook_url, $account_hash, 
                                $user_id, $modelTask, $modelReply)
    {
        $this->apiKey = $apiKey;
        $this->webhook_url = $webhook_url;
        $this->account_hash = $account_hash;
        $this->modelTask = $modelTask;
        $this->modelReply = $modelReply;
        $this->user_id = $user_id;
    }

    public function generateImage($prompt)
    {
        $data = [
            'prompt' => $prompt,
            'webhook_url' => $this->webhook_url,
            'webhook_type' => "progress",
            'account_hash' => $this->account_hash,
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

        if (isset($response['hash']))
            $this->modelTask->Update([
                'user_id'=>$this->user_id,
                'hash'=>$response['hash']
            ]);

        return $response;
    }

    public function Update($actionObject) {
        $tasks = $this->modelTask->getItems(['state'=>'active']);
        print_r($tasks);
        if (count($tasks) > 0) {
        }
    }
}