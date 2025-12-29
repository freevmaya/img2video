<?

/*
Команды
subscribe - подписка

*/

abstract class YKassaBot extends BaseBot {

    public function Balance() {
        return (new TransactionsModel())->Balance($this->getUserId());
    }

    protected function callbackProcess($callback, $chatId, $messageId, $data) {

        $user_id = $this->getUser()['id'];
        $pref = explode('-', $data);

        StatisticModel::trace($pref[0], $chatId);

        switch ($pref[0]) {
            case 'subscribe':
                if (isset($pref[1]))
                    $this->SubscribeAction($chatId, $pref[1]);
                else $this->subscribe($chatId);
                return true;
            case 'MySubscribe':
                $this->mySubscribe($chatId);
                return true;
            default: return false;
        }
    }

    protected function mySubscribe($chatId) {
    }

    protected function runUpdate($update) {
        if (isset($update['pre_checkout_query'])) {

            $this->handlePreCheckout($update['pre_checkout_query']);

        } else if (isset($update['message']['successful_payment'])) {

            // Успешный платеж
            $this->handleSuccessfulPayment(
                $update['message']['chat']['id'],
                $update['message']['successful_payment']
            );

        } else if (isset($update['shipping_query'])) {

            // Ответ на инвойс (shipping query)
            $this->handleShippingQuery($update['shipping_query']);

        } else parent::runUpdate($update);
    }

    protected function handleSuccessfulPayment($chat_id, $payment) {
        $invoice_payload = $payment['invoice_payload'];
        $total_amount = $payment['total_amount'];
        $currency = $payment['currency'];
        $telegram_payment_charge_id = $payment['telegram_payment_charge_id'];
        $provider_payment_charge_id = $payment['provider_payment_charge_id'];
        
        // Обновляем статус заказа
        $this->updateOrderStatus($invoice_payload, true, $provider_payment_charge_id);
        
        // Отправляем подтверждение пользователю
        $this->api->sendMessage([
            'chat_id' => $chat_id,
            'text' => '✅ Спасибо за оплату! Вы приобрели подписку.',
            'parse_mode' => 'HTML'
        ]);
        
        // Можно отправить товар/услугу
        $this->deliverProduct($chat_id, $invoice_payload);
    }

    private function checkProductAvailability($payload) {
        // Проверка наличия товара в БД
        return true;
    }
    
    private function updateOrderStatus($payload, $status, $provider_payment_charge_id) {
        // Обновление статуса заказа в БД

        $model = new TransactionsModel();
        if ($trans = $model->getItem($payload, 'payload')) {

            $data = json_decode($trans['data'], true);

            $data['provider_payment_charge_id'] = $provider_payment_charge_id;

            $model->Update([
                'payload'=>$payload,
                'type'=> $status ? 'subscribe' : 'failure',
                'data'=>json_encode($data)
            ], 'payload');
        }
    }
    
    private function deliverProduct($chat_id, $payload) {
        // Доставка цифрового товара или обновление статуса подписки
        trace($payload);
    }

    protected function handlePreCheckout($query)
    {
        $query_id       = $query['id'];
        $user_id        = $query['from']['id'];
        $payload        = $query['invoice_payload'];
        $total_amount   = $query['total_amount'];
        $currency       = $query['currency'];
        
        // Проверяем возможность оплаты
        
        if ($this->checkProductAvailability($payload)) {
            // Подтверждаем оплату
            
            $this->api->answerPreCheckoutQuery([
                'pre_checkout_query_id' => $query_id,
                'ok' => true
            ]);
        } else {
            // Отказываем в оплате
            $this->api->answerPreCheckoutQuery([
                'pre_checkout_query_id' => $query_id,
                'ok' => false,
                'error_message' => Lang("This subscription is temporarily unavailable")
            ]);
        }
    }

    protected function SubscribeAction($chatId, $subscribe_type_id) {
        if ($subscribe_type_id > 0) {
            if ($stype = (new SubscribeOptions())->getItem($subscribe_type_id)) {

                try {
                    $currency = "RUB";
                    $amount = intval($stype['price']);

                    if (PaymentHelper::validateCurrencyAmount($currency, $amount)) {

                        $prices = [
                            [
                                'label' => $stype['name'],
                                'amount' => $amount * 100
                            ]
                        ];

                        $payload = PaymentHelper::createPayload($chatId, $stype['id'], [
                            'currency' => $currency
                        ]);

                        $data = [
                            'type_id'=>$subscribe_type_id
                        ];

                        $transaction_id = (new TransactionsModel())->Add($this->getUser()['id'], $payload, $amount, 'prepare', $data);

                        $params = [
                            'chat_id' => $chatId,
                            'title' => $stype['name'],                      // Название товара (1-32 символа)
                            'description' => $stype['description'],         // Описание (1-255 символов)
                            'payload' => $payload,                          // Уникальный идентификатор (1-128 байт)
                            'provider_token' => YKASSA_TOKEN,               // Токен платежного провайдера
                            'currency' => $currency,                        // Код валюты (USD, RUB, EUR и т.д.)
                            'prices' => $prices,                            // Массив с ценами
                            'start_parameter' => 'test',                    // Параметр для deep linking
                        ];

                        trace($params);

                        $response = $this->api->sendInvoice($params);
                
                        $result = [
                            'success' => true,
                            'message_id' => $response->getMessageId(),
                            'invoice_payload' => $response->getInvoicePayload(),
                            'response' => $response
                        ];
                    }
                    
                } catch (\Exception $e) {
                    $result = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }

                trace($result);
            }
        }
    }

