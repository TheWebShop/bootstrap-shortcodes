<?php
/**
 * The converter: shortcode text in, block markup or static HTML out, with
 * everything it could not convert left exactly where it was and explained.
 */
class MigratorConvertTest extends WP_UnitTestCase {

	private function blocks( $content, $style = 'inline' ) {
		return BS_Migrator::convert( $content, array( 'mode' => 'blocks', 'style' => $style ) );
	}

	private function html( $content, $style = 'inline' ) {
		return BS_Migrator::convert( $content, array( 'mode' => 'html', 'style' => $style ) );
	}

	/* ---- untouched things ------------------------------------------------ */

	public function test_content_without_our_shortcodes_is_returned_byte_for_byte() {
		$content = "<p>Hello</p>\n[gallery ids=\"1,2\"]\n<!-- wp:paragraph -->\n<p>Block</p>\n<!-- /wp:paragraph -->";
		$r = $this->blocks( $content );
		$this->assertSame( $content, $r['content'] );
		$this->assertSame( 0, $r['converted'] );
	}

	public function test_escaped_shortcodes_and_unknown_bs_tags_are_left_alone() {
		$content = 'Docs: [[bs_well]x[/bs_well]] and [bs_bogus a="1"] and [bs_well]real[/bs_well]';
		$r = $this->html( $content );
		$this->assertStringContainsString( '[[bs_well]x[/bs_well]]', $r['content'] );
		$this->assertStringContainsString( '[bs_bogus a="1"]', $r['content'] );
		$this->assertStringNotContainsString( '[bs_well]real', $r['content'] );
		$this->assertSame( 1, $r['converted'] );
	}

	public function test_unbalanced_tags_are_skipped_and_explained() {
		$content = '[bs_well]never closed [bs_button value="ok"]';
		$r = $this->html( $content );
		$this->assertStringStartsWith( '[bs_well]never closed', $r['content'] );
		$this->assertStringNotContainsString( '[bs_button', $r['content'] );
		$this->assertCount( 1, $r['skipped'] );
		$this->assertSame( 'bs_well', $r['skipped'][0]['tag'] );
		$this->assertSame( 'unbalanced', $r['skipped'][0]['reason'] );
	}

	public function test_unknown_attributes_are_dropped_with_a_note() {
		$r = $this->html( '[bs_well size="sm" color="red"]x[/bs_well]' );
		$this->assertStringNotContainsString( 'red', $r['content'] );
		$this->assertStringContainsString( 'Attribute "color" on [bs_well]', implode( "\n", $r['notes'] ) );
	}

	public function test_editor_line_break_markers_are_removed_like_the_handlers_do() {
		$r = $this->html( '[bs_well]<br class="nc" />Text<br class="nc"/>[/bs_well]' );
		$this->assertStringNotContainsString( 'class="nc"', $r['content'] );
		$this->assertStringContainsString( 'Text', $r['content'] );
	}

	/* ---- grid ------------------------------------------------------------- */

	public function test_grid_becomes_columns_blocks_with_widths() {
		$r = $this->blocks( '[bs_row][bs_col class="col-sm-8"]Main[/bs_col][bs_col class="col-sm-4"]Side[/bs_col][/bs_row]' );
		$c = $r['content'];
		$this->assertStringContainsString( '<!-- wp:columns -->', $c );
		$this->assertStringContainsString( '<!-- wp:column {"width":"66.67%"} -->', $c );
		$this->assertStringContainsString( '<div class="wp-block-column" style="flex-basis:66.67%">', $c );
		$this->assertStringContainsString( '<!-- wp:column {"width":"33.33%"} -->', $c );
		$this->assertStringContainsString( '<!-- wp:paragraph -->' . "\n" . '<p>Main</p>' . "\n" . '<!-- /wp:paragraph -->', $c );
		$this->assertSame( 3, $r['converted'] );
		$blocks = parse_blocks( $c );
		$this->assertSame( 'core/columns', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['blockName'] );
	}

