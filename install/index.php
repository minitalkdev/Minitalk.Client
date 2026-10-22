<?php
/**
 * 이 파일은 미니톡 클라이언트의 일부입니다. (https://www.minitalk.io)
 *
 * 미니톡 설치화면을 구성한다.
 * 
 * @file /install/index.php
 * @license MIT License
 * @modified 2025. 2. 7.
 */
REQUIRE_ONCE str_replace('/install','',str_replace(DIRECTORY_SEPARATOR,'/',__DIR__)).'/configs/init.config.php';

$package = json_decode(file_get_contents(__MINITALK_PATH__.'/package.json'));
$language = Request('language') ? Request('language') : 'en';
$acceptLanguage = substr((($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en') ?? 'en'),0,2);
?>
<!DOCTYPE HTML>
<html lang="<?php echo $language; ?>" data-lang="<?php echo $acceptLanguage; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=800">
<title>Minitalk Install - <?php echo $package->title; ?></title>
<link rel="stylesheet" href="//www.minitalk.io/modules/moimz/styles/install.css" type="text/css">
<link rel="shortcut icon" type="image/x-icon" href="//www.minitalk.io/modules/moimz/images/favicon.ico">
<?php if ($language != 'en') { ?>
<link rel="stylesheet" href="//www.minitalk.io/modules/moimz/styles/install.<?php echo $language; ?>.css" type="text/css">
<?php } ?>
<script src="//www.minitalk.io/modules/moimz/scripts/jquery.js"></script>
<script src="//www.minitalk.io/modules/moimz/scripts/install.js"></script>
</head>
<body>

<input type="hidden" id="language" value="<?php echo $language; ?>">
<div class="selectControl" data-field="language">
	<button type="button">Select Language <span class="arrow"></span></button>
</div>

<header>
	<h1>Minitalk Install <small>From minitalk.io</small></h1>
</header>

<main data-start="license" data-id="<?php echo $package->id; ?>" data-version="<?php echo $package->version; ?>">
	<section>
		<article>
			<h2 style="background-image:url(//www.minitalk.io/modules/moimz/images/<?php echo $package->title; ?>.png);"><?php echo $package->title; ?> <small>v.<?php echo $package->version; ?></small></h2>
			
			<article data-step="license">
				<p></p>
				
				<label><input type="checkbox"> <span data-language="agree"></span></label>
			</article>
			
			<article data-step="check" data-url="./process/index.php">
				<button type="button" onclick="Step('check');"><i class="mi mi-refresh-bold"></i> <span data-language="dependencyRefresh"></span></button>
				<ul>
					<li data-dependency="latest"></li>
					<?php foreach ($package->dependencies as $dependency=>$version) { ?>
					<li data-dependency="<?php echo $dependency; ?>" data-version="<?php echo $version; ?>"></li>
					<?php } ?>
					<?php foreach ($package->requirements as $requirement=>$version) { ?>
					<li data-requirement="<?php echo $requirement; ?>" data-version="<?php echo $version; ?>"></li>
					<?php } ?>
					<?php foreach ($package->directories as $directory=>$permission) { ?>
					<li data-directory="<?php echo $directory; ?>" data-permission="<?php echo $permission; ?>"></li>
					<?php } ?>
					<?php foreach ($package->configs as $config) { ?>
					<li data-config="<?php echo $config; ?>"></li>
					<?php } ?>
					<?php if (isset($package->preset) == true && $package->preset) { ?>
					<li data-preset="<?php echo $package->preset; ?>"></li>
					<?php } ?>
				</ul>
			</article>
			
			<article data-step="insert">
				<form onsubmit="return false;">
					<input type="hidden" name="language" value="<?php echo $language; ?>">
					
					<div data-role="input">
						<label data-language="key"></label>
						<input type="text" name="key" class="inputControl">
						<div class="helpBlock" data-config="key" data-language="key_help" data-language-exists="key_exists_help" data-language-preset="key_preset_help"></div>
					</div>
					
					<hr>
					
					<div data-role="input">
						<label data-language="db_host"></label>
						<input type="text" name="db_host" class="inputControl" value="localhost">
					</div>
					
					<div data-role="input">
						<label data-language="db_port"></label>
						<input type="text" name="db_port" class="inputControl" value="3306">
					</div>
					
					<div data-role="input">
						<label data-language="db_id"></label>
						<input type="text" name="db_id" class="inputControl">
					</div>
					
					<div data-role="input">
						<label data-language="db_password"></label>
						<input type="text" name="db_password" class="inputControl">
					</div>
					
					<div data-role="input">
						<label data-language="db_name"></label>
						<input type="text" name="db_name" class="inputControl">
						<div class="helpBlock" data-config="db" data-language="db_help" data-language-exists="db_exists_help" data-language-preset="db_preset_help"></div>
					</div>
					
					<hr>
					
					<div data-role="input">
						<label data-language="admin_id"></label>
						<input type="text" name="admin_id" class="inputControl">
						<div class="helpBlock"></div>
					</div>
					
					<div data-role="input">
						<label data-language="admin_password"></label>
						<input type="text" name="admin_password" class="inputControl">
						<div class="helpBlock"></div>
					</div>
					
					<div data-role="input">
						<label data-language="admin_nickname"></label>
						<input type="text" name="admin_nickname" class="inputControl">
						<div class="helpBlock"></div>
					</div>
				</form>
			</article>
			
			<article data-step="complete">
				<h3 data-language="complete"></h3>
				
				<p data-language="go_admin"></p>
			</article>
		</article>
		
		<nav>
			<ul>
				<li data-step="license" data-next="check"></li>
				<li data-step="check" data-prev="license" data-next="insert"></li>
				<li data-step="insert" data-prev="check" data-action="./process/index.php?action=install" data-next="complete"></li>
				<li data-step="complete" data-link="../admin/index.php"></li>
			</ul>
			
			<button type="button" data-role="prev"><i class="mi mi-back-bold"></i><span data-language="back"></span></button>
			<button type="submit" data-role="next"><span data-language="continue"></span><i class="mi mi-go-bold"></i></button>
		</nav>
	</section>
</main>

</body>
</html>