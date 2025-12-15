<?php


declare(ticks = 1);

require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

require SERVICES_PATH.'cycle/BaseCycle.php';
require SERVICES_PATH.'cycle/MjCycle.php';
require SERVICES_PATH.'cycle/KlingCycle.php';

use \Telegram\Bot\Api;
use App\Services\API\cycle;

// === ИНИЦИАЛИЗАЦИЯ БЛОКИРОВКИ ===
$lock = new ProcessLock(__DIR__ . '/main_cycle.pid');

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

    $telegram = new Api(BOTTOKEN);

    $dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);

    $mj = new MainCycleEx($telegram);

    //Основной цикл
    while ($lock->isFile()) {
        $mj->Update();
        
        usleep(100);
    }

    $dbp->Close();
    
} catch (Exception $e) {
    trace_error("Fatal bot error: " . $e->getMessage());
    echo $e->getMessage();
    exit(1);
}