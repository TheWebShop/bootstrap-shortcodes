tinymce.PluginManager.add('bs_tabs', function(editor, url) {
    editor.addButton('bs_tabs', {
        tooltip: 'Tabs',
        icon: 'bs-tabs',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Tabs',
                url: url + '/tabs.html',
                width: 480,
                height: 320
            });
        }
    });
});