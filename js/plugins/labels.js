
tinymce.PluginManager.add('bs_labels', function(editor, url) {
    editor.addButton('bs_labels', {
        tooltip: 'Labels',
        icon: 'bs-labels',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Labels',
                url: url + '/labels.html',
                width: 480,
                height: 320
            });
        }
    });
});
