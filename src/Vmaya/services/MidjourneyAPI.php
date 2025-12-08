<?php
namespace App\Services\API;

class MidjourneyAPI implements APIInterface
{
    private $apiKey;
    private $baseUrl = 'https://api.userapi.ai';
    private $webhook_url;
    private $account_hash;

    public function __construct($apiKey, $webhook_url, $account_hash)
    {
        $this->apiKey = $apiKey;
        $this->webhook_url = $webhook_url;
        $this->account_hash = $account_hash;
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

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function getStatus($jobId) {

    }
}