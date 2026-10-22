<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 사용하려고 하는 웹폰트명을 받아, 웹폰트파일 및 폰트스타일시트를 불러온다.
 *
 * @file /styles/font.css.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
session_write_close();
header("Content-Type:text/css; charset=utf-8");
header('Expires: '.gmdate('D, d M Y H:i:s',time() + 3600).' GMT');
header('Cache-Control: max-age=3600');
header('Pragma: public');

$language = isset($_GET['language']) == true && is_string($_GET['language']) == true ? $_GET['language'] : 'ko';
$font = isset($_GET['font']) == true && is_string($_GET['font']) == true ? explode(',',$_GET['font']) : array();
$default = isset($_GET['default']) == true && is_string($_GET['default']) == true ? $_GET['default'] : null;

$css = '';
for ($i=0, $loop=count($font);$i<$loop;$i++) {
	if (is_file('./fonts/'.$font[$i].'.css') == true) $css.= file_get_contents('./fonts/'.$font[$i].'.css');
}
echo str_replace('../../fonts','../fonts',$css);

if ($default != null) $fontFamily = $default.', ';
else $fontFamily = '';

if ($language == 'ko') $fontFamily.= '"Apple SD Neo Gothic", "malgun gothic", dotum';
else $fontFamily.= 'Arial';
$fontFamily.= ', sans-serif';
echo 'html, body {font-family:'.$fontFamily.';}';
?>