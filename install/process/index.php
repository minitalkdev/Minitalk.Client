<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 설치작업을 처리한다.
 * 
 * @file /install/process/index.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
REQUIRE_ONCE str_replace('/install/process','',str_replace(DIRECTORY_SEPARATOR,'/',__DIR__)).'/configs/init.config.php';
header("Content-type: text/json; charset=utf-8",true);

set_time_limit(0);
@ini_set('memory_limit',-1);
@ini_set('zlib.output_compression','Off');
@ini_set('output_buffering','Off');
@ini_set('output_handler','');
if (function_exists('apache_setenv') == true) {
	@apache_setenv('no-gzip',1);
}

$action = Request('action');
$results = new stdClass();
// An existing customer's database must only change through the approved admin update.
if ($action === 'install' && $_CONFIGS->installed === true) {
	try {
		$installed = new Minitalk();
		if ($installed->db()->exists('admin_table') && $installed->db()->select('admin_table')->count() > 0) {
			http_response_code(403);
			exit(json_encode(array('success'=>false,'code'=>'USE_ADMIN_DATABASE_UPDATE')));
		}
	} catch (Throwable $error) {
		http_response_code(403);
		exit(json_encode(array('success'=>false,'code'=>'INSTALLATION_STATE_UNVERIFIED')));
	}
}
if ($action == 'dependency') {
	$dependency = Request('dependency');
	$version = Request('version');
	
	$check = CheckDependency($dependency,$version);
	$results->success = true;
	$results->installed = $check->installed;
	$results->installedVersion = $check->installedVersion;
	$results->dependency = $dependency;
	$results->version = $version;
}

if ($action == 'requirement') {
	$requirement = Request('requirement');
	$version = Request('version');
	
	if (is_dir(__MINITALK_PATH__.'/'.$requirement) == true && is_file(__MINITALK_PATH__.'/'.$requirement.'/package.json') == true) {
		$package = json_decode(file_get_contents(__MINITALK_PATH__.'/'.$requirement.'/package.json'));
		if ($package == null) {
			$results->success = true;
			$results->installed = false;
			$results->installedVersion = null;
		} else {
			$results->success = true;
			$results->installed = true;
			$results->installedVersion = $package->version;
		}
	} else {
		$results->success = true;
		$results->installed = false;
		$results->installedVersion = null;
	}
	
	$results->requirement = $requirement;
	$results->version = $version;
}

if ($action == 'directory') {
	$directory = Request('directory');
	$permission = Request('permission');
	
	$path = $directory;
	if ($directory == 'attachments' && isset($_CONFIGS->attachment) == true && is_object($_CONFIGS->attachment) == true && isset($_CONFIGS->attachment->path) == true) {
		$path = $_CONFIGS->attachment->path;
	}
	$path = strpos($path,'/') === 0 ? $path : __MINITALK_PATH__.'/'.$path;
	
	$results->success = true;
	$results->path = $path;
	$results->directory = $directory;
	$results->created = CheckDirectoryPermission($path,$permission);
	$results->permission = $permission;
}

if ($action == 'config') {
	$config = Request('config');
	$results->success = true;
	$results->config = $config;
	$results->not_exists = !is_file(__MINITALK_PATH__.'/configs/'.$config.'.config.php');
}

if ($action == 'preset') {
	$preset = Request('preset');
	$results->success = true;
	$results->preset = $preset;
	$results->not_exists = !is_file(__MINITALK_PATH__.'/'.$preset.'.preset.php');
	$results->configs = new stdClass();
	$results->configs->key = $_CONFIGS->presets->key;
	$results->configs->db = $_CONFIGS->presets->db;
}

