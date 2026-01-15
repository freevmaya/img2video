<?php


declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use \Telegram\Bot\Api;
use \Telegram\Bot\FileUpload\InputFile;
use GuzzleHttp\Client;
use Telegram\Bot\HttpClients\GuzzleHttpClient; // Правильный namespace
$dbp = null;

// === ОСНОВНОЙ КОД БОТА ===
try {
    $telegram = new Api(BOTTOKEN);

    $dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);
    $bot = new Image2VideoBot($telegram, $dbp, __FILE__.'.cfg');
    $bot->GetUpdates();
    $dbp->Close();
    echo "Ok\n";
} catch (Exception $e) {
    trace_error("Fatal bot error: " . $e->getMessage());
    if ($dbp)
        $dbp->Close();
}