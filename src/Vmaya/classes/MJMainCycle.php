<?
use \App\Services\API\MidjourneyAPI;
use \Telegram\Bot\FileUpload\InputFile;
use \Telegram\Bot\Exceptions\TelegramResponseException;

class MJMainCycle extends MidjourneyAPI {

    private $lastMessageId;

    protected function initLang($language_code) {
        GLOBAL $lang;
        $fileName = LANGUAGE_PATH.$language_code.'.php';
        if (file_exists($fileName))
            include_once($fileName);
    }

    protected function updateTask($task) {
        if ($user = (new TGUserModel())->getItem($task['user_id']))
            $this->initLang($user['language_code']);

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

        if (isset($response['result'])) {
        	$result = json_decode($response['result'], true);
        	if (isset($result['url']) && $result['url']) {

                $hash = $task['hash'];
        		$isProgress = $response['status'] == 'progress';
				$info = pathinfo($result['filename']);
				$filename = $hash.'.'.$info['extension'];

				$file_path = ($isProgress?PROCESS_PATH:RESULT_PATH).$filename;

				if (!file_exists($file_path))
        			downloadFile($result['url'], $file_path);

        		$file_url = ($isProgress?PROCESS_URL:RESULT_URL).$filename;

                if ($this->lastMessageId)
                    $this->bot->deleteMessage([
                        'chat_id' => $task['chat_id'],
                        'message_id' => $this->lastMessageId
                    ]);

                if ($isProgress) {
            		$photoMessage = $this->bot->sendPhoto([
    				    'chat_id' => $task['chat_id'],
    				    'photo' => InputFile::create($file_path, $filename),
    				    'caption' => Lang("Your image in progress"),
    				    'parse_mode' => 'HTML'
    				]);
                    $this->lastMessageId = $photoMessage->getMessageId();
                } else {
                    $photoMessage = $this->bot->sendPhoto([
                        'chat_id' => $task['chat_id'],
                        'photo' => InputFile::create($file_path, $filename),
                        'caption' => Lang('Choose the option you like best'),
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '1', 'callback_data' => "task_{$hash}_1"],
                                    ['text' => '2', 'callback_data' => "task_{$hash}_2"]
                                ],[
                                    ['text' => '3', 'callback_data' => "task_{$hash}_3"],
                                    ['text' => '4', 'callback_data' => "task_{$hash}_4"]
                                ]
                            ]
                        ])
                    ]);
                    $this->lastMessageId = null;
                }

				return $photoMessage->getMessageId() > 0;
        	}
        }
        return false;
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