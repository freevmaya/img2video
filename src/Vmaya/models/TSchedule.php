<?
class TSchedule extends BaseModel {
	
	protected function getTable() {
		return 'schedule';
	}

	public function getItems($options=null, $fieldNames = ['place', 'date', 'state', 'user_id']) {
		GLOBAL $dbp;

		$where = BaseModel::GetConditions($options, $fieldNames);
		$where[] = "DATE_ADD(s.date, INTERVAL duration DAY) >= NOW()";
		$whereStr = implode(" AND ", $where);

		$query = "SELECT s.*, u.username, u.first_name, u.last_name FROM {$this->getTable()} s LEFT JOIN tg_users u ON s.user_id = u.id WHERE {$whereStr} ORDER BY place";

		return $dbp->asArray($query);
	}

	public function ListMessage() {
		$items = $this->getItems(['state'=>'active']);
		$list = [];
        $place = null;

		foreach ($items as $item) {
			if ($place != $item['place']) {
                $place = $item['place'];
                $label = '*'.$item['place'].'*';
                $date = null;
            } else $label = '     ';

            $userLink = BaseBot::getUserLink($item['user_id'], TGUserModel::getName($item));

            $strItem = $label." ".$userLink.", ".date('d.m.Y H:i', strtotime($item['date']));

            if ($item['duration']) $strItem .= ', на '.$item['duration'].' дня';
            if ($item['description']) $strItem .= ', '.$item['description'];

            $list[] = $strItem;
		}

		return implode("\n", $list);
	}

	public function checkAndAdd($user_id, $text) {
		GLOBAL $dbp;
		if ($text) {
			$items = explode(",", $text);
			$count = count($items);

			if ($count >= 2) {

				for ($i=0; $i<$count; $i++)
					$items[$i] = trim($items[$i]);

				try {

					$duration = is_numeric($items[1]) ? intval($items[1]) : (isset($items[2]) && is_numeric($items[2]) ? intval($items[2]) : 1);
					
					$date_value = is_date($items[1]) ? strtotime($items[1]) : strtotime("now");
					$date = date('Y-m-d H:i:s', $date_value);
					$finish_date = strtotime($date." +{$duration} day");

					if (strtotime("now") <= $finish_date) {
						$description = ($count > 2) && (is_numeric($items[$count - 1]) === false)? toUTF($items[$count - 1]) : "";

						$params = [
							"user_id"=>$user_id,
							"place" => toUTF($items[0]),
							"date" => $date,
							"duration" => $duration,
							"description" => $description
						];

						$items = $this->getItems([
							"user_id"=>$user_id,
							'place'=>$params['place'],
							'date'=>$params['date']
						]);

						if (count($items) == 0) {
							$query = "INSERT INTO {$this->getTable()} (`user_id`, `place`, `date`, `duration`, `description`) VALUES (?, ?, ?, ?, ?)";
							return $dbp->bquery($query, $params);
						} else return "Уже есть";
					} else return "Дата ".date('d.m.Y H:i', $finish_date)." уже прошла!";

				} catch (Exception $e) {
				    trace_error($e->getMessage());
				}
			} 
		}

		return false;
	}
}
?>