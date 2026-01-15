<?
namespace App\Services\API\cycle;

abstract class BaseCycle {

    protected $modelTask;
    protected $modelReply;
    protected $parent;

    public function __construct($parent, $modelTask, $modelReply)
    {
        $this->parent       = $parent;
        $this->modelTask    = $modelTask;
        $this->modelReply   = $modelReply;
    }

    protected function getResponses($task) {
        return $this->modelReply->getItems(['processed'=>0, 'hash'=>$task['hash']]);
    }

	public function doServiceAction($task) {
        $responses = $this->getResponses($task);

        if (count($responses) == 0) {
            if (HoursDiffDate($task['date']) > 12) { // Если разница в 12 часа, то закрываем задание
                $this->parent->finishTask($task, 'expired');
            }
        } else {
            foreach ($responses as $item)
                $this->doProcessResponse($task, $item);
        }
    }

    protected abstract function doProcessResponse($task, $response);

    protected function finishResponse($response) {
        $this->setResponseProcessed($response, 1);
    }

    protected function setResponseProcessed($response, $value = 1) {
        $this->modelReply->Update([
            'id'=>$response['id'], 'processed'=>$value
        ]);
    }
}