<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 자주 사용되는 공통함수를 정의한다.
 *
 * @file /classes/functions.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */

/**
 * 요청된 변수데이터를 가져온다.
 *
 * @param string $var 변수명
 * @param string $type 변수형태 (request : POST or GET 으로 요청된 변수 / session : 세션변수 / cookie : 쿠키변수)
 * @return any $object
 */
function Request($var,$type='request') {
	switch ($type) {
		case 'request' :
			$value = isset($_REQUEST[$var]) == true ? $_REQUEST[$var] : null;
		break;

		case 'session' :
			$value = isset($_SESSION[$var]) == true ? $_SESSION[$var] : null;
		break;

		case 'cookie' :
			$value = isset($_COOKIE[$var]) == true ? $_COOKIE[$var] : null;
		break;
	}

	if ($value === null) return null;
	if (is_array($value) == false && is_object($value) == false) return trim($value);
	return $value;
}

/**
 * 요청된 변수 중 필수데이터를 가져온다.
 *
 * @param string $var 변수명
 * @return any $object
 */
function Param($var) {
	global $MINITALK;
	
	if (isset($_REQUEST[$var]) == false) {
		if (isset($MINITALK) == true) return $MINITALK->printError('MISSING_PARAMETER',$var);
		return null;
	}
	$value = $_REQUEST[$var];
	if (is_array($value) == false && is_object($value) == false) return trim($value);
	return $value;
}

/**
 * 복호화가 가능한 방식(AES-256-CBC)으로 문자열을 암호화한다.
 *
 * @param string $value 암호화할 문자열
 * @param string $key 암호화키 (없을경우 설치시 사용한 기본 암호화키)
 * @param string $mode 암호화된 문자열 인코딩방식 (base64 또는 hex)
 * @return string $ciphertext
 */
function Encoder($value,$key=null,$mode='base64') {
	global $_CONFIGS;
	
	$key = $key !== null ? md5($key) : md5($_CONFIGS->key);
	$padSize = 16 - (strlen($value) % 16);
	$value = $value.str_repeat(chr($padSize),$padSize);
	
	$output = openssl_encrypt($value,'AES-256-CBC',$key,OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,str_repeat(chr(0),16));
	
	return $mode == 'base64' ? base64_encode($output) : bin2hex($output);
}

/**
 * 복호화가 가능한 방식(AES-256-CBC)으로 암호화된 문자열을 복호화한다.
 *
 * @param string $value 암호화된 문자열
 * @param string $key 암호화키 (없을경우 설치시 사용한 기본 암호화키)
 * @param string $mode 암호화된 문자열 인코딩방식 (base64 또는 hex)
 * @return string $plaintext
 */
function Decoder($value,$key=null,$mode='base64') {
	global $_CONFIGS;
	
	$key = $key !== null ? md5($key) : md5($_CONFIGS->key);
	$value = $mode == 'base64' ? base64_decode(str_replace(' ','+',$value)) : hex2bin($value);
	
	$output = openssl_decrypt($value,'AES-256-CBC',$key,OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,str_repeat(chr(0),16));
	if ($output === false) return false;
	
	$valueLen = strlen($output);
	if ($valueLen % 16 > 0) return false;

	$padSize = ord($output[$valueLen - 1]);
	if (($padSize < 1) || ($padSize > 16)) return false;

	for ($i=0;$i<$padSize;$i++) {
		if (ord($output[$valueLen - $i - 1]) != $padSize) return false;
	}
	
	return substr($output,0,$valueLen-$padSize);
}

/**
 * 파일에서 특정라인을 읽어온다.
 *
 * @param string $path 읽어올 파일 경로
 * @param string $line 읽어올 라인
 * @return string $text
 */
function FileReadLine($path,$line) {
	if (is_file($path) == true) {
		$file = @file($path);
		if (isset($file[$line]) == false) throw new Exception('Line Overflow : '.$path.'(Line '.$line.')');
		return trim($file[$line]);
	} else {
		throw new Exception('Not Found : '.$path);
		return '';
	}
}

/**
 * 이메일이 형식에 맞는지 확인한다.
 *
 * @param string $email 이메일
 * @return boolean $isValid
 */
function CheckEmail($email) {
	return preg_match('/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i',$email);
}

