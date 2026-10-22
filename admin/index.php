<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 클라이언트 관리자 레이아웃을 출력한다.
 * 관리자페이지와 관련된 파일은 ExtJS 라이센스정책에 따라 GPLv3 라이센스로 배포됩니다.
 * 
 * @file /admin/index.php
 * @license GPLv3
 * @modified 2025. 2. 7.
 */
REQUIRE '../configs/init.config.php';
if ($_CONFIGS->installed === false) {
	header("location:../install");
	exit;
}

$MINITALK = new Minitalk();
$logged = $MINITALK->getAdminLogged();
$checkUpdate = true;
$databaseUpdate = null;
if ($logged !== null) {
	$databaseUpdate = $MINITALK->getDatabaseUpdateStatus();
	if (!isset($_SESSION['MINITALK_DATABASE_CSRF'])) $_SESSION['MINITALK_DATABASE_CSRF'] = bin2hex(random_bytes(32));
}

if ($logged !== null && $logged->language == 'ko') {
	$checkUpdate = $MINITALK->checkUpdate();
	
	$fontStyle = '../styles/font.css.php?font=moimz,XEIcon,FontAwesome,NanumBarunGothic,OpenSans&default=NanumBarunGothic';
} else {
	$fontStyle = '../styles/font.css.php?font=moimz,XEIcon,FontAwesome,OpenSans&default=OpenSans';
}

