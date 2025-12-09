<?php
require '../vendor/autoload.php';
require '../src/Vmaya/engine.php';

use \Telegram\Bot\Api;
use \Telegram\Bot\FileUpload\InputFile;
use \App\Services\API\MidjourneyAPI;

$telegram = new Api(BOTTOKEN);

$dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);

$mj = new MJMainCycle(MJ_APIKEY, MJ_HOOK_URL, MJ_ACCOUNTHASH, $telegram, new TaskModel(), new MJModel());

//Основной цикл
while (true) {
    $mj->Update();
}

$dbp->Close();