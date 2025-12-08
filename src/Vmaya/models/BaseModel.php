<?

abstract class BaseModel {
	abstract protected function getTable();
	public function getFields() {return [];}
	public function checkUnique($data) { return false; }
	public function getTitle() { return Lang(get_class($this)); }

	protected function verifyId($idField, $id) {

		if ($id && ($idField != 'id')) {
			$fields = $this->getFields();
			if (isset($fields[$idField]) && ($fields[$idField]['dbtype'] == 's'))
				$id = "'{$id}'";
		}

		return $id;
	}

	public function Update($values, $idField = 'id') {
		GLOBAL $dbp;
		$types = $this->dbTypes(array_keys($values));

		$id = isset($values[$idField]) ? $values[$idField] : null;

		$values = $this->allowUpdateValues($values );

		if ($id) {
			if ($dbp->bquery($this->updateQuery($id, $values, $idField), $types, array_values($values)))
				return $id;
		}
		else if ($dbp->bquery($this->insertQuery($values), $types, array_values($values)))
				return $dbp->lastID();

		return false;
	}

	protected function allowUpdateValues($values) {
		$fields = $this->getFields();
		$result = [];
		foreach ($values as $field=>$value)
			if (isset($fields[$field]) && isset($fields[$field]['dbtype']))
				$result[$field] = $value;

		return $result;
	}

	protected function updateQuery($id, $values, $idField='id') {

		$updateList = [];
		$id = $this->verifyId($idField, $id);

		foreach($this->getFields() as $fieldName=>$field)
			if (($fieldName != 'id') && isset($values[$fieldName]))
				$updateList[] = "`{$fieldName}`=?";

		return "UPDATE `{$this->getTable()}` SET ".implode(',', $updateList)." WHERE `{$idField}`={$id}";
	}

	protected function insertQuery($values) {
		unset($values['id']);

		$fieldList = array_keys($values);
		$valuesList = [];
		foreach($fieldList as $fieldName) $valuesList[] = '?';

		return "INSERT INTO {$this->getTable()} (".implode(',', $fieldList).") VALUES (".implode(',', $valuesList).")";
	}

	protected function dbTypes($fieldsList)
	{
		$fields = $this->getFields();
		$types = '';
		foreach ($fieldsList as $field)
			if (($field != 'id') and isset($fields[$field]))
				$types .= isset($fields[$field]['dbtype']) ? $fields[$field]['dbtype'] : 's';

		return $types;
	}

	public function getItems($options=null, $fieldNames = null) {
		GLOBAL $dbp;
		if (!$fieldNames && $options)
			$fieldNames = array_keys($options);

		$where = $fieldNames ? ('WHERE '.implode(' AND ', BaseModel::GetConditions($options, $fieldNames))) : '';
		return $this->getItemsWhere($where);
	}

	public function getItemsWhere($where) {
		GLOBAL $dbp;
		return $dbp->asArray("SELECT * FROM {$this->getTable()} ".$where);
	}

	public function getItem($id, $idField = 'id') {		
		GLOBAL $dbp;

		$id = $this->verifyId($idField, $id);
		return $id ? $dbp->line("SELECT * FROM {$this->getTable()} WHERE `{$idField}`={$id}") : null;
	}

	public static  function AddWhere($whereList, $options, $paramName, $operand = '=') {
		$optionCondition = '';
		if (isset($options[$paramName])) {
			if (is_array($options[$paramName]))
				$optionCondition = "{$paramName} IN ('".implode("','", $options[$paramName])."')";
			else $optionCondition = "{$paramName} {$operand} '{$options[$paramName]}'";
		}
		if ($optionCondition) $whereList[] = $optionCondition;
		return $whereList;
	}

	public static  function GetConditions($values, $paramsNames, $operand = '=') {
		$list = [];

		if ($values)
			foreach ($paramsNames as $key=>$param)
				$list = BaseModel::AddWhere($list, $values, $param, $operand);

		return $list;
	}

	public static function getValues($values, $fields, $defaults) {
		$result = [];
		foreach ($fields as $i=>$field) {
			$v = isset($values[$field]) ? $values[$field] : $defaults[$i];
			if (is_object($v) || is_array($v))
				$v = json_encode($v);
			$result[] = $v;
		}
		return $result;
	}

	public static function getListValues($items, $field) {
		$result = [];

		foreach ($items as $item)
			if (isset($item[$field]))
				$result[] = $item[$field];

		return array_unique($result);
	}

	public static function FullItem($item, $models) {

		foreach ($models as $key=>$model) {
			$idpos = strpos($key, '_id');
			if ($idpos > -1) {
				$outfield = substr($key, 0, $idpos);
				if (@$item[$key])
					$item[$outfield] = $model->getItem($item[$key]);
			} else trace_error('Field '.$idpos.' not found');
		}

		return $item;
	}

	public static function FullItems($items, $models) {
		for ($i=0; $i<count($items); $i++)
			$items[$i] = BaseModel::FullItem($items[$i], $models);

		return $items;
	}
}
?>