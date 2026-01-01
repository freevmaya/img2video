<?
class TelegramWebhookServer {
    private $adapter;
    private $server;
    private $secretToken;
    
    public function __construct(string $botToken, string $secretToken = null) {
        $this->adapter = new TelegramSwooleAdapter($botToken);
        $this->secretToken = $secretToken;
    }
    
    /**
     * Запуск сервера
     */
    public function start(int $port = 9501, string $host = '0.0.0.0'): void {
        $this->server = new Server($host, $port);
        
        // Настройки сервера
        $this->server->set([
            'worker_num' => swoole_cpu_num() * 2,
            'max_request' => 10000,
            'enable_coroutine' => true,
            'http_parse_post' => true,
            'package_max_length' => 10 * 1024 * 1024, // 10MB для больших updates
        ]);
        
        // Обработчики событий
        $this->server->on('request', [$this, 'handleRequest']);
        $this->server->on('workerStart', [$this, 'onWorkerStart']);
        
        echo "✅ Telegram Webhook Server запущен на $host:$port\n";
        echo "📊 Worker processes: " . (swoole_cpu_num() * 2) . "\n";
        
        $this->server->start();
    }
    
    /**
     * Обработка входящих запросов
     */
    public function handleRequest(Request $request, Response $response): void {
        // Валидация secret token (если настроен в Telegram)
        if ($this->secretToken && 
            $request->header['x-telegram-bot-api-secret-token'] !== $this->secretToken) {
            $response->status(403);
            $response->end('Forbidden');
            return;
        }
        
        // Проверка метода
        if ($request->server['request_method'] !== 'POST') {
            $response->status(405);
            $response->end('Method Not Allowed');
            return;
        }
        
        $rawData = $request->rawContent();
        $update = json_decode($rawData, true);
        
        if (!$update) {
            $response->status(400);
            $response->end(json_encode(['error' => 'Invalid JSON']));
            return;
        }
        
        $updateId = $update['update_id'] ?? 'unknown';
        
        // Обрабатываем в отдельной корутине
        Coroutine::create(function () use ($update, $updateId) {
            $this->adapter->processUpdateAsync($update);
            echo "📨 Update $updateId принят в обработку\n";
        });
        
        // Немедленно отвечаем Telegram (приняли update)
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode(['ok' => true]));
    }
    
    /**
     * Инициализация воркера
     */
    public function onWorkerStart(): void {
        echo "🔄 Worker #" . $this->server->worker_id . " запущен\n";
        
        // Можно инициализировать подключения к БД и т.д.
        $this->initializeDatabasePool();
    }
    
    /**
     * Инициализация пула подключений к БД (опционально)
     */
    private function initializeDatabasePool(): void {
        // Пример с Swoole MySQL Pool
        Swoole\Runtime::enableCoroutine();
        
        Coroutine::create(function () {
            $pool = new \Swoole\Coroutine\Channel(10);
            
            for ($i = 0; $i < 10; $i++) {
                $mysql = new \Swoole\Coroutine\MySQL();
                $mysql->connect([
                    'host' => '127.0.0.1',
                    'port' => 3306,
                    'user' => 'user',
                    'password' => 'password',
                    'database' => 'telegram_bot',
                ]);
                $pool->push($mysql);
            }
            
            // Сохраняем пул в глобальной переменной или контейнере
            $GLOBALS['db_pool'] = $pool;
        });
    }
}