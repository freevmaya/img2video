<?
namespace App\Services\API\cycle;

class KlingCycle extends BaseCycle {

	protected function finalyDownloadfile($task, $response, $file_path, $filename) {

        //trace("Attempt send mp4 {$file_path}");
		if ($this->parent->sendMp4($task, $file_path, $filename, Lang('Your video is ready'))) {

            $this->parent->PayVideo($task['user_id'], [
                'hash'=>$task['hash']
            ]);
            $this->parent->finishTask($task);
        } else $this->setResponseProcessed($response, 0);
	}

    protected function getResponses($task) {
        return $this->modelReply->getItems(['processed'=>0, 'task_id'=>$task['hash']]);
    }

    public function afterDownloadVideo($record, $data) {

        $task       = $data['task'];
        $response   = $data['response'];
        $hash       = $task['hash'];
        $state      = $record['state'];

        if ($state == 'failure') {

            if ($this->parent->Message($task['chat_id'], ['text' => sprintf(Lang("DownloadFailure"), $task['id']), 'reply_markup'=> json_encode([
                    'inline_keyboard' => [
                        [['text' => '💬 '.Lang('Help Desk'), 'callback_data' => 'support']]
                    ]
                ])
            ])) {
                $this->parent->finishTask($task, $state);
            } else $this->setResponseProcessed($response, 0);
        } else $this->finalyDownloadfile($task, $response, $data['file_path'], $data['file_name']);

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

                    $this->parent->downloadClient->AddTask([$this, 'afterDownloadVideo'], $response['result_url'], $file_path, [
                        'task' => $task,
                        'response' => $response,
                        'file_path' => $file_path,
                        'file_name' => $filename
                    ]);
                }
            }
        }
	}
}