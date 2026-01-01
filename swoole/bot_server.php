<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Vmaya/engine.php';

$botToken = getenv('BOT_TOKEN') ?: BOTTOKEN;
$secretToken = getenv('SECRET_TOKEN') ?: null;

$server = new TelegramWebhookServer($botToken, $secretToken);
$server->start(9501);