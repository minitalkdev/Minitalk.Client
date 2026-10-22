<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 신뢰하는 프록시 목록을 기준으로 실제 클라이언트 아이피를 구한다.
 * functions.php 와 widget.php 의 GetClientIp() 가 같은 판정을 하도록 공유한다.
 *
 * @file /classes/clientip.php
 * @license MIT License
 */
if (function_exists('MinitalkResolveClientIp') == false) {
	/**
	 * @param string[] $trusted 신뢰하는 프록시 아이피 또는 CIDR 대역
	 * @return string $ip
	 */
	function MinitalkResolveClientIp($trusted) {
		$peer = $_SERVER['REMOTE_ADDR'] ?? '';
		if (!is_string($peer) || !filter_var($peer,FILTER_VALIDATE_IP)) return '';
		if (!is_array($trusted)) $trusted = array();
		$isTrusted = static function($ip) use ($trusted) {
			foreach ($trusted as $range) {
				if (!is_string($range)) continue;
				$parts = explode('/',$range,2);
				$network = @inet_pton($parts[0]); $address = @inet_pton($ip);
				if ($network === false || $address === false || strlen($network) !== strlen($address)) continue;
				$bits = isset($parts[1]) ? filter_var($parts[1],FILTER_VALIDATE_INT) : strlen($network)*8;
				if ($bits === false || $bits < 0 || $bits > strlen($network)*8) continue;
				$bytes = intdiv($bits,8); $remaining = $bits%8;
				if (substr($network,0,$bytes) !== substr($address,0,$bytes)) continue;
				if (!$remaining || ((ord($network[$bytes]) ^ ord($address[$bytes])) & (255 << (8-$remaining))) === 0) return true;
			}
			return false;
		};
		$forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
		if (!$isTrusted($peer) || !is_string($forwarded) || strlen($forwarded) > 8192) return $peer;
		$chain = array_map('trim',explode(',',$forwarded));
		foreach ($chain as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP)) return $peer;
		// Walk from the trusted peer: an attacker can prepend arbitrary XFF entries.
		for ($i=count($chain)-1; $i>=0 && $isTrusted($peer); $i--) $peer=$chain[$i];
		return $peer;
	}
}
?>
