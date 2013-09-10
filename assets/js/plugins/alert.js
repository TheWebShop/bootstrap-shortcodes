// JavaScript Document
(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.dws_alerts', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'dws_alerts':
                var c = cm.createSplitButton('dws_alerts', {
                    title : 'Notification',
                    onclick : function() {
                    }
                });

                c.onRenderMenu.add(function(c, m) {
                    // Boxes & frames
                    m.add({title : 'Notification', 'class' : 'mceMenuItemTitle'}).setDisabled(1);

                    m.add({title : 'Warning notification', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[notification type="error"]<strong>Warning!</strong>  Best check yo self, you\'re not looking too good. [/notification]' );
                    }});  
                    m.add({title : 'Error notification', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[notification type="alert"]<strong>Oh snap!</strong> Change a few things up and try submitting again.[/notification]' );
                    }});  
                    m.add({title : 'Success notification', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[notification type="success"]<strong>Well done!</strong>   You successfully read this important alert message.  [/notification]' );
                    }});   
                    m.add({title : 'Info notification', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[notification type="info"]<strong>Heads up!</strong>   This alert needs your attention, but it\'s not super important.  [/notification]' );
                    }});  
                   
                });

                // Return the new splitbutton instance
                return c;
                
            }
            return null;
        }
    });
    tinymce.PluginManager.add('dws_alerts', tinymce.plugins.dws_alerts);
})();