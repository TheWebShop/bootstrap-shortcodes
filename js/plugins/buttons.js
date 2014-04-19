(function() {
	// Creates a new plugin
    tinymce.PluginManager.add( 'bs_buttons', function( editor, url ) {
        editor.addButton( 'bs_buttons', { 
        	text: 'Buttons',
        	tooltip: tinymce.translate('Add a button'),
            icon: false,
            onclick: function() {
            	tinymce.activeEditor.windowManager.open({
            	   title: 'Buttons',
        		   url: url + '/buttons.php',
             	   width: 480,
             	   height: 320
             	}, {
             	   custom_param: 1
             	});
            } 
        });
    });
})();