	public function test_grid_custom_classes_and_offsets_are_kept_or_noted() {
		$r = $this->blocks( '[bs_row class="row fancy"][bs_col class="col-md-6 col-md-offset-3 hero"]x[/bs_col][/bs_row]' );
		$this->assertStringContainsString( '<!-- wp:columns {"className":"fancy"} -->', $r['content'] );
		$this->assertStringContainsString( '<div class="wp-block-columns fancy">', $r['content'] );
		$this->assertStringContainsString( '{"width":"50%","className":"hero"}', $r['content'] );
		$this->assertStringContainsString( 'offset', wp_json_encode( $r['notes'] ) );
	}

	public function test_grid_in_html_mode_keeps_bootstrap_markup() {
		$r = $this->html( '[bs_row][bs_col class="col-sm-6"]A[/bs_col][/bs_row]' );
		$this->assertSame( '<div class="row"><div class="col-sm-6">A</div></div>', $r['content'] );
		$this->assertContains( 'bootstrap-css', $r['dependencies'] );
	}

	/* ---- buttons ---------------------------------------------------------- */

	public function test_standalone_button_becomes_a_buttons_block() {
		$r = $this->blocks( "Before\n\n[bs_button type=\"primary\" value=\"Go\" href=\"https://x.test/\" target=\"_blank\"]\n\nAfter" );
		$c = $r['content'];
		$this->assertStringContainsString( '<!-- wp:buttons -->', $c );
		$this->assertStringContainsString( '<a class="wp-block-button__link wp-element-button" href="https://x.test/" target="_blank" rel="noopener noreferrer">Go</a>', $c );
		$blocks = array_values( array_filter( parse_blocks( $c ), function ( $b ) { return null !== $b['blockName']; } ) );
		$this->assertSame( array( 'core/paragraph', 'core/buttons', 'core/paragraph' ), wp_list_pluck( $blocks, 'blockName' ) );
	}

	public function test_inline_button_stays_inline_html() {
		$r = $this->blocks( 'Click [bs_button value="here" href="/x"] to continue.' );
		$this->assertStringContainsString( '<p>Click <a class="btn btn-default" href="/x">here</a> to continue.</p>', $r['content'] );
		$this->assertStringNotContainsString( 'wp:buttons', $r['content'] );
	}

	public function test_button_html_mode_is_identical_to_the_shortcode_output() {
		$shortcode = '[bs_button size="lg" type="primary" value="Click Me" href="https://example.com" target="_blank"]';
		$this->assertSame( do_shortcode( $shortcode ), $this->html( $shortcode )['content'] );
	}

	/* ---- wells, alerts, lead ---------------------------------------------- */

	public function test_well_inline_style_is_self_contained() {
		$r = $this->html( '[bs_well size="lg"]Text[/bs_well]' );
		$this->assertStringContainsString( '<div class="well well-lg" style="', $r['content'] );
		$this->assertStringContainsString( 'background-color:#f5f5f5', $r['content'] );
		$this->assertStringContainsString( 'padding:24px', $r['content'] );
		$this->assertNotContains( 'bootstrap-css', $r['dependencies'] );
	}

	public function test_well_classes_style_needs_bootstrap_css() {
		$r = $this->html( '[bs_well]Text[/bs_well]', 'classes' );
		$this->assertSame( '<div class="well">Text</div>', $r['content'] );
		$this->assertContains( 'bootstrap-css', $r['dependencies'] );
	}

	public function test_well_in_blocks_mode_becomes_a_group_block_with_inner_paragraph() {
		$r = $this->blocks( '[bs_well]One<br class="nc"/>[/bs_well]', 'classes' );
		$this->assertStringContainsString( '<!-- wp:group {"className":"well"} -->', $r['content'] );
		$this->assertStringContainsString( '<div class="wp-block-group well"><!-- wp:paragraph -->', $r['content'] );
		$html = $this->blocks( '[bs_well]One[/bs_well]', 'inline' );
		$this->assertStringContainsString( '<!-- wp:html -->', $html['content'] );
		$this->assertStringContainsString( '<div class="well" style="', $html['content'] );
	}

