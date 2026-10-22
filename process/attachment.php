<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 첨부파일을 열람하거나 다운로드한다.
 * 
 * @file /process/attachment.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
if (defined('__MINITALK__') == false) exit;

$extras = is_string(Request('extras')) && Request('extras') !== '' ? explode('/',Request('extras')) : array();
if (count($extras) != 3) {
	header("HTTP/1.1 404 Not Found");
	exit;
}

$mode = $extras[0];
$hash = $extras[1];
$file = $this->db()->select($this->table->attachment)->where('hash',$hash)->getOne();
if ($file == null || ($file->exp_date > 0 && $file->exp_date < time()) || is_file($this->getAttachmentPath().'/'.$file->path) == false) {
	$this->printFileError($mode);
}

session_write_close();

if ($mode == 'view' && $file->type == 'image') {
	/**
	 * @todo 이미지 가로크기가 1000 픽셀 이상인 경우, 썸네일을 생성한다.
	 */
	if (false && $file->width > 1400) {
		/*
		if (is_file($this->IM->getAttachmentPath().'/'.$file->path.'.view') == true) {
			if ($file->type == 'image') header('Content-Type: '.$file->mime);
			else header('Content-Type: image/jpeg');
			header('Content-Length: '.filesize($this->IM->getAttachmentPath().'/'.$file->path.'.view'));
			
			session_write_close();
			readfile($this->IM->getAttachmentPath().'/'.$file->path.'.view');
			exit;
		} else {
			if ($this->createThumbnail($this->IM->getAttachmentPath().'/'.$file->path,$this->IM->getAttachmentPath().'/'.$file->path.'.view',1400,0,false) == false) {
				header("HTTP/1.1 404 Not Found");
				exit;
			}
			header('Content-Type: '.$file->mime);
			header('Content-Length: '.filesize($this->IM->getAttachmentPath().'/'.$file->path.'.view'));
			
			session_write_close();
			readfile($this->IM->getAttachmentPath().'/'.$file->path.'.view');
			exit;
		}
		*/
	} else {
		header('Content-Type: '.$file->mime);
		readfile($this->getAttachmentPath().'/'.$file->path);
	}
} else {
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false);
	if (preg_match('/Safari/',$_SERVER['HTTP_USER_AGENT']) == true) {
		header('Content-Disposition: attachment; filename="'.$file->name.'"');
	} else {
		header('Content-Disposition: attachment; filename="'.rawurlencode($file->name).'"; filename*=UTF-8\'\''.rawurlencode($file->name));
	}
	header("Content-Transfer-Encoding: binary");
	header('Content-Type: '.($file->mime == 'Unknown' ? 'application/x-unknown' : $file->mime));
	header('Content-Length: '.$file->size);

	session_write_close();

	readfile($this->getAttachmentPath().'/'.$file->path);
}
exit;
?>