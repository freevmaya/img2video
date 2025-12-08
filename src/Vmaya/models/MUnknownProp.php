<?
class MUnknownProp extends BaseModel {
	
	protected function getTable() {
		return 'm_unknown_prop';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'date' => [
				'type' => 'hidden',
				'dbtype' => 's'
			],
			'name' => [
				'label'=> 'Название',
				'dbtype' => 's'
			],
			'value' => [
				'label'=> 'Значение',
				'dbtype' => 's'
			]
		];
	}
}
?>