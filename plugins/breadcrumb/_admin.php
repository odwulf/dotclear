<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
# This file is part of breadcrumb, a plugin for Dotclear 2.
#
# Copyright (c) Franck Paul and contributors
# carnet.franck.paul@gmail.com
#
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_CONTEXT_ADMIN')) { return; }

// dead but useful code, in order to have translations
__('Breadcrumb').__('Breadcrumb for Dotclear');

$core->addBehavior('adminBlogPreferencesForm',array('breadcrumbBehaviors','adminBlogPreferencesForm'));
$core->addBehavior('adminBeforeBlogSettingsUpdate',array('breadcrumbBehaviors','adminBeforeBlogSettingsUpdate'));

class breadcrumbBehaviors
{
	public static function adminBlogPreferencesForm($core,$settings)
	{
		$settings->addNameSpace('breadcrumb');
		echo
		'<div class="fieldset"><h4>'.__('Breadcrumb').'</h4>'.
		'<p><label class="classic">'.
		form::checkbox('breadcrumb_enabled','1',$settings->breadcrumb->breadcrumb_enabled).
		__('Enable breadcrumb for this blog').'</label></p>'.
		'<p class="form-note">'.__('The {{tpl:Breadcrumb [separator=" &amp;rsaquo; "]}} tag should be present (or inserted if not) in the template.').'</p>'.
		form::checkbox('breadcrumb_alone','1',$settings->breadcrumb->breadcrumb_alone).
		__('Do not encapsulate breadcrumb in a &lt;p id="breadcrumb"&gt;...&lt;/p&gt; tag.').'</label></p>'.
		'</div>';
	}

	public static function adminBeforeBlogSettingsUpdate($settings)
	{
		$settings->addNameSpace('breadcrumb');
		$settings->breadcrumb->put('breadcrumb_enabled',!empty($_POST['breadcrumb_enabled']),'boolean');
		$settings->breadcrumb->put('breadcrumb_alone',!empty($_POST['breadcrumb_alone']),'boolean');
	}
}
