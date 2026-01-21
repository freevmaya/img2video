<?

namespace App\Services\API;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Services\API\BaseKlingApi;

class KlingApi extends BaseKlingApi
{
    protected $modelTask;

    public function __construct($accessKey, $secretKey, $modelTask=null, $bot=null)
    {
    	parent::__construct($accessKey, $secretKey, $bot);
        $this->modelTask = $modelTask;
    }

    public function AccountInfo($params = []) {
        return $this->makeRequest('account/costs', array_merge([
            'start_time'=>time(),
            'end_time'=>time(),
            'resource_pack_name'=>'All'
        ], $params), false);
    }

    protected function setImages($model_name, &$options, $images) {

        if (count($images) == 0)
            return false;

        foreach ($options as $key=>$rec)
            if ($key == 'image') {
                $options['image'] = $this->prepareImage($images[0]);
                return true;
            }
        return false;
    }

    public function prepareImage($url) {

        $file_name = $this->bot->getUserId().'_'.basename($url);
        $filePath = USER_PATH.$file_name;
        $newUrl = USER_URL.$file_name;

        if (file_exists($filePath))
            return $newUrl;

        $result = downloadFile($url, $filePath);

        if ($result['success']) {
            
            resizeImageIfTooLarge($filePath, null, [
                'max_width' => 640,
                'max_height' => 640,
            ]);

            return $newUrl;
        }
        return false;
    }

    protected function makeRequest($url, $request_data, $preset_name=null)
    {
        $url = isUrl($url) ? $url : $this->baseUrl . $url;

        $request_data['callback_url'] = KL_HOOK_URL;

        if (PRODUCTION) 
            $response = parent::makeRequest($url, $request_data);
        else {

            if (DEV) {
                echo("DEV Kling REQUEST!\n");
                print_r($request_data);
            }

            $response = [
                'code'=>0,
                'data'=>[
                    'task_id' => '123456789'
                ]
            ];
        }
            
        $request_data_json = json_encode($request_data, JSON_FLAGS);
        $log_data = "\nUrl: {$url}\n\nResponse: ".json_encode($response, JSON_FLAGS)."\n\nRequest data: ".$request_data_json;

        if (isset($response['data']) && ($response['code'] == 0)) {
        	$data = $response['data'];


        	$params = [
        		'hash'=>$data['task_id'],
        		'service'=>'kling',
                'preset'=>$preset_name,
                'request_data' => $request_data_json,
                'response_data'=> json_encode($response, JSON_FLAGS)
        	];

            if (DEV)
                trace($log_data);

            if ($this->modelTask) {

                if ($this->bot) {
                    $params['user_id'] = $this->bot->getUserId();
                    $params['chat_id'] = $this->bot->getCurrentChatId();
                } else if (DEV) {
                    $params['user_id'] = ADMIN_USERID;
                    $params['chat_id'] = ADMIN_USERID;
                }
                $this->modelTask->Update($params);
            }

            $this->Answer(Lang("The task has been accepted"));

            return $response;
        } else {
            trace_error($log_data);
            $this->SendToAdmin(['text' => $log_data]);
        }

        $this->Wrong();

        return false;
    }
}