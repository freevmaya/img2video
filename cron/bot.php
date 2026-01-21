<?php


declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use \Telegram\Bot\Api;
use \Telegram\Bot\FileUpload\InputFile;
use GuzzleHttp\Client;
use Telegram\Bot\HttpClients\GuzzleHttpClient; // Правильный namespace

// === ИНИЦИАЛИЗАЦИЯ БЛОКИРОВКИ ===
$lock = new ProcessLock(__DIR__ . '/bot.pid');

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

$dbp = null;

// === ОСНОВНОЙ КОД БОТА ===
try {
    $telegram = new Api(BOTTOKEN);

    $dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);
    $bot = new Image2VideoBot($telegram, YKASSA_TOKEN);

    // 1. Удаляем вебхук, если он был установлен
    $telegram->deleteWebhook(['drop_pending_updates' => true]);

    $head = "======= Бот запущен. PID: " . getmypid().', date: '.date('d.m H:i:s').' =======';
    echo $head."\n";
    trace($head);
    
    // Основной цикл с обработкой обновлений
    while ($lock->isFile()) {

        $bot->GetUpdates();
        
        // Проверяем, не нужно ли завершить работу
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        
        //echo "Cycle\n";
        usleep(100000);
    }
    $dbp->Close();
    echo "Finish\n";
    exit(0);
    
} catch (Exception $e) {
    trace_error("Fatal bot error: " . $e->getMessage());
    if ($dbp)
        $dbp->Close();
    exit(1);
}