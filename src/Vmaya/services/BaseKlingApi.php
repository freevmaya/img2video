<?

namespace App\Services\API;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class BaseKlingApi extends BaseApi
{
    private $accessKey;
    private $secretKey;
    protected $baseUrl = 'https://api-singapore.klingai.com/';

    public function __construct($accessKey, $secretKey, $bot=null)
    {
        parent::__construct('kling', $bot);
        $this->accessKey    = $accessKey;
        $this->secretKey 	= $secretKey;
    }

    public function generateToken() {
	    
	    $payload = [
	        "iss" => $this->accessKey,
	        "exp" => time() + 1800, # The valid time, in this example, represents the current time+1800s(30min)
	        "nbf" => time() - 5 # The time when it starts to take effect, in this example, represents the current time minus 5s
	    ];

	    return JWT::encode($payload, $this->secretKey, "HS256");
	}

    protected function requireTranslate($info) {
        return false;
    }

    protected function checkResponse($response) {
        /*
        if (DEV) {
            $msg = json_encode($response, JSON_FLAGS)."\n";
            echo $msg;
            trace($msg);
        }*/

        if (isset($response['code']) && ($response['code'] > 0)) {
            $msg = json_encode($response, JSON_FLAGS)."\n";
            if (DEV)
                echo $msg;
            
            trace_error($msg);
            return false;
        }
        return true;
    }

    public function initSession($videoUrl) {
        $response = $this->baseRequest($this->baseUrl.'v1/videos/multi-elements/init-selection', [
            'video_url' => $videoUrl
        ]);

        if (!$this->checkResponse($response)) return false;

        return $response['data']['session_id'];
    }

    public function addVideoSelection($session_id, $index, $points) {
        $params = [
            'session_id'    => $session_id,
            'frame_index'   => $index,
            'points'        => $points
        ];

        $response = $this->baseRequest($this->baseUrl.'/v1/videos/multi-elements/add-selection', $params);
        if (!$this->checkResponse($response)) return false;

        return $response;
    }

    public function deleteVideoSelection($session_id, $index, $points) {

        $response = $this->baseRequest($this->baseUrl.'/v1/videos/multi-elements/delete-selection', [
            'session_id'    => $session_id,
            'frame_index'   => $index,
            'points'        => $points
        ]);
        if (!$this->checkResponse($response)) return false;

        return $response;
    }

    public function clearVideoSelection($session_id) {

        $response = $this->baseRequest($this->baseUrl.'/v1/videos/multi-elements/clear-selection', [
            'session_id'    => $session_id
        ]);
        if (!$this->checkResponse($response)) return false;

        return $response;
    }

    public function baseRequest($url, $data, $post = true)
    {
        $ch = curl_init($url);
        $token = $this->generateToken();
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => $post,
            CURLOPT_POSTFIELDS => is_string($data) ? $data : json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json'
            ]
        ]);

        $response = json_decode(curl_exec($ch), true);

        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            curl_close($ch);
        }

        return $response;
    }

    protected function makeRequest($url, $data, $preset_name=null)
    {
        return $this->baseRequest($url, $data);
    }
}
