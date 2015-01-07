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
if (!defined('DC_RC_PATH')) { return; }

function dotclearUpgrade($core)
{
    $cleanup_sessions = false; // update it in a step that needed sessions to be removed
	$version = $core->getVersion('core');

	if ($version === null) {
		return false;
	}

	if (version_compare($version,DC_VERSION,'<') == 1 || strpos(DC_VERSION,'dev'))
	{
		try
		{
			if ($core->con->driver() == 'sqlite') {
				throw new Exception(__('SQLite Database Schema cannot be upgraded.'));
			}

			# Database upgrade
			$_s = new dbStruct($core->con,$core->prefix);
			require dirname(__FILE__).'/db-schema.php';

			$si = new dbStruct($core->con,$core->prefix);
			$changes = $si->synchronize($_s);

			/* Some other upgrades
			------------------------------------ */
			# Populate media_dir field (since 2.0-beta3.3)
			if (version_compare($version,'2.0-beta3.3','<'))
			{
				$strReq = 'SELECT media_id, media_file FROM '.$core->prefix.'media ';
				$rs_m = $core->con->select($strReq);
				while($rs_m->fetch()) {
					$cur = $core->con->openCursor($core->prefix.'media');
					$cur->media_dir = dirname($rs_m->media_file);
					$cur->update('WHERE media_id = '.(integer) $rs_m->media_id);
				}
			}

			if (version_compare($version,'2.0-beta7.3','<'))
			{
				# Blowup becomes default theme
				$strReq = 'UPDATE '.$core->prefix.'setting '.
						"SET setting_value = '%s' ".
						"WHERE setting_id = 'theme' ".
						"AND setting_value = '%s' ".
						'AND blog_id IS NOT NULL ';
				$core->con->execute(sprintf($strReq,'blueSilence','default'));
				$core->con->execute(sprintf($strReq,'default','blowup'));
			}

			if (version_compare($version,'2.1-alpha2-r2383','<'))
			{
				$schema = dbSchema::init($core->con);
				$schema->dropUnique($core->prefix.'category',$core->prefix.'uk_cat_title');

				# Reindex categories
				$rs = $core->con->select(
					'SELECT cat_id, cat_title, blog_id '.
					'FROM '.$core->prefix.'category '.
					'ORDER BY blog_id ASC , cat_position ASC '
				);
				$cat_blog = $rs->blog_id;
				$i = 2;
				while ($rs->fetch()) {
					if ($cat_blog != $rs->blog_id) {
						$i = 2;
					}
					$core->con->execute(
						'UPDATE '.$core->prefix.'category SET '
						.'cat_lft = '.($i++).', cat_rgt = '.($i++).' '.
						'WHERE cat_id = '.(integer) $rs->cat_id
					);
					$cat_blog = $rs->blog_id;
				}
			}

			if (version_compare($version,'2.1.6','<='))
			{
				# ie7js has been upgraded
				$ie7files = array (
					'ie7-base64.php ',
					'ie7-content.htc',
					'ie7-core.js',
					'ie7-css2-selectors.js',
					'ie7-css3-selectors.js',
					'ie7-css-strict.js',
					'ie7-dhtml.js',
					'ie7-dynamic-attributes.js',
					'ie7-fixed.js',
					'ie7-graphics.js',
					'ie7-html4.js',
					'ie7-ie5.js',
					'ie7-layout.js',
					'ie7-load.htc',
					'ie7-object.htc',
					'ie7-overflow.js',
					'ie7-quirks.js',
					'ie7-server.css',
					'ie7-standard-p.js',
					'ie7-xml-extras.js'
					);
				foreach ($ie7files as $f) {
					@unlink(DC_ROOT.'/admin/js/ie7/'.$f);
				}
			}

			if (version_compare($version,'2.2-alpha1-r3043','<'))
			{
				# metadata has been integrated to the core.
				$core->plugins->loadModules(DC_PLUGINS_ROOT);
				if ($core->plugins->moduleExists('metadata')) {
					$core->plugins->deleteModule('metadata');
				}

				# Tags template class has been renamed
				$sqlstr =
					'SELECT blog_id, setting_id, setting_value '.
					'FROM '.$core->prefix.'setting '.
					'WHERE (setting_id = \'widgets_nav\' OR setting_id = \'widgets_extra\') '.
					'AND setting_ns = \'widgets\';';
				$rs = $core->con->select($sqlstr);
				while ($rs->fetch()) {
					$widgetsettings = base64_decode($rs->setting_value);
					$widgetsettings = str_replace ('s:11:"tplMetadata"','s:7:"tplTags"',$widgetsettings);
					$cur = $core->con->openCursor($core->prefix.'setting');
					$cur->setting_value = base64_encode($widgetsettings);
					$sqlstr = 'WHERE setting_id = \''.$rs->setting_id.'\' AND setting_ns = \'widgets\' '.
					'AND blog_id ' .
					($rs->blog_id == NULL ? 'is NULL' : '= \''.$core->con->escape($rs->blog_id).'\'');
					$cur->update($sqlstr);
				}
			}

			if (version_compare($version,'2.3','<'))
			{
				# Add global favorites
				$init_fav = array();

				$init_fav['new_post'] = array('new_post','New entry','post.php',
					'images/menu/edit.png','images/menu/edit-b.png',
					'usage,contentadmin',null,null);
				$init_fav['newpage'] = array('newpage','New page','plugin.php?p=pages&amp;act=page',
					'index.php?pf=pages/icon-np.png','index.php?pf=pages/icon-np-big.png',
					'contentadmin,pages',null,null);
				$init_fav['media'] = array('media','Media manager','media.php',
					'images/menu/media.png','images/menu/media-b.png',
					'media,media_admin',null,null);
				$init_fav['widgets'] = array('widgets','Presentation widgets','plugin.php?p=widgets',
					'index.php?pf=widgets/icon.png','index.php?pf=widgets/icon-big.png',
					'admin',null,null);
				$init_fav['blog_theme'] = array('blog_theme','Blog appearance','blog_theme.php',
					'images/menu/themes.png','images/menu/blog-theme-b.png',
					'admin',null,null);

				$count = 0;
				foreach ($init_fav as $k => $f) {
					$t = array('name' => $f[0],'title' => $f[1],'url' => $f[2], 'small-icon' => $f[3],
						'large-icon' => $f[4],'permissions' => $f[5],'id' => $f[6],'class' => $f[7]);
					$sqlstr = 'INSERT INTO '.$core->prefix.'pref (pref_id, user_id, pref_ws, pref_value, pref_type, pref_label) VALUES ('.
						'\''.sprintf("g%03s",$count).'\',NULL,\'favorites\',\''.serialize($t).'\',\'string\',NULL);';
					$core->con->execute($sqlstr);
					$count++;
				}

				# A bit of housecleaning for no longer needed files
				$remfiles = array (
					'admin/style/cat-bg.png',
					'admin/style/footer-bg.png',
					'admin/style/head-logo.png',
					'admin/style/tab-bg.png',
					'admin/style/tab-c-l.png',
					'admin/style/tab-c-r.png',
					'admin/style/tab-l-l.png',
					'admin/style/tab-l-r.png',
					'admin/style/tab-n-l.png',
					'admin/style/tab-n-r.png',
					'inc/clearbricks/_common.php',
					'inc/clearbricks/common/lib.crypt.php',
					'inc/clearbricks/common/lib.date.php',
					'inc/clearbricks/common/lib.files.php',
					'inc/clearbricks/common/lib.form.php',
					'inc/clearbricks/common/lib.html.php',
					'inc/clearbricks/common/lib.http.php',
					'inc/clearbricks/common/lib.l10n.php',
					'inc/clearbricks/common/lib.text.php',
					'inc/clearbricks/common/tz.dat',
					'inc/clearbricks/common/_main.php',
					'inc/clearbricks/dblayer/class.cursor.php',
					'inc/clearbricks/dblayer/class.mysql.php',
					'inc/clearbricks/dblayer/class.pgsql.php',
					'inc/clearbricks/dblayer/class.sqlite.php',
					'inc/clearbricks/dblayer/dblayer.php',
					'inc/clearbricks/dbschema/class.dbschema.php',
					'inc/clearbricks/dbschema/class.dbstruct.php',
					'inc/clearbricks/dbschema/class.mysql.dbschema.php',
					'inc/clearbricks/dbschema/class.pgsql.dbschema.php',
					'inc/clearbricks/dbschema/class.sqlite.dbschema.php',
					'inc/clearbricks/diff/lib.diff.php',
					'inc/clearbricks/diff/lib.unified.diff.php',
					'inc/clearbricks/filemanager/class.filemanager.php',
					'inc/clearbricks/html.filter/class.html.filter.php',
					'inc/clearbricks/html.validator/class.html.validator.php',
					'inc/clearbricks/image/class.image.meta.php',
					'inc/clearbricks/image/class.image.tools.php',
					'inc/clearbricks/mail/class.mail.php',
					'inc/clearbricks/mail/class.socket.mail.php',
					'inc/clearbricks/net/class.net.socket.php',
					'inc/clearbricks/net.http/class.net.http.php',
					'inc/clearbricks/net.http.feed/class.feed.parser.php',
					'inc/clearbricks/net.http.feed/class.feed.reader.php',
					'inc/clearbricks/net.xmlrpc/class.net.xmlrpc.php',
					'inc/clearbricks/pager/class.pager.php',
					'inc/clearbricks/rest/class.rest.php',
					'inc/clearbricks/session.db/class.session.db.php',
					'inc/clearbricks/template/class.template.php',
					'inc/clearbricks/text.wiki2xhtml/class.wiki2xhtml.php',
					'inc/clearbricks/url.handler/class.url.handler.php',
					'inc/clearbricks/zip/class.unzip.php',
					'inc/clearbricks/zip/class.zip.php',
					'themes/default/tpl/.htaccess',
					'themes/default/tpl/404.html',
					'themes/default/tpl/archive.html',
					'themes/default/tpl/archive_month.html',
					'themes/default/tpl/category.html',
					'themes/default/tpl/home.html',
					'themes/default/tpl/post.html',
					'themes/default/tpl/search.html',
					'themes/default/tpl/tag.html',
					'themes/default/tpl/tags.html',
					'themes/default/tpl/user_head.html',
					'themes/default/tpl/_flv_player.html',
					'themes/default/tpl/_footer.html',
					'themes/default/tpl/_head.html',
					'themes/default/tpl/_mp3_player.html',
					'themes/default/tpl/_top.html'
				);
				$remfolders = array (
					'inc/clearbricks/common',
					'inc/clearbricks/dblayer',
					'inc/clearbricks/dbschema',
					'inc/clearbricks/diff',
					'inc/clearbricks/filemanager',
					'inc/clearbricks/html.filter',
					'inc/clearbricks/html.validator',
					'inc/clearbricks/image',
					'inc/clearbricks/mail',
					'inc/clearbricks/net',
					'inc/clearbricks/net.http',
					'inc/clearbricks/net.http.feed',
					'inc/clearbricks/net.xmlrpc',
					'inc/clearbricks/pager',
					'inc/clearbricks/rest',
					'inc/clearbricks/session.db',
					'inc/clearbricks/template',
					'inc/clearbricks/text.wiki2xhtml',
					'inc/clearbricks/url.handler',
					'inc/clearbricks/zip',
					'inc/clearbricks',
					'themes/default/tpl'
				);

				foreach ($remfiles as $f) {
					@unlink(DC_ROOT.'/'.$f);
				}
				foreach ($remfolders as $f) {
					@rmdir(DC_ROOT.'/'.$f);
				}
			}

			if (version_compare($version,'2.3.1','<'))
			{
				# Remove unecessary file
				@unlink(DC_ROOT.'/'.'inc/libs/clearbricks/.hgignore');
			}

			if (version_compare($version,'2.4.0','<='))
			{
				# setup media_exclusion
				$strReq = 'UPDATE '.$core->prefix.'setting '.
						"SET setting_value = '/\\.php\$/i' ".
						"WHERE setting_id = 'media_exclusion' ".
						"AND setting_value = '' ";
				$core->con->execute($strReq);
			}

			if (version_compare($version,'2.5','<='))
			{
				# Try to disable daInstaller plugin if it has been installed outside the default plugins directory
				$path = explode(PATH_SEPARATOR,DC_PLUGINS_ROOT);
				$default = path::real(dirname(__FILE__).'/../../plugins/');
				foreach ($path as $root)
				{
					if (!is_dir($root) || !is_readable($root)) {
						continue;
					}
					if (substr($root,-1) != '/') {
						$root .= '/';
					}
					if (($p = @dir($root)) === false) {
						continue;
					}
					if(path::real($root) == $default) {
						continue;
					}
					if (($d = @dir($root.'daInstaller')) === false) {
						continue;
					}
					$f = $root.'/daInstaller/_disabled';
					if (!file_exists($f))
					{
						@file_put_contents($f,'');
					}
				}
			}

			if (version_compare($version,'2.5.1','<='))
			{
				// Flash enhanced upload no longer needed
				@unlink(DC_ROOT.'/'.'inc/swf/swfupload.swf');
			}

			if (version_compare($version,'2.6','<='))
			{
				// README has been replaced by README.md and CONTRIBUTING.md
				@unlink(DC_ROOT.'/'.'README');

				// trackbacks are now merged into posts
				@unlink(DC_ROOT.'/'.'admin/trackbacks.php');

				# daInstaller has been integrated to the core.
				# Try to remove it
				$path = explode(PATH_SEPARATOR,DC_PLUGINS_ROOT);
				foreach ($path as $root)
				{
					if (!is_dir($root) || !is_readable($root)) {
						continue;
					}
					if (substr($root,-1) != '/') {
						$root .= '/';
					}
					if (($p = @dir($root)) === false) {
						continue;
					}
					if (($d = @dir($root.'daInstaller')) === false) {
						continue;
					}
					files::deltree($root.'/daInstaller');
				}

				# Some settings change, prepare db queries
				$strReqFormat = 'INSERT INTO '.$core->prefix.'setting';
				$strReqFormat .= ' (setting_id,setting_ns,setting_value,setting_type,setting_label)';
				$strReqFormat .= ' VALUES(\'%s\',\'system\',\'%s\',\'string\',\'%s\')';

				$strReqSelect = 'SELECT count(1) FROM '.$core->prefix.'setting';
				$strReqSelect .= ' WHERE setting_id = \'%s\'';
				$strReqSelect .= ' AND setting_ns = \'system\'';
				$strReqSelect .= ' AND blog_id IS NULL';

				# Add date and time formats
				$date_formats = array('%Y-%m-%d','%m/%d/%Y','%d/%m/%Y','%Y/%m/%d','%d.%m.%Y','%b %e %Y','%e %b %Y','%Y %b %e',
				'%a, %Y-%m-%d','%a, %m/%d/%Y','%a, %d/%m/%Y','%a, %Y/%m/%d','%B %e, %Y','%e %B, %Y','%Y, %B %e','%e. %B %Y',
				'%A, %B %e, %Y','%A, %e %B, %Y','%A, %Y, %B %e','%A, %Y, %B %e','%A, %e. %B %Y');
				$time_formats = array('%H:%M','%I:%M','%l:%M','%Hh%M','%Ih%M','%lh%M');
				if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
					$date_formats = array_map(create_function('$f',
											  'return str_replace(\'%e\',\'%#d\',$f);'
											  ),$date_formats);
				}

				$rs = $core->con->select(sprintf($strReqSelect,'date_formats'));
				if ($rs->f(0)==0) {
					$strReq = sprintf($strReqFormat,'date_formats',serialize($date_formats),'Date formats examples');
					$core->con->execute($strReq);
				}
				$rs = $core->con->select(sprintf($strReqSelect,'time_formats'));
				if ($rs->f(0)==0) {
					$strReq = sprintf($strReqFormat,'time_formats',serialize($time_formats),'Time formats examples');
					$core->con->execute($strReq);
				}

				# Add repository URL for themes and plugins as daInstaller move to core
				$rs = $core->con->select(sprintf($strReqSelect,'store_plugin_url'));
				if ($rs->f(0)==0) {
					$strReq = sprintf($strReqFormat,'store_plugin_url','http://update.dotaddict.org/dc2/plugins.xml','Plugins XML feed location');
					$core->con->execute($strReq);
				}
				$rs = $core->con->select(sprintf($strReqSelect,'store_theme_url'));
				if ($rs->f(0)==0) {
					$strReq = sprintf($strReqFormat,'store_theme_url','http://update.dotaddict.org/dc2/themes.xml','Themes XML feed location');
					$core->con->execute($strReq);
				}
			}

			if (version_compare($version,'2.7','<='))
			{
				# Some new settings should be initialized, prepare db queries
				$strReqFormat = 'INSERT INTO '.$core->prefix.'setting';
				$strReqFormat .= ' (setting_id,setting_ns,setting_value,setting_type,setting_label)';
				$strReqFormat .= ' VALUES(\'%s\',\'system\',\'%s\',\'string\',\'%s\')';

				$strReqCount = 'SELECT count(1) FROM '.$core->prefix.'setting';
				$strReqCount .= ' WHERE setting_id = \'%s\'';
				$strReqCount .= ' AND setting_ns = \'system\'';
				$strReqCount .= ' AND blog_id IS NULL';

				$strReqSelect = 'SELECT setting_value FROM '.$core->prefix.'setting';
				$strReqSelect .= ' WHERE setting_id = \'%s\'';
				$strReqSelect .= ' AND setting_ns = \'system\'';
				$strReqSelect .= ' AND blog_id IS NULL';

				# Add nb of posts for home (first page), copying nb of posts on every page
				$rs = $core->con->select(sprintf($strReqCount,'nb_post_for_home'));
				if ($rs->f(0)==0) {
					$rs = $core->con->select(sprintf($strReqSelect,'nb_post_per_page'));
					$strReq = sprintf($strReqFormat,'nb_post_for_home',$rs->f(0),'Nb of posts on home (first page only)');
					$core->con->execute($strReq);
				}
			}

			$core->setVersion('core',DC_VERSION);
			$core->blogDefaults();

			# Drop content from session table if changes or if needed
			if ($changes != 0 || $cleanup_sessions) {
				$core->con->execute('DELETE FROM '.$core->prefix.'session ');
			}

			# Empty templates cache directory
			try {
				$core->emptyTemplatesCache();
			} catch (Exception $e) {}

			return $changes;
		}
		catch (Exception $e)
		{
			throw new Exception(__('Something went wrong with auto upgrade:').
			' '.$e->getMessage());
		}
	}

	# No upgrade?
	return false;
}