    protected function commandProcess($command, $chatId, $messageId, $text) {
        switch ($command) {
            case '/subscribe':
                //$this->DeleteMessage($chatId, $messageId);
                $this->subscribe($chatId);
                return true;
        }
    }

    protected function isAllowedImage() {
        return $this->Balance() >= (new TransactionsModel)->GetPrice($this->getUserId(), 'image_limit');
    }

    protected function isAllowedVideo() {
        return $this->Balance() >= (new TransactionsModel)->GetPrice($this->getUserId(), 'video_limit');
    }

    protected function notEnough($chatId) {
        $keyboard[] = [
            ['text' => "💰 ".Lang("Purchase a subscription"), 'callback_data' => 'subscribe']
        ];
        $this->Answer($chatId, $this->genContent(Lang("Insufficient balance"), true, $keyboard), $this->getSession('lastBotMessageId'));
    }

    protected function subscribeTypeList() {
        $list = (new SubscribeOptions())->ByArea($this->getUser()['area_id']);
        $keyboard = [];

        foreach ($list as $item)
            $keyboard[] = [['text' => $item['price'].' '.$item['currency'].' - '.$item['name'], 'callback_data' => 'subscribe-'.$item['id']]];
        return $keyboard;
    }

    protected function subscribe($chatId) {
        $keyboard = $this->subscribeTypeList();

        $tmodel = new TransactionsModel();

        $subscribeBlock = [];
        if ($tmodel->Balance($this->getUser()['id']) > 0) $subscribeBlock[] = ['text'=>Lang('My subscribe'), 'callback_data' => 'MySubscribe'];
        if ($tmodel->Expense($this->getUser()['id']) > 0) $subscribeBlock[] = ['text'=>Lang('My expenses'), 'callback_data' => 'MyExpenses'];

        $keyboard[] = $subscribeBlock;

        $this->Answer($chatId, $this->genContent(Lang("Subscription options"), true, $keyboard), $this->getSession('lastBotMessageId'));
    }
}

class PaymentHelper {
    
    /**
     * Создать payload для инвойса
     */
    public static function createPayload($user_id, $product_id, $data = []) {
        $payload = [
            'user_id' => $user_id,
            'product_id' => $product_id,
            'timestamp' => time(),
            'data' => $data
        ];
        
        return base64_encode(json_encode($payload));
    }
    
    /**
     * Распарсить payload
     */
    public static function parsePayload($payload) {
        $decoded = base64_decode($payload);
        return json_decode($decoded, true);
    }
    
    /**
     * Форматировать цену для Telegram
     */
    public static function formatPrice($amount, $currency) {
        // Проверка валюты и минимальной единицы
        $minimal_units = [
            'RUB' => 100,   // 1 рубль = 100 копеек
            'USD' => 100,   // 1 доллар = 100 центов
            'EUR' => 100,   // 1 евро = 100 центов
            'UAH' => 100,   // 1 гривна = 100 копеек
            'KZT' => 100,   // 1 тенге = 100 тиынов
            'BYN' => 100,   // 1 белорусский рубль = 100 копеек
        ];
        
        $multiplier = $minimal_units[$currency] ?? 100;
        return (int)($amount * $multiplier);
    }
    
    /**
     * Получить список доступных валют
     */
    public static function getAvailableCurrencies() {
        return [
            'USD' => 'Доллар США ($)',
            'EUR' => 'Евро (€)',
            'RUB' => 'Российский рубль (₽)',
            'UAH' => 'Украинская гривна (₴)',
            'KZT' => 'Казахстанский тенге (₸)',
            'BYN' => 'Белорусский рубль (Br)',
            'GBP' => 'Фунт стерлингов (£)',
            'JPY' => 'Японская иена (¥)',
            'CNY' => 'Китайский юань (¥)',
        ];
    }

    public static function getCurrencyLimits($currency) {
        $limits = [
            'USD' => ['min' => 0.50,   'max' => 10000.00],   // $0.50 - $10,000
            'EUR' => ['min' => 0.45,   'max' => 9000.00],    // €0.45 - €9,000
            'RUB' => ['min' => 50.00,  'max' => 750000.00],  // 50₽ - 750,000₽
            'UAH' => ['min' => 13.00,  'max' => 270000.00],  // 13₴ - 270,000₴
            'KZT' => ['min' => 220.00, 'max' => 4500000.00], // 220₸ - 4,500,000₸
            'BYN' => ['min' => 1.30,   'max' => 26000.00],   // 1.3Br - 26,000Br
            'GBP' => ['min' => 0.40,   'max' => 8000.00],    // £0.40 - £8,000
            'JPY' => ['min' => 50,     'max' => 1000000],    // 50¥ - 1,000,000¥
            'CNY' => ['min' => 3.50,   'max' => 70000.00],   // 3.5¥ - 70,000¥
        ];
        
        return $limits[$currency] ?? $limits['USD'];
    }

    public static function validateCurrencyAmount($currency, $amount) {
        
        $limits = PaymentHelper::getCurrencyLimits($currency);
        
        if ($amount < $limits['min']) {
            throw new Exception(
                "Сумма слишком мала. Минимум для {$currency}: {$limits['min']}"
            );
        }
        
        if ($amount > $limits['max']) {
            throw new Exception(
                "Сумма слишком велика. Максимум для {$currency}: {$limits['max']}"
            );
        }
        
        return true;
    }
}
?>