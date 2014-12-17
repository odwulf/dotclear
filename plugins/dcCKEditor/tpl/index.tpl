<html>
	<head>
		<title>dcCKEditor</title>
	</head>
	<body>
		<?php echo dcPage::breadcrumb(array(__('Plugins') => '',__('dcCKEditor') => '')).dcPage::notices(); ?>

		<?php if ($is_admin):?>
			<h3 class="hidden-if-js"><?php echo __('Settings');?></h3>
			<form action="<?php echo $p_url;?>" method="post" enctype="multipart/form-data">
				<div class="fieldset">
					<h3><?php echo __('Plugin activation');?></h3>
					<p>
						<label class="classic" for="dcckeditor_active">
							<?php echo form::checkbox('dcckeditor_active', 1, $dcckeditor_active);?>
							<?php echo __('Enable dcCKEditor plugin');?>
						</label>
					</p>
				</div>

				<?php if ($dcckeditor_active):?>
					<div class="fieldset">
						<h3><?php echo __('Options'); ?></h3>
						<p>
							<?php echo form::checkbox('dcckeditor_alignment_buttons', 1, $dcckeditor_alignment_buttons); ?>
							<label class="classic" for="dcckeditor_alignment_buttons">&nbsp;<?php echo __('Add alignment buttons');?></label>
						</p>
						<p>
							<?php echo form::checkbox('dcckeditor_list_buttons', 1, $dcckeditor_list_buttons); ?>
							<label class="classic" for="dcckeditor_list_buttons">&nbsp;<?php echo __('Add lists buttons');?></label>
						</p>
						<p>
							<?php echo form::checkbox('dcckeditor_textcolor_button', 1, $dcckeditor_textcolor_button); ?>
							<label class="classic" for="dcckeditor_textcolor_button">&nbsp;<?php echo __('Add text color button');?></label>
						</p>
						<p>
							<?php echo form::checkbox('dcckeditor_cancollapse_button', 1, $dcckeditor_cancollapse_button); ?>
							<label class="classic" for="dcckeditor_cancollapse_button">&nbsp;<?php echo __('Add collapse button');?></label>
						</p>
						<p>
							<?php echo form::checkbox('dcckeditor_format_select', 1, $dcckeditor_format_select);?>
							<label class="classic" for="dcckeditor_format_select">&nbsp;<?php echo __('Add format selection');?></label>
						</p>
						<p>
							<?php echo form::checkbox('dcckeditor_table_button', 1, $dcckeditor_table_button);?>
							<label class="classic" for="dcckeditor_table_button">&nbsp;<?php echo __('Add table button');?></label>
						</p>
						<p>
							<?php echo form::checkbox('dcckeditor_clipboard_buttons', 1, $dcckeditor_clipboard_buttons);?>
							<label class="classic" for="dcckeditor_clipboard_buttons">&nbsp;<?php echo __('Add clipboard buttons');?></label>
						</p>
						<p class="clear form-note">
							<?php echo __('Copy, Paste, Paste Text, Paste from Word');?>
						</p>
					</div>
				<?php endif;?>

				<p>
					<input type="hidden" name="p" value="dcCKEditor"/>
					<?php echo $core->formNonce();?>
					<input type="submit" name="saveconfig" value="<?php echo __('Save configuration');?>" />
				</p>
			</form>
		<?php endif;?>

		<?php dcPage::helpBlock('dcCKEditor');?>
	</body>
</html>
