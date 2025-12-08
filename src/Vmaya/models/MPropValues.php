<?
class MPropValues extends BaseModel {
	
	protected function getTable() {
		return 'm_prop_values';
	}

	public function getFields() {
		return [
			'property_id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'message_id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'chat_id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'value' => [
				'label'=> 'Значение',
				'dbtype' => 's'
			]
		];
	}
}
?>