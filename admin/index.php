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

if (!empty($_GET['pf'])) {
	require dirname(__FILE__).'/../inc/load_plugin_file.php';
	exit;
}

require dirname(__FILE__).'/../inc/admin/prepend.php';

if (!empty($_GET['default_blog'])) {
	try {
		$core->setUserDefaultBlog($core->auth->userID(),$core->blog->id);
		$core->adminurl->redirect("admin.home");
	} catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

dcPage::check('usage,contentadmin');

if ($core->plugins->disableDepModules($core->adminurl->get('admin.home',array()))) {
	exit;
}

# Logout
if (!empty($_GET['logout'])) {
	$core->session->destroy();
	if (isset($_COOKIE['dc_admin'])) {
		unset($_COOKIE['dc_admin']);
		setcookie('dc_admin',false,-600,'','',DC_ADMIN_SSL);
	}
	$core->adminurl->redirect("admin.auth");
	exit;
}

# Plugin install
$plugins_install = $core->plugins->installModules();

# Check dashboard module prefs
$ws = $core->auth->user_prefs->addWorkspace('dashboard');
if (!$core->auth->user_prefs->dashboard->prefExists('doclinks')) {
	if (!$core->auth->user_prefs->dashboard->prefExists('doclinks',true)) {
		$core->auth->user_prefs->dashboard->put('doclinks',true,'boolean','',null,true);
	}
	$core->auth->user_prefs->dashboard->put('doclinks',true,'boolean');
}
if (!$core->auth->user_prefs->dashboard->prefExists('dcnews')) {
	if (!$core->auth->user_prefs->dashboard->prefExists('dcnews',true)) {
		$core->auth->user_prefs->dashboard->put('dcnews',true,'boolean','',null,true);
	}
	$core->auth->user_prefs->dashboard->put('dcnews',true,'boolean');
}
if (!$core->auth->user_prefs->dashboard->prefExists('quickentry')) {
	if (!$core->auth->user_prefs->dashboard->prefExists('quickentry',true)) {
		$core->auth->user_prefs->dashboard->put('quickentry',false,'boolean','',null,true);
	}
	$core->auth->user_prefs->dashboard->put('quickentry',false,'boolean');
}

// Handle folded/unfolded sections in admin from user preferences
$ws = $core->auth->user_prefs->addWorkspace('toggles');
if (!$core->auth->user_prefs->toggles->prefExists('unfolded_sections')) {
	$core->auth->user_prefs->toggles->put('unfolded_sections','','string','Folded sections in admin',null,true);
}


# Dashboard icons
$__dashboard_icons = new ArrayObject();

$favs = $core->favs->getUserFavorites();
$core->favs->appendDashboardIcons($__dashboard_icons);

# Check plugins and themes update from repository
function dc_check_store_update($mod, $url, $img, $icon)
{
	$repo = new dcStore($mod, $url);
	$upd = $repo->get(true);
	if (!empty($upd)) {
		$icon[0] .= '<br />'.sprintf(__('An update is available', '%s updates are available.', count($upd)),count($upd));
		$icon[1] .= '#update';
		$icon[2] = 'images/menu/'.$img.'-b-update.png';
	}
}
if (isset($__dashboard_icons['plugins'])) {
	dc_check_store_update($core->plugins, $core->blog->settings->system->store_plugin_url, 'plugins', $__dashboard_icons['plugins']);
}
if (isset($__dashboard_icons['blog_theme'])) {
	$themes = new dcThemes($core);
	$themes->loadModules($core->blog->themes_path, null);
	dc_check_store_update($themes, $core->blog->settings->system->store_theme_url, 'blog-theme', $__dashboard_icons['blog_theme']);
}

# Latest news for dashboard
$__dashboard_items = new ArrayObject(array(new ArrayObject(),new ArrayObject()));

$dashboardItem = 0;

if ($core->auth->user_prefs->dashboard->dcnews) {
	try
	{
		if (empty($__resources['rss_news'])) {
			throw new Exception();
		}

		$feed_reader = new feedReader;
		$feed_reader->setCacheDir(DC_TPL_CACHE);
		$feed_reader->setTimeout(2);
		$feed_reader->setUserAgent('Dotclear - http://www.dotclear.org/');
		$feed = $feed_reader->parse($__resources['rss_news']);
		if ($feed)
		{
			$latest_news = '<div class="box medium dc-box"><h3>'.__('Dotclear news').'</h3><dl id="news">';
			$i = 1;
			foreach ($feed->items as $item)
			{
				$dt = isset($item->link) ? '<a href="'.$item->link.'" class="outgoing" title="'.$item->title.'">'.
					$item->title.' <img src="images/outgoing-blue.png" alt="" /></a>' : $item->title;

				if ($i < 3) {
					$latest_news .=
					'<dt>'.$dt.'</dt>'.
					'<dd><p><strong>'.dt::dt2str(__('%d %B %Y:'),$item->pubdate,'Europe/Paris').'</strong> '.
					'<em>'.text::cutString(html::clean($item->content),120).'...</em></p></dd>';
				} else {
					$latest_news .=
					'<dt>'.$dt.'</dt>'.
					'<dd>'.dt::dt2str(__('%d %B %Y:'),$item->pubdate,'Europe/Paris').'</dd>';
				}
				$i++;
				if ($i > 2) { break; }
			}
			$latest_news .= '</dl></div>';
			$__dashboard_items[$dashboardItem][] = $latest_news;
			$dashboardItem++;
		}
	}
	catch (Exception $e) {}
}

# Documentation links
if ($core->auth->user_prefs->dashboard->doclinks) {
	if (!empty($__resources['doc']))
	{
		$doc_links = '<div class="box small dc-box"><h3>'.__('Documentation and support').'</h3><ul>';

		foreach ($__resources['doc'] as $k => $v) {
			$doc_links .= '<li><a class="outgoing" href="'.$v.'" title="'.$k.'">'.$k.
			' <img src="images/outgoing-blue.png" alt="" /></a></li>';
		}

		$doc_links .= '</ul></div>';
		$__dashboard_items[$dashboardItem][] = $doc_links;
		$dashboardItem++;
	}
}

$core->callBehavior('adminDashboardItems', $core, $__dashboard_items);

# Dashboard content
$dashboardContents = '';
$__dashboard_contents = new ArrayObject(array(new ArrayObject,new ArrayObject));
$core->callBehavior('adminDashboardContents', $core, $__dashboard_contents);

# Editor stuff
$admin_post_behavior = '';
if ($core->auth->user_prefs->dashboard->quickentry) {
	if ($core->auth->check('usage,contentadmin',$core->blog->id))
	{
		$post_format = $core->auth->getOption('post_format');
		$post_editor = $core->auth->getOption('editor');
		if ($post_editor && !empty($post_editor[$post_format])) {
			// context is not post because of tags not available
			$admin_post_behavior = $core->callBehavior('adminPostEditor', $post_editor[$post_format], 'quickentry', array('#post_content'),$post_format);
		}
	}
}

/* DISPLAY
-------------------------------------------------------- */
dcPage::open(__('Dashboard'),
	dcPage::jsLoad('js/_index.js').
	$admin_post_behavior.
	# --BEHAVIOR-- adminDashboardHeaders
	$core->callBehavior('adminDashboardHeaders'),
	dcPage::breadcrumb(
		array(
		__('Dashboard').' : '.html::escapeHTML($core->blog->name) => ''
		),
		array('home_link' =>false)
	)
);

# Dotclear updates notifications
if ($core->auth->isSuperAdmin() && is_readable(DC_DIGESTS))
{
	$updater = new dcUpdate(DC_UPDATE_URL,'dotclear',DC_UPDATE_VERSION,DC_TPL_CACHE.'/versions');
	$new_v = $updater->check(DC_VERSION);
	$version_info = $new_v ? $updater->getInfoURL() : '';

	if ($updater->getNotify() && $new_v) {
		echo
		'<div class="dc-update"><h3>'.sprintf(__('Dotclear %s is available!'),$new_v).'</h3> '.
		'<p><a class="button submit" href="'.$core->adminurl->get("admin.update").'">'.sprintf(__('Upgrade now'),$new_v).'</a> '.
		'<a class="button" href="'.$core->adminurl->get("admin.update", array('hide_msg' => 1)).'">'.__('Remind me later').'</a>'.
		($version_info ? ' </p>'.
		'<p class="updt-info"><a href="'.$version_info.'">'.__('Information about this version').'</a>' : '').'</p>'.
		'</div>';
	}
}

if ($core->auth->getInfo('user_default_blog') != $core->blog->id && $core->auth->getBlogCount() > 1) {
	echo
	'<p><a href="'.$core->adminurl->get("admin.home",array('default_blog' => 1)).'" class="button">'.__('Make this blog my default blog').'</a></p>';
}

if ($core->blog->status == 0) {
	echo '<p class="static-msg">'.__('This blog is offline').'.</p>';
} elseif ($core->blog->status == -1) {
	echo '<p class="static-msg">'.__('This blog is removed').'.</p>';
}

if (!defined('DC_ADMIN_URL') || !DC_ADMIN_URL) {
	echo
	'<p class="static-msg">'.
	sprintf(__('%s is not defined, you should edit your configuration file.'),'DC_ADMIN_URL').
	' '.__('See <a href="http://dotclear.org/documentation/2.0/admin/config">documentation</a> for more information.').
	'</p>';
}

if (!defined('DC_ADMIN_MAILFROM') || !DC_ADMIN_MAILFROM) {
	echo
	'<p class="static-msg">'.
	sprintf(__('%s is not defined, you should edit your configuration file.'),'DC_ADMIN_MAILFROM').
	' '.__('See <a href="http://dotclear.org/documentation/2.0/admin/config">documentation</a> for more information.').
	'</p>';
}

$err = array();

# Check cache directory
if ( $core->auth->isSuperAdmin() ) {
	if (!is_dir(DC_TPL_CACHE) || !is_writable(DC_TPL_CACHE)) {
		$err[] = '<p>'.__("The cache directory does not exist or is not writable. You must create this directory with sufficient rights and affect this location to \"DC_TPL_CACHE\" in inc/config.php file.").'</p>';
	}
} else {
	if (!is_dir(DC_TPL_CACHE) || !is_writable(DC_TPL_CACHE)) {
		$err[] = '<p>'.__("The cache directory does not exist or is not writable. You should contact your administrator.").'</p>';
	}
}

# Check public directory
if ( $core->auth->isSuperAdmin() ) {
	if (!is_dir($core->blog->public_path) || !is_writable($core->blog->public_path)) {
		$err[] = '<p>'.__("There is no writable directory /public/ at the location set in about:config \"public_path\". You must create this directory with sufficient rights (or change this setting).").'</p>';
	}
} else {
	if (!is_dir($core->blog->public_path) || !is_writable($core->blog->public_path)) {
		$err[] = '<p>'.__("There is no writable root directory for the media manager. You should contact your administrator.").'</p>';
	}
}

# Error list
if (count($err) > 0) {
	echo '<div class="error"><p><strong>'.__('Error:').'</strong></p>'.
	'<ul><li>'.implode("</li><li>",$err).'</li></ul></div>';
}

# Plugins install messages
if (!empty($plugins_install['success']))
{
	echo '<div class="success">'.__('Following plugins have been installed:').'<ul>';
	foreach ($plugins_install['success'] as $k => $v) {
		echo '<li>'.$k.'</li>';
	}
	echo '</ul></div>';
}
if (!empty($plugins_install['failure']))
{
	echo '<div class="error">'.__('Following plugins have not been installed:').'<ul>';
	foreach ($plugins_install['failure'] as $k => $v) {
		echo '<li>'.$k.' ('.$v.')</li>';
	}
	echo '</ul></div>';
}
# Errors modules notifications
if ($core->auth->isSuperAdmin())
{
	$list = $core->plugins->getErrors();
	if (!empty($list)) {
		echo
		'<div class="error" id="module-errors" class="error"><p>'.__('Errors have occured with following plugins:').'</p> '.
		'<ul><li>'.implode("</li>\n<li>", $list).'</li></ul></div>';
	}
}

# Dashboard columns (processed first, as we need to know the result before displaying the icons.)
$dashboardItems = '';

foreach ($__dashboard_items as $i)
{
	if ($i->count() > 0)
	{
		$dashboardItems .= '';
		foreach ($i as $v) {
			$dashboardItems .= $v;
		}
		$dashboardItems .= '';
	}
}

# Dashboard elements
echo '<div id="dashboard-main">';

# Dashboard icons
echo '<div id="icons">';
foreach ($__dashboard_icons as $i)
{
	echo
	'<p><a href="'.$i[1].'"><img src="'.dc_admin_icon_url($i[2]).'" alt="" />'.
	'<br /><span>'.$i[0].'</span></a></p>';
}
echo '</div>';

if ($core->auth->user_prefs->dashboard->quickentry) {
	if ($core->auth->check('usage,contentadmin',$core->blog->id))
	{
		# Getting categories
		$categories_combo = dcAdminCombos::getCategoriesCombo(
			$core->blog->getCategories(array('post_type'=>'post'))
		);

		echo
		'<div id="quick">'.
		'<h3>'.__('Quick entry').sprintf(' &rsaquo; %s',$core->auth->getOption('post_format')).'</h3>'.
		'<form id="quick-entry" action="'.$core->adminurl->get('admin.post').'" method="post" class="fieldset">'.
		'<h4>'.__('New entry').'</h4>'.
		'<p class="col"><label for="post_title" class="required"><abbr title="'.__('Required field').'">*</abbr> '.__('Title:').'</label>'.
		form::field('post_title',20,255,'','maximal').
		'</p>'.
		'<p class="area"><label class="required" '.
		'for="post_content"><abbr title="'.__('Required field').'">*</abbr> '.__('Content:').'</label> '.
		form::textarea('post_content',50,10).
		'</p>'.
		'<p><label for="cat_id" class="classic">'.__('Category:').'</label> '.
		form::combo('cat_id',$categories_combo).'</p>'.
		($core->auth->check('categories', $core->blog->id)
			? '<div>'.
			'<p id="new_cat" class="q-cat">'.__('Add a new category').'</p>'.
			'<p class="q-cat"><label for="new_cat_title">'.__('Title:').'</label> '.
			form::field('new_cat_title',30,255,'','').'</p>'.
			'<p class="q-cat"><label for="new_cat_parent">'.__('Parent:').'</label> '.
			form::combo('new_cat_parent',$categories_combo,'','').
			'</p>'.
			'<p class="form-note info clear">'.__('This category will be created when you will save your post.').'</p>'.
			'</div>'
			: '').
		'<p><input type="submit" value="'.__('Save').'" name="save" /> '.
		($core->auth->check('publish',$core->blog->id)
			? '<input type="hidden" value="'.__('Save and publish').'" name="save-publish" />'
			: '').
		$core->formNonce().
		form::hidden('post_status',-2).
		form::hidden('post_format',$core->auth->getOption('post_format')).
		form::hidden('post_excerpt','').
		form::hidden('post_lang',$core->auth->getInfo('user_lang')).
		form::hidden('post_notes','').
		'</p>'.
		'</form>'.
		'</div>';
	}
}

foreach ($__dashboard_contents as $i)
{
	if ($i->count() > 0)
	{
		$dashboardContents .= '';
		foreach ($i as $v) {
			$dashboardContents .= $v;
		}
		$dashboardContents .= '';
	}
}

if ($dashboardContents != '' || $dashboardItems != '') {
	echo
	'<div id="dashboard-boxes">'.
	'<div class="db-items">'.$dashboardItems.$dashboardContents.'</div>'.
	'</div>';
}

echo '</div>'; #end dashboard-main
dcPage::helpBlock('core_dashboard');
dcPage::close();
