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
				trace($query." ".$types);
				$stmt = $this->mysqli->prepare($query);
				$stmt->bind_param($types, ...$params);

				$result = $stmt->execute();
				$stmt->store_result();

				$stmt->close();
			} catch (Exception $e) {
				$this->error('mysql_error='.$e->getMessage().' query='.$query);
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
	}
?>