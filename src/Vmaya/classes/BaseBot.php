<?
use GuzzleHttp\Client;
use Telegram\Bot\HttpClients\GuzzleHttpClient;
use \Telegram\Bot\FileUpload\InputFile;

abstract class BaseBot extends SettingsManager {
    private $origin_user_id;
    private $reply_to_message;
    private $chatId;

    protected $user;
    protected $api;
    protected $currentUpdate = null;
    protected $currentLanguage;

    public function getUser() { return $this->user; }
    public function getUserId() { return $this->user ? $this->user['id'] : null; }
    public function getOriginUserId() { return $this->origin_user_id; }
    public function getReplyToMessage() { return $this->reply_to_message; }

	function __construct($api, $file_settings = null) {
        $this->api = $api;
        parent::__construct($file_settings);
    }

    public function setSettingsAll($settings = null) {
        parent::setSettingsAll($settings);

        if ($client_timeout = $this->getSetting('client_timeout', 10))
            $this->setTimeout($client_timeout);
    }

    protected function setTimeout($client_timeout = 10) {

        if (DEV)
            echo "setTimeout {$client_timeout}\n";

        $connect_timeout = round($client_timeout + $client_timeout * 0.1);
        $guzzleClient = new Client([
            'timeout' => $client_timeout,
            'connect_timeout' => $connect_timeout,
            'read_timeout' => $client_timeout,
            'curl' => [
                CURLOPT_TIMEOUT => $client_timeout,
                CURLOPT_CONNECTTIMEOUT => $connect_timeout/*,
                CURLOPT_LOW_SPEED_LIMIT => 1024,
                CURLOPT_LOW_SPEED_TIME => 300,*/
            ],
        ]);

        $httpClient = new GuzzleHttpClient($guzzleClient);
        $this->api->setHttpClientHandler($httpClient);
    }

    public function Api() {
        return $this->api;
    }

    public static function getUserLink($userId, $userName) {
        $escapedName = str_replace(['_', '*'], ['\\_', '\\*'], $userName);
        return "[{$escapedName}](tg://user?id={$userId})";
    }

    private function _callbackProcess() {

        $callback = $this->currentUpdate['callback_query'];
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $callback_data = cnvBase64($callback['data']); // Здесь содержится ваш callback_data
        
        // 1. Ответим на callback (убирает "часики" у кнопки)
        $this->api->answerCallbackQuery([
            'callback_query_id' => $callback['id'],
            'text' => 'Обрабатываю ваш выбор...'
        ]);

        return $this->callbackProcess($callback, $chatId, $messageId, $callback_data);
    }

    protected abstract function callbackProcess($callback, $chatId, $messageId, $callback_data);
    protected abstract function commandProcess($command, $chatId, $messageId, $text);
    protected abstract function replyToMessage($reply, $chatId, $messageId, $text);
    protected abstract function messageProcess($chatId, $messageId, $data);

    protected function stat($userId, $type, $data = null) {
        if ($userId != ADMIN_USERID)
            StatisticModel::trace($type, $data);
    }

    public function CurrentUpdate() {
        return $this->currentUpdate;
    }

    public function unsetSessions($names) {
        if ($this->currentUpdate) 
            parent::unsetSessions($names);
    }

    public function popSession($name) {

        $result = null;
        if ($this->currentUpdate)
            $result = parent::popSession($name);

        return $result;
    }

    public function DeleteMessageByIndex($message_index=null) {
        if ($message_index)
            $this->DeleteMessage(null, $this->getMessageId($message_index));
    }

    public function LastBotMessageId() {
        return $this->getSession('lastBotMessageId');
    }

    public function DeleteMessage($chatId=null, $message_id=null) {
        if (empty($message_id))
            $message_id = $this->LastBotMessageId();
        if (empty($chatId))
            $chatId = $this->getCurrentChatId();

        if (!empty($message_id) && !empty($chatId)) {
            try {
                $this->api->deleteMessage([ 'chat_id' => $chatId, 'message_id' => $message_id]); 
            } catch (Exception $e) {
                trace_error($e->getMessage());
            }
        }
    }

