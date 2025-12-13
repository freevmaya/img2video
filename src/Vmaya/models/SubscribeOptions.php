<?
class SubscribeOptions extends BaseModel {
	
	protected function getTable() {
		return 'subscribe_options';
	}

	public function ByArea($area_id) {
		GLOBAL $dbp;
		if ($area_id)
			return $dbp->asArray("SELECT s.*, a.currency FROM {$this->getTable()} s LEFT JOIN `areas` a ON s.area_id = a.id WHERE s.`area_id` LIKE '%{$area_id}%'");

		return [];
	}

	public static function GetPrice($userId, $limitName='image_limit') {
		GLOBAL $dbp;

		$query = "SELECT so.* FROM `tg_users` u ".
			"LEFT JOIN `subscribe_options` so ON u.area_id=so.area_id ".
			"LEFT JOIN `areas` a ON a.id=u.area_id ".
			"WHERE u.id={$userId} AND a.default_subscribe_id=so.id";

		//trace($query);
		if ($soption = $dbp->line($query))
			return $soption['price'] / $soption[$limitName];
		else {
			trace_error("User not found: ".$query);
			return 100;
		}
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'area_id' => [
				'type' => 'Area',
				'dbtype' => 'i'
			],
			'name' => [
				'type' => 'Name',
				'dbtype' => 's'
			],
			'description' => [
				'type' => 'Description',
				'dbtype' => 's'
			],
			'price' => [
				'label'=> 'Price',
				'dbtype' => 'd'
			],
			'image_limit' => [
				'label'=> 'Image limit',
				'dbtype' => 'i'
			],
			'video_limit' => [
				'label'=> 'Video limit',
				'dbtype' => '1'
			]
		];
	}
}
?>