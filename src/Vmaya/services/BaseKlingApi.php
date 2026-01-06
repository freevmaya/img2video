<?

namespace App\Services\API;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class BaseKlingApi extends BaseApi
{
    private $accessKey;
    private $secretKey;
    private $baseUrl = 'https://api-singapore.klingai.com/';

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

    protected function makeRequest($endpoint, $data)
    {
        $ch = curl_init($this->baseUrl . $endpoint);

        //trace($data);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$this->generateToken(),
                'Content-Type: application/json'
            ]
        ]);

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($response['code']) && (intval($response['code']) > 0))
            trace_error($response);
        else trace($response);

        return $response;
    }
}
