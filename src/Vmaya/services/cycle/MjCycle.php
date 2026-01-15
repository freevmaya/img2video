<?
namespace App\Services\API\cycle;

class MjCycle extends BaseCycle {

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
                        $this->$method($task, $response);
                    } else if ($this->$method($task, $response))
                        $this->parent->finishTask($task);
                }

                $this->finishResponse($response); 
            }
            else {
                $this->finishResponse($response);
                trace_error("The method is missing: {$method}");
            }
        } else {
            if ($response['status'] != 'done')
                $this->finishResponse($response);
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

        $fileData = \MjModel::GetResultFile($task, $response);
        $task_result = json_decode($response['result'], true);

        $this->parent->downloadClient->AddTask(function($record, $data) {

            $task       = $data['task'];
            $response   = $data['response'];
            $hash       = $task['hash'];
            $state      = $record['state'];
            $_this      = $data['this'];
            $parent     = $_this->parent;

            $parent->finishTask($task, $state);

            if ($state == 'finished') {
                if ($result = $parent->sendPhoto($data['task']['chat_id'], $data['file_path'], $data['file_name'], Lang("Your photo is ready"), [
                        [
                            ['text' => Lang('Animate'), 'callback_data' => "{$hash}.animate"],
                        ]
                    ])) {

                    (new TransactionsModel())->PayUpscale($data['task']['user_id'], [
                        'response_id'=>$data['response']['id'],
                        'hash' => $hash
                    ]);
                }
            } else {
                $parent->Message($task['chat_id'], Lang('Fail download image'));
            }

        }, $fileData['file_url'], $fileData['file_path'], array_merge([
            'this' => $this,
            'task' => $task,
            'response' => $response,
            'task_result' => $task_result
        ], $fileData));

        /*
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
        */
    }

    protected function process_animate($task, $response) {

        $fileData = \MjModel::GetResultFile($task, $response);
        $task_result = json_decode($response['result'], true);

        $this->parent->downloadClient->AddTask(function($record, $data) {

            $task       = $data['task'];
            $response   = $data['response'];
            $hash       = $task['hash'];
            $state      = $record['state'];
            $_this      = $data['this'];
            $parent     = $_this->parent;

            $parent->finishTask($task, $state);

            if ($state == 'finished') {
                if ($result = $parent->sendMp4($task['chat_id'], $data['file_path'], $data['file_name'], '🎬 '.Lang("Your video is ready"), [
                        'width' => $data['task_result']['width'],
                        'height' => $data['task_result']['height']
                    ])) {

                    (new TransactionsModel())->PayUpscale($task['user_id'], [
                        'response_id'=>$data['response']['id'],
                        'hash'=>$hash
                    ]);
                }
            } else {
                $parent->Message($task['chat_id'], Lang('Fail download video'));
            }

        }, $fileData['file_url'], $fileData['file_path'], array_merge([
            'this' => $this,
            'task' => $task,
            'response' => $response,
            'task_result' => $task_result
        ], $fileData));

        /*
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
        */
    }

    protected function process_imagine($task, $response) {
        
        $prev_response = $this->modelReply->getPreviousResponse($response['id'], $response['hash']);

        $fileData = \MjModel::GetResultFile($task, $prev_response);

        $this->parent->downloadClient->AddTask(function($record, $data) {

            $method     = 'select';
            $task       = $data['task'];
            $response   = $data['response'];
            $hash       = $task['hash'];
            $state      = $record['state'];
            $_this      = $data['this'];
            $parent     = $_this->parent;

            $parent->finishTask($task, $state);

            trace("Attempt send photo {$data['file_path']}");

            resizeImageIfTooLarge($data['file_path']);

            if ($state == 'finished') {
                $result = $parent->sendPhoto($task['chat_id'], $data['file_path'], $data['file_name'], Lang('Choose the option you like best'),
                    [
                        [
                            ['text' => '1', 'callback_data' => "task.{$hash}.{$method}.0"],
                            ['text' => '2', 'callback_data' => "task.{$hash}.{$method}.1"]
                        ],[
                            ['text' => '3', 'callback_data' => "task.{$hash}.{$method}.2"],
                            ['text' => '4', 'callback_data' => "task.{$hash}.{$method}.3"]
                        ]
                    ]
                );

                if ($result) {

                    $parent->PayUpscale($task['user_id'], [
                        'response_id'=>$response['id'],
                        'hash'=>$hash
                    ]);
                }
            } else {
                $parent->Message($task['chat_id'], Lang('Fail download image'));
            }
        }, $fileData['file_url'], $fileData['file_path'], array_merge([
            'this' => $this,
            'task' => $task,
            'response' => $response
        ], $fileData));
    }
}