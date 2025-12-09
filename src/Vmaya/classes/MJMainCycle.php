<?
use \App\Services\API\MidjourneyAPI;
class MJMainCycle extends MidjourneyAPI {

    protected function updateTask($task) {
        $responses = $this->modelReply->getItems(['processed'=>0, 'hash'=>$task['hash']]);

        foreach ($responses as $response) {
            if ($this->doServiceAction($task, $response)) {
                $this->modelReply->Update([
                    'id'=>$response['id'], 'processed'=>1
                ]);
                break;
            }
        }
    }

    public function Update() {
        $tasks = $this->modelTask->getItems(['state'=>'active']);
        if (count($tasks) > 0) {
            foreach ($tasks as $task) {
                $this->updateTask($task);
            }
        }
    }   

    protected function doServiceAction($task, $response) {
        $params = ['text'=>'Ваше изображение готово!'];

        if (isset($response['result'])) {
        	$result = json_decode($response['result'], true);
        	if (isset($result['url']) && $result['url']) {

        		$isProgress = $response['status'] == 'progress';
				$info = pathinfo($result['filename']);
				$filename = $task['hash'].'.'.$info['extension'];
				
				$file_path = ($isProgress?PROCESS_PATH:RESULT_PATH).$filename;

				if (!file_exists($file_path))
        			downloadFile($result['url'], $file_path);

        		$file_url = ($isProgress?PROCESS_URL:RESULT_URL).$filename;

        		$response = $this->bot->sendPhoto([
				    'chat_id' => $task['chat_id'],
				    'photo' => InputFile::create($file_path, $filename),
				    'caption' => 'Ваше изображение готово!',
				    'parse_mode' => 'HTML'
				]);

				return $response;
        	}
        }

        $this->Message($task['chat_id'], $params);
        return true;
    }

    public function Message($chatId, $msg, $parse_mode = 'Markdown') {

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => $parse_mode
        ], is_string($msg) ? ['text' => $msg] : $msg);

        print_r($params);

        return $this->bot->sendMessage($params);
    }
}