<?
class MReferenceValues extends BaseModel {
	
	protected function getTable() {
		return 'm_reference_values';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'prop_id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'type' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'value' => [
				'label'=> 'Наименование',
				'dbtype' => 's'
			]
		];
	}
}
?>