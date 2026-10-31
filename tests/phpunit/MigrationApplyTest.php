<?php
/**
 * Writing migrations: originals are kept, stale previews are refused, rollback
 * restores byte for byte, permissions are enforced, batches are bounded.
 */
class MigrationApplyTest extends WP_UnitTestCase {

	private $admin;
	private $opts = array( 'mode' => 'html', 'style' => 'inline' );

	public function set_up() {
		parent::set_up();
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin );
	}

	public function test_apply_keeps_the_original_and_rewrites_the_post() {
		$original = "Intro\n\n[bs_well]Hello[/bs_well]\n\nOutro";
		$id       = self::factory()->post->create( array( 'post_content' => $original ) );

		$r = BS_Migration::apply( $id, $this->opts );

		$this->assertNotWPError( $r );
		$this->assertSame( 1, $r['converted'] );
		$post = get_post( $id );
		$this->assertStringContainsString( '<div class="well" style="', $post->post_content );
		$this->assertStringNotContainsString( '[bs_well]', $post->post_content );
		$backup = get_post_meta( $id, BS_Migration::META_ORIGINAL, true );
		$this->assertSame( $original, $backup['content'] );
		$this->assertTrue( BS_Migration::is_migrated( $id ) );
		$this->assertContains( $id, BS_Migration::migrated_posts() );
		$this->assertNotEmpty( wp_get_post_revisions( $id ), 'wp_update_post should still record a revision' );
	}

	public function test_rollback_restores_the_original_exactly() {
		$original = "<p>Keep</p>\n[bs_label type=\"info\"]x[/bs_label]\n";
		$id       = self::factory()->post->create( array( 'post_content' => $original ) );
		BS_Migration::apply( $id, $this->opts );
		$this->assertNotSame( $original, get_post( $id )->post_content );

		$this->assertTrue( BS_Migration::rollback( $id ) );

		$this->assertSame( $original, get_post( $id )->post_content );
		$this->assertFalse( BS_Migration::is_migrated( $id ) );
		$this->assertEmpty( get_post_meta( $id, BS_Migration::META_RESULT, true ) );
		$this->assertWPError( BS_Migration::rollback( $id ) );
	}

	public function test_a_second_migration_does_not_overwrite_the_first_backup() {
		$original = '[bs_well]a[/bs_well] [bs_bogus]';
		$id       = self::factory()->post->create( array( 'post_content' => $original ) );
		BS_Migration::apply( $id, $this->opts );
		wp_update_post( array( 'ID' => $id, 'post_content' => get_post( $id )->post_content . ' [bs_lead]later[/bs_lead]' ) );

		BS_Migration::apply( $id, $this->opts );

		$this->assertSame( $original, get_post_meta( $id, BS_Migration::META_ORIGINAL, true )['content'] );
		$this->assertTrue( BS_Migration::rollback( $id ) );
		$this->assertSame( $original, get_post( $id )->post_content );
	}

	public function test_stale_preview_is_refused() {
		$id      = self::factory()->post->create( array( 'post_content' => '[bs_well]a[/bs_well]' ) );
		$preview = BS_Migration::preview( $id, $this->opts );
		// Someone edits the post after the preview was shown (wp_update_post always
		// stamps "now", which within one second equals the preview, so write the
		// edit straight to the table the way a later save would look).
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_content' => '[bs_well]b[/bs_well]', 'post_modified_gmt' => '2030-01-01 00:00:00', 'post_modified' => '2030-01-01 00:00:00' ), array( 'ID' => $id ) );
		clean_post_cache( $id );

		$r = BS_Migration::apply( $id, $this->opts, $preview['modified_gmt'] );

		$this->assertWPError( $r );
		$this->assertSame( 'bs_stale', $r->get_error_code() );
		$this->assertSame( '[bs_well]b[/bs_well]', get_post( $id )->post_content );
		$this->assertFalse( BS_Migration::is_migrated( $id ) );
	}

	public function test_nothing_to_convert_writes_nothing() {
		$id = self::factory()->post->create( array( 'post_content' => 'plain' ) );
		$r  = BS_Migration::apply( $id, $this->opts );
		$this->assertWPError( $r );
		$this->assertSame( 'bs_nothing', $r->get_error_code() );
		$this->assertFalse( BS_Migration::is_migrated( $id ) );

		$id2 = self::factory()->post->create( array( 'post_content' => '[bs_well]unclosed' ) );
		$r2  = BS_Migration::apply( $id2, $this->opts );
		$this->assertWPError( $r2 );
		$this->assertSame( '[bs_well]unclosed', get_post( $id2 )->post_content );
	}

	public function test_users_without_edit_rights_are_refused() {
		$id = self::factory()->post->create( array( 'post_content' => '[bs_well]a[/bs_well]' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 'bs_forbidden', BS_Migration::preview( $id, $this->opts )->get_error_code() );
		$this->assertSame( 'bs_forbidden', BS_Migration::apply( $id, $this->opts )->get_error_code() );
		$this->assertSame( 'bs_forbidden', BS_Migration::rollback( $id )->get_error_code() );
		$this->assertSame( '[bs_well]a[/bs_well]', get_post( $id )->post_content );
	}

	public function test_batch_is_bounded_reports_per_post_and_is_safe_to_retry() {
		$ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = self::factory()->post->create( array( 'post_content' => "[bs_lead]{$i}[/bs_lead]" ) );
		}
		$plain = self::factory()->post->create( array( 'post_content' => 'plain' ) );
		$ids[] = $plain;
		$ids[] = 999999;

		$out = BS_Migration::batch( $ids, $this->opts );

		$this->assertCount( 5, $out );
		$this->assertTrue( $out[ $ids[0] ]['ok'] );
		$this->assertFalse( $out[ $plain ]['ok'] );
		$this->assertSame( 'bs_nothing', $out[ $plain ]['code'] );
		$this->assertSame( 'bs_not_found', $out[999999]['code'] );

		// Retry: already-migrated posts have nothing left to convert and are untouched.
		$again = BS_Migration::batch( $ids, $this->opts );
		$this->assertSame( 'bs_nothing', $again[ $ids[0] ]['code'] );
		$this->assertSame( "[bs_lead]0[/bs_lead]", get_post_meta( $ids[0], BS_Migration::META_ORIGINAL, true )['content'] );

		$this->assertCount( BS_Migration::MAX_BATCH, BS_Migration::batch( range( 1, 100 ), $this->opts ) );
	}

	public function test_migrated_content_renders_without_the_shortcode_handlers() {
		$id = self::factory()->post->create( array( 'post_content' => "[bs_collapse id=\"g\"][bs_citem id=\"a\" parent=\"g\" title=\"Q\"]A[/bs_citem][/bs_collapse]\n\n[bs_icon name=\"glyphicon glyphicon-ok\"] done" ) );
		BS_Migration::apply( $id, array( 'mode' => 'blocks', 'style' => 'inline' ) );

		// Simulate the plugin being gone: no bs_ shortcodes registered.
		global $shortcode_tags;
		$saved = $shortcode_tags;
		foreach ( BS_Scanner::tags() as $tag ) {
			remove_shortcode( $tag );
		}
		$rendered = apply_filters( 'the_content', get_post( $id )->post_content );
		$shortcode_tags = $saved;

		$this->assertMatchesRegularExpression( '#<details class="wp-block-details[^"]*" name="g" id="a"><summary>Q</summary>#', $rendered );
		$this->assertStringContainsString( '<svg class="bs-icon bs-icon-ok"', $rendered );
		$this->assertStringNotContainsString( '[bs_', $rendered );
	}
}
