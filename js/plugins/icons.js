tinymce.PluginManager.add('bs_icons', function(editor, url) {
    editor.addButton('bs_icons', {
        tooltip : 'Icons',
        icon : 'bs-icons',
        onclick : function() {
            tinymce.activeEditor.windowManager.open({
                title : 'Icons',
                url : url + '/icons.html',
                width: Math.max(window.innerWidth * 0.4, 400),
                height: Math.max(window.innerHeight * 0.6, 400)
            });
        }
    });
});
