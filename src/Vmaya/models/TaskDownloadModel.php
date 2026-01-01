<?

class TaskDownloadModel extends BaseModel {
	
	protected function getTable() {
		return 'task_download';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 's'
			],
			'url' => [
				'type' => 'url',
				'dbtype' => 's'
			],
			'path' => [
				'type' => 'path',
				'dbtype' => 's'
			],
			'attempt_count' => [
				'type' => 'attempt_count',
				'dbtype' => 'i'
			],
			'last_attempt' => [
				'type' => 'last_attempt ',
				'dbtype' => 's'
			],
			'state' => [
				'type' => 'state ',
				'dbtype' => 's'
			]
		];
	}

	public function AddTask($url, $distancePath) {
		$d_task_id = md5($url);

		if (!$this->getItem($d_task_id))
			$this->Update([
	            'id'			=> $d_task_id,
	            'url'			=> $url,
	            'path'			=> $distancePath,
	            'attempt_count' => 0,
	            'last_attempt' 	=> date('Y-m-d H:i:s'),
	            'state'			=> 'active'
	        ]);
        return $d_task_id;
	}

    public function SetStateTask($id, $state = 'finished', $attempt_count = 0) {
    	$this->Update([
    		'id' => $id,
            'attempt_count' => $attempt_count,
    		'state' => $state,
    		'last_attempt' => date('Y-m-d H:i:s')
    	]);
    }
}