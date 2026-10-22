<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 패스워드를 저장하기 위한 SALT 기반 해시를 생성하거나, 패스워드를 검증한다.
 *
 * @file /classes/Hash.class.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
define('PBKDF2_COMPAT_HASH_ALGORITHM','SHA256');
define('PBKDF2_COMPAT_ITERATIONS',12000);
define('PBKDF2_COMPAT_SALT_BYTES',24);
define('PBKDF2_COMPAT_HASH_BYTES',24);

class Hash {
	function password_hash($password,$force_compat=false) {
		if (function_exists('mcrypt_create_iv') && version_compare(PHP_VERSION,'7.2','<')) {
			$salt = base64_encode(mcrypt_create_iv(PBKDF2_COMPAT_SALT_BYTES,MCRYPT_DEV_URANDOM));
		} elseif (file_exists('/dev/urandom') && $fp = @fopen('/dev/urandom','r')) {
			$salt = base64_encode(fread($fp,PBKDF2_COMPAT_SALT_BYTES));
		} else {
			$salt = '';
			for ($i=0;$i<PBKDF2_COMPAT_SALT_BYTES;$i+= 2) {
				$salt.= pack('S',mt_rand(0,65535));
			}
			$salt = base64_encode(substr($salt,0,PBKDF2_COMPAT_SALT_BYTES));
		}
		
		$algo = strtolower(PBKDF2_COMPAT_HASH_ALGORITHM);
		$iterations = PBKDF2_COMPAT_ITERATIONS;
		if ($force_compat || !function_exists('hash_algos') || !in_array($algo,hash_algos())) {
			$algo = false;
			$iterations = round($iterations / 5);
		}
		
		$pbkdf2 = $this->pbkdf2_default($algo,$password,$salt,$iterations,PBKDF2_COMPAT_HASH_BYTES);
		$prefix = $algo ? $algo : 'sha1';
		return $salt.':'.base64_encode($pbkdf2);
	}

	function password_validate($password,$hash) {
		$params = explode(':',$hash);
		if (count($params) < 2) return false;
		
		$pbkdf2 = base64_decode($params[1]);
		$pbkdf2_check = $this->pbkdf2_default(PBKDF2_COMPAT_HASH_ALGORITHM,$password,$params[0],PBKDF2_COMPAT_ITERATIONS,strlen($pbkdf2));
		
		return $this->slow_equals($pbkdf2,$pbkdf2_check);
	}

	function slow_equals($a,$b) {
		$diff = strlen($a) ^ strlen($b);
		for($i = 0; $i < strlen($a) && $i < strlen($b); $i++) {
			$diff |= ord($a[$i]) ^ ord($b[$i]);
		}
		return $diff === 0; 
	}

	function pbkdf2_default($algo,$password,$salt,$count,$key_length) {
		if ($count <= 0 || $key_length <= 0) {
			throw new InvalidArgumentException('PBKDF2 ERROR: Invalid parameters.');
		}
		
		if (!$algo) return $this->pbkdf2_fallback($password,$salt,$count,$key_length);
		
		$algo = strtolower($algo);
		if (!function_exists('hash_algos') || !in_array($algo,hash_algos())) {
			if ($algo === 'sha1') {
				return $this->pbkdf2_fallback($password,$salt,$count,$key_length);
			} else {
				throw new RuntimeException('PBKDF2 ERROR: Hash algorithm not supported.');
			}
		}
		
		if (function_exists('hash_pbkdf2')) {
			return hash_pbkdf2($algo,$password,$salt,$count,$key_length,true);
		}
		
		$hash_length = strlen(hash($algo,'',true));
		$block_count = ceil($key_length / $hash_length);
		
		$output = '';
		for ($i=1;$i<=$block_count;$i++) {
			$last = $salt.pack('N',$i);
			$last = $xorsum = hash_hmac($algo,$last,$password,true);
			for ($j=1;$j<$count;$j++) {
				$xorsum ^= ($last = hash_hmac($algo,$last,$password,true));
			}
			$output.= $xorsum;
		}
		
		return substr($output,0,$key_length);
	}

	function pbkdf2_fallback($password,$salt,$count,$key_length) {
		$hash_length = 20;
		$block_count = ceil($key_length / $hash_length);
		
		if (strlen($password) > 64) {
			$password = str_pad(sha1($password,true),64,chr(0));
		} else {
			$password = str_pad($password,64,chr(0));
		}
		
		$opad = str_repeat(chr(0x5C),64) ^ $password;
		$ipad = str_repeat(chr(0x36),64) ^ $password;
		
		$output = '';
		for ($i=1;$i<=$block_count;$i++) {
			$last = $salt . pack('N',$i);
			$xorsum = $last = pack('H*',sha1($opad.pack('H*',sha1($ipad.$last))));
			for ($j=1;$j<$count;$j++) {
				$last = pack('H*',sha1($opad.pack('H*',sha1($ipad.$last))));
				$xorsum ^= $last;
			}
			$output.= $xorsum;
		}
		
		return substr($output,0,$key_length);
	}
}
?>