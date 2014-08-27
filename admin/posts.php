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
	$users_combo = array_merge(
		array('-' => ''),
		dcAdminCombos::getUsersCombo($users)
	);

	$categories_combo = array_merge(
		array(
			new formSelectOption('-',''),
			new formSelectOption(__('(No cat)'),'NULL')),
		dcAdminCombos::getCategoriesCombo($categories,false)
	);
	$categories_values = array();
	foreach ($categories_combo as $cat) {
		if (isset($cat->value)) {
			$categories_values[$cat->value]=true;
		}
	}

	$status_combo = array_merge(
		array('-' => ''),
		dcAdminCombos::getPostStatusesCombo()
	);

	$selected_combo = array(
	'-' => '',
	__('Selected') => '1',
	__('Not selected') => '0'
	);

	$attachment_combo = array(
	'-' => '',
	__('With attachments') => '1',
	__('Without attachments') => '0'
	);

	# Months array
	$dt_m_combo = array_merge(
		array('-' => ''),
		dcAdminCombos::getDatesCombo($dates)
	);

	$lang_combo = array_merge(
		array('-' => ''),
		dcAdminCombos::getLangsCombo($langs,false)
	);

	$sortby_combo = array(
	__('Date') => 'post_dt',
	__('Title') => 'post_title',
	__('Category') => 'cat_title',
	__('Author') => 'user_id',
	__('Status') => 'post_status',
	__('Selected') => 'post_selected',
	__('Number of comments') => 'nb_comment',
	__('Number of trackbacks') => 'nb_trackback'
	);

	$order_combo = array(
	__('Descending') => 'desc',
	__('Ascending') => 'asc'
	);
}

# Actions combo box

$posts_actions_page = new dcPostsActionsPage($core,$core->adminurl->get("admin.posts"));

if ($posts_actions_page->process()) {
	return;
}

/* Get posts
-------------------------------------------------------- */
$user_id = !empty($_GET['user_id']) ?	$_GET['user_id'] : '';
$cat_id = !empty($_GET['cat_id']) ?	$_GET['cat_id'] : '';
$status = isset($_GET['status']) ?	$_GET['status'] : '';
$selected = isset($_GET['selected']) ?	$_GET['selected'] : '';
$attachment = isset($_GET['attachment']) ?	$_GET['attachment'] : '';
$month = !empty($_GET['month']) ?		$_GET['month'] : '';
$lang = !empty($_GET['lang']) ?		$_GET['lang'] : '';
$sortby = !empty($_GET['sortby']) ?	$_GET['sortby'] : 'post_dt';
$order = !empty($_GET['order']) ?		$_GET['order'] : 'desc';

$show_filters = false;

$page = !empty($_GET['page']) ? max(1,(integer) $_GET['page']) : 1;
$nb_per_page =  30;

if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
	if ($nb_per_page != $_GET['nb']) {
		$show_filters = true;
	}
	$nb_per_page = (integer) $_GET['nb'];
}

$params['limit'] = array((($page-1)*$nb_per_page),$nb_per_page);
$params['no_content'] = true;

# - User filter
if ($user_id !== '' && in_array($user_id,$users_combo)) {
	$params['user_id'] = $user_id;
	$show_filters = true;
} else {
	$user_id='';
}

# - Categories filter
if ($cat_id !== '' && isset($categories_values[$cat_id])) {
	$params['cat_id'] = $cat_id;
	$show_filters = true;
} else {
	$cat_id='';
}

# - Status filter
if ($status !== '' && in_array($status,$status_combo)) {
	$params['post_status'] = $status;
	$show_filters = true;
} else {
	$status='';
}

# - Selected filter
if ($selected !== '' && in_array($selected,$selected_combo)) {
	$params['post_selected'] = $selected;
	$show_filters = true;
} else {
	$selected='';
}

# - Selected filter
if ($attachment !== '' && in_array($attachment,$attachment_combo)) {
	$params['media'] = $attachment;
	$params['link_type'] = 'attachment';
	$show_filters = true;
} else {
	$attachment='';
}

# - Month filter
if ($month !== '' && in_array($month,$dt_m_combo)) {
	$params['post_month'] = substr($month,4,2);
	$params['post_year'] = substr($month,0,4);
	$show_filters = true;
} else {
	$month='';
}

# - Lang filter
if ($lang !== '' && in_array($lang,$lang_combo)) {
	$params['post_lang'] = $lang;
	$show_filters = true;
} else {
	$lang='';
}

# - Sortby and order filter
if ($sortby !== '' && in_array($sortby,$sortby_combo)) {
	if ($order !== '' && in_array($order,$order_combo)) {
		$params['order'] = $sortby.' '.$order;
	} else {
		$order='desc';
	}

	if ($sortby != 'post_dt' || $order != 'desc') {
		$show_filters = true;
	}
} else {
	$sortby='post_dt';
	$order='desc';
}