/**
 * 실명에 포함될 수 없는 문자열이 있는지 확인한다.
 *
 * @param string $name 실명
 * @return boolean $isValid
 */
function CheckName($name) {
	if (preg_match('/[~!@#\$%\^&\*\(\)\-_\+\=\[\]\<\>\/\?\'":;\{\}\x{25a0}-\x{25ff}\x{2600}-\x{26ff}]+/u',$name) == true) return false;
	if (mb_strlen($name,'utf-8') < 2) return false;
	
	return true;
}

/**
 * 닉네임이 형식에 맞는지 확인한다.
 *
 * @param string $nickname 닉네임
 * @return boolean $isValid
 */
function CheckNickname($nickname) {
	if (preg_match('/[~!@#\$%\^&\*\(\)\-_\+\=\[\]\<\>\/\?\'":;\{\}\x{25a0}-\x{25ff}\x{2600}-\x{26ff}[:space:]]+/u',$nickname) == true) return false;
	if (mb_strlen($nickname,'utf-8') < 2 || mb_strlen($nickname,'utf-8') > 10) return false;
	
	return true;
}

/**
 * 전화번호 형식이 맞는지 확인한다.
 *
 * @param string $phone
 * @return boolean $isValid
 */
function CheckPhoneNumber($phone) {
	$phone = str_replace('-','',$phone);
	
	if (preg_match('/^02/',$phone) == true) return preg_match('/^02[0-9]{7,8}$/',$phone);
	else return preg_match('/^0[0-9]{9,11}$/',$phone);
}

/**
 * 이메일형식이 이메일수집봇에 의해 자동으로 수집되지 않도록 변환한다.
 *
 * @param string $email 이메일주소
 * @param boolean $isLink 이메일링크를 포함할지 여부 (기본값 : true)
 * @return boolean $email 변환된 이메일주소
 */
function GetAntiSpamEmail($email,$isLink=true) {
	$email = str_replace('@','<i class="fa fa-at"></i>',$email);
	return $isLink == true ? '<span class="iModuleEmail">'.$email.'</span>' : $email;
}

/**
 * UNIXTIMESTAMP 를 주어진 포맷에 따라 변환한다.
 *
 * @param string $format 변환할 포맷 (@see http://php.net/manual/en/function.date.php)
 * @param int $time UNIXTIMESTAMP (없을 경우 현재시각)
 * @param boolean $is_moment momentjs 용 태그를 생성할 지 여부 (@see http://momentjs.com)
 * @return string $time 변환된 시각
 */
function GetTime($format,$time=null,$is_moment=true) {
	$time = $time === null ? time() : $time;
	
	/**
	 * PHP date 함수 포맷텍스트를 momentjs 포맷텍스트로 치환하기 위한 배열정의
	 */
	$replacements = array(
		'd' => 'DD',
		'D' => 'ddd',
		'j' => 'D',
		'l' => 'dddd',
		'N' => 'E',
		'S' => 'o',
		'w' => 'e',
		'z' => 'DDD',
		'W' => 'W',
		'F' => 'MMMM',
		'm' => 'MM',
		'M' => 'MMM',
		'n' => 'M',
		't' => '', // no equivalent
		'L' => '', // no equivalent
		'o' => 'YYYY',
		'Y' => 'YYYY',
		'y' => 'YY',
		'a' => 'a',
		'A' => 'A',
		'B' => '', // no equivalent
		'g' => 'h',
		'G' => 'H',
		'h' => 'hh',
		'H' => 'HH',
		'i' => 'mm',
		's' => 'ss',
		'u' => 'SSS',
		'e' => 'zz', // deprecated since version 1.6.0 of moment.js
		'I' => '', // no equivalent
		'O' => '', // no equivalent
		'P' => '', // no equivalent
		'T' => '', // no equivalent
		'Z' => '', // no equivalent
		'c' => '', // no equivalent
		'r' => '', // no equivalent
		'U' => 'X'
	);
	$momentFormat = strtr($format,$replacements);
	
	if ($is_moment == true) return '<time datetime="'.date('c',$time).'" data-time="'.$time.'" data-format="'.$format.'" data-moment="'.$momentFormat.'">'.date($format,$time).'</time>';
	else return date($format,$time);
}

