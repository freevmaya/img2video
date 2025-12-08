<?
class MMessages extends BaseModel {
	
	protected function getTable() {
		return 'm_messages';
	}

	public function getFields() {
		return [
			'message_id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'chat_id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'user_id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'text' => [
				'label'=> 'Текст',
				'dbtype' => 's'
			]
		];
	}
}
?>