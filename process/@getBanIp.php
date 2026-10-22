<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 아이피 차단정보를 가져온다.
 * 
 * @file /process/@getBanIp.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
if (defined('__MINITALK__') == false) exit;

$ip = Param('ip');
$data = is_string($ip) ? $this->db()->select($this->table->banip)->where('ip',$ip)->getOne() : null;
if ($data === null) {
	$results->success = false;
	$results->message = $this->getErrorText('NOT_FOUND');
	return;
}

$data->oIp = $data->ip;

$results->success = true;
$results->data = $data;
?>