/**
 * 폰번호를 변환한다.
 * @todo 한국 외 다른 나라 형식
 *
 * @param string $phone 폰번호
 * @param int $code 국가코드 (한국 82)
 * @param boolean $is_included_code 변환된 전화번호에 국가코드(예 : +82)를 포함할지 여부 (기본값 : false)
 * @return string $phone 변환된 폰번호
 */
function GetPhoneNumber($phone,$code='82',$is_included_code=false) {
	$phone = str_replace('-','',$phone);
	if (strlen($phone) < 9) return '';

	if (substr($phone,0,2) == '02') {
		if (strlen($phone) == 10) {
			$value = substr($phone,0,2).'-'.substr($phone,2,4).'-'.substr($phone,6,4);
		} else {
			$value = substr($phone,0,2).'-'.substr($phone,2,3).'-'.substr($phone,5,4);
		}
	} else {
		if (strlen($phone) == 11) {
			$value = substr($phone,0,3).'-'.substr($phone,3,4).'-'.substr($phone,7,4);
		} else {
			$value = substr($phone,0,3).'-'.substr($phone,3,3).'-'.substr($phone,6,4);
		}
	}

	return $value;
}

/**
 * 사용자 브라우져의 언어코드를 가져온다.
 *
 * @return string[] $languages
 */
