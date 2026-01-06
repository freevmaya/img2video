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
            'leo' => new LeonardoApi(LEO_APIKEY, $this, $this->taskModel, new LeoTasksModel()),
            'kling' => new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY, $this->taskModel, $this)
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
        $this->expect = $this->popSession("expect");
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
            $result[] = [['text' => '🖼️ '.Lang('Create an image'), 'callback_data' => 'create_image']];
        }

        return $result;
    }

    protected function showPMenu($chatId, $text = 'Меню установлено') {

        if ($this->getSession('pmenu_state') != 'show') {
            $result = $this->api->sendMessage([
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
        if ($this->getSession('pmenu_state') == 'show') {
            $result = $this->api->sendMessage([
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
            "Command: {$command}, ".json_encode($data, JSON_FLAGS)."\n";

        switch ($command) {
            case 'show_menu': 
                $this->showPMenu($chatId);
                return true;
            case 'hide_menu': 
                $this->hidePMenu($chatId);
                return true;
            case 'start':
                $this->start($chatId);
                return true;
            case 'menu':
                $this->showMainMenu($chatId);
                return true;
            case 'discribe':
                $this->discribe($chatId, $data);
                return true;
            case 'create_image':
                $this->textToImage(0, $chatId);
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
            case 'info':
                $this->getModelInfo($chatId, $data);
                return true;
            case 'imageToVideo':
                return $this->imageToVideo($data);
            case 'textToImage':
                return $this->textToImage($data);
        }
        return false;
    }

    protected function commandProcess($command, $chatId, $messageId, $text) {
        if (!$this->_commandProcessor(substr($command, 1), $chatId, $text))
            parent::commandProcess($command, $chatId, $messageId, $text);
    }

    protected function callbackProcess($callback, $chatId, $messageId, $data) {

        $parts = explode('.', $data);

        if (DEV) print_r($parts);
        if ($this->_commandProcessor($parts[0], $chatId, $parts))
            return true;
        else return parent::callbackProcess($callback, $chatId, $messageId, $data);
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
            $this->Answer($chatId, $this->genContent($text, true));
        }
    }

    protected function getActualyModelsInfo() {
        $infos = [];
        foreach ($this->generators as $gen_name=>$gen) {
            $m_infos = $gen->getActualyModelsInfo();

            foreach ($m_infos as $name=>$info) {
                $infos[] = array_merge([
                    'gen_index'=>$gen_name,
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

    protected function models($chatId) {

        $list = [];

        $models = $this->getActualyModelsInfo();
        if (count($models) > 0) {
            $cur_type = null;
            foreach ($models as $info) {
                if ($cur_type != $info['type'])
                    $list[] = [['text'=>'-------- '.Lang($info['type']).' -------', 'callback_data' => 'ignore']];

                $cur_type = $info['type'];
                $current_model = $this->getSession($cur_type.'_model');
                $name = $info['name'];
                $gen_index = $info['gen_index'];

                $line = [['text' => $current_model == $info['gen_index'].'.'.$name ? '🟢 '.$name : $name, 'callback_data' => "set_model.{$cur_type}.{$gen_index}_{$name}"]];
                if ($info && isset($info['info']))
                    $line[] = ['text'=>'ⓘ', 'callback_data' => "info.{$cur_type}.{$gen_index}_{$name}"];

                $list[] = $line;
            }
        }

        $this->Answer($chatId, $this->genContent(Lang('Models'), true, $list));
    }

    protected function setModel($chatId, $data) {

        $gen_model = str_replace('_', '.', $data[2]);
        $key = $data[1].'_model';

        $this->setSession($key, $gen_model);

        $this->Answer($chatId, $this->genContent(sprintf(Lang('Selected model %s'), $gen_model), true), $this->getSession('lastBotMessageId'));
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

/*
    protected function processTask($chatId, $parts) {

        if (DEV)
            echo "processTask: ".print_r($parts, true)."\n";

        if (count($parts) > 2) {
            $action = $parts[2];
            switch ($action) {
                case 'textToImage':
                    if ($prompt = $this->popSession($parts[1])) {
                        $this->DeleteMessage();
                        if ($this->isAllowedImage() || $this->firstStart)
                            $this->textToImage($prompt);
                        else $this->notEnough($chatId);
                    }
                    break;
                case 'generateVideo':
                    $this->DeleteMessage();
                    $this->image2video_photo($chatId, $this->popSession('userText'), $this->getSession('file_id'));
                    break;
                case 'imageToVideo': 
                    $this->DeleteMessage();
                    $this->image2video_photo_prompt($chatId, $this->getPrompt($parts[1]));
                    break;
            }
        }
    }*/

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

    protected function callMethod($method, $data) {
        if (method_exists($this, $method)) {
            if ($data) {
                $execute = "\$this->{$method}({$data});";
                eval($execute);
            }
            else $this->$method($this->getCurrentChatId(), $data);
        }
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
            } else {
                if ($photo) {
                    $this->Answer($chatId, $this->genContent(Lang("What to do about this?"), false, [
                        [['text'=>Lang('Create a video'), 'callback_data' => "imageToVideo.1.prompt"]]
                    ]));
                } else if (!empty($text)) {
                    $this->Answer($chatId, $this->genContent(Lang("What to do about this?"), false, [
                        [['text'=>Lang('Create an image'), 'callback_data' => "textToImage.1.prompt"]]
                    ]));
                }
            }
        }
    }

    /*

    protected function findModelGen($model_name) {
        foreach ($this->generators as $generator)
            if ($generator->hasModel($model_name))
                return $generator;
        return false;
    }

    protected function generateVideo($model_name, $imageData, $prompt) {
        if ($gen = $this->findModelGen($model_name)) {
            $gen->generateVideoFromImage($imageData, $prompt, $gen->getDefaultOptions($model_name));
        } else trace_error("Model not found: {$model_name}");
    }

    protected function klingGenerateVideo($chatId, $prompt) {

        $file_id = $this->getSession('file_id');
        if (($image_url = $this->GetFileUrl($file_id)) && !empty($prompt)) {

            if (!($kling_model = $this->getSession('kling_model')))
                $kling_model = 'kling-v1';

            $this->generateVideo($kling_model, [$image_url], $prompt);

        } else {
            trace_error("Empty prompt or file_id");
            $this->Wrong($chatId);
        }
    }

    protected function image2video_photo_prompt($chatId, $prompt) {
        $this->klingGenerateVideo($chatId, $prompt);
    }

    protected function getPrompt($index) {
        if ($index == 'userText')
            return $this->getSession('userText');

        return is_numeric($index) ? Lang('imageToVideoPrompts')[$index] : false;
    }
    protected function _image2video_photo($chatId, $text, $photo_id = null) {

        if ($this->isAllowedVideo()) {

            if (empty($photo_id)) {
                $best_photo = $this->getMessagePhoto();
                $photo_id = $best_photo ? $best_photo['file_id'] : false;
                $text = $this->currentUpdate['message']['caption'] ?? $text;
            }

            if ($photo_id) {

                $this->setSession('file_id', $photo_id);

                $promptList = Lang('imageToVideoPrompts');
                $menu = [];

                if (!empty($text)) {
                    $this->setSession('userText', $text); 
                    $menu[] = [['text' => $text, 'callback_data' => "task.userText.imageToVideo"]];
                }

                foreach ($promptList as $i=>$prompt)
                    $menu[] = [['text' => Lang($prompt), 'callback_data' => "task.{$i}.imageToVideo"]];


                $result = $this->Answer($chatId, ['text' => Lang("Send a prompt for video"), 'reply_markup'=> json_encode([
                    'inline_keyboard' => $menu
                ])]);

                if (isset($result['message_id']))
                    $this->setSession('promptMessageId', $result['message_id']);

            } else $this->askSendPhoto($chatId);

        } else $this->notEnough($chatId);
    }*/

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
        $this->Answer($chatId, $this->genContent(sprintf(Lang("HelpDeskDescription"), $this->getUserId()), true, [
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
            
            $this->Answer($chatId, $this->genContent(sprintf(Lang("Your balance %s"), $balance.' '.@$area['currency'])."\n\n".$limitsText, true));

        } else {

            $this->Answer($chatId, $this->genContent(Lang("No subscription"), true, [
                    [['text' => '⭐'.Lang('Subscription'), 'callback_data' => 'subscribe']]
                ]
            ));
        }
    }

    protected function getCurrentGenModel($type) {
        if ($gen_model = $this->getSession($type.'_model')) {
            $index = explode('.', $gen_model);
            return [$this->generators[$index[0]], $index[1]];
        } else {
            foreach ($this->generators as $gen) {
                if ($model_name = $gen->getDefaultModelName($type))
                    return [$gen, $model_name];
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
        }
        return false;
    }

    protected function Generate($type, $images, $prompt) {
        if (!empty($prompt)) {
            [$gen, $model_name] = $this->getCurrentGenModel($type);
            if ($gen) {

                $info = $gen->getModelInfo($model_name);
                $min_max_images = null;

                if (isset($info['require_images']))
                    $min_max_images = $info['require_images'];

                $images_url = [];
                if (!empty($images))
                    foreach ($images as $image_id)
                        $images_url[] = $this->GetFileUrl($image_id);

                if ($min_max_images && (count($images_url) < $min_max_images[0])) {
                    $this->Wrong("Отсутствует минимальное количество изображений");
                    return false;
                }

                if ($this->isAllowType($type)) {
                    if ($gen->Generate($type, $images_url, $prompt, $model_name))
                        return true;
                }
            }
            else trace_error("Generator not found for type {$type}");
        } else $this->Wrong("Отсутствует промпт");
        return false;
    }

    protected function askSendPrompt($genType, $stage) {

        $params = [];

        if ($gen_model = $this->getSession($genType.'_model'))
            $params['text'] = sprintf(Lang("Send a prompt. Current model %s"), $gen_model);
        else $params['text'] = Lang("Send a prompt");

        $promptList = Lang($genType.'Prompts');

        if (is_array($promptList)) {
            $user_prompt = $this->getSession('prompt');
            $menu = [];

            if (!empty($user_prompt))
                $menu[] = [['text' => $user_prompt, 'callback_data' => "{$genType}.{$stage}.prompt"]];

            foreach ($promptList as $i=>$prompt)
                $menu[] = [['text' => Lang($prompt), 'callback_data' => "{$genType}.{$stage}.{$i}"]];

            $params['reply_markup'] = json_encode(['inline_keyboard' => $menu]);
        }

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
                    $this->Answer($this->getCurrentChatId(), $this->genContent(Lang("Send you photo"), true));
                    $this->setSession("expect", 'imageToVideo(1)');
                break;
            case 1: 
                    $this->askSendPrompt('imageToVideo', 2);
                    $this->setSession("expect", 'imageToVideo(2)');
                break;
            case 2: 
                    $prompt = isset($prompt) ? $prompt : $this->popSession('prompt');
                    return $this->Generate('imageToVideo', $this->popImages(), $prompt);
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

    protected function textToVideo($chatId, $prompt) {
        $this->Generate('textToVideo', [], $prompt);
        //$this->Answer($chatId, $this->genContent("Prompt: ".$prompt));
    }
}
?>