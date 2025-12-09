<?

    include_once(dirname(__FILE__).'/dataBaseProvider.php');

	class mySQLProvider extends dataBaseProvider {
		protected $mysqli;
		protected $result_type;


		function __construct($host, $dbname, $user='', $passwd='') {
			parent::__construct($host, $dbname, $user, $passwd);
			$this->result_type = MYSQLI_ASSOC;
		}

		public function connect($host, $dbname, $user='', $passwd='') {
			$this->mysqli = new mysqli($host, $user, $passwd, $dbname);
		    if ($this->mysqli->connect_errno) 
		    	$this->error($this->mysqli->connect_errno.', '.$this->mysqli->error);
		}

		public function close() {
			$this->mysqli->close();
		}

		public function safeVal($str) {
			if (is_array($str) || is_object($str)) $str = json_encode($str);
	        return $this->mysqli->real_escape_string($str);
	    }

	    public function bquery($query, $types, $params) {
			$result = false;

			try {
				//trace($query." ".$types);
				$stmt = $this->mysqli->prepare($query);

				$i=0;
		        foreach ($params as $key => $value) {
		            if ($this->isDateTime($value))
		                $params[$key] = $this->formatDateTime($value);
		            if (($types[$i] == 's') && !is_string($value))
		            	$params[$key] = json_encode($value);
		            $i++;
		        }
				$stmt->bind_param($types, ...$params);

				$result = $stmt->execute();
				$stmt->store_result();

				$stmt->close();
			} catch (Exception $e) {
				$this->error('mysql_error='.$e->getMessage().' query='.$query.', data: '.json_encode($params));
			}

			return $result;
	    }

		public function query($query) {
			$result = false;
			try {
				$result = $this->mysqli->query($query);
			} catch (Exception $e) {
				$this->error('mysql_error='.$e->getMessage().' query='.$query);
			}

			return $result;
		}

		public function isTableExists($tableName) {
			return $this->mysqli->query("SHOW TABLES LIKE '{$tableName}'")->num_rows == 1;
		}

		protected function dbAsArray($query) {
			$ret = [];
			if ($result = $this->query($query)) {
				while ($row = $result->fetch_array($this->result_type)) 
					$ret[] = $row;
				
				$result->free();
			}
			return $ret;
		}

		protected function dbOne($query, $column=0) {
			$row=$this->dbLine($query);
			if ($row===false) return false;
			return array_shift($row);
		}

		protected function dbLine($query) {
			$res = false;
			if ($result = $this->query($query)) {
				if ($result->num_rows >= 1) $res = $result->fetch_array($this->result_type);
				$result->free();
			} 
			return $res;
		}

		public function lastID() {
			return $this->one("SELECT LAST_INSERT_ID()");
		}

		public function escape_string($string) {
			return $this->mysqli->escape_string($string);
		}

	    private function isDateTime($value)
	    {
	        if (!is_string($value)) {
	            return false;
	        }
	        
	        // Проверяем форматы даты
	        $patterns = [
	            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', // ISO 8601 с Z
	            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', // ISO 8601 с миллисекундами
	            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', // ISO 8601 с таймзоной
	            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', // MySQL формат
	        ];
	        
	        foreach ($patterns as $pattern) {
	            if (preg_match($pattern, $value)) {
	                return true;
	            }
	        }
	        
	        return false;
	    }

	    private function formatDateTime($dateTimeString)
	    {
	        // Если уже в MySQL формате, возвращаем как есть
	        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateTimeString)) {
	            return $dateTimeString;
	        }
	        
	        try {
	            $date = new \DateTime($dateTimeString);
	            return $date->format('Y-m-d H:i:s');
	        } catch (\Exception $e) {
	            // Если не удалось распарсить, возвращаем текущую дату
	            return date('Y-m-d H:i:s');
	        }
	    }
	}
?>