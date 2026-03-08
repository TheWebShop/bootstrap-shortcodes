<?php
/**
 * Class ShortcodeAlertsTest
 *
 * @package Bootstrap_Shortcodes
 */

class ShortcodeAlertsTest extends WP_UnitTestCase {

	public function test_default_alert() {
		$output = do_shortcode( '[bs_notification]Hello World[/bs_notification]' );
		
		$this->assertStringContainsString( '<div class="alert alert-unknown alert-dismissible">', $output );
		$this->assertStringContainsString( '<button type="button" class="close" data-dismiss="alert"', $output );
		$this->assertStringContainsString( 'Hello World', $output );
	}

	public function test_alert_types() {
		$types = array( 'success', 'info', 'warning', 'danger' );
		
		foreach ( $types as $type ) {
			$output = do_shortcode( '[bs_notification type="' . $type . '"]Test[/bs_notification]' );
			$this->assertStringContainsString( 'alert-' . $type, $output );
		}
	}

	public function test_undismissible_alert() {
		$output = do_shortcode( '[bs_notification dismissible="false"]Fixed Alert[/bs_notification]' );
		
		$this->assertStringNotContainsString( 'alert-dismissible', $output );
		$this->assertStringNotContainsString( '<button', $output );
		$this->assertStringContainsString( 'Fixed Alert', $output );
	}
}
