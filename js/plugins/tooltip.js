tinymce.PluginManager.add('bs_tooltip', function(editor, url) {
    editor.addButton('bs_tooltip', {
        tooltip: 'Tooltip',
        icon: 'bs-tooltip',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Tooltip',
                url: url + '/tooltip.html',
                width: Math.max(window.innerWidth * 0.4, 400),
                height: Math.max(window.innerHeight * 0.6, 400)
            });
        }
    });
});