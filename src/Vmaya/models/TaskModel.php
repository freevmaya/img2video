<?
class TaskModel extends BaseModel {
	
	protected function getTable() {
		return 'task';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'service' => [
				'type' => 'service',
				'dbtype' => 's'
			],
			'preset' => [
				'type' => 'preset',
				'dbtype' => 's'
			],
			'date' => [
				'type' => 'date',
				'dbtype' => 's'
			],
			'chat_id' => [
				'type' => 'chat_id',
				'dbtype' => 'i'
			],
			'user_id' => [
				'type' => 'user_id',
				'dbtype' => 'i'
			],
			'hash' => [
				'type' => 'hash',
				'dbtype' => 's'
			],
			'state' => [
				'type' => 'state',
				'dbtype' => 's'
			],
			'request_data' => [
				'type' => 'request_data',
				'dbtype' => 's'
			],
			'response_data' => [
				'type' => 'request_data',
				'dbtype' => 's'
			],
			'data' => [
				'type' => 'data',
				'dbtype' => 's'
			]
		];
	}
}