<?php
/**
 * Accessibility attributes that Bootstrap 3's reference markup carries and the
 * shortcodes were missing. None of these change layout.
 */
class ShortcodeAccessibilityTest extends WP_UnitTestCase {

	public function test_alert_close_button_is_labelled_and_not_hidden() {
		$output = do_shortcode( '[bs_notification type="info"]Hi[/bs_notification]' );

		$this->assertStringContainsString( '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>', $output );
		$this->assertStringNotContainsString( '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">', $output );
	}

	public function test_closed_collapse_item_states_collapsed() {
		$output = do_shortcode( '[bs_collapse id="g"][bs_citem id="c1" parent="g" title="One"]Body[/bs_citem][/bs_collapse]' );

		$this->assertStringContainsString( '<a class="accordion-toggle collapsed" data-toggle="collapse" aria-expanded="false" aria-controls="c1" data-parent="#g" href="#c1">One</a>', $output );
		$this->assertStringContainsString( '<div id="c1" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading_c1">', $output );
	}

	public function test_open_collapse_item_states_expanded() {
		$output = do_shortcode( '[bs_collapse id="g"][bs_citem id="c2" parent="g" title="Two" open="true"]Body[/bs_citem][/bs_collapse]' );

		$this->assertStringContainsString( '<a class="accordion-toggle" data-toggle="collapse" aria-expanded="true" aria-controls="c2" data-parent="#g" href="#c2">Two</a>', $output );
		$this->assertStringContainsString( '<div id="c2" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="heading_c2">', $output );
	}

	public function test_tabs_carry_wai_aria_roles() {
		$output = do_shortcode( '[bs_tabs][bs_thead][bs_tab class="active" href="#home" title="Home"][/bs_thead][bs_tcontents][bs_tcontent class="active" id="home"]Body[/bs_tcontent][/bs_tcontents][/bs_tabs]' );

		$this->assertStringContainsString( '<ul class="nav nav-tabs" role="tablist">', $output );
		$this->assertStringContainsString( '<li class="active" role="presentation"><a data-toggle="tab" href="#home" role="tab">Home</a></li>', $output );
		$this->assertStringContainsString( '<div class="tab-pane fade active in" id="home" role="tabpanel">', $output );
	}

	public function test_icon_is_hidden_from_assistive_tech() {
		$this->assertSame( '<i class="glyphicon glyphicon-star" aria-hidden="true"></i>', do_shortcode( '[bs_icon name="glyphicon glyphicon-star"]' ) );
	}
}
