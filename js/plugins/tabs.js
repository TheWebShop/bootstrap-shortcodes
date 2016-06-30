tinymce.PluginManager.add('bs_tabs', function(editor, url) {
    editor.addButton('bs_tabs', {
        tooltip: 'Tabs',
        icon: 'bs-tabs',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Tabs',
                url: url + '/tabs.html',
                width: Math.max(window.innerWidth * 0.4, 400),
                height: Math.max(window.innerHeight * 0.6, 400)
            });
        }
    });
});