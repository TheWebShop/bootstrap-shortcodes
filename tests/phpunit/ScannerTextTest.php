<?php
/**
 * The text-level scanner: finds bs_ shortcodes the way do_shortcode() would and
 * flags what would complicate a migration. Pure text in, structured data out;
 * never renders anything.
 */
class ScannerTextTest extends WP_UnitTestCase {

	public function test_finds_each_component_with_attributes_and_offsets() {
		$text   = 'Intro [bs_well size="lg"]Hello[/bs_well] and [bs_button value="Go" href="https://x.test/"] end';
		$result = BS_Scanner::scan_text( $text );

		$this->assertCount( 2, $result['occurrences'] );
		$this->assertSame( 'bs_well', $result['occurrences'][0]['tag'] );
		$this->assertSame( array( 'size' => 'lg' ), $result['occurrences'][0]['attrs'] );
		$this->assertSame( 'Hello', $result['occurrences'][0]['content'] );
		$this->assertSame( 6, $result['occurrences'][0]['offset'] );
		$this->assertSame( 'bs_button', $result['occurrences'][1]['tag'] );
		$this->assertSame( array( 'bs_well' => 1, 'bs_button' => 1 ), $result['counts'] );
		$this->assertSame( array(), $result['flags'] );
	}

	public function test_nested_components_are_counted_at_every_depth() {
		$text   = '[bs_row][bs_col class="col-sm-6"][bs_well]Deep[/bs_well][/bs_col][/bs_row]';
		$result = BS_Scanner::scan_text( $text );

		$this->assertSame( array( 'bs_row' => 1, 'bs_col' => 1, 'bs_well' => 1 ), $result['counts'] );
		$depths = wp_list_pluck( $result['occurrences'], 'depth', 'tag' );
		$this->assertSame( array( 'bs_row' => 0, 'bs_col' => 1, 'bs_well' => 2 ), $depths );
		$this->assertContains( 'nesting', $result['flags'] );
	}

	public function test_expected_structural_nesting_is_not_flagged() {
		// Tabs and collapse are nested by design; only foreign nesting is a complication.
		$text   = '[bs_collapse id="g"][bs_citem id="a" parent="g" title="A"]Plain text[/bs_citem][/bs_collapse]';
		$result = BS_Scanner::scan_text( $text );

		$this->assertNotContains( 'nesting', $result['flags'] );
	}

	public function test_unknown_attributes_are_flagged() {
		$result = BS_Scanner::scan_text( '[bs_well size="sm" color="red"]x[/bs_well]' );

		$this->assertContains( 'unknown_attribute', $result['flags'] );
		$this->assertSame( array( 'color' ), $result['occurrences'][0]['unknown_attrs'] );
	}

	public function test_custom_classes_are_flagged() {
		$result = BS_Scanner::scan_text( '[bs_row class="row my-special-row"][bs_col class="col-md-4 highlight"]x[/bs_col][/bs_row]' );

		$this->assertContains( 'custom_class', $result['flags'] );
		$this->assertTrue( $result['occurrences'][0]['custom_class'] );
		$this->assertTrue( $result['occurrences'][1]['custom_class'] );

		$clean = BS_Scanner::scan_text( '[bs_row class="row"][bs_col class="col-md-4 col-xs-12"]x[/bs_col][/bs_row]' );
		$this->assertNotContains( 'custom_class', $clean['flags'] );
	}

	public function test_malformed_syntax_is_flagged() {
		$unclosed = BS_Scanner::scan_text( 'Start [bs_well]never closed' );
		$this->assertContains( 'malformed', $unclosed['flags'] );
		$this->assertSame( array( 'bs_well' ), $unclosed['unbalanced'] );

		$typo = BS_Scanner::scan_text( '[bs_butotn value="x"]' );
		$this->assertContains( 'unknown_tag', $typo['flags'] );
		$this->assertSame( array( 'bs_butotn' ), $typo['unknown_tags'] );
	}

	public function test_escaped_shortcodes_are_reported_separately() {
		$result = BS_Scanner::scan_text( 'Docs: [[bs_well]example[/bs_well]]' );

		$this->assertSame( array(), $result['counts'] );
		$this->assertSame( 1, $result['escaped'] );
	}

	public function test_duplicate_ids_are_flagged() {
		$result = BS_Scanner::scan_text( '[bs_tcontent id="one"]a[/bs_tcontent][bs_tcontent id="one"]b[/bs_tcontent]' );

		$this->assertContains( 'duplicate_id', $result['flags'] );
		$this->assertSame( array( 'one' ), $result['duplicate_ids'] );
	}

	public function test_missing_required_ids_are_flagged() {
		$result = BS_Scanner::scan_text( '[bs_collapse][bs_citem title="A"]x[/bs_citem][/bs_collapse]' );

		$this->assertContains( 'missing_id', $result['flags'] );
	}

	public function test_third_party_shortcodes_and_html_are_left_alone() {
		$result = BS_Scanner::scan_text( '<p>[gallery ids="1"]</p>[bs_lead]<strong>L</strong>[/bs_lead][contact-form-7 id="2"]' );

		$this->assertSame( array( 'bs_lead' => 1 ), $result['counts'] );
		$this->assertSame( array(), $result['unknown_tags'] );
	}

	public function test_empty_and_non_string_input() {
		$this->assertSame( array(), BS_Scanner::scan_text( '' )['occurrences'] );
		$this->assertSame( array(), BS_Scanner::scan_text( null )['occurrences'] );
		$this->assertSame( array(), BS_Scanner::scan_text( 'No shortcodes at all [other]' )['occurrences'] );
	}
}
