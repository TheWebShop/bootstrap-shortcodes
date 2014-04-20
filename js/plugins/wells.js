tinymce.PluginManager.add('bs_wells', function(editor, url) {
    editor.addButton('bs_wells', {
        type: 'menubutton',
        tooltip: 'Well',
        icon: 'bs-wells',
        menu: [
            { text: 'Small well',  onclick: function() { editor.insertContent('[bs_well size="sm"]This well needs your attention.[/bs_well]'); } },
            { text: 'Medium well', onclick: function() { editor.insertContent('[bs_well size="md"]This well needs your attention.[/bs_well]'); } },
            { text: 'Large well',  onclick: function() { editor.insertContent('[bs_well size="lg"]This well needs your attention.[/bs_well]'); } }
        ]
    });
});