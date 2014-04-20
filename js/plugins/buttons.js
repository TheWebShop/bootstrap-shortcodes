tinymce.PluginManager.add('bs_buttons', function(editor, url) {
    editor.addButton('bs_buttons', {
        tooltip: 'Buttons',
        icon: 'bs-buttons',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Buttons',
                url: url + '/buttons.html',
                width: 480,
                height: 320
            });
        }
    });
});