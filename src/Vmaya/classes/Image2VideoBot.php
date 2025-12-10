<?

/*
Команды

menu - главное меню
subscribe - подписка

*/

use \App\Services\API\MidjourneyAPI;

class Image2VideoBot extends YKassaBot {

    protected $serviceApi;
    protected $expect;

    protected function initUser($update) {
        parent::initUser($update);
        if ($this->getUserId())
            $this->serviceApi = new MidjourneyAPI(MJ_APIKEY, MJ_HOOK_URL, MJ_ACCOUNTHASH, 
                                    $this, new TaskModel(), new MJModel());
    }

    protected function runUpdate($update) {
        $this->expect = $this->popSession("expect");
        parent::runUpdate($update);
    }

    protected function startMenuList() {
        $result = [
            [['text' => '🖼️ Создать изображение', 'callback_data' => 'create_image']],
            [['text' => '🎥 Создать видео', 'callback_data' => 'create_video']],
            [['text' => '💰 Баланс', 'callback_data' => 'MySubscribe']],
            [['text' => '📊 Мои генерации', 'callback_data' => 'my_generations']],
            [['text' => 'Подписка', 'callback_data' => 'subscribe']]
        ];

        if ($this->getUserId() == ADMIN_USERID)
            $result[] = [['text' => 'Остановить', 'callback_data' => 'stopBot']];

        return $result;
    }

    protected function callbackProcess($callback, $chatId, $messageId, $data) {

        switch ($data) {
            case 'create_image':
                if ($this->isAllowedImage())
                    $this->text2image($chatId);
                else $this->notEnough($chatId);
                return true;
            case 'create_video':
                if ($this->isAllowedVideo())
                    $this->text2video($chatId);
                else $this->notEnough($chatId);
                return true;
            case 'stopBot':
                $this->stopBot($chatId);
                return true;
            default: 
                return parent::callbackProcess($callback, $chatId, $messageId, $data);
        }
    }

    protected function messageProcess($chatId, $messageId, $text) {

        if ($expect = $this->expect) {
            if (method_exists($this, $expect)) {
                $this->$expect($chatId, $text);
            }
            else {
                switch ($expect) {
                    case 'photo':
                        if ($photo = @$this->currentUpdate['message']['photo']) {
                            $best_photo = $photo[count($photo) - 1];
                            $file_url = $this->GetFileUrl($best_photo['file_id']);
                            trace($file_url);
                        }
                        break;
                    default:
                        break;

                }
            }
        }
    }

    protected function replyToMessage($reply, $chatId, $messageId, $text) {
        $this->messageProcess($chatId, $messageId, $text);
    }

    protected function commandProcess($command, $chatId, $messageId, $text) {
        switch ($command) {
            case '/start':
                //$this->DeleteMessage($chatId, $messageId);
                $this->start($chatId);
                break;
            case '/menu':
                //$this->DeleteMessage($chatId, $messageId);
                $this->showMainMenu($chatId);
                break;
                /*
            case '/text2image':
                //$this->DeleteMessage($chatId, $messageId);
                if ($this->isAllowedImage())
                    $this->text2image($chatId);
                else $this->notEnough($chatId);
                break;
            case '/text2video':
                //$this->DeleteMessage($chatId, $messageId);
                if ($this->isAllowedVideo())
                    $this->text2video($chatId);
                else $this->notEnough($chatId);
                break;*/
            default:
                parent::commandProcess($command, $chatId, $messageId, $text);
                break;
        }
    }

    protected function showMainMenu($chatId) {        
        $this->Answer($chatId, [
            'text' => 'Выберите действие:',
            'reply_markup' => json_encode(['inline_keyboard' => $this->startMenuList()])
        ]);
    }

    protected function start($chatId) {
        $keyboard = array_merge($this->startMenuList(), $this->subscribeTypeList());

        $keyboard[] = [['text' => Lang("For free"), 'callback_data' => 'subscribe-0']];

        $this->Answer($chatId, ['text' => Lang("BotDescription"), 'reply_markup'=> json_encode([
            'inline_keyboard' => $keyboard
        ])]);
    }

    protected function stopBot($chatId) {
        GLOBAL $lock;
        if ($lock) {
            $msg = $lock->release() ? 'Successful stop' : 'Failure stop';
            $this->Answer($chatId, ['text' => Lang($msg)]);
        }
    }

    protected function textToImage($chatId, $prompt) {
        $this->Answer($chatId, ['text' => "Prompt: ".$prompt]);
        $this->serviceApi->generateImage($prompt);
    }

    protected function textToVideo($chatId, $prompt) {
        $this->Answer($chatId, ['text' => "Prompt: ".$prompt]);
    }

    protected function text2image($chatId) {
        $this->Answer($chatId, ['text' => Lang("Send a prompt")]);
        $this->setSession("expect", 'textToImage');
    }

    protected function text2video($chatId) {
        $this->Answer($chatId, ['text' => Lang("Send a prompt")]);
        $this->setSession("expect", 'textToVideo');
    }
}
?>