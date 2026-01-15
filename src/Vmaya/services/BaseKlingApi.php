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

    protected function makeRequest($url, $data, $post = true)
    {
        $ch = curl_init($url);
        $token = $this->generateToken();

        //trace($data);
        
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
}
