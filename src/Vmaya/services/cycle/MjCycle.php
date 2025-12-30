<?
namespace App\Services\API\cycle;

class MjCycle extends BaseCycle {

    public static $paterns = [ 
        '/\/([a-z\d-]+)_grid_([\d]+)/',
        '/_([a-z\d-]+).png\?/',
        '/within_a__([\w\d-]+)\.webp/'
    ];

    public static function parseUrl($url) {

        foreach (MjCycle::$paterns as $pattern) {
            if (preg_match($pattern, $url, $matches) && (count($matches) > 1)) 
                return $matches;
        }
        return null;
    }

    public static function convertUrl($url, $task, $choice = 0) {

        $paterns = [ 
            MjCycle::$paterns[0]  => '%s/grid_0.png',
            MjCycle::$paterns[1]  => '%s/0_%s.png',
            MjCycle::$paterns[2]  => 'video/%s/0.mp4'
        ];

        foreach ($paterns as $pattern=>$replace) {
            if (preg_match($pattern, $url, $matches) && (count($matches) > 1)) {

                if ($task && (!empty($request_data = $task['request_data'])) && 
                    ($request_data = json_encode($request_data, true)) &&
                    isset($request_data['choice']))
                    $choice = $request_data['choice'];

                $relativePath = sprintf($replace, $matches[1], $choice);
                return MJ_BASE_URL.$relativePath;
            }
        }
    }

    public static function prepareFile($task, $response, $path, $result) {
        if (isset($result['url']) && $result['url']) {

            $url = $result['url'];
            $new_url = MJCycle::convertUrl($url, $task);

            $info = pathinfo(explode('?', $new_url)[0]);
            $filename = $task['hash'].'-'.$response['id'].'.'.$info['extension'];

            $file_path = $path.$filename;

            if (!file_exists($file_path)) {
                trace($new_url);
                if (scraperDownload($new_url, $file_path))
                    return $file_path;
            } else return $file_path;
        }
        return false;
    }

    protected function getPreviousResult($id, $hash) {
        return $this->modelReply->getPreviousResponse($id, $hash);
    }

	protected function doProcessResponse($task, $response) {
		if (isset($response['result']) && !empty($response['result'])) {

            $method = 'process_'.$response['type'];

            if (method_exists($this, $method)) {
                if ($response['status'] == 'done') {
                    if ($response['fail_time']) {
                        if (time() - strtotime($response['fail_time']) < 10) // Задержка перед следующей попыткой скачивания
                            return;
                    }

                    $result = json_decode(@$response['result'], true);

                    if ($url = @$result['url']) {

                        if ($this->$method($task, $response)) {
                            $this->parent->finishTask($task);
                            $this->finishResponse($response);
                        }
                        else {
                            
                            if ($response['fail_count'] >= 6) {

                                $this->parent->finishTask($task, 'failure');
                                $this->finishResponse($response);

                                $this->parent->Message($task['chat_id'], ['text' => sprintf(Lang("DownloadFailure"), $task['id']), 'reply_markup'=> json_encode([
                                        'inline_keyboard' => [
                                            [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']]
                                        ]
                                    ])
                                ]);
                            } else {
                                $this->modelReply->Update([
                                    'id'=>$response['id'], 
                                    'fail_count' => $response['fail_count'] + 1,
                                    'fail_time'  => date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                    } else if ($this->$method($task, $response)) {
                        $this->parent->finishTask($task);
                        $this->finishResponse($response);
                    }
                } else {
                    $this->finishResponse($response);
                } 
            }
            else {
                $this->finishResponse($response);
                trace_error("The method is missing: {$method}");
            }
        }
	}

    protected function process_describe($task, $response) {
        if (isset($response['result']) && !empty($response['result'])) {
            $result = json_decode($response['result'], true);
            $this->parent->Message($task['chat_id'], 
                ['text' => $result[0], 
                 'reply_markup'=> json_encode([
                                        'inline_keyboard' => 
                                        [
                                            [['text' => Lang('Upscale'), 'callback_data' => "task.{$task['hash']}.upscale.1"]]
                                        ]
                                    ])
            ]);
            return true;
        }
        return false;
    }

    protected function process_upscale($task, $response) {
        $result = json_decode($response['result'], true);
        $hash = $task['hash'];

        if ($file_path = MjCycle::prepareFile($task, $response, RESULT_PATH, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            if ($result = $this->parent->sendPhoto($task['chat_id'], $file_path, $filename, Lang("Your photo is ready"), [
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

    protected function process_animate($task, $response) {
        $result = json_decode($response['result'], true);
        $hash = $task['hash'];

        if ($file_path = MjCycle::prepareFile($task, $response, RESULT_PATH, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            if ($result = $this->parent->sendMp4($task['chat_id'], $file_path, $filename, '🎬 '.Lang("Your video is ready"), [
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

    protected function process_imagine($task, $response) {


        $prevResponse = $this->getPreviousResult($response['id'], $response['hash']);

        if ($prevResponse && ($prevResponse['result']))
            $result = json_decode($prevResponse['result'], true);
        else $result = json_decode($response['result'], true);

        $isProgress = $response['status'] == 'progress';
        $path = $isProgress?PROCESS_PATH:RESULT_PATH;

        $hash = $task['hash'];

        if ($file_path = MjCycle::prepareFile($task, $response, $path, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            if ($isProgress) {

                $result = $this->parent->sendPhoto($task['chat_id'], $file_path, $filename, Lang("Your image in progress"));
            } else {

                $result = $this->parent->sendPhoto($task['chat_id'], $file_path, $filename, Lang('Choose the option you like best'),
                    [
                        [
                            ['text' => '1', 'callback_data' => "task.{$hash}.upscale.0"],
                            ['text' => '2', 'callback_data' => "task.{$hash}.upscale.1"]
                        ],[
                            ['text' => '3', 'callback_data' => "task.{$hash}.upscale.2"],
                            ['text' => '4', 'callback_data' => "task.{$hash}.upscale.3"]
                        ]
                    ]
                );

                if ($result) {

                    $this->parent->PayUpscale($task['user_id'], [
                        'response_id'=>$response['id'],
                        'hash'=>$hash
                    ]);
                }
            }
            return $result;
        }
        return false;
    }
}