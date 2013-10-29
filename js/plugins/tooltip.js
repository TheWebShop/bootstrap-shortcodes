/**
* Shortcode markup
* -----------------------
*      [tooltip placement="" trigger="" ]content
*      [/tooltip]
*  -----------------------
*/
(function($) {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.bs_tooltip', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'bs_tooltip':
                var c = cm.createSplitButton('bs_tooltip', {
                    title : 'Tooltip'
                });

                c.onRenderMenu.add(function(c, m) {
                    m.onShowMenu.add(function(c,m){ 
                        $('#menu_'+c.id).height('auto').width('auto');
                        $('#menu_'+c.id+'_co').height('auto').addClass('mceListBoxMenu'); 
                        var $menu = $('#menu_'+c.id+'_co').find('tbody:first');
                        if($menu.data('added')) return;
                        $menu.append('<div style="padding:0 10px 10px">\
                            <label>Content</label>\
                            <input type="text" name="content" value="The Tooltip" onclick="this.select()"  />\
                            <label>Placement</label>\
                            <select name="placement">\
                                <option value="top" selected>Top</option>\
                                <option value="right">Right</option>\
                                <option value="bottom">Bottom</option>\
                                <option value="left">Left</option>\
                            </select>\
                            <label>Trigger</label>\
                            <select name="trigger">\
                                <option value="click">Click</option>\
                                <option value="hover" selected>Hover</option>\
                                <option value="focus">Focus</option>\
                            </select>\
                        </div>');

                        $('<input type="button" class="button" value="Insert" />')
                            .wrap('<div style="padding: 0 10px 10px"></div>')
                            .appendTo($menu)
                            .on('click', function() {
                                var content = $menu.find('input[name=content]').val();
                                var placement = $menu.find('select[name=placement]').val();
                                var trigger = $menu.find('select[name=trigger]').val();
                                var shortcode = '[bs_tooltip placement="' + placement + '" trigger="' + trigger + '"]';
                                shortcode+= content;
                                shortcode+= '[/bs_tooltip]';

                                tinymce.activeEditor.execCommand('mceInsertContent', false, shortcode);
                                c.hideMenu();
                            });

                        $menu.data('added', true); 
                    });

                   // XSmall
                    m.add({title : 'Tooltip', 'class' : 'mceMenuItemTitle'}).setDisabled(1);
                 });
                // Return the new splitbutton instance
                return c;
            }
            return null;
        }
    });
    tinymce.PluginManager.add('bs_tooltip', tinymce.plugins.bs_tooltip);
})(jQuery);