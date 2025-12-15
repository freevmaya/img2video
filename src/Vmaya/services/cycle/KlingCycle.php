<?
namespace App\Services\API\cycle;

use App\Services\API\cycle\BaseCycle;

class KlingCycle extends BaseCycle {

	protected function finalyDownloadfile($task, $response, $file_path, $filename) {
		if ($this->parent->sendMp4($task, $file_path, $filename, Lang('Your video is ready'))) {

            $this->parent->PayVideo($task['user_id'], [
                'hash'=>$task['hash']
            ]);
        	
        }
        $this->parent->finishTask($task);
        $this->finishResponse($response);
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

                if (file_exists($file_path)) {                    
                    $this->finalyDownloadfile($task, $response, $file_path, $filename);
                    return true;
                } else {
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
                    }
                }
            } else return false;
        }

        return true;
	}
}