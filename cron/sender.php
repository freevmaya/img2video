<?php


declare(ticks = 1);

require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use \Telegram\Bot\Api;
use GuzzleHttp\Client;
use Telegram\Bot\HttpClients\GuzzleHttpClient; // Правильный namespace

use App\Services\API\cycle;

// === ИНИЦИАЛИЗАЦИЯ БЛОКИРОВКИ ===
$lock = new ProcessLock(__DIR__ . '/sender.pid');

if (!$lock->acquire()) {
    exit(0);
}

// Регистрируем обработчики для корректного завершения
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use ($lock) { exit(0); });
    pcntl_signal(SIGINT, function() use ($lock) { exit(0); });
}

register_shutdown_function(function() use ($lock) {
    $lock->release();
});

// === ОСНОВНОЙ КОД БОТА ===
try {

    $guzzleClient = new Client([
        'timeout' => 800,
        'connect_timeout' => 100,
        'read_timeout' => 800,
        'curl' => [
            CURLOPT_TIMEOUT => 100,
            CURLOPT_CONNECTTIMEOUT => 100
        ],
    ]);

    $httpClient = new GuzzleHttpClient($guzzleClient);


    $telegram = new Api(BOTTOKEN);
    $telegram->setHttpClientHandler($httpClient);

    $dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);
    
    $cycle = new SenderCycle($telegram, 'bot.php.cfg');

    //Основной цикл
    while ($lock->isFile()) {
        
        if (!$cycle->Update())
            break;        
        sleep(5);
    }

    $dbp->Close();
    
} catch (Exception $e) {
    trace_error("Fatal sender error: " . $e->getMessage());
    echo $e->getMessage();
    exit(1);
}