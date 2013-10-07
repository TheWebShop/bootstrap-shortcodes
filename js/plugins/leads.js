(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.bs_leads', {
        createControl: function(n, cm) {
            switch (n) {
                case 'bs_leads':
                    var c = cm.createButton('bs_leads', {
                        title: 'leads',
                        onclick: function() {
                            tinyMCE.activeEditor.execCommand('mceInsertContent', false, '[leads]This is leads text and needs your attention.[/leads]');
                        }
                    });

                    // Return the new splitbutton instance
                    return c;

            }
            return null;
        }
    });
    tinymce.PluginManager.add('bs_leads', tinymce.plugins.bs_leads);
})();
