<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 클라이언트에서 작업을 처리하기 위해 모든 작업요청을 받는다.
 * 
 * @file /process/index.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
REQUIRE_ONCE str_replace('/process','',str_replace(DIRECTORY_SEPARATOR,'/',__DIR__)).'/configs/init.config.php';

set_time_limit(0);
@ini_set('memory_limit',-1);
@ini_set('zlib.output_compression','Off');
@ini_set('output_buffering','Off');
@ini_set('output_handler','');
if (function_exists('apache_setenv') == true) {
	@apache_setenv('no-gzip',1);
}

/**
 * 미니톡 클라이언트 클래스를 선언한다.
 */
$MINITALK = new Minitalk();

/**
 * 요청작업을 수행할 요청작업코드
 */
$action = Request('action');
if (!is_string($action) || !preg_match('/^@?[A-Za-z][A-Za-z0-9]*$/D',$action)) {
	header('Content-Type: application/json; charset=utf-8');
	exit(json_encode(array('success'=>false,'message'=>'INVALID_ACTION')));
}

/**
 * 세션이 필요없는 액션인 경우 세션잠금을 해제한다.
 */
if (preg_match('/^@?(get|save|delete)/',$action) == true) {
	session_write_close();
}

/**
 * 작업코드가 @ 로 시작할 경우 관리자권한으로 동작하는 작업으로 관리자권한을 확인한다.
 */
if (preg_match('/^@/',$action) == true && $MINITALK->isAdmin() == false) {
	header('Content-type:text/json; charset=utf-8',true);
	header('Cache-Control:no-store, no-cache, must-revalidate, max-age=0');
	header('Cache-Control:post-check=0, pre-check=0', false);
	header('Pragma:no-cache');
	exit(json_encode(array('success'=>false,'message'=>$MINITALK->getErrorText('FORBIDDEN')),JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
} else {
	header('Access-Control-Allow-Origin:*');
	header('Access-Control-Allow-Credentials:true');
	header('Access-Control-Allow-Methods:*');
	header('Access-Control-Allow-Headers:*');

	$results = $MINITALK->doProcess($action);
	if ($results !== null) {
		header('Content-type:text/json; charset=utf-8',true);
		header('Cache-Control:no-store, no-cache, must-revalidate, max-age=0');
		header('Cache-Control:post-check=0, pre-check=0', false);
		header('Pragma:no-cache');
		exit(json_encode($results,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
	}
}
?>