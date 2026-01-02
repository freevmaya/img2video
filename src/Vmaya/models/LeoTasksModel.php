<?

class LeoTasksModel extends BaseModel {
	
	protected function getTable() {
		return 'leo_tasks';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'hash' => [
				'label' => 'hash',
				'dbtype' => 's'
			],
			'processed' => [
				'label' => 'processed',
				'dbtype' => 'i'
			],
			'type' => [
				'label' => 'type',
				'dbtype' => 's'
			],
			'status' => [
				'label' => 'status',
				'dbtype' => 's'
			],
			'object' => [
				'label' => 'object',
				'dbtype' => 's'
			],
			'time' => [
				'label'=> 'time',
				'dbtype' => 's'
			],
			'result_url' => [
				'label'=> 'result_url',
				'dbtype' => 's'
			],
			'data' => [
				'label'=> 'data',
				'dbtype' => 's'
			]
		];
	}
}