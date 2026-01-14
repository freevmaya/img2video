<?
namespace App\Services\API\cycle;

class LeoCycle extends BaseCycle {

	protected function doProcessResponse($task, $response) {

        $type = explode('.', $response['type']);

        $method = 'process_'.$type[0];

        if (method_exists($this, $method)) {
            if ($response['status'] == 'COMPLETE') {
                if ($this->$method($task, $response)) 
                    $this->finishResponse($response); 
            }
        }
        else {
            $this->finishResponse($response);
            trace_error("The method is missing: {$method}");
        }
	}

    public function afterDownloadVideo($record, $data)
    {
        $method     = 'select';
        $task       = $data['task'];
        $response   = $data['response'];
        $hash       = $task['hash'];
        $dl_state   = $record['state'];

        trace("Attempt send photo {$data['file_path']}");

        if ($dl_state == 'finished') {
            $result = $this->parent->sendMp4($task, $data['file_path'], $data['file_name'], Lang('Your video is ready'));

            if ($result) {

                $this->parent->PayUpscale($task['user_id'], [
                    'response_id'=>$response['id'],
                    'hash'=>$hash
                ]);

                $this->parent->finishTask($task, 'finished');
            } else {
                /*
                $this->parent->Message($task['chat_id'], Lang('Something wrong'));
                $this->parent->finishTask($task, 'failure');
                */
                $this->setResponseProcessed($response, 0);
            }
        } else {
            $this->parent->Message($task['chat_id'], Lang('Fail download image'));
            $this->parent->finishTask($task, 'failure');
        }
    }

    protected function process_video_generation($task, $response) {
        $result = json_decode($response['data'], true);
        if (!isset($result['object']['images'][0]['motionMP4URL'])) {
            trace_error("Unknown result: ".$response['data']);
            return false;
        }

        $file_url = $result['object']['images'][0]['motionMP4URL'];
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

        return $this->parent->downloadClient->AddTask([$this, 'afterDownloadVideo'], $method_data['file_url'], $method_data['file_path'], $method_data);
    }

    public function afterDownloadImage($record, $data)
    {
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
                /*
                $parent->Message($task['chat_id'], Lang('Something wrong'));
                $parent->finishTask($task, 'failure');
                */
                $_this->setResponseProcessed($response, 0);
            }
        } else {
            $parent->Message($task['chat_id'], Lang('Fail download image'));
            $parent->finishTask($task, 'failure');
        }
    }

    protected function process_image_generation($task, $response) {

        $images = json_decode($response['data'], true)['object']['images'];
        $result = true;

        foreach ($images as $image) {

            $file_url = $image['url'];
            $info = pathinfo(explode('?', $file_url)[0]);
            $file_name = $task['hash'].'-'.$response['id'].'-'.$image['id'].'.'.$info['extension'];

            $method_data = [
                'this'      => $this,
                'task'      => $task,
                'response'  => $response,
                'file_url'  => $file_url,
                'file_name' => $file_name,
                'file_path' => RESULT_PATH.$file_name
            ];
            $result = $result && $this->parent->downloadClient->AddTask([$this, 'afterDownloadImage'], $method_data['file_url'], $method_data['file_path'], $method_data);
        }

        return $result;
    }
}