<?
class SubscriberWeather extends BaseModel {
	
	protected function getTable() {
		return 'subscriber_weather';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden'
			],
			'user_id' => [
				'type' => 'hidden',
				'dbtype' => 'i',
				'default' => -1
			],
			'active' => [
				'label'=> 'Number',
				'dbtype' => 'i'
			]
		];
	}
}
?>