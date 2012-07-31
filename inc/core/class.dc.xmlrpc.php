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

class dcXmlRpc extends xmlrpcIntrospectionServer
{
	public $core;
	private $blog_id;
	private $blog_loaded = false;
	private $debug = false;
	private $debug_file = '/tmp/dotclear-xmlrpc.log';
	private $trace_args = true;
	private $trace_response = true;
	
	public function __construct($core,$blog_id)
	{
		parent::__construct();
		
		$this->core =& $core;
		$this->blog_id = $blog_id;
		
		# Blogger methods
		$this->addCallback('blogger.newPost',array($this,'blogger_newPost'),
			array('string','string','string','string','string','string','integer'),
			'New post');
		
		$this->addCallback('blogger.editPost',array($this,'blogger_editPost'),
			array('boolean','string','string','string','string','string','integer'),
			'Edit a post');
		
		$this->addCallback('blogger.getPost',array($this,'blogger_getPost'),
			array('struct','string','integer','string','string'),
			'Return a posts by ID');
		
		$this->addCallback('blogger.deletePost',array($this,'blogger_deletePost'),
			array('string','string','string','string','string','integer'),
			'Delete a post');
		
		$this->addCallback('blogger.getRecentPosts',array($this,'blogger_getRecentPosts'),
			array('array','string','string','string','string','integer'),
			'Return a list of recent posts');
		
		$this->addCallback('blogger.getUsersBlogs',array($this,'blogger_getUserBlogs'),
			array('struct','string','string','string'),
			"Return user's blog");
		
		$this->addCallback('blogger.getUserInfo',array($this,'blogger_getUserInfo'),
			array('struct','string','string','string'),
			'Return User Info');
		
		# Metaweblog methods
		$this->addCallback('metaWeblog.newPost',array($this,'mw_newPost'),
			array('string','string','string','string','struct','boolean'),
			'Creates a new post, and optionnaly publishes it.');
		
		$this->addCallback('metaWeblog.editPost',array($this,'mw_editPost'),
			array('boolean','string','string','string','struct','boolean'),
			'Updates information about an existing entry');
		
		$this->addCallback('metaWeblog.getPost',array($this,'mw_getPost'),
			array('struct','string','string','string'),
			'Returns information about a specific post');
		
		$this->addCallback('metaWeblog.getRecentPosts',array($this,'mw_getRecentPosts'),
			array('array','string','string','string','integer'),
			'List of most recent posts in the system');
		
		$this->addCallback('metaWeblog.newMediaObject',array($this,'mw_newMediaObject'),
			array('struct','string','string','string','struct'),
			'Upload a file on the web server');
		
		# MovableType methods
		$this->addCallback('mt.getRecentPostTitles',array($this,'mt_getRecentPostTitles'),
			array('array','string','string','string','integer'),
			'List of most recent posts in the system');
		
		$this->addCallback('mt.publishPost',array($this,'mt_publishPost'),
			array('boolean','string','string','string'),
			'Retrieve pings list for a post');
		
		$this->addCallback('mt.supportedMethods',array($this,'listMethods'),
			array(),'Retrieve information about the XML-RPC methods supported by the server.');
		
		$this->addCallback('mt.supportedTextFilters',array($this,'mt_supportedTextFilters'),
			array(),'Retrieve information about supported text filters.');
		
		# WordPress methods
		$this->addCallback('wp.getUsersBlogs',array($this,'wp_getUsersBlogs'),
			array('array','string','string'),
			'Retrieve the blogs of the user.');
		
		$this->addCallback('wp.getPage',array($this,'wp_getPage'),
			array('struct','integer','integer','string','string'),
			'Get the page identified by the page ID.');
		
		$this->addCallback('wp.getPages',array($this,'wp_getPages'),
			array('array','integer','string','string','integer'),
			'Get an array of all the pages on a blog.');
		
		$this->addCallback('wp.newPage',array($this,'wp_newPage'),
			array('integer','integer','string','string','struct','boolean'),
			'Create a new page.');
		
		$this->addCallback('wp.deletePage',array($this,'wp_deletePage'),
			array('boolean','integer','string','string','integer'),
			'Removes a page from the blog.');
		
		$this->addCallback('wp.editPage',array($this,'wp_editPage'),
			array('boolean','integer','integer','string','string','struct','boolean'),
			'Make changes to a blog page.');
		
		$this->addCallback('wp.getPageList',array($this,'wp_getPageList'),
			array('array','integer','string','string'),
			'Get an array of all the pages on a blog. Just the minimum details, lighter than wp.getPages.');
		
		$this->addCallback('wp.getAuthors',array($this,'wp_getAuthors'),
			array('array','integer','string','string'),
			'Get an array of users for the blog.');
		
		$this->addCallback('wp.getTags',array($this,'wp_getTags'),
			array('array','integer','string','string'),
			'Get list of all tags for the blog.');
		
		$this->addCallback('wp.uploadFile',array($this,'wp_uploadFile'),
			array('struct','integer','string','string','struct'),
			'Upload a file');
		
		$this->addCallback('wp.getPostStatusList',array($this,'wp_getPostStatusList'),
			array('array','integer','string','string'),
			'Retrieve all of the post statuses.');
		
		$this->addCallback('wp.getPageStatusList',array($this,'wp_getPageStatusList'),
			array('array','integer','string','string'),
			'Retrieve all of the pages statuses.');
		
		$this->addCallback('wp.getPageTemplates',array($this,'wp_getPageTemplates'),
			array('struct','integer','string','string'),
			'Retrieve page templates.');
		
		$this->addCallback('wp.getOptions',array($this,'wp_getOptions'),
			array('struct','integer','string','string','array'),
			'Retrieve blog options');
		
		$this->addCallback('wp.setOptions',array($this,'wp_setOptions'),
			array('struct','integer','string','string','struct'),
			'Update blog options');
		
	}
	
