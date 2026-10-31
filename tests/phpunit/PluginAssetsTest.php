<?php
/**
 * Frontend asset loading: which handles land on the page under each setting.
 */
class PluginAssetsTest extends WP_UnitTestCase {

	private $plugin;

	public function set_up() {
		parent::set_up();
		$this->plugin = new BootstrapShortcodes();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
		wp_default_scripts( $GLOBALS['wp_scripts'] );
		wp_default_styles( $GLOBALS['wp_styles'] );
	}

	public function tear_down() {
		delete_option( 'bs_options' );
		unset( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] );
		parent::tear_down();
	}

	// Print straight from the dependency queues: wp_print_styles() drags core's
	// emoji hooks along, which only adds deprecation noise here.
	private function printed_scripts() {
		return get_echo( array( $GLOBALS['wp_scripts'], 'do_items' ) );
	}

	private function printed_styles() {
		return get_echo( array( $GLOBALS['wp_styles'], 'do_items' ) );
	}

	public function test_hooks_are_registered_on_enqueue_actions() {
		$this->assertNotFalse( has_action( 'wp_enqueue_scripts', array( $this->plugin, 'enqueue_frontend_assets' ) ) );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', array( $this->plugin, 'enqueue_admin_assets' ) ) );
	}

	public function test_bundled_css_and_js_load_with_default_options() {
		update_option( 'bs_options', BootstrapShortcodes::default_options() );

		$this->plugin->enqueue_frontend_assets();

		$scripts = $this->printed_scripts();
		$styles  = $this->printed_styles();
		$this->assertStringContainsString( 'js/bootstrap.js', $scripts );
		$this->assertStringContainsString( 'js/init.js', $scripts );
		$this->assertStringContainsString( 'css/bootstrap.css', $styles );
		$this->assertStringContainsString( 'css/shortcodes.css', $styles );
		// Bootstrap must come before init.js so tooltips can bind.
		$this->assertLessThan( strpos( $scripts, 'js/init.js' ), strpos( $scripts, 'js/bootstrap.js' ) );
	}

	public function test_init_script_still_loads_when_bundled_bootstrap_js_is_disabled() {
		$options = BootstrapShortcodes::default_options();
		unset( $options['chk_default_options_js'] );
		update_option( 'bs_options', $options );

		$this->plugin->enqueue_frontend_assets();

		$scripts = $this->printed_scripts();
		$this->assertStringNotContainsString( 'js/bootstrap.js', $scripts );
		$this->assertStringContainsString( 'js/init.js', $scripts, 'init.js must not silently depend on the bundled Bootstrap handle' );
		$this->assertStringContainsString( 'jquery', $scripts );
	}

	public function test_bundled_css_is_skipped_when_disabled() {
		$options = BootstrapShortcodes::default_options();
		unset( $options['chk_default_options_css'] );
		update_option( 'bs_options', $options );

		$this->plugin->enqueue_frontend_assets();

		$styles = $this->printed_styles();
		$this->assertStringNotContainsString( 'css/bootstrap.css', $styles );
		$this->assertStringNotContainsString( 'css/shortcodes.css', $styles );
	}

	public function test_conditional_loading_is_off_by_default_so_assets_load_everywhere() {
		update_option( 'bs_options', BootstrapShortcodes::default_options() );
		$this->go_to( home_url( '/' ) );

		$this->plugin->enqueue_frontend_assets();

		$this->assertStringContainsString( 'css/bootstrap.css', $this->printed_styles() );
	}

	public function test_conditional_loading_skips_pages_without_shortcodes() {
		$options = BootstrapShortcodes::default_options();
		$options['chk_conditional_assets'] = '1';
		update_option( 'bs_options', $options );
		$post_id = self::factory()->post->create( array( 'post_content' => 'Plain content, no shortcodes.' ) );
		$this->go_to( get_permalink( $post_id ) );

		$this->plugin->enqueue_frontend_assets();

		$this->assertStringNotContainsString( 'css/bootstrap.css', $this->printed_styles() );
		$this->assertStringNotContainsString( 'js/init.js', $this->printed_scripts() );
	}

	public function test_conditional_loading_detects_shortcodes_in_the_main_query() {
		$options = BootstrapShortcodes::default_options();
		$options['chk_conditional_assets'] = '1';
		update_option( 'bs_options', $options );
		$post_id = self::factory()->post->create( array( 'post_content' => 'Before [bs_well]inside[/bs_well] after' ) );
		$this->go_to( get_permalink( $post_id ) );

		$this->plugin->enqueue_frontend_assets();

		$this->assertStringContainsString( 'css/bootstrap.css', $this->printed_styles() );
		$this->assertStringContainsString( 'js/init.js', $this->printed_scripts() );
	}

	public function test_conditional_loading_enqueues_late_when_a_shortcode_renders_anyway() {
		$options = BootstrapShortcodes::default_options();
		$options['chk_conditional_assets'] = '1';
		update_option( 'bs_options', $options );
		$post_id = self::factory()->post->create( array( 'post_content' => 'No shortcode in the post itself.' ) );
		$this->go_to( get_permalink( $post_id ) );

		$this->plugin->enqueue_frontend_assets();
		$this->assertStringNotContainsString( 'css/bootstrap.css', $this->printed_styles() );

		// A widget, template or custom field renders one anyway: the fallback kicks in.
		do_shortcode( '[bs_button value="Late"]' );

		// Second pass prints only what was queued since the head.
		$this->assertStringContainsString( 'css/bootstrap.css', $this->printed_styles() );
		$this->assertStringContainsString( 'js/init.js', $this->printed_scripts() );
	}

	public function test_filter_can_force_assets_off() {
		update_option( 'bs_options', BootstrapShortcodes::default_options() );
		add_filter( 'bs_shortcodes_load_assets', '__return_false' );

		$this->plugin->enqueue_frontend_assets();
		do_shortcode( '[bs_button value="Late"]' );

		$this->assertStringNotContainsString( 'css/bootstrap.css', $this->printed_styles() );
		$this->assertStringNotContainsString( 'js/init.js', $this->printed_scripts() );
		remove_filter( 'bs_shortcodes_load_assets', '__return_false' );
	}
}
