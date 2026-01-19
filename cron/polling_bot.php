<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Telegram\Bot\Api;

include('../../config/config_pay.php');

$bot = new Api(BOTTOKEN);
$offset = 0;

echo "Платежный бот запущен...\n";

while (true) {
    $updates = $bot->getUpdates(['offset' => $offset, 'timeout' => 30]);
    
    foreach ($updates as $update) {
        $offset = $update->getUpdateId() + 1;
        
        if ($update->has('message')) {
            $message = $update->getMessage();
            $chatId = $message->getChat()->getId();
            $text = $message->getText();
            
            if ($text === '/start') {
                $bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Привет! Для оплаты нажмите /pay',
                ]);
            }
            
            if ($text === '/pay') {
                // Простой инвойс
                $bot->sendInvoice([
                    'chat_id' => $chatId,
                    'title' => 'Тестовый товар',
                    'description' => 'Описание товара',
                    'payload' => 'test_' . time(),
                    'provider_token' => PAYTOKEN,
                    'currency' => 'RUB',
                    'prices' => [
                        [
                            'label' => 'Товар',
                            'amount' => 10000, // 100 рублей
                        ]
                    ]
                ]);
            }
            
            if ($message->has('successful_payment')) {
                $payment = $message->getSuccessfulPayment();
                $bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Спасибо за оплату! Товар отправлен.',
                ]);
            }
        }
        
        if ($update->has('pre_checkout_query')) {
            $query = $update->getPreCheckoutQuery();
            $bot->answerPreCheckoutQuery([
                'pre_checkout_query_id' => $query->getId(),
                'ok' => true,
            ]);
        }
    }
    
    sleep(1);
}