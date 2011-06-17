(function($){
	var methods = {
		init: function(options) {
			var settings = {
				formatter: ''
			}
			$.extend(settings,options);
			
			if (dcToolBar.getConfig(settings.formatter) == null) {
				if (console != 'undefined') {
					console.log('No toolbar configuration for formatter ['+ settings.formatter +']');
				}
			}
			
			return this.each(function(){
				$.data(this,'toolbar',new tinymce.Editor($(this).attr('id'),dcToolBar.getConfig(settings.formatter)));
				$(this).data('toolbar').activeFormatter = settings.formatter;
				$(this).data('toolbar').onPreInit.add(function(ed) {
					tinymce.addI18n(dcToolBar.getI18n());
				});
			});
		
		},
		draw: function() {
			return this.each(function(){
				if ($(this).data('toolbar') == null) {
					throw 'Toolbar should be initialize before render it';
				}
				$(this).data('toolbar').render();
			});
		},
		show: function() {
			return this.each(function(){
				if ($(this).data('toolbar') == null) {
					throw 'Toolbar should be initialize before show it';
				}
				var t = $(this).data('toolbar');

				tinymce.dom.show(t.getContainer());
				t.load();
			});
		},
		hide: function() {
			return this.each(function(){
				if ($(this).data('toolbar') == null) {
					throw 'Toolbar should be initialize before hide it';
				}
				var t = $(this).data('toolbar');

				tinymce.dom.hide(t.getContainer());
				t.load();
			});
		},
		toggle: function() {
			return this.each(function(){
				if ($(this).data('toolbar') == null) {
					throw 'Toolbar should be initialize before toogle it';
				}
				var t = $(this).data('toolbar');
				if (t.isHidden()) {
					t.show();
					t.load();
				}
				else {
					t.save();
					$(t.getContainer()).hide();
				}
			});
		},
		destroy: function() {
			return this.each(function(){
				if ($(this).data('toolbar') == null) {
					return;
				}
				$(this).data('toolbar').remove();
				$.data(this,'toolbar',null);
			});
		},
		switch: function(formatter) {
			return this.each(function(){
				if ($(this).data('formatter') != formatter) {
					var options = {};
					options.formatter = formatter;
					methods.destroy.apply($(this));
					methods.init.apply($(this),[options]);
					methods.draw.apply($(this));
				}
			});
		}
	};
	
	$.fn.dctoolbar = function(method) {
		try {
			if (methods[method]) {
				return methods[method].apply(this,Array.prototype.slice.call(arguments,1));
			} else if (typeof method === 'object' || !method) {
				return methods.init.apply(this,arguments);
			} else {
				throw 'Method ' + method + ' does not exist on jQuery.dctoolbar';
			}
		} catch (e) {
			$.error('Error happend on jQuery.dctoolbar: ' + e);
		}
	};
})(jQuery);

function dcToolBar() {
	this.setConfig = function(formatter,config) {
		this.configurations[formatter] = config;
	};
	
	this.setI18n = function(i18n) {
		this.i18n = i18n;
	};
	
	this.getConfig = function(formatter) {
		if (this.configurations.hasOwnProperty(formatter)) {
			return this.configurations[formatter];
		} else {
			return null;
		}
	};
	
	this.getI18n = function() {
		return this.i18n == null ? {} : this.i18n;
	};
}

dcToolBar.prototype = {
	configurations: {},
	i18n: null
}