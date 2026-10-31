<?php
/**
 * The site-level scan: bounded batches over posts, plus the other surfaces
 * (widgets, options, post meta, term descriptions, theme files), and the
 * shareable summary that must not identify the site.
 */
class ScannerSiteTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		BS_Scanner::reset();
	}

	public function tear_down() {
		BS_Scanner::reset();
		delete_option( 'widget_text' );
		delete_option( 'bs_test_builder_option' );
		parent::tear_down();
	}

	private function run_to_completion( $limit = 100 ) {
		BS_Scanner::start();
		$guard = 0;
		do {
			$state = BS_Scanner::run_step( $limit );
		} while ( empty( $state['finished'] ) && ++$guard < 1000 );
		return BS_Scanner::results();
	}

	public function test_scan_covers_statuses_and_types_and_counts_published_separately() {
		$published = self::factory()->post->create( array( 'post_status' => 'publish', 'post_content' => '[bs_well]a[/bs_well] [bs_well]b[/bs_well]' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft', 'post_content' => '[bs_button value="d"]' ) );
		$private   = self::factory()->post->create( array( 'post_status' => 'private', 'post_content' => '[bs_lead]p[/bs_lead]' ) );
		$page      = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[bs_well]page[/bs_well]' ) );
		$trashed   = self::factory()->post->create( array( 'post_status' => 'trash', 'post_content' => '[bs_well]t[/bs_well]' ) );
		$plain     = self::factory()->post->create( array( 'post_status' => 'publish', 'post_content' => 'Nothing here' ) );
		wp_save_post_revision( $published );

		$results = $this->run_to_completion();

		$this->assertNotEmpty( $results['finished'] );
		$this->assertSame( 4, $results['components']['bs_well']['occurrences'] );
		$this->assertSame( 3, $results['components']['bs_well']['posts'] );
		$this->assertSame( 2, $results['components']['bs_well']['published_posts'] );
		$this->assertSame( 1, $results['components']['bs_button']['posts'] );
		$this->assertSame( 0, $results['components']['bs_button']['published_posts'] );

		$ids = wp_list_pluck( $results['posts'], 'id' );
		$this->assertContains( $published, $ids );
		$this->assertContains( $draft, $ids );
		$this->assertContains( $private, $ids );
		$this->assertContains( $page, $ids );
		$this->assertContains( $trashed, $ids );
		$this->assertNotContains( $plain, $ids );
		$this->assertSame( 5, $results['totals']['posts_affected'] );
		$this->assertSame( 2, $results['totals']['published_affected'] );
		$this->assertGreaterThanOrEqual( 1, $results['revisions'] );
	}

	public function test_scan_is_resumable_in_small_batches() {
		for ( $i = 0; $i < 5; $i++ ) {
			self::factory()->post->create( array( 'post_content' => '[bs_lead]' . $i . '[/bs_lead]' ) );
		}
		BS_Scanner::start();
		$state = BS_Scanner::run_step( 2 );
		$this->assertEmpty( $state['finished'] );
		$this->assertSame( 2, $state['posts_scanned'] );
		$state = BS_Scanner::run_step( 2 );
		$this->assertSame( 4, $state['posts_scanned'] );
		$state = BS_Scanner::run_step( 2 );
		$this->assertNotEmpty( $state['finished'] );
		$this->assertSame( 5, $state['posts_scanned'] );

		// Stepping again after completion changes nothing.
		$again = BS_Scanner::run_step( 2 );
		$this->assertSame( $state['posts_scanned'], $again['posts_scanned'] );
		$this->assertSame( 5, BS_Scanner::results()['components']['bs_lead']['posts'] );
	}

	public function test_flags_and_edit_links_are_recorded_per_post() {
		$id = self::factory()->post->create( array( 'post_content' => '[bs_row class="row fancy"][bs_col class="col-sm-6"][bs_well]x[/bs_well][/bs_col][/bs_row] [bs_oops]' ) );

		$results = $this->run_to_completion();
		$row     = null;
		foreach ( $results['posts'] as $p ) {
			if ( $p['id'] === $id ) {
				$row = $p;
			}
		}
		$this->assertNotNull( $row );
		$this->assertContains( 'nesting', $row['flags'] );
		$this->assertContains( 'custom_class', $row['flags'] );
		$this->assertContains( 'unknown_tag', $row['flags'] );
		$this->assertSame( 1, $results['flags']['nesting'] );
		$this->assertSame( 1, $results['flags']['unknown_tag'] );
	}

	public function test_other_surfaces_are_scanned_and_named_without_values() {
		update_option( 'widget_text', array( 2 => array( 'title' => 'W', 'text' => 'see [bs_button value="w"]' ), '_multiwidget' => 1 ) );
		update_option( 'bs_test_builder_option', 'x [bs_well]opt[/bs_well]' );
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, 'bs_test_builder_meta', '[bs_lead]meta[/bs_lead]' );
		$term = wp_insert_term( 'Scan term', 'category', array( 'description' => 'desc [bs_label]t[/bs_label]' ) );

		$results = $this->run_to_completion();
		$s       = $results['surfaces'];

		$this->assertSame( 1, $s['widgets']['count'] );
		$this->assertSame( 'text', $s['widgets']['items'][0]['type'] );
		$this->assertContains( 'bs_test_builder_option', wp_list_pluck( $s['options']['items'], 'name' ) );
		$this->assertNotContains( 'widget_text', wp_list_pluck( $s['options']['items'], 'name' ), 'widgets are reported as widgets, not raw options' );
		$this->assertContains( 'bs_test_builder_meta', wp_list_pluck( $s['postmeta']['items'], 'key' ) );
		$this->assertContains( $term['term_id'], wp_list_pluck( $s['terms']['items'], 'term_id' ) );
		$this->assertArrayHasKey( 'theme_files', $s );
		$this->assertNotEmpty( $results['not_scanned'], 'the report must say what it could not inspect' );
		// Values never leave the database through the report.
		$this->assertStringNotContainsString( '[bs_', wp_json_encode( $s ) );
	}

	public function test_share_payload_contains_only_aggregate_counts_and_versions() {
		self::factory()->post->create( array( 'post_title' => 'Secret launch', 'post_content' => '[bs_well]x[/bs_well]' ) );
		update_option( 'blogname', 'Identifiable Site Name' );
		$this->run_to_completion();

		$payload = BS_Scanner::share_payload();
		$json    = wp_json_encode( $payload );

		$this->assertSame( 1, $payload['schema'] );
		$this->assertSame( array( 'occurrences' => 1, 'posts' => 1, 'published_posts' => 1 ), $payload['components']['bs_well'] );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+$/', $payload['environment']['wordpress'] );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+$/', $payload['environment']['php'] );
		foreach ( array( 'Secret launch', 'Identifiable', 'http', '@', home_url(), get_option( 'admin_email' ) ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $json );
		}
		$this->assertSame( array( 'schema', 'generated', 'plugin_version', 'environment', 'settings', 'totals', 'components', 'flags', 'surfaces' ), array_keys( $payload ) );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}$/', $payload['generated'], 'month granularity only' );
	}

	public function test_posts_with_only_unrecognised_tags_are_listed_but_not_counted_as_dependent() {
		$typo = self::factory()->post->create( array( 'post_status' => 'publish', 'post_content' => 'Only [bs_oops] here' ) );
		self::factory()->post->create( array( 'post_status' => 'publish', 'post_content' => '[bs_well]real[/bs_well]' ) );

		$results = $this->run_to_completion();

		$this->assertSame( 1, $results['totals']['posts_affected'] );
		$this->assertSame( 1, $results['totals']['published_affected'] );
		$this->assertContains( $typo, wp_list_pluck( $results['posts'], 'id' ) );
		$this->assertSame( 1, $results['flags']['unknown_tag'] );
		$this->assertSame( 1, BS_Scanner::share_payload()['totals']['posts_affected'] );
	}

	public function test_uninstall_removes_the_scan_options() {
		$this->run_to_completion();
		$this->assertNotNull( BS_Scanner::results() );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'bootstrap-shortcodes/bootstrap-shortcodes.php' );
		}
		include dirname( dirname( __DIR__ ) ) . '/uninstall.php';

		$this->assertNull( BS_Scanner::results() );
		$this->assertNull( BS_Scanner::state() );
		$this->assertFalse( get_option( 'bs_options' ) );
	}
}
