<?php
/*
Plugin Name: Bootstrap 3 Legacy Assets
Plugin URI: https://github.com/TheWebShop/bootstrap-shortcodes/blob/master/docs/migration.md
Description: Keeps loading the Bootstrap 3.4.1 CSS, JavaScript and Glyphicons font that the Bootstrap Shortcodes plugin used to load, for sites that still have markup relying on them. No shortcodes, no settings beyond three switches. Bootstrap 3 is end-of-life: this is a compatibility bridge, not a supported framework.
Version: 1.0.0
Author: Kevin Attfield
Requires at least: 5.0
Requires PHP: 7.4
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'BS_Legacy_Assets' ) ) :

class BS_Legacy_Assets {

	const OPTION  = 'bs_legacy_assets';
	const VERSION = '1.0.0';

	public static function defaults() {
		return array( 'css' => '1', 'js' => '1', 'tooltips' => '1' );
	}

	public static function options() {
		$o = get_option( self::OPTION );
		return is_array( $o ) ? $o : self::defaults();
	}

	public function __construct() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	public static function activate() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	/** True when Bootstrap Shortcodes is active and still loading its own copy. */
	public static function original_plugin_loads_assets() {
		if ( ! class_exists( 'BootstrapShortcodes' ) ) {
			return false;
		}
		$o = get_option( 'bs_options' );
		return is_array( $o ) && ( ! empty( $o['chk_default_options_css'] ) || ! empty( $o['chk_default_options_js'] ) );
	}

	/** A theme or another plugin already registered a Bootstrap file of this kind. */
	public static function other_bootstrap( $kind ) {
		$deps = 'css' === $kind ? wp_styles() : wp_scripts();
		foreach ( $deps->registered as $handle => $dep ) {
			if ( 0 === strpos( $handle, 'bs-legacy' ) || empty( $dep->src ) ) {
				continue;
			}
			if ( preg_match( '#bootstrap(\.bundle)?(\.min)?\.(css|js)(\?|$)#i', $dep->src ) ) {
				return $handle;
			}
		}
		return '';
	}

	public function enqueue() {
		if ( self::original_plugin_loads_assets() ) {
			return; // avoid double loading while the original is still active
		}
		$o    = self::options();
		$base = plugins_url( '', __FILE__ );
		if ( ! empty( $o['css'] ) && ! self::other_bootstrap( 'css' ) ) {
			wp_enqueue_style( 'bs-legacy-bootstrap', $base . '/css/bootstrap.css', array(), '3.4.1' );
		}
		if ( ! empty( $o['js'] ) && ! self::other_bootstrap( 'js' ) ) {
			wp_enqueue_script( 'bs-legacy-bootstrap', $base . '/js/bootstrap.js', array( 'jquery' ), '3.4.1' );
		}
		if ( ! empty( $o['tooltips'] ) ) {
			wp_enqueue_script( 'bs-legacy-init', $base . '/js/init.js', array( 'jquery' ), self::VERSION );
		}
	}

	public function register_settings() {
		register_setting( 'bs_legacy_assets', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $input ) {
		$clean = array();
		foreach ( array_keys( self::defaults() ) as $key ) {
			if ( is_array( $input ) && ! empty( $input[ $key ] ) ) {
				$clean[ $key ] = '1';
			}
		}
		return $clean;
	}

	public function menu() {
		add_options_page( 'Legacy Bootstrap', 'Legacy Bootstrap', 'manage_options', 'bs-legacy-assets', array( $this, 'page' ) );
	}

	public function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( self::original_plugin_loads_assets() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Bootstrap 3 Legacy Assets is waiting: the Bootstrap Shortcodes plugin is still loading its own Bootstrap files. Deactivate Bootstrap Shortcodes (or turn off its two asset settings) and this plugin takes over.', 'bs-legacy-assets' ) . '</p></div>';
		}
	}

	public function page() {
		$o = self::options();
		?>
		<div class="wrap">
			<h1>Bootstrap 3 Legacy Assets</h1>
			<p><?php esc_html_e( 'This plugin exists so a site can remove Bootstrap Shortcodes without losing the Bootstrap 3 styling its remaining markup depends on. It loads the same files that plugin loaded (Bootstrap 3.4.1 CSS and JavaScript, the Glyphicons font, and a small tooltip initialiser). It registers no shortcodes.', 'bs-legacy-assets' ); ?></p>
			<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Bootstrap 3 is end-of-life.', 'bs-legacy-assets' ); ?></strong> <?php esc_html_e( 'Version 3.4.1 (2019) is the last release; the Bootstrap project publishes no fixes for it. Keeping it is a deliberate compatibility choice. Treat this plugin as temporary: migrate the remaining markup to your theme or to blocks, then deactivate it.', 'bs-legacy-assets' ); ?></p></div>
			<form method="post" action="options.php">
				<?php settings_fields( 'bs_legacy_assets' ); ?>
				<table class="form-table">
					<tr><th scope="row"><?php esc_html_e( 'Bootstrap CSS', 'bs-legacy-assets' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[css]" value="1" <?php checked( ! empty( $o['css'] ) ); ?> /> <?php esc_html_e( 'Load bootstrap.css (grid, buttons, wells, labels, alerts, panels, Glyphicons)', 'bs-legacy-assets' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Bootstrap JavaScript', 'bs-legacy-assets' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[js]" value="1" <?php checked( ! empty( $o['js'] ) ); ?> /> <?php esc_html_e( 'Load bootstrap.js (tabs, collapse, dropdowns, modals, tooltips; needs jQuery)', 'bs-legacy-assets' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Tooltips', 'bs-legacy-assets' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[tooltips]" value="1" <?php checked( ! empty( $o['tooltips'] ) ); ?> /> <?php esc_html_e( 'Initialise [data-toggle="tooltip"] elements, as Bootstrap Shortcodes did', 'bs-legacy-assets' ); ?></label></td></tr>
				</table>
				<p class="description"><?php esc_html_e( 'If your theme registers its own Bootstrap CSS or JavaScript, the matching file here is skipped automatically to avoid loading it twice.', 'bs-legacy-assets' ); ?></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

endif;

new BS_Legacy_Assets();
