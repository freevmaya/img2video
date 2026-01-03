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

class Image2VideoBot extends YKassaBot {

    protected $mj_api;
    protected $kling_api;
    protected $leo_api;
    protected $expect;
    protected $taskModel;
    protected $firstStart;
    public $downloadClient;

    protected function initialize() {
        parent::initialize();
        $this->taskModel = new TaskModel();
        $this->downloadClient = new DownloadClient();


        $this->mj_api = new MidjourneyApi(MJ_APIKEY, MJ_HOOK_URL, MJ_ACCOUNTHASH, 
                                $this, $this->taskModel, new MJModel());
        $this->kling_api = new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY, $this->taskModel, 'kling-v1-6', $this);
        $this->leo_api = new LeonardoApi(LEO_APIKEY, $this, $this->taskModel, new LeoTasksModel());
    }

    protected function initUser($update) {
        if (($result = parent::initUser($update)) && $this->getUserId()) {
            $this->firstStart = $this->taskModel->getItem($this->getUser()['id'], 'user_id') == null;
        }
        return $result;
    }

    public function GetUpdates($timeout = 10) {
        parent::GetUpdates($timeout);
        $this->downloadClient->Run();
    }

    protected function runUpdate($update) {
        $this->expect = $this->popSession("expect");
        parent::runUpdate($update);
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

    private function _commandProcessor($command, $chatId, $data) {
        
        $this->stat($chatId, $command, $chatId);
        switch ($command) {
            case 'start':
                $this->start($chatId);
                return true;
            case 'menu':
                $this->showMainMenu($chatId);
                return true;
            case 'task':
                $this->processTask($chatId, $data);
                return true;
            case 'discribe':
                $this->discribe($chatId, $data);
                return true;
            case 'create_image':
                if ($this->isAllowedImage() || $this->firstStart)
                    $this->text2image($chatId);
                else $this->notEnough($chatId);
                return true;
            case 'create_video':
                if ($this->isAllowedVideo())
                    $this->image2video($chatId);
                else $this->notEnough($chatId);
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
            case 'model':
                $this->setModel($chatId, $data);
                return true;
        }
        return false;
    }

    protected function commandProcess($command, $chatId, $messageId, $text) {

        $this->unsetSessions(['expect']);
        if (!$this->_commandProcessor(substr($command, 1), $chatId, $text))
            parent::commandProcess($command, $chatId, $messageId, $text);
    }

    protected function callbackProcess($callback, $chatId, $messageId, $data) {

        $parts = explode('.', $data, 3);
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

    protected function models($chatId) {
        $leo_models = $this->leo_api->getModels();
        $kling_models = $this->kling_api->getModels();

        $leo_model = $this->getSession('leonardo_model');
        $kling_model = $this->getSession('kling_model');

        $list = [];

        if (count($leo_models) > 0) {
            $list[] = [['text'=>'---Leonardo---', 'callback_data' => 'ignore']];
            foreach ($leo_models as $name=>$model) {
                if ($leo_model == $name)
                    $name = '🟢 '.$name;

                $list[] = [['text' => $name, 'callback_data' => "model.leonardo.{$name}"]];
            }
        }

        if (count($kling_models) > 0) {
            $list[] = [['text'=>'---Kling---', 'callback_data' => 'ignore']];

            foreach ($kling_models as $name=>$model) {
                if ($kling_model == $name)
                    $name = '🟢 '.$name;
                $list[] = [['text' => $name, 'callback_data' => "model.kling.{$name}"]];
            }
        }

        $this->Answer($chatId, $this->genContent(Lang('Models'), true, $list));
    }

    protected function setModel($chatId, $data) {
        $this->setSession($data[1].'_model', $data[2]);
        $this->Answer($chatId, $this->genContent(sprintf(Lang('Selected model %s'), $data[2]), true), $this->getSession('lastBotMessageId'));
    }

    protected function replaceUserId($chatId, $text) {
        $newId = intval($text);
        if ($newId == 0)
            $this->popSession("replace_user_id");
        else $this->setSession("replace_user_id", $newId);
    }

    protected function processTask($chatId, $parts) {
        if (count($parts) > 2) {
            $action = $parts[2];
            switch ($action) {
                case 'textToImage':
                    if ($prompt = $this->popSession($parts[1])) {
                        $this->DeleteMessage();
                        if ($this->isAllowedImage() || $this->firstStart)
                            $this->leo_api->generateImage($prompt, ['model' => $this->getSession('leonardo_model')]);
                        else $this->notEnough($chatId);
                    }
                    break;
                case 'generateVideo':
                    $this->DeleteMessage();
                    $this->image2video_photo($chatId, $this->popSession('userText'), $this->popSession('file_id'));
                    break;
            }
        }
    }

    protected function messageProcess($chatId, $messageId, $text) {

        if ($expect = $this->expect) {
            if (method_exists($this, $expect))
                $this->$expect($chatId, $text);
        } else {
            if ($photo = $this->getMessagePhoto()) {

                $this->setSession('userText', $this->currentUpdate['message']['caption']);
                $this->setSession('file_id', $photo['file_id']);
                $this->Answer($chatId, $this->genContent(Lang("What to do about this?"), false, [
                    [['text'=>Lang('Create a video'), 'callback_data' => "task.file_id.generateVideo"]]
                ]));

            } else if (!empty($text)) {
                $this->setSession('prompt', $text);
                $this->Answer($chatId, $this->genContent(Lang("What to do about this?"), false, [
                    [['text'=>Lang('Create an image'), 'callback_data' => "task.prompt.textToImage"]]
                ]));
            }
        }
    }

    protected function klingGenerateVideo($chatId, $prompt) {

        $this->DeleteMessage($chatId, $this->popSession('promptMessageId'));

        $file_id = $this->popSession('file_id');
        if (($image_url = $this->GetFileUrl($file_id)) && !empty($prompt)) {

            if (!empty($image_url) && !empty($prompt)) {
                $this->kling_api->generateVideoFromImage($image_url, $prompt);
            }
            else $this->Wrong($chatId);
        } else $this->Wrong($chatId);
    }

    protected function image2video_photo_prompt($chatId, $prompt) {
        $this->klingGenerateVideo($chatId, $prompt);
    }

    protected function image2video_photo($chatId, $text, $photo_id = null) {

        if ($this->isAllowedVideo()) {

            if (empty($photo_id)) {
                $best_photo = $this->getMessagePhoto();
                $photo_id = $best_photo ? $best_photo['file_id'] : false;
                $text = $this->currentUpdate['message']['caption'] ?? $text;
            }

            if ($photo_id) {

                $this->setSession('file_id', $photo_id); 
                $this->setSession('expect', 'image2video_photo_prompt');     

                $promptList = Lang('imageToVideoPrompts');
                $menu = [];

                if (!empty($text)) {
                    $this->setSession('userText', $text); 
                    $menu[] = [['text' => $text, 'callback_data' => "task.userText.klingVideo"]];
                }

                foreach ($promptList as $i=>$prompt)
                    $menu[] = [['text' => Lang($prompt), 'callback_data' => "task.{$i}.klingVideo"]];


                $result = $this->Answer($chatId, ['text' => Lang("Send a prompt for video"), 'reply_markup'=> json_encode([
                    'inline_keyboard' => $menu
                ])]);

                if (isset($result['message_id']))
                    $this->setSession('promptMessageId', $result['message_id']);

            } else $this->image2video($chatId);

        } else $this->notEnough($chatId);
    }

    protected function replyToMessage($reply, $chatId, $messageId, $text) {
        $this->messageProcess($chatId, $messageId, $text);
    }

    protected function showMainMenu($chatId) {

        $result = $this->Answer($chatId, [
            'text' => Lang('Choose action').':',
            'reply_markup' => json_encode(['inline_keyboard' => $this->startMenuList()])
        ]);
    }

    protected function start($chatId) {
        //$keyboard = array_merge($this->startMenuList(), $this->subscribeTypeList());

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

    protected function mySubscribe($chatId) {

        $tmodel = new TransactionsModel();

        $balance = $this->Balance();

        if (($subscribe = $tmodel->LastSubscribe($this->getUserId())) && ($balance > 0)) {
            $area = (new AreasModel())->getItem($this->getUser()['area_id']);

            $data = json_decode($subscribe['data'], true);
            $stype = (new SubscribeOptions())->getItem($data['type_id']);

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

    protected function textToImage($chatId, $prompt) {
        $this->leo_api->generateImage($prompt, ['model' => $this->getSession('leonardo_model')]);
    }

    protected function textToVideo($chatId, $prompt) {
        $this->Answer($chatId, $this->genContent("Prompt: ".$prompt));
    }

    protected function text2image($chatId) {
        if ($leonardo_model = $this->getSession('leonardo_model'))
            $text = sprintf(Lang("Send a prompt. Current model %s"), $leonardo_model);
        else $text = Lang("Send a prompt");

        $result = $this->Answer($chatId, $this->genContent($text, true));
        $this->setSession("expect", 'textToImage');

        if (isset($result['message_id']))
            $this->setSession('promptMessageId', $result['message_id']);
    }

    protected function image2video($chatId) {
        $this->Answer($chatId, $this->genContent(Lang("Send you photo"), true));
        $this->setSession("expect", 'image2video_photo');
    }

    protected function discribe($chatId, $data) {
        $this->Answer($chatId, $this->genContent(Lang("Send you photo"), true));
        $this->setSession("expect", 'image_to_discribe');
    }

    protected function image_to_discribe($chatId, $text) {
        $best_photo = $this->getMessagePhoto();

        if (($image_url = $this->GetFileUrl($best_photo['file_id']))) {

            if (!empty($image_url)) {
                $this->mj_api->Describe($image_url);
                $this->Answer($chatId, Lang('Sent. This may take several minutes.'));
            }
            else $this->Wrong($chatId);
        }
    }
}
?>