    public function PrivateAnswerAndDelete($user_id, $chatId, $private_text, $temporary_text, $wait_sec = 6) {
        $this->Answer($user_id, $private_text);

        if ($user_id != $chatId)
            $this->AnswerAndDelete($chatId, $temporary_text."\n(Перейти в [личные сообщения](https://t.me/".BOTALIASE."))", $wait_sec);
    }

    public function AnswerAndDelete($chatId, $text, $wait_sec = 6) {
        $msg = $this->Answer($chatId, $text."\n(Закроется через $wait_sec сек.)");
        if (isset($msg["message_id"])) {
            sleep($wait_sec);
            $this->DeleteMessage($chatId, $msg["message_id"]);
        }
    }

    public function Wrong($chatIdOrMessage = null, $messageId = false) {

        if (is_string($chatIdOrMessage)) {
            $text = $chatIdOrMessage;
            $chatIdOrMessage = null;
        } else $text = Lang("Something wrong");

        $this->Answer($chatIdOrMessage, ['text' => $text, 'reply_markup'=> [
                'inline_keyboard' => [
                    [
                        ['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support'],
                        $this->closeMessageButton()
                    ]
                ]
            ]
        ], $messageId);
    }

    public function getCurrentChatId() {
        if ($this->chatId)
            return $this->chatId;
        else {
            if ($this->currentUpdate && $this->currentUpdate->getMessage()) {
                try {
                    return $this->currentUpdate->getMessage()->getChat()->getId();
                } catch (Exception $e) {
                    return $this->getUserId();
                }
            } else return $this->getUserId();
        }
    }

