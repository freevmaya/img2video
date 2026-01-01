<?php
// simple_bot.php
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Swoole\Coroutine;

class SimpleTelegramBot {
    private $telegram;
    
    public function __construct(string $botToken) {
        $this->telegram = new Api($botToken);
    }
    
    /**
     * Главный обработчик - запускается в webhook или getUpdates
     */
    public function handleUpdate(Update $update): void {
        // Обрабатываем в корутине (не блокируем основной поток)
        Coroutine::create(function () use ($update) {
            try {
                $this->processUpdate($update);
            } catch (\Throwable $e) {
                error_log("Bot error: " . $e->getMessage());
            }
        });
    }
    
    /**
     * Обработка разных типов сообщений
     */
    private function processUpdate(Update $update): void {
        // Сообщение
        if ($message = $update->getMessage()) {
            $this->handleMessage($message);
        }
        
        // Callback от кнопок
        if ($callback = $update->getCallbackQuery()) {
            $this->handleCallback($callback);
        }
        
        // Inline запрос
        if ($inlineQuery = $update->getInlineQuery()) {
            $this->handleInlineQuery($inlineQuery);
        }
    }
    
    /**
     * Обработка текстовых сообщений
     */
    private function handleMessage($message): void {
        $chatId = $message->getChat()->getId();
        $text = $message->getText() ?? '';
        $userId = $message->getFrom()->getId();
        
        echo "📨 Сообщение от $userId: $text\n";
        
        // Обработка команд
        switch (true) {
            case $text === '/start':
                $this->sendWelcome($chatId, $message->getFrom());
                break;
                
            case $text === '/help':
                $this->sendHelp($chatId);
                break;
                
            case $text === '/time':
                $this->sendCurrentTime($chatId);
                break;
                
            case str_starts_with($text, '/echo'):
                $echoText = substr($text, 6); // Убираем "/echo "
                $this->sendMessage($chatId, "Эхо: $echoText");
                break;
                
            case $text === '/keyboard':
                $this->showKeyboard($chatId);
                break;
                
            default:
                $this->sendMessage($chatId, "Вы написали: $text");
        }
    }
    
    /**
     * Приветственное сообщение
     */
    private function sendWelcome($chatId, $user): void {
        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();
        $username = $user->getUsername();
        
        $text = "👋 Привет, $firstName!\n\n";
        $text .= "Я простой демо-бот на Swoole.\n";
        $text .= "Доступные команды:\n";
        $text .= "/start - приветствие\n";
        $text .= "/help - помощь\n";
        $text .= "/time - текущее время\n";
        $text .= "/echo [текст] - эхо\n";
        $text .= "/keyboard - показать клавиатуру\n";
        
        if ($username) {
            $text .= "\nВаш username: @$username";
        }
        
        $this->sendMessage($chatId, $text);
    }
    
    /**
     * Помощь
     */
    private function sendHelp($chatId): void {
        $text = "📖 <b>Доступные команды:</b>\n\n";
        $text .= "/start - приветствие\n";
        $text .= "/help - эта справка\n";
        $text .= "/time - текущее время\n";
        $text .= "/echo [текст] - повторить текст\n";
        $text .= "/keyboard - показать клавиатуру\n\n";
        $text .= "Просто напишите текст, и я его повторю!";
        
        $this->sendMessage($chatId, $text, 'HTML');
    }
    
    /**
     * Текущее время
     */
    private function sendCurrentTime($chatId): void {
        $time = date('d.m.Y H:i:s');
        $text = "⏰ Текущее время:\n<b>$time</b>";
        
        $this->sendMessage($chatId, $text, 'HTML');
    }
    
    /**
     * Показать клавиатуру с кнопками
     */
    private function showKeyboard($chatId): void {
        $keyboard = [
            'keyboard' => [
                ['📅 Дата', '⏰ Время'],
                ['🎲 Случайное число', '🐱 Котик'],
                ['❌ Закрыть клавиатуру']
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Выберите действие:',
            'reply_markup' => json_encode($keyboard)
        ]);
    }
    
    /**
     * Обработка нажатий на кнопки
     */
    private function handleCallback($callback): void {
        $message = $callback->getMessage();
        $chatId = $message->getChat()->getId();
        $data = $callback->getData();
        
        // Ответ на callback (убираем "часики")
        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $callback->getId(),
            'text' => "Выбрано: $data"
        ]);
        
        // Обработка данных
        switch ($data) {
            case 'btn_date':
                $this->sendMessage($chatId, "Дата: " . date('d.m.Y'));
                break;
            case 'btn_time':
                $this->sendMessage($chatId, "Время: " . date('H:i:s'));
                break;
        }
    }
    
    /**
     * Inline режим
     */
    private function handleInlineQuery($inlineQuery): void {
        $query = $inlineQuery->getQuery();
        $results = [];
        
        if ($query === 'time') {
            $results[] = [
                'type' => 'article',
                'id' => '1',
                'title' => 'Текущее время',
                'input_message_content' => [
                    'message_text' => '⏰ Время: ' . date('H:i:s')
                ]
            ];
        }
        
        if ($query === 'date') {
            $results[] = [
                'type' => 'article',
                'id' => '2',
                'title' => 'Сегодняшняя дата',
                'input_message_content' => [
                    'message_text' => '📅 Дата: ' . date('d.m.Y')
                ]
            ];
        }
        
        if (!empty($results)) {
            $this->telegram->answerInlineQuery([
                'inline_query_id' => $inlineQuery->getId(),
                'results' => json_encode($results)
            ]);
        }
    }
    
    /**
     * Универсальная отправка сообщений
     */
    private function sendMessage($chatId, $text, $parseMode = null): void {
        $params = [
            'chat_id' => $chatId,
            'text' => $text
        ];
        
        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        
        try {
            $this->telegram->sendMessage($params);
            echo "✅ Отправлено сообщение в $chatId\n";
        } catch (\Exception $e) {
            error_log("Failed to send message: " . $e->getMessage());
        }
    }
}

// ============================================================================
// ИСПОЛЬЗОВАНИЕ
// ============================================================================

// Вариант 1: Webhook режим
function runWebhookBot($botToken): void {

    $bot = new SimpleTelegramBot($botToken);
    
    // Получаем update из webhook
    $input = file_get_contents('php://input');
    $updateData = json_decode($input, true);
    
    if ($updateData) {
        $update = new Update($updateData);
        $bot->handleUpdate($update);
        
        // Telegram ждет быстрый ответ
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }
}

// Вариант 2: Long polling (getUpdates) - для разработки
function runPollingBot($botToken): void {

    $bot = new SimpleTelegramBot($botToken);
    $telegram = new Api($botToken);
    
    echo "🤖 Бот запущен в polling режиме...\n";
    
    $offset = 0;
    
    while (true) {
        try {
            // Получаем updates
            $updates = $telegram->getUpdates([
                'offset' => $offset,
                'timeout' => 30,
                'limit' => 100
            ]);
            
            foreach ($updates as $update) {
                $offset = $update->getUpdateId() + 1;
                $bot->handleUpdate($update);
            }
            
            // Пауза между запросами
            sleep(1);
            
        } catch (\Exception $e) {
            error_log("Polling error: " . $e->getMessage());
            sleep(5); // Пауза при ошибке
        }
    }
}

// Запуск
if (php_sapi_name() === 'cli') {
    // CLI режим - polling
    runPollingBot(BOTTOKEN);
} else {
    // Web режим - webhook
    runWebhookBot(BOTTOKEN);
}