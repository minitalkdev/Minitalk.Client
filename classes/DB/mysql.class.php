<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * MySQL 인터페이스를 정의한다.
 *
 * @file /classes/DB/mysql.class.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
class mysql {
	private function sanitizeSchemaValue($value) {
		return str_replace(array(chr(39), chr(34)), array('&#39;', '&#34;'), strip_tags(str_replace(chr(0), '', (string)$value)));
	}

	private $db;
	private $version = null;
	private $engine = null;
	private $charset = null;
	private $collation = null;
	
	private $_class = null;
	private $_mysqli;
	private $_prefix;
	private $_query;
	private $_lastQuery;
	private $_join = array();
	private $_where = array();
	private $_having = array();
	private $_orderBy = array();
	private $_groupBy = array();
	private $_limit = null;
	private $_bindParams = array('');
	private $_tableDatas = array();
	private $_stmtError;
	private $_tableLockMethod = 'READ';
	public $count = 0;
	private $_transaction_in_progress = false;
	
	/**
	 * 클래스를 초기화한다.
	 *
	 * @param object $db 데이터베이스 접속정보
	 * @param object $class 데이터베이스 루트클래스
	 */
	function __construct($db=null,$class=null) {
		if ($db !== null) {
			$this->db = $db;
			$this->db->port = self::normalizePort($this->db->port ?? null);
			if (isset($this->db->charset) == false) $this->db->charset = 'utf8mb4';
		}
		
		$this->_class = $class;
	}
	
	/**
	 * 데이터베이스에 접속한다.
	 *
	 * @param mysqli $mysqli mysqli 클래스객체
	 * @return mysqli $mysqli mysqli 클래스객체
	 */
	function connect($mysqli=null) {
		if ($mysqli != null) {
			$this->_mysqli = $mysqli;
		} else {
			// PHP 8.1+ 기본값(예외 모드) 대신, 이 클래스가 전제하는 false 반환 방식으로 에러를 처리한다.
			mysqli_report(MYSQLI_REPORT_OFF);
			ob_start();
			$this->_mysqli = new mysqli($this->db->host,$this->db->username,$this->db->password,$this->db->database,$this->db->port);
			ob_end_clean();
			if ($this->_mysqli->connect_error) $this->_error($this->_mysqli->connect_error);
			$this->_mysqli->set_charset($this->db->charset);
		}
		
		$this->version = $this->_mysqli->server_info;
		if (version_compare($this->version,'5.6.4','>=') == true) $this->engine = 'InnoDB';
		else $this->engine = 'MyISAM';
		if (version_compare($this->version,'5.5.3','>=') == true) {
			$this->charset = 'utf8mb4';
			$this->collation = 'utf8mb4_unicode_ci';
		} else {
			$this->charset = 'utf8';
			$this->collation = 'utf8_general_ci';
		}
		
		return $this->_mysqli;
	}
	
	/**
	 * 데이터베이스 접속정보를 가져온다.
	 *
	 * @param object $db
	 */
	function db() {
		return $this->db;
	}
	
	/**
	 * mysqli 클래스객체를 가져온다.
	 *
	 * @param mysqli $mysqli
	 */
	function mysqli() {
		return $this->_mysqli;
	}
	
	/**
	 * 데이터베이스에 접속이 가능한지 확인한다.
	 *
	 * @param object $db 데이터베이스 접속정보
	 * @return boolean $success 접속성공여부
	 */
	static function normalizePort($port) {
		if ($port === null || $port === '') return 3306;
		$value = filter_var($port,FILTER_VALIDATE_INT,array('options'=>array('min_range'=>1,'max_range'=>65535)));
		if ($value === false) throw new InvalidArgumentException('INVALID_DATABASE_PORT');
		return $value;
	}

	function check($db) {
		try {
			$db->port = self::normalizePort($db->port ?? null);
			$mysqli = new mysqli($db->host,$db->username,$db->password,$db->database,$db->port);
		} catch (Throwable $e) {
			return false;
		}
		if ($mysqli->connect_errno) return false;
		$mysqli->close();
		return true;
	}
	
	/**
	 * 데이터베이스 서버에 ping 을 보낸다.
	 *
	 * @return boolean $pong
	 */
	function ping() {
		// mysqli::ping() is deprecated; reconnect explicitly after a lost connection.
		try {
			return $this->_mysqli->query('SELECT 1') !== false;
		} catch (mysqli_sql_exception $e) {
			return false;
		}
	}
	
	/**
	 * 테이블명 앞에 고정적으로 사용될 prefix 값을 가져온다.
	 *
	 * @return string $prefix
	 */
	function getPrefix() {
		return $this->_prefix;
	}
	
	/**
	 * 테이블명 앞에 고정적으로 사용될 prefix 값을 설정한다.
	 *
	 * @param string $prefix
	 */
	function setPrefix($prefix) {
		$this->_prefix = $prefix;
		return $this;
	}
	
	/**
	 * 테이블명 앞에 고정적으로 사용되는 prefix 값을 포함한 전체 테이블명을 가져온다.
	 *
	 * @param string $table prefix 가 없는 테이블명
	 * @return string $table prefix 가 포함된 테이블명
	 */
	function getTable($table) {
		return $this->_prefix.$table;
	}
	
	/**
	 * 진행중인 쿼리빌더를 초기화한다.
	 */
	function reset() {
		$this->_where = array();
		$this->_having = array();
		$this->_join = array();
		$this->_orderBy = array();
		$this->_groupBy = array(); 
		$this->_limit = null;
		$this->_bindParams = array('');
		$this->_tableDatas = array();
		$this->_query = null;
		$this->count = 0;
	}
	
	/**
	 * 쿼리빌더 없이 RAW 쿼리를 실행한다.
	 *
	 * @param string $query 쿼리문
	 * @param any[] $bindParams 바인딩할 변수
	 * @param boolean $sanitize SQL 인젝션 방어를 위한 필터사용여부 (기본값 : true)
	 * @return object 쿼리실행결과
	 */
	function rawQuery($query,$bindParams=null,$sanitize=true) {
		$this->_query = $query;
		if ($sanitize) $this->_query = strip_tags(str_replace(chr(0), '', $query));
		$stmt = $this->_prepareQuery();
		if (is_array($bindParams) === true) {
			$params = array('');
			foreach($bindParams as $prop=>$val) {
				$params[0].= $this->_determineType($val);
				array_push($params,$bindParams[$prop]);
			}
			call_user_func_array(array($stmt,'bind_param'),$this->_refValues($params));
		}
		$stmt->execute();
		$this->_stmtError = $stmt->error;
		$this->reset();
		return $this->_dynamicBindResults($stmt);
	}
	
