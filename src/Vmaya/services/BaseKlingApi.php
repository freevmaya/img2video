<?

namespace App\Services\API;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class BaseKlingApi extends BaseApi
{
    private $accessKey;
    private $secretKey;
    private $model_name;
    private $baseUrl = 'https://api-singapore.klingai.com/';

    public function __construct($accessKey, $secretKey, $model_name='kling-v1', $bot=null)
    {
        parent::__construct('kling', $bot);
        $this->accessKey    = $accessKey;
        $this->secretKey 	= $secretKey;
        $this->model_name 	= $model_name;
    }

    public function defaultUrl() {
        return $this->baseUrl;
    }

    public function generateToken() {
	    
	    $payload = [
	        "iss" => $this->accessKey,
	        "exp" => time() + 1800, # The valid time, in this example, represents the current time+1800s(30min)
	        "nbf" => time() - 5 # The time when it starts to take effect, in this example, represents the current time minus 5s
	    ];

	    return JWT::encode($payload, $this->secretKey, "HS256");
	}

    public function generateImage($prompt, $options=[]) {

    }

    public function generateImageFromImage($imageDatas, $prompt, $options=[]) {
    	
    }

    public function generateVideoFromImage($imageDatas, $prompt, $options=[]) {

    	return $this->makeRequest('v1/videos/image2video', array_merge([
    		'model_name' => $this->model_name,
    		"mode" => "pro",
    		"duration" => "5",
    		"image" => $imageDatas[0],
		    "prompt" => $prompt,
		    "cfg_scale" => 0.5
    	], $options));    	
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
