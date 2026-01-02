<?
namespace App\Services\API\cycle;

class LeoCycle extends BaseCycle {

	protected function doProcessResponse($task, $response) {

        $type = explode('.', $response['type']);

        $method = 'process_'.$type[0];

        if (method_exists($this, $method)) {
            if ($response['status'] == 'COMPLETE') {
                $this->$method($task, $response);
            }

            $this->finishResponse($response); 
        }
        else {
            $this->finishResponse($response);
            trace_error("The method is missing: {$method}");
        }
	}

    protected function process_image_generation($task, $response) {

        $file_url = $response['result_url'];
        $info = pathinfo(explode('?', $file_url)[0]);
        $file_name = $task['hash'].'-'.$response['id'].'.'.$info['extension'];

        $method_data = [
            'this'      => $this,
            'task'      => $task,
            'response'  => $response,
            'file_url'  => $file_url,
            'file_name' => $file_name,
            'file_path' => RESULT_PATH.$file_name
        ];

        $this->parent->downloadClient->AddTask(function($record, $data) {
            $method     = 'select';
            $task       = $data['task'];
            $response   = $data['response'];
            $hash       = $task['hash'];
            $dl_state   = $record['state'];
            $_this      = $data['this'];
            $parent     = $_this->parent;

            trace("Attempt send photo {$data['file_path']}");

            resizeImageIfTooLarge($data['file_path']);

            if ($dl_state == 'finished') {
                $result = $parent->sendPhoto($task['chat_id'], $data['file_path'], $data['file_name'], Lang('Your photo is ready'));

                if ($result) {

                    $parent->PayUpscale($task['user_id'], [
                        'response_id'=>$response['id'],
                        'hash'=>$hash
                    ]);

                    $parent->finishTask($task, 'finished');
                } else {
                    $parent->Message($task['chat_id'], Lang('Something wrong'));
                    $parent->finishTask($task, 'failure');
                }
            } else {
                $parent->Message($task['chat_id'], Lang('Fail download image'));
                $parent->finishTask($task, 'failure');
            }
        }, $method_data['file_url'], $method_data['file_path'], $method_data);
    }
}