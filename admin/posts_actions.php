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

/* ### THIS FILE IS DEPRECATED 					### */
/* ### IT IS ONLY USED FOR PLUGINS COMPATIBILITY ### */

require dirname(__FILE__).'/../inc/admin/prepend.php';

dcPage::check('usage,contentadmin');

if (isset($_REQUEST['redir'])) {
	$u = explode('?',$_REQUEST['redir']);
	$uri = $u[0];
	if (isset($u[1])) {
		parse_str($u[1],$args);
	}
	$args['redir'] = $_REQUEST['redir'];
} else {
	$uri = $core->adminurl->get("admin.posts");
	$args=array();
}

$posts_actions_page = new dcPostsActionsPage($core,$uri,$args);
$posts_actions_page->setEnableRedirSelection(false);
$posts_actions_page->process();
