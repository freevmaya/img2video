<?
	function getSession($chatId) {
	    $file = BASEPATH."sessions/{$chatId}.json";
	    if (file_exists($file)) {
	        return json_decode(file_get_contents($file), true);
	    }
	    return [];
	}

	function saveSession($chatId, $data) {
	    file_put_contents(BASEPATH."sessions/{$chatId}.json", json_encode($data));
	}
?>