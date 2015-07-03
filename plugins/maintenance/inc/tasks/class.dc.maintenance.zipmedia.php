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

class dcMaintenanceZipmedia extends dcMaintenanceTask
{
	protected $perm = 'admin';
	protected $blog = true;
	protected $tab = 'backup';
	protected $group = 'zipblog';

	protected function init()
	{
		$this->task = __('Download media folder of current blog');

		$this->description = __('It may be useful to backup your media folder. This compress all content of media folder into a single zip file. Notice : with some hosters, the media folder cannot be compressed with this plugin if it is too big.');
	}

	public function execute()
	{
		// Instance media
		$this->core->media = new dcMedia($this->core);
		$this->core->media->chdir(null);
		$this->core->media->getDir();

		// Create zip
		@set_time_limit(300);
		$fp = fopen('php://output', 'wb');
		$zip = new fileZip($fp);
		$zip->addExclusion('#(^|/).(.*?)_(m|s|sq|t).jpg$#');
		$zip->addDirectory($this->core->media->root.'/', '', true);

		// Log task execution here as we sent file and stop script
		$this->log();

		// Send zip
		header('Content-Disposition: attachment;filename='.date('Y-m-d').'-'.$this->core->blog->id.'-'.'media.zip');
		header('Content-Type: application/x-zip');
		$zip->write();
		unset($zip);
		exit(1);
	}
}
