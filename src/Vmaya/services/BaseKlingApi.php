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

    protected function preparePresetImages(&$presetOptions, $images) {
        
    }

    protected function requireTranslate($info) {
        return false;
    }

    protected function checkResponse($response) {
        if (DEV)
            echo json_encode($response, JSON_FLAGS)."\n";

        if (isset($response['code']) && ($response['code'] > 0)) {
            trace_error($response);
            return false;
        }
        return true;
    }

    public function prepareVideoMultiElement($videoUrl, $points) {
        $session_id = md5(time());
        $response = $this->baseRequest($this->baseUrl.'v1/videos/multi-elements/init-selection', [
            'video_url' => $videoUrl
        ]);

        if (!$this->checkResponse($response)) return false;

        $session_id = $response['data']['session_id'];

        for ($i=0; $i<count($points); $i++) {
            $response = $this->baseRequest($this->baseUrl.'/v1/videos/multi-elements/add-selection', [
                'session_id'    => $session_id,
                'frame_index'   => $i,
                'points'        => $points[$i]
            ]);
            if (!$this->checkResponse($response)) return false;
        }
    }

    protected function baseRequest($url, $data, $post = true)
    {
        $ch = curl_init($url);
        $token = $this->generateToken();
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => $post,
            CURLOPT_POSTFIELDS => json_encode($data),
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

    protected function makeRequest($url, $data, $post = true)
    {
        return $this->baseRequest($url, $data, $post);
    }
}