function GetDefaultLanguages() {
	$languages = explode(',',($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en'));
	foreach ($languages as &$language) {
		$language = substr($language,0,2);
	}
	
	return array_unique($languages);
}

/**
 * 문자열을 종류에 따라 변환한다.
 *
 * @param string $str 변환할 문자열
 * @param int $code 변환종류
 * @return string $str 변환된 문자열
 */
function GetString($str,$code) {
	if (is_array($str)) return array_map(function($value) use ($code) { return GetString($value,$code); },$str);
	if ($str === null) return '';
	if (!is_scalar($str)) return '';
	$str = (string)$str;
	switch ($code) {
		/**
		 * input 태그에 들어갈 수 있도록 <, >, " 문자열을 HTML 엔티티 문자열로 변환하고 ' 에 \ 를 추가한다.
		 */
		case 'input' :
			$str = str_replace('<','&lt;',$str);
			$str = str_replace('>','&gt;',$str);
			$str = str_replace('"','&quot;',$str);
			$str = str_replace("'",'\'',$str);
		break;
		
		/**
		 * HTML 태그를 HTML 엔티티 문자열로 변환한다.
		 */
		case 'replace' :
			$str = str_replace('<','&lt;',$str);
			$str = str_replace('>','&gt;',$str);
			$str = str_replace('"','&quot;',$str);
		break;
		
		/**
		 * XML 태그에 들어갈 수 있도록 &, <, >, ", ' 문자열을 HTML 엔티티 문자열로 변환한다.
		 */
		case 'xml' :
			$str = str_replace('&','&amp;',$str);
			$str = str_replace('<','&lt;',$str);
			$str = str_replace('>','&gt;',$str);
			$str = str_replace('"','&quot;',$str);
			$str = str_replace("'",'&apos;',$str);
		break;
		
		/**
		 * 가장 일반적인 HTML 태그를 제외한 나머지 태그를 제거한다.
		 */
		case 'default' :
			$allow = '<p>,<br>,<b>,<span>,<a>,<img>,<embed>,<i>,<u>,<strike>,<font>,<center>,<ol>,<li>,<ul>,<strong>,<em>,<div>,<table>,<tr>,<td>';
			$str = strip_tags($str, $allow);
		break;

		/**
		 * \ 및 태그, HTML 엔티티를 제거한다.
		 */
		case 'delete' :
			$str = stripslashes($str);
			$str = strip_tags($str);
			$str = str_replace('&nbsp;','',$str);
			$str = str_replace('"','&quot;',$str);
		break;

		/**
		 * URL 주소를 인코딩한다.
		 */
		case 'encode' :
			$str = urlencode($str);
		break;
		
		/**
		 * 정규식에 들어갈 수 있도록 정규식에 사용되는 문자열을 치환한다.
		 */
		case 'reg' :
			$str = str_replace('\\','\\\\',$str);
			$str = str_replace('[','\[',$str);
			$str = str_replace(']','\]',$str);
			$str = str_replace('(','\(',$str);
			$str = str_replace(')','\)',$str);
			$str = str_replace('?','\?',$str);
			$str = str_replace('.','\.',$str);
			$str = str_replace('*','\*',$str);
			$str = str_replace('-','\-',$str);
			$str = str_replace('+','\+',$str);
			$str = str_replace('^','\^',$str);
			$str = str_replace('$','\$',$str);
			$str = str_replace('/','\/',$str);
		break;
		
		/**
		 * 데이터베이스 인덱스에 사용할 수 있게 HTML태그 및 HTML엔티티, 그리고 불필요한 공백문자를 제거한다.
		 */
		case 'index' :
			$str = preg_replace('/<(P|p)>/',' <p>',$str);
			$str = strip_tags($str);
			$str = preg_replace('/&[a-z]+;/',' ',$str);
			$str = preg_replace('/\r\n/',' ',$str);
			$str = str_replace("\n",' ',$str);
			$str = str_replace("\t",' ',$str);
			$str = preg_replace('/[[:space:]]+/',' ',$str);
	}
	
	return trim($str);
}

/**
 * 정해진 길이에 따라 주어진 문자열을 자른다.
 *
 * @param string $str 자를 문자열
 * @param int $limit 문자열 길이
 * @param boolean $is_html 자를 문자열에 HTML 태그가 포함되어 있는지 여부
 * @return string $str
 */
function GetCutString($str,$limit,$is_html=false) {
	$str = strip_tags($str,'<b><span><strong><i><u><font>');
	$length = mb_strlen($str,'UTF-8');

	$tags = array();
	$htmlLength = 0;
	$countLength = 0;

	$tag = false;
	if ($is_html == true) {
		for ($i=0; $i<=$length && $countLength<$limit;$i++) {
			$LastStr = mb_substr($str,$i,1,'UTF-8');
			if ($LastStr == '<' && preg_match('/^(b|span|strong|i|u|font)+/i',mb_substr($str,$i+1,$length-$i,'UTF-8'),$matchs) == true) {
				$tag = true;
				$tempLength = mb_strlen($matchs[1]);
				$htmlLength = $htmlLength+$tempLength+1;
				$i = $i+$tempLength;
				$tags[] = $matchs[1];

				continue;
			}

			if ($LastStr == '<' && preg_match('/^\/(b|span|strong|i|u|font)+/i',mb_substr($str,$i+1,$length-$i,'UTF-8'),$matchs) == true) {
				$tag = true;
				$tempLength = mb_strlen($matchs[1]);
				$htmlLength = $htmlLength+$tempLength+2;
				$i = $i+$tempLength+1;

				if (strlen(array_search($matchs[1],$tags)) > 0) {
					$tags[array_search($matchs[1],$tags)] = '-1';
				}

				continue;
			}

			if ($tag == true && $LastStr == '>') {
				$tag = false;
				$htmlLength++;
				continue;
			}

			if ($tag == true) {
				$htmlLength++;
				continue;
			}

			if ($tag == false) {
				$countLength++;
			}

			if ($countLength > $limit) {
				break;
			}
		}

		$limit = $limit+$htmlLength;
	}

	$isCut = false;
	if ($length >= $limit) {
		$isCut = true;
		$str = mb_substr($str,0,$limit,"UTF-8");
	} else {
		$str = $str;
	}

	if (count($tags) > 0) {
		$tags = array_reverse($tags);
		for ($i=0, $loop=count($tags);$i<$loop;$i++) {
			if ($tags[$i] != '-1') $str.= '</'.$tags[$i].'>';
		}
	}

	if ($isCut == true) $str.= '...';

	return $str;
}

/**
 * 영문 및 숫자로 이루어진 랜덤한 문자열을 가져온다.
 *
 * @param int $length 랜덤문자열 길이
 * @return string $randomText 랜덤문자열
 */
function GetRandomString($length=10) {
	return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"),0,$length);
}

/**
 * 특정년도의 특정주차의 시작날짜를 가져온다.
 *
 * @param int $year 년도
 * @param int $week 주차
 * @return date $date
 */
function GetDateOfWeek($year,$week) {
	$start = strtotime($year.'-01-01');
	
	$time = $start + ($week - 1) * 7 * 60 * 60 * 24 - date('w',$start) * 60 * 60 * 24;
	return date('Y-m-d',$time);
}

/**
 * 클라이언트 아이피를 가져온다.
 * Proxy 서버를 통해 접속하였을 경우에도 가급적 실제 아이피를 가져온다.
 *
 * @return string $ip
 */
REQUIRE_ONCE __DIR__.'/clientip.php';
function GetClientIp() {
	global $_CONFIGS;
	return MinitalkResolveClientIp($_CONFIGS->trustedProxies ?? array());
}

/**
 * 아이피주소에서 3번째 자리를 치환한다.
 *
 * @param string $ip 아이피주소 (ex : 127.0.0.1)
 * @return string $hiddenIp 변환된 아이피주소 (ex : 127.0.***.1)
 */
function GetHiddenIp($ip) {
	$temp = explode('.',$ip);
	$temp[2] = '***';
	return implode('.',$temp);
}

/**
 * 특정 URL 주소의 파일을 서버에 저장한다.
 *
 * @param string $url 저장할 파일주소 (http://domain.com/file.exe)
 * @param string $filename 저장할 파일명
 * @param string $filetype 파일 MIME (옵션)
 * @return boolean $success
 */
function SaveFileFromUrl($url,$filename,$filetype=null) {
	$parseURL = parse_url($url);

	$scheme = isset($parseURL['scheme']) == true ? $parseURL['scheme'] : '';
	$host = isset($parseURL['host']) == true ? $parseURL['host'] : '';
	$port = isset($parseURL['port']) == true ? $parseURL['port'] : ($parseURL['scheme'] == 'https' ? '443' : '80');
	$path = isset($parseURL['path']) == true ? $parseURL['path'] : '';
	$query = isset($parseURL["query"]) == true ? $parseURL["query"] : '';

	$ch = curl_init();
	if ($scheme == 'https') curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,1);
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_POST,0);
	curl_setopt($ch,CURLOPT_REFERER,$url);
	curl_setopt($ch,CURLOPT_TIMEOUT,30);
	curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
	
	if (isset($_SERVER['HTTP_USER_AGENT']) == true) {
		curl_setopt($ch,CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
	}
	
	curl_setopt($ch,CURLOPT_AUTOREFERER,1);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
	$buffer = curl_exec($ch);
	$info = curl_getinfo($ch);
	$error = curl_error($ch);
	unset($ch);
	
	if ($info['http_code'] != 200 || ($filetype != null && preg_match('/'.$filetype.'/',$info['content_type']) == false)) {
		return false;
	}
	
	$fp = fopen($filename,'w');
	fwrite($fp,$buffer);
	fclose($fp);

	if (file_exists($filename) == false || filesize($filename) == 0) {
		unlink($filename);
		$filepath = '';
	}

	return true;
}

/**
 * 자동링크를 생성한다.
 *
 * @param string $text 본문내용
 * @param string $linkText 링크태그가 추가된 본문내용 
 */
function AutoLink($text) {
	$pattern = '/(http|https|ftp|mms):\/\/[0-9a-z-]+(\.[_0-9a-z-]+)+(:[0-9]{2,4})?\/?';
	$pattern.= '([\.~_0-9a-z-]+\/?)*';
	$pattern.= '(\S+\.[_0-9a-z]+)?';
	$pattern.= '(\?[_0-9a-z#%&=\-\+]+)*/i';
	$replacement = '<a href="\\0" target="_blank">\\0</a>';

	return preg_replace($pattern,$replacement,$text,-1);
}

/**
 * 디렉토리 구성요소를 가져온다.
 *
 * @param string $path 경로
 * @param string $type 가져올 종류 (all : 전체, directory : 디렉토리, file : 파일)
 * @param boolean $recursive 내부폴더 탐색여부
 * @return string[] $items
 */
function GetDirectoryItems($path,$type='all',$recursive=false) {
	$path = realpath($path);
	if (is_dir($path) == false) return array();
	
	$items = array();
	foreach (scandir($path) as $item) {
		if (in_array($item,array('.','..')) == true) continue;
		
		$item = $path.'/'.$item;
		if (is_dir($item) == true) {
			if (in_array($type,array('all','directory')) == true) {
				$items[] = $item;
			}
			
			if ($recursive == true) {
				$items = array_merge($items,GetDirectoryItems($item,$type,$recursive));
			}
		} else {
			if (in_array($type,array('all','file')) == true) {
				$items[] = $item;
			}
		}
	}
	
	return $items;
}

/**
 * 디렉토리내 구성요소의 마지막 수정시간을 가져온다.
 *
 * @param string $path 경로
 * @return int $unixtime 수정시간
 */
function GetDirectoryLastModified($path) {
	$files = GetDirectoryItems($path,'file',true);
	$lastModified = 0;
	foreach ($files as $file) {
		$modified = filemtime($file);
		$lastModified = $lastModified < $modified ? $modified : $lastModified;
	}
	
	return $lastModified;
}

/**
 * 폴더의 용량을 구한다.
 *
 * @param string $path 폴더
 * @return int $size 폴더용량
 */
function GetDirectorySize($path) {
	$files = GetDirectoryItems($path,'file',true);
	$size = 0;
	foreach ($files as $file) {
		$size+= filesize($file);
	}
	return $size;
}

/**
 * byte 단위의 파일용량을 적절한 단위로 변환한다.
 *
 * @param string $size byte 단위 용량
 * @param boolean $isKIB KiB 단위 사용여부
 * @return int $size 폴더용량
 */
function GetFileSize($size,$isKIB=false) {
	$depthSize = $isKIB === true ? 1024 : 1000;
	if ($size / $depthSize / $depthSize / $depthSize > 1) return sprintf('%0.2f',$size / $depthSize / $depthSize / $depthSize).($isKIB === true ? 'GiB' : 'GB');
	else if ($size / $depthSize / $depthSize > 1) return sprintf('%0.2f',$size / $depthSize / $depthSize).($isKIB === true ? 'MiB' : 'MB');
	else if ($size / $depthSize > 1) return sprintf('%0.2f',$size / $depthSize).($isKIB === true ? 'KiB' : 'KB');
	return $size.'B';
}

/**
 * 서버 요구사항을 확인한다.
 *
 * @param string $dependency 확인할 요구사항 종류 (php, mysql, curl, zip, mbstring, gd)
 * @param string $version 최소 요구버전
 * @param object $check 확인결과 (boolean $check->installed : 설치여부,float $check->installedVersion : 설치버전)
 */
function CheckDependency($dependency,$version) {
	$check = new stdClass();
	
	if ($dependency == 'php') {
		$installed = explode('-',phpversion());
		$installed = array_shift($installed);
		$check->installed = version_compare($version,$installed,'<=');
		$check->installedVersion = $installed;
	} elseif ($dependency == 'mysql') {
		$installed = function_exists('mysqli_get_client_version') == true ? mysqli_get_client_version() : '0';
		$check->installed = version_compare($version,$installed,'<=');
		$check->installedVersion = $installed;
	} elseif ($dependency == 'curl') {
		$check->installed = function_exists('curl_init');
		$check->installedVersion = null;
	} elseif ($dependency == 'zip') {
		$check->installed = class_exists('ZipArchive');
		$check->installedVersion = null;
	} elseif ($dependency == 'mbstring') {
		$check->installed = function_exists('mb_strlen');
		$check->installedVersion = null;
	} elseif ($dependency == 'gd') {
		$check->installed = function_exists('ImageCreateFromJPEG');
		$check->installedVersion = null;
	} elseif ($dependency == 'encrypt') {
		$check->installed = function_exists('openssl_encrypt');
		$check->installedVersion = null;
	} elseif ($dependency == 'rewrite') {
		$check->installed = false;
		
		$request = IsHttps() == true ? 'https://' : 'http://';
		$request.= $_SERVER['HTTP_HOST'];
		$request.= explode('/install',$_SERVER['REQUEST_URI'])[0];
		$request.= $version;
		
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_URL,$request);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
		$data = curl_exec($ch);
		$http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		unset($ch);
		
		if ($http_code == 200) {
			$result = json_decode($data);
			if ($result != null && isset($result->success) == true && $result->success == true) {
				$check->installed = true;
			}
		}
	} else {
		$check->installed = false;
		$check->installedVersion = null;
	}
	
	return $check;
}

