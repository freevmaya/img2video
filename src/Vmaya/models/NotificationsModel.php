<?
class NotificationsModel extends BaseModel {
	
	protected function getTable() {
		return 'notifications';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'processed' => [
				'type' => 'processed',
				'dbtype' => 's'
			],
			'name' => [
				'type' => 'name',
				'dbtype' => 's'
			],
			'submit_time' => [
				'type' => 'submit_time',
				'dbtype' => 's'
			],
			'chats_ids' => [
				'label'=> 'chats_ids',
				'dbtype' => 's'
			],
			'sent_chat_ids' => [
				'label'=> 'sent_chat_ids',
				'dbtype' => 's'
			],
			'error_chat_ids' => [
				'label'=> 'error_chat_ids',
				'dbtype' => 's'
			],
			'message' => [
				'label'=> 'message',
				'dbtype' => 's'
			],
			'preset_name' => [
				'label'=> 'preset_name',
				'dbtype' => 's'
			]
		];
	}
}
?>