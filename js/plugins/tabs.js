(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.bs_tabs', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'bs_tabs':
                var c = cm.createButton('bs_tabs', {
                    title : 'Tabs',
                    onclick : function() {
                        tb_show('Tab builder', '../wp-content/plugins/bootstrap-shortcodes/js/plugins/tabs.html?TB_iframe=1');
                    }
                });

                // Return the new splitbutton instance
                return c;
                
            }
            return null;
        }
    });
    tinymce.PluginManager.add('bs_tabs', tinymce.plugins.bs_tabs);
})();
