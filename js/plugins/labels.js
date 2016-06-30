
tinymce.PluginManager.add('bs_labels', function(editor, url) {
    editor.addButton('bs_labels', {
        tooltip: 'Labels',
        icon: 'bs-labels',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Labels',
                url: url + '/labels.html',
                width: Math.max(window.innerWidth * 0.4, 400),
                height: Math.max(window.innerHeight * 0.6, 400)
            });
        }
    });
});
