(function() {
	// Creates a new plugin
	tinymce.PluginManager.add( 'bs_lead', function( editor, url ) {
		editor.addButton( 'bs_lead', {
            text : 'Lead',
            tooltip: tinymce.translate('Make a paragraph stand out'),
            onclick : function() {
            	editor.insertContent('[bs_lead]This is a lead text and needs your attention.[/bs_lead]');
            }
		});
    });
})();
