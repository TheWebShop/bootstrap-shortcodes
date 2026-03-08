<?php
/**
 * Class ShortcodeButtonsTest
 *
 * @package Bootstrap_Shortcodes
 */

class ShortcodeButtonsTest extends WP_UnitTestCase {

	public function test_default_button() {
		$output = do_shortcode( '[bs_button]' );
		
		$this->assertStringContainsString( '<a class="btn btn-default btn-default" href="#">button</a>', $output );
	}

	public function test_button_attributes() {
		$output = do_shortcode( '[bs_button size="lg" type="primary" value="Click Me" href="https://example.com"]' );
		
		$this->assertStringContainsString( 'btn-lg', $output );
		$this->assertStringContainsString( 'btn-primary', $output );
		$this->assertStringContainsString( 'href="https://example.com"', $output );
		$this->assertStringContainsString( '>Click Me</a>', $output );
	}
}
