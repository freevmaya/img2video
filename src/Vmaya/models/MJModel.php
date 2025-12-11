<?
class MJModel extends BaseModel {
	
	protected function getTable() {
		return 'api_comm';
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
			'webhook_type' => [
				'label' => 'webhook_type',
				'dbtype' => 's'
			],
			'prompt' => [
				'label' => 'prompt',
				'dbtype' => 's'
			],
			'type' => [
				'label'=> 'type',
				'dbtype' => 's'
			],
			'status' => [
				'label'=> 'status',
				'dbtype' => 's'
			],
			'result' => [
				'label'=> 'result',
				'dbtype' => 's'
			],
			'created_at' => [
				'label'=> 'created_at',
				'dbtype' => 's'
			],
			'processed' => [
				'label'=> 'processed',
				'dbtype' => 'i'
			],
			'fail_count' => [
				'label'=> 'fail_count',
				'dbtype' => 'i'
			]
		];
	}
}