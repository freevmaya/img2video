<?
class AreasModel extends BaseModel {
	
	protected function getTable() {
		return 'areas';
	}

	public function ByLanguage($lang) {
		GLOBAL $dbp;
		if ($lang) {
			$result = $dbp->line("SELECT * FROM {$this->getTable()} WHERE `languages` LIKE '%{$lang}%'");
			if (!$result)
				$result = $dbp->line("SELECT * FROM {$this->getTable()} WHERE `isDefault` = 1");
		}

		return $result;
	}
}
?>