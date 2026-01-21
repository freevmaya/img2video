<?
class SettingsModel extends BaseModel {
	
	protected function getTable() {
		return 'settings';
	}

	public function getFields() {
		return [
			'id' => [
				'type' => 'hidden',
				'dbtype' => 'i'
			],
			'name' => [
				'type' => 'Area',
				'dbtype' => 's'
			],
			'value' => [
				'type' => 'value',
				'dbtype' => 's'
			]
		];
	}

	public function setValue($name, $value) {
		$this->Update([
			'name' => $name,
			'value' => $value
		], 'name');
		return $value;
	}

	public function getValue($name, $default) {
		if ($item = $this->getItem($name, 'name')) {
			return $item['value'];
		} else {
			return $this->setValue($name, $default);
		}
	}
}
?>