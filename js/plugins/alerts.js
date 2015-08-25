tinymce.PluginManager.add('bs_alerts', function(editor, url) {
    editor.addButton('bs_alerts', {
        tooltip: 'Alerts',
        icon: 'bs-alerts',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Add an alert',
                url: url + '/alerts.html',
                width: 480,
                height: 180
            });
        }
    });
});
