// JavaScript Document
(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.bs_labels', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'bs_labels':
                var c = cm.createSplitButton('bs_labels', {
                    title : 'Boxes & frames shortcodes',
                    onclick : function() {
                    }
                });

                c.onRenderMenu.add(function(c, m) {
                    // Boxes & frames
                    m.add({title : 'Labels & Badges', 'class' : 'mceMenuItemTitle'}).setDisabled(1);
                    m.add({title : 'Default', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[label]Default[/label]' );
                    }}); 
                    m.add({title : 'Success', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[label class="success"]Default[/label]' );
                    }});
                });

                // Return the new splitbutton instance
                return c;
                
            }
            return null;
        }
    });
    tinymce.PluginManager.add('bs_labels', tinymce.plugins.bs_labels);
})();