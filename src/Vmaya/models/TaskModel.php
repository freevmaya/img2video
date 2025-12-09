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
			'user_id' => [
				'type' => 'user_id ',
				'dbtype' => 'i'
			],
			'chat_id' => [
				'type' => 'chat_id ',
				'dbtype' => 'i'
			],
			'hash' => [
				'type' => 'hash ',
				'dbtype' => 's'
			],
			'state' => [
				'type' => 'state',
				'dbtype' => 's'
			]
		];
	}
}