	/**
	 * 데이터베이스의 전체 테이블목록을 가져온다.
	 *
	 * @param boolean $include_desc 테이블구조 포함여부
	 * @return object[] $tables
	 */
	function tables($include_desc=false) {
		if ($include_desc == true) {
			$tables = $this->rawQuery('SHOW TABLE STATUS');
			for ($i=0, $loop=count($tables);$i<$loop;$i++) {
				$table = new stdClass();
				$table->name = $tables[$i]->Name;
				$table->engine = $tables[$i]->Engine;
				$table->rows = $tables[$i]->Rows;
				$table->data_length = $tables[$i]->Data_length;
				$table->index_length = $tables[$i]->Index_length;
				$table->total_length = $table->data_length + $table->index_length;
				$table->collation = $tables[$i]->Collation;
				$table->comment = $tables[$i]->Comment;
				
				$tables[$i] = $table;
			}
		} else {
			$tables = $this->rawQuery("SHOW TABLES");
			for ($i=0, $loop=count($tables); $i<$loop;$i++) {
				foreach ($tables[$i] as $key=>$name) {
					$tables[$i] = $name;
				}
			}
		}
		
		return $tables;
	}
	
	/**
	 * 테이블명이 존재하는지 확인한다.
	 *
	 * @param string $table 테이블명
	 * @param boolean $included_prefix 테이블명에 prefix 포함여부
	 * @param boolean $exists
	 */
	function exists($table,$included_prefix=false) {
		$table = $this->sanitizeSchemaValue($table);
		$count = $this->rawQuery("SHOW TABLES LIKE '".($included_prefix == true ? '' : $this->_prefix).$table."'");
		return count($count) == 1;
	}
	
	/**
	 * 테이블의 용량을 가져온다.
	 *
	 * @param string $table 테이블명
	 * @param boolean $included_prefix 테이블명에 prefix 포함여부
	 * @return int $size
	 */
	function size($table,$included_prefix=false) {
		$table = $this->sanitizeSchemaValue($table);
		$data = $this->rawQuery("SELECT `DATA_LENGTH`, `INDEX_LENGTH` FROM `information_schema`.`TABLES` WHERE `table_schema`='".$this->db->database."' and `table_name`='".($included_prefix == true ? '' : $this->_prefix).$table."'");
		
		if (count($data) > 0) {
			return $data[0]->DATA_LENGTH + $data[0]->INDEX_LENGTH;
		} else {
			return 0;
		}
	}
	
	/**
	 * 테이블의 구조를 가져온다.
	 *
	 * @param string $table 테이블명
	 * @param boolean $included_prefix 테이블명에 prefix 포함여부
	 * @return object[] $desc
	 */
	function desc($table,$included_prefix=false) {
		$columns = $this->rawQuery('SHOW FULL COLUMNS FROM `'.($included_prefix == true ? '' : $this->_prefix).$table.'`');
		for ($i=0, $loop=count($columns);$i<$loop;$i++) {
			$column = new stdClass();
			$column->field = $columns[$i]->Field;
			
			if (preg_match('/(.*?)\(([0-9]+)\)/',$columns[$i]->Type,$match) == true) {
				$column->type = $match[1];
				$column->length = intval($match[2]);
			} else {
				$column->type = $columns[$i]->Type;
				$column->length = null;
			}
			$column->collation = $columns[$i]->Collation;
			$column->key = $columns[$i]->Key;
			$column->comment = $columns[$i]->Comment;
			$column->null = $columns[$i]->Null == 'YES';
			$column->auto_increment = $columns[$i]->Extra == 'auto_increment';
			$column->origin = $columns[$i];
			
			$columns[$i] = $column;
		}
		
		return $columns;
	}
	
	/**
	 * 테이블의 구조를 비교한다.
	 *
	 * @param string $table 테이블명
	 * @param object $schema 테이블구조
	 * @param boolean $included_prefix 테이블명에 prefix 포함여부
	 * @return boolean $is_coincidence
	 */
	function compare($table,$schema,$included_prefix=false) {
		$table = $this->sanitizeSchemaValue($table);
		$desc = $this->rawQuery('SHOW FULL COLUMNS FROM `'.($included_prefix == true ? '' : $this->_prefix).$table.'`');
		if (count($desc) != count(array_keys((array)$schema->columns))) return false;
		
		$auto_increment = '';
		for ($i=0, $loop=count($desc);$i<$loop;$i++) {
			
			if (isset($schema->columns->{$desc[$i]->Field}) == false) return false;
			if ($desc[$i]->Collation && $desc[$i]->Collation != 'utf8mb4_unicode_ci') return false;
			
			$compare = $schema->columns->{$desc[$i]->Field};
			if (preg_match('/(.*?)\((.*?)\)$/',$desc[$i]->Type,$match) == true) {
				if ($compare->type != $match[1]) return false;
				if ($compare->length != $match[2]) return false;
			} else {
				if ($compare->type != $desc[$i]->Type) return false;
			}
			
			if ((isset($compare->default) == true && (strlen($compare->default) != strlen($desc[$i]->Default) || $compare->default != $desc[$i]->Default)) || isset($compare->default) == false && $desc[$i]->Default != null) return false;
			
			$compare->is_null = isset($compare->is_null) == true && $compare->is_null === true;
			if (($compare->is_null == true && $desc[$i]->Null == 'NO') || $compare->is_null == false && $desc[$i]->Null == 'YES') return false;
			
			if (isset($desc[$i]->Extra) == true && $desc[$i]->Extra == 'auto_increment') {
				if (isset($schema->auto_increment) == false || $schema->auto_increment != $desc[$i]->Field) return false;
				$auto_increment = $desc[$i]->Field;
			}
			
			if (isset($compare->comment) == true && $compare->comment != $desc[$i]->Comment) {
				$query = 'ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` CHANGE `'.$desc[$i]->Field.'` `'.$desc[$i]->Field.'` '.$desc[$i]->Type;
				if ($desc[$i]->Null == 'NO') $query.= ' NOT NULL';
				else $query.= ' NULL';
				if (isset($compare->default) == true) $query.= " DEFAULT '".$compare->default."'";
				if ($desc[$i]->Extra == 'auto_increment') $query.= ' AUTO_INCREMENT';
				$query.= " COMMENT '".$compare->comment."'";
				$this->rawQuery($query);
			}
		}
		
		$index = $this->rawQuery('SHOW INDEX FROM `'.($included_prefix == true ? '' : $this->_prefix).$table.'`');
		
		$indexByType = array();
		for ($i=0, $loop=count($index);$i<$loop;$i++) {
			if (isset($indexByType[$index[$i]->Key_name]) == false) $indexByType[$index[$i]->Key_name] = array('column'=>'','type'=>$index[$i]->Index_type,'unique'=>$index[$i]->Non_unique == 0);
			$indexByType[$index[$i]->Key_name]['column'] = $indexByType[$index[$i]->Key_name]['column'] ? $indexByType[$index[$i]->Key_name]['column'].','.$index[$i]->Column_name : $index[$i]->Column_name;
		}
		
		$index = array();
		foreach ($indexByType as $key=>$value) {
			if ($key == 'PRIMARY') {
				$index[$value['column']] = 'primary_key';
			} elseif ($value['type'] == 'FULLTEXT') {
				$index[$value['column']] = 'fulltext';
			} else {
				$index[$value['column']] = $value['unique'] == true ? 'unique' : 'index';
			}
		}
		
		foreach ($index as $column=>$type) {
			if (isset($schema->indexes->$column) == false || $schema->indexes->$column != $type) return false;
		}
		
		foreach ($schema->indexes as $column=>$type) {
			if (isset($index[$column]) == false) {
				$column = $this->sanitizeSchemaValue($column);
				$column = '`'.str_replace(',','`,`',$column).'`';
				if ($type == 'primary_key') {
					$this->rawQuery('ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` ADD PRIMARY KEY('.$column.')');
					if ($this->getLastError()) return false;
				} elseif ($type == 'index') {
					$this->rawQuery('ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` ADD INDEX('.$column.')');
					if ($this->getLastError()) return false;
				} elseif ($type == 'fulltext') {
					$this->rawQuery('ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` ADD FULLTEXT('.$column.')');
					if ($this->getLastError()) return false;
				} elseif ($type == 'unique') {
					$this->rawQuery('ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` ADD UNIQUE('.$column.')');
					if ($this->getLastError()) return false;
				}
			}
		}
		
		if (isset($schema->auto_increment) == true && $auto_increment != $schema->auto_increment) return false;
		
		if (isset($schema->comment) == true) {
			$comment = $this->rawQuery("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = SCHEMA() AND TABLE_NAME = '".($included_prefix == true ? '' : $this->_prefix).$table."'");
			if ($comment[0]->TABLE_COMMENT != $schema->comment) {
				$this->rawQuery("ALTER TABLE `".($included_prefix == true ? '' : $this->_prefix).$table."` COMMENT = '".$schema->comment."'");
			}
		}
		
		return true;
	}
	
