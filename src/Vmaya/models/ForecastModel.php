<?
class ForecastModel extends BaseModel {
	protected function getTable() {
		return 'forecast_openweathermap';
	}

	public function getItem($place_id, $filedName='place_id') {
		GLOBAL $dbp;

		if ($place_id) {
			$query = "SELECT * FROM {$this->getTable()} WHERE {filedName} = {$place_id} LIMIT 1";
			return $dbp->line($query);
		}

		return null;
	}

	public function getItems($options=null, $fieldNames = null) {
		GLOBAL $dbp;
		$where = $fieldNames ? (' WHERE '.BaseModel::GetConditions($options, $fieldNames)) : '';

		$query = "SELECT f.*, p.name as place_name FROM {$this->getTable()} f RIGHT JOIN places p ON f.place_id = p.id {$where} ORDER BY p.id";
		return $dbp->asArray($query);
	}

	public function getObservedItems($period = "f.time > NOW()") {
		GLOBAL $dbp;

		$noweathers = "('Rain', 'Show')";

		$where = "{$period} AND (HOUR(f.time) >= o.time_min) AND (HOUR(f.time) <= o.time_max)".
				" AND (f.wind_speed >= o.wind_speed_min) AND (f.wind_speed <= o.wind_speed_max)".
				" AND (f.wind_deg + 360 >= o.wind_deg_min + 360) AND (f.wind_deg + 360 <= o.wind_deg_max + 360)".
				" AND (f.wind_gust >= o.wind_gust_min) AND (f.wind_gust <= o.wind_gust_max)".
				" AND (f.pressure >= o.pressure_min) AND (f.pressure <= o.pressure_max)".
				" AND (f.humidity >= o.humidity_min) AND (f.humidity <= o.humidity_max)".
				" AND (((o.weather IS NULL) AND (f.weather NOT IN {$noweathers})) OR (f.weather = o.weather))".
				" AND o.active = 1";

		$query = "SELECT p.lat, p.lon, p.name as place_name, o.*, f.* ".
				"FROM {$this->getTable()} f RIGHT JOIN places p ON f.place_id = p.id LEFT JOIN observed o ON o.place_id = f.place_id ".
				"WHERE {$where} ORDER BY p.id, f.time";
				
		return $dbp->asArray($query);
	}

	public function ForecastListMessage($group_id, $period="f.time > NOW()", $title="*Места где в ближайшее время прогнозируется летная погода*") {
		$items = $this->getObservedItems($period);

        $list = [];
        $place = null;
        $date = null;
        foreach ($items as $item) {

            if ($place != $item['name']) {
                $place = $item['name'];
                $label = "[{$item['name']}](https://www.windy.com/{$item['lat']}/{$item['lon']}/gfs)\n";
                $date = null;
            } else $label = '';

            $curDate = date('d.m', strtotime($item['time']));
            $curTime = date('H:i', strtotime($item['time']));
            
            if ($curDate != $date) {
            	$date = $curDate;
            	$timeLabel = $curDate." ".$curTime;
            } else $timeLabel = "          ".$curTime;

            $list[] = $label."   ".$timeLabel.", ".ForecastModel::toAzimuth($item['wind_deg']).", ".round($item['wind_speed'], 1)."м/с, ".$item['weather_description'];
        }

        if (count($list) == 0)
        	$result = "*Нет погоды в ближайшее время*";
        else $result = $title."\n\n".implode("\n", $list);
        
        return $result;
	}

	public static function toAzimuth($degrees) {
	    // Нормализуем градусы (0-360)
	    $degrees = fmod($degrees, 360);
	    if ($degrees < 0) {
	        $degrees += 360;
	    }

	    // Определяем азимут
	    $directions = [
	        'С', 'ССВ', 'СВ', 
	        'ВСВ', 'В', 'ВЮВ', 
	        'ЮВ', 'ЮЮВ', 'Ю', 
	        'ЮЮЗ', 'ЮЗ', 'ЗЮЗ', 
	        'З', 'ЗСЗ', 'СЗ', 
	        'ССЗ', 'С'
	    ];
	    
	    $index = round($degrees / 22.5) % 16;
	    return $directions[$index];
	}
}
?>