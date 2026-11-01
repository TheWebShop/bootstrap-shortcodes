<?php
/**
 * The legacy-assets companion: built from this plugin's own files, and its
 * double-loading guards.
 */
class CompanionTest extends WP_UnitTestCase {

	public function test_manifest_points_at_existing_files() {
		$files = BS_Companion::manifest();
		$this->assertArrayHasKey( 'bootstrap-legacy-assets/bootstrap-legacy-assets.php', $files );
		$this->assertArrayHasKey( 'bootstrap-legacy-assets/css/bootstrap.css', $files );
		$this->assertArrayHasKey( 'bootstrap-legacy-assets/js/bootstrap.js', $files );
		$this->assertArrayHasKey( 'bootstrap-legacy-assets/js/init.js', $files );
		$this->assertArrayHasKey( 'bootstrap-legacy-assets/fonts/glyphicons-halflings-regular.woff2', $files );
		// The original loaded shortcodes.css with bootstrap.css; legacy icon-* sprites live there.
		$this->assertArrayHasKey( 'bootstrap-legacy-assets/css/shortcodes.css', $files );
		$this->assertArrayHasKey( 'bootstrap-legacy-assets/images/glyphicons-halflings.png', $files );
		foreach ( $files as $src ) {
			$this->assertFileExists( $src );
		}
	}

	public function test_zip_builds_with_every_manifest_entry() {
		$zip = BS_Companion::build_zip();
		$this->assertNotWPError( $zip );
		$this->assertFileExists( $zip );
		$archive = new ZipArchive();
		$this->assertTrue( $archive->open( $zip ) );
		$names = array();
		for ( $i = 0; $i < $archive->numFiles; $i++ ) {
			$names[] = $archive->getNameIndex( $i );
		}
		$archive->close();
		unlink( $zip );
		foreach ( array_keys( BS_Companion::manifest() ) as $inside ) {
			$this->assertContains( $inside, $names );
		}
	}

	public function test_companion_skips_when_the_original_still_loads_assets() {
		require_once dirname( dirname( __DIR__ ) ) . '/companion/bootstrap-legacy-assets/bootstrap-legacy-assets.php';
		update_option( 'bs_options', BootstrapShortcodes::default_options() );
		$this->assertTrue( BS_Legacy_Assets::original_plugin_loads_assets() );

		$GLOBALS['wp_styles'] = new WP_Styles();
		( new BS_Legacy_Assets() )->enqueue();
		$this->assertNotContains( 'bs-legacy-bootstrap', $GLOBALS['wp_styles']->queue );

		update_option( 'bs_options', array( 'chk_default_options_grid' => '1' ) );
		$this->assertFalse( BS_Legacy_Assets::original_plugin_loads_assets() );
		( new BS_Legacy_Assets() )->enqueue();
		$this->assertContains( 'bs-legacy-bootstrap', $GLOBALS['wp_styles']->queue );
		delete_option( 'bs_options' );
	}

	public function test_companion_detects_a_theme_bootstrap() {
		require_once dirname( dirname( __DIR__ ) ) . '/companion/bootstrap-legacy-assets/bootstrap-legacy-assets.php';
		$GLOBALS['wp_styles'] = new WP_Styles();
		wp_register_style( 'my-theme-bootstrap', 'https://theme.test/assets/bootstrap.min.css?ver=3' );
		$this->assertSame( 'my-theme-bootstrap', BS_Legacy_Assets::other_bootstrap( 'css' ) );
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$this->assertSame( '', BS_Legacy_Assets::other_bootstrap( 'js' ) );
	}