	/**
	 * 테이블을 생성한다.
	 *
	 * @param string $table 테이블명
	 * @paran object $schema 테이블구조
	 * @param boolean $included_prefix 테이블명에 prefix 포함여부
	 * @return boolean $success
	 */
	function create($table,$schema,$included_prefix=false) {
		$table = $this->sanitizeSchemaValue($table);
		$query = 'CREATE TABLE IF NOT EXISTS `'.($included_prefix == true ? '' : $this->_prefix).$table.'` (';
		
		$isFirst = true;
		foreach ($schema->columns as $column=>$options) {
			if ($isFirst == false) $query.= ', ';
			
			$column = $this->sanitizeSchemaValue($column);
			$query.= '`'.$column.'` ';
			if (isset($options->length) == true) {
				$query.= $options->type.'('.$options->length.')';
			} else {
				$query.= $options->type;
			}
			
			if (isset($options->is_null) == true && $options->is_null == true) {
				$query.= ' NULL';
			} else {
				$query.= ' NOT NULL';
			}
			
			if (isset($options->default) == true) {
				$query.= " DEFAULT '".$this->escape($options->default)."'";
			}
			
			if (isset($options->comment) == true) {
				$query.= " COMMENT '".$this->escape($options->comment)."'";
			}
			
			$isFirst = false;
		}
		$query.= ') ENGINE = '.$this->engine.' CHARACTER SET '.$this->charset.' COLLATE '.$this->collation;
		if (isset($schema->comment) == true) $query.= " COMMENT = '".$schema->comment."'";
		$this->rawQuery($query);
		if ($this->getLastError()) return false;
		
		foreach ($schema->indexes as $column=>$index) {
			$column = $this->sanitizeSchemaValue($column);
			$column = '`'.str_replace(',','`,`',$column).'`';
			
			if ($index == 'primary_key') {
				$this->rawQuery('ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` ADD PRIMARY KEY('.$column.')');
			} elseif ($index == 'index') {
				$this->rawQuery('ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` ADD INDEX('.$column.')');
			} elseif ($index == 'fulltext') {
				if (version_compare($this->version,'5.7.6','>=') == true) {
					$this->rawQuery('ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` ADD FULLTEXT('.$column.') WITH PARSER ngram');
				} else {
					$this->rawQuery('ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` ADD FULLTEXT('.$column.')');
				}
			} elseif ($index == 'unique') {
				$this->rawQuery('ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` ADD UNIQUE('.$column.')');
			}
			
			if ($this->getLastError()) {
				$this->drop($table);
				return false;
			}
		}
		
		if (isset($schema->auto_increment) == true && $schema->auto_increment) {
			$auto_increment = $this->sanitizeSchemaValue($schema->auto_increment);
			$query = 'ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` CHANGE `'.$auto_increment.'` `'.$auto_increment.'` int('.$schema->columns->{$schema->auto_increment}->length.') NOT NULL AUTO_INCREMENT';
			if (isset($schema->columns->{$schema->auto_increment}->comment) == true) $query.= " COMMENT '".$schema->columns->{$schema->auto_increment}->comment."'";
			$this->rawQuery($query);
			if ($this->getLastError()) return false;
		}
		
		return true;
	}
	
