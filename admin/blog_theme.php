<?php
# -- BEGIN LICENSE BLOCK ---------------------------------------
#
# This file is part of Dotclear 2.
#
# Copyright (c) 2003-2013 Olivier Meunier & Association Dotclear
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK -----------------------------------------

require dirname(__FILE__).'/../inc/admin/prepend.php';

dcPage::check('admin');

# Loading themes
$core->themes = new dcThemes($core);
$core->themes->loadModules($core->blog->themes_path,null);

# Theme screenshot
if (!empty($_GET['shot']) && $core->themes->moduleExists($_GET['shot']))
{
	if (empty($_GET['src'])) {
		$f = $core->blog->themes_path.'/'.$_GET['shot'].'/screenshot.jpg';
	} else {
		$f = $core->blog->themes_path.'/'.$_GET['shot'].'/'.path::clean($_GET['src']);
	}
	
	$f = path::real($f);
	
	if (!file_exists($f)) {
		$f = dirname(__FILE__).'/images/noscreenshot.png';
	}
	
	http::cache(array_merge(array($f),get_included_files()));
	
	header('Content-Type: '.files::getMimeType($f));
	header('Content-Length: '.filesize($f));
	readfile($f);
	
	exit;
}

$can_install = $core->auth->isSuperAdmin();
$is_writable = is_dir($core->blog->themes_path) && is_writable($core->blog->themes_path);
$default_tab = 'themes-list';

# Selecting theme
if (!empty($_POST['theme']) && !empty($_POST['select']) && empty($_REQUEST['conf']))
{
	$core->blog->settings->addNamespace('system');
	$core->blog->settings->system->put('theme',$_POST['theme']);
	$core->blog->triggerBlog();
	$theme = $core->themes->getModules($_POST['theme']);
	dcPage::addSuccessNotice(sprintf(
		__('Current theme has been successfully changed to "%s".'),
		html::escapeHTML($theme['name']))
	);

	http::redirect('blog_theme.php');
}

if ($can_install && !empty($_POST['theme']) && !empty($_POST['remove']) && empty($_REQUEST['conf']))
{
	try
	{
		if ($_POST['theme'] == 'default') {
			throw new Exception(__('You can\'t remove default theme.'));
		}
		
		if (!$core->themes->moduleExists($_POST['theme'])) {
			throw new Exception(__('Theme does not exist.'));
		}
		
		$theme = $core->themes->getModules($_POST['theme']);
		
		# --BEHAVIOR-- themeBeforeDelete
		$core->callBehavior('themeBeforeDelete',$theme);
		
		$core->themes->deleteModule($_POST['theme']);
		
		# --BEHAVIOR-- themeAfterDelete
		$core->callBehavior('themeAfterDelete',$theme);
		
		http::redirect('blog_theme.php');
	}
	catch (Exception $e)
	{
		$core->error->add($e->getMessage());
	}
}

# Theme upload
if ($can_install && $is_writable && ((!empty($_POST['upload_pkg']) && !empty($_FILES['pkg_file'])) ||
	(!empty($_POST['fetch_pkg']) && !empty($_POST['pkg_url']))))
{
	try
	{
		if (empty($_POST['your_pwd']) || !$core->auth->checkPassword(crypt::hmac(DC_MASTER_KEY,$_POST['your_pwd']))) {
			throw new Exception(__('Password verification failed'));
		}
		
		if (!empty($_POST['upload_pkg']))
		{
			files::uploadStatus($_FILES['pkg_file']);
			
			$dest = $core->blog->themes_path.'/'.$_FILES['pkg_file']['name'];
			if (!move_uploaded_file($_FILES['pkg_file']['tmp_name'],$dest)) {
				throw new Exception(__('Unable to move uploaded file.'));
			}
		}
		else
		{
			$url = urldecode($_POST['pkg_url']);
			$dest = $core->blog->themes_path.'/'.basename($url);
			
			try
			{
				$client = netHttp::initClient($url,$path);
				$client->setUserAgent('Dotclear - http://www.dotclear.org/');
				$client->useGzip(false);
				$client->setPersistReferers(false);
				$client->setOutput($dest);
				$client->get($path);
			}
			catch( Exception $e)
			{
				throw new Exception(__('An error occurred while downloading the file.'));
			}
			
			unset($client);
		}
		
		$ret_code = dcModules::installPackage($dest,$core->themes);
		if ($ret_code == 2) {
			dcPage::addSuccessNotice(__('Theme has been successfully upgraded.'));
		} else {
			dcPage::addSuccessNotice(__('Theme has been successfully installed.'));
		}
		http::redirect('blog_theme.php');
	}
	catch (Exception $e)
	{
		$core->error->add($e->getMessage());
		$default_tab = 'add-theme';
	}
}

