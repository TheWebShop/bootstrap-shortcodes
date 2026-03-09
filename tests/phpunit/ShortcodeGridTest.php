<?php
/**
 * Class ShortcodeGridTest
 *
 * @package Bootstrap_Shortcodes
 */

class ShortcodeGridTest extends WP_UnitTestCase {

	public function test_default_row() {
		$output = do_shortcode( '[bs_row]Inner[/bs_row]' );
		
		$this->assertStringContainsString( '<div class="row">', $output );
		$this->assertStringContainsString( 'Inner', $output );
		$this->assertStringContainsString( '</div>', $output );
	}

	public function test_row_with_custom_class() {
		$output = do_shortcode( '[bs_row class="custom-row-class"]Content[/bs_row]' );
		
		$this->assertStringContainsString( '<div class="custom-row-class">', $output );
	}

	public function test_default_col() {
		$output = do_shortcode( '[bs_col]Col Content[/bs_col]' );
		
		$this->assertStringContainsString( '<div class="col-sm-1">', $output );
		$this->assertStringContainsString( 'Col Content', $output );
	}

	public function test_col_with_custom_class() {
		$output = do_shortcode( '[bs_col class="col-md-6 col-lg-4"]Nested[/bs_col]' );
		
		$this->assertStringContainsString( '<div class="col-md-6 col-lg-4">', $output );
	}
	
	public function test_nested_grid() {
		$output = do_shortcode( '[bs_row][bs_col class="col-xs-6"]Left[/bs_col][bs_col class="col-xs-6"]Right[/bs_col][/bs_row]' );
		
		// Checking structure roughly
		$this->assertMatchesRegularExpression( '/<div class="row">.*?<div class="col-xs-6">Left<\/div>.*?<div class="col-xs-6">Right<\/div>.*?<\/div>/s', $output );
	}
}
