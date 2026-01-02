<?

use \Telegram\Bot\FileUpload\InputFile;
use \Telegram\Bot\Exceptions\TelegramResponseException;
use App\Services\API\cycle\MjCycle;
use App\Services\API\cycle\KlingCycle;
use App\Services\API\cycle\LeoCycle;

class MainCycleEx {

    private $lastMessageId;
    private $currentLanguage;
    protected $user;
    protected $modelTask;
    protected $api;
    protected $processors;
    protected $transactionModel;
    public $downloadClient;

    public function __construct($api)
    {
        $this->api          = $api;
        $this->modelTask    = new TaskModel();
        $this->transactionModel = new TransactionsModel();

        $this->processors 	= [
        	'mj' => new MjCycle($this, $this->modelTask, new MJModel()),
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
        $tasks = $this->modelTask->getItems(['state'=>'active']);
        if (count($tasks) > 0) {
            foreach ($tasks as $task) {
                $this->updateTask($task);
            }
        }

        $this->downloadClient->Run();
    }

    protected function updateTask($task) {
        if ($this->user = (new TGUserModel())->getItem($task['user_id']))
            $this->initLang($this->user['language_code']);

        
    	foreach ($this->processors as $key=>$processor)
    		if ($key == $task['service'])
    			$processor->doServiceAction($task);
    }

    public function finishTask($task, $state='finished') {        
        $this->modelTask->Update([
            'id'=>$task['id'], 'state'=>$state
        ]);

        trace("finish task {$task['id']}: {$state}");
    }

    public function sendMp4($task, $filePath, $filename, $message, $params=[]) {
        if (!$filePath || !file_exists($filePath)) {
            $this->Message($task['chat_id'], '⚠️ '.Lang('Animation not found'));
            return;
        }

        try {  

            return $this->api->sendVideo([
                'chat_id' => $task['chat_id'],
                'video' => fopen($filePath, 'r'),
                'caption' => $message,
                'width' => 512,
                'height' => 512,
                'supports_streaming' => true
            ]);
        } catch (Exception $e) {
            trace_error($e->getMessage());
        }

        return false;
    }

    public function sendPhoto($chat_id, $file_path, $filename, $caption, $inline_keyboard = null) {
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

            try {
                $photoMessage = $this->api->sendPhoto($params);
                return $photoMessage->getMessageId();
            } catch (Exception $e) {
                trace_error($e.getMessage());
            }
        } else {
            trace_error("File ({$file_path}) is not exists");
            return true;
        }

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

    public function Message($chatId, $msg, $parse_mode = 'Markdown') {

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => $parse_mode
        ], is_string($msg) ? ['text' => $msg] : $msg);

        return $this->api->sendMessage($params);
    }
}