	public function serve($data=false,$encoding='UTF-8')
	{
		parent::serve(false,$encoding);
	}
	
	public function call($methodname,$args)
	{
		try {
			$rsp = @parent::call($methodname,$args);
			$this->debugTrace($methodname,$args,$rsp);
			return $rsp;
		} catch (Exception $e) {
			$this->debugTrace($methodname,$args,array($e->getMessage(),$e->getCode()));
			throw $e;
		}
	}
	
	private function debugTrace($methodname,$args,$rsp)
	{
		if (!$this->debug) {
			return;
		}
		
		if (($fp = @fopen($this->debug_file,'a')) !== false)
		{
			fwrite($fp,'['.date('r').']'.' '.$methodname);
			
			if ($this->trace_args) {
				fwrite($fp,"\n- args ---\n".var_export($args,1));
			}
			
			if ($this->trace_response) {
				fwrite($fp,"\n- response ---\n".var_export($rsp,1));
			}
			fwrite($fp,"\n");
			fclose($fp);
		}
	}
	
	/* Internal methods
	--------------------------------------------------- */
	private function setUser($user_id,$pwd)
	{
		if ($this->core->auth->userID() == $user_id) {
			return true;
		}
		
		if ($this->core->auth->checkUser($user_id,$pwd) !== true) {
			throw new Exception('Login error');
		}
		
		return true;
	}
	
	private function setBlog()
	{
		if (!$this->blog_id) {
			throw new Exception('No blog ID given.');
		}
		
		if ($this->blog_loaded) {
			return true;
		}
		
		$this->core->setBlog($this->blog_id);
		$this->blog_loaded = true;
		
		if (!$this->core->blog->id) {
			$this->core->blog = null;
			throw new Exception('Blog does not exist.');
		}
		
		if (!$this->core->blog->settings->system->enable_xmlrpc ||
		!$this->core->auth->check('usage,contentadmin',$this->core->blog->id)) {
			$this->core->blog = null;
			throw new Exception('Not enough permissions on this blog.');
		}
		
		foreach ($this->core->plugins->getModules() as $id => $m) {
			$this->core->plugins->loadNsFile($id,'xmlrpc');
		}
		
		return true;
	}
	
	private function getPostRS($post_id,$user,$pwd,$post_type='post')
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		$rs = $this->core->blog->getPosts(array(
			'post_id' => (integer) $post_id,
			'post_type' => $post_type
		));
		
		if ($rs->isEmpty()) {
			throw new Exception('This entry does not exist');
		}
		