	public function test_alert_loses_its_dismiss_button_and_says_so() {
		$r = $this->html( '[bs_notification type="warning"]Careful[/bs_notification]' );
		$this->assertStringContainsString( '<div class="alert alert-warning" role="alert" style="', $r['content'] );
		$this->assertStringContainsString( '#fcf8e3', $r['content'] );
		$this->assertStringNotContainsString( 'data-dismiss', $r['content'] );
		$this->assertStringContainsString( 'dismiss', wp_json_encode( $r['notes'] ) );
	}

	public function test_lead_becomes_a_large_paragraph() {
		$r = $this->blocks( '[bs_lead]Intro[/bs_lead]' );
		$this->assertStringContainsString( '<!-- wp:paragraph {"className":"lead","fontSize":"large"} -->' . "\n" . '<p class="lead has-large-font-size">Intro</p>', $r['content'] );
		$h = $this->html( '[bs_lead]Intro[/bs_lead]' );
		$this->assertStringContainsString( '<p class="lead" style="font-size:1.3em;font-weight:300">Intro</p>', $h['content'] );
	}

	/* ---- inline components ------------------------------------------------ */

	public function test_label_stays_inline_with_fallback_styling() {
		$r = $this->html( 'Status: [bs_label type="success"]New[/bs_label]!' );
		$this->assertStringContainsString( 'Status: <span class="label label-success" style="', $r['content'] );
		$this->assertStringContainsString( 'background-color:#5cb85c', $r['content'] );
		$this->assertStringEndsWith( '>New</span>!', $r['content'] );
	}

	public function test_icon_becomes_inline_svg_from_the_bundled_font() {
		$r = $this->html( 'A [bs_icon name="glyphicon glyphicon-star"] star' );
		$this->assertMatchesRegularExpression( '#A <svg class="bs-icon bs-icon-star" [^>]*aria-hidden="true"[^>]*><path [^>]*d="M[^"]+"/></svg> star#', $r['content'] );
		$this->assertStringContainsString( 'viewBox="0 0 1200 1200"', $r['content'] );
		$this->assertNotContains( 'glyphicons', $r['dependencies'] );
	}

	public function test_icon_without_a_known_glyph_keeps_the_markup_and_flags_the_font() {
		$r = $this->html( '[bs_icon name="glyphicon glyphicon-not-a-glyph"]' );
		$this->assertSame( '<i class="glyphicon glyphicon-not-a-glyph" aria-hidden="true"></i>', $r['content'] );
		$this->assertContains( 'glyphicons', $r['dependencies'] );
		$classes = $this->html( '[bs_icon name="glyphicon glyphicon-star"]', 'classes' );
		$this->assertSame( '<i class="glyphicon glyphicon-star" aria-hidden="true"></i>', $classes['content'] );
		$this->assertContains( 'glyphicons', $classes['dependencies'] );
	}

	public function test_tooltip_becomes_a_native_title() {
		$r = $this->html( 'See [bs_tooltip placement="left"]the details[/bs_tooltip].' );
		$this->assertStringContainsString( 'See <span class="bs-tooltip" title="the details" style="', $r['content'] );
		$this->assertStringContainsString( '>the details</span>.', $r['content'] );
		$this->assertStringContainsString( 'placement', wp_json_encode( $r['notes'] ) );
	}

	/* ---- collapse and tabs ------------------------------------------------- */

	public function test_collapse_becomes_details_blocks_sharing_a_name() {
		$r = $this->blocks( '[bs_collapse id="faq"][bs_citem id="q1" parent="faq" title="First?"]A1[/bs_citem][bs_citem id="q2" parent="faq" title="Second?" open="true"]A2[/bs_citem][/bs_collapse]' );
		$c = $r['content'];
		$this->assertStringContainsString( '<!-- wp:details {"name":"faq"} -->' . "\n" . '<details class="wp-block-details" name="faq" id="q1"><summary>First?</summary>', $c );
		$this->assertStringContainsString( '<!-- wp:details {"showContent":true,"name":"faq"} -->' . "\n" . '<details class="wp-block-details" name="faq" id="q2" open><summary>Second?</summary>', $c );
		$this->assertStringContainsString( '<p>A2</p>', $c );
		$blocks = array_values( array_filter( parse_blocks( $c ), function ( $b ) { return null !== $b['blockName']; } ) );
		$this->assertSame( array( 'core/details', 'core/details' ), wp_list_pluck( $blocks, 'blockName' ) );
	}

