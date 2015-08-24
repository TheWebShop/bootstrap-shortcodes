(function() {
    tinymce.PluginManager.add('bs_collapse', function(editor, url) {
        editor.addButton('bs_collapse', {
            tooltip: 'Collapse',
            icon: 'bs-collapse',
            onclick: function() {
                // Open window
                editor.windowManager.open({
                    title: 'Collapse',
                    body: [{
                        type: 'textbox',
                        name: 'itemnum',
                        value: '3',
                        label: 'Number of items'
                    },{
                        type: 'checkbox',
                        name: 'isopen',
                        checked: false,
                        label: 'Start open?'
                    }],
                    onsubmit: function(e) {
                        // Insert content when the window form is submitted
                        var uID = guid();
                        var shortcode = '[bs_collapse id="collapse_' + uID + '"]<br class="nc"/>';
                        var num = e.data.itemnum;
                        for (i = 0; i < num; i++) {
                            var id = guid();
                            var title = 'Collapsible Group Item ' + (i + 1);
                            shortcode += '[bs_citem';
                            shortcode += ' title="' + title + '"';
                            shortcode += ' id="citem_' + id + '"';
                            shortcode += ' parent="collapse_' + uID + '"';
                            shortcode += (e.data.isopen? ' open="true"': '');
                            shortcode += ']<br class="nc"/>';
                            shortcode += 'Collapse content goes here....<br class="nc"/>';
                            shortcode += '[/bs_citem]<br class="nc"/>';
                        }

                        shortcode += '[/bs_collapse]';
                        editor.insertContent(shortcode);
                    }
                });
            }
        });
    });

    function guid() {
        function s4() {
            return Math.floor((1 + Math.random()) * 0x10000).toString(16).substring(1);
        }
        return s4() + '-' + s4();
    }
})();