	/**
	 * 테이블을 삭제한다.
	 *
	 * @param string $table 테이블명
	 * @param boolean $included_prefix 테이블명에 prefix 포함여부
	 * @return boolean $success
	 */
	function drop($table,$included_prefix=false) {
		$table = $this->sanitizeSchemaValue($table);
		$this->rawQuery('DROP TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'`');
		
		return $this->getLastError() == '';
	}
	
	/**
	 * 테이블을 비운다.
	 *
	 * @param string $table 테이블명
	 * @param boolean $included_prefix 테이블명에 prefix 포함여부
	 * @return boolean $success
	 */
	function truncate($table,$included_prefix=false) {
		$table = $this->sanitizeSchemaValue($table);
		$this->rawQuery('TRUNCATE TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'`');
		
		return $this->getLastError() == '';
	}
	
	/**
	 * 테이블의 이름을 변경한다.
	 *
	 * @param string $table 변경전 테이블명
	 * @param string $newname 변경할 테이블명
	 * @param boolean $included_prefix 테이블명에 prefix 포함여부
	 * @return boolean $success
	 */
	function rename($table,$newname,$included_prefix=false) {
		$table = $this->sanitizeSchemaValue($table);
		$newname = $this->sanitizeSchemaValue($newname);
		$this->rawQuery('RENAME TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` TO `'.($included_prefix == true ? '' : $this->_prefix).$newname.'`');
		
		return $this->getLastError() == '';
	}
	
	/**
	 * 백업테이블을 생성한다.
	 *
	 * @param string $table 백업할 테이블명
	 * @param boolean $included_prefix 테이블명에 prefix 포함여부
	 * @return boolean $success
	 */
	function backup($table,$included_prefix=false) {
		$table = $this->sanitizeSchemaValue($table);
		$backupname = $table.'_BK'.date('YmdHis');
		$this->rawQuery('CREATE TABLE IF NOT EXISTS `'.($included_prefix == true ? '' : $this->_prefix).$backupname.'` SELECT * FROM `'.($included_prefix == true ? '' : $this->_prefix).$table.'`');
		
		return $this->getLastError() == '';
	}
	
	/**
	 * 테이블 구조를 변경한다.
	 *
	 * @param string $table 테이블명
	 * @param string $type 구조변경종류 (ADD, CHANGE, DROP)
	 * @param string $column 컬럼명 (ADD, CHANGE 인 경우 컬럼구조객체)
	 * @param object $target 대상컬럼 (ADD 인 경우 추가할 컬럼위치의 컬럼명)
	 */
	function alter($table,$type,$column,$target=null,$included_prefix=false) {
		$type = strtoupper($type);
		if (in_array($type,array('ADD','CHANGE','DROP')) == false) {
			$this->_error('Bad alter type: Can be either ADD or CHANGE or DROP');
			return false;
		}
		$table = $this->sanitizeSchemaValue($table);
		$query = 'ALTER TABLE `'.($included_prefix == true ? '' : $this->_prefix).$table.'` '.$type.' ';
		
		if ($type == 'ADD' || $type == 'CHANGE') {
			if (is_array($column) == true) $column = (object)$column;
			if (is_object($column) == false || isset($column->type) == false) {
				$this->_error('Bad column type');
				return false;
			}
			
			if ($type == 'ADD') {
				if (isset($column->name) == false) {
					$this->_error('Bad column name');
					return false;
				}
				$query.= '`'.$this->sanitizeSchemaValue($column->name).'`';
			}
			
			if ($type == 'CHANGE') {
				if ($target == null) {
					$this->_error('Bad target column');
					return false;
				}
				$query.= ' `'.$target.'` ';
				if (isset($column->name) == true) $query.= '`'.$this->sanitizeSchemaValue($column->name).'`';
				else $query.= $target;
			}
			
			$query.= ' ';
			if (isset($column->length) == true) {
				$query.= $column->type.'('.$column->length.')';
			} else {
				$query.= $column->type;
			}
			
			if (isset($column->is_null) == true && $column->is_null == true) {
				$query.= ' NULL';
			} else {
				$query.= ' NOT NULL';
			}
			
			if (isset($column->default) == true) {
				$query.= " DEFAULT '".$this->escape($column->default)."'";
			}
			
			if (isset($column->comment) == true) {
				$query.= " COMMENT '".$this->escape($column->comment)."'";
			}
			
			if ($type == 'ADD' && $target != null) $query.= ' AFTER `'.$target.'`';
		}
		
		if ($type == 'DROP') {
			if (is_string($column) == false) {
				$this->_error('Bad column name');
				return false;
			}
			$query.= '`'.$this->sanitizeSchemaValue($column).'`';
		}
		
		$this->rawQuery($query);
		
		return $this->getLastError() == '';
	}
	
	/**
	 * LOCK 방법을 설정한다.
	 *
	 * @param string $method 방법 (READ, WRITE)
	 * @return class $this
	 */
	function setLockMethod($method) {
		switch(strtoupper($method)) {
			case 'READ' || 'WRITE' :
				$this->_tableLockMethod = $method;
				break;
			default:
				$this->_error('Bad lock type: Can be either READ or WRITE');
				break;
		}
		return $this;
	}
	
	/**
	 * 테이블을 설정된 LOCK 방법에 따라 LOCK 한다.
	 *
	 * @param string $table prefix 를 포함하지 않은 테이블명
	 * @return boolean $success
	 */
	function lock($table) {
		$this->_query = 'LOCK TABLES';
		
		if(gettype($table) == 'array') {
			foreach($table as $key => $value) {
				if(gettype($value) == 'string') {
					if($key > 0) {
						$this->_query .= ',';
					}
					$this->_query .= ' '.$this->_prefix.$value.' '.$this->_tableLockMethod;
				}
			}
		} else{
			$table = $this->_prefix.$table;
			$this->_query = 'LOCK TABLES '.$table.' '.$this->_tableLockMethod;
		}
		
		$result = $this->_unpreparedQuery($this->_query);
		$errno  = $this->_mysqli->errno;
		
		$this->reset();
		
		if ($result) {
			return true;
		} else {
			$this->_error('Locking of table '.$table.' failed',$errno);
		}
	}
	
	/**
	 * 현재 LOCK 중인 테이블을 UNLOCK 한다.
	 *
	 * @return boolean $success
	 */
	function unlock() {
		$this->_query = 'UNLOCK TABLES';
		$result = $this->_unpreparedQuery($this->_query);
		$errno  = $this->_mysqli->errno;
		$this->reset();
		if ($result) {
			return true;
		} else {
			$this->_error('Unlocking of tables failed',$errno);
			return false;
		}
	}
	
	/**
	 * SELECT 쿼리빌더를 시작한다.
	 *
	 * @param string $table prefix 를 포함하지 않은 테이블명
	 * @param any[] $columns 가져올 컬럼명 (배열 또는 콤마(,)로 구분된 컬럼명)
	 * @return class $this
	 */
	function select($table,$columns='*') {
		if (empty($columns)) $columns = '*';
		$column = is_array($columns) ? implode(',',$columns) : $columns; 
		$this->_query = 'SELECT '.$column.' FROM '.$this->_prefix.$table;
		
		return $this;
	}
	
	/**
	 * INSERT 쿼리빌더를 시작한다.
	 *
	 * @param string $table prefix 를 포함하지 않은 테이블명
	 * @param any[] $data 저장할 데이터 (array(컬럼명=>값))
	 * @return class $this
	 */
	function insert($table,$data) {
		$this->_query = 'INSERT into '.$this->_prefix.$table;
		$this->_tableDatas = $data;
		
		return $this;
	}
	
	/**
	 * REPLACE 쿼리빌더를 시작한다.
	 *
	 * @param string $table prefix 를 포함하지 않은 테이블명
	 * @param any[] $data 저장할 데이터 (array(컬럼명=>값))
	 * @return class $this
	 */
	function replace($table,$data) {
		$this->_query = 'REPLACE into '.$this->_prefix.$table;
		$this->_tableDatas = $data;
		
		return $this;
	}
	
	/**
	 * UPDATE 쿼리빌더를 시작한다.
	 *
	 * @param string $table prefix 를 포함하지 않은 테이블명
	 * @param any[] $data 저장할 데이터 (array(컬럼명=>값))
	 * @return class $this
	 */
	function update($table,$data) {
		$this->_query = 'UPDATE '.$this->_prefix.$table.' SET ';
		$this->_tableDatas = $data;
		
		return $this;
	}
	
	/**
	 * DELETE 쿼리빌더를 시작한다.
	 *
	 * @param string $table prefix 를 포함하지 않은 테이블명
	 * @param any[] $data 저장할 데이터 (array(컬럼명=>값))
	 * @return class $this
	 */
	function delete($table) {
		$this->_query = 'DELETE FROM '.$this->_prefix.$table;
		
		return $this;
	}
	
	/**
	 * WHERE 절을 정의한다. (AND조건)
	 *
	 * @param string $whereProp WHERE 조건절 (컬럼명 또는 WHERE 조건문)
	 * @param any[] $whereValue 검색할 조건값 (컬럼데이터 또는 WHERE 조건문에 바인딩할 값의 배열)
	 * @param string $operator 조건 (=, IN, NOT IN, LIKE 등)
	 * @return class $this
	 */
	function where($whereProp,$whereValue=null,$operator=null) {
		if ($operator) $whereValue = array($operator=>$whereValue);
		$this->_where[] = array('AND',$whereValue,$whereProp);
		return $this;
	}
	
	/**
	 * WHERE 절을 정의한다. (OR조건)
	 *
	 * @param string $whereProp WHERE 조건절 (컬럼명 또는 WHERE 조건문)
	 * @param any[] $whereValue 검색할 조건값 (컬럼데이터 또는 WHERE 조건문에 바인딩할 값의 배열)
	 * @param string $operator 조건 (=, IN, NOT IN, LIKE 등)
	 * @return class $this
	 */
	function orWhere($whereProp,$whereValue=null,$operator=null) {
		if ($operator) $whereValue = array($operator=>$whereValue);
		$this->_where[] = array('OR',$whereValue,$whereProp);
		return $this;
	}
	
	/**
	 * HAVING 절을 정의한다. (AND조건)
	 *
	 * @param string $havingProp HAVING 조건절 (컬럼명 또는 WHERE 조건문)
	 * @param any[] $havingValue 검색할 조건값 (컬럼데이터 또는 WHERE 조건문에 바인딩할 값의 배열)
	 * @param string $operator 조건 (=, IN, NOT IN, LIKE 등)
	 * @return class $this
	 */
	function having($havingProp,$havingValue=null,$operator=null) {
		if ($operator) $havingValue = array($operator=>$havingValue);
		$this->_having[] = array('AND',$havingValue,$havingProp);
		return $this;
	}
	
	/**
	 * HAVING 절을 정의한다. (OR조건)
	 *
	 * @param string $havingProp HAVING 조건절 (컬럼명 또는 WHERE 조건문)
	 * @param any[] $havingValue 검색할 조건값 (컬럼데이터 또는 WHERE 조건문에 바인딩할 값의 배열)
	 * @param string $operator 조건 (=, IN, NOT IN, LIKE 등)
	 * @return class $this
	 */
	function orHaving($havingProp,$havingValue=null,$operator=null) {
		if ($operator) $havingValue = array($operator=>$havingValue);
		$this->_having[] = array('OR',$havingValue,$havingProp);
		return $this;
	}
	
	/**
	 * JOIN 절을 정의한다. (AND조건)
	 *
	 * @param string $joinTable JOIN 할 prefix 가 포함되지 않은 테이블명
	 * @param string $joinCondition JOIN 조건
	 * @param string $joinType 조인형태 (LEFT, RIGHT, OUTER, INNER, LEFT OUTER, RIGHT OUTER)
	 * @return class $this
	 */
	function join($joinTable,$joinCondition,$joinType = '') {
		$allowedTypes = array('LEFT','RIGHT','OUTER','INNER','LEFT OUTER','RIGHT OUTER');
		$joinType = strtoupper(trim($joinType));
		if ($joinType && in_array($joinType,$allowedTypes) == false)
			die('Wrong JOIN type: '.$joinType);
		if (is_object($joinTable) == false) {
			$joinTable = $this->_prefix.$this->sanitizeSchemaValue($joinTable);
		}
		$this->_join[] = array($joinType,$joinTable,$joinCondition);
		return $this;
	}
	
	/**
	 * ORDER 절을 정의한다.
	 *
	 * @param string $orderByField 정렬할 필드명
	 * @param string $orderbyDirection 정렬순서 (ASC, DESC)
	 * @param string $customFields 커스덤필드배열 (테이블에 정의된 컬럼이 아닌 경우)
	 * @return class $this
	 */
	function orderBy($orderByField,$orderbyDirection='DESC',$customFields=null) {
		$allowedDirection = array('ASC','DESC');
		$orderbyDirection = strtoupper(trim($orderbyDirection));
		$orderByField = preg_replace('/[^-a-z0-9\.\(\),_]+/i','',$orderByField);
		if (empty($orderbyDirection) == true || in_array($orderbyDirection,$allowedDirection) == false) $this->_error('Wrong order direction: '.$orderbyDirection);
		
		if (is_array($customFields) == true) {
			foreach ($customFields as $key=>$value) {
				$customFields[$key] = preg_replace('/[^-a-z0-9\.\(\),_]+/i','',$value);
			}
			$orderByField = 'FIELD ('.$orderByField.',"'.implode('","',$customFields).'")';
		}
		$this->_orderBy[$orderByField] = $orderbyDirection;
		return $this;
	}
	
	/**
	 * GROUP 절을 정의한다.
	 *
	 * @param string $groupByField GROUP 할 컬럼명
	 * @return class $this
	 */
	function groupBy($groupByField) {
		$groupByField = preg_replace ('/[^-a-z0-9\.\(\),_]+/i','',$groupByField);
		$this->_groupBy[] = $groupByField;
		return $this;
	}
	
	/**
	 * LIMIT 절을 정의한다.
	 *
	 * @param int $start 시작점
	 * @param int $limit 가져올 갯수 ($limit 이 정의되지 않은 경우, 0번째 부터 $start 갯수만큼 가져온다.)
	 * @return class $this
	 */
	function limit($start,$limit=null) {
		$start = is_numeric($start) == false || $start < 0 ? 0 : $start;
		if ($limit != null) {
			$this->_limit = array($start,$limit);
		} else {
			$this->_limit = array(0,$start);
		}
		return $this;
	}
	
	/**
	 * 쿼리를 실행한다.
	 *
	 * @return object 실행결과
	 */
	function execute() {
		if (preg_match('/^SELECT /',$this->_query) == true) {
			return $this->get();
		} elseif (preg_match('/^INSERT /',$this->_query) == true) {
			$stmt = $this->_buildQuery();
			$stmt->execute();
			$this->_stmtError = $stmt->error;
			$this->reset();
			$this->count = $stmt->affected_rows;
			if ($stmt->affected_rows < 1) return false;
			if ($stmt->insert_id > 0) return $stmt->insert_id;
			return true;
		} elseif (preg_match('/^DELETE /',$this->_query) == true) {
			$stmt = $this->_buildQuery();
			$stmt->execute();
			$this->_stmtError = $stmt->error;
			$this->reset();
			return ($stmt->affected_rows > 0);
		} else {
			$stmt = $this->_buildQuery();
			$status = $stmt->execute();
			$this->reset();
			$this->_stmtError = $stmt->error;
			$this->count = $stmt->affected_rows;
			return $status;
		}
	}
	
	/**
	 * SELECT 쿼리문에 의해 선택된 데이터의 갯수를 가져온다.
	 *
	 * @return boolean $has
	 */
	function count() {
		if (count($this->_groupBy) == 0) {
			$this->_query = preg_replace('/SELECT (.*?) FROM /','SELECT COUNT(*) AS ROW_COUNT FROM ',$this->_query);
			$stmt = $this->_buildQuery();
			$stmt->execute();
			$result = $this->_dynamicBindResults($stmt);
			$this->reset();
			return $result[0]->ROW_COUNT;
		} else {
			$this->_query = preg_replace('/SELECT (.*?) FROM /','SELECT '.$this->_groupBy[0].' FROM ',$this->_query);
			$stmt = $this->_buildQuery();
			$stmt->execute();
			$stmt->store_result();
			$count = $stmt->num_rows;
			$stmt->free_result();
			$this->reset();
			return $count;
		}
	}
	
	/**
	 * SELECT 쿼리문에 의해 선택된 데이터가 존재하는지 확인한다.
	 *
	 * @return boolean $has
	 */
	function has() {
		return $this->count() > 0;
	}
	
	/**
	 * SELECT 쿼리문에 의해 선택된 데이터를 가져온다.
	 *
	 * @param string $field 필드명 (필드명을 지정할 경우, 컬럼명->컬럼값이 아닌 해당 필드명의 값만 배열로 반환한다.)
	 * @return any[] $items
	 */
	function get($field=null) {
		$stmt = $this->_buildQuery();
		
		$stmt->execute();
		$this->_stmtError = $stmt->error;
		$this->reset();
		return $this->_dynamicBindResults($stmt,$field);
	}
	
	/**
	 * SELECT 쿼리문에 의해 선택된 데이터중 한개만 가져온다.
	 *
	 * @param string $field 필드명 (필드명을 지정할 경우, 컬럼명->컬럼값이 아닌 해당 필드명의 값만 반환한다.)
	 * @return object $item
	 */
	function getOne($field=null) {
		$result = $this->get();
		
		$item = null;
		if (is_object($result) == true) $item = $result;
		if (isset($result[0]) == true) $item = $result[0];
		
		if ($field != null) {
			return $item != null && isset($item->{$field}) == true ? $item->{$field} : null;
		} else {
			return $item;
		}
	}
	
	/**
	 * 마지막으로 실행한 INSERT 쿼리문의 결과값(insert_id, AUTO_INCREMENT)을 가져온다.
	 *
	 * @return int $insert_id
	 */
	function getInsertId() {
		return $this->_mysqli->insert_id;
	}
	
	/**
	 * 마지막으로 실행한 쿼리문을 가져온다.
	 *
	 * @param string $query
	 */
	function getLastQuery() {
		return $this->_lastQuery;
	}
	
	/**
	 * 마지막으로 실행한 에러메시지를 가져온다.
	 *
	 * @param string $error
	 */
	function getLastError() {
		return trim($this->_stmtError.' '.$this->_mysqli->error);
	}
	
	/**
	 * escape 한 문자열을 가져온다.
	 * 예 : iModule's class -> iModule\'s class
	 *
	 * @param string $str
	 * @return string $escaped_str
	 */
	function escape($str) {
		return $this->_mysqli->real_escape_string($str);
	}
	
	/**
	 * 변수형태을 반환한다.
	 *
	 * @param any $item 변수형태를 파악하기 위한 변수
	 * @return string $type
	 */
	private function _determineType($item) {
		switch (gettype($item)) {
			case 'NULL':
			case 'string':
				return 's';
				break;
			case 'boolean':
			case 'integer':
				return 'i';
				break;
			case 'blob':
				return 'b';
				break;
			case 'double':
				return 'd';
				break;
		}
		return '';
	}
	
	/**
	 * 바인딩 데이터를 처리한다.
	 */
	private function _bindParam($value) {
		$this->_bindParams[0].= $this->_determineType($value);
		array_push ($this->_bindParams,$value);
	}
	
	/**
	 * 바인딩 데이터를 처리한다.
	 */
	private function _bindParams($values) {
		foreach ($values as $value) $this->_bindParam($value);
	}
	
	/**
	 * 바인딩 데이터를 처리한다.
	 */
	private function _buildPair($operator,$value) {
		if (is_object($value) == true) return $this->_error('OBJECT_PAIR');
		$this->_bindParam($value);
		return ' '.$operator.' ? ';
	}
	
	/**
	 * 쿼리빌더로 정의된 설정값을 이용하여 실제 쿼리문을 생성한다.
	 */
	private function _buildQuery() {
		$this->_buildJoin();
		if (empty($this->_tableDatas) == false) $this->_buildTableData($this->_tableDatas);
		$this->_buildWhere();
		$this->_buildGroupBy();
		$this->_buildHaving();
		$this->_buildOrderBy();
		$this->_buildLimit();
		$this->_lastQuery = $this->_replacePlaceHolders($this->_query,$this->_bindParams);

		$stmt = $this->_prepareQuery();
		if (count($this->_bindParams) > 1) call_user_func_array(array($stmt,'bind_param'),$this->_refValues($this->_bindParams));
		return $stmt;
	}
	
	/**
	 * 쿼리 실행결과 반환된 결과값을 정리한다.
	 *
	 * @param mysqli_stmt $stmt
	 * @param string[] $selector 가져올 값
	 * @return any[] $result
	 */
	private function _dynamicBindResults(mysqli_stmt $stmt,$selector=null) {
		$parameters = array();
		$results = array();
		$meta = $stmt->result_metadata();
		if(!$meta && $stmt->sqlstate) { 
			return array();
		}
		$row = array();
		while ($field = $meta->fetch_field()) {
			$row[$field->name] = null;
			$parameters[] = &$row[$field->name];
		}
		$stmt->store_result();
		
		call_user_func_array(array($stmt,'bind_result'),$parameters);
		$this->count = 0;
		while ($stmt->fetch()) {
			$result = array();
			foreach ($row as $key=>$val) {
				$result[$key] = isset($val) == false || $val === null ? '' : $val;
			}
			array_push($results,$selector == null ? (object)$result : (isset($result[$selector]) == true ? $result[$selector] : ''));
			$this->count++;
		}
		$stmt->free_result();
		return $results;
	}
	
	/**
	 * JOIN 절을 생성한다.
	 */
	private function _buildJoin() {
		if (empty($this->_join) == true) return;
		foreach ($this->_join as $data) {
			list($joinType,$joinTable,$joinCondition) = $data;
			
			if (is_object($joinTable) == true) $joinStr = $this->_buildPair('',$joinTable);
			else $joinStr = $joinTable;
			
			$this->_query.= ' '.$joinType.' JOIN '.$joinStr.' on '.$joinCondition;
		}
	}
	
	/**
	 * 테이블 데이터절 생성한다.
	 *
	 * @param any[] $tableData 테이블데이터 (array(컬럼명=>값))
	 */
	private function _buildTableData($tableData) {
		if (is_array($tableData) == false) return;
		
		$isInsert = strpos($this->_query,'INSERT') !== false || strpos($this->_query,'REPLACE') !== false;
		$isUpdate = strpos($this->_query,'UPDATE');
		if ($isInsert !== false) {
			$this->_query.= '(`'.implode('`,`',array_keys($tableData)).'`)';
			$this->_query.= ' VALUES(';
		}
		
		foreach ($tableData as $column=>$value) {
			if ($isUpdate !== false) $this->_query.= '`'.$column.'` = ';
			if (is_null($value) == true) {
				$this->_query.= 'NULL,';
				continue;
			}
			if (is_object($value) == true) {
				$this->_query.= $this->_buildPair('',$value).',';
				continue;
			}
			if (is_array($value) == false) {
				$this->_bindParam($value);
				$this->_query.= '?,';
				continue;
			}
			$key = key($value);
			$val = $value[$key];
			switch ($key) {
				case '[I]':
					$this->_query.= $column.$val.',';
					break;
				case '[F]':
					$this->_query.= $val[0].',';
					if (empty($val[1]) == false) $this->_bindParams($val[1]);
					break;
				case '[N]':
					if ($val == null) $this->_query.= '!'.$column.',';
					else $this->_query.= '!'.$val.',';
					break;
				default:
					$this->_error('Wrong operation');
			}
		}
		$this->_query = rtrim($this->_query,',');
		if ($isInsert !== false) $this->_query.= ')';
	}
	
	/**
	 * WHERE 절을 생성한다.
	 */
	private function _buildWhere() {
		if (empty($this->_where) == true) return;
		$this->_query.= ' WHERE ';
		$this->_where[0][0] = '';
		
		foreach ($this->_where as $index=>&$cond) {
			list ($concat,$wValue,$wKey) = $cond;
			
			if ($wKey == '(') {
				$this->_query.= ' '.$concat.' ';
				if (isset($this->_where[$index+1]) == true) $this->_where[$index+1][0] = '';
			} elseif ($wKey != ')') {
				$this->_query.= ' '.$concat.' ';
			}
			if (is_array($wValue) == false || (strtolower(key($wValue)) != 'inset' && strtolower(key($wValue)) != 'fulltext')) $this->_query.= $wKey;
			
			if ($wValue === null) continue;
			
			if (is_array($wValue) == false) $wValue = array('='=>$wValue);
			
			$key = key($wValue);
			$val = $wValue[$key];
			switch (strtolower($key)) {
				case '0':
					$this->_bindParams($wValue);
					break;
				case 'not in':
				case 'in':
					$comparison = ' '.$key.' (';
					if (is_object($val) == true) {
						$comparison.= $this->_buildPair('',$val);
					} else {
						foreach ($val as $v) {
							$comparison.= ' ?,';
							$this->_bindParam($v);
						}
					}
					$this->_query.= rtrim($comparison,',').' ) ';
					break;
				case 'inset' :
					$comparison = ' FIND_IN_SET (?,'.$wKey.')';
					$this->_bindParam($val);
					
					$this->_query.= $comparison;
					break;
				case 'is not':
					$this->_query.= ' IS NOT NULL';
					break;
				case 'is':
					$this->_query.= ' IS NULL';
					break;
				case 'not between':
				case 'between':
					$this->_query.= " $key ? AND ? ";
					$this->_bindParams($val);
					break;
				case 'not exists':
				case 'exists':
					$this->_query.= $key.$this->_buildPair('',$val);
					break;
				case 'not like':
				case 'like':
					$this->_query .= " $key ? ";
					$this->_bindParam($val);
					break;
				case 'fulltext':
					$comparison = ' MATCH ('.$wKey.') AGAINST (? IN BOOLEAN MODE)';
					
					$keylist = explode(' ',$val);
					for ($i=0, $loop=count($keylist);$i<$loop;$i++) {
						$keylist[$i] = '\'+'.$keylist[$i].'*\'';
					}
					$keylist = implode(' ',$keylist);
					
					$this->_bindParam($keylist);
					$this->_query.= $comparison;
					
					break;
				default:
					$this->_query.= $this->_buildPair($key,$val);
			}
		}
	}
	
	/**
	 * HAVING 절을 생성한다.
	 */
	private function _buildHaving() {
		if (empty($this->_having) == true) return;
		$this->_query.= ' HAVING ';
		$this->_having[0][0] = '';
		
		foreach ($this->_having as $index=>&$cond) {
			list ($concat,$wValue,$wKey) = $cond;
			
			if ($wKey == '(') {
				$this->_query.= ' '.$concat.' ';
				if (isset($this->_having[$index+1]) == true) $this->_having[$index+1][0] = '';
			} elseif ($wKey != ')') {
				$this->_query.= ' '.$concat.' ';
			}
			if (is_array($wValue) == false || (strtolower(key($wValue)) != 'inset' && strtolower(key($wValue)) != 'fulltext')) $this->_query.= $wKey;
			
			if ($wValue === null) continue;
			
			if (is_array($wValue) == false) $wValue = array('='=>$wValue);
			
			$key = key($wValue);
			$val = $wValue[$key];
			switch (strtolower($key)) {
				case '0':
					$this->_bindParams($wValue);
					break;
				case 'not in':
				case 'in':
					$comparison = ' '.$key.' (';
					if (is_object($val) == true) {
						$comparison.= $this->_buildPair('',$val);
					} else {
						foreach ($val as $v) {
							$comparison.= ' ?,';
							$this->_bindParam($v);
						}
					}
					$this->_query.= rtrim($comparison,',').' ) ';
					break;
				case 'inset' :
					$comparison = ' FIND_IN_SET (?,'.$wKey.')';
					$this->_bindParam($val);
					
					$this->_query.= $comparison;
					break;
				case 'is not':
					$this->_query.= ' IS NOT NULL';
					break;
				case 'is':
					$this->_query.= ' IS NULL';
					break;
				case 'not between':
				case 'between':
					$this->_query.= " $key ? AND ? ";
					$this->_bindParams($val);
					break;
				case 'not exists':
				case 'exists':
					$this->_query.= $key.$this->_buildPair('',$val);
					break;
				case 'not like':
				case 'like':
					$this->_query .= " $key ? ";
					$this->_bindParam($val);
					break;
				case 'fulltext':
					$comparison = ' MATCH ('.$wKey.') AGAINST (? IN BOOLEAN MODE)';
					
					$keylist = explode(' ',$val);
					for ($i=0, $loop=count($keylist);$i<$loop;$i++) {
						$keylist[$i] = '\'+'.$keylist[$i].'*\'';
					}
					$keylist = implode(' ',$keylist);
					
					$this->_bindParam($keylist);
					$this->_query.= $comparison;
					
					break;
				default:
					$this->_query.= $this->_buildPair($key,$val);
			}
		}
	}
	
	/**
	 * GROUP 절을 생성한다.
	 */
	private function _buildGroupBy() {
		if (empty($this->_groupBy) == true) return;
		
		$this->_query.= ' GROUP BY ';
		foreach ($this->_groupBy as $key=>$value) {
			$this->_query.= $value.',';
		}
		$this->_query = rtrim($this->_query,',').' ';
	}
	
	/**
	 * ORDER 절을 생성한다.
	 */
	private function _buildOrderBy() {
		if (empty($this->_orderBy) == true) return;
		
		$this->_query.= ' ORDER BY ';
		foreach ($this->_orderBy as $prop=>$value) {
			if (strtolower(str_replace(' ','',$prop)) == 'rand()') $this->_query.= 'rand(),';
			else $this->_query.= $prop.' '.$value.',';
		}
		$this->_query = rtrim($this->_query,',').' ';
	}
	
	/**
	 * LIMIT 절을 생성한다.
	 */
	private function _buildLimit() {
		if ($this->_limit == null) return;
		
		if (is_array($this->_limit) == true) {
			$this->_query.= ' LIMIT '.(int)$this->_limit[0].','.(int)$this->_limit[1];
		} else {
			$this->_query.= ' LIMIT '.(int)$this->_limit;
		}
	}
	
	/**
	 * 쿼리문을 준비시킨다.
	 *
	 * @return mysqli_stmt $stmt
	 */
	private function _prepareQuery() {
		if (!$stmt = $this->_mysqli->prepare($this->_query)) {
			$this->_error('Problem preparing query '.$this->_mysqli->error,$this->_query);
		}
		return $stmt;
	}
	
	/**
	 * prepared 되지 않은 쿼리를 실행한다.
	 *
	 * @param string $query
	 */
	private function _unpreparedQuery($query) {
		$stmt = $this->_mysqli->query($query);
		if (!$stmt) {
			$this->_error('Problem unpreparing query '.$this->_mysqli->error,$query);
		}
		return $stmt;
	}
	
	/**
	 * 데이터베이스 에러메시지를 출력한다.
	 * 루트디비클래스가 있는 경우, 해당 루트디비클래스의 printError() 함수를 이용하여 출력한다.
	 *
	 * @param string $msg 에러메시지
	 * @param string $query 쿼리문
	 */
	private function _error($msg,$query='') {
		$this->reset();
		if ($this->_class == null) die('DATABASE_ERROR : '.$msg.'<br>'.$query);
		else $this->_class->printError($msg,$query);
	}
	
	/**
	 * 데이터값을 call by reference 로 전달하기 위해 변환한다.
	 *
	 * @param any[] $arr
	 * @return any[] $arr
	 */
	private function _refValues($arr) {
		if (strnatcmp(phpversion(),'5.3') >= 0) {
			$refs = array();
			foreach ($arr as $key=>$value) {
				$refs[$key] = &$arr[$key];
			}
			return $refs;
		}
		return $arr;
	}
	
	/**
	 * 바인딩해야하는 변수를 치환한다.
	 *
	 * @param string $str 바인딩되기전의 문자열
	 * @return string $newStr 바인딩해야하는 변수를 치환한 문자열
	 */
	private function _replacePlaceHolders($str,$vals) {
		$i = 1;
		$newStr = '';
		while ($pos = strpos ($str,'?')) {
			$val = $vals[$i++];
			if (is_object($val == true)) $val = '[object]';
			
			$newStr.= substr($str,0,$pos).$val;
			$str = substr($str,$pos + 1);
		}
		$newStr.= $str;
		return $newStr;
	}
	
	function interval($diff,$func='NOW()') {
		$types = array('s'=>'second','m'=>'minute','h'=>'hour','d'=>'day','M'=>'month','Y'=>'year');
		$incr = '+';
		$items = '';
		$type = 'd';
		if ($diff && preg_match('/([+-]?) ?([0-9]+) ?([a-zA-Z]?)/',$diff,$matches)) {
			if (empty($matches[1]) == false) $incr = $matches[1];
			if (empty($matches[2]) == false) $items = $matches[2];
			if (empty($matches[3]) == false) $type = $matches[3];
			if (in_array($type,array_keys($types)) == false) $this->_error('invalid interval type in '.$diff);
			
			$func.= ' '.$incr.' interval '.$items.' '.$types[$type].' ';
		}
		return $func;
	}
	
	function now($diff=null,$func='NOW()') {
		return array('[F]'=>array($this->interval($diff,$func)));
	}
	
	function inc($num=1) {
		return array('[I]'=>'+'.(int)$num);
	}
	
	function dec($num = 1) {
		return array('[I]'=>"-".(int)$num);
	}
	
	function not($col=null) {
		return array('[N]'=>(string)$col);
	}
	
	function func($expr,$bindParams=null) {
		return array('[F]'=>array($expr,$bindParams));
	}
	
	/**
	 * 현재까지 쿼리빌더에 의해 생성된 쿼리를 복제한다.
	 *
	 * @param class $copy 복제된 쿼리빌더 클래스
	 */
	function copy() {
		// 쿼리빌더 배열만 복사하고 mysqli 연결은 공유한다. (mysqli 객체는 직렬화할 수 없다.)
		return clone $this;
	}
	
	/**
	 * 트랜잭션을 시작한다.
	 */
	function startTransaction() {
		$this->_mysqli->autocommit(false);
		$this->_transaction_in_progress = true;
		register_shutdown_function(array($this,'_transaction_status_check'));
	}
	
	/**
	 * 입력된 모든 쿼리를 커밋한다.
	 */
	function commit() {
		$this->_mysqli->commit();
		$this->_transaction_in_progress = false;
		$this->_mysqli->autocommit(true);
	}
	
	/**
	 * 입력된 쿼리를 롤백한다.
	 */
	function rollback() {
		$this->_mysqli->rollback();
		$this->_transaction_in_progress = false;
		$this->_mysqli->autocommit(true);
	}
	
	/**
	 * 트랜잭션 시작이후 입력된 쿼리의 오류를 확인한다.
	 */
	function _transaction_status_check() {
		if (!$this->_transaction_in_progress) return;
		$this->rollback();
	}
}
?>
