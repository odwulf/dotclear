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

/* HTML page
-------------------------------------------------------- */
require dirname(__FILE__).'/../inc/admin/prepend.php';

dcPage::check('media,media_admin');

$post_id = !empty($_REQUEST['post_id']) ? (integer) $_REQUEST['post_id'] : null;
if ($post_id) {
	$post = $core->blog->getPosts(array('post_id'=>$post_id,'post_type'=>''));
	if ($post->isEmpty()) {
		$post_id = null;
	}
	$post_title = $post->post_title;
	$post_type = $post->post_type;
	unset($post);
}
$d = isset($_REQUEST['d']) ? $_REQUEST['d'] : null;
$plugin_id = isset($_REQUEST['plugin_id']) ? html::sanitizeURL($_REQUEST['plugin_id']) : '';
$dir = null;

$page = !empty($_GET['page']) ? max(1,(integer) $_GET['page']) : 1;
$nb_per_page = ((integer) $core->auth->user_prefs->interface->media_by_page ? (integer) $core->auth->user_prefs->interface->media_by_page : 30);

# We are on home not comming from media manager
if ($d === null && isset($_SESSION['media_manager_dir'])) {
	# We get session information
	$d = $_SESSION['media_manager_dir'];
}

if (!isset($_GET['page']) && isset($_SESSION['media_manager_page'])) {
	$page = $_SESSION['media_manager_page'];
}

# We set session information about directory, page and display mode
if ($d) {
	$_SESSION['media_manager_dir'] = $d;
} else {
	unset($_SESSION['media_manager_dir']);
}
if ($page != 1) {
	$_SESSION['media_manager_page'] = $page;
} else {
	unset($_SESSION['media_manager_page']);
}

# Get query in any
$q = isset($_REQUEST['q']) ? $_REQUEST['q'] : null;

# Sort combo
$sort_combo = array(
	__('By names, in ascending order') => 'name-asc',
	__('By names, in descending order') => 'name-desc',
	__('By dates, in ascending order') => 'date-asc',
	__('By dates, in descending order') => 'date-desc'
	);

if (!empty($_GET['file_mode'])) {
	$_SESSION['media_file_mode'] = $_GET['file_mode'];
}
$file_mode = !empty($_SESSION['media_file_mode']) ? $_SESSION['media_file_mode'] : false;

if (!empty($_GET['file_sort']) && in_array($_GET['file_sort'],$sort_combo)) {
	$_SESSION['media_file_sort'] = $_GET['file_sort'];
}
$file_sort = !empty($_SESSION['media_file_sort']) ? $_SESSION['media_file_sort'] : null;

$nb_per_page = !empty($_SESSION['nb_per_page']) ? (integer)$_SESSION['nb_per_page'] : $nb_per_page;
if (!empty($_GET['nb_per_page']) && (integer)$_GET['nb_per_page'] > 0) {
	$nb_per_page = $_SESSION['nb_per_page'] = (integer)$_GET['nb_per_page'];
}

$popup = (integer) !empty($_REQUEST['popup']);

$page_url_params = new ArrayObject(array('popup' => $popup,'post_id' => $post_id));
if ($d) {
	$page_url_params['d'] = $d;
}
if ($plugin_id != '') {
	$page_url_params['plugin_id'] = $plugin_id;
}
if ($q) {
	$page_url_params['q'] = $q;
}

$core->callBehavior('adminMediaURLParams',$page_url_params);
$page_url_params = (array) $page_url_params;

if ($popup) {
	$open_f = array('dcPage','openPopup');
	$close_f = array('dcPage','closePopup');
} else {
	$open_f = array('dcPage','open');
	$close_f = create_function('',"dcPage::helpBlock('core_media'); dcPage::close();");
}