if ($action == 'install') {
	REQUIRE_ONCE __MINITALK_PATH__.'/classes/DB/mysql.class.php';
	
	$language = Request('language');
	$package = json_decode(file_get_contents(__MINITALK_PATH__.'/package.json'));
	
	$errors = array();
	if ($_CONFIGS->presets->key == true) {
		if (Request('key') != $_CONFIGS->key) $errors['key'] = 'key_preset';
	} elseif (is_file(__MINITALK_PATH__.'/configs/key.config.php') == true) {
		$keyFile = explode(PHP_EOL,file_get_contents(__MINITALK_PATH__.'/configs/key.config.php'));
		$key = $keyFile[1];
		if (Request('key') != $key) $errors['key'] = 'key_exists';
	} else {
		$key = Request('key') ? Request('key') : $errors['key'] = 'key';
	}
	$admin_id = Request('admin_id') ? Request('admin_id') : $errors['admin_id'] = 'admin_id';
	$admin_password = Request('admin_password') ? Request('admin_password') : $errors['admin_password'] = 'admin_password';
	$admin_nickname = Request('admin_nickname') ? Request('admin_nickname') : $errors['admin_nickname'] = 'admin_nickname';
	
	if ($_CONFIGS->presets->db == true) {
		$db = $_CONFIGS->db;
	} elseif (is_file(__MINITALK_PATH__.'/configs/db.config.php') == true) {
		$dbFile = explode(PHP_EOL,file_get_contents(__MINITALK_PATH__.'/configs/db.config.php'));
		$db = json_decode(Decoder($dbFile[1],$key));
	} else {
		$db = new stdClass();
		$db->type = 'mysql';
		$db->host = Request('db_host');
		$port = Request('db_port');
		$db->port = $port === null || $port === '' ? 3306 : filter_var($port,FILTER_VALIDATE_INT,array('options'=>array('min_range'=>1,'max_range'=>65535)));
		if ($db->port === false) $errors['db_port'] = 'INVALID_PORT';
		$db->username = Request('db_id');
		$db->password = Request('db_password');
		$db->database = Request('db_name');
	}
	
	if ($db->type == 'mysql') {
		$mysqli = new mysql();
		if ($mysqli->check($db) === false) {
			$errors['db_host'] = $errors['db_id'] = $errors['db_password'] = $errors['db_name'] = 'db';
		} else {
			$dbConnect = new mysql($db);
			$dbConnect->setPrefix(__MINITALK_DB_PREFIX__);
			$dbConnect->connect();
		}
	}
	
	if (count($errors) == 0) {
		$results->success = false;
		
		if ($_CONFIGS->presets->key == false) $keyFile = @file_put_contents(__MINITALK_PATH__.'/configs/key.config.php','<?php /*'.PHP_EOL.$key.PHP_EOL.'*/ ?>');
		else $keyFile = true;
		if ($_CONFIGS->presets->db == false) $dbFile = @file_put_contents(__MINITALK_PATH__.'/configs/db.config.php','<?php /*'.PHP_EOL.Encoder(json_encode($db),$key).PHP_EOL.'*/ ?>');
		else $dbFile = true;
		
		$attachments = isset($_CONFIGS->attachment) == true && is_object($_CONFIGS->attachment) == true && isset($_CONFIGS->attachment->path) == true ? $_CONFIGS->attachment->path : __MINITALK_PATH__.'/attachments';
		
		if (is_dir($attachments.'/temp') == false) {
			mkdir($attachments.'/temp',0707);
		}
		
		if ($keyFile !== false && $dbFile !== false) {
			if (CreateDatabase($dbConnect,$package->databases) === true) {
				$mHash = new Hash();
				$admin_password = $mHash->password_hash($admin_password);
				
				if ($dbConnect->select('admin_table')->where('idx',1)->has() == true) {
					$dbConnect->update('admin_table',array('user_id'=>$admin_id,'password'=>$admin_password,'nickname'=>$admin_nickname,'language'=>$language,'permission'=>'*','latest_login'=>time()))->where('idx',1)->execute();
				} else {
					$dbConnect->insert('admin_table',array('user_id'=>$admin_id,'password'=>$admin_password,'nickname'=>$admin_nickname,'language'=>$language,'permission'=>'*','latest_login'=>time()))->execute();
				}
				
				$results->success = true;
			} else {
				$results->message = 'table';
			}
		} else {
			$results->message = 'file';
		}
	} else {
		$results->success = false;
		$results->errors = $errors;
	}
}

exit(json_encode($results));
?>