/**
 * 디렉토리의 퍼미션을 확인한다.
 *
 * @param string $dir 확인할 디렉토리 경로
 * @param string $permission 최소 요구 퍼미션 (예 : 707)
 * @param boolean $check 최소 요구 퍼미션을 만족하는지 여부
 */
function CheckDirectoryPermission($dir,$permission) {
	if (is_dir($dir) == true) {
		$check = substr(sprintf('%o',fileperms($dir)),-4);
		for ($i=1;$i<4;$i++) {
			if (intval($check[$i]) < intval($permission[$i])) return false;
		}
		
		return true;
	}
	
	return false;
}

/**
 * 데이터베이스 테이블을 생성한다.
 *
 * @param DB $dbConnect 데이터베이스 함수
 * @param object $schema 테이블 구조 (package.json 에 정의)
 * @return boolean $success
 */
function CreateDatabase($dbConnect,$schema) {
    // Initial installation is explicit. Existing admin page loads never call this helper.
    try {
        $package = json_decode(file_get_contents(__MINITALK_PATH__.'/package.json'));
        $updater = new DatabaseUpdater($dbConnect,$schema,$package->database_schema_version ?? DatabaseUpdater::VERSION);
        $status = $updater->status();
        $result = $updater->apply($status['schema_hash']);
        return $result['success'] ? true : $result['code'];
    } catch (Throwable $error) {
        error_log('Minitalk initial schema validation failed: '.get_class($error).' code '.$error->getCode());
        return 'DATABASE_INITIALIZATION_FAILED';
    }
}
/**
 * 디렉토리를 생성한다.
 *
 * @param string $path 생성할 경로
 * @return boolean $success
 */
