<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 클라이언트 클래스를 정의한다.
 * 
 * @file /classes/Minitalk.class.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
class Minitalk {
	/**
	 * DB 관련 변수정의
	 *
	 * @private DB $DB DB에 접속하고 데이터를 처리하기 위한 DB class (@see /classes/DB.class.php)
	 * @private string[] $table DB 테이블 별칭 및 원 테이블명을 정의하기 위한 변수
	 */
	private $DB = null;
	private $table;
	
	/**
	 * 미니톡 클라이언트 설정변수
	 */
	public $language;
	private $mode = null;
	
	/**
	 * 언어셋을 정의한다.
	 * 
	 * @private object $lang 현재 사이트주소에서 설정된 언어셋
	 * @private object $oLang package.json 에 의해 정의된 기본 언어셋
	 */
	private $lang = null;
	private $oLang = null;
	
	/**
	 * DB접근을 줄이기 위해 DB에서 불러온 데이터를 저장할 변수를 정의한다.
	 *
	 * @private $categories 카테고리정보
	 * @private $channels 채널정보
	 */
	private $categories = array();
	private $channels = array();
	private $servers = array();
	private $ipColumnSizes = array();
	
	private $initTime = 0;
	private $timezone; // server timezone
	
	/**
	 * class 선언
	 */
	function __construct() {
		global $_CONFIGS;
		
		/**
		 * 페이지 로딩시간을 구하기 위한 최초 마이크로타임을 기록한다.
		 */
		$this->initTime = $this->getMicroTime();
		
		/**
		 * 타임존 설정
		 * @todo 언젠가 사용할 예정
		 */
		$this->timezone = 'Asia/Seoul';
		date_default_timezone_set($this->timezone);
		
		/**
		 * 미니톡 클라이언트에서 사용하는 DB 테이블 별칭 정의
		 * @see package.json 의 databases 참고
		 */
		$this->table = new stdClass();
		$this->table->admin = 'admin_table';
		$this->table->broadcast = 'broadcast_table';
		$this->table->server = 'server_table';
		$this->table->category = 'category_table';
		$this->table->channel = 'channel_table';
		$this->table->attachment = 'attachment_table';
		$this->table->banip = 'banip_table';
		$this->table->history = 'history_table';
	}
	
	/**
	 * 미니톡 클라이언트의 실행모드를 설정한다.
	 *
	 * @param string $mode 실행모드
	 * @return Minitalk $this
	 */
	function setMode($mode) {
		$this->mode = $mode;
		return $this;
	}
	
	/**
	 * DB클래스를 반환한다.
	 *
	 * @param string $code DB코드 (기본값 : default)
	 * @param string $prefix DB 테이블 앞에 고정적으로 사용되는 PREFIX 명 (정의되지 않을 경우 init.config.php 에서 정의된 __MINITALK_DB_PREFIX__ 상수값을 사용한다.
	 * @return DB $DB
	 */
	function db($code='default',$prefix=null) {
		if ($this->DB == null || $this->DB->ping() === false) {
			$DB = new DB($this);
			$prefix = $prefix === null ? __MINITALK_DB_PREFIX__ : $prefix;
			$this->DB = $DB->get($code,$prefix);
		}
		
		return $this->DB;
	}
	
	/**
	 * 사용중인 DB테이블 별칭을 이용하여 실제 DB테이블 명을 반환한다.
	 *
	 * @param string $table DB테이블 별칭
	 * @return string $table 실제 DB테이블 명
	 */
	function getTable($table) {
		return empty($this->table->$table) == true ? null : $this->table->$table;
	}
	
