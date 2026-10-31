(function($) {
    function initTooltips() {
        // Bootstrap's tooltip plugin may come from the theme rather than this plugin,
        // possibly loaded later than us; bail out quietly if it is not here yet.
        if (!$.fn.tooltip) {
            return false;
        }
        $('[data-toggle="tooltip"]').tooltip()
            // Stop "click triggered" tootips from acting as bookmarks to top of page
            .filter('[data-trigger*="click"]')
            .on('click', function(e) {
                e.preventDefault();
            });
        return true;
    }
    $(document).ready(function() {
        if (!initTooltips()) {
            $(window).on('load', initTooltips);
        }
    });
}(jQuery));
