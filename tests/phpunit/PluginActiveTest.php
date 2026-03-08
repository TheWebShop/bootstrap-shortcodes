<?php
/**
 * Class Test_Plugin_Active
 *
 * @package Bootstrap_Shortcodes
 */

/**
 * Basic tests to checking that the plugin initializes properly.
 */
class PluginActiveTest extends WP_UnitTestCase {

	/**
	 * Test that the main plugin class exists
	 */
	public function test_plugin_class_exists() {
		$this->assertTrue( class_exists( 'BootstrapShortcodes' ) );
	}

	/**
	 * Test that the global $bscodes instance is set and is the correct class
	 */
	public function test_plugin_instance() {
		$bscodes = new BootstrapShortcodes();
		$this->assertInstanceOf( 'BootstrapShortcodes', $bscodes );
	}

	/**
	 * Test that the shortcodes list is populated
	 */
	public function test_shortcode_list() {
		$bscodes = new BootstrapShortcodes();
		$this->assertNotEmpty( $bscodes->shortcodes );
		$this->assertContains( 'grid', $bscodes->shortcodes );
		$this->assertContains( 'buttons', $bscodes->shortcodes );
	}
}
