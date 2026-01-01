<?php
class BotManager {
    private $bots = [];
    private $server;
    
    public function addBot(string $name, string $token, string $secretToken = null): void {
        $this->bots[$name] = [
            'adapter' => new AdvancedTelegramHandler($token),
            'secret' => $secretToken,
            'webhook_path' => "/webhook/$name"
        ];
    }
    
    public function startMultiBotServer(int $port = 9501): void {
        $this->server = new Server('0.0.0.0', $port);
        
        $this->server->set([
            'worker_num' => swoole_cpu_num() * 2,
            'enable_coroutine' => true,
        ]);
        
        $this->server->on('request', function (Request $req, Response $res) {
            $path = $req->server['request_uri'];
            
            foreach ($this->bots as $name => $bot) {
                if ($path === $bot['webhook_path']) {
                    $this->handleBotWebhook($name, $req, $res);
                    return;
                }
            }
            
            $res->status(404);
            $res->end('Not Found');
        });
        
        $this->server->start();
    }
    
    private function handleBotWebhook(string $botName, Request $req, Response $res): void {
        $bot = $this->bots[$botName];
        
        // Проверка secret token
        if ($bot['secret'] && 
            ($req->header['x-telegram-bot-api-secret-token'] ?? '') !== $bot['secret']) {
            $res->status(403);
            $res->end('Forbidden');
            return;
        }
        
        $updateData = json_decode($req->rawContent(), true);
        
        if ($updateData) {
            $update = new Update($updateData);
            
            // Обработка в отдельной корутине
            Coroutine::create(function () use ($bot, $update) {
                $bot['adapter']->process($update);
            });
        }
        
        $res->header('Content-Type', 'application/json');
        $res->end(json_encode(['ok' => true]));
    }
}

// Использование
$manager = new BotManager();
$manager->addBot('mybot', 'TOKEN1', 'secret123');
$manager->addBot('secondbot', 'TOKEN2', 'secret456');

$manager->startMultiBotServer();