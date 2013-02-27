<?php
# -- BEGIN LICENSE BLOCK ---------------------------------------
#
# This file is part of Dotclear 2.
#
# Copyright (c) 2003-2011 Olivier Meunier & Association Dotclear
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK -----------------------------------------
if (!defined('DC_RC_PATH')) { return; }

require dirname(__FILE__).'/class.widgets.php';

# Available widgets
global $__widgets;
$__widgets = new dcWidgets;

$__widgets->create('search',__('Search engine'),array('defaultWidgets','search'),null,'Search engine form');
$__widgets->search->setting('title',__('Title:'),__('Search'));
$__widgets->search->setting('homeonly',__('Display on:'),0,'combo',
	array(__('All pages') => 0, __('Home page only') => 1, __('Except on home page') => 2));
$__widgets->search->setting('content_only',__('Content only'),0,'check');
$__widgets->search->setting('class',__('CSS class:'),'');

$__widgets->create('navigation',__('Navigation links'),array('defaultWidgets','navigation'),null,'List of navigation links');
$__widgets->navigation->setting('title',__('Title:'),'');
$__widgets->navigation->setting('homeonly',__('Display on:'),0,'combo',
	array(__('All pages') => 0, __('Home page only') => 1, __('Except on home page') => 2));
$__widgets->navigation->setting('content_only',__('Content only'),0,'check');
$__widgets->navigation->setting('class',__('CSS class:'),'');

$__widgets->create('bestof',__('Selected entries'),array('defaultWidgets','bestof'),null,'List of selected entries');
$__widgets->bestof->setting('title',__('Title:'),__('Best of me'));
$__widgets->bestof->setting('orderby',__('Sort:'),'asc','combo',array(__('Ascending') => 'asc', __('Descending') => 'desc'));
$__widgets->bestof->setting('homeonly',__('Display on:'),1,'combo',
	array(__('All pages') => 0, __('Home page only') => 1, __('Except on home page') => 2));
$__widgets->bestof->setting('content_only',__('Content only'),0,'check');
$__widgets->bestof->setting('class',__('CSS class:'),'');

$__widgets->create('langs',__('Blog languages'),array('defaultWidgets','langs'),null,'List of available languages');
$__widgets->langs->setting('title',__('Title:'),__('Languages'));
$__widgets->langs->setting('homeonly',__('Display on:'),1,'combo',
	array(__('All pages') => 0, __('Home page only') => 1, __('Except on home page') => 2));
$__widgets->langs->setting('content_only',__('Content only'),0,'check');
$__widgets->langs->setting('class',__('CSS class:'),'');


$__widgets->create('subscribe',__('Subscribe links'),array('defaultWidgets','subscribe'),null,'RSS or Atom feed subscription links');
$__widgets->subscribe->setting('title',__('Title:'),__('Subscribe'));
$__widgets->subscribe->setting('type',__('Feeds type:'),'atom','combo',array('Atom' => 'atom', 'RSS' => 'rss2'));
$__widgets->subscribe->setting('homeonly',__('Display on:'),1,'combo',
	array(__('All pages') => 0, __('Home page only') => 1, __('Except on home page') => 2));
$__widgets->subscribe->setting('content_only',__('Content only'),0,'check');
$__widgets->subscribe->setting('class',__('CSS class:'),'');

$__widgets->create('feed',__('Feed reader'),array('defaultWidgets','feed'),null,'Last entries from feed');
$__widgets->feed->setting('title',__('Title:'),__('Somewhere else'));
$__widgets->feed->setting('url',__('Feed URL:'),'');
$__widgets->feed->setting('limit',__('Entries limit:'),10);
$__widgets->feed->setting('homeonly',__('Display on:'),1,'combo',
	array(__('All pages') => 0, __('Home page only') => 1, __('Except on home page') => 2));
$__widgets->feed->setting('content_only',__('Content only'),0,'check');
$__widgets->feed->setting('class',__('CSS class:'),'');

$__widgets->create('text',__('Text'),array('defaultWidgets','text'),null,'Simple text');
$__widgets->text->setting('title',__('Title:'),'');
$__widgets->text->setting('text',__('Text:'),'','textarea');
$__widgets->text->setting('homeonly',__('Display on:'),0,'combo',
	array(__('All pages') => 0, __('Home page only') => 1, __('Except on home page') => 2));
$__widgets->text->setting('content_only',__('Content only'),0,'check');
$__widgets->text->setting('class',__('CSS class:'),'');

$__widgets->create('lastposts',__('Last entries'),array('defaultWidgets','lastposts'),null,'List of last entries published');
$__widgets->lastposts->setting('title',__('Title:'),__('Last entries'));
if ($core->plugins->moduleExists('tags')) {
	$__widgets->lastposts->setting('tag',__('Tag:'),'');
}
$__widgets->lastposts->setting('limit',__('Entries limit:'),10);
$__widgets->lastposts->setting('homeonly',__('Display on:'),1,'combo',
	array(__('All pages') => 0, __('Home page only') => 1, __('Except on home page') => 2));
$__widgets->lastposts->setting('content_only',__('Content only'),0,'check');
$__widgets->lastposts->setting('class',__('CSS class:'),'');


# --BEHAVIOR-- initWidgets
$core->callBehavior('initWidgets',$__widgets);

# Default widgets
global $__default_widgets;
$__default_widgets = array('nav'=> new dcWidgets(), 'extra'=> new dcWidgets(), 'custom'=> new dcWidgets());

$__default_widgets['nav']->append($__widgets->search);
$__default_widgets['nav']->append($__widgets->navigation);
$__default_widgets['nav']->append($__widgets->bestof);
$__default_widgets['extra']->append($__widgets->subscribe);

# --BEHAVIOR-- initDefaultWidgets
$core->callBehavior('initDefaultWidgets',$__widgets,$__default_widgets);
?>