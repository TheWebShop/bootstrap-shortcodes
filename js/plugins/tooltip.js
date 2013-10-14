// JavaScript Document
(function() {
    // Creates a new plugin class and a custom listbox
    tinymce.create('tinymce.plugins.bs_tooltip', {
        createControl: function(n, cm) {
            switch (n) {                
                case 'bs_tooltip':
                var c = cm.createSplitButton('bs_tooltip', {
                    title : 'Tooltip',
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
                        $menu.append('<div style="padding:0 10px 10px">\
                        <label>Content</label>\
                        <input type="text" name="content" value="The Tooltip" onclick="this.select()"  />\
                        <label>State</label>\
                		<select name="state">\
                        <option value="show"> Show</option>\
                        <option value="hide"> Hide</option>\
                        <option value="toggle"> Toggle</option>\
                        <option value="destroy"> Destroy</option>\
                        </select>\
	            		<label>Placement</label>\
	            		<select name="placement">\
	            		<option value="top"> Top</option>\
	            		<option value="right"> Right</option>\
	            		<option value="bottom"> Bottom</option>\
	            		<option value="left"> Left</option>\
	            		</select>\
                        </div>');

                        jQuery('<input type="button" class="button" value="Insert" />').appendTo($menu)
                                .click(function(){
                         /**
                          * Shortcode markup
                          * -----------------------
                          *      [tooltip id="#" state="" placement=""]content
                          *      [/tooltip]
                          *  -----------------------
                          */
                            	var content = $menu.find('input[name=content]').val();
                            	var state = $menu.find('select[name=state]').val();
                            	var placement = $menu.find('select[name=placement]').val();
                                var uID =  Math.floor((Math.random()*100)+1);
                                var shortcode = '[tooltip id="Tooltip_'+uID+'" state="'+state+'" placement="'+placement+'"]';
                                shortcode+= content;
                                shortcode+= '[/tooltip]';

                                    tinymce.activeEditor.execCommand('mceInsertContent',false,shortcode);
                                    c.hideMenu();
                                }).wrap('<div style="padding: 0 10px 10px"></div>')

                        $menu.data('added',true); 

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
})();