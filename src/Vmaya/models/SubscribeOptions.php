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
				'dbtype' => 'f'
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