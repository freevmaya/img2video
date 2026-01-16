<?

use \Telegram\Bot\FileUpload\InputFile;
use \Telegram\Bot\Exceptions\TelegramResponseException;

class SenderCycle extends TelegramClient {

    protected $notificationsModel;

    public function __construct($api, $file_settings = null)
    {
        parent::__construct($api, $file_settings);
        $this->notificationsModel = new NotificationsModel();
    }

    public function Update() {

        try {

            $notifications = $this->notificationsModel->getItems('`processed`= 0 AND `submit_time` < NOW()');
            foreach ($notifications as $item)
                $this->runNotification($item);

        } catch (Exception $e) {
            trace_error($e->getMessage());
        }
    }

    public function runNotification($notification) {

        $chats_ids = $notification['chats_ids'] ? json_decode($notification['chats_ids'], true) : [];
        $sent_chat_ids = $notification['sent_chat_ids'] ? json_decode($notification['sent_chat_ids'], true) : [];
        $error_chat_ids = $notification['error_chat_ids'] ? json_decode($notification['error_chat_ids'], true) : [];
        $last_chat_ids = $this->getLastErrorsIds();

        $userModel = new TGUserModel();

        if ((count($chats_ids) > 0) && ($chats_ids[0] == '*'))
            $chats_ids = BaseModel::getListValues((new TGUserModel())->getItems(), 'id');

        $chats_ids = array_values(array_diff($chats_ids, $sent_chat_ids, $error_chat_ids, $last_chat_ids));

        if (($presetName = $notification['preset_name']) && 
            ($preset = $this->getPreset($presetName))) {

            $count = count($chats_ids);
            if ($count > 0) {

                $chat_id = $chats_ids[0];

                if ($user = $userModel->getItem($chat_id))
                	$this->initLang($user['language_code']);

                if (isset($preset['image'])) {

	                $fileName = basename($preset['image']);

	                $sendResult = $this->sendPhoto($chat_id, BASEPATH.$preset['image'], $fileName, $preset['caption'], [
	                    [['text'=>Lang('Begin'), 'callback_data' => "runPreset.{$presetName}"],
	                    $this->closeMessageButton()]
	                ]);

                } else if (isset($preset['video'])) {

                	$sendResult = $this->sendMp4($chat_id, BASEPATH.$preset['video'], basename($preset['video']), $preset['caption'], [
	                    [['text'=>Lang('Begin'), 'callback_data' => "runPreset.{$presetName}"],
	                    $this->closeMessageButton()]
	                ]);
                }

                if ($sendResult) {

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
}
?>