<?
class TransactionsModel extends BaseModel {
	
	protected function getTable() {
		return 'transactions';
	}

	public function Add($user_id, $payload='', $value=0, $type='prepare', $data=[]) {
		return $this->Update([
			'user_id'=>$user_id,
			'time'=>date('Y-m-d H:i:s'),
			'payload'=>$payload,
			'value'=>$value,
			'type'=>$type,
			'data'=>json_encode($data)
		]);
	}

	public function Balance($userId) {
		GLOBAL $dbp;

		return $dbp->one("SELECT SUM(`value`) FROM {$this->getTable()} WHERE `user_id`={$userId} AND `type` IN ('expense', 'subscribe')");
	}

	public function Expense($userId) {
		GLOBAL $dbp;

		return $dbp->one("SELECT SUM(`value`) FROM {$this->getTable()} WHERE `user_id`={$userId} AND `type` = 'expense'");
	}

	public function LastSubscribe($userId) {
		GLOBAL $dbp;

		return $dbp->line("SELECT * FROM {$this->getTable()} WHERE `user_id`={$userId} AND `type` = 'subscribe' ORDER BY `id` DESC");
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'user_id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'time' => [
				'type' => 'Time',
				'dbtype' => 's'
			],
			'payload' => [
				'type' => 'Payload',
				'dbtype' => 's'
			],
			'type' => [
				'label'=> 'Type',
				'dbtype' => 's'
			],
			'value' => [
				'label'=> 'Value',
				'dbtype' => 'i'
			],
			'data' => [
				'label'=> 'Data',
				'dbtype' => 's'
			]
		];
	}
}
?>