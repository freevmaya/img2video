<?php
// set_webhook.php
require 'vendor/autoload.php';

use Telegram\Bot\Api;

$token = 'YOUR_BOT_TOKEN';
$webhookUrl = 'https://yourdomain.com:9501/webhook';
$secretToken = 'your_secret_token_here';

$telegram = new Api($token);

// Установка webhook
$response = $telegram->setWebhook([
    'url' => $webhookUrl,
    'secret_token' => $secretToken,
    'max_connections' => 100,
    'allowed_updates' => json_encode(['message', 'callback_query', 'inline_query'])
]);

if ($response) {
    echo "✅ Webhook установлен: $webhookUrl\n";
    
    // Проверка информации о webhook
    $info = $telegram->getWebhookInfo();
    print_r($info);
} else {
    echo "❌ Ошибка установки webhook\n";
}