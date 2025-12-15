<?

use \Telegram\Bot\FileUpload\InputFile;
use \Telegram\Bot\Exceptions\TelegramResponseException;

class MainCycle {

    private $lastMessageId;
    protected $user;
    protected $modelTask;
    protected $mj_model;
    protected $kling_model;
    protected $api;

    public function __construct($api)
    {
        $this->api          = $api;
        $this->modelTask    = new TaskModel();
        $this->mj_model     = new MJModel();
        $this->kling_model  = new KlingModel();
    }

    protected function initLang($language_code) {
        GLOBAL $lang;
        $fileName = LANGUAGE_PATH.$language_code.'.php';
        if (file_exists($fileName))
            include($fileName);
    }

    protected function updateTask($task) {
        if ($this->user = (new TGUserModel())->getItem($task['user_id']))
            $this->initLang($this->user['language_code']);

        if ($task['service'] == 'mj') {
            $responses = $this->mj_model->getItems(['processed'=>0, 'hash'=>$task['hash']]);

            if (count($responses) == 0) {
                if (HoursDiffDate($task['date']) > 1)
                    $this->finishTask($task, 'failure');
            } else {
                foreach ($responses as $response) {
                    if ($this->mj_doServiceAction($task, $response)) {
                        if ($response['status'] == 'done')
                            $this->finishTask($task);
                        break;
                    } 
                }
            }
        } else if ($task['service'] == 'kling') {
            $responses = $this->kling_model->getItems(['processed'=>0, 'task_id'=>$task['hash']]);

            if (count($responses) == 0) {
                if (HoursDiffDate($task['date']) > 1)
                    $this->finishTask($task, 'failure');
            } else {
                foreach ($responses as $response) {
                    if ($this->kling_doServiceAction($task, $response))
                        break;
                }
            }
        }
    }

    protected function finishTask($task, $state='finished') {        
        $this->modelTask->Update([
            'id'=>$task['id'], 'state'=>$state
        ]);
    }

