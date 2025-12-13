<?
class TGUserModel extends BaseModel {
	
	protected function getTable() {
		return 'tg_users';
	}

	public static function getName($user) {

		$fullUserName = implode(" ", [$user['first_name'], $user['last_name']]);
		return $fullUserName ? $fullUserName : $user['username'];
	}

	public static function getArea($user_id) {
		GLOBAL $dbp;
		return $dbp->line("SELECT a.* FROM tg_users u LEFT JOIN areas a ON u.area_id=a.id");
	}

	public function checkAndAdd($record) {
		GLOBAL $dbp;

		$user = $record;

		if ($record && isset($record['id'])) {

			$a_user = $this->getItem($record['id']);

			if (!$a_user) {

				$username = toUTF($record['username']);
				$first_name = toUTF($record['first_name']);
				$last_name = toUTF($record['last_name']);

				$area = (new AreasModel())->ByLanguage($record['language_code']);

				$query = "INSERT INTO {$this->getTable()} (`id`, `area_id`, `username`, `first_name`, `last_name`, `language_code`) VALUES ({$record['id']}, {$area['id']}, '{$username}', '{$first_name}', '{$last_name}', '{$record['language_code']}')";
				if ($dbp->query($query))
					$user['area_id'] = $area['id'];
			} else $user = $a_user;
		}

		return $user;
	}
}
?>