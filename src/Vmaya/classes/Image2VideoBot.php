<?

/*
Команды

text2image - текст в картинку
text2video - текст в видео
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
                break;
            default:
                parent::commandProcess($command, $chatId, $messageId, $text);
                break;
        }
    }

    protected function start($chatId) {
        $keyboard = $this->subscribeTypeList();

        $keyboard[] = [['text' => Lang("For free"), 'callback_data' => 'subscribe-0']];

        $this->Answer($chatId, ['text' => Lang("BotDescription"), 'reply_markup'=> json_encode([
            'inline_keyboard' => $keyboard
        ])]);
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