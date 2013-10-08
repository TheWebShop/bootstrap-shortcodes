(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.bs_lead', {
        createControl: function(n, cm) {
            switch (n) {
                case 'bs_lead':
                var c = cm.createButton('bs_lead', {
                    title : 'Lead',
                    onclick : function() {
                        tinyMCE.activeEditor.execCommand('mceInsertContent', false, '[lead]This is a lead text and needs your attention.[/lead]');
                    }
                });
                // Return the new splitbutton instance
                return c;

            }
            return null;
        }
    });
    tinymce.PluginManager.add('bs_lead', tinymce.plugins.bs_lead);
})();
