<?

/*

*/

abstract class MLServiceBot extends BaseBot {

    protected function replyToMessage($reply, $chatId, $messageId, $text) {

        if (MLSERVER && ($reply['from']['username'] == BOTALIASE)) {
            $this->mlDialog($chatId, $messageId, $text);
        } else $this->messageProcess($chatId, $messageId, $text);
    }


    protected function messageProcess($chatId, $messageId, $text) {
        $this->mlProcess($chatId, $messageId, $text);
    }

    protected function mlText($response) {
        return @$response['choices']['message'];
    }

    protected function mlDialog($chatId, $messageId, $text) {
        $user_id = $this->getUser()['id'];
        $observer_chat_id = 'observer_chat';

        $result = $this->MLQuery($text, "Отвечай на русском языке. Коротко.", $observer_chat_id);
        $text = $this->mlText($result);

        if ($text)
            $this->Answer($chatId, $text, false, $messageId);
    }

    protected function mlProcess($chatId, $messageId, $text) {

        $user_id = $this->getUser()['id'];
        $promt = html::RenderFile(TEMPLATES_PATH.'observer_promt2.php');

        $result = $this->MLQuery($text, $promt);

        trace($result);
        $response = $this->mlText($result);

        if ($response) {            
            if (DEV) $this->Answer($chatId, $response);
        }
    }
}
?>