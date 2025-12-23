<?
class SessionsModel extends BaseModel {
	
	protected function getTable() {
		return 'sessions';
	}

	public function getFields() {
		return [
			'chat_id' => [
				'type' => 'chat_id',
				'dbtype' => 'i'
			],
			'data' => [
				'type' => 'data',
				'dbtype' => 's'
			]
		];
	}
}
?>