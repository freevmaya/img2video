<?

function MjConvertUrl($url, $task, $choice = 0) {

    $paterns = [ 
        '/\/([a-z\d-]+)_grid_([\d]+)/'  => '%s/grid_0.png',
        '/_([a-z\d-]+).png\?/'  		=> '%s/0_%s.png',
        '/within_a__([\w\d-]+)\.webp/'  => 'video/%s/0.mp4'
    ];

    foreach ($paterns as $pattern=>$replace) {
        if (preg_match($pattern, $url, $matches) && (count($matches) > 1)) {

            if ($task && (!empty($request_data = $task['request_data'])) && 
                ($request_data = json_encode($request_data, true)) &&
                isset($request_data['choice']))
                $choice = $request_data['choice'];

            $relativePath = sprintf($replace, $matches[1], $choice);
            return MJ_BASE_URL.$relativePath;
        }
    }
}

class MJModel extends BaseModel {
	
	protected function getTable() {
		return 'mj_tasks';
	}

	public function getPreviousResponse($id, $hash) {
		GLOBAL $dbp;

		$list = $dbp->asArray("SELECT * FROM {$this->getTable()} WHERE id < $id AND `hash`='{$hash}' AND `result` IS NOT NULL");

		$count = count($list);
		return $count > 0 ? $list[$count - 1] : null;
	}

    public static function GetResultFile($task, $response) {

        $task_result = json_decode($response['result'], true);
        $url = $task_result['url'];

        $new_url = $task['service'] == 'mj' ? MjConvertUrl($url, $task) : $url;

        $info = pathinfo(explode('?', $new_url)[0]);
        $filename = $task['hash'].'-'.$response['id'].'.'.$info['extension'];

        return [
        	'file_url' => $new_url,
            'file_name' => $filename,
            'file_path' => RESULT_PATH.$filename
        ];
    }

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'hash' => [
				'label' => 'hash',
				'dbtype' => 's'
			],
			'webhook_type' => [
				'label' => 'webhook_type',
				'dbtype' => 's'
			],
			'prompt' => [
				'label' => 'prompt',
				'dbtype' => 's'
			],
			'type' => [
				'label'=> 'type',
				'dbtype' => 's'
			],
			'status' => [
				'label'=> 'status',
				'dbtype' => 's'
			],
			'result' => [
				'label'=> 'result',
				'dbtype' => 's'
			],
			'created_at' => [
				'label'=> 'created_at',
				'dbtype' => 's'
			],
			'processed' => [
				'label'=> 'processed',
				'dbtype' => 'i'
			],
			'fail_count' => [
				'label'=> 'fail_count',
				'dbtype' => 'i'
			],
			'fail_time' => [
				'label'=> 'fail_time',
				'dbtype' => 's'
			]
		];
	}
}