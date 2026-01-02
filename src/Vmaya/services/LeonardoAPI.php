<?php
namespace App\Services\API;

use App\Services\API\cycle\MjCycle;
use \Telegram\Bot\FileUpload\InputFile;

class LeonardoAPI implements APIInterface
{
    private $apiKey;
    private $baseUrl = 'https://cloud.leonardo.ai/api/rest/v1';
    protected $modelTask;
    protected $modelReply;
    protected $bot;

    public function __construct($apiKey, $bot = null, $modelTask = null, $modelReply = null)
    {
        $this->apiKey       = $apiKey;
        $this->modelTask    = $modelTask;
        $this->modelReply   = $modelReply;
        $this->bot          = $bot;
    }

    public function generateImage($prompt, $options=[])
    {
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

        return $this->makeRequest('/generations', $data);
    }

    public function generateImageFromImage($imagePath, $prompt, $options=[]) {

    }

    public function generateVideoFromImage($imagePath, $prompt, $options=[]) {

    }

    private function makeRequest($endpoint, $data)
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'accept: application/json',
                'authorization: Bearer '.$this->apiKey
            ]
        ]);

        if (PRODUCTION) $response = curl_exec($ch);
        else {
            echo "DEV Leonardo AI REQUEST!";
            $response = [
                'hash'=>md5(strtotime('now'))
            ];
        }

        curl_close($ch);

        $logstr = "Endpoint: {$endpoint}\nResponse: ".json_encode($response, JSON_FLAGS);

        if (isset($response['error']) || (isset($response['status']) && ($response['status'] === false))) {
            trace_error($logstr.".\nSend data:".json_encode($data, JSON_FLAGS));
        }
        else {
        
            trace($logstr);

            $job = $response['sdGenerationJob'];

            $hash = isset($job['generationId']) ? $job['generationId'] : false;

            if ($hash && $this->modelTask && $this->bot) {

                $chat_id = @$this->bot->CurrentUpdate()->getMessage()->getChat()->getId();
                $this->modelTask->Update([
                    'user_id'=>$this->bot->getUserId(),
                    'chat_id'=>$chat_id,
                    'service'=>'leo'
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