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
            [['text' => '💰 '.Lang('Balance'), 'callback_data' => 'MySubscribe']],
            //[['text' => '📊 '.Lang('My generations'), 'callback_data' => 'my_generations']],
            [['text' => '⭐ '.Lang('Subscription'), 'callback_data' => 'subscribe']],
            [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']],
            [['text' => '🤖 '.Lang('Models'), 'callback_data' => 'models']],
            [['text' => '❕'.Lang('Agreement'), 'callback_data' => 'agreement']]
        ];

        if ($this->getOriginUserId() == ADMIN_USERID) {
            $result[] = [['text' => 'Остановить', 'callback_data' => 'stopBot'], ['text' => 'Сменить ID', 'callback_data' => 'changeId']];

            //$result[] = [['text' => 'Lonardo Image', 'callback_data' => 'create_image']];
            //$result[] = [['text' => '🖼️ '.Lang('Create an image'), 'callback_data' => 'create_image']];
        }

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

        if (DEV)
            echo("Command: {$command}\n".json_encode($data, JSON_FLAGS)."\n");

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
            case 'selectModelType':
                return $this->selectModelType($data[1], $data[2]);
            case 'selectedModel':
                return $this->setModel($data[1]);
            case 'closeLast':
                return $this->DeleteMessage();
            case 'deleteMessage':
                return $this->DeleteMessage(null, $this->getMessageId($data[1]));
            case 'runPreset':
                return $this->runPreset($data[1]);
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

            if ($expect = $this->expect) {
                if (DEV) echo "Expect: $expect\n";

                [$before, $inside] = extractParenthesesContent($this->expect);
                $method = $before ? $before : $expect;

                $this->callMethod($method, $inside);
            } else $this->whatDo($photo);
        }
    }

    protected function getPreset($presetName) {
        $presets = json_decode(file_get_contents(BASEPATH.'data/presets.json'), true);
        return isset($presets[$presetName]) ? $presets[$presetName] : null;
    }

    protected function preset($presetName) {

        if ($preset = $this->getPreset($presetName)) {

            $this->SendPhoto($preset['caption'], BASEPATH.$preset['image'], [
                [['text'=>Lang('Begin'), 'callback_data' => "runPreset.{$presetName}"],
                $this->closeMessageButton()]
            ]);
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

    protected function getActualyModelInfo($model_index) {
        $models_info = $this->getActualyModelsInfo();
        foreach ($models_info as $info)
            if ($info['index'] == $model_index)
                return [$this->generators[$info['gen_index']], $info];

        return [null, null];
    }

    protected function getActualyModelsInfo() {
        $infos = [];
        foreach ($this->generators as $gen_name=>$gen) {
            $m_infos = $gen->getActualyModelsInfo();

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
            return strcmp($a["type"], $b["type"]);
        });
        return $infos;
    }

    protected function set_model_finish($data) {
        $this->setSession($data[1], $data[2]);

        $this->DeleteMessageByIndex(@$data[3]);
        $this->DeleteMessageByIndex(@$data[4]);
        $this->recall(@$data[4]);
    }

    protected function selectModelType($type, $backMessageIndex) {

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

    protected function pushImage($image_id) {
        if (!($images = $this->getSession('images'))) $images = [];
        $images[] = $image_id;
        $this->setSession('images', $images);
    }

    protected function popImages() {
        return $this->popSession('images');
    }

    protected function getImages() {
        return $this->getSession('images');
    }

    protected function getImagesUrl() {
        $images = $this->getImages();
        $images_url = [];
        if (!empty($images))
            foreach ($images as $image_id)
                $images_url[] = $this->GetFileUrl($image_id);
        return $images_url;
    }

    protected function callMethod($method, $data) {
        if (method_exists($this, $method)) {
            if ($data) {
                $execute = "\$this->{$method}({$data});";
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

            $text .= "\n(".$this->getCurrentModelForText('imageToVideo').')';

            $this->Answer(null, $this->genContent($text, true, [
                [['text'=>Lang('Create a video'), 'callback_data' => "imageToVideo.1.prompt"],
                 ['text'=>Lang('Select model'), 'callback_data' => "selectModelType.imageToVideo.{$this->messageIndex()}"]]
            ]));
        } else if (!empty($text)) {

            $text .= "\n(".$this->getCurrentModelForText('textToImage').')';

            $this->Answer(null, $this->genContent($text, true, [
                [['text'=>Lang('Create an image'), 'callback_data' => "textToImage.1.prompt"],
                 ['text'=>Lang('Select model'), 'callback_data' => "selectModelType.textToImage.{$this->messageIndex()}"]]
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

    protected function start($chatId) {

        $this->showPMenu($chatId, '----');
        $this->Answer($chatId, [
            'text' => Lang("BotDescription"), 
            'reply_markup'=> json_encode(['inline_keyboard' => $this->startMenuList()])
        ]);
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
                    if ($this->isAllowedImage())
                        return true;
                    else $this->notEnough($this->getCurrentChatId());
                break;
            case 'imageToVideo': 
                    if ($this->isAllowedVideo())
                        return true;
                    else $this->notEnough($this->getCurrentChatId());
                break;
            case 'imagesToImage': 
                    if ($this->isAllowedImage())
                        return true;
                    else $this->notEnough($this->getCurrentChatId());
                break;
            default:
                $this->Answer(null, Lang('Unknown type: %s', $type));
                break;
        }
        return false;
    }


    protected function runPreset($presetName, $stage = 0, $subIndex = 0) {
        if ($preset = $this->getPreset($presetName)) {

            $model_data = $preset['subsequence'][$subIndex];
            $model_index = $model_data['model_index'];

            [$gen, $info] = $this->getActualyModelInfo($model_index);
            $type = $info['type'];

            if ($type == 'imagesToImage') {

                switch ($stage) {
                    case 0:

                        $this->Answer(null, $this->genContent(Lang('Send you photo')));
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
                            if ($gen->GeneratePreset($info['name'], $model_data['options'], $full_images)) {
                                $this->setSession('images', []);
                                return true;
                            }
                        }
                        break;
                }
            }
        }
    }

    protected function Generate($type, $images_url, $prompt) {
        if (!empty($prompt)) {
            [$gen, $info] = $this->getCurrentGenModel($type);
            if ($gen) {

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

    protected function askSendPrompt($genType, $stage) {

        $params = [
            'text' => Lang("Send a prompt")
        ];

        $params['text'] .= "\n(".$this->getCurrentModelForText($genType).")";

        $promptList = Lang($genType.'Prompts');
        $menu = [];

        if (is_array($promptList)) {
            $user_prompt = $this->getSession('prompt');

            if (!empty($user_prompt))
                $menu[] = [['text' => $user_prompt, 'callback_data' => "{$genType}.{$stage}.prompt"]];

            foreach ($promptList as $i=>$prompt)
                $menu[] = [['text' => Lang($prompt), 'callback_data' => "{$genType}.{$stage}.{$i}"]];
        }

        $menu[] = [$this->createButton('Select model', "selectModelType.{$genType}.{$this->messageIndex()}"), $this->closeMessageButton()];
        $this->pushRecallMethod($this->messageIndex(), "askSendPrompt('{$genType}', {$stage})");

        $params['reply_markup'] = json_encode(['inline_keyboard' => $menu]);

        return $this->Answer($this->getCurrentChatId(), $params);
    }

    private function parseCommandData($genType, $data) {

        $second = isset($data[2]) ? $data[2] : false;
        if (is_numeric($second)) {
            $offerPrompts = Lang($genType.'Prompts');

            if (is_array($offerPrompts))
                $second = $offerPrompts[$second];
        } else if ($second) 
            $second = $this->popSession($second);

        return [$data[1], $second];
    }

    protected function imageToVideo($stage) {
        if (is_array($stage))
            [$stage, $prompt] = $this->parseCommandData('imageToVideo', $stage);

        switch ($stage) {
            case 0: 
                    $text = Lang("Submit you image");
                    if ($gen_model = $this->getCurrentModelName('imageToVideo'))
                        $text .= "\n".Lang("Current model %s", $gen_model);

                    $this->pushRecallMethod($this->messageIndex(), "imageToVideo({$stage})");
                    $this->Answer($this->getCurrentChatId(), $this->genContent($text, true, [
                        [$this->createButton('Select model', "selectModelType.imageToVideo.{$this->messageIndex()}")]
                    ]));
                    $this->setSession("expect", 'imageToVideo(1)');
                    $this->setSession('images', []);
                break;
            case 1: 
                    $this->askSendPrompt('imageToVideo', 2);
                    $this->setSession("expect", 'imageToVideo(2)');
                break;
            case 2: 
                    $prompt = isset($prompt) ? $prompt : $this->popSession('prompt');
                    return $this->Generate('imageToVideo', $this->getImagesUrl(), $prompt);
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

            $text = Lang("Submit your image").$countText."\n".
                    Lang("Current model %s", $info['name']);

            $this->pushRecallMethod($this->messageIndex(), "imagesToImage(0)");
            $this->Answer(null, $this->genContent($text, true, [
                [$this->createButton('Select model', "selectModelType.imagesToImage.{$this->messageIndex()}")]
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