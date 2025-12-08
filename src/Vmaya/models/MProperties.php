<?
class MProperties extends BaseModel {
	
	protected function getTable() {
		return 'm_properties';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'name' => [
				'label'=> 'Наименование',
				'dbtype' => 's'
			],
			'active' => [
				'label'=> 'Активно',
				'dbtype' => 'i'
			]
		];
	}

	public function getItems($options=null, $fieldNames = null) {
		GLOBAL $dbp;
		if (!$fieldNames && $options)
			$fieldNames = array_keys($options);

		$where = $fieldNames ? (' WHERE '.implode(' AND ', BaseModel::GetConditions($options, $fieldNames))) : '';
		return $dbp->asArray("SELECT p.* FROM {$this->getTable()} p INNER JOIN m_prop_cat c ON c.prop_id = p.id".$where);
	}

	public function getItemsWithCatsNames($separator='" и "') {
		GLOBAL $dbp;
		$query = "SELECT p.id, p.name, CONCAT('\"', GROUP_CONCAT((SELECT CONCAT(t.name, '\" = \"', c.name) FROM m_cats c INNER JOIN m_type_cats t ON c.type_id=t.id WHERE c.id = c.cat_id) SEPARATOR '{$separator}'), '\"') cat_names FROM m_prop_cat c LEFT JOIN m_properties p ON c.prop_id = p.id GROUP BY prop_id ORDER BY cat_names;";
		return $dbp->asArray($query);
	}

	public function getItemByName($key) {
		$prop = parent::getItems(['name'=>$key]);
		if (count($prop) > 0)
			return $prop[0];
		return false;
	}
}
?>