$theme_conf_mode = false;
if (!empty($_REQUEST['conf']))
{
	$theme_conf_file = path::real($core->blog->themes_path.'/'.$core->blog->settings->system->theme).'/_config.php';
	if (file_exists($theme_conf_file)) {
		$theme_conf_mode = true;
	}
}

function display_theme_details($id,$details,$current)
{
	global $core;
	
	$screenshot = 'images/noscreenshot.png';
	if (file_exists($core->blog->themes_path.'/'.$id.'/screenshot.jpg')) {
		$screenshot = 'blog_theme.php?shot='.rawurlencode($id);
	}
	
	$radio_id = 'theme_'.html::escapeHTML($id);
	if (preg_match('#^http(s)?://#',$core->blog->settings->system->themes_url)) {
		$theme_url = http::concatURL($core->blog->settings->system->themes_url,'/'.$id);
	} else {
		$theme_url = http::concatURL($core->blog->url,$core->blog->settings->system->themes_url.'/'.$id);
	}
	$has_conf = file_exists(path::real($core->blog->themes_path.'/'.$id).'/_config.php');
	$has_css = file_exists(path::real($core->blog->themes_path.'/'.$id).'/style.css');
	$parent = $core->themes->moduleInfo($id,'parent');
	$has_parent = (boolean)$parent;
	if ($has_parent) {
		$is_parent_present = $core->themes->moduleExists($parent);
	}
	
	$res =
	'<div class="theme-details'.($current ? ' current-theme' : '').'">'.
	'<div class="theme-shot"><img src="'.$screenshot.'" alt="" /></div>'.
	'<div class="theme-info">'.
		'<h4>'.form::radio(array('theme',$radio_id),html::escapeHTML($id),$current,'','',($has_parent && !$is_parent_present)).' '.
		'<label class="classic" for="'.$radio_id.'">'.
		html::escapeHTML($details['name']).'</label></h4>'.
		'<p><span class="theme-desc">'.html::escapeHTML($details['desc']).'</span> '.
		'<span class="theme-author">'.sprintf(__('by %s'),html::escapeHTML($details['author'])).'</span> '.
		'<span class="theme-version">'.sprintf(__('version %s'),html::escapeHTML($details['version'])).'</span> ';
		if ($has_parent) {
			if ($is_parent_present) {
				$res .= '<span class="theme-parent-ok">'.sprintf(__('(built on "%s")'),html::escapeHTML($parent)).'</span> ';
			} else {
				$res .= '<span class="theme-parent-missing">'.sprintf(__('(requires "%s")'),html::escapeHTML($parent)).'</span> ';
			}
		}
		if ($has_css) {
			$res .= '<span class="theme-css"><a href="'.$theme_url.'/style.css">'.__('Stylesheet').'</a></span>';
		}
		$res .= '</p>';
	$res .=
	'</div>'.
	'<div class="theme-actions">';
		if ($current && $has_conf) {
			$res .= '<p><a href="blog_theme.php?conf=1" class="button">'.__('Configure theme').'</a></p>';
		}
		if ($current) {
			# --BEHAVIOR-- adminCurrentThemeDetails
			$res .= $core->callBehavior('adminCurrentThemeDetails',$core,$id,$details);
		}
	$res .=
	'</div>'.
	'</div>';
	
	return $res;
}

if (!$theme_conf_mode)
{
	$breadcrumb = dcPage::breadcrumb(
		array(
			html::escapeHTML($core->blog->name) => '',
			__('Blog appearance') => ''
		));
} else {
	$breadcrumb = dcPage::breadcrumb(
		array(
			html::escapeHTML($core->blog->name) => '',
			__('Blog appearance') => 'blog_theme.php',
			__('Theme configuration') => ''
		));
}

dcPage::open(__('Blog appearance'),
	(!$theme_conf_mode ? dcPage::jsLoad('js/_blog_theme.js') : '').
	dcPage::jsPageTabs($default_tab).
	dcPage::jsColorPicker(),
	$breadcrumb
);

