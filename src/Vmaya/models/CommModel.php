<?
class CommModel extends BaseModel {
	
	protected function getTable() {
		return 'api_comm';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'task_hash ' => [
				'type' => 'task_hash ',
				'dbtype' => 's'
			],
			'service' => [
				'type' => 'service',
				'dbtype' => 's'
			],
			'data' => [
				'type' => 'data',
				'dbtype' => 's'
			],
			'type' => [
				'label'=> 'type',
				'dbtype' => 's'
			],
			'processed' => [
				'label'=> 'processed',
				'dbtype' => 'i'
			]
		];
	}
}