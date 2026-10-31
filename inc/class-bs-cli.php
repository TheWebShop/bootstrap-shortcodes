<?php
/**
 * WP-CLI: `wp bs-shortcodes scan`
 *
 * The same scan as Tools > Bootstrap Shortcodes, for large sites, scripts and
 * agencies with many installs. Reads content; changes nothing.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class BS_CLI_Command extends WP_CLI_Command {

	/**
	 * Scan the site for content that depends on the plugin's shortcodes.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table (default), json (the full local report), or share (the aggregate
	 *   summary with no identifying fields).
	 *
	 * [--batch=<n>]
	 * : Posts per batch. Default 200.
	 *
	 * [--posts]
	 * : With table format, also list the affected posts (the report keeps the first 2000).
	 *
	 * ## EXAMPLES
	 *
	 *     wp bs-shortcodes scan
	 *     wp bs-shortcodes scan --format=json > report.json
	 *     wp bs-shortcodes scan --posts
	 *
	 * @when after_wp_load
	 */
	public function scan( $args, $assoc ) {
		$format = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
		$batch  = isset( $assoc['batch'] ) ? (int) $assoc['batch'] : 200;

		$state = BS_Scanner::start();
		$bar   = 'table' === $format ? \WP_CLI\Utils\make_progress_bar( 'Scanning', max( 1, (int) $state['total'] ) ) : null;
		$done  = 0;
		do {
			$state = BS_Scanner::run_step( $batch );
			if ( $bar ) {
				$bar->tick( (int) $state['posts_scanned'] - $done );
				$done = (int) $state['posts_scanned'];
			}
		} while ( empty( $state['finished'] ) );
		if ( $bar ) {
			$bar->finish();
		}

		$results = BS_Scanner::results();

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $results, JSON_PRETTY_PRINT ) );
			return;
		}
		if ( 'share' === $format ) {
			WP_CLI::line( wp_json_encode( BS_Scanner::share_payload(), JSON_PRETTY_PRINT ) );
			return;
		}

		$t = $results['totals'];
		WP_CLI::log( sprintf( '%d shortcodes in %d posts (%d published); %d revisions also contain them.', $t['occurrences'], $t['posts_affected'], $t['published_affected'], $results['revisions'] ) );

		$rows = array();
		foreach ( $results['components'] as $tag => $c ) {
			$rows[] = array( 'shortcode' => $tag, 'occurrences' => $c['occurrences'], 'posts' => $c['posts'], 'published_posts' => $c['published_posts'] );
		}
		if ( $rows ) {
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'shortcode', 'occurrences', 'posts', 'published_posts' ) );
		}

		if ( $results['flags'] ) {
			$labels = BS_Admin_Tools::flag_labels();
			$parts  = array();
			foreach ( $results['flags'] as $flag => $n ) {
				$parts[] = sprintf( '%s: %d', isset( $labels[ $flag ] ) ? $labels[ $flag ] : $flag, $n );
			}
			WP_CLI::log( 'Complications: ' . implode( ', ', $parts ) );
		}

		$s = $results['surfaces'];
		WP_CLI::log( sprintf( 'Other surfaces: widgets %d, customizer %d, options %d, custom fields %d, terms %d, theme files %d.', $s['widgets']['count'], $s['theme_mods']['count'], $s['options']['count'], $s['postmeta']['count'], $s['terms']['count'], $s['theme_files']['count'] ) );
		WP_CLI::log( 'Not scanned: ' . count( $results['not_scanned'] ) . ' surfaces (see --format=json).' );

		if ( ! empty( $assoc['posts'] ) && $results['posts'] ) {
			$post_rows = array();
			foreach ( $results['posts'] as $p ) {
				$post_rows[] = array(
					'id'         => $p['id'],
					'type'       => $p['type'],
					'status'     => $p['status'],
					'title'      => $p['title'],
					'components' => implode( ' ', array_map( function ( $tag, $n ) { return $tag . 'x' . $n; }, array_keys( $p['counts'] ), $p['counts'] ) ),
					'flags'      => implode( ' ', array_merge( $p['flags'], $p['unknown_tags'] ) ),
				);
			}
			\WP_CLI\Utils\format_items( 'table', $post_rows, array( 'id', 'type', 'status', 'title', 'components', 'flags' ) );
			if ( $results['posts_overflow'] ) {
				WP_CLI::warning( sprintf( '%d more affected posts not listed (report row cap).', $results['posts_overflow'] ) );
			}
		}
	}
}

WP_CLI::add_command( 'bs-shortcodes', 'BS_CLI_Command' );
