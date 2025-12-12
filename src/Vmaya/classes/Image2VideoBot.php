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

    protected function initUser($update) {
        parent::initUser($update);
        if ($this->getUserId()) {
            $taskModel = new TaskModel();
            $this->mj_api = new MidjourneyAPI(MJ_APIKEY, MJ_HOOK_URL, MJ_ACCOUNTHASH, 
                                    $this, $taskModel, new MJModel());
            $this->kling_api = new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY, $taskModel);
        }
    }

    protected function runUpdate($update) {
        $this->expect = $this->popSession("expect");
        parent::runUpdate($update);
    }

    protected function startMenuList() {
        $result = [
            //[['text' => '🖼️'.Lang('Create an image'), 'callback_data' => 'create_image']],
            [['text' => '🎥'.Lang('Create a video'), 'callback_data' => 'create_video']],
            [['text' => '💰'.Lang('Balance'), 'callback_data' => 'MySubscribe']],
            [['text' => '📊'.Lang('My generations'), 'callback_data' => 'my_generations']],
            [['text' => '⭐'.Lang('Subscription'), 'callback_data' => 'subscribe']],
            [['text' => '💬'.Lang('Help Desk'), 'callback_data' => 'support']]
        ];

        if ($this->getUserId() == ADMIN_USERID)
            $result[] = [['text' => 'Остановить', 'callback_data' => 'stopBot']];

        return $result;
    }

    protected function callbackProcess($callback, $chatId, $messageId, $data) {

        $parts = explode('.', $data);
        switch ($parts[0]) {
            case 'task':
                $this->processTask($parts);
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
            default: 
                return parent::callbackProcess($callback, $chatId, $messageId, $data);
        }
    }

    protected function processTask($parts) {
        if (count($parts) > 2) {
            $action = $parts[2];
            if ($action == 'animate')
                $this->mj_api->Animate($parts[1]);
            else $this->mj_api->Upscale($parts[1], intval($action));
        }
    }

    protected function messageProcess($chatId, $messageId, $text) {

        if ($expect = $this->expect) {
            if (method_exists($this, $expect)) {
                $this->$expect($chatId, $text);
            }
            else {
                switch ($expect) {
                    case 'image2video_photo':
                        if ($photo = @$this->currentUpdate['message']['photo']) {
                            $best_photo = $photo[count($photo) - 1];
                            $file_url = $this->GetFileUrl($best_photo['file_id']);
                            trace($file_url);
                        } else $this->image2video($chatId);
                        break;
                    default:
                        break;

                }
            }
        }
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

        $link = 'tg://user?id='.ADMIN_USERID;
        $this->Answer($chatId, ['text' => Lang("HelpDeskDescription"), 'reply_markup'=> json_encode([
            'inline_keyboard' => [
                [['text' => Lang("Go to dialogue"), 'url' => $link]]
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