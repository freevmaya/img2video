<?

/*
Команды

text2image - текст в картинку
text2video - текст в видео
subscribe - подписка

*/

class Image2VideoBot extends YKassaBot {

    protected function callbackProcess($callback, $chatId, $messageId, $data) {
        parent::callbackProcess($callback, $chatId, $messageId, $data);
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


    protected function messageProcess($chatId, $messageId, $text) {
        switch ($this->popSession("expect")) {
            case 'photo':
                if ($photo = @$this->currentUpdate['message']['photo']) {
                    $best_photo = $photo[count($photo) - 1];
                    $file_url = $this->GetFileUrl($best_photo['file_id']);
                    trace($file_url);
                }
                break;
            default:
                parent::messageProcess($chatId, $messageId, $text);
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

    protected function text2image($chatId) {
        $this->Answer($chatId, ['text' => Lang("Send a photo")]);
        $this->setSession("expect", 'photo');
    }

    protected function text2video($chatId) {
        $this->Answer($chatId, ['text' => Lang("Send a photo")]);
        $this->setSession("expect", 'photo');
    }
}
?>