<?

class DownloadClient {
	private $model;
	protected $listeners;
	protected $index;

	function __construct() {
		$this->listeners = [];
    	$this->model = new TaskDownloadModel();
    }

    public function AddTask(callable $callback, $url, $destancePath, $data = null) {
    	$id = $this->model->AddTask($url, $destancePath);

    	$this->listeners[] = [
    		'id' => $id,
    		'callback' => $callback,
    		'data' => $data
    	];

    	return $id;
    }

    public function IndexOf($id) {
    	for ($i=0; $i < count($this->listeners); $i++) { 
    		if ($this->listeners[$i]['id'] == $id)
    			return $i;
    	}
    	return -1;
    }

    public function Find($id) {
    	foreach ($this->listeners as $item)
    		if ($item['id'] == $id)
    			return $item;
    	return false;
    }

    public function Run() {

    	$dl_tasks = $this->model->getItems("`state` != 'active'");
    	foreach ($dl_tasks as $dl_task) {
    		$index = $this->IndexOf($dl_task['id']);
    		if ($index > -1) {
    			$listener = $this->listeners[$index];
                if (file_exists($dl_task['path'])) {
        			$listener['callback']($dl_task, $listener['data']);
                } else {
                    trace_error("File not found: {$dl_task['path']}");

                    $dl_task['state'] = 'failure';
                    $listener['callback']($dl_task, $listener['data']);
                }
                array_splice($this->listeners, $index, 1);
    		}
    	}
    }
}