	public function test_install_is_refused_without_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$r = BS_Companion::install_and_activate();
		$this->assertWPError( $r );
		$this->assertSame( 'bs_forbidden', $r->get_error_code() );
	}

	public function test_install_and_activate_puts_a_working_plugin_in_place() {
		if ( ! wp_is_writable( WP_PLUGIN_DIR ) ) {
			$this->markTestSkipped( 'plugins directory not writable in this environment' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'bs_options', array( 'chk_default_options_css' => '1' ) ); // JS off: companion must mirror it
		$dir = WP_PLUGIN_DIR . '/' . BS_Companion::SLUG;
		$this->assertDirectoryDoesNotExist( $dir );

		try {
			$r = BS_Companion::install_and_activate();

			$this->assertTrue( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
			$this->assertFileExists( $dir . '/bootstrap-legacy-assets.php' );
			$this->assertFileExists( $dir . '/css/bootstrap.css' );
			$this->assertFileExists( $dir . '/fonts/glyphicons-halflings-regular.woff2' );
			$this->assertTrue( BS_Companion::is_active() );
			$this->assertSame( array( 'css' => '1', 'tooltips' => '1' ), get_option( 'bs_legacy_assets' ) );
			$this->assertSame( file_get_contents( dirname( dirname( __DIR__ ) ) . '/css/bootstrap.css' ), file_get_contents( $dir . '/css/bootstrap.css' ) );
		} finally {
			deactivate_plugins( BS_Companion::FILE );
			delete_option( 'bs_legacy_assets' );
			delete_option( 'bs_options' );
			global $wp_filesystem;
			WP_Filesystem();
			$wp_filesystem->delete( $dir, true );
		}
	}

	public function test_companion_loads_shortcodes_css_with_bootstrap_css() {
		require_once dirname( dirname( __DIR__ ) ) . '/companion/bootstrap-legacy-assets/bootstrap-legacy-assets.php';
		update_option( 'bs_options', array( 'chk_default_options_grid' => '1' ) );
		update_option( 'bs_legacy_assets', array( 'css' => '1' ) );
		$GLOBALS['wp_styles'] = new WP_Styles();
		( new BS_Legacy_Assets() )->enqueue();
		$this->assertContains( 'bs-legacy-bootstrap', $GLOBALS['wp_styles']->queue );
		$this->assertContains( 'bs-legacy-shortcodes', $GLOBALS['wp_styles']->queue );
		delete_option( 'bs_options' );
		delete_option( 'bs_legacy_assets' );
	}

	public function test_with_conditional_loading_the_companion_covers_pages_the_original_skips() {
		require_once dirname( dirname( __DIR__ ) ) . '/companion/bootstrap-legacy-assets/bootstrap-legacy-assets.php';
		update_option( 'bs_options', array_merge( BootstrapShortcodes::default_options(), array( 'chk_conditional_assets' => '1' ) ) );
		update_option( 'bs_legacy_assets', array( 'css' => '1', 'js' => '1' ) );
		$this->assertFalse( BS_Legacy_Assets::original_plugin_loads_assets() );

		// A page without shortcodes: the original queues nothing, the companion loads.
		$GLOBALS['wp_styles']  = new WP_Styles();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		( new BS_Legacy_Assets() )->enqueue();
		$this->assertContains( 'bs-legacy-bootstrap', $GLOBALS['wp_styles']->queue );
		$this->assertContains( 'bs-legacy-bootstrap', $GLOBALS['wp_scripts']->queue );

		// The original's render-time fallback then stands down instead of loading Bootstrap twice.
		$plugin = new BootstrapShortcodes();
		$plugin->enqueue_assets();
		$this->assertNotContains( 'bs_bootstrap', $GLOBALS['wp_styles']->queue );
		$this->assertNotContains( 'bs_bootstrap', $GLOBALS['wp_scripts']->queue );

		// A page where the original already queued its copy: the companion stays out.
		$GLOBALS['wp_styles']  = new WP_Styles();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		( new BootstrapShortcodes() )->enqueue_assets();
		( new BS_Legacy_Assets() )->enqueue();
		$this->assertNotContains( 'bs-legacy-bootstrap', $GLOBALS['wp_styles']->queue );
		$this->assertNotContains( 'bs-legacy-bootstrap', $GLOBALS['wp_scripts']->queue );
		delete_option( 'bs_options' );
		delete_option( 'bs_legacy_assets' );
	}
}
