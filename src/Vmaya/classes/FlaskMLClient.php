<?
class FlaskMLClient extends BaseMLStudioClient {

    public function Send() {

    	$data = ['prompt' => 'Почему небо синее?'];

		$options = [
	        'http' => [
	            'header'  => "Content-Type: application/json\r\n",
	            'method'  => 'POST',
	            'content' => json_encode($data),
	        ],
	    ];

	    $context = stream_context_create($options);
	    $result = json_decode(toUTF(file_get_contents($this->url, false, $context)), true);

		if (isset($result['choices'][0]['message'])) {
		    $aiReply = $result['choices'][0]['message']['content'];
		    $this->addMessage('assistant', $aiReply);
		} else {
		    echo "Ошибка: " . print_r($result, true);
		}

		return $result;
	}
}
?>