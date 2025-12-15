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
            if (HoursDiffDate($task['date']) > 1)
                $this->parent->finishTask($task, 'failure');
        } else {
            foreach ($responses as $item)
                $this->doProcessResponse($task, $item);
        }
    }

    protected abstract function doProcessResponse($task, $response);

    protected function finishResponse($response) {
        $this->modelReply->Update([
            'id'=>$response['id'], 'processed'=>1
        ]);
    }

    protected function scraperDownload($url, $file_path) {
        $output = null;
        $command = 'py '.BASEPATH."scraper_download.py \"{$url}\" \"{$file_path}\"";

        exec($command, $output);
        $result = 0;

        if ($output && (count($output) > 0))
            $result = intval($output[count($output) - 1]);
            
        if ($result != 1)
            trace_error($command."; Result: ".$result);

        return $result == 1;
    }
}