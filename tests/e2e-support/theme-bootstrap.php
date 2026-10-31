<?php
/**
 * Plugin Name: E2E helper: theme-provided Bootstrap
 * Description: Test-environment only. Mounted as an mu-plugin by .wp-env.json so the Playwright suite can simulate a theme that ships its own Bootstrap 3. Does nothing unless ?e2e_theme_bootstrap=1 is on the URL.
 */

add_action( 'wp_enqueue_scripts', function () {
	if ( empty( $_GET['e2e_theme_bootstrap'] ) ) {
		return;
	}
	// Reuse the plugin's own copies under theme-style handles, loaded in the footer so
	// they arrive *after* init.js (a theme is under no obligation to load first).
	$base = plugins_url( '', WP_PLUGIN_DIR . '/bootstrap-shortcodes/bootstrap-shortcodes.php' );
	wp_enqueue_style( 'theme-bootstrap', $base . '/css/bootstrap.css', array(), 'e2e' );
	wp_enqueue_script( 'theme-bootstrap', $base . '/js/bootstrap.js', array( 'jquery' ), 'e2e', true );
}, 1 );