	/**
	 * 미니톡 API를 호출하였을 경우, API 요청을 처리하기 위한 함수로 API 실행결과를 반환한다.
	 * 소스코드 관리를 편하게 하기 위해 각 요쳥별로 별도의 PHP 파일로 관리한다.
	 *
	 * @param string $protocol API 호출 프로토콜 (get, post, put, delete)
	 * @param string $api API명
	 * @param any $idx API 호출대상 고유값
	 * @param object $params API 호출시 전달된 파라메터
	 * @return object $datas API처리후 반환 데이터 (해당 데이터는 /api/index.php 를 통해 API호출자에게 전달된다.)
	 * @see /api/index.php
	 */
	function getApi($protocol,$api,$idx=null,$params=null) {
		global $_CONFIGS;
		if (!is_string($api) || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/D',$api)) return (object)array('success'=>false,'message'=>'INVALID_API');
		
		$headers = getallheaders();
		$token = null;
		$client_secret = null;
		$secret_key = null;
		
		if (isset($headers['Authorization']) == true || isset($headers['authorization']) == true) {
			$authorization = explode(' ',isset($headers['Authorization']) == true ? $headers['Authorization'] : $headers['authorization']);
			$type = array_shift($authorization);
			if ($type == 'TOKEN') {
				$token = Decoder(implode(' ',$authorization));
				if ($token !== false) $token = json_decode($token);
				else $token = null;
			} elseif ($type == 'CLIENT_SECRET') {
				$client_secret = implode(' ',$authorization);
			} elseif ($type == 'SECRET_KEY') {
				$secret_key = implode(' ',$authorization);
			}
		}
		
		// @todo 7.2.0 버전에서 제거
		if ($client_secret == null && isset($headers['CLIENT_SECRET']) == true) {
			$client_secret = $headers['CLIENT_SECRET'];
		}
		if ($secret_key == null && isset($headers['SECRET_KEY']) == true) {
			$secret_key = $headers['SECRET_KEY'];
		}
		
		$data = new stdClass();
		
		/**
		 * api 폴더에 $api 에 해당하는 파일이 있을 경우 불러온다.
		 */
		if (is_file($this->getPath().'/api/'.$api.'.'.$protocol.'.php') == true) {
			INCLUDE $this->getPath().'/api/'.$api.'.'.$protocol.'.php';
		}
		
		return $data;
	}
	
	/**
	 * 언어셋파일에 정의된 코드를 이용하여 사이트에 설정된 언어별로 텍스트를 반환한다.
	 * 코드에 해당하는 문자열이 없을 경우 1차적으로 package.json 에 정의된 기본언어셋의 텍스트를 반환하고, 기본언어셋 텍스트도 없을 경우에는 코드를 그대로 반환한다.
	 *
	 * @param string $code 언어코드
	 * @param string $replacement 일치하는 언어코드가 없을 경우 반환될 메시지 (기본값 : null, $code 반환)
	 * @return string $language 실제 언어셋 텍스트
	 */
	function getText($code,$replacement=null) {
		if ($this->lang == null) {
			$package = json_decode(file_get_contents(__MINITALK_PATH__.'/package.json'));
			if (file_exists(__MINITALK_PATH__.'/languages/'.$this->language.'.json') == true) {
				$this->lang = json_decode(file_get_contents(__MINITALK_PATH__.'/languages/'.$this->language.'.json'));
				if ($this->language != $package->language) {
					$this->oLang = json_decode(file_get_contents(__MINITALK_PATH__.'/languages/'.$package->language.'.json'));
				}
			} else {
				$this->lang = json_decode(file_get_contents(__MINITALK_PATH__.'/languages/'.$package->language.'.json'));
				$this->oLang = null;
			}
		}
		
		$returnString = null;
		$temp = explode('/',$code);
		
		$string = $this->lang;
		$oString = $this->oLang;
		for ($i=0, $loop=count($temp);$i<$loop;$i++) {
			if (isset($string->{$temp[$i]}) == true) {
				$string = $string->{$temp[$i]};
			} else {
				$string = null;
				break;
			}
		}
		
		if ($string != null) {
			$returnString = $string;
		} elseif ($this->oLang != null) {
			if ($string == null && $this->oLang != null) {
				$string = $this->oLang;
				for ($i=0, $loop=count($temp);$i<$loop;$i++) {
					if (isset($string->{$temp[$i]}) == true) {
						$string = $string->{$temp[$i]};
					} else {
						$string = null;
						break;
					}
				}
			}
			
			if ($string != null) $returnString = $string;
		}
		
		if ($returnString == null) return $replacement === null ? $code : $replacement;
		else return $returnString;
	}
	
	/**
	 * 상황에 맞게 에러코드를 반환한다.
	 *
	 * @param string $code 에러코드
	 * @param object $value(옵션) 에러와 관련된 데이터
	 * @param string $message(옵션) 변환된 에러메시지
	 */
	function getErrorText($code,$value=null,$message=null,$isRawData=false) {
		if (is_object($code) == true) {
			$message = $code->message;
			$description = $code->description;
			$type = $code->type;
		} else {
			$message = '';
			if ($message == null) {
				$message = $this->getText('error/'.$code,$code);
			}
			
			if ($message == $code) {
				$message = $this->getText('error/UNKNOWN');
				$description = $code;
				$type = 'MAIN';
			} else {
				$description = null;
				switch ($code) {
					case 'PHP_ERROR' :
						$description = 'File : '.$value['file'].'<br>Line : '.$value['line'].'<br><br>';
						$description.= nl2br(str_replace(array('<','>'),array('&lt;','&gt;'),$value['message']));
						$type = 'MAIN';
						break;
						
					case 'NOT_FOUND_PAGE' :
						$description = $value ? $value : $this->getUrl();
						$type = 'BACK';
						break;
						
					case 'REQUIRED_LOGIN' :
						$type = 'LOGIN';
						break;
						
					default :
						if ($value != null && is_string($value) == true) $description = $value;
						$type = 'BACK';
				}
				$description = $description === null || strlen($description) == 0 ? null : $description;
			}
		}
		
		if ($isRawData === true) {
			$data = new stdClass();
			$data->message = $message;
			$data->description = $description;
			$data->type = $type;
			
			return $data;
		}
		
		return $message.($description !== null ? ' ('.$description.')' : '');
	}
	
	/**
	 * 미니톡 클라이언트의 상대경로를 가져온다.
	 *
	 * @param string $dir
	 */
	function getDir() {
		return __MINITALK_DIR__;
	}
	
	/**
	 * 미니톡 클라이언트의 절대경로를 가져온다.
	 *
	 * @param string $path
	 */
	function getPath() {
		return __MINITALK_PATH__;
	}
	
	/**
	 * 함수가 호출될 시점의 microtime 을 구한다.
	 *
	 * @return double $microtime
	 */
	function getMicroTime() {
		$microtimestmp = explode(" ",microtime());
		return $microtimestmp[0]+$microtimestmp[1];
	}
	
	/**
	 * 미니톡 클라이언트가 선언되고 나서 함수가 호출되는 시점까지의 수행시간을 구한다.
	 *
	 * @return double $loadtime
	 */
	function getLoadTime() {
		return sprintf('%0.5f',$this->getMicroTime() - $this->initTime);
	}
	
	/**
	 * 모든 첨부파일이 저장되는 절대경로를 반환한다.
	 *
	 * @return string $attachment_path
	 * @see /modules/ModuleAttachment.class.php
	 * @tode 첨부파일 저장되는 경로를 변경할 수 있는 설정값 추가
	 */
	function getAttachmentPath() {
		global $_CONFIGS;
		if (isset($_CONFIGS->attachment) == true && isset($_CONFIGS->attachment->path) == true) return $_CONFIGS->attachment->path;
		return __MINITALK_PATH__.'/attachments';
	}
	
	/**
	 * 미니톡 클라이언트 관리자 로그인정보를 가져온다.
	 *
	 * @return object $logged
	 */
	function getAdminLogged() {
		$logged = Request('MINITALK_LOGGED','session') != null ? Request('MINITALK_LOGGED','session') : Request('MINITALK_LOGGED','cookie');
		$logged = $logged != null && Decoder($logged) !== false && json_decode(Decoder($logged)) !== null ? json_decode(Decoder($logged)) : null;
		if ($logged == null || $logged->ip != GetClientIp()) return null;
		
		$logged = $this->db()->select($this->table->admin)->where('idx',$logged->idx)->getOne();
		$this->setLanguage($logged->language);
		
		return $logged;
	}
	
	/**
	 * 미니톡 클라이언트 관리자 여부를 가져온다.
	 *
	 * @return boolean $is_admin
	 */
	function isAdmin() {
		return $this->getAdminLogged() !== null;
	}
	
	/**
	 * 미니톡 클라이언트의 언어셋을 지정한다.
	 *
	 * @param string $languge
	 */
	function setLanguage($language) {
		$this->language = $language;
	}
	
	/**
	 * 문자버전을 숫자버전로 변경한다.
	 *
	 * @param string $version
	 * @param int $version
	 */
	function getVersionToInt($version) {
		$temp = explode('.',$version);
		return $temp[0].sprintf('%02d',$temp[1]).sprintf('%02d',$temp[2]);
	}
	
	/**
	 * 숫자버전을 문자버전으로 변경한다.
	 *
	 * @param string $version
	 * @param int $version
	 */
	function getIntToVersion($version) {
		$v1 = floor($version / 10000);
		$v2 = floor(($version - $v1 * 10000) / 100);
		$v3 = $version % 100;
		
		return $v1.'.'.$v2.'.'.$v3;
	}
	
	/**
	 * 서버정보를 가져온다.
	 *
	 * @param int $domain 서버도메인
	 * @return object $domain
	 */
	function getServer($domain) {
		global $_CONFIGS;
		
		$server = $this->db()->select($this->table->server)->where('domain',$domain)->getOne();
		if ($server != null) {
			if ($server->latest_update < time() - 60) {
				$this->updateServer($domain);
				return $this->getServer($domain);
			}
			
			if ($server->type == 'SERVER') {
				$connection = new stdClass();
				$connection->domain = $server->domain;
				$connection->client_id = md5($server->domain);
				$connection->connection = Encoder(json_encode(array('max_user'=>0,'client_id'=>md5($server->domain),'key'=>$_CONFIGS->key,'lifetime'=>time() + 3600)));
				
				$server->connection = $connection;
			} else {
				$server->connection = $server->connection ? json_decode($server->connection) : null;
			}
		}
		
		return $server;
	}
	
	/**
	 * 서버상태정보를 가져온다.
	 *
	 * @param string $domain 서버도메인
	 * @return object $status
	 */
	function getServerStatus($domain) {
		return $this->callServerApi('GET',$domain,'status');
	}
	
	/**
	 * 카테고리정보를 가져온다.
	 *
	 * @param int $idx 카테고리 고유값
	 * @return object $category
	 */
	function getCategory($idx) {
		if (isset($this->categories[$idx]) == true) return $this->categories[$idx];
		$this->categories[$idx] = $this->db()->select($this->table->category)->where('idx',$idx)->getOne();
		return $this->categories[$idx];
	}
	
	/**
	 * 카테고리명을 가져온다.
	 *
	 * @param int $idx 카테고리 고유값
	 * @return string $category
	 */
	function getCategoryTitle($idx) {
		$category = $this->getCategory($idx);
		return $category == null ? '' : $category->category;
	}
	
	/**
	 * 채널정보를 가져온다.
	 *
	 * @param string $name 채널명
	 * @return object $channel
	 */
	function getChannel($channel) {
		if (!is_string($channel) || $channel === '') return null;
		if (isset($this->channels[$channel]) == true) return $this->channels[$channel];
		$channel = $this->db()->select($this->table->channel)->where('channel',$channel)->getOne();
		
		if ($channel === null) return null;
		$this->channels[$channel->channel] = $channel;
		return $this->channels[$channel->channel];
	}
	
	/**
	 * 유저코드를 가져온다.
	 *
	 * @param string $nickname 닉네임(필수)
	 * @param int $level 권한레벨 (1~9, 9 : 최고관리자 / 기본값 : 1)
	 * @param string $nickcon 닉이미지 (옵션)
	 * @param string $photo 프로필사진 (옵션)
	 * @param any[] $extras 유저 추가정보 (옵션, key-value 배열만 가능)
	 * @return string $userCode 유저코드
	 */
	function getUserCode($nickname,$level=1,$nickcon=null,$photo=null,$extras=array()) {
		$user = array(
			'nickname'=>$nickname,
			'level'=>intval($level),
			'nickcon'=>$nickcon ? $nickcon : null,
			'photo'=>$photo ? $photo : null,
			'extras'=>count($extras) > 0 ? $extras : null,
			'ip'=>GetClientIp()
		);
		
		return Encoder(json_encode($user,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
	
	/**
	 * 미니톡 클라이언트 API 주소를 가져온다.
	 *
	 * @paran boolean $is_fullurl 전체주소포함 여부
	 * @return string $url
	 */
	function getClientApiUrl($is_fullurl=false) {
		if ($is_fullurl == true) $url = (IsHttps() == true ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
		else $url = '';
		
		return $url.__MINITALK_DIR__.'/api/index.php';
	}
	
	/**
	 * 미니톡 클라이언트 프로세스 주소를 가져온다.
	 *
	 * @param string $action 프로세스액션명
	 * @paran boolean $is_fullurl 전체주소포함 여부
	 * @return string $url
	 */
	function getClientProcessUrl($action,$is_fullurl=false) {
		if ($is_fullurl == true) $url = (IsHttps() == true ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
		else $url = '';
		
		return $url.__MINITALK_DIR__.'/process/'.$action;
	}
	
	/**
	 * 서버 API 를 호출한다.
	 *
	 * @param string $protocol
	 * @param string $server
	 * @param string $api
	 * @param object[] $data
	 * @param object[] $headers
	 * @return object $results
	 */
	function callServerApi($protocol,$server,$api,$data=array(),$headers=array()) {
		global $_CONFIGS;
		
		$headers['authorization'] = 'CLIENT_SECRET_KEY '.$_CONFIGS->key;
		
		$cHeaders = array();
		foreach ($headers as $key=>$value) {
			$cHeaders[] = $key.': '.$value;
		}
		
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_HTTPHEADER,$cHeaders);
		if ($protocol == 'GET') {
			curl_setopt($ch,CURLOPT_URL,$server.'/'.$api.(count($data) > 0 ? '?'.http_build_query($data) : ''));
			curl_setopt($ch,CURLOPT_POST,false);
		} else {
			curl_setopt($ch,CURLOPT_URL,$server.'/'.$api);
			if ($protocol != 'POST') curl_setopt($ch,CURLOPT_CUSTOMREQUEST,$protocol);
			curl_setopt($ch,CURLOPT_POST,true);
			curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));
		}
		
		curl_setopt($ch,CURLOPT_TIMEOUT,5);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
		
		$data = curl_exec($ch);
		$http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		unset($ch);
		
		if ($data && json_decode($data) != null) {
			return json_decode($data);
		} else {
			$results = new stdClass();
			$results->success = false;
			$results->message = 'SERVER CONNECT ERROR.';
			return $results;
		}
	}
	
	/**
	 * 미니톡서비스 API 데이터를 가져온다.
	 *
	 * @param string $api API 명
	 * @param string $query 가져올 데이터
	 */
	function callServiceApi($protocol,$api,$data=array(),$headers=array()) {
		global $_CONFIGS;
		
		$apiUrl = 'https://api.minitalk.io';
		
		$headers['MINITALK_KEY'] = $_CONFIGS->key;
		$headers['MINITALK_CLIENT_VERSION'] = __MINITALK_VERSION__;
		$headers['MINITALK_CLIENT_URL'] = $this->getClientApiUrl(true);
		
		$cHeaders = array();
		foreach ($headers as $key=>$value) {
			$cHeaders[] = $key.': '.$value;
		}
		
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_HTTPHEADER,$cHeaders);
		if ($protocol == 'GET') {
			curl_setopt($ch,CURLOPT_URL,$apiUrl.'/'.$api.(count($data) > 0 ? '?'.http_build_query($data) : ''));
			curl_setopt($ch,CURLOPT_POST,false);
		} else {
			curl_setopt($ch,CURLOPT_URL,$apiUrl.'/'.$api);
			if ($protocol != 'POST') curl_setopt($ch,CURLOPT_CUSTOMREQUEST,$protocol);
			curl_setopt($ch,CURLOPT_POST,true);
			curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		}
		curl_setopt($ch,CURLOPT_TIMEOUT,10);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
		
		$data = curl_exec($ch);
		$http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		unset($ch);
		
		if ($http_code == 200) {
			$results = json_decode($data);
			if ($results === null || isset($results->success) == false) {
				$results = new stdClass();
				$results->success = false;
				$results->message = 'SERVICE_API_FAIL';
			}
		} else {
			$results = new stdClass();
			$results->success = false;
			$results->message = 'SERVICE_API_FAIL';
		}
		
		return $results;
	}
	
	/**
	 * 채널의 채팅서버에 접속하기 위한 서버접속정보를 가져온다.
	 *
	 * @param string $channel 채널명
	 * @return object $connection
	 */
	function getServerConnection($channel) {
		global $_CONFIGS;
		$results = new stdClass();
		$channel = $this->getChannel($channel);
		
		if ($channel == null) {
			$results->success = false;
			$results->error = 'NOT_FOUND_CHANNEL';
			return $results;
		}
		
		/**
		 * 채널에 할당되어 있는 서버가 없을경우
		 */
		if (!$channel->server || $this->getServer($channel->server) == null || $this->getServer($channel->server)->status == 'OFFLINE') {
			$this->updateServer();
		
			/**
			 * 가용한 서버중 접속자수가 가장 작은 서버를 가져온다.
			 */
			$server = $this->db()->select($this->table->server)->where('status','ONLINE')->orderBy('user','asc')->getOne();
			
			/**
			 * 온라인인 서버가 없을 경우
			 */
			if ($server == null) {
				$results->success = false;
				$results->error = 'NOT_FOUND_ONLINE_SERVER';
				return $results;
			}
			
			$this->db()->update($this->table->channel,array('server'=>$server->domain,'user'=>0))->where('channel',$channel->channel)->execute();
			unset($this->channels[$channel->channel]);
			return $this->getServerConnection($channel->channel);
		}
		
		$this->updateServer($channel->server);
		$server = $this->getServer($channel->server);
		
		$channel->use_history = $channel->use_history == 'TRUE';
		
		if ($server->connection == null) {
			$results->success = false;
			$results->error = 'SERVICE_TEMPORARILY_UNAVAILABLE';
		} else {
			$results->success = true;
			$results->connection = clone $server->connection;
			if (isset($_CONFIGS->socket_origins) && is_object($_CONFIGS->socket_origins)) {
				$publicOrigin = $_CONFIGS->socket_origins->{$server->domain} ?? null;
				if ($publicOrigin !== null) {
					$url = is_string($publicOrigin) ? parse_url($publicOrigin) : false;
					if ($url === false || !in_array($url['scheme'] ?? '',array('http','https'),true) || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !in_array($url['path'] ?? '',array('','/'),true)) {
						throw new UnexpectedValueException('Invalid configured socket origin');
					}
					$results->connection->domain = rtrim($publicOrigin,'/');
				}
			}
			$results->connection->channel = Encoder(json_encode(array('name'=>$channel->channel,'title'=>$channel->title,'send_limit'=>$channel->send_limit,'password'=>$channel->password,'max_user'=>$channel->max_user,'guest_name'=>$channel->guest_name,'allow_nickname_edit'=>$channel->allow_nickname_edit == 'TRUE','user_limit'=>$channel->user_limit,'box_limit'=>$channel->box_limit,'file_limit'=>$channel->file_limit,'font_limit'=>$channel->font_limit)));
		}
		
		$results->channel = new stdClass();
		$results->channel->use_boxes = $channel->box_limit > -1;
		$results->channel->use_users = $channel->user_limit > -1;
		$results->channel->use_history = $channel->use_history;
		$results->channel->token = Encoder(json_encode(array('channel'=>$channel->channel,'ip'=>GetClientIp())));
		
		return $results;
	}
	
	/**
	 * 특정경로에 있는 파일의 MIME 값을 읽어온다.
	 *
	 * @param string $path 파일절대경로
	 * @return string $mime 파일 MIME
	 */
	function getFileMime($path) {
		if (is_file($path) == true) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime = finfo_file($finfo,$path);
			unset($finfo);

			return $mime;
		} else {
			return false;
		}
	}
	
	/**
	 * 파일의 MIME 값을 이용하여 파일종류를 정리한다.
	 *
	 * @param string $mime 파일 MIME
	 * @return string $type 파일종류
	 */
	function getFileType($mime) {
		$type = 'file';
		if ($mime == 'image/svg+xml') {
			$type = 'svg';
		} elseif ($mime == 'image/x-icon') {
			$type = 'icon';
		} elseif (preg_match('/application\/vnd.openxmlformats\-officedocument/',$mime) == true || $mime == 'application/CDFV2-corrupt' || $mime == 'application/pdf') {
			$type = 'document';
		} elseif (preg_match('/text\//',$mime) == true) {
			$type = 'text';
		} elseif (preg_match('/^image\/(jpeg|png|gif)/',$mime) == true) {
			$type = 'image';
		} elseif (preg_match('/^video/',$mime) == true) {
			$type = 'video';
		} elseif (preg_match('/^audio/',$mime) == true) {
			$type = 'audio';
		} elseif (preg_match('/application\/(zip|gzip|x\-rar\-compressed|x\-gzip)/',$mime) == true) {
			$type = 'archive';
		}

		return $type;
	}
	
	/**
	 * 파일의 확장자만 가져온다.
	 *
	 * @param string $filename 파일명
	 * @param string $filepath 파일절대경로 (파일절대경로가 존재할 경우, 실제 파일의 확장자를 가져온다.)
	 * @return string $extension 파일 확장자
	 */
	function getFileExtension($filename,$filepath='') {
		return strtolower(pathinfo($filename,PATHINFO_EXTENSION));
	}
	
	/**
	 * 미니톡 구성요소의 마지막 수정일을 가져온다.
	 *
	 * @return int $unixtime
	 */
	function getLastModified() {
		return max(
			GetDirectoryLastModified(__MINITALK_PATH__.'/languages'),
			GetDirectoryLastModified(__MINITALK_PATH__.'/scripts'),
			GetDirectoryLastModified(__MINITALK_PATH__.'/styles'),
			GetDirectoryLastModified(__MINITALK_PATH__.'/plugins'),
			GetDirectoryLastModified(__MINITALK_PATH__.'/templets')
		);
	}
	
	/**
	 * 서버상태를 업데이트한다.
	 *
	 * @param string $domain 온라인여부를 확인할 도메인 (없을 경우 전체서버)
	 */
	function updateServer($domain=null,$is_force=false) {
		global $_CONFIGS;
		
		$servers = $this->db()->select($this->table->server);
		if ($is_force == false) $servers->where('latest_update',time() - 30,'<');
		if ($domain != null) $servers->where('domain',$domain);
		$servers = $servers->get();
		
		foreach ($servers as $server) {
			if ($server->type == 'SERVER') {
				$status = $this->getServerStatus($server->domain);
				if ($status !== null && $status->status == 'ONLINE') {
					$this->db()->update($this->table->server,array('status'=>'ONLINE','user'=>$status->user,'channel'=>$status->channel,'latest_update'=>time(),'connection'=>''))->where('domain',$server->domain)->execute();
				} else {
					$this->db()->update($this->table->server,array('status'=>'OFFLINE','user'=>0,'channel'=>0,'connection'=>'','latest_update'=>time()))->where('domain',$server->domain)->execute();
					if (isset($this->servers[$server->domain]) == true) unset($this->servers[$server->domain]);
				}
			}
			
			if ($server->type == 'SERVICE') {
				$service = $this->callServiceApi('GET','service/'.$server->domain,array(),array('MINITALK_CLIENT_SECRET'=>$server->client_secret));
				if ($service->success == true) {
					$service = $service->service;
					if ($service->server != null && $service->server->status == 'ONLINE') {
						$connection = new stdClass();
						$connection->domain = $service->server->domain;
						$connection->connection = $service->server->connection;
						$this->db()->update($this->table->server,array('status'=>'ONLINE','user'=>$service->server->user,'channel'=>$service->server->channel,'latest_update'=>time(),'connection'=>json_encode($connection)))->where('domain',$server->domain)->execute();
						continue;
					}
				}
				
				$this->db()->update($this->table->server,array('status'=>'OFFLINE','user'=>0,'channel'=>0,'connection'=>'','latest_update'=>time()))->where('domain',$server->domain)->execute();
				if (isset($this->servers[$server->domain]) == true) unset($this->servers[$server->domain]);
			}
		}
	}
	
	/**
	 * 아이피 차단대상자인지 확인한다.
	 *
	 * @param string $ip
	 * @return boolean $isBanned
	 */
	function isBanIp($ip=null) {
		$ip = $ip == null ? $_SERVER['REMOTE_ADDR'] : $ip;
		return $this->db()->select($this->table->banip)->where('ip',$ip)->has();
	}
	
	/**
	 * 에러메시지를 출력한다.
	 *
	 * @param string $code 에러코드
	 * @param object $value(옵션) 에러와 관련된 데이터
	 * @param string $message(옵션) 변환된 에러메시지
	 * @return null
	 */
	function printError($code=null,$value=null,$message=null) {
		$results = new stdClass();
		$results->success = false;
		$results->message = $this->getErrorText($code);
		if ($value != null) $results->message.= ' : '.$value;
		if ($message != null) $results->message.= ' : '.$message;
		
		exit(json_encode($results,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}
	
	/**
	 * 첨부파일 관련 에러메시지를 출력한다.
	 *
	 * @param string $mode 파일모드
	 */
	function printFileError($mode) {
		if ($mode == 'view') {
			header('Content-Type: image/png');
			readfile(__MINITALK_PATH__.'/images/noimage.png');
			exit;
		} else {
			$languages = GetDefaultLanguages();
			foreach ($languages as $language) {
				if (is_file(__MINITALK_PATH__.'/languages/'.$language.'.json') == true) break;
			}
			$this->setLanguage($language);
			
			$html = array(
				'<!DOCTYPE HTML>',
				'<html>',
				'<head>',
				'	<meta charset="utf-8">',
					'<meta name="viewport" content="user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, width=device-width">',
					'<title>ERROR</title>',
					'<link rel="stylesheet" href="'.__MINITALK_DIR__.'/styles/error.css" type="text/css">',
					'<link rel="stylesheet" href="'.__MINITALK_DIR__.'/styles/font.css.php?language=ko&font=moimz,OpenSans,NanumSquare&default=NanumSquare&v=1569068468" type="text/css">',
				'</head>',
				'<body>',
				'	<section class="errorbox">',
				'		<div>',
				'			<div>',
				'				<h1><i class="mi mi-attention-o"></i> ERROR</h1>',
				'				<p>'.$this->getErrorText('FILE_EXPIRED').'</p>',
				'			</div>',
				'		</div>',
				'	</section>',
				'</body>',
				'</html>',
			);
			
			exit(implode(PHP_EOL,$html));
		}
	}
	
	/**
	 * 서버에 브로드캐스트 메시지를 전송한다.
	 *
	 * @param string $message 전송할 메시지
	 * @param string $url 링크주소 (옵션)
	 * @return object $results 전송결과
	 */
	function sendBroadcast($message,$url='') {
		$receiver = 0;
		$servers = $this->db()->select($this->table->server)->where('type','SERVER')->get();
		
		$results = new stdClass();
		
		if (count($servers) > 0) {
			foreach ($servers as $server) {
				$api = $this->callServerApi('POST',$server->domain,'broadcast/'.md5($server->domain),array('message'=>$message,'url'=>$url));
				if ($api->success == true) {
					$receiver+= $api->receiver;
				}
			}
			
			$results->success = true;
			$results->receiver = $receiver;
		} else {
			$results->success = false;
			$results->message = 'SERVER HOSTING OR SERVER LICENSE REQUIRED.';
		}
	
		return $results;
	}
	
	/**
	 * 서버에 일반 메시지를 전송한다.
	 *
	 * @param string $channel 메시지를 전송할 채널
	 * @param string $type 메시지타입
	 * @param string $message 메시지
	 * @param user $user 유저객체 array('nickname'=>'','nickcon'=>'','photo'=>'','extras'=>null,'level'=>0)
	 * @param object $data 추가 데이터
	 * @return object $results 전송결과
	 */
	function sendMessage($channel,$type,$message,$user=array(),$data=null) {
		$channel = $this->getChannel($channel);
		$server = $this->getServer($channel->server);
		
		if ($server == null) return null;
		if ($server->type == 'SERVER') {
			return $this->callServerApi('POST',$server->domain,'message/'.md5($server->domain).'/'.$channel->channel,array('type'=>$type,'message'=>$message,'user'=>json_encode($user),'data'=>json_encode($data)));
		} else {
			$results = new stdClass();
			$results->success = false;
			$results->message = 'SERVER HOSTING OR SERVER LICENSE REQUIRED.';
			return $results;
		}
	}
	
	/**
	 * 서버에 사용자정의 프로토콜을 전송한다.
	 *
	 * @param string $channel 메시지를 전송할 채널
	 * @param string $protocol 사용자정의 프로토콜명
	 * @param string $data 데이터
	 * @param string $to 프로토콜을 받을 사람의 닉네임 (기본값 : *, * 인 경우 전체유저에게 전송한다.)
	 * @return object $results 전송결과
	 */
	function sendProtocol($channel,$protocol,$data=array(),$to='*') {
		$channel = $this->getChannel($channel);
		$server = $this->getServer($channel->server);
		
		if ($server == null) return null;
		if ($server->type == 'SERVER') {
			return $this->callServerApi('POST',$server->domain,'protocol/'.md5($server->domain).'/'.$channel->channel,array('protocol'=>$protocol,'data'=>json_encode($data),'to'=>$to));
		} else {
			$results = new stdClass();
			$results->success = false;
			$results->message = 'SERVER HOSTING OR SERVER LICENSE REQUIRED.';
			return $results;
		}
	}
	
	/**
	 * 캐시파일 생성시각을 가져온다.
	 *
	 * @param string $name
	 * @return string $path
	 */
	function getCacheTime($name) {
		return is_file($this->getAttachmentPath().'/cache/'.$name.'.cache') == true ? filemtime($this->getAttachmentPath().'/cache/'.$name.'.cache') : 0;
	}
	
	/**
	 * 캐시파일 내용을 가져온다.
	 *
	 * @param string $name
	 * @return string $content
	 */
	function getCacheContent($name) {
		return is_file($this->getAttachmentPath().'/cache/'.$name.'.cache') == true ? file_get_contents($this->getAttachmentPath().'/cache/'.$name.'.cache') : null;
	}
	
	/**
	 * 캐시파일을 저장한다.
	 *
	 * @param string $name
	 * @param string $content
	 */
	function saveCacheContent($name,$content) {
		if (is_dir($this->getAttachmentPath().'/cache') == false) {
			mkdir($this->getAttachmentPath().'/cache',0707,true);
			chmod($this->getAttachmentPath().'/cache',0707);
		}
		
		file_put_contents($this->getAttachmentPath().'/cache/'.$name.'.cache',$content);
		chmod($this->getAttachmentPath().'/cache/'.$name.'.cache',0707);
	}
	
	/**
	 * 업데이트를 확인한다.
	 *
	 * @return boolean/string $hasUpdate
	 */
	function checkUpdate() {
		global $_CONFIGS;
		
		$cacheFile = 'core.latest';
		if ($this->getCacheTime($cacheFile) >= time() - 60 * 60 * 24) {
			$latest = json_decode($this->getCacheContent($cacheFile));
			if ($latest->version != __MINITALK_VERSION__) {
				unlink($this->getAttachmentPath().'/cache/'.$cacheFile.'.cache');
				return $this->checkUpdate();
			}
		} else {
			$latest = new stdClass();
			$latest->is_latest = true;
			$latest->latest = __MINITALK_VERSION__;
			$latest->version = __MINITALK_VERSION__;
			
			if ($_CONFIGS->installed === true && is_file(__MINITALK_PATH__.'/package.json') == true) {
				$package = json_decode(file_get_contents(__MINITALK_PATH__.'/package.json'));
				
				$ch = curl_init();
				curl_setopt($ch,CURLOPT_URL,'https://moimz.minitalk.io/tools/'.$package->id.'/latest/'.$package->version);
				curl_setopt($ch,CURLOPT_POST,false);
				curl_setopt($ch,CURLOPT_TIMEOUT,10);
				curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
				curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
				
				$data = curl_exec($ch);
				$http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
				unset($ch);
				
				if ($http_code == 200) {
					$results = json_decode($data);
					if ($results->success == true) {
						$latest->is_latest = $results->is_latest;
						$latest->latest = $results->latest;
					}
				}
			}
			
			$this->saveCacheContent($cacheFile,json_encode($latest));
		}
		
		return $latest->is_latest == true ? true : str_replace(array('{version}','{latest}'),array(__MINITALK_VERSION__,$latest->latest),$this->getText('error/AVAILABLE_UPDATE'));
	}
	
	/**
	 * 데이터베이스 변경 필요 여부를 읽기 전용으로 확인한다. 실행은 관리자 승인 요청에서만 한다.
	 *
	 * @return boolean/string $result
	 */
	function checkInstall() {
		global $_CONFIGS;
		
		if ($_CONFIGS->installed === true && $this->getDatabaseUpdateStatus()['pending']) return '데이터베이스 업데이트가 필요합니다.';
		
		return true;
	}

	/** Read-only schema inspection. Opening a page never changes customer tables. */
	function getDatabaseUpdateStatus() {
		try { return (new DatabaseUpdater($this->db()))->status(); }
		catch (Throwable $error) {
			error_log('Minitalk schema inspection failed: '.get_class($error).' code '.$error->getCode());
			return array('pending'=>true,'blocked'=>array('DATABASE_STATE_UNVERIFIED'),'schema_hash'=>null);
		}
	}

	/** Before approval, reject long-IP writes instead of truncating customer records. */
	function canStoreClientIp($table,$ip) {
		if (!is_string($ip) || !filter_var($ip,FILTER_VALIDATE_IP) || !in_array($table,array('history','attachment','banip'),true)) return false;
		if (strlen($ip) <= 15) return true;
		if (!isset($this->ipColumnSizes[$table])) {
			try {
				$db = $this->db();
				$stmt = $db->mysqli()->prepare('SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=\'ip\'');
				if ($stmt === false) return false;
				try {
					$name = $db->getTable($this->getTable($table));
					if (!$stmt->bind_param('s',$name) || !$stmt->execute() || !$stmt->bind_result($capacity)) return false;
					$this->ipColumnSizes[$table] = $stmt->fetch() === true ? (int)$capacity : 0;
				} finally { $stmt->close(); }
			} catch (Throwable $error) { return false; }
		}
		return $this->ipColumnSizes[$table] >= strlen($ip);
	}

	/** Only the authenticated POST handler may initiate an existing-install update. */
	function updateDatabase($schemaHash) {
		if (!$this->isAdmin()) return array('success'=>false,'code'=>'FORBIDDEN');
		return (new DatabaseUpdater($this->db()))->apply($schemaHash);
	}
	
	/**
	 * 미니톡 클라이언트에서 처리해야하는 요청이 들어왔을 경우 처리하여 결과를 반환한다.
	 * 소스코드 관리를 편하게 하기 위해 각 요쳥별로 별도의 PHP 파일로 관리한다.
	 * 작업코드가 '@' 로 시작할 경우 미니톡 클라이언트 관리자를 위한 작업으로 관리자 권한이 필요하다.
	 *
	 * @param string $action 작업코드
	 * @return object $results 수행결과
	 * @see /process/index.php
	 */
	function doProcess($action) {
		$results = new stdClass();

		/**
		 * 미니톡 클라이언트 process 폴더에 $action 에 해당하는 파일이 있을 경우 불러온다.
		 * basename() 으로 경로 구분자(../ 등)를 제거하여 path traversal 을 방지한다.
		 */
		$action = basename($action);
		if (is_file($this->getPath().'/process/'.$action.'.php') == true) {
			INCLUDE $this->getPath().'/process/'.$action.'.php';
		}
		
		return $results;
	}
}
?>