if (!$theme_conf_mode)
{
	if (!empty($_GET['upd'])) {
		dcPage::success(__('Theme has been successfully changed.'));
	}
	
	if (!empty($_GET['added'])) {
		dcPage::success(($_GET['added'] == 2 ? __('Theme has been successfully upgraded') : __('Theme has been successfully installed.')));
	}
	
	if (!empty($_GET['del'])) {
		dcPage::success(__('Theme has been successfully deleted.'));
	}
	
	# Themes list
	echo '<div class="multi-part" id="themes-list" title="'.__('Themes').'">'.
	'<h3>'.__('Available themes in your installation').'</h3>';
	
	$themes = $core->themes->getModules();
	if (isset($themes[$core->blog->settings->system->theme])) {
		echo '<p>'.sprintf(__('You are currently using <strong>%s</strong>'),$themes[$core->blog->settings->system->theme]['name']).'.</p>';
	}
	
	echo
	'<form action="blog_theme.php" method="post" id="themes-form">'.
	'<div id="themes">';
	
	if (isset($themes[$core->blog->settings->system->theme])) {
		echo display_theme_details($core->blog->settings->system->theme,$themes[$core->blog->settings->system->theme],true);
	}
	
	foreach ($themes as $k => $v)
	{
		if ($core->blog->settings->system->theme == $k) { // Current theme
			continue;
		}
		echo display_theme_details($k,$v,false);
	}
	
	echo '</div>';
	
	echo
	'<div id="themes-actions">'.
	
	'<p>'.$core->formNonce().'<input type="submit" name="select" value="'.__('Use selected theme').'" /> ';	
	if ($can_install) {
		echo ' <input type="submit" class="delete" name="remove" value="'.__('Delete selected theme').'" />';
	}
	echo '</p>'.
	
	'</div>'.
	'</form>'.
	'</div>';
	
	# Add a new theme
	if ($can_install)
	{
		echo
		'<div class="multi-part clear" id="add-theme" title="'.__('Install or upgrade a theme').'">'.
		'<h3>'.__('Add themes to your installation').'</h3>'.
		'<p class="form-note info">'.sprintf(__('You can find additional themes for your blog on %s.'),
		'<a href="http://themes.dotaddict.org/galerie-dc2/">Dotaddict</a>').'</p>';
		
		if ($is_writable)
		{
			echo '<p>'.__('You can also install themes by uploading or downloading zip files.').'</p>';
			
			# 'Upload theme' form
			echo
			'<form method="post" action="blog_theme.php" id="uploadpkg" enctype="multipart/form-data" class="fieldset">'.
			'<h4>'.__('Upload a zip file').'</h4>'.
			'<p class="field"><label for="pkg_file" class="classic required"><abbr title="'.__('Required field').'">*</abbr> '.__('Theme zip file:').'</label> '.
			'<input type="file" name="pkg_file" id="pkg_file" /></p>'.
			'<p class="field"><label for="your_pwd1" class="classic required"><abbr title="'.__('Required field').'">*</abbr> '.__('Your password:').'</label> '.
			form::password(array('your_pwd','your_pwd1'),20,255).'</p>'.
			'<p><input type="submit" name="upload_pkg" value="'.__('Upload theme').'" />'.
			$core->formNonce().'</p>'.
			'</form>';
			
			# 'Fetch theme' form
			echo
			'<form method="post" action="blog_theme.php" id="fetchpkg" class="fieldset">'.
			'<h4>'.__('Download a zip file').'</h4>'.
			'<p class="field"><label for="pkg_url" class="classic required"><abbr title="'.__('Required field').'">*</abbr> '.__('Theme zip file URL:').'</label> '.
			form::field(array('pkg_url','pkg_url'),40,255).'</p>'.
			'<p class="field"><label for="your_pwd2" class="classic required"><abbr title="'.__('Required field').'">*</abbr> '.__('Your password:').'</label> '.
			form::password(array('your_pwd','your_pwd2'),20,255).'</p>'.
			'<p><input type="submit" name="fetch_pkg" value="'.__('Download theme').'" />'.
			$core->formNonce().'</p>'.
			'</form>';
		}
		else
		{
			echo
			'<p class="static-msg">'.
			__('To enable this function, please give write access to your themes directory.').
			'</p>';
		}
		echo '</div>';
	}
}
else
{
	$theme_name = $core->themes->moduleInfo($core->blog->settings->system->theme,'name');
	$core->themes->loadModuleL10Nresources($core->blog->settings->system->theme,$_lang);

	echo
	'<p><a class="back" href="blog_theme.php">'.__('Back to Blog appearance').'</a></p>';
	
	try
	{
		# Let theme configuration set their own form(s) if required
		$standalone_config = (boolean) $core->themes->moduleInfo($core->blog->settings->system->theme,'standalone_config');

		if (!$standalone_config)
			echo '<form id="theme_config" action="blog_theme.php?conf=1" method="post" enctype="multipart/form-data">';

		include $theme_conf_file;

		if (!$standalone_config)
			echo
			'<p class="clear"><input type="submit" value="'.__('Save').'" />'.
			$core->formNonce().'</p>'.
			'</form>';

	}
	catch (Exception $e)
	{
		echo '<div class="error"><p>'.$e->getMessage().'</p></div>';
	}
}

dcPage::close();
?>