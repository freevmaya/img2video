<?

use \Telegram\Bot\FileUpload\InputFile;
use \Telegram\Bot\Exceptions\TelegramResponseException;
use App\Services\API\cycle\MjCycle;
use App\Services\API\cycle\KlingCycle;
use App\Services\API\cycle\LeoCycle;

class MainCycleEx extends SettingsManager {

    private $lastMessageId;
    private $currentLanguage;
    private $notificationsModel;
    protected $user;
    protected $modelTask;
    protected $api;
    protected $processors;
    protected $transactionModel;
    public $downloadClient;

    public function __construct($api, $file_settings = null)
    {
        parent::__construct($file_settings);
        $this->api          = $api;
        $this->modelTask    = new TaskModel();
        $this->transactionModel = new TransactionsModel();
        $this->notificationsModel = new NotificationsModel();

        $this->processors 	= [
        	//'mj' => new MjCycle($this, $this->modelTask, new MJModel()),
        	'kling' => new KlingCycle($this, $this->modelTask, new KlingModel()),
            'leo' => new LeoCycle($this, $this->modelTask, new LeoTasksModel())
        ];

        $this->downloadClient = new DownloadClient();
    }

    protected function initLang($language_code) {
        GLOBAL $lang;
        if ($this->currentLanguage != $language_code) {
            $fileName = LANGUAGE_PATH.$language_code.'.php';
            if (file_exists($fileName)) {
                include($fileName);
                $this->currentLanguage = $language_code;
            }
        }
    }

    public function Update() {

        try {
            $tasks = $this->modelTask->getItems(['state'=>'active']);
            if (count($tasks) > 0) {
                foreach ($tasks as $task) {
                    $this->updateTask($task);
                }
            }

            $notifications = $this->notificationsModel->getItems('`processed`= 0 AND `submit_time` < NOW()');

            foreach ($notifications as $item)
                $this->runNotification($item);

        } catch (Exception $e) {
            trace_error($e->getMessage());
        }

        $this->downloadClient->Run();
    }

    protected function updateTask($task) {
        if ($this->user = (new TGUserModel())->getItem($task['user_id'])) {
            $this->initLang($this->user['language_code']);
            $this->readSession($task['chat_id']);
            
            $idDo = false;
            foreach ($this->processors as $key=>$processor)
                if ($key == $task['service']) {
                    $processor->doServiceAction($task);
                    $idDo = true;
                    break;
                }

            if (!$idDo) $this->finishTask($task, 'failure');

            if ($this->isSessionChanged()) $this->saveSession();
        }
    }

    public function finishTask($task, $state='finished') {        
        $this->modelTask->Update([
            'id'=>$task['id'], 'state'=>$state
        ]);

        trace("finish task {$task['id']}: {$state}");
    }

    public function finishNotification($id, $code = 1) {
        $this->notificationsModel->Update([
            'id'=>$id,
            'processed'=>$code
        ]);
    }

    protected function getLastErrorsIds() {
        $result = [];
        $errorItems = $this->notificationsModel->getItems('`error_chat_ids` IS NOT NULL AND `processed`= 1');
        foreach ($errorItems as $item) {
            $error_chat_ids = array_values(json_decode($item['error_chat_ids']));
            $result = array_merge($result, $error_chat_ids);
        }
        return array_unique($result);
    }

