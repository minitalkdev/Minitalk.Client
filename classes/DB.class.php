<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 데이터베이스 클래스를 정의한다.
 *
 * @file /classes/DB.class.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
class DB {
	/**
	 * 데이터베이스 관련 변수설정
	 *
	 * @private $class DB클래스를 호출한 클래스
	 * @private $connectors 데이터베이스 코드별 접속정보
	 * @private $connections 데이터베이스 코드별 커넥션
	 * @private $classes 최종 호출된 데이터베이스 클래스
	 */
	private $class = null;
	private $connectors = array();
	private $connections = array();
	private $classes = array();
	private $code;
	private $table;
	
	function __construct($class=null) {
		$this->class = $class;
	}
	
	function get($code='default',$prefix=null) {
		global $_CONFIGS;
		
		if (is_object($code) == true) {
			$db = $code;
			$code = sha1(json_encode($code));
		}
		
		if (isset($this->connectors[$code]) == false) {
			if ($code == 'default') $db = $_CONFIGS->db;
			
			// @todo use others db code
			
			if (!$db) return $this;
			
			if (is_file(__MINITALK_PATH__.'/classes/DB/'.$db->type.'.class.php') == false) die('Not Support Database : '.$db->type);
			
			if (isset($db->charset) == false) $db->charset = 'utf8';
			REQUIRE_ONCE __MINITALK_PATH__.'/classes/DB/'.$db->type.'.class.php';
			
			$this->connectors[$code] = $db;
		}
		
		$driver = $this->connectors[$code]->type;
		$dbClass = new $driver($this->connectors[$code],$this);
		if (isset($this->connections[$code]) == false) {
			$this->connections[$code] = $dbClass->connect();
		} else {
			$dbClass->connect($this->connections[$code]);
		}
		
		$prefix = $prefix === null ? __MINITALK_DB_PREFIX__ : $prefix;
		$dbClass->setPrefix($prefix);
		
		$this->classes[$code] = $dbClass;
		
		return $dbClass;
	}
	
	function createCode($type,$host,$username,$password,$database,$port=null,$charset=null) {
		$code = array('type'=>$type,'host'=>$host,'username'=>$username,'password'=>$password,'database'=>$database);
		if ($port !== null) $code['port'] = $port;
		if ($charset !== null) $code['charset'] = $charset;
		
		return Encoder(json_encode($code));
	}
	
	function printError($msg,$query='') {
		if ($this->class == null) die('DATABASE_ERROR : '.$msg.'<br>'.$query);
		
		$this->class->setMode('SAFETY')->printError('DATABASE_ERROR',$msg.'<br>'.$query);
	}
}
?>