<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 채팅위젯에서 사용되는 모든 자바스크립트를 불러온다.
 * 
 * @file /scripts/widget.js.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
REQUIRE_ONCE str_replace('/scripts/widget.js.php','',str_replace(DIRECTORY_SEPARATOR,'/',$_SERVER['SCRIPT_FILENAME'])).'/configs/init.config.php';

session_write_close();
header('Content-Type: application/x-javascript; charset=utf-8');
header('Expires: '.gmdate('D, d M Y H:i:s',time() + 3600).' GMT');
header('Cache-Control: max-age=3600');
header('Pragma: public');

$channel = isset($_GET['channel']) == true ? $_GET['channel'] : null;
$templet = isset($_GET['templet']) == true ? $_GET['templet'] : null;
$language = isset($_GET['language']) == true ? $_GET['language'] : null;
$languages = GetDefaultLanguages();
if ($language == null) {
	foreach ($languages as $language) {
		if (is_file(__MINITALK_PATH__.'/languages/'.$language.'.json') == true) {
			break;
		}
	}
}

$MINITALK = new Minitalk();

$checksum = substr(md5(json_encode(GetDirectoryItems(__MINITALK_PATH__.'/plugins','directory',false))),0,6);
$cacheFile = $language.'.'.($templet == null ? 'common' : $templet).'.'.($channel == null ? 'global' : 'channel').'.'.$checksum.'.js';
if ($MINITALK->getCacheTime($cacheFile) >= $MINITALK->getLastModified()) {
	$content = $MINITALK->getCacheContent($cacheFile);
} else {
	$minifier = new Minifier();
	$js = $minifier->js();
	$js->add(__MINITALK_PATH__.'/scripts/uuid.js');
	$js->add(__MINITALK_PATH__.'/scripts/jquery.js');
	$js->add(__MINITALK_PATH__.'/scripts/widget.js');
	
	/**
	 * 미니톡 클라이언트의 언어파일을 불러온다.
	 */
	$package = json_decode(file_get_contents(__MINITALK_PATH__.'/package.json'));
	$lang = null;
	if (is_file(__MINITALK_PATH__.'/languages/'.$language.'.json') == false) {
		$language = $package->language;
	}
	
	$lang = file_get_contents(__MINITALK_PATH__.'/languages/'.$language.'.json');
	
	$js->add('Minitalk.version = '.$MINITALK->getVersionToInt(__MINITALK_VERSION__).';');
	$js->add('Minitalk.LANG = '.$lang.';');
	$js->add('Minitalk.language = "'.$language.'";');
	$js->add(__MINITALK_PATH__.'/scripts/common.js');
	
	$js->add(__MINITALK_PATH__.'/scripts/jquery.extend.js');
	$js->add(__MINITALK_PATH__.'/scripts/moment.js');
	
	if ($channel !== null) {
		$js->add(__MINITALK_PATH__.'/scripts/socket.io.js');
		$js->add(__MINITALK_PATH__.'/scripts/widget.user.js');
		$js->add(__MINITALK_PATH__.'/scripts/widget.ui.js');
		$js->add(__MINITALK_PATH__.'/scripts/widget.socket.js');
		$js->add(__MINITALK_PATH__.'/scripts/widget.protocol.js');
		$js->add(__MINITALK_PATH__.'/scripts/widget.box.js');
		
		/**
		 * 템플릿 전용 스크립트가 있을 경우 해당 스크립트를 불러온다.
		 */
		if ($templet !== null && is_file(__MINITALK_PATH__.'/templets/'.$templet.'/script.js') == true) {
			$js->add(__MINITALK_PATH__.'/templets/'.$templet.'/script.js');
		}
	}
	
	/**
	 * 플러그인을 불러온다.
	 */
	foreach (GetDirectoryItems(__MINITALK_PATH__.'/plugins','directory') as $plugin) {
		$name = basename($plugin);
		$js->add('Minitalk.plugins.'.$name.' = function() {');
		$js->add('
			var me = this;
			me.getText = function(code,replacement) {
				var replacement = replacement ? replacement : null;
				var temp = code.split("/");
				var string = me.LANG;
				for (var i=0, loop=temp.length;i<loop;i++) {
					if (string[temp[i]]) {
						string = string[temp[i]];
					} else {
						string = null;
						break;
					}
				}
				if (string != null) return string;
				return replacement == null ? code : replacement;
			};
		');
		
		/**
		 * 플러그인에 언어팩이 존재할 경우
		 */
		if (is_dir($plugin.'/languages') == true) {
			if (is_file($plugin.'/languages/'.$language.'.json') == false) {
				$language = $package->language;
			}
			$lang = file_get_contents($plugin.'/languages/'.$language.'.json');
		} else {
			$lang = 'null';
		}
		$js->add('me.LANG = '.$lang.';');
		
		if (is_file($plugin.'/script.js') == true) {
			$js->add($plugin.'/script.js');
		}
		$js->add('};');
		$js->add('new Minitalk.plugins.'.$name.'();');
	}
	
	$content = $js->minify(__MINITALK_PATH__.'/scripts');
	$MINITALK->saveCacheContent($cacheFile,$content);
}
?>
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 채팅위젯에서 사용될 모든 자바스크립트를 불러온다.
 * 
 * @file /scripts/widget.js.php
 * @license MIT License
 * @modified 2025. 2. 7.
 * @cached <?php echo date('Y. n. j. H:i:s',$MINITALK->getCacheTime($cacheFile))."\n"; ?>
 */
<?php echo $content; ?>