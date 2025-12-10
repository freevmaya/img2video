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

        if (count($responses) == 0) {
            if (HoursDiffDate($task['date']) > 1) {
                $this->modelTask->Update([
                    'id'=>$task['id'], 'state'=>'failure'
                ]);
            }
        } else {
            foreach ($responses as $response) {
                if ($this->doServiceAction($task, $response)) {
                    $this->modelReply->Update([
                        'id'=>$response['id'], 'processed'=>1
                    ]);

                    if ($response['status'] == 'done') {
                        $this->modelTask->Update([
                            'id'=>$task['id'], 'state'=>'finished'
                        ]);
                    }
                    break;
                }
            }
        }
    }

    protected function doServiceAction($task, $response) {
        if (isset($response['result']) && !empty($response['result'])) {
            $method = $response['type'].'_do';
            if (method_exists($this, $method))
                return $this->$method($task, $response);
            else {
                trace_error("The method is missing: {$method}");
                return false;
            }
        } else return true;
    }

    public function Update() {
        $tasks = $this->modelTask->getItems(['state'=>'active']);
        if (count($tasks) > 0) {
            foreach ($tasks as $task) {
                $this->updateTask($task);
            }
        }
    }

    protected function sendPhoto($chat_id, $file_path, $filename, $caption, $inline_keyboard = null) {
        if (file_exists($file_path)) {

            $params = [
                'chat_id' => $chat_id,
                'photo' => InputFile::create($file_path, $filename),
                'caption' => $caption,
                'parse_mode' => 'HTML'
            ];

            if ($inline_keyboard)
                $params['reply_markup'] = json_encode([
                    'inline_keyboard' => $inline_keyboard
                ]);

            $photoMessage = $this->bot->sendPhoto($params);
            return $photoMessage->getMessageId();
        } else {
            trace_error("File ({$file_path}) is not exists");
            return true;
        }

        return false;
    }

    protected function prepareFile($hash, $path, $result) {
        if (isset($result['url']) && $result['url']) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            $file_path = $path.$filename;

            if (!file_exists($file_path)) {
                $downloadResult = downloadFile($result['url'], $file_path);
                return $downloadResult['success'];
            }

            return true;
        }
        return false;
    }

    protected function upscale_do($task, $response) {
        $result = json_decode($response['result'], true);

        if ($this->prepareFile($task['hash'], RESULT_PATH, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $task['hash'].'.'.$info['extension'];

            if ($result = $this->sendPhoto($task['chat_id'], RESULT_PATH.$filename, $filename, Lang("Your photo is ready"))) {

                (new TransactionsModel())->PayUpscale($task['user_id'], [
                    'response_id'=>$response['id'],
                    'hash'=>$task['hash']
                ]);
            }

            return $result;
        }
        return false;
    }

    protected function imagine_do($task, $response) {

        $result = json_decode($response['result'], true);
        $isProgress = $response['status'] == 'progress';
        $path = $isProgress?PROCESS_PATH:RESULT_PATH;

        $hash = $task['hash'];

        if ($this->prepareFile($hash, $path, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            $file_path = $path.$filename;

            if (is_numeric($this->lastMessageId))
                $this->bot->deleteMessage([
                    'chat_id' => $task['chat_id'],
                    'message_id' => $this->lastMessageId
                ]);

            if ($isProgress) {

                $result = $this->lastMessageId = $this->sendPhoto($task['chat_id'], $file_path, $filename, Lang("Your image in progress"));
            } else {

                $result = $this->sendPhoto($task['chat_id'], $file_path, $filename, Lang('Choose the option you like best'),
                    [
                        [
                            ['text' => '1', 'callback_data' => "task.{$hash}.1"],
                            ['text' => '2', 'callback_data' => "task.{$hash}.2"]
                        ],[
                            ['text' => '3', 'callback_data' => "task.{$hash}.3"],
                            ['text' => '4', 'callback_data' => "task.{$hash}.4"]
                        ]
                    ]
                );
                if ($result)
                    $this->lastMessageId = null;
            }
            return $result;
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