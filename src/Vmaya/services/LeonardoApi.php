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

    private function makeRequest($url, $data)
    {

        if (PRODUCTION) {
            try {

                $ch = curl_init($url);
                
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

                $response   = json_decode(curl_exec($ch), true);
                $error      = curl_error($ch);
            } catch (Exception $e) {
                trace_error('Caught exception: ',  $e->getMessage());
            }

            if (version_compare(PHP_VERSION, '8.0.0', '<')) {
                curl_close($ch);
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

        $logstr = "Endpoint: {$url}\nResponse: ".json_encode($response, JSON_FLAGS);

        if (isset($response['error']) || !empty($error) || 
            (isset($response['status']) && ($response['status'] === false))) {
            
            trace_error($logstr.".\nSend data:".json_encode($data, JSON_FLAGS));
            if (!empty($error))
                trace_error($error);
        }
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

            trace($hash);

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