# Get posts
try {
	$posts = $core->blog->getPosts($params);
	$counter = $core->blog->getPosts($params,true);
	$post_list = new adminPostList($core,$posts,$counter->f(0));
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

/* DISPLAY
-------------------------------------------------------- */

$form_filter_title = __('Show filters and display options');
$starting_script  = dcPage::jsLoad('js/_posts_list.js');
$starting_script .= dcPage::jsLoad('js/filter-controls.js');
$starting_script .=
	'<script type="text/javascript">'."\n".
	"//<![CDATA["."\n".
	dcPage::jsVar('dotclear.msg.show_filters', $show_filters ? 'true':'false')."\n".
	dcPage::jsVar('dotclear.msg.filter_posts_list',$form_filter_title)."\n".
	dcPage::jsVar('dotclear.msg.cancel_the_filter',__('Cancel filters and display options'))."\n".
	"//]]>".
	"</script>";

dcPage::open(__('Entries'),$starting_script,
	dcPage::breadcrumb(
		array(
			html::escapeHTML($core->blog->name) => '',
			__('Entries') => ''
		))
);
if (!empty($_GET['upd'])) {
	dcPage::success(__('Selected entries have been successfully updated.'));
} elseif (!empty($_GET['del'])) {
	dcPage::success(__('Selected entries have been successfully deleted.'));
}
if (!$core->error->flag())
{
	echo
	'<p class="top-add"><a class="button add" href="'.$core->adminurl->get("admin.post").'">'.__('New entry').'</a></p>'.
	'<form action="'.$core->adminurl->get("admin.posts").'" method="get" id="filters-form">'.
	'<h3 class="out-of-screen-if-js">'.$form_filter_title.'</h3>'.

	'<div class="table">'.
	'<div class="cell">'.
	'<h4>'.__('Filters').'</h4>'.
	'<p><label for="user_id" class="ib">'.__('Author:').'</label> '.
	form::combo('user_id',$users_combo,$user_id).'</p>'.
	'<p><label for="cat_id" class="ib">'.__('Category:').'</label> '.
	form::combo('cat_id',$categories_combo,$cat_id).'</p>'.
	'<p><label for="status" class="ib">'.__('Status:').'</label> ' .
	form::combo('status',$status_combo,$status).'</p> '.
	'</div>'.

	'<div class="cell filters-sibling-cell">'.
	'<p><label for="selected" class="ib">'.__('Selected:').'</label> '.
	form::combo('selected',$selected_combo,$selected).'</p>'.
	'<p><label for="attachment" class="ib">'.__('Attachments:').'</label> '.
	form::combo('attachment',$attachment_combo,$attachment).'</p>'.
	'<p><label for="month" class="ib">'.__('Month:').'</label> '.
	form::combo('month',$dt_m_combo,$month).'</p>'.
	'<p><label for="lang" class="ib">'.__('Lang:').'</label> '.
	form::combo('lang',$lang_combo,$lang).'</p> '.
	'</div>'.

	'<div class="cell filters-options">'.
	'<h4>'.__('Display options').'</h4>'.
	'<p><label for="sortby" class="ib">'.__('Order by:').'</label> '.
	form::combo('sortby',$sortby_combo,$sortby).'</p>'.
	'<p><label for="order" class="ib">'.__('Sort:').'</label> '.
	form::combo('order',$order_combo,$order).'</p>'.
	'<p><span class="label ib">'.__('Show').'</span> <label for="nb" class="classic">'.
	form::field('nb',3,3,$nb_per_page).' '.
	__('entries per page').'</label></p>'.
	'</div>'.
	'</div>'.

	'<p><input type="submit" value="'.__('Apply filters and display options').'" />'.
	'<br class="clear" /></p>'. //Opera sucks
	'</form>';

	# Show posts
	$post_list->display($page,$nb_per_page,
	'<form action="'.$core->adminurl->get("admin.posts").'" method="post" id="form-entries">'.

	'%s'.

	'<div class="two-cols">'.
	'<p class="col checkboxes-helpers"></p>'.

	'<p class="col right"><label for="action" class="classic">'.__('Selected entries action:').'</label> '.
	form::combo('action',$posts_actions_page->getCombo()).
	'<input type="submit" value="'.__('ok').'" /></p>'.
	form::hidden(array('user_id'),$user_id).
	form::hidden(array('cat_id'),$cat_id).
	form::hidden(array('status'),$status).
	form::hidden(array('selected'),$selected).
	form::hidden(array('attachment'),$attachment).
	form::hidden(array('month'),$month).
	form::hidden(array('lang'),$lang).
	form::hidden(array('sortby'),$sortby).
	form::hidden(array('order'),$order).
	form::hidden(array('page'),$page).
	form::hidden(array('nb'),$nb_per_page).
	$core->formNonce().
	'</div>'.
	'</form>',
	$show_filters
	);
}

dcPage::helpBlock('core_posts');
dcPage::close();
