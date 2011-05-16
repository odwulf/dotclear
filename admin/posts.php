<?php
# -- BEGIN LICENSE BLOCK ---------------------------------------
#
# This file is part of Dotclear 2.
#
# Copyright (c) 2003-2010 Olivier Meunier & Association Dotclear
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK -----------------------------------------

require dirname(__FILE__).'/../inc/admin/prepend.php';

dcPage::check('usage,contentadmin');

# Getting categories
try {
	$categories = $core->blog->getCategories(array('post_type'=>'post'));
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

# Getting authors
try {
	$users = $core->blog->getPostsUsers();
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

# Getting dates
try {
	$dates = $core->blog->getDates(array('type'=>'month'));
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

# Getting langs
try {
	$langs = $core->blog->getLangs();
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

# Creating filter combo boxes
if (!$core->error->flag())
{
	# Filter form we'll put in html_block
	$users_combo = $categories_combo = array();
	while ($users->fetch())
	{
		$user_cn = dcUtils::getUserCN($users->user_id,$users->user_name,
		$users->user_firstname,$users->user_displayname);
		
		if ($user_cn != $users->user_id) {
			$user_cn .= ' ('.$users->user_id.')';
		}
		
		$users_combo[$user_cn] = $users->user_id; 
	}
	
	$categories_combo[__('None')] = 'NULL';
	while ($categories->fetch()) {
		$categories_combo[str_repeat('&nbsp;&nbsp;',$categories->level-1).'&bull; '.
			html::escapeHTML($categories->cat_title).
			' ('.$categories->nb_post.')'] = $categories->cat_id;
	}
	
	$status_combo = array(
	);
	foreach ($core->blog->getAllPostStatus() as $k => $v) {
		$status_combo[$v] = (string) $k;
	}
	
	$selected_combo = array(
	__('selected') => '1',
	__('not selected') => '0'
	);
	
	# Months array
	while ($dates->fetch()) {
		$dt_m_combo[dt::str('%B %Y',$dates->ts())] = $dates->year().$dates->month();
	}
	
	while ($langs->fetch()) {
		$lang_combo[$langs->post_lang] = $langs->post_lang;
	}
	
	$sortby_combo = array(
	__('Date') => 'post_dt',
	__('Title') => 'post_title',
	__('Category') => 'cat_title',
	__('Author') => 'user_id',
	__('Status') => 'post_status',
	__('Selected') => 'post_selected'
	);
	
	$order_combo = array(
	__('Descending') => 'desc',
	__('Ascending') => 'asc'
	);
}

# Actions combo box
$combo_action = array();
if ($core->auth->check('publish,contentadmin',$core->blog->id))
{
	$combo_action[__('Status')] = array(
		__('Publish') => 'publish',
		__('Unpublish') => 'unpublish',
		__('Schedule') => 'schedule',
		__('Mark as pending') => 'pending'
	);
}
$combo_action[__('Mark')] = array(
	__('Mark as selected') => 'selected',
	__('Mark as unselected') => 'unselected'
);
$combo_action[__('Change')] = array(__('Change category') => 'category');
if ($core->auth->check('admin',$core->blog->id))
{
	$combo_action[__('Change')] = array_merge($combo_action[__('Change')],
		array(__('Change author') => 'author'));
}
if ($core->auth->check('delete,contentadmin',$core->blog->id))
{
	$combo_action[__('Delete')] = array(__('Delete') => 'delete');
}

# --BEHAVIOR-- adminPostsActionsCombo
$core->callBehavior('adminPostsActionsCombo',array(&$combo_action));

/* Get posts
-------------------------------------------------------- */
$page = !empty($_GET['page']) ? (integer) $_GET['page'] : 1;
$nb_per_page =  30;

if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
	if ($nb_per_page != $_GET['nb']) {
		$show_filters = true;
	}
	$nb_per_page = (integer) $_GET['nb'];
}

$params = new ArrayObject();
$params['limit'] = array((($page-1)*$nb_per_page),$nb_per_page);
$params['no_content'] = true;

$filterSet = new dcFilterSet('posts.php');
class monthComboFilter extends comboFilter {
	public function applyFilter($params) {
		$month=$this->values[0];
		$params['post_month'] = substr($month,4,2);
		$params['post_year'] = substr($month,0,4);
	}
}
$filterSet
	->addFilter(new comboFilter(
		'users',__('Author'), 'user', $users_combo))
	->addFilter(new comboFilter(
		'category',__('Category'), 'cat_id', $categories_combo))
	->addFilter(new comboFilter(
		'post_status',__('Status'), 'post_status', $status_combo,array('singleval' => 1)))
	->addFilter(new comboFilter(
		'post_selected',__('Selected'), 'post_selected', $selected_combo))
	->addFilter(new comboFilter(
		'lang',__('Lang'), 'post_lang', $lang_combo))
	->addFilter(new monthComboFilter(
		'month',__('Month'), 'post_month', $dt_m_combo,array('singleval' => 1)));
$filterSet->setValues($_GET);

# Get posts
try {
	$nfparams = $params->getArrayCopy();
	$filtered = $filterSet->applyFilters($params);
	$posts = $core->blog->getPosts($params);
	$counter = $core->blog->getPosts($params,true);
	if ($filtered) {
		$totalcounter = $core->blog->getPosts($nfparams,true);
		$page_title = sprintf(__('Entries / %s filtered out of %s'),$counter->f(0),$totalcounter->f(0));
	} else {
		$page_title = __('Entries');
	}
	$post_list = new adminPostList($core,$posts,$counter->f(0));
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

$filterSet->setColumnsForm($post_list->getColumnsForm());

/* DISPLAY
-------------------------------------------------------- */
$starting_script = dcPage::jsLoad('js/_posts_list.js');

$starting_script .= $filterSet->header();

dcPage::open(__('Entries'),$starting_script);

if (!$core->error->flag())
{
	echo 
	'<h2>'.html::escapeHTML($core->blog->name).' &rsaquo; '.$page_title.'</h2>'.
	'<p class="top-add"><a class="button add" href="post.php">'.__('New entry').'</a></p>';

	$filterSet->display();

	# Show posts
	$post_list->display($page,$nb_per_page,
	'<form action="posts_actions.php" method="post" id="form-entries">'.
	
	'%s'.
	
	'<div class="two-cols">'.
	'<p class="col checkboxes-helpers"></p>'.
	
	'<p class="col right"><label for="action" class="classic">'.__('Selected entries action:').'</label> '.
	form::combo('action',$combo_action).
	'<input type="submit" value="'.__('ok').'" /></p>'.
	$filterSet->getFormFieldsAsHidden().
	$core->formNonce().
	'</div>'.
	'</form>'
	);
}

dcPage::helpBlock('core_posts');
dcPage::close();
?>