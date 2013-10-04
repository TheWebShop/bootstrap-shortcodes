// JavaScript Document
(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.bs_icons', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'bs_icons':
                var c = cm.createButton('bs_icons', {
                    title : 'Icons',
                    onclick : function() {
                        tb_show('Select icons', '../wp-content/plugins/bootstrap-shortcodes/js/plugins/icons.html?TB_iframe=1');
                    }
                });

                // Return the new splitbutton instance
                return c;
            }
            return null;
        }
    });
    tinymce.PluginManager.add('bs_icons', tinymce.plugins.bs_icons);
})();

