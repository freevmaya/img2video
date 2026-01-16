<?

use \Telegram\Bot\FileUpload\InputFile;
use \Telegram\Bot\Exceptions\TelegramResponseException;

class TelegramClient extends SettingsManager {
    protected $currentLanguage;
    protected $api;

    public function __construct($api, $file_settings = null)
    {
        parent::__construct($file_settings);
        $this->api = $api;
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

    public function sendMp4($taskOrChatId, $filePath, $filename, $message, $inline_keyboard=null) {

    	$chatId = is_numeric($taskOrChatId) ? $taskOrChatId : $taskOrChatId['chat_id'];

        if (!$filePath || !file_exists($filePath)) {
            $this->Message($chatId, '⚠️ '.Lang('Animation not found'));
            return;
        }

        $max_atempt = 3;
        $attempt_count = 0;
        $error_message = '';

        while ($attempt_count < $max_atempt) {

            sleep(round(pow($attempt_count, 1.5) * 3));
            try {

            	$params = [
                    'chat_id' => $chatId,
                    'video' => fopen($filePath, 'r'),
                    'caption' => $message,
                    'width' => 512,
                    'height' => 512,
                    'supports_streaming' => true
                ];

	            if ($inline_keyboard)
	                $params['reply_markup'] = json_encode([
	                    'inline_keyboard' => $inline_keyboard
	                ]);

                $message = $this->afterSend($this->api->sendVideo($params), true);
                if ($message->getMessageId()) 
                    return true;

            } catch (Exception $e) {
                $error_message = $e->getMessage();
            }
            $attempt_count++;
        }

        trace_error("Failed to send mp4 to chatId: {$chatId}\n\nError: {$error_message}");

        return false;
    }

    public function sendPhoto($chat_id, $file_path, $filename, $caption, $inline_keyboard = null) {
            
        $error_message = '';
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

            $max_atempt = 3;
            $attempt_count = 0;

            while ($attempt_count < $max_atempt) {
                try {

                    sleep(round(pow($attempt_count, 1.5) * 3));
                    $photoMessage = $this->afterSend($this->api->sendPhoto($params), true);
                    if ($photoMessage->getMessageId()) {
                        return true;
                    }

                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
                $attempt_count++;
            }
        } else {
            trace_error("File ({$file_path}) is not exists");
            return true;
        }

        trace_error("Failed to send image to chatId: {$chat_id}\n\nError: {$error_message}");
        return false;
    }

    public function Message($chatId, $msg, $parse_mode = 'Markdown') {

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => $parse_mode
        ], is_string($msg) ? ['text' => $msg] : $msg);

        $max_atempt = 3;
        $attempt_count = 0;
        $error_message = '';

        while ($attempt_count < $max_atempt) {

            sleep(round(pow($attempt_count, 1.5) * 3));
            try {

                $message = $this->afterSend($this->api->sendMessage($params), true);
                if ($message->getMessageId())
                    return true;

            } catch (Exception $e) {
                $error_message = $e->getMessage();
            }
            $attempt_count++;
        }

        trace_error("Failed to send message to chatId: {$chatId}\n\nError: {$error_message}");
        return false;
    }
}