<?

namespace App\Services\API;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Services\API\BaseKlingApi;

class KlingApi extends BaseKlingApi
{
    protected $bot;
    protected $modelTask;

    public function __construct($accessKey, $secretKey, $modelTask, $model_name='kling-v1', 
                                $bot=null)
    {
    	parent::__construct($accessKey, $secretKey, $model_name);
        $this->bot = $bot;
        $this->modelTask = $modelTask;
    }

    protected function extendOptions() {
        return [
        	'callback_url'=> KL_HOOK_URL
        ];
    }

    protected function makeRequest($endpoint, $data)
    {
        $response = ['data'=>[
        	'code'=>0,
        	'task_id'=>1234567890
        ]];//parent::makeRequest($endpoint, $data);

        if (isset($response['data']) && (@$response['code'] == 0)) {
        	$data = $response['data'];

        	$params = [
        		'hash'=>$data['task_id'],
        		'service'=>'kling',
                'user_id'=>ADMIN_USERID,
                'chat_id'=>ADMIN_USERID
        	];

        	if ($this->bot) {
        		$params['user_id'] = $this->bot->getUserId();
        		$params['chat_id'] = $this->bot->CurrentUpdate()->getMessage()->getChat()->getId();
        	}

        	$this->modelTask->Update($params);
        }

        return $response;
    }
}