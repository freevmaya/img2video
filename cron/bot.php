<?php


declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

// Увеличиваем лимиты
ini_set('mysql.connect_timeout', 300);
ini_set('default_socket_timeout', 300);

// Регистрируем обработчик для автоматического восстановления соединения
register_shutdown_function(function() {
    $lastError = error_get_last();
    if ($lastError && strpos($lastError['message'], 'MySQL server has gone away') !== false) {
        // Логируем и продолжаем работу
        error_log("MySQL connection lost, but continuing...");
    }
});

use \Telegram\Bot\Api;
use \Telegram\Bot\FileUpload\InputFile;

// === ИНИЦИАЛИЗАЦИЯ БЛОКИРОВКИ ===
$lock = new ProcessLock(__DIR__ . '/bot.pid');

if (!$lock->acquire()) {
    error_log("Bot is already running. Exiting.");
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
    $telegram = new Api(BOTTOKEN);

    $dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);
    $bot = new Image2VideoBot($telegram, $dbp);

    // 1. Удаляем вебхук, если он был установлен
    $telegram->deleteWebhook(['drop_pending_updates' => true]);


    trace("Бот запущен. PID: " . getmypid());
    
    // Основной цикл с обработкой обновлений
    while ($lock->isFile()) {

        $bot->GetUpdates();
        
        // Проверяем, не нужно ли завершить работу
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        
        usleep(300);
    }

    $dbp->Close();
    
} catch (Exception $e) {
    trace_error("Fatal bot error: " . $e->getMessage());
    $dbp->Close();
    exit(1);
}