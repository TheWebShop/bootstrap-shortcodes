// JavaScript Document
(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.bs_labels', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'bs_labels':
                var c = cm.createSplitButton('bs_labels', {
                    title : 'Labels',
                    onclick : function() {

                    }
                    //'class':'mceListBoxMenu'
                });
                

                c.onRenderMenu.add(function(c, m) {
                    m.onShowMenu.add(function(c,m){
                        jQuery('#menu_'+c.id).height('auto').width('auto');
                        jQuery('#menu_'+c.id+'_co').height('auto').addClass('mceListBoxMenu'); 
                        var $menu = jQuery('#menu_'+c.id+'_co').find('tbody:first');
                        if($menu.data('added')) return;
                        $menu.append('');
                        $menu.append('<div style="padding: 0 10px 10px">\
                        <label>Types<br/>\
                        <select name="type">\
                        <option value="Default"> Default</option>\
                        <option value="Primary"> Primary</option>\
                        <option value="Success"> Success</option>\
                        <option value="Info" selected> Info</option>\
                        <option value="Warning"> Warning</option>\
                        <option value="Danger"> Danger</option>\
                        <option value="Link"> Link</option>\
                        </select>\
                        <label>Text<br />\
                        <input type="text" name="text" value="my text" onclick="this.select()"  /></label>\
                        </div>');

                        jQuery('<input type="button" class="button" value="Insert" />').appendTo($menu)
                                .click(function(){
                                    var type = $menu.find('select[name=type]').val();
                                    var text = $menu.find('input[name=text]').val();
                                    tinymce.activeEditor.execCommand('mceInsertContent',false,'[bs_label type="'+type.toLowerCase()+'"]'+text+'[/bs_label]');
                                    c.hideMenu();
                                }).wrap('<div style="padding: 0 10px 10px"></div>')
                 
                        $menu.data('added',true); 

                    });

                   // XSmall
                    m.add({title : 'Labels', 'class' : 'mceMenuItemTitle'}).setDisabled(1);

                 });
                // Return the new splitbutton instance
                return c;
                
            }
            return null;
        }
    });
    tinymce.PluginManager.add('bs_labels', tinymce.plugins.bs_labels);
})();