$current = Request('menu') ? Request('menu') : 'server';
$hasServer = is_dir(__MINITALK_PATH__.'/server') == true;
?>
<!DOCTYPE HTML>
<html lang="<?php echo $logged == null ? 'en' : $logged->language; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=1200, maximum-scale=10, user-scalable=yes">
<title>Minitalk Administrator</title>
<script src="../scripts/jquery.js?t=<?php echo filemtime('../scripts/jquery.js'); ?>"></script>
<script src="../scripts/jquery.extend.js?t=<?php echo filemtime('../scripts/jquery.extend.js'); ?>"></script>
<script src="../scripts/moment.js?t=<?php echo filemtime('../scripts/moment.js'); ?>"></script>
<?php if ($logged !== null) { ?>
<link rel="stylesheet" href="./styles/style.css?t=<?php echo filemtime('./styles/style.css'); ?>" type="text/css">
<link rel="stylesheet" href="../styles/extjs.css?t=<?php echo filemtime('../styles/extjs.css'); ?>" type="text/css">
<link rel="stylesheet" href="../styles/extjs.extend.css?t=<?php echo filemtime('../styles/extjs.extend.css'); ?>" type="text/css">
<script src="../scripts/extjs.js?t=<?php echo filemtime('../scripts/extjs.js'); ?>"></script>
<script src="../scripts/extjs.extend.js?t=<?php echo filemtime('../scripts/extjs.extend.js'); ?>"></script>
<?php } else { ?>
<link rel="stylesheet" href="./styles/login.css?t=<?php echo filemtime('./styles/login.css'); ?>" type="text/css">
<?php } ?>
<link rel="stylesheet" href="<?php echo $fontStyle; ?>" type="text/css">
<script src="./scripts/script.js?t=<?php echo filemtime('./scripts/script.js'); ?>"></script>
<?php if ($logged !== null) { ?><script src="./scripts/database-update.js?t=<?php echo filemtime('./scripts/database-update.js'); ?>" defer></script><?php } ?>
<script src="../scripts/language.js.php?language=<?php echo $logged == null ? 'en' : $logged->language; ?>"></script>
<link rel="shortcut icon" type="image/x-icon" href="//www.minitalk.io/modules/moimz/images/Minitalk.ico">
</head>
<body<?php echo $logged === null ? ' class="login"' : ''; ?>>
<?php
if ($logged === null) {
	INCLUDE './login.php';
} else {
	$menuIcons = array('server'=>'xi-cloud-network','channel'=>'xi-chat','log'=>'xi-time-back','resource'=>'xi-archive','banip'=>'xi-slash-circle','broadcast'=>'xi-signal','admin'=>'xi-crown');
?>
<header id="MinitalkHeader">
 <?php if (PHP_VERSION_ID >= 80600) { ?>
 <span role="status">현재 PHP 버전은 검증된 지원 범위(8.2~8.5)를 벗어납니다. 업데이트 전 호환성을 확인해 주세요.</span>
 <?php } ?>
	<?php if ($checkUpdate !== true) { ?>
	<a href="https://www.minitalk.io/ko/download/current" target="_blank" data-role="update"><?php echo $checkUpdate; ?></a>
	<?php } ?>
	<?php if ($databaseUpdate !== null && $databaseUpdate['pending']) { ?>
	<?php if ($databaseUpdate['blocked']) { ?>
	<a data-role="install" role="status">자동으로 변경할 수 없는 DB 구조입니다. 기존 데이터는 유지됩니다. 관리자에게 문의해 주세요.</a>
	<?php } else { ?>
	<a href="#database-update" role="button" id="MinitalkDatabaseUpdate" data-role="install"
		data-url="<?php echo htmlspecialchars($MINITALK->getClientProcessUrl('@updateDatabase'),ENT_QUOTES,'UTF-8'); ?>"
		data-token="<?php echo htmlspecialchars($_SESSION['MINITALK_DATABASE_CSRF'],ENT_QUOTES,'UTF-8'); ?>"
		data-schema="<?php echo htmlspecialchars($databaseUpdate['schema_hash'],ENT_QUOTES,'UTF-8'); ?>">새 클라이언트에 필요한 DB 업데이트가 있습니다. 설정과 대화 기록을 유지하고 필요한 필드만 변경합니다. [업데이트]</a>
	<?php } ?>
	<?php } ?>
	<h1>Minitalk <small>Administrator</small></h1>
	
	<ul data-role="menu">
		<li data-role="more">
			<button type="button"><i class="mi mi-angle-down"></i></button>
			<ul></ul>
		</li>
		<?php foreach ($MINITALK->getText('admin/menu') as $menu=>$title) { if (in_array($menu,array('log','broadcast')) == true && $hasServer == false) continue; ?>
		<li<?php echo $menu == 'server' ? ' class="selected"' : ''; ?>><button data-tab="<?php echo $menu; ?>"><i class="xi <?php echo $menuIcons[$menu]; ?>"></i><?php echo $title; ?></button></li>
		<?php } ?>
	</ul>
	
	<aside>
		<button type="button" onclick="Admin.logout();">LOGOUT</button>
	</aside>
</header>

<footer id="MinitalkFooter">
	Copyright(c) <?php echo date('Y'); ?> Minitalk v<?php echo __MINITALK_VERSION__; ?> / MIT License / <?php echo htmlspecialchars($_SERVER['SERVER_ADDR'] ?? '',ENT_QUOTES,'UTF-8'); ?>
</footer>

<script>
Ext.onReady(function () {
	new Ext.Viewport({
		id:"AdminViewport",
		layout:{type:"border"},
		items:[
			new Ext.Panel({
				id:"MinitalkHeaderPanel",
				region:"north",
				border:false,
				contentEl:"MinitalkHeader",
				cls:"x-visible-panel"
			}),
			new Ext.TabPanel({
				id:"MinitalkTabPanel",
				border:false,
				region:"center",
				items:[
					new Ext.grid.Panel({
						id:"MinitalkPanel-server",
						hasServer:<?php echo $hasServer == true ? 'true' : 'false'; ?>,
						tbar:[
							new Ext.Button({
								text:Admin.getText("server/add"),
								iconCls:"mi mi-plus",
								handler:function() {
									Admin.server.add();
								}
							}),
							new Ext.Button({
								text:Admin.getText("server/delete"),
								iconCls:"mi mi-trash",
								handler:function() {
									Admin.server.status("delete");
								}
							})
						],
						store:new Ext.data.JsonStore({
							proxy:{
								type:"ajax",
								simpleSortMode:true,
								url:Minitalk.getProcessUrl("@getServers"),
								reader:{type:"json"}
							},
							remoteSort:false,
							sorters:[{property:"domain",direction:"ASC"}],
							autoLoad:true,
							pageSize:0,
							groupField:"type",
							groupDir:"ASC",
							fields:["domain","status","status_message",{"name":"channel","type":"int"},{"name":"user","type":"int"},{"name":"maxuser","type":"int"},{"name":"latest_update","type":"int"},{"name":"exp_date","type":"int"}],
							listeners:{
								load:function(store,records,success,e) {
									if (success == false) {
										if (e.getError()) {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										} else {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_LOAD_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										}
									}
								}
							}
						}),
						columns:[{
							text:Admin.getText("server/columns/domain"),
							summaryType:"count",
							dataIndex:"domain",
							minWidth:200,
							flex:1,
							sortable:true,
							summaryRenderer:function(value) {
								return value+" server"+(value > 1 ? "s" : "");
							}
						},{
							text:Admin.getText("server/columns/status"),
							dataIndex:"status",
							width:80,
							sortable:true,
							align:"center",
							renderer:function(value,p) {
								if (value == "ONLINE") p.style = "color:blue;";
								else p.style = "color:red;";
								
								return Admin.getText("server/status/"+value);
							}
						},{
							text:Admin.getText("server/columns/status_message"),
							dataIndex:"status_message",
							width:250,
							sortable:true,
							renderer:function(value,p,record) {
								if (record.data.type == "SERVER") {
									if (record.data.status == "ONLINE") {
										return "Uptime : " + moment(moment().unix() * 1000 - value.uptime * 1000).locale("ko").fromNow(true) + " / Memory : " + Minitalk.getFileSize(value.memory.rss + value.memory.heapTotal + value.memory.heapUsed + value.memory.external);
									} else {
										return;
									}
								} else {
									return value;
								}
							}
						},{
							text:Admin.getText("server/columns/channel"),
							dataIndex:"channel",
							width:120,
							sortable:true,
							align:"right",
							summaryType:"sum",
							renderer:function(value) {
								return Ext.util.Format.number(value,"0,000");
							},
							summaryRenderer:function(value) {
								return Ext.util.Format.number(value,"0,000");
							}
						},{
							text:Admin.getText("server/columns/user"),
							dataIndex:"user",
							width:120,
							sortable:true,
							align:"right",
							summaryType:"sum",
							renderer:function(value) {
								return Ext.util.Format.number(value,"0,000");
							},
							summaryRenderer:function(value) {
								return Ext.util.Format.number(value,"0,000");
							}
						},{
							text:Admin.getText("server/columns/max_user"),
							dataIndex:"max_user",
							width:120,
							sortable:true,
							align:"right",
							summaryType:"sum",
							renderer:function(value) {
								if (value == 0) return '<div style="text-align:center;">'+Admin.getText("server/unlimited")+'</div>';
								return Ext.util.Format.number(value,"0,000");
							},
							summaryRenderer:function(value) {
								if (value == 0) return '<div style="text-align:center;">'+Admin.getText("server/unlimited")+'</div>';
								return Ext.util.Format.number(value,"0,000");
							}
						},{
							text:Admin.getText("server/columns/latest_update"),
							dataIndex:"latest_update",
							width:160,
							sortable:true,
							align:"center",
							summaryType:"max",
							renderer:function(value) {
								if (value == 0) return Admin.getText("server/unknown");
								return moment(value * 1000).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm");
							},
							summaryRenderer:function(value) {
								if (value == 0) return Admin.getText("server/unknown");
								return moment(value * 1000).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm");
							}
						},{
							text:Admin.getText("server/columns/exp_date"),
							dataIndex:"exp_date",
							width:160,
							sortable:true,
							align:"center",
							summaryType:"max",
							renderer:function(value) {
								if (value == -1) return Admin.getText("server/unknown");
								else if (value == 0) return Admin.getText("server/unlimited");
								return moment(value * 1000).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm");
							},
							summaryRenderer:function(value) {
								if (value == 0) return Admin.getText("server/unlimited");
								return moment(value * 1000).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm");
							}
						}],
						selModel:new Ext.selection.CheckboxModel(),
						features:[{
							ftype:"groupingsummary",
							groupHeaderTpl:'<tpl if="name == \'SERVER\'">'+Admin.getText("server/type/SERVER")+'<tpl elseif="name == \'SERVICE\'">'+Admin.getText("server/type/SERVICE")+'</tpl>',
							hideGroupedHeader:false,
							enableGroupingMenu:false
						}],
						bbar:[
							new Ext.Button({
								iconCls:"x-tbar-loading",
								handler:function() {
									Ext.getCmp("MinitalkPanel-server").getStore().reload();
								}
							}),
							"->",
							{xtype:"tbtext",text:Admin.getText("server/grid_help")}
						],
						listeners:{
							itemdblclick:function(grid,record) {
								Admin.server.add(record.data.domain);
							},
							itemcontextmenu:function(grid,record,item,index,e) {
								var menu = new Ext.menu.Menu();
								
								menu.addTitle(record.data.domain);
								
								menu.add({
									text:Admin.getText("server/modify"),
									iconCls:"xi xi-form",
									handler:function() {
										Admin.server.add(record.data.domain);
									}
								});
								
								menu.add({
									text:Admin.getText("server/delete"),
									iconCls:"mi mi-trash",
									handler:function() {
										Admin.server.status("delete");
									}
								});
								
								e.stopEvent();
								menu.showAt(e.getXY());
							}
						}
					}),
					new Ext.grid.Panel({
						id:"MinitalkPanel-channel",
						tbar:[
							new Ext.form.ComboBox({
								id:"MinitalkChannelCategory1",
								store:new Ext.data.JsonStore({
									proxy:{
										type:"ajax",
										url:Minitalk.getProcessUrl("@getCategories"),
										extraParams:{parent:0,is_all:"true"},
										reader:{type:"json"}
									},
									autoLoad:true,
									remoteSort:false,
									sorters:[{property:"sort",direction:"ASC"}],
									fields:["idx","category",{name:"sort",type:"int"}]
								}),
								width:120,
								editable:false,
								matchFieldWidth:false,
								listConfig:{
									minWidth:120
								},
								displayField:"category",
								valueField:"idx",
								value:"",
								listeners:{
									change:function(form,value) {
										Ext.getCmp("MinitalkChannelCategory2").setValue("");
										Ext.getCmp("MinitalkChannelCategory2").getStore().getProxy().setExtraParam("parent",value ? value : -1);
										Ext.getCmp("MinitalkChannelCategory2").getStore().reload();
										
										Ext.getCmp("MinitalkPanel-channel").getStore().getProxy().setExtraParam("category1",value);
										Ext.getCmp("MinitalkPanel-channel").getStore().getProxy().setExtraParam("category2","");
										Ext.getCmp("MinitalkPanel-channel").getStore().loadPage(1);
									}
								}
							}),
							new Ext.form.ComboBox({
								id:"MinitalkChannelCategory2",
								store:new Ext.data.JsonStore({
									proxy:{
										type:"ajax",
										url:Minitalk.getProcessUrl("@getCategories"),
										extraParams:{parent:-1,is_all:"true"},
										reader:{type:"json"}
									},
									autoLoad:true,
									remoteSort:false,
									sorters:[{property:"sort",direction:"ASC"}],
									fields:["idx","category",{name:"sort",type:"int"}]
								}),
								width:120,
								editable:false,
								matchFieldWidth:false,
								listConfig:{
									minWidth:120
								},
								displayField:"category",
								valueField:"idx",
								value:"",
								listeners:{
									change:function(form,value) {
										Ext.getCmp("MinitalkPanel-channel").getStore().getProxy().setExtraParam("category2",value);
										Ext.getCmp("MinitalkPanel-channel").getStore().loadPage(1);
									}
								}
							}),
							new Ext.Button({
								iconCls:"mi mi-config",
								handler:function() {
									Admin.channel.category.panel();
								}
							}),
							"-",
							Admin.searchField("MinitalkChannelKeyword",200,Admin.getText("channel/columns/channel") + " / " + Admin.getText("channel/columns/title"),function(keyword) {
								Ext.getCmp("MinitalkPanel-channel").getStore().getProxy().setExtraParam("keyword",keyword);
								Ext.getCmp("MinitalkPanel-channel").getStore().loadPage(1);
							}),
							"-",
							new Ext.Button({
								text:Admin.getText("channel/add"),
								iconCls:"mi mi-plus",
								handler:function() {
									Admin.channel.add();
								}
							}),
							new Ext.Button({
								iconCls:"mi mi-trash",
								text:Admin.getText("channel/delete"),
								handler:function() {
									Admin.channel.delete();
								}
							})
						],
						store:new Ext.data.JsonStore({
							proxy:{
								type:"ajax",
								simpleSortMode:true,
								url:Minitalk.getProcessUrl("@getChannels"),
								reader:{type:"json"}
							},
							remoteSort:true,
							sorters:[{property:"channel",direction:"ASC"}],
							autoLoad:true,
							pageSize:50,
							fields:["channel","title"],
							listeners:{
								load:function(store,records,success,e) {
									if (success == false) {
										if (e.getError()) {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										} else {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_LOAD_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										}
									}
								}
							}
						}),
						columns:[{
							text:Admin.getText("channel/columns/channel"),
							summaryType:"count",
							dataIndex:"channel",
							width:180,
							sortable:true
						},{
							text:Admin.getText("channel/columns/category1"),
							dataIndex:"category1",
							width:120
						},{
							text:Admin.getText("channel/columns/category2"),
							dataIndex:"category2",
							width:120
						},{
							text:Admin.getText("channel/columns/title"),
							dataIndex:"title",
							minWidth:150,
							flex:1,
							sortable:true
						},{
							text:Admin.getText("channel/columns/password"),
							dataIndex:"password",
							width:120
						},{
							text:Admin.getText("channel/columns/server"),
							dataIndex:"server",
							width:240,
							renderer:function(value,p) {
								if (value == null) {
									p.tdStyle = "color:#666;";
									return Admin.getText("channel/unknown");
								} else {
									return value.domain;
								}
							}
						},{
							text:Admin.getText("channel/columns/send_limit"),
							dataIndex:"send_limit",
							width:140,
							align:"center",
							renderer:function(value) {
								return Minitalk.getText("level/"+value);
							}
						},{
							text:Admin.getText("channel/columns/user"),
							dataIndex:"user",
							width:120,
							align:"right",
							sortable:true,
							renderer:function(value,p,record) {
								var sHTML = "";
								sHTML+= '<b style="color:blue;">'+Ext.util.Format.number(value,"0,000")+'</b> / ';
								sHTML+= Ext.util.Format.number(record.data.max_user,"0,000");
								return sHTML;
							}
						},{
							text:Admin.getText("channel/columns/options"),
							dataIndex:"options",
							width:300,
							renderer:function(value,p,record) {
								var sHTML = "";
								if (record.data.allow_nickname_edit == true) {
									sHTML+= '<i class="xi xi-check-boxout"></i>';
								} else {
									sHTML+= '<i class="xi xi-layout-full"></i>';
								}
								sHTML+= ' '+Admin.getText("channel/form/allow_nickname_edit");
								
								sHTML+= "&nbsp;&nbsp;&nbsp;";
								if (record.data.use_user_tab == true) {
									sHTML+= '<i class="xi xi-check-boxout"></i>';
								} else {
									sHTML+= '<i class="xi xi-layout-full"></i>';
								}
								sHTML+= ' '+Admin.getText("channel/form/use_user_tab");
								sHTML+= '(' + record.data.user_limit + '<i class="fa fa-caret-up"></i>)';
								
								sHTML+= "&nbsp;&nbsp;&nbsp;";
								if (record.data.use_box_tab == true) {
									sHTML+= '<i class="xi xi-check-boxout"></i>';
								} else {
									sHTML+= '<i class="xi xi-layout-full"></i>';
								}
								sHTML+= ' '+Admin.getText("channel/form/use_box_tab");
								sHTML+= '(' + record.data.box_limit + '<i class="fa fa-caret-up"></i>)';
								
								return sHTML;
							}
						}],
						selModel:new Ext.selection.CheckboxModel(),
						bbar:new Ext.PagingToolbar({
							store:null,
							displayInfo:false,
							items:[
								"->",
								{xtype:"tbtext",text:Admin.getText("channel/grid_help")}
							],
							listeners:{
								beforerender:function(tool) {
									tool.bindStore(tool.ownerCt.getStore());
								}
							}
						}),
						listeners:{
							itemdblclick:function(grid,record) {
								Admin.channel.add(record.data.channel);
							},
							itemcontextmenu:function(grid,record,row,index,e) {
								var menu = new Ext.menu.Menu();
								
								menu.addTitle(record.data.title);
								
								menu.add({
									text:Admin.getText("channel/preview"),
									iconCls:"xi xi-monitor",
									handler:function() {
										Admin.channel.preview(record.data);
									}
								});
								
								menu.add({
									text:Admin.getText("channel/code"),
									iconCls:"xi xi-code",
									handler:function() {
										Admin.channel.code(record.data);
									}
								});
								
								menu.add("-");
								
								menu.add({
									text:Admin.getText("channel/modify"),
									iconCls:"xi xi-form",
									handler:function() {
										Admin.channel.add(record.data.channel);
									}
								});
								
								menu.add({
									text:Admin.getText("channel/delete"),
									iconCls:"mi mi-trash",
									handler:function() {
										Admin.channel.delete();
									}
								});
								
								e.stopEvent();
								menu.showAt(e.getXY());
							}
						}
					}),
					new Ext.TabPanel({
						id:"MinitalkPanel-resource",
						tabPosition:"bottom",
						items:[
							new Ext.grid.Panel({
								id:"MinitalkAttachment",
								border:false,
								iconCls:"xi xi-upload",
								title:Admin.getText("resource/attachment/title"),
								tbar:[
									Admin.searchField("MinitalkAttachmentKeyword",200,Admin.getText("resource/attachment/columns/name") + " / " + Admin.getText("resource/attachment/columns/nickname"),function(keyword) {
										Ext.getCmp("MinitalkAttachment").getStore().getProxy().setExtraParam("keyword",keyword);
										Ext.getCmp("MinitalkAttachment").getStore().loadPage(1);
									}),
									"-",
									new Ext.Button({
										iconCls:"mi mi-trash",
										text:Admin.getText("resource/attachment/delete_selected"),
										handler:function() {
											Admin.resource.attachment.delete();
										}
									}),
									"->",
									new Ext.Button({
										iconCls:"mi mi-calendar",
										text:Admin.getText("resource/attachment/delete_expired"),
										handler:function() {
											Admin.resource.attachment.expired();
										}
									})
								],
								store:new Ext.data.JsonStore({
									proxy:{
										type:"ajax",
										simpleSortMode:true,
										url:Minitalk.getProcessUrl("@getAttachments"),
										reader:{type:"json"}
									},
									remoteSort:true,
									sorters:[{property:"reg_date",direction:"DESC"}],
									autoLoad:true,
									pageSize:50,
									fields:["hash","icon","name","channel","nickname","ip","path",{"name":"size","type":"int"},{"name":"reg_date","type":"int"},{"name":"exp_date","type":"int"}],
									listeners:{
										load:function(store,records,success,e) {
											if (success == false) {
												if (e.getError()) {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												} else {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_LOAD_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												}
											}
										}
									}
								}),
								columns:[{
									text:Admin.getText("resource/attachment/columns/name"),
									dataIndex:"name",
									width:200,
									sortable:true,
									renderer:function(value,p,record) {
										return '<i class="icon" style="background-image:url(' + record.data.icon + '); background-size:contain; background-repeat:no-repeat; background-position:50% 50%; margin-right:8px;"></i>' + value;
									}
								},{
									text:Admin.getText("resource/attachment/columns/channel"),
									dataIndex:"channel",
									width:120,
									sortable:true,
									renderer:function(value) {
										return "#" + value;
									}
								},{
									text:Admin.getText("resource/attachment/columns/nickname"),
									dataIndex:"nickname",
									width:120,
									sortable:true
								},{
									text:Admin.getText("resource/attachment/columns/ip"),
									dataIndex:"ip",
									width:120,
									sortable:true,
									align:"center"
								},{
									text:Admin.getText("resource/attachment/columns/size"),
									dataIndex:"size",
									width:100,
									sortable:true,
									align:"right",
									renderer:function(value) {
										return Minitalk.getFileSize(value);
									}
								},{
									text:Admin.getText("resource/attachment/columns/path"),
									dataIndex:"path",
									minWidth:200,
									flex:1,
									sortable:true
								},{
									text:Admin.getText("resource/attachment/columns/reg_date"),
									dataIndex:"reg_date",
									width:145,
									sortable:true,
									align:"center",
									renderer:function(value) {
										return moment(value * 1000).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm");
									}
								},{
									text:Admin.getText("resource/attachment/columns/exp_date"),
									dataIndex:"exp_date",
									width:145,
									sortable:true,
									align:"center",
									renderer:function(value,p) {
										if (value * 1000 < moment().valueOf()) p.style = "color:#999;";
										if (value == 0) return "";
										
										return moment(value * 1000).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm");
									}
								}],
								selModel:new Ext.selection.CheckboxModel(),
								bbar:new Ext.PagingToolbar({
									store:null,
									displayInfo:false,
									items:[
										"->",
										{xtype:"tbtext",text:Admin.getText("resource/attachment/grid_help")}
									],
									listeners:{
										beforerender:function(tool) {
											tool.bindStore(tool.ownerCt.getStore());
										}
									}
								}),
								listeners:{
									itemdblclick:function(grid,record) {
										downloadFrame.location.href = record.data.download;
									},
									itemcontextmenu:function(grid,record,item,index,e) {
										var menu = new Ext.menu.Menu();
										
										menu.addTitle(record.data.name);
										
										menu.add({
											text:Admin.getText("resource/attachment/delete"),
											iconCls:"mi mi-trash",
											handler:function() {
												Admin.resource.attachment.delete();
											}
										});
										
										e.stopEvent();
										menu.showAt(e.getXY());
									}
								}
							}),
							new Ext.grid.Panel({
								id:"MinitalkCache",
								border:false,
								iconCls:"xi xi-shipping",
								title:Admin.getText("resource/cache/title"),
								tbar:[
									Admin.searchField("MinitalkCacheKeyword",200,Admin.getText("resource/cache/columns/name"),function(keyword) {
										if (keyword.length > 0) {
											Ext.getCmp("MinitalkCache").getStore().filter(function(record) {
												return record.data.name.indexOf(keyword) > -1;
											});
										} else {
											Ext.getCmp("MinitalkCache").getStore().clearFilter();
										}
									}),
									"-",
									new Ext.Button({
										iconCls:"mi mi-trash",
										text:Admin.getText("resource/cache/delete_selected"),
										handler:function() {
											Admin.resource.cache.delete();
										}
									})
								],
								store:new Ext.data.JsonStore({
									proxy:{
										type:"ajax",
										simpleSortMode:true,
										url:Minitalk.getProcessUrl("@getCaches"),
										reader:{type:"json"}
									},
									remoteSort:true,
									sorters:[{property:"create_date",direction:"DESC"}],
									autoLoad:true,
									pageSize:0,
									fields:["name","type","language","capacity",{name:"size",type:"int"},"path",{name:"create_date",type:"int"}],
									listeners:{
										load:function(store,records,success,e) {
											if (success == false) {
												if (e.getError()) {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												} else {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_LOAD_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												}
											}
										}
									}
								}),
								columns:[{
									text:Admin.getText("resource/cache/columns/name"),
									dataIndex:"name",
									width:250,
									sortable:true
								},{
									text:Admin.getText("resource/cache/columns/type"),
									dataIndex:"type",
									width:120,
									align:"center",
									sortable:true,
									renderer:function(value) {
										return Admin.getText("resource/cache/type/" + value);
									}
								},{
									text:Admin.getText("resource/cache/columns/language"),
									dataIndex:"language",
									width:80,
									align:"center",
									sortable:true
								},{
									text:Admin.getText("resource/cache/columns/capacity"),
									dataIndex:"capacity",
									width:120,
									sortable:true,
									renderer:function(value) {
										if (value.indexOf("channel.box.") === 0) {
											return Admin.getText("resource/cache/capacity/channel.box") + "("+value.split(".").pop()+")";
										} else {
											return Admin.getText("resource/cache/capacity/" + value);
										}
										
									}
								},{
									text:Admin.getText("resource/cache/columns/size"),
									dataIndex:"size",
									width:100,
									sortable:true,
									align:"right",
									renderer:function(value) {
										return Minitalk.getFileSize(value);
									}
								},{
									text:Admin.getText("resource/cache/columns/path"),
									dataIndex:"path",
									minWidth:200,
									flex:1,
									sortable:true
								},{
									text:Admin.getText("resource/cache/columns/create_date"),
									dataIndex:"create_date",
									width:145,
									sortable:true,
									align:"center",
									renderer:function(value) {
										return moment(value * 1000).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm");
									}
								}],
								selModel:new Ext.selection.CheckboxModel(),
								bbar:[
									new Ext.Button({
										iconCls:"x-tbar-loading",
										handler:function() {
											Ext.getCmp("MinitalkCache").getStore().reload();
										}
									}),
									"->",
									{xtype:"tbtext",text:Admin.getText("resource/attachment/grid_help")}
								],
								listeners:{
									itemdblclick:function(grid,record) {
									},
									itemcontextmenu:function(grid,record,item,index,e) {
										var menu = new Ext.menu.Menu();
										
										menu.addTitle(record.data.name);
										
										menu.add({
											text:Admin.getText("resource/cache/delete"),
											iconCls:"mi mi-trash",
											handler:function() {
												Admin.resource.cache.delete();
											}
										});
										
										e.stopEvent();
										menu.showAt(e.getXY());
									}
								}
							}),
							new Ext.grid.Panel({
								id:"MinitalkComponent",
								border:false,
								iconCls:"mi mi-minitalk",
								title:Admin.getText("resource/component/title"),
								tbar:[
									Admin.searchField("MinitalkComponentKeyword",200,Admin.getText("resource/attachment/columns/name"),function(keyword) {
										if (keyword.length > 0) {
											Ext.getCmp("MinitalkComponent").getStore().filter(function(record) {
												return (record.data.title != null && record.data.title.toString().indexOf(keyword) > -1) || (record.data.id != null && record.data.id.toString().indexOf(keyword) > -1);
											});
										} else {
											Ext.getCmp("MinitalkComponent").getStore().clearFilter();
										}
									})
								],
								store:new Ext.data.JsonStore({
									proxy:{
										type:"ajax",
										simpleSortMode:true,
										url:Minitalk.getProcessUrl("@getComponents"),
										reader:{type:"json"}
									},
									remoteSort:true,
									sorters:[{property:"title",direction:"ASC"}],
									autoLoad:true,
									pageSize:0,
									groupField:"type",
									groupDir:"ASC",
									fields:["title","version"],
									listeners:{
										load:function(store,records,success,e) {
											if (success == false) {
												if (e.getError()) {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												} else {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_LOAD_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												}
											}
										}
									}
								}),
								columns:[{
									text:Admin.getText("resource/component/columns/title"),
									width:180,
									dataIndex:"title",
									renderer:function(value,p,record) {
										return '<i class="icon '+record.data.icon+'"></i>'+value;
									},
									summaryType:"count",
									summaryRenderer:function(value) {
										return value + "EA";
									}
								},{
									text:Admin.getText("resource/component/columns/version"),
									width:60,
									dataIndex:"version",
									align:"center"
								},{
									text:Admin.getText("resource/component/columns/id"),
									width:250,
									dataIndex:"id"
								},{
									text:Admin.getText("resource/component/columns/description"),
									minWidth:200,
									flex:1,
									dataIndex:"description"
								},{
									text:Admin.getText("resource/component/columns/author"),
									width:180,
									dataIndex:"author",
									renderer:function(value,p,record) {
										var sHTML = value;
										if (record.data.email) sHTML+= '(<a href=mailto:"' + record.data.email + '">' + record.data.email + '</a>)';
										
										return sHTML;
									}
								},{
									text:Admin.getText("resource/component/columns/path"),
									width:250,
									dataIndex:"path"
								}],
								selModel:new Ext.selection.CheckboxModel(),
								features:[{
									ftype:"groupingsummary",
									groupHeaderTpl:'<tpl>{[values.children[0].data.type_name]}</tpl>',
									hideGroupedHeader:false,
									enableGroupingMenu:false
								}],
								bbar:[
									new Ext.Button({
										iconCls:"x-tbar-loading",
										handler:function() {
											Ext.getCmp("MinitalkComponent").getStore().reload();
										}
									}),
									"->",
									{xtype:"tbtext",text:Admin.getText("resource/attachment/grid_help")}
								],
								listeners:{
									itemdblclick:function(grid,record) {
									},
									itemcontextmenu:function(grid,record,item,index,e) {
									}
								}
							})
						]
					}),
					new Ext.grid.Panel({
						id:"MinitalkPanel-banip",
						tbar:[
							Admin.searchField("MinitalkIpKeyword",200,Admin.getText("banip/columns/ip") + " / " + Admin.getText("banip/columns/nickname"),function(keyword) {
								Ext.getCmp("MinitalkPanel-banip").getStore().getProxy().setExtraParam("keyword",keyword);
								Ext.getCmp("MinitalkPanel-banip").getStore().loadPage(1);
							}),
							"-",
							new Ext.Button({
								text:Admin.getText("banip/add"),
								iconCls:"mi mi-plus",
								handler:function() {
									Admin.banip.add();
								}
							}),
							new Ext.Button({
								text:Admin.getText("banip/delete"),
								iconCls:"mi mi-trash",
								handler:function() {
									Admin.banip.delete();
								}
							})
						],
						store:new Ext.data.JsonStore({
							proxy:{
								type:"ajax",
								simpleSortMode:true,
								url:Minitalk.getProcessUrl("@getBanIps"),
								reader:{type:"json"}
							},
							remoteSort:true,
							sorters:[{property:"reg_date",direction:"DESC"}],
							autoLoad:true,
							pageSize:50,
							fields:["ip","nickname","memo",{name:"reg_date",type:"int"}],
							listeners:{
								load:function(store,records,success,e) {
									if (success == false) {
										if (e.getError()) {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										} else {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_LOAD_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										}
									}
								}
							}
						}),
						columns:[{
							header:Admin.getText("banip/columns/ip"),
							dataIndex:"ip",
							width:140
						},{
							header:Admin.getText("banip/columns/nickname"),
							dataIndex:"nickname",
							width:200
						},{
							header:Admin.getText("banip/columns/memo"),
							dataIndex:"memo",
							minWidth:200,
							flex:1
						},{
							header:Admin.getText("banip/columns/reg_date"),
							dataIndex:"reg_date",
							width:140,
							renderer:function(value) {
								return moment(value * 1000).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm");
							}
						}],
						selModel:new Ext.selection.CheckboxModel(),
						bbar:new Ext.PagingToolbar({
							store:null,
							displayInfo:false,
							items:[
								"->",
								{xtype:"tbtext",text:Admin.getText("banip/grid_help")}
							],
							listeners:{
								beforerender:function(tool) {
									tool.bindStore(tool.ownerCt.getStore());
								}
							}
						}),
						listeners:{
							itemdblclick:function(grid,record) {
								Admin.banip.add(record.data.ip);
							},
							itemcontextmenu:function(grid,record,item,index,e) {
								var menu = new Ext.menu.Menu();
								
								menu.addTitle(record.data.ip);
								
								menu.add({
									text:Admin.getText("banip/modify"),
									iconCls:"xi xi-form",
									handler:function() {
										Admin.banip.add(record.data.ip);
									}
								});
								
								menu.add({
									text:Admin.getText("banip/delete"),
									iconCls:"mi mi-trash",
									handler:function() {
										Admin.banip.delete();
									}
								});
								
								e.stopEvent();
								menu.showAt(e.getXY());
							}
						}
					}),
					<?php if ($hasServer == true) { ?>
					new Ext.TabPanel({
						id:"MinitalkPanel-log",
						tabPosition:"bottom",
						items:[
							new Ext.Panel({
								id:"MinitalkHistory",
								title:Admin.getText("log/history/title"),
								iconCls:"xi xi-message",
								autoScroll:true,
								border:false,
								tbar:[
									new Ext.Button({
										iconCls:"fa fa-caret-left",
										handler:function() {
											var date = Ext.getCmp("MinitalkHistoryDate").getValue();
											var move = moment(date).add(-1,"day");
											Ext.getCmp("MinitalkHistoryDate").setValue(move.format("YYYY-MM-DD"));
										}
									}),
									new Ext.form.DateField({
										id:"MinitalkHistoryDate",
										format:"Y-m-d",
										width:115,
										value:moment().format("YYYY-MM-DD"),
										listeners:{
											change:function(form,value) {
												var current = moment(value);
												if (current.isValid() == true) {
													Ext.getCmp("MinitalkHistory").store.getProxy().setExtraParam("date",current.format("YYYY-MM-DD"));
													Ext.getCmp("MinitalkHistory").store.loadPage(1);
												}
											}
										}
									}),
									new Ext.Button({
										iconCls:"fa fa-caret-right",
										handler:function() {
											var date = Ext.getCmp("MinitalkHistoryDate").getValue();
											var move = moment(date).add(1,"day");
											Ext.getCmp("MinitalkHistoryDate").setValue(move.format("YYYY-MM-DD"));
										}
									}),
									"-",
									Admin.searchField("MinitalkHistoryChannel",150,Admin.getText("log/history/channel"),function(keyword) {
										Ext.getCmp("MinitalkHistory").store.getProxy().setExtraParam("channel",keyword);
										Ext.getCmp("MinitalkHistory").store.loadPage(1);
									}),
									Admin.searchField("MinitalkHistoryNickname",150,Admin.getText("log/history/nickname"),function(keyword) {
										Ext.getCmp("MinitalkHistory").store.getProxy().setExtraParam("nickname",keyword);
										Ext.getCmp("MinitalkHistory").store.loadPage(1);
									}),
									Admin.searchField("MinitalkHistoryKeyword",150,Admin.getText("log/history/keyword"),function(keyword) {
										Ext.getCmp("MinitalkHistory").store.getProxy().setExtraParam("keyword",keyword);
										Ext.getCmp("MinitalkHistory").store.loadPage(1);
									})
								],
								store:new Ext.data.JsonStore({
									proxy:{
										type:"ajax",
										simpleSortMode:true,
										url:Minitalk.getProcessUrl("@getHistory"),
										extraParams:{date:moment().format("YYYY-MM-DD")},
										reader:{type:"json"}
									},
									remoteSort:true,
									sorters:[{property:"time",direction:"ASC"}],
									pageSize:50,
									fields:["user","time","channel","nickname","message","ip"],
									listeners:{
										load:function(store,records,success,e) {
											if (success == true) {
												$("#MinitalkHistoryTotalRows").html(Ext.util.Format.number(store.getTotalCount(),"0,000"));
												
												var $panel = $("#MinitalkHistory-innerCt");
												$panel.empty();
												for (var i=0, loop=store.getCount();i<loop;i++) {
													var item = store.getAt(i).data;
													
													var $item = $("<div>");
													$item.css("padding","5px");
													
													var $time = $("<time>");
													$time.css("color","#666").css("paddingRight","5px");
													$time.html("[" + moment(item.time).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm:ss") + "]");
													$item.append($time);
													
													var $channel = $("<u>");
													$channel.css("fontWeight","bold").css("color","#2196F3").css("paddingRight","5px");
													$channel.html("#" + item.room);
													$item.append($channel);
													
													var $photo = $("<i>").addClass("message photo");
													if (item.user.photo) $photo.css("backgroundImage","url(" + item.user.photo + ");");
													$item.append($photo);
													
													if (item.to == null) {
														var $user = $("<b>");
														$user.html(item.nickname);
														$item.append($user);
													} else {
														var $user = $("<b>").css("color","#ff00ff");
														$user.html(item.nickname + ' <i class="mi mi-right"></i> ' + item.to.nickname);
														$item.append($user);
													}
													
													var $message = $("<span>");
													if (item.to !== null) $message.css("color","#ff00ff");
													if (item.type == "message") {
														$message.html(' : ' + item.message);
													} else if (item.type == "file") {
														$message.html(' : <i class="mi mi-download"></i> <a href="' + item.data.download + '">' + item.message + ' (' + Minitalk.getFileSize(item.data.size) + ')</a>');
													}
													$item.append($message);
													
													var $ip = $("<label>");
													$ip.css("color","#999").css("fontFamily","OpenSans").css("fontSize","11px");
													$ip.html(" (" + item.ip + ")");
													$item.append($ip);
													
													$panel.append($item);
												}
											} else {
												if (e.getError()) {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												} else {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_LOAD_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												}
											}
											
											setTimeout(function() { Ext.getCmp("MinitalkHistory").scrollTo(0,0); },100);
										}
									}
								}),
								html:'',
								bbar:new Ext.PagingToolbar({
									store:null,
									displayInfo:false,
									listeners:{
										beforerender:function(tool) {
											tool.bindStore(tool.ownerCt.store);
										}
									}
								}),
								listeners:{
									render:function() {
										if (Ext.getCmp("MinitalkHistory").store.isLoaded() == false && Ext.getCmp("MinitalkHistory").store.isLoading() == false) {
											Ext.getCmp("MinitalkHistory").store.loadPage(1);
										}
									}
								}
							}),
							new Ext.Panel({
								id:"MinitalkServerLog",
								title:Admin.getText("log/server/title"),
								iconCls:"xi xi-lineheight-plus",
								autoScroll:true,
								border:false,
								tbar:[
									new Ext.Button({
										iconCls:"fa fa-caret-left",
										handler:function() {
											var date = Ext.getCmp("MinitalkServerLogDate").getValue();
											var move = moment(date).add(-1,"day");
											Ext.getCmp("MinitalkServerLogDate").setValue(move.format("YYYY-MM-DD"));
										}
									}),
									new Ext.form.DateField({
										id:"MinitalkServerLogDate",
										format:"Y-m-d",
										width:115,
										value:moment().format("YYYY-MM-DD"),
										listeners:{
											change:function(form,value) {
												var current = moment(value);
												if (current.isValid() == true) {
													Ext.getCmp("MinitalkServerLog").store.getProxy().setExtraParam("date",current.format("YYYY-MM-DD"));
													if (Ext.getCmp("MinitalkServerLogDomain").getValue()) Ext.getCmp("MinitalkServerLog").store.reload();
												}
											}
										}
									}),
									new Ext.Button({
										iconCls:"fa fa-caret-right",
										handler:function() {
											var date = Ext.getCmp("MinitalkServerLogDate").getValue();
											var move = moment(date).add(1,"day");
											Ext.getCmp("MinitalkServerLogDate").setValue(move.format("YYYY-MM-DD"));
										}
									}),
									new Ext.form.ComboBox({
										id:"MinitalkServerLogDomain",
										store:new Ext.data.JsonStore({
											proxy:{
												type:"ajax",
												url:Minitalk.getProcessUrl("@getServers"),
												extraParams:{type:"SERVER",is_all:"true"},
												reader:{type:"json"}
											},
											autoLoad:false,
											remoteSort:false,
											sorters:[{property:"domain",direction:"ASC"}],
											fields:["domain"],
											listeners:{
												load:function(store) {
													if (!Ext.getCmp("MinitalkServerLogDomain").getValue() && store.getCount() > 0) {
														Ext.getCmp("MinitalkServerLogDomain").setValue(store.getAt(0).get("domain"));
													}
												}
											}
										}),
										width:240,
										editable:false,
										matchFieldWidth:false,
										listConfig:{
											minWidth:300
										},
										displayField:"domain",
										valueField:"domain",
										value:null,
										listeners:{
											change:function(form,value) {
												if (value) {
													Ext.getCmp("MinitalkServerLog").store.getProxy().setExtraParam("server",value);
													Ext.getCmp("MinitalkServerLog").store.reload();
												}
											}
										}
									})
								],
								store:new Ext.data.JsonStore({
									proxy:{
										type:"ajax",
										simpleSortMode:true,
										url:Minitalk.getProcessUrl("@getLogs"),
										extraParams:{date:moment().format("YYYY-MM-DD")},
										reader:{type:"json"}
									},
									remoteSort:true,
									sorters:[{property:"time",direction:"ASC"}],
									pageSize:0,
									fields:[],
									listeners:{
										load:function(store,records,success,e) {
											if (success == true) {
												var $panel = $("#MinitalkServerLog-innerCt");
												$panel.empty();
												for (var i=0, loop=store.getCount();i<loop;i++) {
													var log = store.getAt(i).data;
													var $item = $("<pre>").css("fontFamily","monospace").css("fontSize","12px").css("margin","5px").html(log);
													
													$panel.append($item);
												}
											} else {
												if (e.getError()) {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												} else {
													Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_LOAD_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
												}
											}
											
											Ext.getCmp("MinitalkServerLog").scrollTo(0,0);
										}
									}
								}),
								html:'',
								bbar:[
									new Ext.Button({
										iconCls:"x-tbar-loading",
										handler:function() {
											Ext.getCmp("MinitalkServerLog").store.reload();
										}
									})
								],
								listeners:{
									render:function() {
										Ext.getCmp("MinitalkServerLogDomain").getStore().load();
									}
								}
							})
						]
					}),
					new Ext.grid.Panel({
						id:"MinitalkPanel-broadcast",
						tbar:[
							Admin.searchField("MinitalkBroadcastKeyword",200,Admin.getText("broadcast/columns/message"),function(keyword) {
								Ext.getCmp("MinitalkPanel-broadcast").getStore().getProxy().setExtraParam("keyword",keyword);
								Ext.getCmp("MinitalkPanel-broadcast").getStore().loadPage(1);
							}),
							"-",
							new Ext.Button({
								text:Admin.getText("broadcast/send"),
								iconCls:"xi xi-paper-plane",
								handler:function() {
									Admin.broadcast.send();
								}
							}),
							new Ext.Button({
								text:Admin.getText("broadcast/delete"),
								iconCls:"mi mi-trash",
								handler:function() {
									Admin.broadcast.delete();
								}
							})
						],
						store:new Ext.data.JsonStore({
							proxy:{
								type:"ajax",
								simpleSortMode:true,
								url:Minitalk.getProcessUrl("@getBroadcasts"),
								reader:{type:"json"}
							},
							remoteSort:true,
							sorters:[{property:"reg_date",direction:"DESC"}],
							autoLoad:true,
							pageSize:50,
							fields:["id","type","message","nickname","url",{name:"receiver",type:"int"},{name:"reg_date",type:"int"}],
							listeners:{
								load:function(store,records,success,e) {
									if (success == false) {
										if (e.getError()) {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										} else {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_LOAD_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										}
									}
								}
							}
						}),
						columns:[{
							header:Admin.getText("broadcast/columns/type"),
							dataIndex:"type",
							width:100,
							align:"center",
							renderer:function(value,p) {
								if (value == "NOTICE") p.style = "color:red;";
								else p.style = "color:blue;";
								return Admin.getText("broadcast/type/"+value);
							}
						},{
							header:Admin.getText("broadcast/columns/channel"),
							dataIndex:"channel",
							width:100
						},{
							header:Admin.getText("broadcast/columns/message"),
							dataIndex:"message",
							minWidth:100,
							flex:1,
							renderer:function(value,p,record) {
								if (record.data.type == "NOTICE") return value;
								
								var sHTML = "";
								if (value.user.photo) {
									sHTML+= '<i class="message photo" style="background-image:url(' + value.user.photo + ');"></i>';
								} else {
									sHTML+= '<i class="message photo"></i>';
								}
								sHTML+= "<b>" + value.user.nickname + "</b> : " + value.message;
								return sHTML;
							}
						},{
							header:Admin.getText("broadcast/columns/url"),
							dataIndex:"url",
							width:250
						},{
							header:Admin.getText("broadcast/columns/receiver"),
							dataIndex:"receiver",
							width:100,
							align:"right",
							renderer:function(value,p,record) {
								if (record.data.type == "NOTICE") return Ext.util.Format.number(value,"0,000");
								return "UNKNOWN";
							}
						},{
							header:Admin.getText("broadcast/columns/reg_date"),
							dataIndex:"reg_date",
							width:160,
							align:"center",
							renderer:function(value) {
								return moment(value * 1000).locale($("html").attr("lang")).format("YYYY.MM.DD(dd) HH:mm:ss");
							}
						}],
						selModel:new Ext.selection.CheckboxModel(),
						bbar:new Ext.PagingToolbar({
							store:null,
							displayInfo:false,
							items:[
								"->",
								{xtype:"tbtext",text:Admin.getText("broadcast/grid_help")}
							],
							listeners:{
								beforerender:function(tool) {
									tool.bindStore(tool.ownerCt.getStore());
								}
							}
						}),
						listeners:{
							itemdblclick:function(grid,record) {
								Admin.broadcast.send(record.data);
							},
							itemcontextmenu:function(grid,record,item,index,e) {
								var menu = new Ext.menu.Menu();
								
								menu.addTitle(record.data.message);
								
								menu.add({
									text:Admin.getText("broadcast/resend"),
									iconCls:"xi xi-reply",
									handler:function() {
										Admin.broadcast.send(record.data);
									}
								});
								
								menu.add({
									text:Admin.getText("broadcast/delete"),
									iconCls:"mi mi-trash",
									handler:function() {
										Admin.broadcast.delete();
									}
								});
								
								e.stopEvent();
								menu.showAt(e.getXY());
							}
						}
					}),
					<?php } ?>
					null
				],
				listeners:{
					render:function(tab) {
						tab.getTabBar().setVisible(false);
					},
					afterRender:function(tabs) {
						if (Ext.getCmp("MinitalkTabPanel").getActiveTab().getId() == "MinitalkPanel-<?php echo $current; ?>") {
							tabs.fireEvent("tabchange",tabs,Ext.getCmp("MinitalkTabPanel").getActiveTab());
						} else {
							Ext.getCmp("MinitalkTabPanel").setActiveTab("MinitalkPanel-<?php echo $current; ?>");
						}
					},
					tabchange:function(tabs,tab) {
						var panel = tab.getId().split("-").pop();
						$("#MinitalkHeader li.selected").removeClass("selected");
						$("#MinitalkHeader button[data-tab="+panel+"]").parent().addClass("selected");
						
						var title = $("#MinitalkHeader button[data-tab="+panel+"]").text() + " - " + $("title").text().split(" - ").pop();
						history.pushState({panel:panel},title,location.href.split("/admin").shift() + "/admin/" + panel);
						document.title = title;
					}
				}
			}),
			new Ext.Panel({
				id:"MinitalkFooterPanel",
				region:"south",
				height:25,
				border:false,
				contentEl:"MinitalkFooter"
			})
		],
		listeners:{
			afterRender:function() {
				$(document).triggerHandler("MinitalkAdminReady");
			},
			resize:function() {
				var $header = $("#MinitalkHeader");
				var $menus = $("ul[data-role=menu]",$header);
				var $items = $("> li",$menus);
				var $lists = $("> li[data-role=more] > ul",$menus);
				$lists.empty();
				$items.show();
				
				var count = 0;
				var limitWidth = $menus.innerWidth();
				var currentWidth = 0;
				var is_more = false;
				$items.each(function() {
					var width = $(this).outerWidth();
					if (currentWidth + width + 40 > limitWidth) {
						is_more = true;
						return false;
					}
					
					currentWidth+= width;
					count++;
				});
				
				if (is_more == true) {
					for (var i=loop=$items.length - 1;i>=Math.max(1,count);i--) {
						var $menu = $items.eq(i).clone(true);
						
						$lists.prepend($menu);
						$items.eq(i).hide();
					}
				} else {
					$items.eq(0).hide();
				}
			}
		}
	}).updateLayout();
	
	try {
		document.fonts.ready.then(function() {
			setTimeout(function() { Ext.getCmp("AdminViewport").updateLayout(); Ext.getCmp("AdminViewport").fireEvent("resize"); },1000);
		});
	} catch (e) {}
});

$(document).ready(function() {
	$("#MinitalkHeader ul[data-role=menu] > li[data-role=more] > button").on("click",function(e) {
		$(this).parent().toggleClass("on");
		e.stopPropagation();
	});
});

$(document).on("click",function() {
	$("#MinitalkHeader ul[data-role=menu] > li[data-role=more]").removeClass("on");
});
</script>
<iframe name="downloadFrame" style="display:none;"></iframe>
<?php } ?>
</body>
</html>
