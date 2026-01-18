<?

/*
Команды
subscribe - подписка

*/

abstract class YKassaBot extends BaseBot {

    public function Balance() {
        return (new TransactionsModel())->Balance($this->getUserId());
    }

    protected function commandProcess($command, $chatId, $messageId, $text) {
        switch ($command) {
            case '/subscribe':
                $this->subscribe($chatId);
                return true;
        }
    }

    protected function callbackProcess($callback, $chatId, $messageId, $data) {

        $user_id = $this->getUser()['id'];
        $pref = explode('-', $data);

        switch ($pref[0]) {
            case 'subscribe':
                if (isset($pref[1]))
                    $this->SubscribeAction($chatId, $pref[1]);
                else $this->subscribe($chatId);
                return true;
            case 'MySubscribe':
                $this->MySubscribe($chatId);
                return true;
            case 'free_invoce':
                $this->FreeInvoce($pref[1]);
                return true;
            default: return false;
        }
    }

    protected function MySubscribe($chatId) {
    }

    protected function currency() {

        if ($this->currentLanguage && isset(CURRENCY_LIST[$this->currentLanguage])) 
            return CURRENCY_LIST[$this->currentLanguage];
        else return reset(array_values(CURRENCY_LIST));
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
        $this->Answer(null, $this->genContent('✅ Спасибо за оплату! Вы приобрели подписку.', 'Close', [
            [['text'=>Lang('My subscribe'), 'callback_data'=>"MySubscribe"]]
        ]));
        
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

    protected function CreateInvoice($amount, $type_id, $productId, $product, $productDesc) {
                
        try {

            $this->stat($this->getCurrentChatId(), 'payment-attempt', $productId);

            $chatId = $this->getCurrentChatId();
            $response = null;
            $params = null;

            $currency = $this->currency();

            if (DEV) echo $currency."\n";

            $amount = PaymentHelper::validateCurrencyAmount($currency, $amount);

            $prices = [
                [
                    'label' => $product,
                    'amount' => $amount * 100
                ]
            ];

            $payload = PaymentHelper::createPayload($chatId, $productId, [
                'currency' => $currency
            ]);

            $data = [
                'type_id'=>$type_id
            ];

            $transaction_id = (new TransactionsModel())->Add($this->getUser()['id'], $payload, $amount, 'prepare', $data);

            $params = [
                'chat_id' => $chatId,
                'title' => $product,                            // Название товара (1-32 символа)
                'description' => $productDesc,                  // Описание (1-255 символов)
                'payload' => $payload,                          // Уникальный идентификатор (1-128 байт)
                'provider_token' => YKASSA_TOKEN,               // Токен платежного провайдера
                'currency' => $currency,                        // Код валюты (USD, RUB, EUR и т.д.)
                'prices' => $prices,
                'start_parameter' => 'test'                     // Параметр для deep linking
            ];

            /*
            if (DEV)
                $params['start_parameter'] = 'test';
                */

            $response = $this->api->sendInvoice($params);

            trace("sendInvoice\nParams: ".json_encode($params, JSON_FLAGS).
                    "\nResponse: ".json_encode($response, JSON_FLAGS));
            
        } catch (\Exception $e) {
            $this->Wrong();
            trace_error('sendInvoice error: '.$e->getMessage().
                "\nParams: ".json_encode($params, JSON_FLAGS).
                "\nResponse: ".json_encode($response, JSON_FLAGS));
        }

    }

    protected function FreeInvoce($amount, $description="") {
        if (empty($description))
            $description = Lang('One-time payment');

        $this->CreateInvoice($amount, 0, 0, Lang("Account replenishment"), $description);
    }

    protected function SubscribeAction($chatId, $subscribe_type_id) {
        if ($subscribe_type_id > 0) {
            if ($stype = (new SubscribeOptions())->getItem($subscribe_type_id)) {

                $amount = intval($stype['price']);
                $this->CreateInvoice($amount, $subscribe_type_id, $stype['id'], $stype['name'], $stype['description']);
            }
        }
    }

    protected function isAllowedImage() {
        $price = (new TransactionsModel)->GetPrice($this->getUserId(), 'image_limit');
        return $this->Balance() - $price;
    }

    protected function isAllowedVideo() {
        $price = (new TransactionsModel)->GetPrice($this->getUserId(), 'video_limit');
        return $this->Balance() - $price;
    }

    protected function notEnough($amount) {
        $keyboard[] = [
            ['text' => "💰 ".Lang("Top up"), 'callback_data' => "free_invoce-{$amount}"]
        ];
        $this->Answer(null, $this->genContent(Lang("There is not enough on your balance $%s", $amount), true, $keyboard));
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

        $this->Answer($chatId, $this->genContent(Lang("Subscription options"), true, $keyboard));
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
            'RUB' => ['min' => 88,      'max' => 877271.00],
            'USD' => ['min' => 1,       'max' => 10000.00],
            'EUR' => ['min' => 0.88,    'max' => 8817],
            'UAH' => ['min' => 13.00,   'max' => 270000.00], 
            'KZT' => ['min' => 220.00,  'max' => 4500000.00],
            'BYN' => ['min' => 1.30,    'max' => 26000.00],
            'GBP' => ['min' => 0.40,    'max' => 8000.00],
            'JPY' => ['min' => 50,      'max' => 1000000],
            'CNY' => ['min' => 3.50,    'max' => 70000.00]
        ];
        
        return $limits[$currency] ?? $limits['RUB'];
    }

    public static function validateCurrencyAmount($currency, $amount) {
        
        $limits = PaymentHelper::getCurrencyLimits($currency);

        return min(max($amount, $limits['min']), $limits['max']);
    }
}
?>