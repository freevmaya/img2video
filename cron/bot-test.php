<?php


declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use \Telegram\Bot\Api;
use \Telegram\Bot\FileUpload\InputFile;
use GuzzleHttp\Client;
use Telegram\Bot\HttpClients\GuzzleHttpClient; // Правильный namespace

// === ИНИЦИАЛИЗАЦИЯ БЛОКИРОВКИ ===
$lock = new ProcessLock(__DIR__ . '/bot-test.pid');

if (!$lock->acquire()) {
    exit(0);
}

// Регистрируем обработчики для корректного завершения
if (function_exists('pcntl_signal')) {
    GLOBAL $lock;
    pcntl_signal(SIGTERM, function() use ($lock) { exit(0); });
    pcntl_signal(SIGINT, function() use ($lock) { exit(0); });
}

register_shutdown_function(function() use ($lock) {
    GLOBAL $lock;
    $lock->release();
});

// === ОСНОВНОЙ КОД БОТА ===
try {

    $guzzleClient = new Client([
        'timeout' => 15,
        'connect_timeout' => 15,
        'read_timeout' => 15,
        'curl' => [
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_LOW_SPEED_LIMIT => 1024,
            CURLOPT_LOW_SPEED_TIME => 300,
        ],
    ]);

    $httpClient = new GuzzleHttpClient($guzzleClient);


    $telegram = new Api('8510008902:AAE6i7gD3MvMExRtvXP1nYPVxoFIOhMASyI');
    $telegram->setHttpClientHandler($httpClient);

    // 1. Удаляем вебхук, если он был установлен
    $telegram->deleteWebhook(['drop_pending_updates' => true]);

    $head = "======= Бот запущен. PID: " . getmypid().' =======';
    echo $head."\n";

    $lastUpdateId = 0;
    
    // Основной цикл с обработкой обновлений
    while ($lock->isFile()) {

        $updates = $telegram->getUpdates([
            'offset' => $lastUpdateId + 1,
            'timeout' => 10, // Длительность ожидания новых сообщений (сек)
        ]);

        if (count($updates) > 0) {
            $lastUpdateId = $updates[count($updates) - 1]->getUpdateId();
            print_r(count($updates)."\n");
        } else echo "Cycle\n";
        usleep(100000);
    }

    echo "Finish\n";
    exit(0);
    
} catch (Exception $e) {
    echo("Fatal bot error: " . $e->getMessage());
    exit(1);
}