<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 서버의 핑퐁메시지를 처리한다.
 * 
 * @file /api/ping.post.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
if (defined('__MINITALK__') == false) exit;

$domain = Request('domain');
$time = Request('time');

if (($secret_key == null && $client_secret == null) || is_string($domain) == false || $domain === '') {
	$data->success = false;
	$data->message = 'MISSING PARAMTERS : SECRET_KEY OR CLIENT_SECRET OR DOMAIN';
	return;
}

if ($secret_key != null && $secret_key != $_CONFIGS->key) {
	$data->success = false;
	$data->message = 'INVALID PARAMETER : SECRET_KEY';
	return;
}

if ($client_secret != null && $this->db()->select($this->table->server)->where('domain',$domain)->where('client_secret',$client_secret)->has() == false) {
	$data->success = false;
	$data->message = 'INVALID PARAMETER : CLIENT_SECRET';
	return;
}

$user = Request('user') ? Request('user') : 0;
$channel = Request('channel') ? Request('channel') : 0;
$this->db()->update($this->table->server,array('status'=>'ONLINE','user'=>$user,'channel'=>$channel,'latest_update'=>time()))->where('domain',$domain)->where('type','SERVER')->execute();
$this->db()->update($this->table->server,array('status'=>'ONLINE','user'=>$user,'channel'=>$channel))->where('domain',$domain)->where('type','SERVICE')->execute();
$this->db()->update($this->table->channel,array('user'=>0))->where('server',$domain)->execute();

$users = is_string(Request('users')) ? json_decode(Request('users')) : null;
if (is_object($users) == false && is_array($users) == false) $users = array();
foreach ($users as $room=>$user) {
	$temp = explode('#',(string)$room);
	if (count($temp) < 2) continue;
	$channel = $temp[1];
	
	$this->db()->update($this->table->channel,array('user'=>$user))->where('channel',$channel)->where('server',$domain)->execute();
}

$data->success = true;
$data->client = round(array_sum(explode(' ',microtime())) * 1000);
$data->server = intval($time);
$data->timegap = floatval(sprintf('%0.3f',abs($data->client - $data->server) / 1000));
?>