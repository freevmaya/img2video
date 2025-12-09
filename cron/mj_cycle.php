<?php


declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use \Telegram\Bot\Api;
use \App\Services\API\MidjourneyAPI;

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

    $mj = new MJMainCycle(MJ_APIKEY, MJ_HOOK_URL, MJ_ACCOUNTHASH, $telegram, new TaskModel(), new MJModel());

    //Основной цикл
    while ($lock->isFile()) {
        $mj->Update();
        
        usleep(300);
    }

    $dbp->Close();
    
} catch (Exception $e) {
    trace_error("Fatal bot error: " . $e->getMessage());

    $dbp->Close();
    exit(1);
}