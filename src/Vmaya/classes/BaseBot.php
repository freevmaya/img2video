<?
abstract class BaseBot {
    private $session;
    private $lastUpdateId;
    private $user;
    private $reply_to_message;

    protected $api;
    protected $dbp;
    protected $currentUpdate = null;

    public function getUser() { return $this->user; }
    public function getUserId() { return $this->user ? $this->user['id'] : null; }
    public function getReplyToMessage() { return $this->reply_to_message; }

	function __construct($api, $dbp) {
        $this->api = $api;
        $this->lastUpdateId = 0;
        $this->initialize();
    }

    protected function initialize() {
        
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

    protected function setSession($name, $value) {
        $this->session[$name] = $value;
        saveSession($this->currentUpdate->getMessage()->getChat()->getId(), $this->session);
    }

    public function CurrentUpdate() {
        return $this->currentUpdate;
    }

    protected function hasSession($name) {
        return isset($this->session[$name]);
    }

    protected function getSession($name) {
        return $this->hasSession($name) ? $this->session[$name] : false;
    }

    protected function popSession($name) {

        if (isset($this->session[$name])) {
            $result = $this->session[$name];
            unset($this->session[$name]);
            saveSession($this->currentUpdate->getMessage()->getChat()->getId(), $this->session);
        } else $result = null;

        return $result;
    }

    public function DeleteMessage($chatId, $message_id) {
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

    public function Wrong($chatId) {
        $this->Answer($chatId, ['text' => Lang("Something wrong"), 'reply_markup'=> json_encode([
                'inline_keyboard' => [
                    [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']]
                ]
            ])
        ]);
    }

    public function Answer($chatId, $msg, $messageId = false, $reply_to_message_id = false, $parse_mode = 'Markdown') {

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

        if ($messageId) {
            $params['message_id'] = $messageId;
            return $this->api->editMessageText($params);
        } else return $this->api->sendMessage($params);
    }

    /*
    protected function getReplyToMessage() {
        $message = $this->currentUpdate->getMessage();
    }*/

    protected function initUser($update) {
        $fields = ['message', 'callback_query', 'pre_checkout_query', 'response'];
        $user = null;
        foreach ($fields as $field)
            if (isset($update[$field])) {
                $user = $update[$field]['from'];
                break;
            }

        if ($user) {
            $this->user = (new TGUserModel())->checkAndAdd($user);
            $this->initLang($this->user['language_code']);
        } else $this->initLang('ru');
    }

    protected function initLang($language_code) {
        GLOBAL $lang;
        $fileName = LANGUAGE_PATH.$language_code.'.php';
        if (file_exists($fileName))
            include_once($fileName);
    }

    public function GetWebhookUpdates() {

        //$this->sendImmediateHttpResponse();
        $update = $this->api->getWebhookUpdate();

        $this->initUser($update);

        if ($this->lastUpdateId != $update->getUpdateId())
            $this->_runUpdate($update);
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
            // 4. Получаем обновления с учетом последнего обработанного ID
            $updates = $this->api->getUpdates([
                'offset' => $this->lastUpdateId + 1,
                'timeout' => 30, // Длительность ожидания новых сообщений (сек)
            ]);

            // 5. Обрабатываем каждое обновление
            foreach ($updates as $update) {
                $this->initUser($update);

                $this->_runUpdate($update);
            } 
        } catch (Exception $e) {
            // 9. Обработка ошибок
            echo 'Ошибка: ' . $e->getMessage() . PHP_EOL;
            sleep(5); // Пауза перед повторной попыткой
        }
    }

    protected function runUpdate($update) {

        $this->currentUpdate = $update;

        $message = $update->getMessage();
        $chat    = $message->getChat();

        if ($chat) {
            $this->session = readSession($chat->getId());
            $chatId = $message->getChat()->getId();
            $messageId = $message['message_id'];

            if (@$message['from']['is_bot'])
                $this->setSession('lastBotMessageId', $messageId);

            $text = $message->getText();

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
        } else {
            $this->session = [];
        }
    }

    private function _runUpdate($update) {
        trace($update);
        // 6. Обновляем ID последнего обработанного сообщения
        $this->lastUpdateId = $update->getUpdateId();
        $this->runUpdate($update);
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
}
?>