<?
class ContestModel extends BaseModel {
	
	protected function getTable() {
		return 'сontest';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden'
			],
			'user_id' => [
				'label' => 'user_id',
				'dbtype' => 'i'
			],
			'type' => [
				'label' => 'type',
				'dbtype' => 's'
			],
			'file_id' => [
				'label'=> 'file_id',
				'dbtype' => 's'
			],
			'date' => [
				'label'=> 'date',
				'dbtype' => 's'
			],
			'votes' => [
				'label'=> 'votes',
				'dbtype' => 'i'
			],
			'state' => [
				'label'=> 'state',
				'dbtype' => 's'
			],
			'data' => [
				'label'=> 'data',
				'dbtype' => 's'
			]
		];
	}