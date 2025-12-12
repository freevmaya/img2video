<?

namespace App\Services\API;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class KlingApi extends BaseKlingApi
{
    protected $bot;

    public function __construct($accessKey, $secretKey, $model_name='kling-v1', 
                                $bot=null)
    {
    	parent::__construct($accessKey, $secretKey, $model_name);
        $this->bot          = $bot;
    }

    protected function extendOptions() {
        return [
        	'callback_url'=> KL_HOOK_URL
        ];
    }

    protected function makeRequest($endpoint, $data)
    {        
        $response = $this->makeRequest($endpoint, $data);

        if (isset($response['data']) && (@$response['code'] == 0)) {
        	$data = $response['data'];

        	$params = [
        		'hash'=>$data['task_id'],
        		'service'=>'kling'
        	]

        	if ($this->bot) {
        		$params['user_id'] = $this->bot->getUserId();
        		$params['chat_id'] = $this->bot->CurrentUpdate()->getMessage()->getChat()->getId();
        	}

        	(new TaskModel())->Update($params);
        }

        return $response;
    }
}