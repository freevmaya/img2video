<?php


declare(ticks = 1);

require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use \Telegram\Bot\Api;
use App\Services\API\cycle;

// === ИНИЦИАЛИЗАЦИЯ БЛОКИРОВКИ ===
$lock = new ProcessLock(__DIR__ . '/downloader.pid');

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

$dbp = null;

try {


    $dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);

    $manager = new TaskDownloadManager(2, 2);

    //Основной цикл
    while ($lock->isFile()) {
        $manager->Run();
        
        usleep(50);
    }

    $dbp->Close();
    
} catch (Exception $e) {
    trace_error("Fatal manager error: " . $e->getMessage());
    echo $e->getMessage();
    if ($dbp) $dbp->Close();
    exit(1);
}