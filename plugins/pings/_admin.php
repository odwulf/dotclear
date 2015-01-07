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
if (!defined('DC_CONTEXT_ADMIN')) { return; }

$_menu['Blog']->addItem(__('Pings'),
		$core->adminurl->get('admin.plugin.pings'),
		dcPage::getPF('pings/icon.png'),
		preg_match('/'.preg_quote($core->adminurl->get('admin.plugin.pings')).'/',$_SERVER['REQUEST_URI']),
		$core->auth->isSuperAdmin());

$__autoload['pingsAPI'] = dirname(__FILE__).'/lib.pings.php';
$__autoload['pingsBehaviors'] = dirname(__FILE__).'/lib.pings.php';

# Create settings if they don't exist
if (!array_key_exists('pings',$core->blog->settings->dumpNamespaces()))
{
	$default_pings_uris = array(
		'Ping-o-Matic!' => 'http://rpc.pingomatic.com/',
		'Google Blog Search' => 'http://blogsearch.google.com/ping/RPC2'
	);

	$core->blog->settings->addNamespace('pings');
	$core->blog->settings->pings->put('pings_active',1,'boolean','Activate pings plugin',true,true);
	$core->blog->settings->pings->put('pings_uris',serialize($default_pings_uris),'string','Pings services URIs',true,true);
}

$core->addBehavior('adminPostHeaders',array('pingsBehaviors','pingJS'));
$core->addBehavior('adminPostFormItems',array('pingsBehaviors','pingsFormItems'));
$core->addBehavior('adminAfterPostCreate',array('pingsBehaviors','doPings'));
$core->addBehavior('adminAfterPostUpdate',array('pingsBehaviors','doPings'));

$core->addBehavior('adminDashboardFavorites','pingDashboardFavorites');

function pingDashboardFavorites($core,$favs)
{
	$favs->register('pings', array(
		'title' => __('Pings'),
		'url' => $core->adminurl->get('admin.plugin.pings'),
		'small-icon' => dcPage::getPF('pings/icon.png'),
		'large-icon' => dcPage::getPF('pings/icon-big.png'),
	));
}

$core->addBehavior('adminPageHelpBlock', 'pingsPageHelpBlock');

function pingsPageHelpBlock($blocks)
{
	$found = false;
	foreach($blocks as $block) {
		if ($block == 'core_post') {
			$found = true;
			break;
		}
	}
	if (!$found) {
		return null;
	}
	$blocks[] = 'pings_post';
}
