// JavaScript Document
(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.dws_wells', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'dws_wells':
                var c = cm.createSplitButton('dws_wells', {
                    title : 'Well',
                    onclick : function() {
                    }
                });

                c.onRenderMenu.add(function(c, m) {
                    // Boxes & frames
                    m.add({title : 'Well', 'class' : 'mceMenuItemTitle'}).setDisabled(1);
                    m.add({title : 'Small well', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[well size="sm"]This well needs your attention.[/well]' );
                    }});   
                    m.add({title : 'Medium well', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[well size="md"]This well needs your attention.[/well]' );
                    }});  
                    m.add({title : 'Large well', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[well size="lg"]This well needs your attention.[/well]' );
                    }});
                   
                });

                // Return the new splitbutton instance
                return c;
            }
            return null;
        }
    });
    tinymce.PluginManager.add('dws_wells', tinymce.plugins.dws_wells);
})();