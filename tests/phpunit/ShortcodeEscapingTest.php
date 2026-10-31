<?php
/**
 * Output-context escaping across every handler: attribute values must never
 * break out of their quotes, and URLs must never carry a script protocol.
 */
class ShortcodeEscapingTest extends WP_UnitTestCase {

	private function dom( $html ) {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="bs-test-root">' . $html . '</div>' );
		libxml_clear_errors();
		return $dom;
	}

	private function assertNoInjectedAttributes( $html ) {
		$dom = $this->dom( $html );
		foreach ( $dom->getElementsByTagName( '*' ) as $el ) {
			foreach ( $el->attributes as $attr ) {
				$this->assertStringStartsNotWith( 'on', strtolower( $attr->name ), 'event handler attribute injected in: ' . $html );
				$this->assertNotSame( 'style', strtolower( $attr->name ), 'style attribute injected in: ' . $html );
				if ( in_array( strtolower( $attr->name ), array( 'href', 'src' ), true ) ) {
					$this->assertStringNotContainsString( 'javascript:', strtolower( $attr->value ), 'script URL survived in: ' . $html );
				}
			}
		}
		$this->assertSame( 0, $dom->getElementsByTagName( 'script' )->length, 'script element injected in: ' . $html );
	}

	public function malicious_shortcodes() {
		$break = 'x" onmouseover="alert(1)" style="color:red';
		$img   = '<img src=x onerror=alert(1)>';
		return array(
			'row class'          => array( "[bs_row class='{$break}']c[/bs_row]" ),
			'col class'          => array( "[bs_col class='{$break}']c[/bs_col]" ),
			'tab href script'    => array( '[bs_tab href="javascript:alert(1)" title="t"]' ),
			'tab class'          => array( "[bs_tab class='{$break}' title='{$img}' href='#a']" ),
			'dropdown id'        => array( "[bs_dropdown id='{$break}' class='{$break}' title='{$img}']x[/bs_dropdown]" ),
			'tcontent id'        => array( "[bs_tcontent id='{$break}' class='{$break}']c[/bs_tcontent]" ),
			'collapse id'        => array( "[bs_collapse id='{$break}']c[/bs_collapse]" ),
			'citem all'          => array( "[bs_citem id='{$break}' parent='{$break}' title='{$img}' open='{$break}']c[/bs_citem]" ),
			'alert type'         => array( "[bs_notification type='{$break}' dismissible='{$break}']c[/bs_notification]" ),
			'well size'          => array( "[bs_well size='{$break}']c[/bs_well]" ),
			'button all'         => array( "[bs_button size='{$break}' type='{$break}' value='{$img}' href='javascript:alert(1)' target='{$break}']" ),
			'label type'         => array( "[bs_label type='{$break}']c[/bs_label]" ),
			'icon name'          => array( "[bs_icon name='{$break}']" ),
			'tooltip all'        => array( "[bs_tooltip placement='{$break}' trigger='{$break}' href='javascript:alert(1)']{$img}[/bs_tooltip]" ),
		);
	}

	/**
	 * @dataProvider malicious_shortcodes
	 */
	public function test_attribute_values_cannot_inject_markup( $shortcode ) {
		$this->assertNoInjectedAttributes( do_shortcode( $shortcode ) );
	}
}
