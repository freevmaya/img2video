<?
namespace App\Services\API\cycle;

class LeoCycle extends BaseCycle {

	protected function doProcessResponse($task, $response) {
		if (isset($response['result_url']) && !empty($response['result_url'])) {

            $type = explode('.', $response['type']);

            $method = 'process_'.$type[0];

            if (method_exists($this, $method)) {
                if ($type[0] == 'complete') {

                    $result = json_decode(@$response['result'], true);

                    if ($url = $response['result_url']) {
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
            'file_path' => RESULT_PATH.$file_name
        ];

        $this->parent->downloadClient->AddTask(function($record, $data) {

            $method     = 'select';
            $task       = $data['task'];
            $response   = $data['response'];
            $hash       = $task['hash'];
            $status      = $record['status'];
            $_this      = $data['this'];
            $parent     = $_this->parent;

            $parent->finishTask($task, $status == 'COMPLETE' ? 'finished' : 'failure');

            trace("Attempt send photo {$data['file_path']}");

            resizeImageIfTooLarge($data['file_path']);

            if ($status == 'COMPLETE') {
                $result = $parent->sendPhoto($task['chat_id'], $data['file_path'], $data['file_name']);

                if ($result) {

                    $parent->PayUpscale($task['user_id'], [
                        'response_id'=>$response['id'],
                        'hash'=>$hash
                    ]);
                }
            } else {
                $parent->Message($task['chat_id'], Lang('Fail download image'));
            }
        }, $method_data['file_url'], $method_data['file_path'], $method_data);
    }
}