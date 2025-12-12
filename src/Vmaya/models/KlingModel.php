<?
class KlingModel extends BaseModel {
	
	protected function getTable() {
		return 'kling_tasks';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'task_id' => [
				'label' => 'service',
				'dbtype' => 's'
			],
			'processed' => [
				'label' => 'processed',
				'dbtype' => 'i'
			],
			'fail_count' => [
				'label' => 'fail_count',
				'dbtype' => 'i'
			],
			'status' => [
				'label' => 'status',
				'dbtype' => 's'
			],
			'result_url' => [
				'label'=> 'result_url',
				'dbtype' => 's'
			],
			'error_message' => [
				'label'=> 'error_message',
				'dbtype' => 's'
			],
			'created_at' => [
				'label'=> 'created_at',
				'dbtype' => 's'
			]
		];
	}
}