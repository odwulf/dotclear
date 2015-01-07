<?php
# -- BEGIN LICENSE BLOCK ---------------------------------------
#
# This file is part of Dotclear 2.
#
# Copyright (c) 2003-2014 Olivier Meunier & Association Dotclear
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK -----------------------------------------

if (!defined('DC_CONTEXT_ADMIN')) { return; }
header('Content-type: text/javascript');
if (!empty($_GET['context'])) {
	$context = $_GET['context'];
} else {
	$context = '';
}
$__extraPlugins = new ArrayObject();
$core->callBehavior('ckeditorExtraPlugins',$__extraPlugins,$context);
$extraPlugins = $__extraPlugins->getArrayCopy();
?>
(function($) {
	$.toolbarPopup = function toolbarPopup(url) {
		var args = Array.prototype.slice.call(arguments);
		var width = 520, height = 420;
		if (args[1]!==undefined) {
			width = args[1].width || width;
			height = args[1].height || height;
		}

		var popup_params = 'alwaysRaised=yes,dependent=yes,toolbar=yes,';
		popup_params += 'height='+height+',width='+width+',menubar=no,resizable=yes,scrollbars=yes,status=no';
		var popup_link = window.open(url,'dc_popup', popup_params);
	};

	$.stripBaseURL = function stripBaseURL(url) {
		if (dotclear.base_url != '') {
			var pos = url.indexOf(dotclear.base_url);
			if (pos == 0) {
				url = url.substr(dotclear.base_url.length);
			}
		}

		return url;
	};

	/* Retrieve editor from popup */
	$.active_editor = null;
	$.getEditorName = function getEditorName() {
		return $.active_editor;
	}
	chainHandler(window, 'onbeforeunload', function(e) {
		if (e == undefined && window.event) {
			e = window.event;
		}

		var editor = CKEDITOR.instances[$.getEditorName()];
		if (editor!==undefined && !confirmClosePage.formSubmit && editor.checkDirty()) {
			e.returnValue = confirmClosePage.prompt;
			return confirmClosePage.prompt;
		}
		return false;
	});
})(jQuery);

$(function() {
	/* By default ckeditor load related resources with a timestamp to avoid cache problem when upgrading itself
	 * load_plugin_file.php does not allow other param that file to load (pf param), so remove timestamp
	 */

	CKEDITOR.timestamp = '';
	CKEDITOR.config.skin = 'dotclear,'+dotclear.dcckeditor_plugin_url+'/js/ckeditor-skins/dotclear/';
    CKEDITOR.config.baseHref = dotclear.base_url;

<?php if (!empty($dcckeditor_cancollapse_button)):?>
	CKEDITOR.config.toolbarCanCollapse = true;
<?php endif;?>

	CKEDITOR.plugins.addExternal('entrylink',dotclear.dcckeditor_plugin_url+'/js/ckeditor-plugins/entrylink/');
	CKEDITOR.plugins.addExternal('dclink',dotclear.dcckeditor_plugin_url+'/js/ckeditor-plugins/dclink/');
	CKEDITOR.plugins.addExternal('media',dotclear.dcckeditor_plugin_url+'/js/ckeditor-plugins/media/');
	CKEDITOR.plugins.addExternal('img',dotclear.dcckeditor_plugin_url+'/js/ckeditor-plugins/img/');

<?php if (!empty($extraPlugins) && count($extraPlugins)>0) {
	foreach ($extraPlugins as $plugin) {
		printf("\tCKEDITOR.plugins.addExternal('%s','%s');\n", $plugin['name'], $plugin['url']);
	}
}
?>
    if (dotclear.ckeditor_context===undefined || dotclear.ckeditor_tags_context[dotclear.ckeditor_context]===undefined) {
        return;
    }
	$(dotclear.ckeditor_tags_context[dotclear.ckeditor_context].join(',')).ckeditor({
<?php
$defautExtraPlugins = 'entrylink,dclink,media,justify,colorbutton,format,img';
if (!empty($extraPlugins) && count($extraPlugins)>0) {
	foreach ($extraPlugins as $plugin) {
		$defautExtraPlugins .= ','. $plugin['name'];
	}
}
?>
		extraPlugins: '<?php echo $defautExtraPlugins;?>',

		<?php if (!empty($dcckeditor_format_select)):?>
		// format tags
		format_tags: 'p;h1;h2;h3;h4;h5;h6;pre;address',

		// following definition are needed to be specialized
		format_p: { element: 'p' },
		format_h1: { element: 'h1' },
		format_h2: { element: 'h2' },
		format_h3: { element: 'h3' },
		format_h4: { element: 'h4' },
		format_h5: { element: 'h5' },
		format_h6: { element: 'h6' },
		format_pre: { element: 'pre' },
		format_address: { element: 'address' },
		<?php endif;?>

		entities: false,
		removeButtons: '',
		allowedContent: true,
		toolbar: [
			{
				name: 'basicstyles',
				items: [
<?php if (!empty($dcckeditor_format_select)):?>
					'Format',
<?php endif;?>
					'Bold','Italic','Underline','Strike','Subscript','Superscript','Code','Blockquote',

<?php if (!empty($dcckeditor_list_buttons)):?>
					'NumberedList', 'BulletedList',
<?php endif;?>
					'RemoveFormat'
				]
			},
<?php if (!empty($dcckeditor_clipboard_buttons)):?>
			{
				name: 'clipoard',
				items: ['Cut','Copy','Paste','PasteText','PasteFromWord']
			},
<?php endif;?>
<?php if (!empty($dcckeditor_alignment_buttons)):?>
			{
				name: 'paragraph',
				items: ['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock']
			},
<?php endif;?>
<?php if (!empty($dcckeditor_table_button)):?>
			{
				name: 'table',
				items: ['Table']
			},
<?php endif;?>
			{
				name: 'custom',
				items: [
					'EntryLink','dcLink','Media','img','-',
					'Source'
<?php if (!empty($dcckeditor_textcolor_button)):?>
                ,'TextColor'
<?php endif;?>
				]
			},
            {
                name: 'special',
                items: [
                    'Maximize'
                ]
            },
			<?php // add extra buttons comming from dotclear plugins
			if (!empty($extraPlugins) && count($extraPlugins)>0) {
				$extraPlugins_str = "{name: 'extra', items: [%s]},\n";
				$extra_icons = '';
				foreach ($extraPlugins as $plugin) {
					$extra_icons .= sprintf("'%s',", $plugin['button']);
				}
				printf($extraPlugins_str, $extra_icons);
			}
			?>
		]
	});

	CKEDITOR.on('instanceReady', function(e) {
		if ($('label[for="post_excerpt"] a img').attr('src')==dotclear.img_minus_src) {
			$('#cke_post_excerpt').removeClass('hide');
		} else {
			$('#cke_post_excerpt').addClass('hide');
		}

		$('#excerpt-area label').click(function() {
			$('#cke_post_excerpt').toggleClass('hide',$('#post_excerpt').hasClass('hide'));
		});
	});

	// @TODO: find a better way to retrieve active editor
	for (var id in CKEDITOR.instances) {
		CKEDITOR.instances[id].on('focus', function(e) {
			$.active_editor = e.editor.name;
		});
	}
});
