<?php
class AdvancedTelegramHandler {
    private $telegram;
    private $middlewares = [];
    
    public function __construct(string $botToken) {
        $this->telegram = new Api($botToken);
        $this->registerDefaultMiddlewares();
    }
    
    /**
     * Регистрация middleware
     */
    public function middleware(callable $middleware): self {
        $this->middlewares[] = $middleware;
        return $this;
    }
    
    /**
     * Обработка update через цепочку middleware
     */
    public function process(Update $update): void {
        Coroutine::create(function () use ($update) {
            $handler = function (Update $update) {
                // Финальный обработчик
                $this->handleUpdate($update);
            };
            
            // Создаем цепочку middleware
            $pipeline = array_reduce(
                array_reverse($this->middlewares),
                function ($next, $middleware) {
                    return function ($update) use ($middleware, $next) {
                        return $middleware($update, $next);
                    };
                },
                $handler
            );
            
            $pipeline($update);
        });
    }
    
    /**
     * Дефолтные middleware
     */
    private function registerDefaultMiddlewares(): void {
        // Логирование
        $this->middleware(function (Update $update, $next) {
            $updateId = $update->getUpdateId();
            echo "🔄 [Middleware] Начало обработки update $updateId\n";
            
            $start = microtime(true);
            $next($update);
            
            $time = round((microtime(true) - $start) * 1000, 2);
            echo "✅ [Middleware] Update $updateId обработан за {$time}ms\n";
        });
        
        // Обработка ошибок
        $this->middleware(function (Update $update, $next) {
            try {
                $next($update);
            } catch (\Throwable $e) {
                error_log("Error in update {$update->getUpdateId()}: " . $e->getMessage());
                
                // Можно отправить сообщение об ошибке админу
                $this->sendToAdmin("Ошибка в update {$update->getUpdateId()}: " . $e->getMessage());
            }
        });
        
        // Антифлуд
        $this->middleware(function (Update $update, $next) {
            $userId = $update->getMessage()?->getFrom()?->getId();
            
            if ($userId) {
                $key = "user_rate:$userId";
                $requests = apcu_inc($key, 1);
                
                if ($requests === 1) {
                    apcu_store($key, 1, 1); // Сброс через 1 секунду
                }
                
                if ($requests > 5) { // Лимит: 5 запросов в секунду
                    echo "⚠️ [Middleware] User $userId превысил лимит запросов\n";
                    return;
                }
            }
            
            $next($update);
        });
    }
    
    /**
     * Основной обработчик
     */
    private function handleUpdate(Update $update): void {
        $message = $update->getMessage();
        
        if ($message && $message->getText()) {
            $this->handleCommand($message);
        }
    }
    
    /**
     * Обработка команд
     */
    private function handleCommand($message): void {
        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        
        switch ($text) {
            case '/start':
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Привет! Я бот на Swoole.',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [[
                            ['text' => 'Кнопка 1', 'callback_data' => 'btn1'],
                            ['text' => 'Кнопка 2', 'callback_data' => 'btn2']
                        ]]
                    ])
                ]);
                break;
                
            case '/stats':
                $stats = $this->getBotStats();
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Статистика:\n" . print_r($stats, true)
                ]);
                break;
                
            default:
                // Отложенная отправка
                Coroutine::sleep(1); // Имитация обработки
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Вы сказали: $text"
                ]);
        }
    }
    
    private function getBotStats(): array {
        // Асинхронное получение статистики
        return Coroutine\run(function () {
            $stats = [];
            
            // Параллельные запросы
            $results = Coroutine\parallel([
                function () {
                    // Запрос 1
                    return ['users' => 100];
                },
                function () {
                    // Запрос 2
                    return ['messages' => 500];
                }
            ]);
            
            return array_merge(...$results);
        });
    }
    
    private function sendToAdmin(string $message): void {
        // Отправка сообщения админу
        $this->telegram->sendMessage([
            'chat_id' => ADMIN_CHAT_ID,
            'text' => $message
        ]);
    }
}