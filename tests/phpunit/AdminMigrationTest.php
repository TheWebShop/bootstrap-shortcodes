<?php
/**
 * Migration handlers: capability and nonce gates on the batch endpoint.
 */
class AdminMigrationTest extends WP_UnitTestCase {

	private $admin;

	public function set_up() {
		parent::set_up();
		$this->admin = new BS_Admin_Migration();
		$_POST = array();
		$_REQUEST = array();
	}

	public function tear_down() {
		$_POST = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	public function test_batch_refuses_editors_and_bad_nonces() {
		$id = self::factory()->post->create( array( 'post_content' => '[bs_well]a[/bs_well]' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_POST = array( '_ajax_nonce' => wp_create_nonce( BS_Admin_Migration::ACTION_BATCH ), 'post_ids' => array( $id ) );
		$_REQUEST = $_POST;
		list( $status ) = $this->admin->batch_response();
		$this->assertSame( 403, $status );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['_ajax_nonce'] = 'bad';
		$_REQUEST = $_POST;
		list( $status ) = $this->admin->batch_response();
		$this->assertSame( 403, $status );
		$this->assertSame( '[bs_well]a[/bs_well]', get_post( $id )->post_content );
	}

	public function test_batch_converts_for_admins_and_reports_per_post() {
		$id = self::factory()->post->create( array( 'post_content' => '[bs_well]a[/bs_well]' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array( '_ajax_nonce' => wp_create_nonce( BS_Admin_Migration::ACTION_BATCH ), 'post_ids' => array( $id, 424242 ), 'mode' => 'html', 'style' => 'classes' );
		$_REQUEST = $_POST;

		list( $status, $data ) = $this->admin->batch_response();

		$this->assertSame( 200, $status );
		$this->assertTrue( $data['results'][ $id ]['ok'] );
		$this->assertFalse( $data['results'][424242]['ok'] );
		$this->assertSame( '<div class="well">a</div>', get_post( $id )->post_content );
		$this->assertContains( 'bootstrap-css', $data['results'][ $id ]['dependencies'] );
	}
}