	public function test_collapse_html_mode_is_native_details() {
		$r = $this->html( '[bs_collapse id="g"][bs_citem id="a" parent="g" title="T &amp; A"]Body[/bs_citem][/bs_collapse]' );
		$this->assertSame( '<div class="bs-collapse" id="g"><details name="g" id="a"><summary>T &amp; A</summary>Body</details></div>', $r['content'] );
	}

	public function test_tabs_become_details_sections_with_titles_matched_by_href() {
		$sc = '[bs_tabs][bs_thead][bs_tab class="active" href="#one" title="One"][bs_dropdown title="More"][bs_tab href="#two" title="Two"][/bs_dropdown][/bs_thead]'
			. '[bs_tcontents][bs_tcontent class="active" id="one"]First pane[/bs_tcontent][bs_tcontent id="two"]Second pane[/bs_tcontent][/bs_tcontents][/bs_tabs]';
		$r = $this->html( $sc );
		$this->assertSame( '<div class="bs-tabs"><details id="one" open><summary>One</summary>First pane</details><details id="two"><summary>More: Two</summary>Second pane</details></div>', $r['content'] );
		$this->assertStringContainsString( 'tabs', wp_json_encode( $r['notes'] ) );

		$b = $this->blocks( $sc );
		$this->assertStringContainsString( '<!-- wp:details {"showContent":true} -->' . "\n" . '<details class="wp-block-details" id="one" open><summary>One</summary>', $b['content'] );
	}

	public function test_tab_pane_without_a_matching_tab_gets_a_generic_title() {
		$r = $this->html( '[bs_tabs][bs_thead][bs_tab href="#a" title="A"][/bs_thead][bs_tcontents][bs_tcontent id="a"]x[/bs_tcontent][bs_tcontent id="orphan"]y[/bs_tcontent][/bs_tcontents][/bs_tabs]' );
		$this->assertStringContainsString( '<details id="orphan"><summary>Section 2</summary>y</details>', $r['content'] );
	}

	/* ---- mixed content and safety ------------------------------------------ */

	public function test_mixed_html_inside_a_container_becomes_a_classic_block() {
		$r = $this->blocks( '[bs_well]<h3>Title</h3><ul><li>a</li></ul>[/bs_well]', 'classes' );
		$this->assertStringContainsString( '<!-- wp:freeform -->' . "\n" . '<h3>Title</h3><ul><li>a</li></ul>' . "\n" . '<!-- /wp:freeform -->', $r['content'] );
	}

	public function test_third_party_shortcodes_inside_containers_survive() {
		$r = $this->html( '[bs_col class="col-sm-6"][contact-form-7 id="3"][/bs_col]' );
		$this->assertSame( '<div class="col-sm-6">[contact-form-7 id="3"]</div>', $r['content'] );
	}

	public function test_attribute_values_are_escaped_in_output() {
		$r = $this->html( '[bs_citem id=\'x" onmouseover="alert(1)\' title=\'<img src=x onerror=alert(1)>\']b[/bs_citem]' );
		$this->assertStringNotContainsString( 'onmouseover="alert', $r['content'] );
		$this->assertStringNotContainsString( '<img', $r['content'] );
	}

	public function test_auto_mode_picks_blocks_only_for_block_content() {
		$classic = BS_Migrator::convert( '[bs_lead]x[/bs_lead]', array( 'mode' => 'auto' ) );
		$this->assertStringStartsWith( '<p class="lead"', $classic['content'] );
		$block = BS_Migrator::convert( "<!-- wp:paragraph -->\n<p>p</p>\n<!-- /wp:paragraph -->\n[bs_lead]x[/bs_lead]", array( 'mode' => 'auto' ) );
		$this->assertStringContainsString( '<!-- wp:paragraph {"className":"lead"', $block['content'] );
	}

