<?php
/**
 * Builds and installs the "Bootstrap 3 Legacy Assets" companion plugin from
 * this plugin's own files. Install only happens from an explicit, nonced,
 * consented admin action; a zip download is always offered as the manual path.
 */
class BS_Companion {

	const SLUG = 'bootstrap-legacy-assets';
	const FILE = 'bootstrap-legacy-assets/bootstrap-legacy-assets.php';

	public static function source_dir() {
		return dirname( BS_SHORTCODES_FILE ) . '/companion/' . self::SLUG;
	}

	/** Files that go into the zip: [path inside zip => absolute source]. */
	public static function manifest() {
		$plugin = dirname( BS_SHORTCODES_FILE );
		$files  = array(
			self::SLUG . '/bootstrap-legacy-assets.php' => self::source_dir() . '/bootstrap-legacy-assets.php',
			self::SLUG . '/readme.txt'                  => self::source_dir() . '/readme.txt',
			self::SLUG . '/css/bootstrap.css'           => $plugin . '/css/bootstrap.css',
			// Loaded with bootstrap.css by the original: legacy icon-* sprites and layout fixups.
			self::SLUG . '/css/shortcodes.css'          => $plugin . '/css/shortcodes.css',
			self::SLUG . '/images/glyphicons-halflings.png' => $plugin . '/images/glyphicons-halflings.png',
			self::SLUG . '/js/bootstrap.js'             => $plugin . '/js/bootstrap.js',
			self::SLUG . '/js/init.js'                  => $plugin . '/js/init.js',
		);
		foreach ( glob( $plugin . '/fonts/glyphicons-halflings-regular.*' ) as $font ) {
			$files[ self::SLUG . '/fonts/' . basename( $font ) ] = $font;
		}
		return $files;
	}

	/**
	 * Write the zip into the uploads dir (private name) and return its path.
	 */
	public static function build_zip() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'bs_zip', $upload['error'] );
		}
		$path = trailingslashit( $upload['basedir'] ) . self::SLUG . '-' . wp_generate_password( 8, false ) . '.zip';
		$files = self::manifest();
		foreach ( $files as $src ) {
			if ( ! is_readable( $src ) ) {
				return new WP_Error( 'bs_zip', sprintf( 'Missing source file %s', basename( $src ) ) );
			}
		}
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				return new WP_Error( 'bs_zip', 'Could not create the zip file.' );
			}
			foreach ( $files as $inside => $src ) {
				$zip->addFile( $src, $inside );
			}
			$zip->close();
		} else {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
			$zip = new PclZip( $path );
			foreach ( $files as $inside => $src ) {
				$res = $zip->add( $src, PCLZIP_OPT_REMOVE_ALL_PATH, PCLZIP_OPT_ADD_PATH, dirname( $inside ) );
				if ( 0 === $res ) {
					return new WP_Error( 'bs_zip', $zip->errorInfo( true ) );
				}
			}
		}
		return $path;
	}

	public static function is_installed() {
		return file_exists( WP_PLUGIN_DIR . '/' . self::FILE );
	}

	public static function is_active() {
		return function_exists( 'is_plugin_active' ) ? is_plugin_active( self::FILE ) : in_array( self::FILE, (array) get_option( 'active_plugins', array() ), true );
	}

	/**
	 * Install (if needed) and activate through core's upgrader. Only works when
	 * WordPress can write to the plugins directory itself; otherwise the caller
	 * should offer the zip for manual upload.
	 */
	public static function install_and_activate() {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'bs_forbidden', __( 'You cannot install plugins on this site.', 'bsshortcodes' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( ! self::is_installed() ) {
			if ( 'direct' !== get_filesystem_method() || ! wp_is_writable( WP_PLUGIN_DIR ) ) {
				return new WP_Error( 'bs_filesystem', __( 'WordPress cannot write to the plugins directory on this host. Download the zip and install it through Plugins > Add New > Upload Plugin.', 'bsshortcodes' ) );
			}
			$zip = self::build_zip();
			if ( is_wp_error( $zip ) ) {
				return $zip;
			}
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			$skin     = new WP_Ajax_Upgrader_Skin();
			$upgrader = new Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $zip );
			@unlink( $zip );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! $result ) {
				$errors = $skin->get_errors();
				return new WP_Error( 'bs_install', $errors && $errors->has_errors() ? $errors->get_error_message() : __( 'Install failed.', 'bsshortcodes' ) );
			}
			if ( ! self::is_installed() ) {
				return new WP_Error( 'bs_install', __( 'The companion did not land in the plugins directory.', 'bsshortcodes' ) );
			}
		}
		// Mirror the original plugin's asset choices so the site looks the same.
		$o = get_option( 'bs_options' );
		$o = is_array( $o ) ? $o : array();
		update_option( 'bs_legacy_assets', array_filter( array(
			'css'      => empty( $o['chk_default_options_css'] ) ? '' : '1',
			'js'       => empty( $o['chk_default_options_js'] ) ? '' : '1',
			'tooltips' => '1', // the original always loads init.js, whatever the JS setting
		) ) );
		$activated = activate_plugin( self::FILE );
		if ( is_wp_error( $activated ) ) {
			return $activated;
		}
		return true;
	}
}
