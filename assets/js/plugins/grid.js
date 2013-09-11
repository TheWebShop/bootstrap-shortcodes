// JavaScript Document
(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.dws_grid', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'dws_grid':
                var c = cm.createSplitButton('dws_grid', {
                    title : 'Fluid grid system',
                    onclick : function() {
                    }
                });

                c.onRenderMenu.add(function(c, m) {
					// Boxes & frames
					m.add({title : 'Fluid grid system', 'class' : 'mceMenuItemTitle'}).setDisabled(1);
                    m.add({title : '12 Columns', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[row class="row"]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[col class="col-xs-1"]Text[/col]<br class="nc"/>[/row]' );
                    }});
                    m.add({title : '6 Columns', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[row class="row"]<br class="nc"/>[col class="col-xs-2"]Text[/col]<br class="nc"/>[col class="col-xs-2"]Text[/col]<br class="nc"/>[col class="col-xs-2"]Text[/col]<br class="nc"/>[col class="col-xs-2"]Text[/col]<br class="nc"/>[col class="col-xs-2"]Text[/col]<br class="nc"/>[col class="col-xs-2"]Text[/col]<br class="nc"/>[/row]' );
                    }});
                    m.add({title : '4 Columns', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[row class="row"]<br class="nc"/>[col class="col-xs-3"]Text[/col]<br class="nc"/>[col class="col-xs-3"]Text[/col]<br class="nc"/>[col class="col-xs-3"]Text[/col]<br class="nc"/>[col class="col-xs-3"]Text[/col]<br class="nc"/>[/row]' );
                    }});
                    m.add({title : '3 Columns', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[row class="row"]<br class="nc"/>[col class="col-xs-4"]Text[/col]<br class="nc"/>[col class="col-xs-4"]Text[/col]<br class="nc"/>[col class="col-xs-4"]Text[/col]<br class="nc"/>[/row]' );
                    }});
                    m.add({title : '2 Columns', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[row class="row"]<br class="nc"/>[col class="col-xs-6"]Text[/col]<br class="nc"/>[col class="col-xs-6"]Text[/col]<br class="nc"/>[/row]' );
                    }}); 
                    m.add({title : '1 Columns', onclick : function() {
                        tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, '[row class="row"]<br class="nc"/>[col class="col-xs-12"]Text[/col]<br class="nc"/>[/row]' );
                    }}); 
                    m.add({title : 'Custom Grid', onclick : function() {
                         tb_show('Custom Grid', '../wp-content/plugins/bootstrap-shortcodes/assets/js/plugins/grid.html?TB_iframe=1');
                    }}); 

                });

                // Return the new splitbutton instance
                return c;
                
            }
            return null;
        }
    });
    tinymce.PluginManager.add('dws_grid', tinymce.plugins.dws_grid);
})();