    public function runNotification($notification) {

        $chats_ids = $notification['chats_ids'] ? json_decode($notification['chats_ids'], true) : [];
        $sent_chat_ids = $notification['sent_chat_ids'] ? json_decode($notification['sent_chat_ids'], true) : [];
        $error_chat_ids = $notification['error_chat_ids'] ? json_decode($notification['error_chat_ids'], true) : [];

        $last_chat_ids = $this->getLastErrorsIds();

        if ((count($chats_ids) > 0) && ($chats_ids[0] == '*'))
            $chats_ids = BaseModel::getListValues((new TGUserModel())->getItems(), 'id');

        $chats_ids = array_values(array_diff($chats_ids, $sent_chat_ids, $error_chat_ids, $last_chat_ids));

        if (($presetName = $notification['preset_name']) && 
            ($preset = $this->getPreset($presetName)) && 
            $preset['image']) {

            $count = count($chats_ids);
            if ($count > 0) {
                $chat_id = $chats_ids[0];
                $fileName = basename($preset['image']);

                if ($this->sendPhoto($chat_id, BASEPATH.$preset['image'], $fileName, $preset['caption'], [
                    [['text'=>Lang('Begin'), 'callback_data' => "runPreset.{$presetName}"],
                    $this->closeMessageButton()]
                ])) {

                    $sent_chat_ids[] = $chat_id;
                    $this->notificationsModel->Update([
                        'id'=>$notification['id'],
                        'sent_chat_ids' => json_encode($sent_chat_ids, JSON_FLAGS)
                    ]);

                } else {
                    
                    $error_chat_ids[] = $chat_id;
                    $this->notificationsModel->Update([
                        'id'=>$notification['id'],
                        'error_chat_ids' => json_encode($error_chat_ids, JSON_FLAGS)
                    ]);
                }

            } else $this->finishNotification($notification['id'], 1);
        } else $this->finishNotification($notification['id'], 2);
    }

    public function sendMp4($task, $filePath, $filename, $message, $params=[]) {
        if (!$filePath || !file_exists($filePath)) {
            $this->Message($task['chat_id'], '⚠️ '.Lang('Animation not found'));
            return;
        }

        $max_atempt = 3;
        $attempt_count = 0;
        $error_message = '';

        while ($attempt_count < $max_atempt) {

            sleep(round(pow($attempt_count, 1.5) * 3));
            try {

                $message = $this->afterSend($this->api->sendVideo([
                    'chat_id' => $task['chat_id'],
                    'video' => fopen($filePath, 'r'),
                    'caption' => $message,
                    'width' => 512,
                    'height' => 512,
                    'supports_streaming' => true
                ]), true);
                if ($message->getMessageId()) 
                    return true;

            } catch (Exception $e) {
                $error_message = $e->getMessage();
            }
            $attempt_count++;
        }

        trace_error("Failed to send mp4 to chatId: {$task['chat_id']}\n\nError: {$error_message}");

        return false;
    }

    public function sendPhoto($chat_id, $file_path, $filename, $caption, $inline_keyboard = null) {
            
        $error_message = '';
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

            $max_atempt = 3;
            $attempt_count = 0;

            while ($attempt_count < $max_atempt) {
                try {

                    sleep(round(pow($attempt_count, 1.5) * 3));
                    $photoMessage = $this->afterSend($this->api->sendPhoto($params), true);
                    if ($photoMessage->getMessageId()) {
                        return true;
                    }

                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
                $attempt_count++;
            }
        } else {
            trace_error("File ({$file_path}) is not exists");
            return true;
        }

        trace_error("Failed to send image to chatId: {$chat_id}\n\nError: {$error_message}");
        return false;
    }

    public function Message($chatId, $msg, $parse_mode = 'Markdown') {

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => $parse_mode
        ], is_string($msg) ? ['text' => $msg] : $msg);

        $max_atempt = 3;
        $attempt_count = 0;
        $error_message = '';

        while ($attempt_count < $max_atempt) {

            sleep(round(pow($attempt_count, 1.5) * 3));
            try {

                $message = $this->afterSend($this->api->sendMessage($params), true);
                if ($message->getMessageId())
                    return true;

            } catch (Exception $e) {
                $error_message = $e->getMessage();
            }
            $attempt_count++;
        }

        trace_error("Failed to send message to chatId: {$chatId}\n\nError: {$error_message}");
        return false;
    }

    public function PayUpscale($user_id, $data) {
        $this->transactionModel->PayUpscale($user_id, $data);
    }

    public function PayVideo($user_id, $data) {
        $this->transactionModel->PayVideo($user_id, $data);
    }

    public function error($error) {
        $this->Message(ADMIN_USERID, $error);
    }
}