<?
namespace App\Services\API\cycle;

use App\Services\API\cycle\BaseCycle;

class MjCycle extends BaseCycle {


    protected function convertUrl($url, $task) {

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
    }

    protected function prepareFile($task, $response, $path, $result) {
        if (isset($result['url']) && $result['url']) {

            $url = $result['url'];
            $info = pathinfo(explode('?', $url)[0]);
            $filename = $task['hash'].'-'.$response['id'].'.'.$info['extension'];

            $file_path = $path.$filename;

            if (!file_exists($file_path)) {
                $url = $this->convertUrl($url, $task);
                trace($url);
                if ($this->scraperDownload($url, $file_path))
                    return $file_path;
            } else return $file_path;
        }
        return false;
    }

	protected function doProcessResponse($task, $response) {
		if (isset($response['result']) && !empty($response['result'])) {

            $method = 'process_'.$response['type'];

            if (method_exists($this, $method)) {
                if ($response['status'] == 'done') {
                    $result = json_decode(@$response['result'], true);
                    if ($url = @$result['url']) {

                        if ($this->$method($task, $response)) {
                            $this->parent->finishTask($task);
                            $this->finishResponse($response);
                            return true;
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
                                    'id'=>$response['id'], 'fail_count'=>$response['fail_count'] + 1
                                ]);
                                sleep(10);
                            }
                            return false;
                        }
                    } else $this->finishResponse($response);
                    return true;
                } else {
                    $this->finishResponse($response);
                    return true;
                } 
            }
            else {
                $this->finishResponse($response);
                trace_error("The method is missing: {$method}");
                return false;
            }
        } else return true;
	}

    protected function process_upscale($task, $response) {
        $result = json_decode($response['result'], true);
        $hash = $task['hash'];

        if ($file_path = $this->prepareFile($task, $response, RESULT_PATH, $result)) {

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

        if ($file_path = $this->prepareFile($task, $response, RESULT_PATH, $result)) {

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

    protected function process_imagine($task, $response) {

        $result = json_decode($response['result'], true);
        $isProgress = $response['status'] == 'progress';
        $path = $isProgress?PROCESS_PATH:RESULT_PATH;

        $hash = $task['hash'];

        if ($file_path = $this->prepareFile($task, $response, $path, $result)) {

            $info = pathinfo($result['filename']);
            $filename = $hash.'.'.$info['extension'];

            if ($isProgress) {

                $result = $this->parent->sendPhoto($task['chat_id'], $file_path, $filename, Lang("Your image in progress"));
            } else {

                /* Временно отменяем выбор изображения
                $result = $this->parent->sendPhoto($task['chat_id'], $file_path, $filename, Lang('Choose the option you like best'),
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

                if ($result = $this->parent->sendPhoto($task['chat_id'], $file_path, $filename, Lang("Your photo is ready")/*, [
                        [
                            ['text' => Lang('Animate'), 'callback_data' => "task.{$hash}.animate"],
                        ]
                    ]*/)) {

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