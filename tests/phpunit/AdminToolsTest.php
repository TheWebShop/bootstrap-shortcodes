<?php
/**
 * Tools page handlers: capability and nonce checks, and the scan step.
 */
class AdminToolsTest extends WP_UnitTestCase {

	private $tools;

	public function set_up() {
		parent::set_up();
		$this->tools = new BS_Admin_Tools();
		BS_Scanner::reset();
		$_POST = array();
		$_GET  = array();
		$_REQUEST = array();
	}

	public function tear_down() {
		BS_Scanner::reset();
		$_POST = array();
		$_GET  = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	public function test_page_is_registered_under_tools_for_admins() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'tools' );
		do_action( 'admin_menu' );
		global $submenu;
		$slugs = wp_list_pluck( $submenu['tools.php'], 2 );
		$this->assertContains( BS_Admin_Tools::PAGE, $slugs );
	}

	public function test_ajax_step_refuses_editors() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_POST['_ajax_nonce'] = wp_create_nonce( BS_Admin_Tools::ACTION );
		$_REQUEST = $_POST;

		list( $status, $data ) = $this->tools->ajax_step_response();

		$this->assertSame( 403, $status );
		$this->assertSame( 'forbidden', $data['message'] );
		$this->assertNull( BS_Scanner::state(), 'no scan may be started by a non-admin' );
	}

	public function test_ajax_step_refuses_a_bad_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['_ajax_nonce'] = 'nope';
		$_REQUEST = $_POST;

		list( $status, $data ) = $this->tools->ajax_step_response();

		$this->assertSame( 403, $status );
		$this->assertSame( 'bad nonce', $data['message'] );
		$this->assertNull( BS_Scanner::state() );
	}

	public function test_ajax_step_runs_the_scan_for_admins() {
		self::factory()->post->create( array( 'post_content' => '[bs_well]x[/bs_well]' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['_ajax_nonce'] = wp_create_nonce( BS_Admin_Tools::ACTION );
		$_POST['start']       = '1';
		$_REQUEST = $_POST;

		list( $status, $data ) = $this->tools->ajax_step_response();

		$this->assertSame( 200, $status );
		$this->assertTrue( $data['finished'] );
		$this->assertSame( 1, $data['posts_scanned'] );
		$this->assertSame( 1, BS_Scanner::results()['components']['bs_well']['posts'] );
		$this->assertSame( 1, BS_Admin_Tools::export_data( 'share' )['components']['bs_well']['posts'] );
	}

	public function test_export_requires_a_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['kind'] = 'share';

		$this->expectException( 'WPDieException' );
		ob_start();
		try {
			$this->tools->handle_export();
		} finally {
			ob_end_clean();
		}
	}
}
