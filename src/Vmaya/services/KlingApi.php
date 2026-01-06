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

    protected function makeRequest($url, $request_data)
    {
        $request_data['callback_url'] = KL_HOOK_URL;

        if (PRODUCTION) 
            $response = parent::makeRequest($url, $request_data);
        else {
            echo("DEV REQUEST!\n");

            $response = [
                'code'=>0,
                'data'=>[
                    'task_id' => '123456789'
                ]
            ];
        }

        if (isset($response['data']) && (@$response['code'] == 0)) {
        	$data = $response['data'];

            $request_data_json = json_encode(array_merge($request_data, ['url'=>$url]), JSON_FLAGS);

        	$params = [
        		'hash'=>$data['task_id'],
        		'service'=>'kling',
                'user_id'=>ADMIN_USERID,
                'chat_id'=>ADMIN_USERID,
                'request_data' => $request_data_json
        	];

            if (DEV)
                trace('Request data: '.$request_data_json);

            if ($this->modelTask) {
                $this->modelTask->Update($params);

                if ($this->bot) {
                    $params['user_id'] = $this->bot->getUserId();
                    $params['chat_id'] = $this->bot->getCurrentChatId();
                }
            }

            $this->Answer(Lang("The task has been accepted"));

            return $response;
        }

        $this->Wrong();

        return false;
    }
}