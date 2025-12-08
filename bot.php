<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Vmaya/engine.php';

use \Telegram\Bot\Api;
use \Telegram\Bot\FileUpload\InputFile;

$telegram = new Api(BOTTOKEN);

$dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);
$bot = new Image2VideoBot($telegram, $dbp);

// 1. Удаляем вебхук, если он был установлен
$telegram->deleteWebhook(['drop_pending_updates' => true]);


trace("Текущий промт:\n".html::RenderFile(TEMPLATES_PATH.'observer_promt2.php'));

// 3. Основной цикл бота
while (true) {
    $bot->GetUpdates();
}

$dbp->Close();