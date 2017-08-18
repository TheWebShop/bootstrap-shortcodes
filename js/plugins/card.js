// tinymce.PluginManager.add('bs_card', function(editor, url) {
//     editor.addButton('bs_card', {
//         tooltip: 'Card',
//         icon: 'bs-card',
//         onclick: function() {
//             editor.insertContent('[bs_card title="Card title" class="card-outline-primary"]Your content here[/bs_card]');
//         }
//     });
// });
tinymce.PluginManager.add('bs_card', function(editor, url) {
    editor.addButton('bs_card', {
        tooltip: 'Card',
        icon: 'bs-card',
        onclick: function() {
            tinymce.activeEditor.windowManager.open({
                title: 'Card',
                url: url + '/cards.html',
                width: 480,
                height: 360
            });
        }
    });
});
