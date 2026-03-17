tinymce.PluginManager.add('bs_buttons', function(editor, url) {
    editor.addButton('bs_buttons', {
        tooltip: 'Buttons',
        icon: 'bs-buttons',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Buttons',
                url: url + '/buttons.html',
                width: Math.max(window.innerWidth * 0.4, 400),
                height: Math.max(window.innerHeight * 0.6, 400)
            });
        }
    });
});