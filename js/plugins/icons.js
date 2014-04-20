tinymce.PluginManager.add('bs_icons', function(editor, url) {
    editor.addButton('bs_icons', {
        tooltip : 'Icons',
        icon : 'bs-icons',
        onclick : function() {
            tinymce.activeEditor.windowManager.open({
                title : 'Icons',
                url : url + '/icons.html',
                width : 480,
                height : 320
            });
        }
    });
});
