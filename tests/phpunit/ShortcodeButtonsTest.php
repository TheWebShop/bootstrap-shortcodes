<?php
/**
 * Class ShortcodeButtonsTest
 *
 * @package Bootstrap_Shortcodes
 */

class ShortcodeButtonsTest extends WP_UnitTestCase {

	public function test_default_button() {
		$output = do_shortcode( '[bs_button]' );
		
		$this->assertStringContainsString( '<a class="btn btn-default" href="#">button</a>', $output );
	}

	public function test_button_attributes() {
		$output = do_shortcode( '[bs_button size="lg" type="primary" value="Click Me" href="https://example.com"]' );
		
		$this->assertStringContainsString( 'btn-lg', $output );
		$this->assertStringContainsString( 'btn-primary', $output );
		$this->assertStringContainsString( 'href="https://example.com"', $output );
		$this->assertStringContainsString( '>Click Me</a>', $output );
	}

	public function test_default_output_unchanged_without_target() {
		$this->assertSame( '<a class="btn btn-default" href="#">button</a>', do_shortcode( '[bs_button]' ) );
		$this->assertSame(
			'<a class="btn btn-primary btn-lg" href="https://example.com">Click Me</a>',
			do_shortcode( '[bs_button size="lg" type="primary" value="Click Me" href="https://example.com"]' )
		);
	}

	public function test_empty_target_is_omitted() {
		$output = do_shortcode( '[bs_button target=""]' );

		$this->assertSame( '<a class="btn btn-default" href="#">button</a>', $output );
	}

	public function test_target_blank_adds_noopener() {
		$output = do_shortcode( '[bs_button href="https://example.com" target="_blank"]' );

		$this->assertSame( '<a class="btn btn-default" href="https://example.com" target="_blank" rel="noopener noreferrer">button</a>', $output );
	}

	public function test_target_is_case_insensitive() {
		$output = do_shortcode( '[bs_button target="_BLANK"]' );

		$this->assertStringContainsString( 'target="_blank" rel="noopener noreferrer"', $output );
	}

	/**
	 * @dataProvider allowed_same_context_targets
	 */
	public function test_allowed_targets_without_rel( $target ) {
		$output = do_shortcode( '[bs_button target="' . $target . '"]' );

		$this->assertSame( '<a class="btn btn-default" href="#" target="' . $target . '">button</a>', $output );
	}

	public function allowed_same_context_targets() {
		return array(
			array( '_self' ),
			array( '_parent' ),
			array( '_top' ),
		);
	}

	/**
	 * @dataProvider disallowed_targets
	 */
	public function test_disallowed_target_is_dropped( $target ) {
		$output = do_shortcode( "[bs_button target='" . $target . "']" );

		$this->assertSame( '<a class="btn btn-default" href="#">button</a>', $output );
	}

	public function disallowed_targets() {
		return array(
			array( 'myframe' ),
			array( '_blank" onmouseover="alert(1)' ),
			array( '_blank><script>alert(1)</script>' ),
			array( 'javascript:alert(1)' ),
		);
	}
}
