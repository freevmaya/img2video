<?
namespace App\Services\API\cycle;

class KlingCycle extends BaseCycle {

	protected function finalyDownloadfile($task, $response, $file_path, $filename) {

        //trace("Attempt send mp4 {$file_path}");
		if ($this->parent->sendMp4($task, $file_path, $filename, Lang('Your video is ready'))) {

            $this->parent->PayVideo($task['user_id'], [
                'hash'=>$task['hash']
            ]);
        	
        }
        $this->parent->finishTask($task);
	}

    protected function getResponses($task) {
        return $this->modelReply->getItems(['processed'=>0, 'task_id'=>$task['hash']]);
    }

	protected function doProcessResponse($task, $response) {

        if (($response['status'] == 'processing') || ($response['status'] == 'submitted')) {
            $this->parent->Message($task['chat_id'], Lang('Your video in progress'));
            $this->finishResponse($response);
        } else if ($response['status'] == 'succeed') {

            if ($response['result_url']) {

                $filename = $task['hash'].'.mp4'; // Какое расширение?

                $file_path = RESULT_PATH.$filename;
                $this->finishResponse($response);

                if (file_exists($file_path)) {                    
                    $this->finalyDownloadfile($task, $response, $file_path, $filename);
                } else {

                    $this->parent->downloadClient->AddTask(function($record, $data) {

                        $task       = $data['task'];
                        $response   = $data['response'];
                        $hash       = $task['hash'];
                        $state      = $record['state'];
                        $_this      = $data['this'];
                        $parent     = $_this->parent;

                        if ($state == 'failure') {

                            $parent->finishTask($task, $state);

                            $parent->Message($task['chat_id'], ['text' => sprintf(Lang("DownloadFailure"), $task['id']), 'reply_markup'=> json_encode([
                                    'inline_keyboard' => [
                                        [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']]
                                    ]
                                ])
                            ]);
                        } else $this->finalyDownloadfile($task, $response, $data['file_path'], $data['file_name']);


                    }, $response['result_url'], $file_path, [
                        'this' => $this,
                        'task' => $task,
                        'response' => $response,
                        'file_path' => $file_path,
                        'file_name' => $filename
                    ]);
                    /*
                    $downloadResult = downloadFile($response['result_url'], $file_path);

                    if ($downloadResult['success']) {                   
                    	$this->finalyDownloadfile($task, $response, $file_path, $filename);
                        return true;
                    } else {
                        if ($response['fail_count'] >= NUMBER_DOWNLOAD_ATTEMPTS) {

                            $this->parent->finishTask($task, 'failure');
                            $this->finishResponse($response);

                            $this->parent->Message($task['chat_id'], ['text' => sprintf(Lang("DownloadFailure"), $task['id']), 'reply_markup'=> json_encode([
                                    'inline_keyboard' => [
                                        [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']]
                                    ]
                                ])
                            ]);
                        } else $this->modelReply->Update([
                            'id'=>$response['id'], 'fail_count'=>$response['fail_count'] + 1
                        ]);
                    }*/
                }
            }
        }
	}
}