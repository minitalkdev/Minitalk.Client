<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 채널목록을 불러온다.
 * 
 * @file /process/@getChannels.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
if (defined('__MINITALK__') == false) exit;

$category1 = Request('category1') ? Request('category1') : 0;
$category2 = Request('category2') ? Request('category2') : 0;
$keyword = Request('keyword') ? Request('keyword') : '';
$start = max(0,(int)(Request('start') ?? 0));
$limit = Request('limit');
$limit = is_numeric($limit) && (int)$limit > 0 ? (int)$limit : 50;
$sort = Request('sort') ? Request('sort') : 'channel';
$dir = Request('dir') ? Request('dir') : 'asc';

$lists = $this->db()->select($this->table->channel);
if ($category1) $lists->where('category1',$category1);
if ($category2) $lists->where('category2',$category2);
if ($keyword) $lists->where('(channel like ? or title like ?)',array('%'.$keyword.'%','%'.$keyword.'%'));
$total = $lists->copy()->count();
$lists = $lists->limit($start,$limit)->orderBy($sort,$dir)->get();
for ($i=0, $loop=count($lists);$i<$loop;$i++) {
	$lists[$i]->category1 = $this->getCategoryTitle($lists[$i]->category1);
	$lists[$i]->category2 = $this->getCategoryTitle($lists[$i]->category2);
	$lists[$i]->server = $this->getServer($lists[$i]->server);
	$lists[$i]->allow_nickname_edit = $lists[$i]->allow_nickname_edit == 'TRUE';
	$lists[$i]->use_user_tab = $lists[$i]->user_limit > -1;
	$lists[$i]->use_box_tab = $lists[$i]->box_limit > -1;
	$lists[$i]->title_channel = $lists[$i]->title.'(#'.$lists[$i]->channel.')';
}

$results->success = true;
$results->lists = $lists;
$results->total = $total;
?>