
tinymce.PluginManager.add('bs_badges', function(editor, url) {
    editor.addButton('bs_badges', {
        tooltip: 'Badges',
        icon: 'bs-badges',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Badges',
                url: url + '/badges.html',
                width: 480,
                height: 210
            });
        }
    });
});
