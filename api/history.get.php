<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 대화기록을 불러온다.
 * 
 * @file /api/history.get.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
if (defined('__MINITALK__') == false) exit;

$room = $idx;
if (is_string($room) == false || $room === '') {
	$data->success = false;
	$data->message = 'NOT_FOUND_ROOM';
	return;
}

$uuid = Request('uuid');

/**
 * 박스인경우 박스가 개설된 채널을 구한다.
 */
if (strpos($room,'@') !== false) {
	list($channel,$box) = explode('@',$room);
	
	$channel = $this->getChannel($channel);
} else {
	$channel = $this->getChannel($room);
	$box = null;
}

if ($channel == null) {
	$data->success = false;
	$data->message = 'NOT_FOUND_CHANNEL';
	return;
}

if ($channel->use_history == false || $token === null || $token->channel != $channel->channel || $token->ip != GetClientIp()) {
	$data->success = false;
	$data->message = 'FORBIDDEN';
	return;
}

$time = Request('time') ? Request('time') : time() * 1000;

/**
 * 대화기록 테이블에 보관된 가장 최근, 가장 이전 시각을 가져온다.
 */
$edge = $this->db()->select($this->table->history,'MIN(time) as oldest, MAX(time) as latest')->where('room',$room)->getOne();
$oldest = $edge->oldest ? $edge->oldest : 0;
$latest = $edge->latest ? $edge->latest : 0;

$history = array();
$server = $this->getServer($channel->server);

/**
 * 할당된 서버가 없는 경우
 */
if ($server == null) {
	$data->success = false;
	$data->message = 'NOT_FOUND_SERVER';
	return;
}

/**
 * 채널이 사용하고 있는 서버가 단독서버인 경우
 */
if ($server->type == 'SERVER') {
	/**
	 * 대화기록 테이블에 보관된 가장 최근 시각을 가져온다.
	 */
	$latest = $this->db()->select($this->table->history,'MAX(time) as latest')->where('room',$room)->getOne();
	$latest = $latest->latest ? $latest->latest : 0;
	
	$history = array();
	
	$data->success = true;
	$data->latest = $latest;
	$data->time = $time;
	
	$selector = array('id','type','message','data','user','uuid','to','target','time','room','edited');
	$selector = array_map(function($column) { return '`'.$column.'`'; },$selector);
	
	/**
	 * 요청한 대화기록이 모두 데이터베이스에 있는 경우
	 */
	if ($latest >= $time) {
		$history = $this->db()->select($this->table->history,$selector)->where('room',$room)->where('(target=? or uuid=? or target=?)',array('*',$uuid,$uuid))->where('time',$time,'<=')->orderBy('time','desc')->limit(30)->get();
		$history = array_reverse($history);
		
		for ($i=0, $loop=count($history);$i<$loop;$i++) {
			$history[$i]->user = json_decode($history[$i]->user);
			$history[$i]->user->uuid = $history[$i]->uuid;
			$history[$i]->data = json_decode($history[$i]->data);
			$history[$i]->to = json_decode($history[$i]->to);
			$history[$i]->edited = $history[$i]->edited == 'TRUE';
			
			if ($history[$i]->target != '*') {
				$history[$i]->to->uuid = $history[$i]->target;
			}
			
			unset($history[$i]->uuid);
			unset($history[$i]->target);
			
			unset($history[$i]->uuid);
		}
	} else {
		$history = $this->db()->select($this->table->history,$selector)->where('room',$room)->where('(target=? or uuid=? or target=?)',array('*',$uuid,$uuid))->where('time',$time,'<=')->orderBy('time','desc')->limit(30)->get();
		$history = array_reverse($history);
		
		for ($i=0, $loop=count($history);$i<$loop;$i++) {
			$history[$i]->user = json_decode($history[$i]->user);
			$history[$i]->user->uuid = $history[$i]->uuid;
			$history[$i]->data = json_decode($history[$i]->data);
			$history[$i]->to = json_decode($history[$i]->to);
			$history[$i]->edited = $history[$i]->edited == 'TRUE';
			
			if ($history[$i]->target != '*') {
				$history[$i]->to->uuid = $history[$i]->target;
			}
			
			unset($history[$i]->uuid);
			unset($history[$i]->target);
		}
		
		$api = $this->callServerApi('GET',$server->domain,'history/'.md5($server->domain).'/'.$room,array('uuid'=>$uuid,'time'=>$time,'limit'=>30));
		if ($api->success == true) {
			$history = array_merge($history,$api->history);
		}
	}
}

/**
 * 채널이 사용하고 있는 서버가 호스팅서버인 경우
 */
if ($server->type == 'SERVICE') {
	$connection = $server->connection ? $server->connection->connection : '';
	$api = $this->callServiceApi('GET','history/'.$server->domain.'/'.$room,array('time'=>$time),array('MINITALK_CLIENT_SECRET'=>$server->client_secret,'MINITALK_CONNECTION'=>$connection));
	if ($api->success == true) {
		$history = array();
		foreach ($api->history as $message) {
			if ($message->to === null) {
				$history[] = $message;
			} else {
				if ($message->to->uuid == $uuid || $message->user->uuid == $uuid) {
					$history[] = $message;
				}
			}
		}
	}
}

$data->success = true;
$data->history = $history;
?>