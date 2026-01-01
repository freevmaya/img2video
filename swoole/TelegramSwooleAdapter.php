<?
require __DIR__ . '/vendor/autoload.php';

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Swoole\Coroutine;
use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

class TelegramSwooleAdapter {
    private $telegram;
    private $botToken;
    
    public function __construct(string $botToken) {
        $this->botToken = $botToken;
        $this->telegram = new Api($botToken);
    }
    
    /**
     * Асинхронная обработка update в корутине
     */
    public function processUpdateAsync(array $update): void {
        Coroutine::create(function () use ($update) {
            try {
                $updateObj = new Update($update);
                $this->handleUpdate($updateObj);
            } catch (\Throwable $e) {
                error_log("Error processing update: " . $e->getMessage());
            }
        });
    }
    
    /**
     * Основной обработчик update
     */
    private function handleUpdate(Update $update): void {
        $message = $update->getMessage();
        
        if ($message) {
            $this->handleMessage($message);
        }
        
        if ($update->getCallbackQuery()) {
            $this->handleCallbackQuery($update->getCallbackQuery());
        }
        
        if ($update->getInlineQuery()) {
            $this->handleInlineQuery($update->getInlineQuery());
        }
    }
    
    /**
     * Асинхронная обработка сообщений
     */
    private function handleMessage($message): void {
        Coroutine::create(function () use ($message) {
            $chatId = $message->getChat()->getId();
            $text = $message->getText() ?? '';
            
            // Пример команд
            if ($text === '/start') {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Добро пожаловать!'
                ]);
            } elseif ($text === '/help') {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Список команд: ...'
                ]);
            } else {
                // Эхо
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Вы написали: $text"
                ]);
            }
        });
    }
    
    /**
     * Обработка callback query
     */
    private function handleCallbackQuery($callbackQuery): void {
        Coroutine::create(function () use ($callbackQuery) {
            $message = $callbackQuery->getMessage();
            $data = $callbackQuery->getData();
            
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Обработано!'
            ]);
            
            // Обновляем сообщение
            $this->telegram->editMessageText([
                'chat_id' => $message->getChat()->getId(),
                'message_id' => $message->getMessageId(),
                'text' => "Вы выбрали: $data"
            ]);
        });
    }
    
    /**
     * Обработка inline query
     */
    private function handleInlineQuery($inlineQuery): void {
        Coroutine::create(function () use ($inlineQuery) {
            $query = $inlineQuery->getQuery();
            $results = [];
            
            if (!empty($query)) {
                $results[] = [
                    'type' => 'article',
                    'id' => uniqid(),
                    'title' => "Результат для: $query",
                    'input_message_content' => [
                        'message_text' => "Вы искали: $query"
                    ]
                ];
            }
            
            $this->telegram->answerInlineQuery([
                'inline_query_id' => $inlineQuery->getId(),
                'results' => json_encode($results),
                'cache_time' => 300
            ]);
        });
    }
}