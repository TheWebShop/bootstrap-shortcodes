// JavaScript Document
(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.dws_lead', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'dws_lead':
                var c = cm.createButton('dws_lead', {
                    title : 'Lead',
                    onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[lead]This is lead text and needs your attention.[/lead]' );
                    } 
                });

                // Return the new splitbutton instance
                return c;
                
            }
            return null;
        }
    });
    tinymce.PluginManager.add('dws_lead', tinymce.plugins.dws_lead);
})();