		return $rs;
	}
	
	/* Generic methods
	--------------------------------------------------- */
	private function newPost($blog_id,$user,$pwd,$content,$struct=array(),$publish=true)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		
		$title = !empty($struct['title']) ? $struct['title'] : '';
		$excerpt = !empty($struct['mt_excerpt']) ? $struct['mt_excerpt'] : '';
		$description = !empty($struct['description']) ? $struct['description'] : null;
		$dateCreated = !empty($struct['dateCreated']) ? $struct['dateCreated'] : null;
		
		if ($description !== null) {
			$content = $description;
		}
		
		if (!$title) {
			$title = text::cutString(html::clean($content),25).'...';
		}
		
		$excerpt_xhtml = $this->core->callFormater('xhtml',$excerpt);
		$content_xhtml = $this->core->callFormater('xhtml',$content);
		
		if (empty($content)) {
			throw new Exception('Cannot create an empty entry');
		}
		
		$cur = $this->core->con->openCursor($this->core->prefix.'post');
		
		$cur->user_id = $this->core->auth->userID();
		$cur->post_lang = $this->core->auth->getInfo('user_lang');
		$cur->post_title = trim($title);
		$cur->post_content = $content;
		$cur->post_excerpt = $excerpt;
		$cur->post_content_xhtml = $content_xhtml;
		$cur->post_excerpt_xhtml = $excerpt_xhtml;
		$cur->post_status = (integer) $publish;
		$cur->post_format = 'xhtml';
		
		if ($dateCreated) {
			if ($dateCreated instanceof xmlrpcDate) {
				$cur->post_dt = date('Y-m-d H:i:00',$dateCreated->getTimestamp());
			} elseif (is_string($dateCreated) && @strtotime($dateCreated)) {
				$cur->post_dt = date('Y-m-d H:i:00',strtotime($dateCreated));
			}
		}
		
		if (isset($struct['wp_slug'])) {
			$cur->post_url = $struct['wp_slug'];
		}
		
		if (isset($struct['wp_password'])) {
			$cur->post_password = $struct['wp_password'];
		}
		
		$cur->post_type = 'post';
		if (!empty($struct['post_type'])) {
			$cur->post_type = $struct['post_type'];
		}
		
		if ($cur->post_type == 'post')
		{
			# --BEHAVIOR-- xmlrpcBeforeNewPost
			$this->core->callBehavior('xmlrpcBeforeNewPost',$this,$cur,$content,$struct,$publish);
			
			$post_id = $this->core->blog->addPost($cur);
			
			# --BEHAVIOR-- xmlrpcAfterNewPost
			$this->core->callBehavior('xmlrpcAfterNewPost',$this,$post_id,$cur,$content,$struct,$publish);
		}
		elseif ($cur->post_type == 'page')
		{
			if (isset($struct['wp_page_order'])) {
				$cur->post_position = (integer) $struct['wp_page_order'];
			}
			
			$this->core->blog->settings->system->post_url_format = '{t}';
			
			$post_id = $this->core->blog->addPost($cur);
		}
		else
		{
			throw new Exception('Invalid post type',401);
		}
		
		return (string) $post_id;
	}
	
	private function editPost($post_id,$user,$pwd,$content,$struct=array(),$publish=true)
	{
		$post_id = (integer) $post_id;
		
		$post_type = 'post';
		if (!empty($struct['post_type'])) {
			$post_type = $struct['post_type'];
		}
		
		$post = $this->getPostRS($post_id,$user,$pwd,$post_type);
		
		$title = (!empty($struct['title'])) ? $struct['title'] : '';
		$excerpt = (!empty($struct['mt_excerpt'])) ? $struct['mt_excerpt'] : '';
		$description = (!empty($struct['description'])) ? $struct['description'] : null;
		$dateCreated = !empty($struct['dateCreated']) ? $struct['dateCreated'] : null;
		
		if ($description !== null) {
			$content = $description;
		}
		
		if (!$title) {
			$title = text::cutString(html::clean($content),25).'...';
		}
		
		$excerpt_xhtml = $this->core->callFormater('xhtml',$excerpt);
		$content_xhtml = $this->core->callFormater('xhtml',$content);
		
		if (empty($content)) {
			throw new Exception('Cannot create an empty entry');
		}
		
		$cur = $this->core->con->openCursor($this->core->prefix.'post');
		
		$cur->post_type = $post_type;
		$cur->post_title = trim($title);
		$cur->post_content = $content;
		$cur->post_excerpt = $excerpt;
		$cur->post_content_xhtml = $content_xhtml;
		$cur->post_excerpt_xhtml = $excerpt_xhtml;
		$cur->post_status = (integer) $publish;
		$cur->post_format = 'xhtml';
		$cur->post_url = $post->post_url;
		
		
		if ($dateCreated) {
			if ($dateCreated instanceof xmlrpcDate) {
				$cur->post_dt = date('Y-m-d H:i:00',$dateCreated->getTimestamp());
			} elseif (is_string($dateCreated) && @strtotime($dateCreated)) {
				$cur->post_dt = date('Y-m-d H:i:00',strtotime($dateCreated));
			}
		} else {
			$cur->post_dt = $post->post_dt;
		}
		
		if (isset($struct['wp_slug'])) {
			$cur->post_url = $struct['wp_slug'];
		}
		
		if (isset($struct['wp_password'])) {
			$cur->post_password = $struct['wp_password'];
		}
		
		if ($cur->post_type == 'post')
		{
			# --BEHAVIOR-- xmlrpcBeforeEditPost
			$this->core->callBehavior('xmlrpcBeforeEditPost',$this,$post_id,$cur,$content,$struct,$publish);
			
			$this->core->blog->updPost($post_id,$cur);
			
			# --BEHAVIOR-- xmlrpcAfterEditPost
			$this->core->callBehavior('xmlrpcAfterEditPost',$this,$post_id,$cur,$content,$struct,$publish);
		}
		elseif ($cur->post_type == 'page')
		{
			if (isset($struct['wp_page_order'])) {
				$cur->post_position = (integer) $struct['wp_page_order'];
			}
			
			$this->core->blog->settings->system->post_url_format = '{t}';
			
			$this->core->blog->updPost($post_id,$cur);
		}
		else
		{
			throw new Exception('Invalid post type',401);
		}
		
		return true;
	}
	
	private function getPost($post_id,$user,$pwd,$type='mw')
	{
		$post_id = (integer) $post_id;
		
		$post = $this->getPostRS($post_id,$user,$pwd);
		
		$res = new ArrayObject();
		
		$res['dateCreated'] = new xmlrpcDate($post->getTS());
		$res['userid'] = $post->user_id;
		$res['postid'] = $post->post_id;
		
		if ($type == 'blogger') {
			$res['content'] = $post->post_content_xhtml;
		}
		
		if ($type == 'mt' || $type == 'mw') {
			$res['title'] = $post->post_title;
		}
		
		if ($type == 'mw') {
			$res['description'] = $post->post_content_xhtml;
			$res['link'] = $res['permaLink'] = $post->getURL();
			$res['mt_excerpt'] = $post->post_excerpt_xhtml;
			$res['mt_text_more'] = '';
			$res['mt_convert_breaks'] = '';
			$res['mt_keywords'] = '';
		}
		
		# --BEHAVIOR-- xmlrpcGetPostInfo
		$this->core->callBehavior('xmlrpcGetPostInfo',$this,$type,array(&$res));
		
		return $res;
	}
	
	private function deletePost($post_id,$user,$pwd)
	{
		$post_id = (integer) $post_id;
		
		$this->getPostRS($post_id,$user,$pwd);
		$this->core->blog->delPost($post_id);
		
		return true;
	}
	
	private function getRecentPosts($blog_id,$user,$pwd,$nb_post,$type='mw')
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		
		$nb_post = (integer) $nb_post;
		
		if ($nb_post > 50) {
			throw new Exception('Cannot retrieve more than 50 entries');
		}
		
		$params = array();
		$params['limit'] = $nb_post;
		
		$posts = $this->core->blog->getPosts($params);
		
		$res = array();
		while ($posts->fetch())
		{
			$tres = array();
			
			$tres['dateCreated'] = new xmlrpcDate($posts->getTS());
			$tres['userid'] = $posts->user_id;
			$tres['postid'] = $posts->post_id;
			
			if ($type == 'blogger') {
				$tres['content'] = $posts->post_content_xhtml;
			}
			
			if ($type == 'mt' || $type == 'mw') {
				$tres['title'] = $posts->post_title;
			}
			
			if ($type == 'mw') {
				$tres['description'] = $posts->post_content_xhtml;
				$tres['link'] = $tres['permaLink'] = $posts->getURL();
				$tres['mt_excerpt'] = $posts->post_excerpt_xhtml;
				$tres['mt_text_more'] = '';
				$tres['mt_convert_breaks'] = '';
				$tres['mt_keywords'] = '';
			}
			
			# --BEHAVIOR-- xmlrpcGetPostInfo
			$this->core->callBehavior('xmlrpcGetPostInfo',$this,$type,array(&$tres));
			
			$res[] = $tres;
		}
		
		return $res;
	}
	
	private function getUserBlogs($user,$pwd)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		
		return array(array(
			'url' => $this->core->blog->url,
			'blogid' => '1',
			'blogName' => $this->core->blog->name
		));
	}
	
	private function getUserInfo($user,$pwd)
	{
		$this->setUser($user,$pwd);
		
		return array(
			'userid' => $this->core->auth->userID(),
			'firstname' => $this->core->auth->getInfo('user_firstname'),
			'lastname' => $this->core->auth->getInfo('user_name'),
			'nickname' => $this->core->auth->getInfo('user_displayname'),
			'email' => $this->core->auth->getInfo('user_email'),
			'url' => $this->core->auth->getInfo('user_url')
		);
	}
	
	private function publishPost($post_id,$user,$pwd)
	{
		$post_id = (integer) $post_id;
		
		$this->getPostRS($post_id,$user,$pwd);
		
		# --BEHAVIOR-- xmlrpcBeforePublishPost
		$this->core->callBehavior('xmlrpcBeforePublishPost',$this,$post_id);
		
		$this->core->blog->updPostStatus($post_id,1);
		
		# --BEHAVIOR-- xmlrpcAfterPublishPost
		$this->core->callBehavior('xmlrpcAfterPublishPost',$this,$post_id);
		
		return true;
	}
	
	private function newMediaObject($blog_id,$user,$pwd,$file)
	{
		if (empty($file['name'])) {
			throw new Exception('No file name');
		}
		
		if (empty($file['bits'])) {
			throw new Exception('No file content');
		}
		
		$file_name = $file['name'];
		$file_bits = $file['bits'];
		
		$this->setUser($user,$pwd);
		$this->setBlog();
		
		$media = new dcMedia($this->core);
		
		$dir_name = path::clean(dirname($file_name));
		$file_name = basename($file_name);
		
		$dir_name = preg_replace('!^/!','',$dir_name);
		if ($dir_name != '')
		{
			$dir = explode('/',$dir_name);
			$cwd = './';
			foreach ($dir as $v)
			{
				$v = files::tidyFileName($v);
				$cwd .= $v.'/';
				$media->makeDir($v);
				$media->chdir($cwd);
			}
		}
		
		$media_id = $media->uploadBits($file_name,$file_bits);
		
		$f = $media->getFile($media_id);
		return array(
			'file' => $file_name,
			'url' => $f->file_url,
			'type' => files::getMimeType($file_name)
		);
	}
	
	private function translateWpStatus($s)
	{
		$status = array(
			'draft' => -2,
			'pending' => -2,
			'private' => 0,
			'publish' => 1,
			'scheduled' => -1
		);
		
		if (is_int($s)) {
			$status = array_flip($status);
			return isset($status[$s]) ? $status[$s] : $status[-2];
		} else {
			return isset($status[$s]) ? $status[$s] : $status['pending'];
		}
	}
	
	
	private function translateWpOptions($options=array())
	{
		$timezone = 0;
		if ($this->core->blog->settings->system->blog_timezone) {
			$timezone = dt::getTimeOffset($this->core->blog->settings->system->blog_timezone)/3600;
		}
		
		$res = array (
		    'software_name' => array (
				'desc' => 'Software Name',
				'readonly' => true,
				'value' => 'Dotclear'
			),
			'software_version' => array (
				'desc' => 'Software Version',
				'readonly' => true,
				'value' => DC_VERSION
			),
			'blog_url' => array (
				'desc' => 'Blog URL',
				'readonly' => true,
				'value' => $this->core->blog->url
			),
			'time_zone' => array (
				'desc' => 'Time Zone',
				'readonly' => true,
				'value' => (string) $timezone
			),
			'blog_title' => array (
				'desc' => 'Blog Title',
				'readonly' => false,
				'value' => $this->core->blog->name
			),
			'blog_tagline' => array (
				'desc' => 'Blog Tagline',
				'readonly' => false,
				'value' => $this->core->blog->desc
			),
			'date_format' => array (
				'desc' => 'Date Format',
				'readonly' => false,
				'value' => $this->core->blog->settings->system->date_format
			),
			'time_format' => array (
				'desc' => 'Time Format',
				'readonly' => false,
				'value' => $this->core->blog->settings->system->time_format
			)
		);
		
		if (!empty($options))
		{
			$r = array();
			foreach ($options as $v) {
				if (isset($res[$v])) {
					$r[$v] = $res[$v];
				}
			}
			return $r;
		}
		
		return $res;
	}
	
	private function getPostStatusList($blog_id,$user,$pwd)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		
		return array(
			'draft' => 'Draft',
			'pending' => 'Pending Review',
			'private' => 'Private',
			'publish' => 'Published',
			'scheduled' => 'Scheduled'
		);
	}
	
	private function getPageStatusList($blog_id,$user,$pwd)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		$this->checkPagesPermission();
		
		return array(
			'draft' => 'Draft',
			'private' => 'Private',
			'published' => 'Published',
			'scheduled' => 'Scheduled'
		);
	}
	
	private function checkPagesPermission()
	{
		if (!$this->core->plugins->moduleExists('pages')) {
			throw new Exception('Pages management is not available on this blog.');
		}
		
		if (!$this->core->auth->check('pages,contentadmin',$this->core->blog->id)) {
			throw new Exception('Not enough permissions to edit pages.',401);
		}
	}
	
	private function getPages($blog_id,$user,$pwd,$limit=null,$id=null)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		$this->checkPagesPermission();
		
		$params = array(
			'post_type' => 'page',
			'order' => 'post_position ASC, post_title ASC'
		);
		
		if ($id) {
			$params['post_id'] = (integer) $id;
		}
		if ($limit) {
			$params['limit'] = $limit;
		}
		
		$posts = $this->core->blog->getPosts($params);
		
		$res = array();
		while ($posts->fetch())
		{
			$tres = array(
				"dateCreated"			=> new xmlrpcDate($posts->getTS()),
				"userid"				=> $posts->user_id,
				"page_id"				=> $posts->post_id,
				"page_status"			=> $this->translateWpStatus((integer) $posts->post_status),
				"description"			=> $posts->post_content_xhtml,
				"title"				=> $posts->post_title,
				"link"				=> $posts->getURL(),
				"permaLink"			=> $posts->getURL(),
				"excerpt"				=> $posts->post_excerpt_xhtml,
				"text_more"			=> '',
				"wp_slug"				=> $posts->post_url,
				"wp_password"			=> $posts->post_password,
				"wp_author"			=> $posts->getAuthorCN(),
				"wp_page_parent_id"		=> 0,
				"wp_page_parent_title"	=> '',
				"wp_page_order"		=> $posts->post_position,
				"wp_author_id"			=> $posts->user_id,
				"wp_author_display_name"	=> $posts->getAuthorCN(),
				"date_created_gmt"		=> new xmlrpcDate(dt::iso8601($posts->getTS(),$posts->post_tz)),
				"custom_fields"		=> array(),
				"wp_page_template"		=> 'default'
			);
			
			# --BEHAVIOR-- xmlrpcGetPageInfo
			$this->core->callBehavior('xmlrpcGetPageInfo',$this,array(&$tres));
			
			$res[] = $tres;
		}
		
		return $res;
	}
	
	private function newPage($blog_id,$user,$pwd,$struct,$publish)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		$this->checkPagesPermission();
		
		$struct['post_type'] = 'page';
		
		return $this->newPost($blog_id,$user,$pwd,null,$struct,$publish);
	}
	
	private function editPage($page_id,$user,$pwd,$struct,$publish)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		$this->checkPagesPermission();
		
		$struct['post_type'] = 'page';
		
		return $this->editPost($page_id,$user,$pwd,null,$struct,$publish);
	}
	
	private function deletePage($page_id,$user,$pwd)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		$this->checkPagesPermission();
		
		$page_id = (integer) $page_id;
		
		$this->getPostRS($page_id,$user,$pwd,'page');
		$this->core->blog->delPost($page_id);
		
		return true;
	}
	
	private function getAuthors($user,$pwd)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		
		$rs = $this->core->getBlogPermissions($this->core->blog->id);
		$res = array();
		
		foreach($rs as $k => $v)
		{
			$res[] = array(
				'user_id' => $k,
				'user_login' => $k,
				'display_name' => dcUtils::getUserCN($k,$v['name'],$v['firstname'],$v['displayname'])
			);
		}
		return $res;
	}
	
	private function getTags($user,$pwd)
	{
		$this->setUser($user,$pwd);
		$this->setBlog();
		
		$tags = $this->core->meta->getMeta('tag');
		$tags->sort('meta_id_lower','asc');
		
		$res = array();
		$url   = $this->core->blog->url.
			$this->core->url->getURLFor('tag','%s');
		$f_url = $this->core->blog->url.
			$this->core->url->getURLFor('tag_feed','%s');
		while ($tags->fetch())
		{
			$res[] = array(
				'tag_id'		=> $tags->meta_id,
				'name'		=> $tags->meta_id,
				'count'		=> $tags->count,
				'slug'		=> $tags->meta_id,
				'html_url'	=> sprintf($url,$tags->meta_id),
				'rss_url'		=> sprintf($f_url,$tags->meta_id)
			);
		}
		return $res;
	}
	
	/* Blogger methods
	--------------------------------------------------- */
	public function blogger_newPost($appkey,$blogid,$username,$password,$content,$publish)
	{
		return $this->newPost($blogid,$username,$password,$content,array(),$publish);
	}
	
	public function blogger_editPost($appkey,$postid,$username,$password,$content,$publish)
	{
		return $this->editPost($postid,$username,$password,$content,array(),$publish);
	}
	
	public function blogger_getPost($appkey,$postid,$username,$password)
	{
		return $this->getPost($postid,$username,$password,'blogger');
	}
	
	public function blogger_deletePost($appkey,$postid,$username,$password,$publish)
	{
		return $this->deletePost($postid,$username,$password);
	}
	
	public function blogger_getRecentPosts($appkey,$blogid,$username,$password,$numberOfPosts)
	{
		return $this->getRecentPosts($blogid,$username,$password,$numberOfPosts,'blogger');
	}
	
	public function blogger_getUserBlogs($appkey,$username,$password)
	{
		return $this->getUserBlogs($username,$password);
	}
	
	public function blogger_getUserInfo($appkey,$username,$password)
	{
		return $this->getUserInfo($username,$password);
	}
	
	
	/* Metaweblog methods
	------------------------------------------------------- */
	public function mw_newPost($blogid,$username,$password,$content,$publish)
	{
		return $this->newPost($blogid,$username,$password,'',$content,$publish);
	}
	
	public function mw_editPost($postid,$username,$password,$content,$publish)
	{
		return $this->editPost($postid,$username,$password,'',$content,$publish);
	}
	
	public function mw_getPost($postid,$username,$password)
	{
		return $this->getPost($postid,$username,$password,'mw');
	}
	
	public function mw_getRecentPosts($blogid,$username,$password,$numberOfPosts)
	{
		return $this->getRecentPosts($blogid,$username,$password,$numberOfPosts,'mw');
	}
	
	public function mw_newMediaObject($blogid,$username,$password,$file)
	{
		return $this->newMediaObject($blogid,$username,$password,$file);
	}
	
	/* MovableType methods
	--------------------------------------------------- */
	public function mt_getRecentPostTitles($blogid,$username,$password,$numberOfPosts)
	{
		return $this->getRecentPosts($blogid,$username,$password,$numberOfPosts,'mt');
	}
	
	public function mt_publishPost($postid,$username,$password)
	{
		return $this->publishPost($postid,$username,$password);
	}
	
	public function mt_supportedTextFilters()
	{
		return array();
	}
	
	/* WordPress methods
	--------------------------------------------------- */
	public function wp_getUsersBlogs($username,$password)
	{
		return $this->getUserBlogs($username,$password);
	}
	
	public function wp_getPage($blogid,$pageid,$username,$password)
	{
		$res = $this->getPages($blogid,$username,$password,null,$pageid);
		
		if (empty($res)) {
			throw new Exception('Sorry, no such page',404);
		}
		
		return $res[0];
	}
	
	public function wp_getPages($blogid,$username,$password,$num=10)
	{
		return $this->getPages($blogid,$username,$password,$num);
	}
	
	public function wp_newPage($blogid,$username,$password,$content,$publish)
	{
		return $this->newPage($blogid,$username,$password,$content,$publish);
	}
	
	public function wp_deletePage($blogid,$username,$password,$pageid)
	{
		return $this->deletePage($pageid,$username,$password);
	}
	
	public function wp_editPage($blogid,$pageid,$username,$password,$content,$publish)
	{
		return $this->editPage($pageid,$username,$password,$content,$publish);
	}
	
	public function wp_getPageList($blogid,$username,$password)
	{
		$A = $this->getPages($blogid,$username,$password);
		$res = array();
		foreach ($A as $v) {
			$res[] = array(
				'page_id' => $v['page_id'],
				'page_title' => $v['title'],
				'page_parent_id' => $v['wp_page_parent_id'],
				'dateCreated' => $v['dateCreated'],
				'date_created_gmt' => $v['date_created_gmt']
			);
		}
		return $res;
	}
	
	public function wp_getAuthors($blogid,$username,$password)
	{
		return $this->getAuthors($username,$password);
	}
	
	public function wp_getTags($blogid,$username,$password)
	{
		return $this->getTags($username,$password);
	}
	
	public function wp_uploadFile($blogid,$username,$password,$file)
	{
		return $this->newMediaObject($blogid,$username,$password,$file);
	}
	
	public function wp_getPostStatusList($blogid,$username,$password)
	{
		return $this->getPostStatusList($blogid,$username,$password);
	}
	
	public function wp_getPageStatusList($blogid,$username,$password)
	{
		return $this->getPostStatusList($blogid,$username,$password);
	}
	
	public function wp_getPageTemplates($blogid,$username,$password)
	{
		return array('Default' => 'default');
	}
	
	public function wp_getOptions($blogid,$username,$password,$options=array())
	{
		$this->setUser($username,$password);
		$this->setBlog();
		
		return $this->translateWpOptions($options);
	}
	
	public function wp_setOptions($blogid,$username,$password,$options)
	{
		$this->setUser($username,$password);
		$this->setBlog();
		
		if (!$this->core->auth->check('admin',$this->core->blog->id)) {
			throw new Exception('Not enough permissions to edit options.',401);
		}
		
		$opt = $this->translateWpOptions();
		
		$done = array();
		$blog_changes = false;
		$cur = $this->core->con->openCursor($this->core->prefix.'blog');
		
		$this->core->blog->settings->addNamespace('system');
		
		foreach ($options as $name => $value)
		{
			if (!isset($opt[$name]) || $opt[$name]['readonly']) {
				continue;
			}
			
			switch ($name)
			{
				case 'blog_title':
					$blog_changes = true;
					$cur->blog_name = $value;
					$done[] = $name;
					break;
				case 'blog_tagline':
					$blog_changes = true;
					$cur->blog_desc = $value;
					$done[] = $name;
					break;
				case 'date_format':
					$this->core->blog->settings->system->put('date_format',$value);
					$done[] = $name;
					break;
				case 'time_format':
					$this->core->blog->settings->system->put('time_format',$value);
					$done[] = $name;
					break;
			}
		}
		
		if ($blog_changes) {
			$this->core->updBlog($this->core->blog->id,$cur);
			$this->core->setBlog($this->core->blog->id);
		}
		
		return $this->translateWpOptions($done);
	}
	
}
?>