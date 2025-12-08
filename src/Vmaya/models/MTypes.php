<?
class MTypes extends BaseModel {
	
	protected function getTable() {
		return 'm_type_cats';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden'
			],
			'name' => [
				'label'=> 'Наименование',
				'dbtype' => 's'
			]
		];
	}
}
?>