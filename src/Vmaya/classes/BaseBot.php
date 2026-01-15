<?
use GuzzleHttp\Client;
use Telegram\Bot\HttpClients\GuzzleHttpClient;
use \Telegram\Bot\FileUpload\InputFile;

abstract class BaseBot extends SettingsManager {
    private $origin_user_id;
    private $reply_to_message;
    private $currentLanguage;
    private $chatId;

    protected $user;
    protected $api;
    protected $currentUpdate = null;

    public function getUser() { return $this->user; }
    public function getUserId() { return $this->user ? $this->user['id'] : null; }
    public function getOriginUserId() { return $this->origin_user_id; }
    public function getReplyToMessage() { return $this->reply_to_message; }

	function __construct($api, $file_settings = null) {
        $this->api = $api;
        parent::__construct($file_settings);
    }

    public function setSettingsAll($settings) {
        parent::setSettingsAll($settings);

        if ($client_timeout = $this->getSetting('client_timeout', 10)) {

            $connect_timeout = round($client_timeout + $client_timeout * 0.1);
            $guzzleClient = new Client([
                'timeout' => $client_timeout,
                'connect_timeout' => $connect_timeout,
                'read_timeout' => $client_timeout,
                'curl' => [
                    CURLOPT_TIMEOUT => $client_timeout,
                    CURLOPT_CONNECTTIMEOUT => $connect_timeout,
                    CURLOPT_LOW_SPEED_LIMIT => 1024,
                    CURLOPT_LOW_SPEED_TIME => 300,
                ],
            ]);

            $httpClient = new GuzzleHttpClient($guzzleClient);
            $this->api->setHttpClientHandler($httpClient);
        }
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
        $callback_data = $callback['data']; // Здесь содержится ваш callback_data
        
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

    protected function unsetSessions($names) {
        if ($this->currentUpdate) 
            parent::unsetSessions($names);
    }

    protected function popSession($name) {

        $result = null;
        if ($this->currentUpdate)
            $result = parent::popSession($name);

        return null;
    }

    public function DeleteMessageByIndex($message_index=null) {
        if ($message_index)
            $this->DeleteMessage(null, $this->getMessageId($message_index));
    }

    public function DeleteMessage($chatId=null, $message_id=null) {
        if (empty($message_id))
            $message_id = $this->getSession('lastBotMessageId');
        if (empty($chatId))
            $chatId = $this->getCurrentChatId();

        if (!empty($message_id) && !empty($chatId))
            $this->api->deleteMessage([ 'chat_id' => $chatId, 'message_id' => $message_id]); 
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

        $this->Answer($chatIdOrMessage, ['text' => $text, 'reply_markup'=> json_encode([
                'inline_keyboard' => [
                    [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']]
                ]
            ])
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

        if ($messageEditId) {
            $params['message_id'] = $messageEditId;
            $result = $this->afterSend($this->api->editMessageText($params));
        } else $result = $this->sendMessage($params);

        return $result;
    }

    public function SendPhoto($caption, $photoPathOrUrl, $buttons = null, $parse_mode = 'Markdown') {
        $chatId = $this->getCurrentChatId();

        $params = array_merge([
            'chat_id' => $chatId,
            'photo' => isUrl($photoPathOrUrl) ? $photoPathOrUrl : InputFile::create($photoPathOrUrl),
            'caption' => $caption,
            'parse_mode' => $parse_mode
        ], is_string($caption) ? ['caption' => $caption] : $caption);

        if ($buttons && is_array($buttons) && (count($buttons) > 0))
            $params['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);

        return $this->afterSend($this->api->sendPhoto($params));
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
        }
    }

    public function popMessageHistory() {
        $history = $this->getSession('history', []);
        $result = array_pop($history);
        $this->setSession('history', $history);
        return $result;
    }

    public function DeleteLastMessage($count = 1) {
        
        for ($i=0; $i<$count; $i++)
            $this->DeleteMessage(null, $this->popMessageHistory());
    }

    public function sendMessage($params) {
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
        foreach ($updates as $update) {
            try {
                if ($this->initUser($update)) 
                    $this->_runUpdate($update);
            } catch (Exception $e) {
                trace_error($e->getMessage()."\n\nUpdate:\n".json_encode($update, JSON_FLAGS));
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
        if (DEV || $this->getSetting('log'))
            trace($update);

        $chatId = $this->getCurrentChatId();
        $this->readSession($chatId);

        // 6. Обновляем ID последнего обработанного сообщения
        $this->setSetting('lastUpdateId', $update->getUpdateId());
        $this->runUpdate($update);

        if ($this->isSessionChanged()) $this->saveSession();
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


    public function DownloadFileByFileId($file_id, $save_path = null) {
        try {

            // 1. Получаем информацию о файле
            $response = $this->api->getFile([
                'file_id' => $file_id
            ]);
            
            $file_path = $response->getFilePath();
            $file_url = $this->GetFileUrl($file_id);
            
            // 3. Скачиваем файл
            $file_content = file_get_contents($file_url);
            
            if ($file_content === false) {
                throw new Exception('Не удалось скачать файл');
            }
            
            // 4. Сохраняем файл
            if ($save_path === null) {
                $save_path = BASEPATH.'downloads'.DS.$this->user['id'].DS.basename($file_path);
            }
            
            // Создаем директорию, если не существует
            $dir = dirname($save_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            
            file_put_contents($save_path, $file_content);
            
            return [
                'success' => true,
                'path' => $save_path,
                'url' => BASEURL.US.'downloads'.US.$this->user['id'].US.basename($file_path),
                'size' => strlen($file_content),
                'original_path' => $file_path
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
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
            $result['reply_markup'] = json_encode(['inline_keyboard' => $btList]);

        return $result;
    }

    protected function getMessagePhoto($onlyLastPhoto = true) {
        $message = $this->currentUpdate['message'];

        if ($photos = @$message['photo'])
            return $onlyLastPhoto ? $photos[count($photos) - 1] : $photos;
        return null;
    }
}
?>