    protected function kling_doServiceAction($task, $response)
    {
        if (($response['status'] == 'processing') || ($response['status'] == 'submitted')) {
            $this->Message($task['chat_id'], Lang('Your video in progress'));
            $this->kling_finishResponse($response);
        } else if ($response['status'] == 'succeed') {

            if ($response['result_url']) {

                $filename = $task['hash'].'.mp4'; // Какое расширение?

                $file_path = RESULT_PATH.$filename;

                if (file_exists($file_path)) {                    
                    $this->sendMp4($task, $file_path, $filename, Lang('Your video is ready'));
                    $this->finishTask($task);
                    $this->kling_finishResponse($response);
                    return true;
                } else {
                    $downloadResult = downloadFile($response['result_url'], $file_path);
                    if ($downloadResult['success']) {
                        $this->sendMp4($task, $file_path, $filename, Lang('Your video is ready'));
                        $this->finishTask($task);
                        $this->kling_finishResponse($response);
                        return true;
                    } else {
                        if ($response['fail_count'] >= NUMBER_DOWNLOAD_ATTEMPTS) {

                            $this->finishTask($task, 'failure');
                            $this->kling_finishResponse($response);

                            $this->Message($task['chat_id'], ['text' => sprintf(Lang("DownloadFailure"), $task['id']), 'reply_markup'=> json_encode([
                                    'inline_keyboard' => [
                                        [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']]
                                    ]
                                ])
                            ]);
                        } else $this->kling_model->Update([
                            'id'=>$response['id'], 'fail_count'=>$response['fail_count'] + 1
                        ]);
                    }
                }
            } else return false;
        }

        return true;
    }

    protected function kling_finishResponse($response) {        
        $this->kling_model->Update([
            'id'=>$response['id'], 'processed'=>1
        ]);
    }

    protected function mj_finishResponse($response) {        
        $this->mj_model->Update([
            'id'=>$response['id'], 'processed'=>1
        ]);
    }

    public function Update() {
        $tasks = $this->modelTask->getItems(['state'=>'active']);
        if (count($tasks) > 0) {
            foreach ($tasks as $task) {
                $this->updateTask($task);
            }
        }
    }

    protected function sendPhoto($chat_id, $file_path, $filename, $caption, $inline_keyboard = null) {
        if (file_exists($file_path)) {

            $params = [
                'chat_id' => $chat_id,
                'photo' => InputFile::create($file_path, $filename),
                'caption' => $caption,
                'parse_mode' => 'HTML'
            ];

            if ($inline_keyboard)
                $params['reply_markup'] = json_encode([
                    'inline_keyboard' => $inline_keyboard
                ]);

            $photoMessage = $this->api->sendPhoto($params);
            return $photoMessage->getMessageId();
        } else {
            trace_error("File ({$file_path}) is not exists");
            return true;
        }

        return false;
    }

    protected function scraperDownload($url, $file_path) {
        $output = null;
        $command = 'py '.BASEPATH."scraper_download.py \"{$url}\" \"{$file_path}\"";

        exec($command, $output);
        $result = 0;

        if ($output && (count($output) > 0))
            $result = intval($output[count($output) - 1]);
            
        if ($result != 1)
            trace_error($command."; Result: ".$result);

        return $result == 1;
    }

    protected function mj_prepareFile($task, $response, $path, $result) {
        if (isset($result['url']) && $result['url']) {

            $url = $result['url'];
            $info = pathinfo(explode('?', $url)[0]);
            $filename = $task['hash'].'-'.$response['id'].'.'.$info['extension'];

            $file_path = $path.$filename;

            if (!file_exists($file_path)) {
                $url = $this->mj_convertUrl($url, $task);
                trace($url);
                if ($this->scraperDownload($url, $file_path))
                    return $file_path;

                /*
                $downloadResult = downloadFile($url, $file_path);
                if (!$downloadResult['success']) {
                    if (!$this->scraperDownload($url, $file_path)) {
                        $url = $this->mj_convertUrl($url, $task);

                        $downloadResult = downloadFile($url, $file_path);
                        if (!$downloadResult['success'])
                            return $this->scraperDownload($url, $file_path);
                    }
                }*/
            } else return $file_path;
        }
        return false;
    }


    protected function mj_convertUrl($url, $task) {

        //https://cdn.discordapp.com/attachments/1446773822048174091/1447904740305408051/vmaya5252_Cyberpunk_samurai_meditating_in_a_neon-lit_rain-soake_4a163b28-2f4a-449d-b81f-035326b7f489.png?ex=693951de&is=6938005e&hm=ee91699be73bd5abbb889d01d6d67b4ae2c1a2403b88e2f20e7772962490680c& -> https://cdn.midjourney.com/4a163b28-2f4a-449d-b81f-035326b7f489/0_0.png

        //https://cdn.discordapp.com/attachments/1446773822048174091/1447904727693135994/4a163b28-2f4a-449d-b81f-035326b7f489_grid_0. -> https://cdn.midjourney.com/4a163b28-2f4a-449d-b81f-035326b7f489/grid_0.png

        $paterns = [ 
            '/\/([a-z\d-]+)_grid_([\d]+)/'  => '%s/grid_0.png',
            '/_([a-z\d-]+).png\?/'            => '%s/0_%s.png',
            '/within_a__([\w\d-]+)\.webp/'  => 'video/%s/0.mp4'
        ];

        foreach ($paterns as $pattern=>$replace) {
            if (preg_match($pattern, $url, $matches) && (count($matches) > 1)) {

                $choice = 0;
                if (!empty($request_data = $task['request_data']) && 
                    ($request_data = json_encode($request_data, true)) &&
                    isset($request_data['choice']))
                    $choice = $request_data['choice'];

                $relativePath = sprintf($replace, $matches[1], $choice);
                trace($matches);        
                return 'https://cdn.midjourney.com/'.$relativePath;
            }
        }

        /*
        if (str_contains($request_data['endpoint'], 'upscale')) {

            $pattern = '/_([a-z\d-]+).png/';

            if (preg_match($pattern, $url, $matches)) {
                trace($matches);
                $relativePath = $matches[1].'/0_'.$task['choice'].'.png';
            }
            else
                return $url;

        } else if (str_contains($request_data['endpoint'], 'imagine')) {

            $pattern = '/\/([a-z\d-]+)_grid_([\d]+)/';

            if (preg_match($pattern, $url, $matches)) {
                trace($matches);
                $relativePath = $matches[1].'/grid_0.png';
            }
            else
                return $url;

        } else if (str_contains($request_data['endpoint'], 'animate')) {
            $pattern = '/within_a__([\w\d-]+)\.webp/';

            if (preg_match($pattern, $url, $matches))
                $relativePath = 'video/'.$matches[1].'/0.mp4';
            else
                return $url;
        }
        
        return 'https://cdn.midjourney.com/'.$relativePath;
        */
    }

    protected function mj_doServiceAction($task, $response) {
        if (isset($response['result']) && !empty($response['result'])) {
            $method = 'mj_'.$response['type'];
            if (method_exists($this, $method)) {
                if ($response['status'] == 'done') {
                    $result = json_decode(@$response['result'], true);
                    if ($url = @$result['url']) {

                        if ($this->$method($task, $response)) {
                            $this->mj_finishResponse($response);
                            return true;
                        }
                        else {
                            
                            if ($response['fail_count'] >= 6) {

                                $this->finishTask($task, 'failure');
                                $this->mj_finishResponse($response);

                                $this->Message($task['chat_id'], ['text' => sprintf(Lang("DownloadFailure"), $task['id']), 'reply_markup'=> json_encode([
                                        'inline_keyboard' => [
                                            [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']]
                                        ]
                                    ])
                                ]);
                            } else {
                                $this->mj_model->Update([
                                    'id'=>$response['id'], 'fail_count'=>$response['fail_count'] + 1
                                ]);
                                sleep(10);
                            }
                            return false;
                        }
                    } else $this->mj_finishResponse($response);
                    return true;
                } else {
                    $this->mj_finishResponse($response);
                    return true;
                } 
            }
            else {
                $this->mj_finishResponse($response);
                trace_error("The method is missing: {$method}");
                return false;
            }
        } else return true;
    }

    protected function mj_upscale($task, $response) {
        $result = json_decode($response['result'], true);
        $hash = $task['hash'];

        if ($file_path = $this->mj_prepareFile($task, $response, RESULT_PATH, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            if ($result = $this->sendPhoto($task['chat_id'], $file_path, $filename, Lang("Your photo is ready"), [
                    [
                        ['text' => Lang('Animate'), 'callback_data' => "task.{$hash}.animate"],
                    ]
                ])) {

                (new TransactionsModel())->PayUpscale($task['user_id'], [
                    'response_id'=>$response['id'],
                    'hash'=>$hash
                ]);
            }

            return $result;
        }
        return false;
    }

    protected function mj_animate($task, $response) {
        $result = json_decode($response['result'], true);
        $hash = $task['hash'];

        if ($file_path = $this->mj_prepareFile($task, $response, RESULT_PATH, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            if ($result = $this->sendAnimation($task['chat_id'], $file_path, $filename, '🎬 '.Lang("Your video is ready"), [
                    'width' => $result['width'],
                    'height' => $result['height']
                ])) {

                (new TransactionsModel())->PayUpscale($task['user_id'], [
                    'response_id'=>$response['id'],
                    'hash'=>$hash
                ]);
            }

            return $result;
        }
        return false;
    }

    protected function mj_imagine($task, $response) {

        $result = json_decode($response['result'], true);
        $isProgress = $response['status'] == 'progress';
        $path = $isProgress?PROCESS_PATH:RESULT_PATH;

        $hash = $task['hash'];

        if ($file_path = $this->mj_prepareFile($task, $response, $path, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            if (is_numeric($this->lastMessageId))
                $this->api->deleteMessage([
                    'chat_id' => $task['chat_id'],
                    'message_id' => $this->lastMessageId
                ]);

            if ($isProgress) {

                $result = $this->lastMessageId = $this->sendPhoto($task['chat_id'], $file_path, $filename, Lang("Your image in progress"));
            } else {

                /* Временно отменяем выбор изображения
                $result = $this->sendPhoto($task['chat_id'], $file_path, $filename, Lang('Choose the option you like best'),
                    [
                        [
                            ['text' => '1', 'callback_data' => "task.{$hash}.upscale.1"],
                            ['text' => '2', 'callback_data' => "task.{$hash}.upscale.2"]
                        ],[
                            ['text' => '3', 'callback_data' => "task.{$hash}.upscale.3"],
                            ['text' => '4', 'callback_data' => "task.{$hash}.upscale.4"]
                        ]
                    ]
                );
                */

                if ($result = $this->sendPhoto($task['chat_id'], $file_path, $filename, Lang("Your photo is ready")/*, [
                        [
                            ['text' => Lang('Animate'), 'callback_data' => "task.{$hash}.animate"],
                        ]
                    ]*/)) {

                    (new TransactionsModel())->PayUpscale($task['user_id'], [
                        'response_id'=>$response['id'],
                        'hash'=>$hash
                    ]);
                }

                if ($result)
                    $this->lastMessageId = null;
            }
            return $result;
        }
        return false;
    }

    protected function sendMp4($task, $filePath, $filename, $message, $params=[]) {
        if (!$filePath || !file_exists($filePath)) {
            $this->Message($task['chat_id'], '⚠️ '.Lang('Animation not found'));
            return;
        }

        $result = $this->api->sendVideo([
            'chat_id' => $task['chat_id'],
            'video' => fopen($filePath, 'r'),
            'caption' => $message,
            'width' => 512,
            'height' => 512,
            'supports_streaming' => true
        ]);

        if ($result) {            

            (new TransactionsModel())->PayVideo($task['user_id'], [
                'hash'=>$task['hash']
            ]);
        }

        return $result;
    }

    protected function sendAnimation($chatId, $webpFile, $filename, $message, $params=[]) {
        
        if (!$webpFile || !file_exists($webpFile)) {
            $this->Message($chatId, '⚠️ '.Lang('Animation not found'));
            return;
        }
        
        // Проверяем, анимированный ли это WebP
        if (!isAnimatedWebP($webpFile)) {
            // Если не анимированный, отправляем как фото
            return $this->api->sendPhoto(array_merge([
                'chat_id' => $chatId,
                'photo' => InputFile::create($webpFile, $filename),
                'caption' => '🎨 '.Lang("Your photo is ready")
            ], $params));
        }

        $mp4Path = ConvertWebPToMP4($webpPath);
    
        if ($mp4Path) {
            return $this->api->sendVideo([
                'chat_id' => $chatId,
                'video' => fopen($mp4Path, 'r'),
                'caption' => $message,
                'width' => 512,
                'height' => 512,
                'supports_streaming' => true
            ]);
        }

        /*
        $gifPath = ConvertToGif($webpFile);
        if ($gifPath) {
            return $this->api->sendAnimation([
                'chat_id' => $chatId,
                'animation' => InputFile::create($gifPath, $filename.'.gif'),
                'caption' => $message,
                'parse_mode' => 'HTML'
            ]);
        }*/
        
        // Отправляем анимированный WebP
        try {
            $response = $this->api->sendAnimation(array_merge([
                'chat_id' => $chatId,
                'animation' => InputFile::create($webpFile, $filename),
                'caption' => $message,
                'width' => 512,
                'height' => 512,
                'duration' => 10,
                'parse_mode' => 'HTML'
            ], $params));
            
            return $response;
            
        } catch (Exception $e) {
            $this->Message($chatId, "❌ Ошибка отправки: " . $e->getMessage());
            return false;
        }
    }

    protected function error($error) {
        $this->Message(ADMIN_USERID, $error);
    }

    public function Message($chatId, $msg, $parse_mode = 'Markdown') {

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => $parse_mode
        ], is_string($msg) ? ['text' => $msg] : $msg);

        return $this->api->sendMessage($params);
    }
}