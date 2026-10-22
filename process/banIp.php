<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 아이피를 차단한다.
 * 
 * @file /process/banIp.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
if (defined('__MINITALK__') == false) exit;

$encoded = Request('bancode');
if (!is_string($encoded) || $encoded === '') {
	$results->success = false; $results->code = 'INVALID_BANCODE'; return;
}
$bancode = json_decode(Decoder($encoded));
if (!is_object($bancode) || !isset($bancode->ip,$bancode->to,$bancode->from)
	|| !is_string($bancode->ip) || !filter_var($bancode->ip,FILTER_VALIDATE_IP)
	|| !is_string($bancode->to) || !is_string($bancode->from)) {
	$results->success = false; $results->code = 'INVALID_BANCODE'; return;
}
if ($bancode !== null) {
	if (!$this->canStoreClientIp('banip',$bancode->ip ?? null)) {
		$results->success=false;
		$results->code='DATABASE_UPDATE_REQUIRED';
		return;
	}
	$this->db()->replace($this->table->banip,array('ip'=>$bancode->ip,'nickname'=>$bancode->to,'memo'=>'From '.$bancode->from,'reg_date'=>time()))->execute();
}

$results->success = true;
?>
