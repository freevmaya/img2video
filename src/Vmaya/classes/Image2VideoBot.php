<?

/*
Команды

menu - главное меню
subscribe - подписка

*/

use \App\Services\API\MidjourneyAPI;
use \App\Services\API\KlingApi;

class Image2VideoBot extends YKassaBot {

    protected $mj_api;
    protected $kling_api;
    protected $expect;
    protected $taskModel;

    protected function initialize() {
        parent::initialize();
        $this->taskModel = new TaskModel();
    }

    protected function initUser($update) {
        if (($result = parent::initUser($update)) && $this->getUserId()) {
            $this->mj_api = new MidjourneyAPI(MJ_APIKEY, MJ_HOOK_URL, MJ_ACCOUNTHASH, 
                                    $this, $this->taskModel, new MJModel());
            $this->kling_api = new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY, $this->taskModel, 'kling-v1', $this);
        }
        return $result;
    }

    protected function runUpdate($update) {
        $this->expect = $this->popSession("expect");
        parent::runUpdate($update);
    }

    protected function startMenuList() {
        $result = [
            //[['text' => '🖼️'.Lang('Create an image'), 'callback_data' => 'create_image']],
            [['text' => '🎥'.Lang('Bring a photo to life'), 'callback_data' => 'create_video']],
            [['text' => '💰'.Lang('Balance'), 'callback_data' => 'MySubscribe']],
            //[['text' => '📊'.Lang('My generations'), 'callback_data' => 'my_generations']],
            [['text' => '⭐'.Lang('Subscription'), 'callback_data' => 'subscribe']],
            [['text' => '💬'.Lang('Help Desk'), 'callback_data' => 'support']],
            [['text' => '❕'.Lang('Agreement'), 'callback_data' => 'agreement']]
        ];

        /*
        if ($this->getOriginUserId() == ADMIN_USERID) {
            $result[] = [['text' => 'Остановить', 'callback_data' => 'stopBot'], ['text' => 'Сменить ID', 'callback_data' => 'changeId']];
        }*/

        return $result;
    }

    protected function callbackProcess($callback, $chatId, $messageId, $data) {

        $parts = explode('.', $data);
        switch ($parts[0]) {
            case 'task':
                $this->processTask($chatId, $parts);
                return true;
            case 'create_image':
                if ($this->isAllowedImage())
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
            default: 
                return parent::callbackProcess($callback, $chatId, $messageId, $data);
        }
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
            $this->Answer($chatId, $text);
        }
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
                case 'animate':
                    $this->mj_api->Animate($parts[1]);
                    break;
                case 'upscale':
                    $this->mj_api->Upscale($parts[1], intval($parts[3]));
                    break;
                case 'klingVideo':
                    if ($parts[1] == 'userText')
                        $prompt = $this->popSession('userText');
                    else $prompt = Lang('imageToVideoPrompts')[intval($parts[1])];
                    $this->klingGenerateVideo($chatId, $prompt, $this->getSession('lastBotMessageId'));
                    break;
            }
        }
    }

    protected function messageProcess($chatId, $messageId, $text) {

        $message = $this->currentUpdate['message'];
        if ($photo = @$message['photo']) {
            if ($this->isAllowedVideo())
                $this->image2video_photo($chatId, $text);
            else $this->notEnough($chatId);
        } else if ($expect = $this->expect) {
            if (method_exists($this, $expect))
                $this->$expect($chatId, $text);
        }
    }

    protected function klingGenerateVideo($chatId, $prompt, $lastMessageId=false) {
        $file_id = $this->popSession('file_id');
        if (($image_url = $this->GetFileUrl($file_id)) && !empty($prompt)) {

            if (!empty($image_url) && !empty($prompt)) {
                $this->kling_api->generateVideoFromImage($image_url, $prompt);
                $this->Answer($chatId, Lang('Sent. This may take several minutes.'), $lastMessageId);
            }
            else $this->Wrong($chatId);
        } else $this->Wrong($chatId);
    }

    protected function image2video_photo_prompt($chatId, $prompt) {
        $this->klingGenerateVideo($chatId, $prompt);
    }

    protected function image2video_photo($chatId, $text) {

        $message = $this->currentUpdate['message'];

        if ($photo = @$message['photo']) {
            $best_photo = $photo[count($photo) - 1];

            $this->setSession('file_id', $best_photo['file_id']); 
            $this->setSession('expect', 'image2video_photo_prompt');     

            $promptList = Lang('imageToVideoPrompts');
            $menu = [];

            $caption = $message['caption'] ?? $text;

            if (!empty($caption)) {
                $this->setSession('userText', $caption); 
                $menu[] = [['text' => $caption, 'callback_data' => "task.userText.klingVideo"]];
            }

            foreach ($promptList as $i=>$prompt)
                $menu[] = [['text' => Lang($prompt), 'callback_data' => "task.{$i}.klingVideo"]];


            $this->Answer($chatId, ['text' => Lang("Send a prompt for video"), 'reply_markup'=> json_encode([
                'inline_keyboard' => $menu
            ])]);
        } else $this->image2video($chatId);
    }

    protected function replyToMessage($reply, $chatId, $messageId, $text) {
        $this->messageProcess($chatId, $messageId, $text);
    }

    protected function commandProcess($command, $chatId, $messageId, $text) {
        switch ($command) {
            case '/start':
                //$this->DeleteMessage($chatId, $messageId);
                $this->start($chatId);
                break;
            case '/menu':
                //$this->DeleteMessage($chatId, $messageId);
                $this->showMainMenu($chatId);
                break;
            default:
                parent::commandProcess($command, $chatId, $messageId, $text);
                break;
        }
    }

    protected function showMainMenu($chatId) {        
        $this->Answer($chatId, [
            'text' => Lang('Choose action').':',
            'reply_markup' => json_encode(['inline_keyboard' => $this->startMenuList()])
        ]);
    }

    protected function start($chatId) {
        $keyboard = array_merge($this->startMenuList(), $this->subscribeTypeList());

        $this->Answer($chatId, ['text' => Lang("BotDescription"), 'reply_markup'=> json_encode([
            'inline_keyboard' => $keyboard
        ])]);
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

            $this->Answer($chatId, ['text' => Lang($msg)]);
        }
    }

    protected function Support($chatId) {

        $link = 'tg://user?id='.SUPPORT_USERID;
        $this->Answer($chatId, ['text' => sprintf(Lang("HelpDeskDescription"), $this->getUserId()), 'reply_markup'=> json_encode([
            'inline_keyboard' => [
                [['text' => Lang("Go to dialogue"), 'url' => $link]]
            ]
        ])]);
    }

    protected function mySubscribe($chatId) {

        $tmodel = new TransactionsModel();

        if ($subscribe = $tmodel->LastSubscribe($this->getUserId())) {
            $area = (new AreasModel())->getItem($this->getUser()['area_id']);

            $data = json_decode($subscribe['data'], true);
            $stype = (new SubscribeOptions())->getItem($data['type_id']);

            $imgPrice = round($stype['price'] / $stype['image_limit']);
            $videoPrice = round($stype['price'] / $stype['video_limit']);

            $limitsText = sprintf(Lang('Enough for %s images or %s videos'), round($this->Balance() / $imgPrice), round($this->Balance() / $videoPrice));
            
            $this->Answer($chatId, ['text' => sprintf(Lang("Your balance %s"), $this->Balance().' '.@$area['currency'])."\n\n".$limitsText]);
        } else $this->Answer($chatId, ['text' => Lang("No subscription"), 'reply_markup'=> json_encode([
            'inline_keyboard' => [
                [['text' => '⭐'.Lang('Subscription'), 'callback_data' => 'subscribe']]
            ]
        ])]);
    }

    protected function textToImage($chatId, $prompt) {
        $this->Answer($chatId, ['text' => "Prompt: ".$prompt]);
        $this->mj_api->generateImage($prompt);
    }

    protected function textToVideo($chatId, $prompt) {
        $this->Answer($chatId, ['text' => "Prompt: ".$prompt]);
    }

    protected function text2image($chatId) {
        $this->Answer($chatId, Lang("Send a prompt"));
        $this->setSession("expect", 'textToImage');
    }

    protected function image2video($chatId) {
        $this->Answer($chatId, Lang("Send you photo"));
        $this->setSession("expect", 'image2video_photo');
    }
}
?>