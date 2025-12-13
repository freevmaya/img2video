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

	public function GetPrice($userId, $limitName='image_limit') {
		if ($subcribe = $this->LastSubscribe($userId)) {
			try {
				$data = json_decode($subcribe['data'], true);
				$soption = (new SubscribeOptions())->getItem($data['type_id']);

				return $soption['price'] / $soption[$limitName];
			} catch (Exception $e) {
				trace_error("Failure get price: ".$e->getMessage());
			}
		} else return SubscribeOptions::GetPrice($userId, $limitName);
		return 0;
	}

	public function PayVideo($userId, $data=[]) {
		$price = $this->GetPrice($userId, 'video_limit');
		if ($price > 0)
			return $this->Add($userId, '', -$price, 'expense', $data);
		else return false;
	}

	public function PayUpscale($userId, $data=[]) {
		$price = $this->GetPrice($userId, 'image_limit');
		if ($price > 0)
			return $this->Add($userId, '', -$price, 'expense', $data);
		else return false;
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