	public function test_block_mode_output_is_what_the_editor_would_save() {
		$content = '[bs_row][bs_col class="col-sm-6"][bs_well]Nested [bs_label]tag[/bs_label][/bs_well][/bs_col][/bs_row]';
		$r = $this->blocks( $content, 'classes' );
		// Round trip through core's parser/serializer must be lossless, or the editor would flag the blocks.
		$this->assertSame( $r['content'], serialize_blocks( parse_blocks( $r['content'] ) ) );
	}

	/* ---- block documents ---------------------------------------------------- */

	public function test_shortcode_block_is_replaced_by_the_converted_blocks() {
		$doc = "<!-- wp:paragraph -->\n<p>Intro</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[bs_row][bs_col class=\"col-sm-6\"]A[/bs_col][bs_col class=\"col-sm-6\"]B[/bs_col][/bs_row]\n<!-- /wp:shortcode -->";
		$r   = BS_Migrator::convert( $doc, array( 'mode' => 'auto' ) );
		$this->assertSame( 'blocks', $r['mode'] );
		$this->assertStringNotContainsString( 'wp:shortcode', $r['content'] );
		$names = wp_list_pluck( array_filter( parse_blocks( $r['content'] ), function ( $b ) { return null !== $b['blockName']; } ), 'blockName' );
		$this->assertSame( array( 'core/paragraph', 'core/columns' ), array_values( $names ) );
		$this->assertSame( $r['content'], serialize_blocks( parse_blocks( $r['content'] ) ) );
	}

	public function test_paragraph_holding_only_a_shortcode_becomes_that_block() {
		$doc = "<!-- wp:paragraph -->\n<p>[bs_button value=\"Go\" href=\"/go\"]</p>\n<!-- /wp:paragraph -->";
		$r   = BS_Migrator::convert( $doc, array( 'mode' => 'auto' ) );
		$this->assertStringContainsString( '<!-- wp:buttons -->', $r['content'] );
		$this->assertStringNotContainsString( '<p>', $r['content'] );
	}

	public function test_inline_shortcodes_inside_paragraph_and_classic_blocks_stay_in_place() {
		$doc = "<!-- wp:paragraph -->\n<p>New: [bs_label type=\"info\"]beta[/bs_label] today</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:freeform -->\n<h3>Old</h3>\n[bs_well]Classic well[/bs_well]\n<!-- /wp:freeform -->";
		$r   = BS_Migrator::convert( $doc, array( 'mode' => 'auto' ) );
		$blocks = array_values( array_filter( parse_blocks( $r['content'] ), function ( $b ) { return null !== $b['blockName']; } ) );
		$this->assertSame( array( 'core/paragraph', 'core/freeform' ), wp_list_pluck( $blocks, 'blockName' ) );
		$this->assertStringContainsString( '<p>New: <span class="label label-info" style="', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( '<div class="well" style="', $blocks[1]['innerHTML'] );
		$this->assertStringNotContainsString( '[bs_', $r['content'] );
		$this->assertSame( 2, $r['converted'] );
	}

	public function test_shortcodes_inside_nested_blocks_are_reached() {
		$doc = "<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:shortcode -->\n[bs_lead]Deep[/bs_lead]\n<!-- /wp:shortcode --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->";
		$r   = BS_Migrator::convert( $doc, array( 'mode' => 'auto' ) );
		$this->assertStringContainsString( '<p class="lead has-large-font-size">Deep</p>', $r['content'] );
		$this->assertStringNotContainsString( 'wp:shortcode', $r['content'] );
		$this->assertSame( 'core/paragraph', parse_blocks( $r['content'] )[0]['innerBlocks'][0]['innerBlocks'][0]['blockName'] );
	}
}
