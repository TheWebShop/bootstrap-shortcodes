tinymce.PluginManager.add('bs_lead', function(editor, url) {
    editor.addButton('bs_lead', {
        tooltip: 'Lead',
        icon: 'bs-lead',
        onclick: function() {
            editor.insertContent('[bs_lead]This is a lead text and needs your attention.[/bs_lead]');
        }
    });
});