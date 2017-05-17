tinymce.PluginManager.add('bs_text_elements', function(editor, url) {
    editor.addButton('bs_text_elements', {
        type: 'menubutton',
        tooltip: 'Text elements',
        icon: 'bs-text-elmts',
        menu: [
            { text: 'Lead paragraph', onclick: function() { editor.insertContent('[bs_lead class="mb-4"]This is a lead text and needs your attention.[/bs_lead]'); } },
            { text: 'Blockquote with citation (left)',  onclick: function() { editor.insertContent('[bs_blockquote class="mb-4" cite="Someone famous in Source Title"]Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante.[/bs_blockquote]'); } },
            { text: 'Blockquote with citation (right)',  onclick: function() { editor.insertContent('[bs_blockquote class="mb-4" align="right" cite="Someone famous in Source Title"]Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante.[/bs_blockquote]'); } }
        ]
    });
});
