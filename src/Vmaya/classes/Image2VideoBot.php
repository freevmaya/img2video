<?

/*
Команды

menu - главное меню
subscribe - подписка
create_image - создать изображение
create_video - оживить фото

*/

use \App\Services\API\MidjourneyApi;
use \App\Services\API\KlingApi;
use \App\Services\API\LeonardoApi;
use Telegram\Bot\Keyboard\Keyboard;

class Image2VideoBot extends YKassaBot {

    //protected $generators['mj'];
    //protected $generators['kling'];
    //protected $generators['leo'];

    protected $generators;
    protected $expect;
    protected $taskModel;
    protected $firstStart;
    public $downloadClient;
    protected $pmenuMap;

    protected function initialize() {
        parent::initialize();
        $this->taskModel = new TaskModel();
        $this->downloadClient = new DownloadClient();


        $this->generators = [
            //'mj' => new MidjourneyApi(MJ_APIKEY, MJ_HOOK_URL, MJ_ACCOUNTHASH, $this, $this->taskModel, new MJModel()),
            'kling' => new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY, $this->taskModel, $this),
            'leo' => new LeonardoApi(LEO_APIKEY, $this, $this->taskModel, new LeoTasksModel())
        ];

        $this->pmenuMap = [
            '/create_image' => '🖼️',
            '/create_video' => '🎥',
            '/menu' => '📋'
        ];
    }

    protected function initUser($update) {
        $this->firstStart = false;
        return parent::initUser($update);
    }

    protected function doNewUser() {
        $this->firstStart = true;
    }

    public function GetUpdates() {
        parent::GetUpdates();
        $this->downloadClient->Run();
    }

    protected function beforeProcess($chatId, $text) {

        if (!$this->getSession('pmenu_state'))
            $this->showPMenu($chatId, Lang("BotDescription"));
        return parent::beforeProcess($chatId, $text);
    }

    protected function runUpdate($update) {
        $this->expect = $this->getSession("expect");
        parent::runUpdate($update);

        if ($this->firstStart) {
            if ($this->user['language_code'] == 'ru') {
                $startBalance = 40;
                (new TransactionsModel())->Add($this->getUserId(), 'NEW', $startBalance, 'present', ["type_id" => 2]);
                $this->Answer($this->getCurrentChatId(), 
                    sprintf(Lang('We have topped up your account with %s'), 
                        strEnum($startBalance, 'рубл[ь,я,ей]', $this->user['language_code'])));
            }
        }
    }

    protected function startMenuList() {
        $result = [
            [['text' => '🖼️ '.Lang('Create an image'), 'callback_data'  => 'create_image']],
            [['text' => '🎥 '.Lang('Bring a photo to life'), 'callback_data' => 'create_video']],
            [['text' => '📂 '.Lang('Presets'), 'callback_data' => 'presets']],
            [['text' => '💰 '.Lang('Balance'), 'callback_data' => 'MySubscribe']],
            //[['text' => '📊 '.Lang('My generations'), 'callback_data' => 'my_generations']],
            [['text' => '⭐ '.Lang('Subscription'), 'callback_data' => 'subscribe']],
            [['text' => '🤖 '.Lang('Models'), 'callback_data' => 'models']],
            [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']],
            [['text' => '❕'.Lang('Agreement'), 'callback_data' => 'agreement']]
        ];

        /*
        if ($this->getOriginUserId() == ADMIN_USERID) {
            $result[] = [['text' => 'Остановить', 'callback_data' => 'stopBot'], ['text' => 'Сменить ID', 'callback_data' => 'changeId']];
        }*/

        return $result;
    }

    protected function showPMenu($chatId, $text = 'Меню установлено') {

        if ($this->getSession('pmenu_state') != 'show') {
            $result = $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => json_encode([
                    'keyboard' => [ 
                        [
                            ['text' => $this->pmenuMap['/create_image'].' '.Lang('Image')],
                            ['text' => $this->pmenuMap['/create_video'].' '.Lang('Video')]
                        ],
                        [
                            ['text' => $this->pmenuMap['/menu'].' '.Lang('More')]
                        ]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false,
                    'selective' => false
                ], JSON_FLAGS)
            ]);

            if (isset($result['message_id'])) {
                $this->setSession('pmenu_state', 'show');
                return $result;
            }
        }
        return false;
    }

    protected function hidePMenu($chatId, $text = 'Меню удалено') {
        if ($this->getSession('pmenu_state') != 'hide') {
            $result = $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => json_encode([
                    'remove_keyboard' => true,
                    'selective' => false
                ], JSON_FLAGS)
            ]);

            if (isset($result['message_id'])) {
                $this->setSession('pmenu_state', 'hide');
                return $result;
            }
        }

        return false;
    }

    private function _commandProcessor($command, $chatId, $data = null) {
        
        $this->stat($chatId, $command, $chatId);

        if (DEV) echo("Command: {$command}\n".json_encode($data, JSON_FLAGS)."\n");

        $commandParts = explode(' ', $command, 2);

        switch ($commandParts[0]) {
            case 'show_menu': 
                $this->showPMenu($chatId);
                return true;
            case 'hide_menu': 
                $this->hidePMenu($chatId);
                return true;
            case 'start':
                $this->start($chatId);
                return true;
            case 'preset':
                $this->preset(isset($commandParts[1]) ? $commandParts[1] : null);
                return true;
            case 'menu':
                $this->showMainMenu($chatId);
                return true;
            case 'discribe':
                $this->discribe($chatId, $data);
                return true;
            case 'create_image':
                $this->create_image(0, $chatId);
                return true;
            case 'create_video':
                $this->imageToVideo(0);
                return true;
            case 'support':
                $this->Support($chatId);
                return true;
            case 'stopBot':
                $this->stopBot($chatId);
                return true;
            case 'changeId':
                $this->changeId($chatId);
                return true;
            case 'agreement':
                $this->agreement($chatId);
                return true;
            case 'models':
                $this->models($chatId);
                return true;
            case 'set_model':
                $this->setModel($chatId, $data);
                return true;
            case 'set_model_finish':
                $this->set_model_finish($data);
                return true;
            case 'info':
                $this->getModelInfo($chatId, $data);
                return true;
            case 'imageToVideo':
                return $this->imageToVideo($data);
            case 'textToImage':
                return $this->textToImage($data);
            case 'imagesToImage':
                return $this->imagesToImage($data);
            case 'selModel':
                return $this->selModel($data[1], $data[2]);
            case 'selectedModel':
                return $this->setModel($data[1]);
            case 'closeLast':
                return $this->DeleteMessage();
            case 'deleteMessage':
                return $this->DeleteMessage(null, $this->getMessageId($data[1]));
            case 'runPreset':
                return $this->runPreset($data[1]);
            case 'presets':
                return $this->presets();
        }
        return false;
    }

    protected function commandProcess($command, $chatId, $messageId, $text) {
        if (!$this->_commandProcessor(substr($command, 1), $chatId, $text))
            parent::commandProcess($command, $chatId, $messageId, $text);
    }

    protected function callbackProcess($callback, $chatId, $messageId, $data) {

        $parts = explode('.', $data);

        if ($this->_commandProcessor($parts[0], $chatId, $parts))
            return true;
        else return parent::callbackProcess($callback, $chatId, $messageId, $data);
    }

    protected function messageProcess($chatId, $messageId, $text) {

        if (!$this->pMenuProcess($chatId, $text)) {

            if ($photo = $this->getMessagePhoto()) {
                $this->pushImage($photo['file_id']);


                if ($caption = $this->currentUpdate->getMessage()->get('caption'))               
                    $this->setSession('prompt', $caption);
            } else 
                if (!empty($text))
                    $this->setSession('prompt', $text);

            if (!$this->processExpect($this->expect))
                $this->whatDo($photo);
        }
    }

    protected function processExpect($expect) {
        if ($expect) {
            if (DEV) echo "Expect: $expect\n";

            [$before, $inside] = extractParenthesesContent($expect);
            $method = $before ? $before : $expect;

            $this->callMethod($method, $inside);
            return true;
        }
        return false;
    }

    protected function preset($presetName) {

        if ($preset = $this->getPreset($presetName)) {

            if (isset($preset['image'])) {
                $this->SendPhoto($preset['caption'], BASEPATH.$preset['image'], [
                    [['text'=>Lang('Begin'), 'callback_data' => "runPreset.{$presetName}"],
                    $this->closeMessageButton()]
                ]);
            } else if (isset($preset['video'])) {

                $this->SendVideo($preset['caption'], BASEPATH.$preset['video'], [
                    [['text'=>Lang('Begin'), 'callback_data' => "runPreset.{$presetName}"],
                    $this->closeMessageButton()]
                ]);
            }
        } else $this->Answer(null, $this->genContent(Lang('Preset "%s" not found', $presetName), true));
    }

    protected function initAdmin($user, $update) {
        $user = parent::initAdmin($user, $update);

        if ($newId = $this->getSession("replace_user_id"))
            $user['id'] = $newId;

        return $user;
    }

    protected function changeId($chatId) {

        $this->Answer($chatId, Lang("Enter new user ID"));
        $this->setSession("expect", 'replaceUserId');
    }

    protected function agreement($chatId) {
        $fileName = LANGUAGE_PATH.$this->user['language_code'].DS.'agreement.txt';
        if (file_exists($fileName)) {            
            $text = file_get_contents($fileName);
            $this->Answer($chatId, $this->genContent($text, 'Close'));
        }
    }

    protected function getActualyModelInfo($model_index, $enabledOnly = true) {
        $models_info = $this->getActualyModelsInfo($enabledOnly);
        foreach ($models_info as $info)
            if ($info['index'] == $model_index)
                return [$this->generators[$info['gen_index']], $info];

        return [null, null];
    }

    protected function getActualyModelsInfo($enabledOnly = true) {
        $infos = [];
        foreach ($this->generators as $gen_name=>$gen) {
            $m_infos = $gen->getActualyModelsInfo($enabledOnly);

            foreach ($m_infos as $name=>$info) {
                $infos[] = array_merge([
                    'gen_index'=>$gen_name,
                    'index'=> str_replace('.', '_', $gen_name.'_'.$name),
                    'name'=>$name
                ], $info);
            }
        }

        usort($infos, function ($a, $b)
        {
            if (isset($a["type"]) && isset($b["type"]))
                return strcmp($a["type"], $b["type"]);
            else return 0;
        });
        return $infos;
    }

    protected function set_model_finish($data) {
        $this->setSession($data[1], $data[2]);

        $this->DeleteMessageByIndex(@$data[3]);
        $this->DeleteMessageByIndex(@$data[4]);
        $this->recall(@$data[4]);
    }

    protected function selModel($type, $backMessageIndex) {

        $list = [];

        $models = $this->getActualyModelsInfo();
        if (count($models) > 0) {
            foreach ($models as $info)
                if ($type == $info['type'])
                    $list[] = [['text' => $info['name'], 
                'callback_data' => "set_model_finish.{$type}.{$info['index']}.{$this->messageIndex()}.{$backMessageIndex}"]];

            $this->Answer(null, $this->genContent(Lang('Models'), true, $list));
        }
    }

    protected function models($chatId, $callbackMessageId = null) {

        $list = [];

        $models = $this->getActualyModelsInfo();
        if (count($models) > 0) {
            $cur_type = null;
            $models_tree = [];

            foreach ($models as $info) {
                if ($cur_type != $info['type'])
                    $models_tree[$info['type']] = [];
                
                $models_tree[$info['type']][] = $info;
                $cur_type = $info['type'];
            }

            $maxchars = 20;

            foreach ($models_tree as $type=>$items) {
                $list[] = [['text'=>'-------- '.Lang($type).' -------', 'callback_data' => 'ignore']];
                $current_model_index = $this->getSession($type);

                $count = count($items);
                $line = [];
                $chars = 0;

                for ($i=0; $i<$count; $i++) {

                    $info       = $items[$i];
                    $name       = $info['name'];

                    if ($chars >= $maxchars) {
                        if (count($line) > 0)
                            $list[] = $line;
                        $line = [];
                        $chars = 0;
                    }

                    $line[] = ['text' => $current_model_index == $info['index'] ? '🟢 '.$name : $name, 'callback_data' => "set_model.{$type}.{$info['index']}"];

                    $chars += strlen($name);

                    if ($i == $count - 1)
                        $list[] = $line;
                }
            }
        }

        $this->Answer($chatId, $this->genContent(Lang('Models'), 'Close', $list), $callbackMessageId);
    }

    protected function callbackMessageId() {

        $messageId = null;
        if ($callback = @$this->currentUpdate['callback_query']) {
            $messageId = $callback['message']['message_id'];
        }

        return $messageId;
    }

    protected function setModel($chatId, $data) {

        $this->setSession($data[1], $data[2]);
        $this->models($chatId, $this->callbackMessageId());

        //$this->Answer($chatId, $this->genContent(sprintf(Lang('Selected model %s'), $gen_model), true), $messageId);
    }

    protected function getModelInfo($chatId, $data) {
        $infoRec = $this->generators[$data[1]]->getModelInfo($data[2]);
        $info = $infoRec['info'];

        $this->Answer($chatId, $this->genContent(sprintf(Lang('Information about the "%s" model'), $data[2])."\n\n".$info, true), $this->getSession('lastBotMessageId'));
    }

    protected function replaceUserId($chatId, $text) {
        $newId = intval($text);
        if ($newId == 0)
            $this->popSession("replace_user_id");
        else $this->setSession("replace_user_id", $newId);
    }

    protected function pMenuProcess($chatId, $text) {
        if (!empty($text)) {
            foreach($this->pmenuMap as $command=>$char) {
                if (substr($text, 0, strlen($char)) == $char) {
                    $this->commandProcess($command, $chatId, null, $text);
                    return true;
                }
            }
        }
        return false;
    }

    protected function callMethod($method, $data) {
        if (method_exists($this, $method)) {
            if ($data) {
                $execute = "\$this->{$method}({$data});";
                if (DEV) echo "Execute: $execute\n";

                eval($execute);
            }
            else $this->$method($this->getCurrentChatId(), $data);
        }
    }

    //Какую магию хотите сделать?
    protected function whatDo($photoOrText = false) {

        $text = Lang("What to do about this?");
        $arg = $photoOrText?'true':'false';
        $this->pushRecallMethod($this->messageIndex(), "whatDo({$arg})");

        if ($photoOrText) {

            $text .= "\n\n(".$this->getCurrentModelForText('imageToVideo').')';

            $this->Answer(null, $this->genContent($text, true, [
                [['text'=>Lang('Create a video'), 'callback_data' => "imageToVideo.1.prompt"],
                 ['text'=>Lang('Select model'), 'callback_data' => "selModel.imageToVideo.{$this->messageIndex()}"]]
            ]));
        } else if (!empty($text)) {

            $text .= "\n\n(".$this->getCurrentModelForText('textToImage').')';

            $this->Answer(null, $this->genContent($text, true, [
                [['text'=>Lang('Create an image'), 'callback_data' => "textToImage.1.prompt"],
                 ['text'=>Lang('Select model'), 'callback_data' => "selModel.textToImage.{$this->messageIndex()}"]]
            ]));
        }
    }

    protected function replyToMessage($reply, $chatId, $messageId, $text) {
        $this->messageProcess($chatId, $messageId, $text);
    }

    protected function showMainMenu($chatId) {

        $result = $this->Answer($chatId, [
            'text' => Lang('Menu'),
            'reply_markup' => json_encode([
                'inline_keyboard' => $this->startMenuList()])
        ]);
    }

    protected function submitFirstPreset() {
        $this->preset(null);
    }

    protected function start($chatId) {

        $this->showPMenu($chatId, '----');
        $this->Answer($chatId, [
            'text' => Lang("BotDescription"), 
            'reply_markup'=> json_encode(['inline_keyboard' => $this->startMenuList()])
        ]);
        $this->submitFirstPreset();
    }

    function gitPull($branch = 'main', $path = null) {
        $path = $path ?: __DIR__;
        
        $command = "cd {$path} && git pull origin {$branch} 2>&1";
        
        // Безопасное выполнение
        $output = [];
        $return_var = 0;
        
        exec($command, $output, $return_var);
        
        return [
            'success' => $return_var === 0,
            'output' => implode("\n", $output),
            'return_code' => $return_var
        ];
    }

    private function stopMJCycle() {
        $file_path = BASEPATH.'cron/mj_cycle.pid';
            if (file_exists($file_path))
                return unlink($file_path);
        return true;
    }

    protected function stopBot($chatId) {
        GLOBAL $lock;
        if ($lock) {
            $result = $this->stopMJCycle() && $lock->release();

            $msg = $result ? 'Successful stop' : 'Failure stop';

            if ($result) {
                $git_result = $this->gitPull('main', BASEPATH);
                $msg .= ' and git pull '.($git_result['success'] ? 'success!' : 'failure');
            }

            $this->Answer($chatId, $this->genContent(Lang($msg), true));
        }
    }

    protected function Support($chatId) {

        $link = 'tg://user?id='.SUPPORT_USERID;
        $this->Answer($chatId, $this->genContent(sprintf(Lang("HelpDeskDescription"), $this->getUserId()), 'Close', [
            [['text' => Lang("Go to dialogue"), 'url' => $link]]
        ]));
    }

    protected function MySubscribe($chatId) {

        $tmodel = new TransactionsModel();

        $balance = $this->Balance();
        $subscribe = $tmodel->LastSubscribe($this->getUserId());

        if ($balance > 0) {
            if ($subscribe) {
                $data = json_decode($subscribe['data'], true);
                $stype = (new SubscribeOptions())->getItem($data['type_id']);
            } else {
                $stype = (new SubscribeOptions())->getItem(null);
            }

            $area = (new AreasModel())->getItem($this->getUser()['area_id']);

            $imgPrice = round($stype['price'] / $stype['image_limit']);
            $videoPrice = round($stype['price'] / $stype['video_limit']);

            $limitsText = sprintf(Lang('Enough for %s images or %s videos'), round($balance / $imgPrice), round($balance / $videoPrice));
            
            $this->Answer($chatId, $this->genContent(sprintf(Lang("Your balance %s"), $balance.' '.@$area['currency'])."\n\n".$limitsText, 'Close'));

        } else {

            $this->Answer($chatId, $this->genContent(Lang("No subscription"), 'Close', [
                    [['text' => '⭐'.Lang('Subscription'), 'callback_data' => 'subscribe']]
                ]
            ));
        }
    }

    protected function getCurrentGenModel($type) {
        if ($gen_model_index = $this->getSession($type)) {
            return $this->getActualyModelInfo($gen_model_index);
        } else {
            $modelsInfo = $this->getActualyModelsInfo();

            foreach ($this->generators as $gen) {
                if ($model_name = $gen->getDefaultModelName($type)) {
                    foreach ($modelsInfo as $info)
                        if ($info['name'] == $model_name)
                            return [$gen, $info];
                }
            }
        }

        return [null, null];
    }

    protected function isAllowType($type) {
        switch ($type) {
            case 'textToImage': 
                    $diff = $this->isAllowedImage();
                break;
            case 'imagesToImage':
                    $diff = $this->isAllowedImage();
                break;
            case 'imageToVideo': 
                    $diff = $this->isAllowedVideo();
                break;
            default:
                $msg = Lang('Unknown type: %s', $type);
                trace_error($msg);
                $this->Answer(null, $msg);
                break;
        }

        if ($diff >= 0)
            return true;
        else {
            $this->setSession('after_payment', time().' '.$this->expect);
            $this->notEnough(-$diff);
        }
        return false;
    }

    protected function handleSuccessfulPayment($chat_id, $payment) {
        parent::handleSuccessfulPayment($chat_id, $payment);

        echo "handleSuccessfulPayment\n";
        echo 'getSession: '.$this->getSession('after_payment')."\n";

        if ($after_payment = $this->popSession('after_payment')) {

            $after_payment = explode(' ', $after_payment, 2);

            $diff_time = time() - intval($after_payment[0]);
            echo "Diff: $diff_time, Call {$after_payment[1]}\n";

            if ($diff_time < 60 * 60) // Обрабатывать сохраненный expect только в течении часа
                $this->processExpect($after_payment[1]);
        }
    }

    protected function presets() {
        $presets = json_decode(file_get_contents(BASEPATH.'data/presets.json'), true);
        $list = [];
        foreach ($presets as $key=>$preset)
            if (!isset($preset['test']) || ($this->getUserId() == ADMIN_USERID))
                $list[] = [['text'=>'⭐ '.$preset['name'], 'callback_data'=>"preset {$key}"]];

        $this->Answer(null, $this->genContent(Lang('Presets'), 'Close', $list));
    }


    protected function runPreset($presetName, $stage = 0, $subIndex = 0) {
        if ($preset = $this->getPreset($presetName)) {

            $model_data     = $preset['subsequence'][0];
            [$gen, $info]   = $this->getActualyModelInfo($model_data['model_index'], false);
            $type           = $info['type'];

            switch ($stage) {
                case 0:

                    $this->Answer(null, $this->genContent(Lang('Send your photo')));
                    $this->setSession("expect", "runPreset('{$presetName}', 1, {$subIndex})");
                    $this->setSession('images', []);

                    break;
                case 1:

                    $images_url = $this->getImagesUrl();
                    $images = [];
                    $n = 0;

                    $full_images = array_merge([], $model_data['images']);

                    for ($i=0; $i<count($full_images); $i++) {
                        if (!$full_images[$i]['value']) {

                            if ($n < count($images_url)) {
                                $full_images[$i]['value'] = $images_url[$n];
                                $n++;
                            } else {
                                $this->Wrong("Отсутствует минимальное количество изображений");
                                return false;
                            }

                        }
                    }

                    if ($this->isAllowType($type)) {
                        if ($gen->GeneratePreset($info['name'], $presetName, $model_data['options'], $full_images)) {
                            $this->setSession('images', []);
                            return true;
                        }
                    }
                    break;
            }
        }
    }

    protected function Generate($type, $images_url, $prompt) {
        if (!empty($prompt)) {
            [$gen, $info] = $this->getCurrentGenModel($type);
            if ($gen) {

                $images_url = array_filter($images_url);

                $min_max_images = null;

                if (isset($info['require_images']))
                    $min_max_images = $info['require_images'];

                if ($min_max_images && (count($images_url) < $min_max_images[0])) {
                    $this->Wrong("Отсутствует минимальное количество изображений");
                    return false;
                }

                if ($this->isAllowType($type)) {
                    if ($gen->Generate($type, $images_url, $prompt, $info['name'])) {
                        $this->setSession('images', []);
                        return true;
                    }
                }
            }
            else trace_error("Generator not found for type {$type}");
        } else $this->Wrong("Отсутствует промпт");
        return false;
    }

    public function getDefaultModelName($type) {
        foreach ($this->generators as $gen) {
            if ($result = $gen->getDefaultModelName($type))
                return $result;
        }
        return null;
    }

    protected function getCurrentModelName($genType) {
        if ($gen_model_index = $this->getSession($genType)) {
            [$gen, $info] = $this->getActualyModelInfo($gen_model_index);
            return $info['name'];
        }
        return $this->getDefaultModelName($genType);
    }

    protected function getCurrentModelForText($genType) {

        if ($gen_model = $this->getCurrentModelName($genType))
            return Lang("Current model %s", $gen_model);

        foreach ($this->generators as $gen) {
            if ($gen_model = $gen->getDefaultModelName($genType))
                return Lang("Current model %s", $gen_model);
        }
    }

    protected function askSendPrompt($genType, $stage, $ext = null) {

        $params = [
            'text' => Lang("Send a prompt")
        ];

        $params['text'] .= "\n\n(".$this->getCurrentModelForText($genType).")";

        $promptList = Lang($genType.'Prompts');
        $menu = [];

        if (is_array($promptList)) {
            $user_prompt = $this->getSession('prompt');
            $extaddstr = ($ext ? '.'.$ext: '');

            if (!empty($user_prompt))
                $menu[] = [['text' => $user_prompt, 'callback_data' => "{$genType}.{$stage}.prompt".$extaddstr]];

            foreach ($promptList as $i=>$prompt)
                $menu[] = [['text' => Lang($prompt), 'callback_data' => "{$genType}.{$stage}.{$i}".$extaddstr]];
        }

        $menu[] = [$this->createButton('Select model', "selModel.{$genType}.{$this->messageIndex()}"), $this->closeMessageButton()];

        $callstr = $ext ? "askSendPrompt('{$genType}', {$stage}, {$ext})" : "askSendPrompt('{$genType}', {$stage})";

        $this->pushRecallMethod($this->messageIndex(), $callstr);

        $params['reply_markup'] = json_encode(['inline_keyboard' => $menu]);

        return $this->Answer($this->getCurrentChatId(), $params);
    }

    private function parseCommandData($genType, $data) {

        $second = isset($data[2]) ? $data[2] : false;
        if (is_numeric($second)) {
            $offerPrompts = Lang($genType.'Prompts');

            if (is_array($offerPrompts) && ($second < count($offerPrompts)))
                $second = $offerPrompts[$second];
        } else if ($second) 
            $second = $this->popSession($second);

        return [$data[1], $second];
    }

    protected function imageToVideo($data) {

        //if (DEV) echo "imageToVideo: ".json_encode($data)."\n";

        if (is_array($data))
            [$stage, $prompt] = $this->parseCommandData('imageToVideo', $data);
        else $stage = $data;

        switch ($stage) {
            case 0: 
                    $text = Lang("Send your photo");
                    if ($gen_model = $this->getCurrentModelName('imageToVideo'))
                        $text .= "\n\n(".Lang("Current model %s", $gen_model).')';

                    $this->pushRecallMethod($this->messageIndex(), "imageToVideo({$stage})");
                    $this->Answer($this->getCurrentChatId(), $this->genContent($text, true, [
                        [$this->createButton('Select model', "selModel.imageToVideo.{$this->messageIndex()}")]
                    ]));
                    $this->setSession("expect", 'imageToVideo(1)');
                    $this->setSession('images', []);
                break;
            case 1: 

                    $ext_data = isset($data[2]) ? $data[2] : false;

                    if ($ext_data) {
                        $this->askSendPrompt('imageToVideo', 2, $ext_data);
                        $this->setSession("expect", "imageToVideo(['imageToVideo', 2, 'prompt', {$ext_data}])");
                    } else {
                        $this->askSendPrompt('imageToVideo', 2);
                        $this->setSession("expect", "imageToVideo(2)");
                    }
                break;
            case 2: 
                    $prompt = isset($prompt) ? $prompt : $this->popSession('prompt');
                    $message_index = isset($data[3]) && is_numeric($data[3]) ? intval($data[3]) : false;

                    // Если есть номер сообщения, то считываем изображения от туда
                    if ($message_index) 
                        $images = [$this->getMessageImageUrl($message_index)];
                    else $images = $this->getImagesUrl();

                    //if (DEV) print_r($images);

                    return $this->Generate('imageToVideo', $images, $prompt);
                break;
        }
    }

    protected function textToImage($stage) {
        if (is_array($stage))
            [$stage, $prompt] = $this->parseCommandData('textToImage', $stage);

        switch ($stage) {
            case 0:
                    $this->askSendPrompt('textToImage', 1);
                    $this->setSession("expect", 'textToImage(1)');
                break;
            case 1:
                    $prompt = isset($prompt) ? $prompt : $this->popSession('prompt');
                    return $this->Generate('textToImage', [], $prompt);
                break;
        }
    }

    protected function imagesToImage($stage) {
        if (is_array($stage))
            [$stage, $prompt] = $this->parseCommandData('imagesToImage', $stage);

        [$gen, $info] = $this->getCurrentGenModel('imagesToImage');
        $count_images = count($this->getSession('images'));
        $require_images = isset($info['require_images']) ? $info['require_images'][0] : 1;

        if ($count_images < $require_images) {

            $countText = $count_images == 0 ? '' : ("\n".sprintf(Lang("Loaded %s of %s"), $count_images, $require_images));

            $text = Lang("Send your photo").$countText."\n\n(".
                    Lang("Current model %s", $info['name']).')';

            $this->pushRecallMethod($this->messageIndex(), "imagesToImage(0)");
            $this->Answer(null, $this->genContent($text, true, [
                [$this->createButton('Select model', "selModel.imagesToImage.{$this->messageIndex()}")]
            ]));

            $this->setSession("expect", "imagesToImage(0)");
        } else {
            $prompt = isset($prompt) ? $prompt : $this->popSession('prompt');
            if (empty($prompt)) {

                $this->askSendPrompt('imagesToImage', 2);
                $this->setSession("expect", 'imagesToImage(2)');
            } else {

                return $this->Generate('imagesToImage', $this->getImagesUrl(), $prompt);
            }
        }
    }

    protected function create_image() {
        [$gen, $imagesToImage_model] = $this->getCurrentGenModel('imagesToImage');
        [$gen, $textToImage_model] = $this->getCurrentGenModel('textToImage');

        $this->setSession('images', []);
        $this->setSession('prompt', '');

        if ($imagesToImage_model && $textToImage_model) {
            $this->Answer(null, $this->genContent(Lang("What kind of magic do you want to do?"), false, [
                [
                    $this->createButton('imagesToImage', "imagesToImage.0.prompt"),
                    $this->createButton('textToImage', "textToImage.0.prompt")
                ],
                [$this->closeMessageButton()]
            ]));
        } else $this->textToImage(0);
    }

    protected function textToVideo($chatId, $prompt) {
        $this->Generate('textToVideo', [], $prompt);
        //$this->Answer($chatId, $this->genContent("Prompt: ".$prompt));
    }
}
?>