$core_media_writable = false;
try {
	$core->media = new dcMedia($core);
	if ($file_sort) {
		$core->media->setFileSort($file_sort);
	}
	$query = false;
	if ($q) {
		$query = $core->media->searchMedia($q);
	}
	if (!$query) {
		$try_d = $d;
		// Reset current dir
		$d = null;
		// Change directory (may cause an exception if directory doesn't exist)
		$core->media->chdir($try_d);
		// Restore current dir variable
		$d = $try_d;
		$core->media->getDir();
	} else {
		$d = null;
		$core->media->chdir($d);
	}
	$core_media_writable = $core->media->writable();
	$dir =& $core->media->dir;
	if  (!$core_media_writable) {
//		throw new Exception('you do not have sufficient permissions to write to this folder: ');
	}
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

# Recent media dirs
$last_dirs = null;
//if ($d) {
	$recent_dir = rtrim($d,'/');;
	$core->auth->user_prefs->addWorkspace('interface');
	$nb_last_dirs = (integer)($core->auth->user_prefs->interface->media_nb_last_dirs);
	if ($nb_last_dirs > 0) {
		$last_dirs = @unserialize($core->auth->user_prefs->interface->media_last_dirs);
		if (!is_array($last_dirs)) {
			$last_dirs = array();
		}
		if (!in_array($recent_dir,$last_dirs)) {
			// Add new dir at the top of the list
			array_unshift($last_dirs,$recent_dir);
			// Remove oldest dir(s)
			while (count($last_dirs) > $nb_last_dirs) {
				array_pop($last_dirs);
			}
		} else {
			// Move current dir at the top of list
			unset($last_dirs[array_search($recent_dir,$last_dirs)]);
			array_unshift($last_dirs,$recent_dir);
		}
		// Store new list
		$core->auth->user_prefs->interface->put('media_last_dirs',serialize($last_dirs));
	}
//}

# Zip download
if (!empty($_GET['zipdl']) && $core->auth->check('media_admin',$core->blog->id))
{
	try
	{
		@set_time_limit(300);
		$fp = fopen('php://output','wb');
		$zip = new fileZip($fp);
		$zip->addExclusion('#(^|/).(.*?)_(m|s|sq|t).jpg$#');
		$zip->addDirectory($core->media->root.'/'.$d,'',true);

		header('Content-Disposition: attachment;filename='.date('Y-m-d').'-'.$core->blog->id.'-'.($d ? $d : 'media').'.zip');
		header('Content-Type: application/x-zip');
		$zip->write();
		unset($zip);
		exit;
	}
	catch (Exception $e)
	{
		$core->error->add($e->getMessage());
	}
}

# New directory
if ($dir && !empty($_POST['newdir']))
{
	try {
		$core->media->makeDir($_POST['newdir']);
		dcPage::addSuccessNotice(sprintf(
			__('Directory "%s" has been successfully created.'),
			html::escapeHTML($_POST['newdir']))
		);
		$core->adminurl->redirect('admin.media',$page_url_params);
	} catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

# Adding a file
if ($dir && !empty($_FILES['upfile'])) {
	// only one file per request : @see option singleFileUploads in admin/js/jsUpload/jquery.fileupload
	$upfile = array('name' => $_FILES['upfile']['name'][0],
		'type' => $_FILES['upfile']['type'][0],
		'tmp_name' => $_FILES['upfile']['tmp_name'][0],
		'error' => $_FILES['upfile']['error'][0],
		'size' => $_FILES['upfile']['size'][0]
		);

	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-type: application/json');
		$message = array();

		try {
			files::uploadStatus($upfile);
			$new_file_id = $core->media->uploadFile($upfile['tmp_name'], $upfile['name']);

			$message['files'][] = array(
				'name' => $upfile['name'],
				'size' => $upfile['size'],
				'html' => mediaItemLine($core->media->getFile($new_file_id),1,$query)
			);
		} catch (Exception $e) {
			$message['files'][] = array('name' => $upfile['name'],
				'size' => $upfile['size'],
				'error' => $e->getMessage()
				);
		}
		echo json_encode($message);
		exit();
	} else {
		try {
			files::uploadStatus($upfile);

			$f_title = (isset($_POST['upfiletitle']) ? $_POST['upfiletitle'] : '');
			$f_private = (isset($_POST['upfilepriv']) ? $_POST['upfilepriv'] : false);

			$core->media->uploadFile($upfile['tmp_name'],$upfile['name'],$f_title,$f_private);

			dcPage::addSuccessNotice(__('Files have been successfully uploaded.'));
			$core->adminurl->redirect('admin.media',$page_url_params);
		} catch (Exception $e) {
			$core->error->add($e->getMessage());
		}
	}
}

# Removing items
if ($dir && !empty($_POST['medias']) && !empty($_POST['delete_medias'])) {
	try {
		foreach ($_POST['medias'] as $media) {
			$core->media->removeItem(rawurldecode($media));
		}
		dcPage::addSuccessNotice(
			sprintf(__('Successfully delete one media.',
					   'Successfully delete %d medias.',
					   count($_POST['medias'])
					   ),
					   count($_POST['medias'])
			)
		);
		$core->adminurl->redirect('admin.media',$page_url_params);
	} catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

# Removing item from popup only
if ($dir && !empty($_POST['rmyes']) && !empty($_POST['remove']))
{
	$_POST['remove'] = rawurldecode($_POST['remove']);

	try {
		if (is_dir(path::real($core->media->getPwd().'/'.path::clean($_POST['remove'])))) {
			$msg = __('Directory has been successfully removed.');
		} else {
			$msg = __('File has been successfully removed.');
		}
		$core->media->removeItem($_POST['remove']);
		dcPage::addSuccessNotice($msg);
		$core->adminurl->redirect('admin.media',$page_url_params);
	} catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

# Rebuild directory
if ($dir && $core->auth->isSuperAdmin() && !empty($_POST['rebuild']))
{
	try {
		$core->media->rebuild($d);

		dcPage::success(sprintf(
			__('Directory "%s" has been successfully rebuilt.'),
			html::escapeHTML($d))
		);
		$core->adminurl->redirect('admin.media',$page_url_params);
	} catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

# DISPLAY confirm page for rmdir & rmfile
if ($dir && !empty($_GET['remove']) && empty($_GET['noconfirm']))
{
	call_user_func($open_f,__('Media manager'),'',
		dcPage::breadcrumb(
			array(
				html::escapeHTML($core->blog->name) => '',
				__('Media manager') => '',
				__('confirm removal') => ''
			),
			array('home_link' => !$popup)
		)
	);

	echo
	'<form action="'.html::escapeURL($core->adminurl->get('admin.media')).'" method="post">'.
	'<p>'.sprintf(__('Are you sure you want to remove %s?'),
		html::escapeHTML($_GET['remove'])).'</p>'.
	'<p><input type="submit" value="'.__('Cancel').'" /> '.
	' &nbsp; <input type="submit" name="rmyes" value="'.__('Yes').'" />'.
	form::hidden('d',$d).
	form::hidden('q',$q).
	$core->adminurl->getHiddenFormFields('admin.media',$page_url_params).
	$core->formNonce().
	form::hidden('remove',html::escapeHTML($_GET['remove'])).'</p>'.
	'</form>';

	call_user_func($close_f);
	exit;
}

/* DISPLAY Main page
-------------------------------------------------------- */
$core->auth->user_prefs->addWorkspace('interface');
$user_ui_enhanceduploader = $core->auth->user_prefs->interface->enhanceduploader;

if (!isset($core->media)) {
	$breadcrumb = dcPage::breadcrumb(
		array(
			html::escapeHTML($core->blog->name) => '',
			__('Media manager') => ''
		),
		array('home_link' => !$popup)
	);
} else {
	$home_params = $page_url_params;
	$home_params['d']='';
	$home_params['q']='';
	if ($query || (!$query && $q)) {
		$count = $query ? count($dir['files']) : 0;
		$breadcrumb = dcPage::breadcrumb(
			array(
				html::escapeHTML($core->blog->name) => '',
				__('Media manager') => $core->adminurl->get('admin.media',$home_params),
				__('Search:').' '.$q.' ('.sprintf(__('%s file found','%s files found',$count),$count).')' => ''
			),
			array('home_link' => !$popup)
		);
	} else {
		$temp_params = $page_url_params;
		$temp_params['d']='%s';
		$bc_template = $core->adminurl->get('admin.media',$temp_params,'&amp;',true);
		$breadcrumb_media = $core->media->breadCrumb($bc_template,'<span class="page-title">%s</span>');
		if ($breadcrumb_media == '') {
			$breadcrumb = dcPage::breadcrumb(
				array(
					html::escapeHTML($core->blog->name) => '',
					__('Media manager') => $core->adminurl->get('admin.media',$home_params)
				),
				array('home_link' => !$popup)
			);
		} else {
			$home_params = $page_url_params;
			$home_params['d']='';

			$breadcrumb = dcPage::breadcrumb(
				array(
					html::escapeHTML($core->blog->name) => '',
					__('Media manager') => $core->adminurl->get('admin.media',$home_params),
					$breadcrumb_media => ''
				),
				array(
					'home_link' => !$popup,
					'hl' => false
				)
			);
		}
	}
}

// Recent media folders
$last_folders = '';
$last_folders_item = '';
$nb_last_dirs = (integer)($core->auth->user_prefs->interface->media_nb_last_dirs);
if ($nb_last_dirs > 0) {
	if (!is_array($last_dirs)) {
		$last_dirs = @unserialize($core->auth->user_prefs->interface->media_last_dirs);
	}
	if (is_array($last_dirs)) {
		foreach ($last_dirs as $ld) {
			$ld_params = $page_url_params;
			$ld_params['d'] = $ld;
			$last_folders_item .=
				'<option value="'.urldecode($core->adminurl->get('admin.media',$ld_params)).'"'.
				($ld == rtrim($d,'/') ? ' selected="selected"' : '').'>'.
				'/'.$ld.'</option>'."\n";
		}
		if ($last_folders_item != '') {
			$last_folders =
				'<p class="media-recent hidden-if-no-js">'.
				'<label class="classic" for="switchfolder">'.__('Goto recent folder:').'</label> '.
				'<select name="switchfolder" id="switchfolder">'.
				$last_folders_item.
				'</select>'.
				'<script type="text/javascript">var urlmenu = document.getElementById(\'switchfolder\');
				 urlmenu.onchange = function() { window.location = this.options[this.selectedIndex].value; };
				</script>'.
				'</p>';
		}
	}
}

call_user_func($open_f,__('Media manager'),
	dcPage::jsLoad('js/_media.js').
	($core_media_writable ? dcPage::jsUpload(array('d='.$d)) : ''),
	$breadcrumb
	);

if ($popup) {
	// Display notices
	echo dcPage::notices();
}

if (!$core_media_writable) {
	dcPage::warning(__('You do not have sufficient permissions to write to this folder.'));
}

if (!empty($_GET['mkdok'])) {
	dcPage::success(__('Directory has been successfully created.'));
}

if (!empty($_GET['upok'])) {
	dcPage::success(__('Files have been successfully uploaded.'));
}

if (!empty($_GET['rmfok'])) {
	dcPage::success(__('File has been successfully removed.'));
}

if (!empty($_GET['rmdok'])) {
	dcPage::success(__('Directory has been successfully removed.'));
}

if (!empty($_GET['rebuildok'])) {
	dcPage::success(__('Directory has been successfully rebuilt.'));
}

if (!empty($_GET['unzipok'])) {
	dcPage::success(__('Zip file has been successfully extracted.'));
}

if (!$dir) {
	call_user_func($close_f);
	exit;
}

if ($post_id) {
	echo '<div class="form-note info"><p>'.sprintf(__('Choose a file to attach to entry %s by clicking on %s'),
		'<a href="'.$core->getPostAdminURL($post_type,$post_id).'">'.html::escapeHTML($post_title).'</a>',
		'<img src="images/plus.png" alt="'.__('Attach this file to entry').'" />');
	if ($core_media_writable) {
		echo ' '.__('or').' '.sprintf('<a href="#fileupload">%s</a>',__('upload a new file'));
	}
	echo '</p></div>';
}
if ($popup) {
	echo '<div class="info"><p>'.sprintf(__('Choose a file to insert into entry by clicking on %s'),
		'<img src="images/plus.png" alt="'.__('Attach this file to entry').'" />');
	if ($core_media_writable) {
		echo ' '.__('or').' '.sprintf('<a href="#fileupload">%s</a>',__('upload a new file'));
	}
	echo '</p></div>';
}

// Remove hidden directories (unless DC_SHOW_HIDDEN_DIRS is set to true)
if (!defined('DC_SHOW_HIDDEN_DIRS') || (DC_SHOW_HIDDEN_DIRS == false)) {
	for ($i = count($dir['dirs']) - 1; $i >= 0; $i--) {
		if ($dir['dirs'][$i]->d) {
			if (strpos($dir['dirs'][$i]->relname,'.') !== false) {
				unset($dir['dirs'][$i]);
			}
		}
	}
}
$items = array_values(array_merge($dir['dirs'],$dir['files']));

$fmt_form_media = '<form action="'.$core->adminurl->get("admin.media").'" method="post" id="form-medias">'.
	'<div class="files-group">%s</div>'.
	'<p class="hidden">'.$core->formNonce().
	form::hidden(array('d'),$d).
	form::hidden(array('q'),$q).
	form::hidden(array('plugin_id'),$plugin_id).
	'</p>';

if (!$popup) {
	$fmt_form_media .=
	'<div class="medias-delete%s">'.
	'<p class="checkboxes-helpers"></p>'.
	'<p><input type="submit" class="delete" name="delete_medias" value="'.__('Remove selected medias').'"/></p>'.
	'</div>';
}
$fmt_form_media .=
	'</form>';

echo '<div class="media-list">';
echo $last_folders;

if (count($items) == 0)
{
	echo
	'<p>'.__('No file.').'</p>'.
	sprintf($fmt_form_media,'',' hide'); // need for jsUpload to append new media
}
else
{
	$pager = new dcPager($page,count($items),$nb_per_page,10);

	echo
	'<form action="'.$core->adminurl->get("admin.media").'" method="get" id="search-form">'.
	'<p><label for="search" class="classic">'.__('Search:').'</label> '.
	form::field('q',20,255,$q).' '.
	'<input type="submit" value="'.__('OK').'" />'.' '.
	'<span class="form-note">'.__('Will search into media filename (including path), title and description').'</span>'.
	form::hidden(array('popup'),$popup).
	form::hidden(array('plugin_id'),$plugin_id).
	form::hidden(array('post_id'),$post_id).
	'</p>'.
	'</form>'.
	'<form action="'.$core->adminurl->get("admin.media").'" method="get" id="filters-form">'.
	'<span class="media-file-mode">'.
	'<a href="'.$core->adminurl->get("admin.media",array_merge($page_url_params,array('file_mode' => 'grid'))).'" title="'.__('Grid display mode').'">'.
	'<img src="images/grid-'.($file_mode == 'grid' ? 'on' : 'off').'.png" alt="'.__('Grid display mode').'" />'.
	'</a>'.
	'<a href="'.$core->adminurl->get("admin.media",array_merge($page_url_params,array('file_mode' => 'list'))).'" title="'.__('List display mode').'">'.
	'<img src="images/list-'.($file_mode == 'list' ? 'on' : 'off').'.png" alt="'.__('List display mode').'" />'.
	'</a>'.
	'</span>'.
	'<p class="three-boxes"><label for="file_sort" class="classic">'.__('Sort files:').'</label> '.
	form::combo('file_sort',$sort_combo,$file_sort).'</p>'.
	'<p class="three-boxes"><label for="nb_per_page" class="classic">'.__('Number of elements displayed per page:').'</label> '.
	form::field('nb_per_page',5,3,(integer) $nb_per_page).' '.
	'<input type="submit" value="'.__('OK').'" />'.
	form::hidden(array('popup'),$popup).
	form::hidden(array('plugin_id'),$plugin_id).
	form::hidden(array('post_id'),$post_id).
	form::hidden(array('q'),$q).
	'</p>'.
	'</form>'.
	$pager->getLinks();

	if ($file_mode == 'list') {
		$table =
		'<div class="table-outer">'.
		'<table>'.
		'<caption class="hidden">'.__('Media list').'</caption>'.
		'<tr>'.
		'<th colspan="2" class="first">'.__('Name').'</th>'.
		'<th scope="col">'.__('Date').'</th>'.
		'<th scope="col">'.__('Size').'</th>'.
		'</tr>';

		$dlist = '';
		$flist = '';
		for ($i=$pager->index_start, $j=0; $i<=$pager->index_end; $i++,$j++)
		{
			if ($items[$i]->d) {
				$dlist .= mediaItemLine($items[$i],$j,$query,true);
			} else {
				$flist .= mediaItemLine($items[$i],$j,$query,true);
			}
		}
		$table .= $dlist.$flist;

		$table .=
		'</table></div>';
		echo sprintf($fmt_form_media,$table,'');
	} else {
		$dgroup = '';
		$fgroup = '';
		for ($i=$pager->index_start, $j=0; $i<=$pager->index_end; $i++,$j++)
		{
			if ($items[$i]->d) {
				$dgroup .= mediaItemLine($items[$i],$j,$query);
			} else {
				$fgroup .= mediaItemLine($items[$i],$j,$query);
			}
		}
		echo
		($dgroup != '' ? '<div class="folders-group">'.$dgroup.'</div>' : '').
		sprintf($fmt_form_media,$fgroup,'');
	}

	echo $pager->getLinks();
}
if (!isset($pager)) {
	echo
	'<p class="clear"></p>';
}
echo
'</div>';

$core_media_archivable = $core->auth->check('media_admin',$core->blog->id) &&
	!(count($items) == 0 || (count($items) == 1 && $items[0]->parent));

if ((!$query) && ($core_media_writable || $core_media_archivable)) {
	echo
	'<div class="vertical-separator">'.
	'<h3 class="out-of-screen-if-js">'.sprintf(__('In %s:'),($d == '' ? '“'.__('Media manager').'”' : '“'.$d.'”')).'</h3>';
}

if ((!$query) && ($core_media_writable || $core_media_archivable)) {
	echo
	'<div class="two-boxes odd">';

	# Create directory
	if ($core_media_writable)
	{
		echo
		'<form action="'.$core->adminurl->getBase('admin.media').'" method="post" class="fieldset">'.
		'<div id="new-dir-f">'.
		'<h4 class="pretty-title">'.__('Create new directory').'</h4>'.
		$core->formNonce().
		'<p><label for="newdir">'.__('Directory Name:').'</label>'.
		form::field(array('newdir','newdir'),35,255).'</p>'.
		'<p><input type="submit" value="'.__('Create').'" />'.
		$core->adminurl->getHiddenFormFields('admin.media',$page_url_params).
		'</p>'.
		'</div>'.
		'</form>';
	}

	# Get zip directory
	if ($core_media_archivable && !$popup)
	{
		echo
		'<div class="fieldset">'.
		'<h4 class="pretty-title">'.sprintf(__('Backup content of %s'),($d == '' ? '“'.__('Media manager').'”' : '“'.$d.'”')).'</h4>'.
		'<p><a class="button submit" href="'.$core->adminurl->get('admin.media',
			array_merge($page_url_params,array('zipdl' => 1))).'">'.__('Download zip file').'</a></p>'.
		'</div>';
	}

	echo
	'</div>';
}

if (!$query && $core_media_writable)
{
	echo
	'<div class="two-boxes fieldset even">';
	if ($user_ui_enhanceduploader) {
		echo
		'<div class="enhanced_uploader">';
	} else {
		echo
		'<div>';
	}

	echo
	'<h4>'.__('Add files').'</h4>'.
	'<p>'.__('Please take care to publish media that you own and that are not protected by copyright.').'</p>'.
	'<form id="fileupload" action="'.html::escapeURL($page_url).'" method="post" enctype="multipart/form-data" aria-disabled="false">'.
	'<p>'.form::hidden(array('MAX_FILE_SIZE'),DC_MAX_UPLOAD_SIZE).
	$core->formNonce().'</p>'.
	'<div class="fileupload-ctrl"><p class="queue-message"></p><ul class="files"></ul></div>';

	echo
	'<div class="fileupload-buttonbar clear">';

	echo
	'<p><label for="upfile">'.'<span class="add-label one-file">'.__('Choose file').'</span>'.'</label>'.
	'<button class="button choose_files">'.__('Choose files').'</button>'.
	'<input type="file" id="upfile" name="upfile[]"'.($user_ui_enhanceduploader?' multiple="mutiple"':'').' data-url="'.html::escapeURL($page_url).'" /></p>';

	echo
	'<p class="max-sizer form-note">&nbsp;'.__('Maximum file size allowed:').' '.files::size(DC_MAX_UPLOAD_SIZE).'</p>';

	echo
	'<p class="one-file"><label for="upfiletitle">'.__('Title:').'</label>'.form::field(array('upfiletitle','upfiletitle'),35,255).'</p>'.
	'<p class="one-file"><label for="upfilepriv" class="classic">'.__('Private').'</label> '.
	form::checkbox(array('upfilepriv','upfilepriv'),1).'</p>';

	if (!$user_ui_enhanceduploader) {
		echo
		'<p class="one-file form-help info">'.__('To send several files at the same time, you can activate the enhanced uploader in').
		' <a href="'.$core->adminurl->get("admin.user.preferences",array('tab' => 'user-options')).'">'.__('My preferences').'</a></p>';
	}

	echo
	'<p class="clear"><button class="button clean">'.__('Refresh').'</button>'.
	'<input class="button cancel one-file" type="reset" value="'.__('Clear all').'"/>'.
	'<input class="button start" type="submit" value="'.__('Upload').'"/></p>'.
	'</div>';

	echo
	'<p style="clear:both;">'.form::hidden(array('d'),$d).'</p>'.
	'</form>'.
	'</div>'.
	'</div>';
}

# Empty remove form (for javascript actions)
echo
'<form id="media-remove-hide" action="'.html::escapeURL($page_url).'" method="post" class="hidden">'.
'<div>'.
form::hidden('rmyes',1).form::hidden('d',html::escapeHTML($d)).
form::hidden(array('plugin_id'),$plugin_id).form::hidden('remove','').
form::hidden(array('q'),$q).
$core->formNonce().
'</div>'.
'</form>';

if ((!$query) && ($core_media_writable || $core_media_archivable)) {
	echo
	'</div>';
}

if (!$popup) {
	echo '<div class="info"><p>'.sprintf(__('Current settings for medias and images are defined in %s'),
	'<a href="'.$core->adminurl->get("admin.blog.pref").'#medias-settings">'.__('Blog parameters').'</a>').'</p></div>';
}

call_user_func($close_f);

/* ----------------------------------------------------- */
function mediaItemLine($f,$i,$query,$table=false)
{
	global $core, $page_url, $popup, $post_id, $plugin_id,$page_url_params;

	$fname = $f->basename;
	$file = $query ? $f->relname : $f->basename;

	$class = $table ? '' : 'media-item media-col-'.($i%2);

	if ($f->d) {

		$link = $core->adminurl->get('admin.media',array_merge($page_url_params,array('d' => html::sanitizeURL($f->relname) )));
		if ($f->parent) {
			$fname = '..';
			$class .= ' media-folder-up';
		} else {
			$class .= ' media-folder';
		}
	} else {
		$params = new ArrayObject(
			array(
				'id' => $f->media_id,
				'plugin_id' => $plugin_id,
				'popup' => $popup,
				'post_id' => $post_id
			)
		);
		$core->callBehavior('adminMediaURLParams',$params);
		$params = (array) $params;
		$link = $core->adminurl->get(
			'admin.media.item', $params
		);
	}

	$maxchars = 36;
	if (strlen($fname) > $maxchars) {
		$fname = substr($fname, 0, $maxchars-4).'...'.($f->d ? '' : files::getExtension($fname));
	}

	$act = '';
	if ($post_id && !$f->d) {
		// Media attachment button
		$act .=
		'<a class="attach-media" title="'.__('Attach this file to entry').'" href="'.
		$core->adminurl->get("admin.post.media", array('media_id' => $f->media_id, 'post_id' => $post_id,'attach' => 1)).
		'">'.
		'<img src="images/plus.png" alt="'.__('Attach this file to entry').'"/>'.
		'</a>';
	}
	if ($popup && !$f->d) {
		// Media insertion button
		$act .= '<a href="'.$link.'"><img src="images/plus.png" alt="'.__('Insert this file into entry').'" '.
		'title="'.__('Insert this file into entry').'" /></a> ';
	}
	if ($f->del) {
		// Deletion button or checkbox
		if (!$popup && !$f->d) {
			$act .= form::checkbox(array('medias[]', 'media_'.rawurlencode($file)),$file);
		} else {
			$act .= '<a class="media-remove" '.
			'href="'.html::escapeURL($page_url).
			'&amp;plugin_id='.$plugin_id.
			'&amp;d='.rawurlencode($GLOBALS['d']).
			'&amp;q='.rawurlencode($GLOBALS['q']).
			'&amp;remove='.rawurlencode($file).'">'.
			'<img src="images/trash.png" alt="'.__('Delete').'" title="'.__('delete').'" /></a>';
		}
	}

	if (!$table) {
		$res =
		'<div class="'.$class.'"><p><a class="media-icon media-link" href="'.rawurldecode($link).'">'.
		'<img src="'.$f->media_icon.'" alt="" />'.($query ? $file : $fname).'</a></p>';

		$lst = '';
		if (!$f->d) {
			$lst .=
			'<li>'.$f->media_title.'</li>'.
			'<li>'.
			$f->media_dtstr.' - '.
			files::size($f->size).' - '.
			'<a href="'.$f->file_url.'">'.__('open').'</a>'.
			'</li>';
		}
		$lst .= ($act != '' ? '<li class="media-action">&nbsp;'.$act.'</li>' : '');

		// Show player if relevant
		$file_type = explode('/',$f->type);
		if ($file_type[0] == 'audio')
		{
			$lst .= '<li>'.dcMedia::audioPlayer($f->type,$f->file_url,$core->adminurl->get("admin.home",array('pf' => 'player_mp3.swf'))).'</li>';
		}

		$res .=	($lst != '' ? '<ul>'.$lst.'</ul>' : '');
		$res .= '</div>';
	} else {
		$res = '<tr class="'.$class.'">';
		$res .= '<td class="media-action">'.$act.'</td>';
		$res .= '<td class="maximal" scope="row"><a class="media-flag media-link" href="'.rawurldecode($link).'">'.
				'<img src="'.$f->media_icon.'" alt="" />'.($query ? $file : $fname).'</a>'.
				'<br />'.($f->d ? '' : $f->media_title).'</td>';
		$res .= '<td class="nowrap count">'.($f->d ? '' : $f->media_dtstr).'</td>';
		$res .= '<td class="nowrap count">'.($f->d ? '' : files::size($f->size).' - '.'<a href="'.$f->file_url.'">'.__('open').'</a>').'</td>';
		$res .= '</tr>';
	}

	return $res;
}
