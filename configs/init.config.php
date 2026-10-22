<?php
// Check before loading application classes, configuration, sessions or database code.
if (PHP_VERSION_ID < 80200) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Minitalk requires PHP 8.2–8.5. 호스팅의 PHP 버전을 8.2~8.5로 변경한 뒤 다시 접속해 주세요.');
}

/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 클라이언트에서 사용되는 기본 상수를 정의하고, 환경설정을 불러온다.
 *
 * @file /configs/init.config.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */

/**
 * 미니톡 클라이언트 상수정의
 * 미니톡 클라이언트에서 사용되는 상수는 상수명 앞뒤를 언더바 2개로 감싼 형태로 정의힌다. (__[상수명]__)
 * 
 * __MINITALK__ : 미니톡 클라이언트에 의해 PHP 파일이 실행되었는지 여부를 확인하는 상수
 * __MINITALK_VERSION__ : 미니톡 클라이언트 버전을 정의하는 상수. 빌드날짜는 포함되지 않음
 * __MINITALK_DB_PREFIX__ : 미니톡 클라이언트에서 생성되는 모든 DB 테이블앞에 붙는 PREFIX를 정의하는 상수
 * __MINITALK_PATH__ : 클라이언트의 절대 설치 경로 를 미니톡 클라이언트가 설치되어 있는 서버상의 경로
 * __MINITALK_DIR__ : DOCUMENT_ROOT 을 포함하지 않는 미니톡 클라이언트가 설치되어 있는 웹브라우저상의 경로
 */
define('__MINITALK__',true);
define('__MINITALK_VERSION__','8.2.0');
define('__MINITALK_DB_PREFIX__','minitalk_');
if (defined('__MINITALK_PATH__') == false) define('__MINITALK_PATH__',str_replace('/configs','',str_replace(DIRECTORY_SEPARATOR,'/',__DIR__)));
if (defined('__MINITALK_DIR__') == false) define('__MINITALK_DIR__',str_replace(str_replace(DIRECTORY_SEPARATOR,'/',($_SERVER['DOCUMENT_ROOT'] ?? __MINITALK_PATH__)),'',__MINITALK_PATH__));

/**
 * 미니톡 클라이언트에서 공통적으로 사용하는 기본 함수셋을 불러온다.
 *
 * @see /classes/functions.php
 */
REQUIRE_ONCE __MINITALK_PATH__.'/classes/functions.php';

/**
 * 미니톡 클라이언트 환경설정값을 저장한다.
 * Minitalk.presets.php 파일에 선언된 환경설정값을 우선시하고, 해당 파일에 선언되어 있지 않은 환경설정은 미니톡 클라이언트 설치시 입력된 정보를 저장한다.
 *
 * @see Minitalk.preset.php.example
 */
$_CONFIGS = new stdClass();
$_CONFIGS->presets = new stdClass();
$_CONFIGS->presets->key = false;
$_CONFIGS->presets->db = false;

if (is_file(__MINITALK_PATH__.'/Minitalk.preset.php') == true) {
	REQUIRE_ONCE __MINITALK_PATH__.'/Minitalk.preset.php';
	
	if (isset($_CONFIGS->key) == true) $_CONFIGS->presets->key = true;
	if (isset($_CONFIGS->db) == true) $_CONFIGS->presets->db = true;
}

try {
	$_CONFIGS->key = isset($_CONFIGS->key) == true ? $_CONFIGS->key : FileReadLine(__MINITALK_PATH__.'/configs/key.config.php',1);
	$_CONFIGS->db = isset($_CONFIGS->db) == true ? $_CONFIGS->db : json_decode(Decoder(FileReadLine(__MINITALK_PATH__.'/configs/db.config.php',1)));
	$_CONFIGS->installed = true;
} catch (Exception $e) {
	$_CONFIGS->key = null;
	$_CONFIGS->db = null;
	$_CONFIGS->installed = false;
}

if ($_CONFIGS->db === null || $_CONFIGS->db === false) $_CONFIGS->installed = false;

/**
 * XSS 공격대비를 위한 GET 변수확인
 */
foreach ($_GET as $key=>$value) {
	if ($key != 'keyword') {
		$_GET[$key] = $_REQUEST[$key] = GetString($value,'replace');
	}
}

/**
 * session 기본 설정
 * 미니톡 클라이언트가 정상설치되어 별도의 세션폴더가 생성되어있다면, 해당 폴더에 세션을 저장한다.
 */
if (isset($_CONFIGS->session_path) == true && is_dir($_CONFIGS->session_path) == true && is_writable($_CONFIGS->session_path) == true) {
	session_save_path($_CONFIGS->session_path);
}
$session_name = session_name('MINITALK_SESSID');
if (isset($_CONFIGS->sessionDomain) == true) {
	session_set_cookie_params(0,'/',$_CONFIGS->sessionDomain);
}
session_start();

/**
 * class 파일을 자동으로 불러오기 위한 __autoload 설정을 정의힌다.
 * 우선적으로 /classes 폴더를 우선탐색하며, class 이름이 Plugin으로 시작할 경우 각 플러그인의 최상위폴더의 Plugin[플러그인명].class.php 파일을 탐색한다.
 * 모든 class 파일은 [클래스명].class.php 파일명의 규칙을 지켜야한다.
 */
function MoimzToolsAutoLoader($class) {
	if (is_file(__MINITALK_PATH__.'/classes/'.$class.'.class.php') == true) REQUIRE_ONCE __MINITALK_PATH__.'/classes/'.$class.'.class.php';
}
spl_autoload_register('MoimzToolsAutoLoader');

if (defined('__DEBUG_MODE__') == true) {
	error_reporting(E_ALL);
	ini_set('display_errors',true);
} else {
	error_reporting(E_ALL);
	ini_set('display_errors',false);
}

/**
 * 사이트 헤더 설정
 * 기본적인 HTTP보안설정 및 언어셋을 선언한다.
 */
header('X-UA-Compatible: IE=Edge');
header('X-XSS-Protection: 1');
header('Content-type: text/html; charset=utf-8');
header('Expires: 0');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: pre-check=0, post-check=0, max-age=0');
header('Pragma: no-cache');
?>
