tinymce.PluginManager.add( 'bs_alerts', function( editor, url ) {
    editor.addButton( 'bs_alerts', {
        type: 'menubutton',
        tooltip: 'Alerts',
            icon: 'bs-alerts',
            menu: [
                 { text: 'Success notification', onclick: function() { editor.insertContent('[bs_notification type="success"]<strong>Well done!</strong>You successfully read <a href="#" class="alert-link">this important alert message</a>.[/bs_notification]');} },
                 { text: 'Info notification',    onclick: function() { editor.insertContent('[bs_notification type="info"]<strong>Heads up!</strong>This <a href="#" class="alert-link">alert needs your attention</a>, but it\'s not super important.[/bs_notification]');} },
                 { text: 'Warning notification', onclick: function() { editor.insertContent('[bs_notification type="warning"]<strong>Warning!</strong>Best check yo self, you\'re <a href="#" class="alert-link">not looking too good</a>.[/bs_notification]');} },
                 { text: 'Error notification',   onclick: function() { editor.insertContent('[bs_notification type="danger"]<strong>Oh snap!</strong> <a href="#" class="alert-link">Change a few things</a> up and try submitting again.[/bs_notification]');} }
            ]
    });
});
