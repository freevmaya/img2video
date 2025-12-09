<?
class MJModel extends BaseModel {
	
	protected function getTable() {
		return 'api_comm';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'task_hash' => [
				'type' => 'task_hash ',
				'dbtype' => 's'
			],
			'webhook_type' => [
				'type' => 'webhook_type',
				'dbtype' => 's'
			],
			'prompt' => [
				'type' => 'prompt',
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
			]
		];
	}
}