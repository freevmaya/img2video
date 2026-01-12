<?
use GuzzleHttp\Client;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

abstract class BaseBot {
    private $session;
    private $sessionChanged;
    private $origin_user_id;
    private $reply_to_message;
    private $sessionModel;
    private $currentLanguage;
    private $file_settings;
    private $time_settings;

    protected $user;
    protected $api;
    protected $dbp;
    protected $settings;
    protected $currentUpdate = null;

    public function getUser() { return $this->user; }
    public function getUserId() { return $this->user ? $this->user['id'] : null; }
    public function getOriginUserId() { return $this->origin_user_id; }
    public function getReplyToMessage() { return $this->reply_to_message; }

	function __construct($api, $dbp, $file_settings = null) {
        $this->api = $api;

        $this->openSettings($file_settings);

        $this->initialize();
    }

    public function setSettings($settings)
    {
        $this->settings = $settings;
        $this->time_settings = time();

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

        trace("Set settings: ".json_encode($settings, JSON_FLAGS));
    }

    public function getDefaultSettings()
    {
        return ['lastUpdateId' => 0, 'update_timeout' => 10, 'client_timeout' => 20];
    }

    protected function openSettings($file_settings)
    {
        if (!empty($file_settings)) {
            $this->file_settings = $file_settings;
            if (file_exists($file_settings)) {
                $this->setSettings(json_decode(file_get_contents($this->file_settings), true));
                $this->time_settings = filemtime($this->file_settings);
            } else if (empty($this->settings)) {
                $this->setSettings($this->getDefaultSettings());
            }
        }
    }

    public function getSetting($param_name, $default_value = null) {
        if (isset($this->settings[$param_name]))
            return $this->settings[$param_name];
        return $default_value;
    }

    protected function saveSettings() {
        if (!empty($this->file_settings) && !empty($this->settings)) {
            file_put_contents($this->file_settings, json_encode($this->settings, JSON_FLAGS));
            $this->time_settings = filemtime($this->file_settings);
        }
    }

    public function checkAndUpdateSettings() {
        
        if (file_exists($this->file_settings) &&
            (filemtime($this->file_settings) != $this->time_settings))
            $this->openSettings($this->file_settings);
    }

    public function Api() {
        return $this->api;
    }

    protected function initialize() {
        $this->sessionModel = new SessionsModel();
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
        $this->sessionChanged = true;
    }

    protected function saveSession($chatId, $data) {
        $this->sessionModel->Update([
            'chat_id' => $chatId,
            'data' => json_encode($data, JSON_FLAGS)
        ], 'chat_id');
    }

    protected function stat($userId, $type, $data = null) {
        if ($userId != ADMIN_USERID)
            StatisticModel::trace($type, $data);
    }

    protected function readSession($chatId) {
        $result = [];

        if ($item = $this->sessionModel->getItem($chatId, 'chat_id'))
            $result = json_decode($item['data'], true);
        else $this->sessionModel->Update(['chat_id'=>$chatId, 'data'=>'{}']);

        if ($chatId != ADMIN_USERID)
            trace("Attempt read session: {$chatId}. Result: ".json_encode($item, JSON_FLAGS));

        return $result;
    }


    public function CurrentUpdate() {
        return $this->currentUpdate;
    }

    protected function hasSession($name) {
        return isset($this->session[$name]);
    }

    protected function getSession($name) {
        /*
        if (!$this->hasSession($name))
            trace("Session field $name not found!\n");
            */
        return $this->hasSession($name) ? $this->session[$name] : false;
    }

    protected function unsetSessions($names) {

        if ($this->currentUpdate) {
            foreach ($names as $name)
                if (isset($this->session[$name])) {
                    unset($this->session[$name]);
                    $this->sessionChanged = true;
                }
        }
    }

    protected function popSession($name) {

        $result = null;
        if ($this->currentUpdate) {           
            if (isset($this->session[$name])) {
                $result = $this->session[$name];
                unset($this->session[$name]);
                $this->sessionChanged = true;
            }
        }

        return $result;
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
        if ($this->currentUpdate)
            return @$this->currentUpdate->getMessage()->getChat()->getId();
        else return $this->getUserId();
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
            $result = $this->api->editMessageText($params);
        } else {
            $result = $this->api->sendMessage($params);
        }

        if (isset($result['message_id']))
            $this->setSession('lastBotMessageId', $result['message_id']);
        return $result;
    }

    /*
    protected function getReplyToMessage() {
        $message = $this->currentUpdate->getMessage();
    }*/

    public function findUserBlock($update) {

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
        if ($block = $this->findUserBlock($update))
            $user = $block['from'];

        $this->session = [];
        if ($user) {
            try {
                $chatId = isset($block['chat']) ? $block['chat']['id'] : @$block['message']['chat']['id'];
                if (empty($chatId)) $chatId = $user['id'];

                $this->session = $this->readSession($chatId);
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
            try {
                $updates = $this->api->getUpdates([
                    'offset' => $this->getSetting('lastUpdateId', 0),
                    'timeout' => $this->getSetting('update_timeout', 0), // Длительность ожидания новых сообщений (сек)
                ]);

                //Обрабатываем каждое обновление
                foreach ($updates as $update) {
                    if ($this->initUser($update)) 
                        $this->_runUpdate($update);
                } 
            } catch (Exception $e) {
                trace_error($e->getMessage());
            }
        } catch (Exception $e) {
            // 9. Обработка ошибок
            echo 'Ошибка: ' . $e->getMessage() . PHP_EOL;
            sleep(5); // Пауза перед повторной попыткой
        }
    }

    /*
    public function GetWebhookUpdates() {

        //$this->sendImmediateHttpResponse();
        $update = $this->api->getWebhookUpdate();

        if ($this->initUser($update)) {
            if ($this->settings['lastUpdateId'] != $update->getUpdateId())
                $this->_runUpdate($update);
        }
    }*/

    protected function beforeProcess($chatId, $text) {
        return true;
    }

    protected function runUpdate($update) {

        $this->currentUpdate = $update;

        if ($message = $update->getMessage()) {
            $chat = $update->getChat();        

            if ($chat) {
                $chatId = $chat->getId();
                $messageId = $message['message_id'];

                $text = $message->getText();
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
                } else 
                    $this->session = [];
                    
            } else {
                $this->session = [];
            }
        } else {
            trace_error("Message is null");
        }
    }

    private function _runUpdate($update) {
        if (DEV || $this->getSetting('log'))
            trace($update);

        $this->sessionChanged = false;

        // 6. Обновляем ID последнего обработанного сообщения
        $this->settings['lastUpdateId'] = $update->getUpdateId();
        $this->saveSettings();
        $this->runUpdate($update);

        if ($this->sessionChanged && $this->currentUpdate->getMessage())
            $this->saveSession($this->currentUpdate->getMessage()->getChat()->getId(), $this->session);
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

        /*
        if ($backToMenu && $this->hasSession('lastBotMessageId')) {
            $back = ['text' => Lang("Back"), 'callback_data' => 'menu'];
            if (count($btList) > 0)
                $btList[count($btList) - 1][] = $back;
            else $btList[] = [$back];            
        }*/

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