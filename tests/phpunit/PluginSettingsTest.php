<?php
/**
 * Settings lifecycle: activation defaults, reactivation, and sanitisation.
 */
class PluginSettingsTest extends WP_UnitTestCase {

	private $plugin;

	public function set_up() {
		parent::set_up();
		$this->plugin = new BootstrapShortcodes();
		delete_option( 'bs_options' );
	}

	public function tear_down() {
		delete_option( 'bs_options' );
		parent::tear_down();
	}

	public function test_activation_writes_defaults_when_no_settings_exist() {
		$this->plugin->add_options_defaults();

		$this->assertSame( BootstrapShortcodes::default_options(), get_option( 'bs_options' ) );
	}

	public function test_activation_keeps_existing_settings() {
		$custom = array( 'chk_default_options_grid' => '1' ); // CSS, JS and every other button switched off.
		update_option( 'bs_options', $custom );

		$this->plugin->add_options_defaults();

		$this->assertSame( $custom, get_option( 'bs_options' ), 'reactivating must not reset settings' );
	}

	public function test_sanitize_keeps_only_known_keys_as_checked() {
		$clean = $this->plugin->sanitize_options( array(
			'chk_default_options_css' => '1',
			'chk_default_options_tabs' => 'yes',
			'chk_conditional_assets' => '1',
			'chk_default_options_js' => '',
			'evil_key' => '<script>',
		) );

		$this->assertSame( array(
			'chk_default_options_css' => '1',
			'chk_default_options_tabs' => '1',
			'chk_conditional_assets' => '1',
		), $clean );
	}

	public function test_sanitize_tolerates_non_array_input() {
		$this->assertSame( array(), $this->plugin->sanitize_options( null ) );
		$this->assertSame( array(), $this->plugin->sanitize_options( 'junk' ) );
	}

	public function test_sanitizer_is_registered_with_the_settings_api() {
		$this->plugin->register_settings();
		$registered = get_registered_settings();

		$this->assertArrayHasKey( 'bs_options', $registered );
		$this->assertSame( array( $this->plugin, 'sanitize_options' ), $registered['bs_options']['sanitize_callback'] );
	}
}
