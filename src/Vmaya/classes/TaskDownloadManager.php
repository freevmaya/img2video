<?

class TaskDownloadManager {
	private $model;
	private $maxAttempt;
	private $waitAttemptSec;

	public function __construct($maxAttempt, $waitAttemptSec = 10)
    {
    	$this->maxAttempt 		= $maxAttempt;
    	$this->waitAttemptSec 	= $waitAttemptSec;
    	$this->model 			= new TaskDownloadModel();
    }

	public function Run() {
		$items = $this->model->getItems(['state'=>'active']);
		foreach ($items as $item) {

			if (file_exists($item['path']))
				$this->model->Update([
		    		'id' => $item['id'],
		    		'state' => 'finished'
		    	]);
			else {
				if (time() - strtotime($item['last_attempt']) >= $this->waitAttemptSec) {

					$attempt_count = $item['attempt_count'] + 1;
	                if (!scraperDownload($item['url'], $item['path']))
	                	$this->model->SetStateTask($item['id'], $item['attempt_count'] >= $this->maxAttempt ? 'failure' : 'active', $attempt_count);
	                else $this->model->SetStateTask($item['id'], 'finished', $attempt_count);
	        	} 
	    	}
		}
	}
}