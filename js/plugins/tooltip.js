tinymce.PluginManager.add('bs_tooltip', function(editor, url) {
    editor.addButton('bs_tooltip', {
        tooltip: 'Tooltip',
        icon: 'bs-tooltip',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Tooltip',
                url: url + '/tooltip.html',
                width: 480,
                height: 320
            });
        }
    });
});