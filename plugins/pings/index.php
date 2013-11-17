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

dcPage::checkSuper();

try
{
	$pings_uris = @unserialize($core->blog->settings->pings->pings_uris);
	if (!$pings_uris) {
		$pings_uris = array();
	}

	if (isset($_POST['pings_srv_name']))
	{
		$pings_srv_name = is_array($_POST['pings_srv_name']) ? $_POST['pings_srv_name'] : array();
		$pings_srv_uri = is_array($_POST['pings_srv_uri']) ? $_POST['pings_srv_uri'] : array();
		$pings_uris = array();

		foreach ($pings_srv_name as $k => $v) {
			if (trim($v) && trim($pings_srv_uri[$k])) {
				$pings_uris[trim($v)] = trim($pings_srv_uri[$k]);
			}
		}

		$core->blog->settings->addNamespace('pings');
		$core->blog->settings->pings->put('pings_active',!empty($_POST['pings_active']),null,null,true,true);
		$core->blog->settings->pings->put('pings_uris',serialize($pings_uris),null,null,true,true);
		dcPage::addSuccessNotice(__('Settings have been successfully updated.'));
		http::redirect($p_url);
	}
}
catch (Exception $e)
{
	$core->error->add($e->getMessage());
}
?>
<html>
<head>
  <title><?php echo __('Pings'); ?></title>
</head>

<body>
<?php

echo dcPage::breadcrumb(
	array(
		__('Plugins') => '',
		__('Pings configuration') => ''
	));

echo
'<form action="'.$p_url.'" method="post">'.
'<p><label for="pings_active" class="classic">'.form::checkbox('pings_active',1,$core->blog->settings->pings->pings_active).
__('Activate pings extension').'</label></p>';

$i = 0;
foreach ($pings_uris as $n => $u)
{
	echo
	'<p><label for="pings_srv_name-'.$i.'" class="classic">'.__('Service name:').'</label> '.
	form::field(array('pings_srv_name[]','pings_srv_name-'.$i),20,128,html::escapeHTML($n)).' '.
	'<label for="pings_srv_uri-'.$i.'" class="classic">'.__('Service URI:').'</label> '.
	form::field(array('pings_srv_uri[]','pings_srv_uri-'.$i),40,255,html::escapeHTML($u));

	if (!empty($_GET['test']))
	{
		try {
			pingsAPI::doPings($u,'Example site','http://example.com');
			echo ' <img src="images/check-on.png" alt="OK" />';
		} catch (Exception $e) {
			echo ' <img src="images/check-off.png" alt="'.__('Error').'" /> '.$e->getMessage();
		}
	}

	echo '</p>';
	$i++;
}

echo
'<p><label for="pings_srv_name2" class="classic">'.__('Service name:').'</label> '.
form::field(array('pings_srv_name[]','pings_srv_name2'),20,128).' '.
'<label for="pings_srv_uri2" class="classic">'.__('Service URI:').'</label> '.
form::field(array('pings_srv_uri[]','pings_srv_uri2'),40,255).
'</p>'.

'<p><input type="submit" value="'.__('Save').'" />'.
$core->formNonce().'</p>'.
'</form>';

echo '<p><a class="button" href="'.$p_url.'&amp;test=1">'.__('Test ping services').'</a></p>';
?>

<?php dcPage::helpBlock('pings'); ?>

</body>
</html>
