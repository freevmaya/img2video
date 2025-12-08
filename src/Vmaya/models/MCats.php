<?
class MCats extends BaseModel {
	
	protected function getTable() {
		return 'm_cats';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden'
			],
			'type_id' => [
				'type' => 'hidden',
				'dbtype' => 'i',
				'default' => null
			],
			'name' => [
				'label'=> 'Наименование',
				'dbtype' => 's'
			]
		];
	}

	public function criteriesAll() {
		GLOBAL $dbp;
		return $dbp->asArray("SELECT c.*, t.name AS typeName FROM {$this->getTable()} c LEFT JOIN m_type_cats t ON t.id = c.type_id ORDER BY c.type_id");
	}

	public function criteriesTree() {
		$items = $this->criteriesAll();
		$cur_type = false;
		$result = [];

		foreach ($items as $item) {
			if ($cur_type != $item['typeName']) {
				$cur_type = $item['typeName'];
				$result[] = $item['type_id'].'. '.$cur_type;
			}
			$result[] = "\t".$item['type_id'].'.'.$item['id'].'. '.$item['name'];
		}

		return implode("\n", $result);
	}

	public function checkAndAdd($chat_id, $message_id, $key, $val) {
		GLOBAL $dbp;

		$cat_id = false;
		$cats = $this->getItems(['name'=>$val]);
		if (count($cats) > 0) {
			return $dbp->query("REPLACE m_msgs_cats (`chat_id`, `message_id`, `cat_id`) ".
									"VALUES ({$chat_id}, {$message_id}, {$cats[0]['id']})");
		}
		return false;
	}
}
?>