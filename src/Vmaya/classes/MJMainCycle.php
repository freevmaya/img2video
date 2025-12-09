<?
use \App\Services\API\MidjourneyAPI;
class MJMainCycle extends MidjourneyAPI {

    protected function updateTask($task) {
        $responses = $this->modelReply->getItems(['processed'=>0, 'hash'=>$task['hash']]);

        foreach ($responses as $response) {
            if ($this->doServiceAction($task, $response)) {
                $this->modelReply->Update([
                    'id'=>$response['id'], 'processed'=>1
                ]);
                break;
            }
        }
    }

    public function Update() {
        $startTime = time();
        do {
            $tasks = $this->modelTask->getItems(['state'=>'active']);
            if (count($tasks) > 0) {
                foreach ($tasks as $task) {
                    $this->updateTask($task);
                }
            }

            $delta = time() - $startTime;
            //print_r(time() - $startTime);

        } while ((count($tasks) > 0) && ($delta < 2));
    }   

    protected function doServiceAction($task, $response) {
        print_r($task);
        print_r($response);
        return true;
    } 
}