    public function Answer($chatId, $msg, $messageEditId = false, $reply_to_message_id = false, $parse_mode = 'Markdown') {

        if (empty($chatId))
            $chatId = $this->getCurrentChatId();

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => $parse_mode
        ], is_string($msg) ? ['text' => $msg] : $msg);


        $message = $this->currentUpdate->getMessage();

        if ($reply_to_message_id)
            $params['reply_to_message_id'] = $reply_to_message_id;
        else if (isset($message['message_thread_id'])) {

            if ($this->reply_to_message && ($this->reply_to_message['message_id'] == $message['message_thread_id']))
                 $params['reply_to_message_id'] = $message['message_thread_id'];
            else $params['message_thread_id'] = $message['message_thread_id'];
        }

        $params = encodeTelegramParams($params);

        if ($messageEditId) {
            $params['message_id'] = $messageEditId;
            $params = encodeTelegramParams($params);
            $result = $this->afterSend($this->api->editMessageText($params));
        } else $result = $this->sendMessage($params);

        return $result;
    }

    public function SendPhoto($caption, $photoPathOrUrl, $buttons = null, $parse_mode = 'Markdown', $asFileId = false) {
        $chatId = $this->getCurrentChatId();

        $params = array_merge([
            'chat_id' => $chatId,
            'photo' => $asFileId || isUrl($photoPathOrUrl) ? $photoPathOrUrl : InputFile::create($photoPathOrUrl),
            'caption' => $caption,
            'parse_mode' => $parse_mode
        ], is_string($caption) ? ['caption' => $caption] : $caption);

        if ($buttons && is_array($buttons) && (count($buttons) > 0))
            $params['reply_markup'] = ['inline_keyboard' => $buttons];


        $params = encodeTelegramParams($params);
        return $this->afterSend($this->api->sendPhoto($params));
    }

    public function SendVideo($caption, $videoPathOrId, $buttons = null, $parse_mode = 'Markdown', $asFileId = false) {
        $chatId = $this->getCurrentChatId();

        if ($asFileId || file_exists($videoPathOrId)) {
            $params = array_merge([
                'chat_id' => $chatId,
                'video' => $asFileId ? $videoPathOrId : InputFile::create($videoPathOrId),
                'caption' => $caption,
                'supports_streaming' => true
            ], is_string($caption) ? ['caption' => $caption] : $caption);

            if ($buttons && is_array($buttons) && (count($buttons) > 0))
                $params['reply_markup'] = ['inline_keyboard' => $buttons];


            $params = encodeTelegramParams($params);
            return $this->afterSend($this->api->sendVideo($params));
        } else {
            trace_error("File {$videoPathOrId} not found");
            return false;
        }
    }

    public function getMessageId($messageIndex) {
        if (($history = $this->getSession('history')) &&
            isset($history[$messageIndex])) {

            if (DEV) echo "MessageID: {$history[$messageIndex]}\n";
            return $history[$messageIndex];
        }
        return null;
    }

    public function pushRecallMethod($messageIndex, $eval) {
        $recall = $this->getSession('recall', []);
        array_add_limit($recall, $messageIndex, $eval, 5);
        $this->setSession('recall', $recall);
    }

    public function recall($messageIndex) {
        $recall = $this->getSession('recall', []);
        if (isset($recall[$messageIndex])) {
            $eval = "\$this->{$recall[$messageIndex]};";
            if (DEV) echo "$eval\n";
            eval($eval);
            return true;
        }
        return false;
    }

    public function popMessageHistory() {
        $history = $this->getSession('history', []);
        if (count($history) > 0) {
            $result = array_pop($history);
            $this->setSession('history', $history);
            return $result;
        }
        return null;
    }

    public function DeleteMessages($count = 1) {
        
        for ($i=0; $i<$count; $i++) {
            if ($messageId = $this->popMessageHistory())
                $this->DeleteMessage(null, $messageId);
        }
    }

    public function sendMessage($params) {

        $params = encodeTelegramParams($params);
        return $this->afterSend($this->api->sendMessage($params));
    }

    public function createButton($eng_caption, $command) {
        return ['text'=>Lang($eng_caption), 'callback_data' => $command];
    }

    /*
    protected function getReplyToMessage() {
        $message = $this->currentUpdate->getMessage();
    }*/

    public function findUpdateBlock($update) {

        $fields = [
            'message',
            'callback_query',
            'inline_query',
            'chosen_inline_result',
            'channel_post',
            'pre_checkout_query',
            'edited_message',
            'response',
            'my_chat_member',
            'edited_channel_post',
            'shipping_query',
            'poll',
            'poll_answer',
            'chat_member',
            'chat_join_request'
        ];

        $block = null;
        foreach ($fields as $field)
            if (isset($update[$field])) {
                return $update[$field];
            }

        return false;
    }

    protected function initUser($update) {
        
        $user = null;
        $this->chatId = 0;

        if ($block = $this->findUpdateBlock($update))
            $user = $block['from'];

        if ($user) {
            try {
                $this->chatId = isset($block['chat']) ? $block['chat']['id'] : @$block['message']['chat']['id'];
                if (empty($this->chatId)) $this->chatId = $user['id'];

                $this->origin_user_id = $user['id'];

                if ($this->origin_user_id == ADMIN_USERID)
                    $user = $this->initAdmin($user, $update);

                $this->user = (new TGUserModel())->checkAndAdd($user);

                $this->initLang($this->user['language_code']);

                if (isset($this->user['is_new'])) 
                    $this->doNewUser();
                return true;
            } catch (Exception $e) {
                $this->trace_error($e->getMessage(), $update);
            }

        } else $this->trace_error("User block not found!", $update);

        return false;
    }

    protected function doNewUser() {

    }

    protected function initAdmin($user, $update) {
        return $user;
    }

    protected function trace_error($error, $data) {
        $data['error'] = $error;
        trace_error($data);
    }

    protected function initLang($language_code) {
        GLOBAL $lang;
        if ($this->currentLanguage != $language_code) {
            $fileName = LANGUAGE_PATH.$language_code.'.php';
            if (file_exists($fileName)) {
                $this->currentLanguage = $language_code;
                include($fileName);
            }
        }
    }

    private function sendImmediateHttpResponse() {

        // КРИТИЧЕСКИ ВАЖНО: отвечаем в течение 1 секунды
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'received']);
        
        // Принудительно отправляем ответ
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
            ob_flush();
        }
    }

    private function processUpdate($update) {

        if (!$this->notUserUpdate($update) && 
            $this->initUser($update)) 
                $this->_runUpdate($update);
    }

    public function GetUpdates() {

        try {
            $this->checkAndUpdateSettings();
            
            //Получаем обновления с учетом последнего обработанного ID
            $updates = $this->api->getUpdates([
                'offset' => $this->getSetting('lastUpdateId', 0) + 1,
                'timeout' => $this->getSetting('update_timeout', 0), // Длительность ожидания новых сообщений (сек)
            ]);
        } catch (Exception $e) {
            trace_error($e->getMessage());
            usleep(500000); // Пауза перед повторной попыткой
            return;
        }

        //Обрабатываем каждое обновление
        foreach ($updates as $update) 
            if ($update) {
                $this->setSetting('lastUpdateId', $update->getUpdateId());

                if (DEV || $this->getSetting('log'))
                    trace($update);
                if (DEV)
                    $this->processUpdate($update);
                else {
                    try {
                        $this->processUpdate($update);
                    } catch (Exception $e) {
                        trace_error($e->getMessage()."\n\nUpdate:\n".json_encode($update, JSON_FLAGS));
                    }
                }
            }

        if ($this->settingsChange)
            $this->saveSettings();
    }

    protected function beforeProcess($chatId, $text) {
        return true;
    }

    protected function runUpdate($update) {

        if ($message = $update->getMessage()) {
            $chat = $update->getChat();

            if ($chat) {
                $chatId     = $chat->getId();
                $messageId  = $message->getMessageId();
                $text       = $message->getText();

                if ($this->beforeProcess($chatId, $text)) {

                    $this->reply_to_message = isset($message['reply_to_message']) ? $message['reply_to_message'] : null;

                    if ($text && ($text[0] == '/')) {
                        $ctext = explode('@', $text);

                        if (!isset($ctext[1]) || ($ctext[1] == BOTALIASE))
                            $this->commandProcess($ctext[0], $chatId, $messageId, $text);
                    }
                    else if (isset($update['callback_query'])) 
                        $this->_callbackProcess();
                    else if ($this->reply_to_message)
                        $this->replyToMessage($this->reply_to_message, $chatId, $messageId, $text);
                    else $this->messageProcess($chatId, $messageId, $text);
                }
            }
        } else {
            trace_error("Message is null");
        }
    }

    private function _runUpdate($update) {

        $this->currentUpdate = $update;

        $chatId = $this->getCurrentChatId();
        $this->readSession($chatId);
        $this->runUpdate($update);

        if ($this->isSessionChanged()) $this->saveSession();
    }

    protected function notUserUpdate($update) {

        if (!isset($update['my_chat_member'])) {
            return false;
        }
    
        $chatMember = $update['my_chat_member'];

        // Извлекаем данные
        $chat = $chatMember['chat'];
        $from = $chatMember['from'];
        $oldChatMember = $chatMember['old_chat_member'];
        $newChatMember = $chatMember['new_chat_member'];
        
        $oldStatus = $oldChatMember['status'];
        $newStatus = $newChatMember['status'];
        
        $date = $chatMember['date'];

        //$this->SendToOwnerChangeStatus($chat, $from, $oldStatus, $newStatus);

        return true;
    }

    public function SendToOwnerChangeStatus($chat, $from, $oldStatus, $newStatus) {
        
        $chatType = $chat['type']; // private, group, supergroup, channel
        $chatName = $chat['title'] ?? $chat['username'] ?? 'Личные сообщения';
        
        $statusNames = [
            'creator' => 'Создатель',
            'administrator' => 'Администратор',
            'member' => 'Участник',
            'restricted' => 'Ограниченный',
            'left' => 'Покинул',
            'kicked' => 'Исключен',
        ];
        
        $oldStatusName = $statusNames[$oldStatus] ?? $oldStatus;
        $newStatusName = $statusNames[$newStatus] ?? $newStatus;
        
        $message = "📢 *Изменение статуса бота*\n\n";
        $message .= "*Чат:* $chatName\n";
        $message .= "*Тип:* $chatType\n";
        $message .= "*Пользователь:* {$from['first_name']}\n";
        $message .= "*ID пользователя:* `{$from['id']}`\n";
        $message .= "*ID чата:* `{$chat['id']}`\n";
        $message .= "*Изменения:* $oldStatusName → $newStatusName\n";
        
        $chageType = '';

        switch ("$oldStatus:$newStatus") {
            // Бота добавили в чат
            case 'left:member':
            case 'kicked:member':
            case 'left:administrator':
            case 'kicked:administrator':
                $chageType = "Added";
                break;
                
            // Бота сделали администратором
            case 'member:administrator':
                $chageType = "Set admin";
                break;
                
            // Бота удалили из администраторов
            case 'administrator:member':
                $chageType = "Remove admin";
                break;
                
            // Бота исключили из чата
            case 'member:left':
            case 'administrator:left':
            case 'member:kicked':
            case 'administrator:kicked':
                $chageType = "Removed";
                break;
                
            // Бот сам покинул чат
            case 'member:left':
            case 'administrator:left':
                $chageType = "Leave";
                break;
        }

        $message .= "*Тип изменения:* {$chageType}\n";

        if ($chatType !== 'private') {
            $message .= "\n👥 *Участников:* " . ($chat['members_count'] ?? 'неизвестно');
        }
        
        $this->SendToAdmin($message);
    }

    public function SendToAdmin($msg) {
        try {

            return $this->api->sendMessage(array_merge([
                'chat_id' => ADMIN_USERID,
                'parse_mode' => 'Markdown'
            ], is_string($msg) ? ['text'=>$msg] : $msg));
            
        } catch (Exception $e) {
            trace_error("Cannot send notification to owner: " . $e->getMessage());
        }
    }

    protected function MLQuery($message, $start_promt="Отвечай на русском языке. Коротко.", $session_id=false)
    {
        $history = $session_id ? $this->getSession($session_id) : false;

        if (!$history)
            $history = [
                ['role'=>'system', 'content'=>'Ты - полезный AI-ассистент. Отвечай на русском языке.'],
                ['role'=>'user', 'content'=>$start_promt]
            ];

        $history[] = ['role'=>'user', 'content'=>$message];
        
        $context = stream_context_create([
            'http' => [
                'header'  => "Content-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode(['messages' => $history]),
                'timeout' => 1800
            ]
        ]);
        
        $result = false;
        try {
            if (($result = file_get_contents(MLSERVER, false, $context)) === FALSE)
                return false;
            else $result = json_decode($result, true);

            if ($session_id && isset($result['response'])) {
                $history[] = ['role'=>'assistant', 'content'=>$result['response']]; // Сохраняем историю
                $this->setSession($session_id, $history);
            }
            
        } catch (Exception $e) {
            trace_error($e->getMessage());
        }
        
        return $result;
    }

    public function GetFileUrl($file_id) {

        if (filter_var($file_id, FILTER_VALIDATE_URL))
            return $file_id;
        
        $response = $this->api->getFile([
            'file_id' => $file_id
        ]);        
        
        $file_path = $response->getFilePath();
        
        return "https://api.telegram.org/file/bot{$this->api->getAccessToken()}/{$file_path}";
    }

    protected function genContent($text, $backToMenu = false, $buttons = null) {
        
        if ($buttons && !is_array($buttons)) {
            trace_error($buttons);
            $buttons = null;
        }

        $btList = empty($buttons) ? [] : $buttons;
        $result = ['text' => $text];

        if ($backToMenu) $btList[] = [$this->closeMessageButton(is_string($backToMenu) ? $backToMenu : 'Cancel')];

        if ($btList && is_array($btList) && (count($btList) > 0))
            $result['reply_markup'] = ['inline_keyboard' => $btList];

        return $result;
    }

    protected function getMessagePhoto($onlyLastPhoto = true) {
        $message = $this->currentUpdate['message'];

        if ($photos = @$message['photo'])
            return $onlyLastPhoto ? $photos[count($photos) - 1] : $photos;
        return null;
    }

    protected function getMessageVideo() {
        $message = $this->currentUpdate['message'];

        if ($video = @$message['video'])
            return $video;
        return null;
    }
}
?>