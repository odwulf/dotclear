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

dcPage::checkSuper();

# Creating filter combo boxes
$sortby_combo = array(
__('Username') => 'user_id',
__('Last Name') => 'user_name',
__('First Name') => 'user_firstname',
__('Display name') => 'user_displayname',
__('Number of entries') => 'nb_post'
);

$order_combo = array(
__('Descending') => 'desc',
__('Ascending') => 'asc'
);

# Actions combo box
$combo_action = array(
	__('Set permissions') => 'blogs',
	__('Delete') => 'deleteuser'
);

# --BEHAVIOR-- adminUsersActionsCombo
$core->callBehavior('adminUsersActionsCombo',array(&$combo_action));


#�Get users
$page = !empty($_GET['page']) ? $_GET['page'] : 1;
$nb_per_page =  30;

if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
	$nb_per_page = $_GET['nb'];
}

$q = !empty($_GET['q']) ? $_GET['q'] : '';
$sortby = !empty($_GET['sortby']) ?	$_GET['sortby'] : 'user_id';
$order = !empty($_GET['order']) ?		$_GET['order'] : 'asc';

$params['limit'] = array((($page-1)*$nb_per_page),$nb_per_page);

$show_filters = false;

# - Search filter
if ($q) {
	$params['q'] = $q;
	$show_filters = true;
}

# - Sortby and order filter
if ($sortby !== '' && in_array($sortby,$sortby_combo)) {
	if ($order !== '' && in_array($order,$order_combo)) {
		$params['order'] = $sortby.' '.$order;
	} else {
		$order='asc';
	}
	
	if ($sortby != 'user_id' || $order != 'asc') {
		$show_filters = true;
	}
} else {
	$sortby = 'user_id';
	$order = 'asc';
}

# Get users
try {
	$rs = $core->getUsers($params);
	$counter = $core->getUsers($params,1);
	$user_list = new adminUserList($core,$rs,$counter->f(0));
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}


/* DISPLAY
-------------------------------------------------------- */
$starting_script = dcPage::jsLoad('js/_users.js');
if (!$show_filters) {
	$starting_script .= dcPage::jsLoad('js/filter-controls.js');
}

dcPage::open(__('Users'),$starting_script);

if (!$core->error->flag())
{
	if (!empty($_GET['del'])) {
		dcPage::message(__('User has been successfully removed.'));
	}
	if (!empty($_GET['upd'])) {
		dcPage::message(__('The permissions have been successfully updated.'));
	}
	
	dcPage::breadcrumb(
		array(
			__('System') => '',
			'<span class="page-title">'.__('Users').'</span>' => ''
		));

	echo
	'<p class="top-add"><strong><a class="button add" href="user.php">'.__('Create a new user').'</a></strong></p>';
	
	if (!$show_filters) {
		echo '<p><a id="filter-control" class="form-control" href="#">'.__('Filters').'</a></p>';
	}
	
	echo
	'<form action="users.php" method="get" id="filters-form">'.
	'<fieldset class="two-cols"><legend>'.__('Filters').'</legend>'.
	
	'<div class="col">'.
	'<p><label for="sortby">'.__('Order by:').' '.
	form::combo('sortby',$sortby_combo,$sortby).
	'</label> '.
	'<label for="order">'.__('Sort:').' '.
	form::combo('order',$order_combo,$order).
	'</label></p>'.
	'</div>'.
	
	'<div class="col">'.
	'<p><label for="q">'.__('Search:').' '.
	form::field('q',20,255,html::escapeHTML($q)).
	'</label></p>'.
	'<p><label for="nb" class="classic">'.	form::field('nb',3,3,$nb_per_page).' '.
	__('Users per page').'</label> '.
	'<input type="submit" value="'.__('Apply filters').'" /></p>'.
	'</div>'.
	
	'<br class="clear" />'. //Opera sucks
	'</fieldset>'.
	'</form>';
	
	# Show users
	$user_list->display($page,$nb_per_page,
	'<form action="users_actions.php" method="post" id="form-users">'.
	
	'%s'.
	
	'<div class="two-cols">'.
	'<p class="col checkboxes-helpers"></p>'.
	
	'<p class="col right"><label for="action" class="classic">'.
	__('Selected users action:').' '.
	form::combo('action',$combo_action).
	'</label> '.
	'<input type="submit" value="'.__('ok').'" />'.
	form::hidden(array('q'),html::escapeHTML($q)).
	form::hidden(array('sortby'),$sortby).
	form::hidden(array('order'),$order).
	form::hidden(array('page'),$page).
	form::hidden(array('nb'),$nb_per_page).
	$core->formNonce().
	'</p>'.
	'</div>'.
	'</form>'
	);
}

dcPage::close();
?>