function CreateDirectory($path) {
	$success = true;
	$serverPath = '';
	$dir = explode('/',str_replace($_SERVER['DOCUMENT_ROOT'].'/','',$path));
	for ($i=0, $loop=count($dir);$i<$loop;$i++) {
		$serverPath.= '/'.$dir[$i];

		if (is_dir($_SERVER['DOCUMENT_ROOT'].$serverPath) == false) {
			@mkdir($_SERVER['DOCUMENT_ROOT'].$serverPath) or $success = false;
			@chmod($_SERVER['DOCUMENT_ROOT'].$serverPath,0707);
		}
	}

	return $success;
}

/**
 * 버퍼크기와 관계없이 강제로 출력된 데이터를 FLUSH한다.
 */
function ForceFlush() {
	ob_start();
	ob_end_clean();
	flush();
	set_error_handler(function() {});
	ob_end_flush();
	restore_error_handler();
}

/**
 * HEX 컬러값을 RGB 컬러값으로 변환한다.
 *
 * @param string $hex
 * @param int $opacity (옵션)
 * @return string $rgb
 */
function GetHexToRgb($hex,$opacity=null) {
	$hex = str_replace('#','',$hex);

	if (strlen($hex) == 3) {
		$r = hexdec(substr($hex,0,1).substr($hex,0,1));
		$g = hexdec(substr($hex,1,1).substr($hex,1,1));
		$b = hexdec(substr($hex,2,1).substr($hex,2,1));
	} else {
		$r = hexdec(substr($hex,0,2));
		$g = hexdec(substr($hex,2,2));
		$b = hexdec(substr($hex,4,2));
	}
	$rgb = array($r,$g,$b);
	
	if ($opacity !== null) {
		return $opacity === 1 || $opacity === true ? 'rgb('.$r.','.$g.','.$b.')' : 'rgba('.$r.','.$g.','.$b.','.$opacity.')';
	} else {
		return $rgb;
	}
}

/**
 * HTTPS 접속여부를 확인한다.
 *
 * @return boolean $isHttps
 */
function IsHttps() {
	if (isset($_SERVER['HTTPS']) == true && $_SERVER['HTTPS'] == 'on') return true;
	if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) == true && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') return true;
	if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) == true && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') return true;
	
	return false;
}

/**
 * 아파치 웹서버가 아닌경우 아파치 웹서버 전용함수인 getallheaders 함수를 정의한다.
 *
 * @see http://php.net/getallheaders
 * @return $headers
 */
if (!function_exists('getallheaders')) {
	function getallheaders() { 
		$headers = array(); 
		foreach ($_SERVER as $name=>$value) {
			if (substr($name,0,5) == 'HTTP_') {
				$headers[str_replace(' ', '-',ucwords(strtolower(str_replace('_',' ',substr($name,5)))))] = $value;
			}
		}
		return $headers;
	}
}
?>
