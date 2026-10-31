<?php
/**
 * Dependency scanner: what content depends on this plugin's shortcodes?
 *
 * Text in, structured findings out. It never renders or rewrites anything, and
 * it uses WordPress's own shortcode regex so it finds exactly what do_shortcode()
 * would act on (and reports escaped [[bs_x]] separately).
 */
class BS_Scanner {

	/**
	 * Attributes each tag understands (from inc/bs_*.php). Anything else is "unknown".
	 */
	public static $known_attrs = array(
		'bs_row'          => array( 'class' ),
		'bs_col'          => array( 'class' ),
		'bs_tabs'         => array(),
		'bs_thead'        => array(),
		'bs_tab'          => array( 'href', 'title', 'class', 'type' ), // type="tab" is what the editor emits; the handler ignores it
		'bs_dropdown'     => array( 'title', 'id', 'class' ),
		'bs_tcontents'    => array(),
		'bs_tcontent'     => array( 'id', 'class' ),
		'bs_collapse'     => array( 'id' ),
		'bs_citem'        => array( 'id', 'title', 'parent', 'open' ),
		'bs_notification' => array( 'type', 'dismissible' ),
		'bs_well'         => array( 'size' ),
		'bs_button'       => array( 'size', 'type', 'value', 'href', 'target' ),
		'bs_label'        => array( 'type' ),
		'bs_icon'         => array( 'name' ),
		'bs_lead'         => array(),
		'bs_tooltip'      => array( 'placement', 'trigger', 'href' ),
	);

	/**
	 * Tags that are *meant* to contain other bs_ tags, and which children they expect.
	 * Nesting inside these is structure, not a complication.
	 */
	public static $structural_children = array(
		'bs_row'       => array( 'bs_col' ),
		'bs_tabs'      => array( 'bs_thead', 'bs_tcontents' ),
		'bs_thead'     => array( 'bs_tab', 'bs_dropdown' ),
		'bs_dropdown'  => array( 'bs_tab' ),
		'bs_tcontents' => array( 'bs_tcontent' ),
		'bs_collapse'  => array( 'bs_citem' ),
	);

	/** Tags whose id attribute wires behaviour together; empty means broken JS. */
	public static $id_required = array( 'bs_collapse', 'bs_citem', 'bs_tcontent' );

	/** Self-closing tags (content is ignored by the handler). */
	public static $self_closing = array( 'bs_button', 'bs_icon', 'bs_tab' );

	public static function tags() {
		return array_keys( self::$known_attrs );
	}

	/**
	 * Scan one blob of text.
	 *
	 * @return array {
	 *   occurrences: list of [tag, attrs, content, offset, depth, unknown_attrs, custom_class, id]
	 *   counts: tag => occurrences (all depths)
	 *   flags: subset of nesting|unknown_attribute|custom_class|malformed|unknown_tag|duplicate_id|missing_id
	 *   unbalanced: tags whose open/close counts differ
	 *   unknown_tags: bs_ tags that are not ours (typos, other plugins)
	 *   duplicate_ids: ids used more than once
	 *   escaped: number of [[bs_x]] escapes (rendered as literal text, not a dependency)
	 * }
	 */
	public static function scan_text( $text ) {
		$result = array(
			'occurrences'   => array(),
			'counts'        => array(),
			'flags'         => array(),
			'unbalanced'    => array(),
			'unknown_tags'  => array(),
			'duplicate_ids' => array(),
			'escaped'       => 0,
		);
		if ( ! is_string( $text ) || false === strpos( $text, '[bs_' ) ) {
			return $result;
		}

		self::collect( $text, 0, null, $result );

		foreach ( $result['occurrences'] as $o ) {
			$result['counts'][ $o['tag'] ] = isset( $result['counts'][ $o['tag'] ] ) ? $result['counts'][ $o['tag'] ] + 1 : 1;
		}

		// Balance check per enclosing tag, over the raw text (regex matching alone
		// silently drops an unclosed [bs_well] by treating it as self-closing).
		foreach ( self::tags() as $tag ) {
			if ( in_array( $tag, self::$self_closing, true ) ) {
				continue;
			}
			$opens  = preg_match_all( '/\[' . preg_quote( $tag, '/' ) . '(?![\w-])/', $text );
			$closes = preg_match_all( '/\[\/' . preg_quote( $tag, '/' ) . '\]/', $text );
			if ( $opens !== $closes ) {
				$result['unbalanced'][] = $tag;
			}
		}

		// bs_ tags we do not register: typos or another plugin's namespace.
		if ( preg_match_all( '/\[(bs_[\w-]+)/', $text, $m ) ) {
			$result['unknown_tags'] = array_values( array_unique( array_diff( $m[1], self::tags() ) ) );
		}

		$ids = array();
		foreach ( $result['occurrences'] as $o ) {
			if ( '' !== $o['id'] ) {
				$ids[ $o['id'] ] = isset( $ids[ $o['id'] ] ) ? $ids[ $o['id'] ] + 1 : 1;
			}
		}
		$result['duplicate_ids'] = array_keys( array_filter( $ids, function ( $n ) { return $n > 1; } ) );

		$flags = array();
		foreach ( $result['occurrences'] as $o ) {
			if ( $o['foreign_nesting'] ) {
				$flags['nesting'] = true;
			}
			if ( $o['unknown_attrs'] ) {
				$flags['unknown_attribute'] = true;
			}
			if ( $o['custom_class'] ) {
				$flags['custom_class'] = true;
			}
			if ( $o['missing_id'] ) {
				$flags['missing_id'] = true;
			}
		}
		if ( $result['unbalanced'] ) {
			$flags['malformed'] = true;
		}
		if ( $result['unknown_tags'] ) {
			$flags['unknown_tag'] = true;
		}
		if ( $result['duplicate_ids'] ) {
			$flags['duplicate_id'] = true;
		}
		$result['flags'] = array_keys( $flags );

		return $result;
	}

	private static function collect( $text, $depth, $parent, &$result ) {
		if ( ! preg_match_all( '/' . get_shortcode_regex( self::tags() ) . '/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return;
		}
		foreach ( $matches as $m ) {
			// Same escape convention as do_shortcode_tag(): [[tag]] ... [[/tag]]
			if ( '[' === $m[1][0] && ']' === $m[6][0] ) {
				$result['escaped']++;
				continue;
			}
			$tag     = $m[2][0];
			$attrs   = shortcode_parse_atts( $m[3][0] );
			$attrs   = is_array( $attrs ) ? $attrs : array();
			$content = isset( $m[5][0] ) ? $m[5][0] : '';
			$named   = array();
			foreach ( $attrs as $k => $v ) {
				if ( is_string( $k ) ) {
					$named[ $k ] = $v;
				}
			}
			$unknown = array_values( array_diff( array_keys( $named ), self::$known_attrs[ $tag ] ) );
			$id      = isset( $named['id'] ) ? (string) $named['id'] : '';

			$result['occurrences'][] = array(
				'tag'             => $tag,
				'attrs'           => $named,
				'content'         => $content,
				'offset'          => $m[0][1],
				'depth'           => $depth,
				'parent'          => $parent,
				'unknown_attrs'   => $unknown,
				'custom_class'    => self::has_custom_class( $tag, $named ),
				'id'              => $id,
				'missing_id'      => in_array( $tag, self::$id_required, true ) && '' === $id,
				'foreign_nesting' => null !== $parent && ! self::is_structural( $parent, $tag ),
			);

			if ( '' !== $content && false !== strpos( $content, '[bs_' ) ) {
				self::collect( $content, $depth + 1, $tag, $result );
			}
		}
	}

	private static function is_structural( $parent, $child ) {
		return isset( self::$structural_children[ $parent ] ) && in_array( $child, self::$structural_children[ $parent ], true );
	}

	/**
	 * A class value the editor never produces: anything beyond Bootstrap grid /
	 * row classes (row, col-*), or beyond "active" on tabs and panes.
	 */
	private static function has_custom_class( $tag, $attrs ) {
		if ( empty( $attrs['class'] ) ) {
			return false;
		}
		$classes = preg_split( '/\s+/', trim( $attrs['class'] ) );
		foreach ( $classes as $class ) {
			switch ( $tag ) {
				case 'bs_row':
					if ( 'row' !== $class ) {
						return true;
					}
					break;
				case 'bs_col':
					if ( ! preg_match( '/^col-(xs|sm|md|lg)-(offset-|push-|pull-)?\d{1,2}$/', $class ) ) {
						return true;
					}
					break;
				default:
					if ( 'active' !== $class ) {
						return true;
					}
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Site-level scan: state, batches, other surfaces, shareable summary  */
	/* ------------------------------------------------------------------ */

	const STATE_OPTION   = 'bs_scan_state';
	const RESULTS_OPTION = 'bs_scan_results';
	const MAX_POST_ROWS  = 2000;
	const MAX_ITEMS      = 200;

	public static function reset() {
		delete_option( self::STATE_OPTION );
		delete_option( self::RESULTS_OPTION );
	}

	/**
	 * Post types and statuses the scan walks. Everything with content a visitor
	 * or editor could see; revisions are counted separately, auto-drafts skipped.
	 */
	public static function post_types() {
		$types   = get_post_types( array(), 'names' );
		$skip    = array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face' );
		return array_values( array_diff( $types, $skip ) );
	}

	public static function post_statuses() {
		return array_values( array_diff( get_post_stati(), array( 'auto-draft' ) ) );
	}

	public static function start() {
		$state = array(
			'started'       => time(),
			'finished'      => 0,
			'cursor'        => 0,
			'posts_scanned' => 0,
			'total'         => self::count_candidates(),
		);
		update_option( self::STATE_OPTION, $state, false );
		update_option( self::RESULTS_OPTION, self::empty_results(), false );
		return $state;
	}

	public static function state() {
		$state = get_option( self::STATE_OPTION );
		return is_array( $state ) ? $state : null;
	}

	public static function results() {
		$results = get_option( self::RESULTS_OPTION );
		return is_array( $results ) ? $results : null;
	}

	private static function empty_results() {
		return array(
			'started'     => time(),
			'finished'    => 0,
			'components'  => array(),
			'posts'       => array(),
			'posts_overflow' => 0,
			'totals'      => array( 'posts_candidates' => 0, 'posts_affected' => 0, 'published_affected' => 0, 'occurrences' => 0, 'escaped' => 0 ),
			'flags'       => array(),
			'revisions'   => 0,
			'surfaces'    => array(),
			'not_scanned' => array(),
			'environment' => self::environment(),
		);
	}

	private static function like() {
		global $wpdb;
		return '%' . $wpdb->esc_like( '[bs_' ) . '%';
	}

	private static function count_candidates() {
		global $wpdb;
		$types    = self::post_types();
		$statuses = self::post_statuses();
		if ( ! $types || ! $statuses ) {
			return 0;
		}
		$sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') AND post_status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( array( self::like() ), $types, $statuses ) ) );
	}

	/**
	 * Process one bounded batch. Safe to call repeatedly; a no-op once finished.
	 *
	 * @return array the scan state after this step.
	 */
	public static function run_step( $limit = 100 ) {
		global $wpdb;
		$state   = self::state();
		$results = self::results();
		if ( ! $state || ! $results ) {
			$state   = self::start();
			$results = self::results();
		}
		if ( ! empty( $state['finished'] ) ) {
			return $state;
		}
		$limit    = max( 1, min( 1000, (int) $limit ) );
		$types    = self::post_types();
		$statuses = self::post_statuses();
		$rows     = array();
		if ( $types && $statuses ) {
			$sql  = "SELECT ID, post_type, post_status, post_title, post_content FROM {$wpdb->posts} WHERE ID > %d AND post_content LIKE %s AND post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') AND post_status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ') ORDER BY ID ASC LIMIT %d';
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( (int) $state['cursor'], self::like() ), $types, $statuses, array( $limit ) ) ) );
		}

		foreach ( $rows as $row ) {
			$state['cursor'] = (int) $row->ID;
			$state['posts_scanned']++;
			$results['totals']['posts_candidates']++;
			self::record_post( $results, $row );
		}

		if ( count( $rows ) < $limit ) {
			$results['revisions']   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_content LIKE %s", self::like() ) );
			$results['surfaces']    = self::scan_surfaces();
			$results['not_scanned'] = self::not_scanned();
			$results['finished']    = time();
			$state['finished']      = $results['finished'];
		}

		update_option( self::RESULTS_OPTION, $results, false );
		update_option( self::STATE_OPTION, $state, false );
		return $state;
	}

	private static function record_post( &$results, $row ) {
		$scan = self::scan_text( $row->post_content );
		$results['totals']['escaped'] += $scan['escaped'];
		if ( ! $scan['counts'] && ! $scan['unknown_tags'] ) {
			return; // LIKE matched but nothing real: escaped or foreign bs_ text
		}
		$published = ( 'publish' === $row->post_status );
		// Only posts with shortcodes this plugin renders count as dependent; a post
		// holding nothing but an unrecognised bs_ tag is listed (the typo is worth
		// seeing) but changes nothing when the plugin goes.
		if ( $scan['counts'] ) {
			$results['totals']['posts_affected']++;
			if ( $published ) {
				$results['totals']['published_affected']++;
			}
		}
		foreach ( $scan['counts'] as $tag => $n ) {
			if ( ! isset( $results['components'][ $tag ] ) ) {
				$results['components'][ $tag ] = array( 'occurrences' => 0, 'posts' => 0, 'published_posts' => 0 );
			}
			$results['components'][ $tag ]['occurrences'] += $n;
			$results['components'][ $tag ]['posts']++;
			if ( $published ) {
				$results['components'][ $tag ]['published_posts']++;
			}
			$results['totals']['occurrences'] += $n;
		}
		foreach ( $scan['flags'] as $flag ) {
			$results['flags'][ $flag ] = isset( $results['flags'][ $flag ] ) ? $results['flags'][ $flag ] + 1 : 1;
		}
		if ( count( $results['posts'] ) >= self::MAX_POST_ROWS ) {
			$results['posts_overflow']++;
			return;
		}
		$results['posts'][] = array(
			'id'            => (int) $row->ID,
			'title'         => $row->post_title,
			'type'          => $row->post_type,
			'status'        => $row->post_status,
			'counts'        => $scan['counts'],
			'flags'         => $scan['flags'],
			'unknown_tags'  => $scan['unknown_tags'],
			'duplicate_ids' => $scan['duplicate_ids'],
			'unbalanced'    => $scan['unbalanced'],
		);
	}

	/**
	 * Content that lives outside post_content. Names and counts only; values
	 * are never copied into the results.
	 */
	public static function scan_surfaces() {
		global $wpdb;
		$like     = self::like();
		$surfaces = array();

		// Widgets: classic text / custom HTML / block widgets.
		$widget_items = array();
		$widget_options = array( 'widget_text' => 'text', 'widget_custom_html' => 'content', 'widget_block' => 'content' );
		$sidebars = function_exists( 'wp_get_sidebars_widgets' ) ? wp_get_sidebars_widgets() : array();
		foreach ( $widget_options as $option => $field ) {
			$instances = get_option( $option );
			if ( ! is_array( $instances ) ) {
				continue;
			}
			$type = substr( $option, 7 );
			foreach ( $instances as $number => $instance ) {
				if ( ! is_array( $instance ) || ! is_numeric( $number ) || empty( $instance[ $field ] ) ) {
					continue;
				}
				$scan = self::scan_text( $instance[ $field ] );
				if ( $scan['counts'] ) {
					$widget_items[] = array(
						'type'    => $type,
						'id'      => $type . '-' . $number,
						'sidebar' => self::widget_sidebar( $sidebars, $type . '-' . $number ),
						'counts'  => $scan['counts'],
						'flags'   => $scan['flags'],
					);
				}
			}
		}
		$surfaces['widgets'] = array( 'count' => count( $widget_items ), 'items' => $widget_items );

		// Theme mods (Customizer text).
		$mods       = get_theme_mods();
		$mod_items  = array();
		if ( is_array( $mods ) ) {
			self::walk_strings( $mods, '', function ( $path, $value ) use ( &$mod_items ) {
				$scan = self::scan_text( $value );
				if ( $scan['counts'] ) {
					$mod_items[] = array( 'name' => $path, 'counts' => $scan['counts'] );
				}
			} );
		}
		$surfaces['theme_mods'] = array( 'count' => count( $mod_items ), 'items' => array_slice( $mod_items, 0, self::MAX_ITEMS ) );

		// Options (page builders, theme options, other plugins). Names only.
		$exclude = array_merge( array_keys( $widget_options ), array( self::RESULTS_OPTION, self::STATE_OPTION, 'theme_mods_' . get_option( 'stylesheet' ) ) );
		$sql     = "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s AND option_name NOT LIKE %s AND option_name NOT IN (" . implode( ',', array_fill( 0, count( $exclude ), '%s' ) ) . ') ORDER BY option_name LIMIT %d';
		$names   = $wpdb->get_col( $wpdb->prepare( $sql, array_merge( array( $like, $wpdb->esc_like( '_transient' ) . '%' ), $exclude, array( self::MAX_ITEMS + 1 ) ) ) );
		$option_items = array();
		foreach ( $names as $name ) {
			$value = get_option( $name );
			$count = 0;
			self::walk_strings( $value, '', function ( $path, $v ) use ( &$count ) {
				$scan = self::scan_text( $v );
				$count += array_sum( $scan['counts'] );
			} );
			if ( $count ) {
				$option_items[] = array( 'name' => $name, 'occurrences' => $count );
			}
		}
		$surfaces['options'] = array( 'count' => count( $option_items ), 'truncated' => count( $names ) > self::MAX_ITEMS, 'items' => array_slice( $option_items, 0, self::MAX_ITEMS ) );

		// Post meta: builders and custom fields. Keys and counts only.
		$meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, COUNT(*) AS rows_count, COUNT(DISTINCT post_id) AS posts FROM {$wpdb->postmeta} WHERE meta_value LIKE %s AND meta_key NOT LIKE %s GROUP BY meta_key ORDER BY rows_count DESC LIMIT %d", $like, $wpdb->esc_like( '_bs_migration' ) . '%', self::MAX_ITEMS ) );
		$meta_items = array();
		foreach ( $meta as $m ) {
			$meta_items[] = array( 'key' => $m->meta_key, 'rows' => (int) $m->rows_count, 'posts' => (int) $m->posts );
		}
		$surfaces['postmeta'] = array( 'count' => count( $meta_items ), 'items' => $meta_items );

		// Term descriptions.
		$terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, tt.taxonomy FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.description LIKE %s LIMIT %d", $like, self::MAX_ITEMS ) );
		$term_items = array();
		foreach ( $terms as $t ) {
			$term_items[] = array( 'term_id' => (int) $t->term_id, 'name' => $t->name, 'taxonomy' => $t->taxonomy );
		}
		$surfaces['terms'] = array( 'count' => count( $term_items ), 'items' => $term_items );

		// Theme PHP files that call do_shortcode() or embed [bs_ directly.
		$surfaces['theme_files'] = self::scan_theme_files();

		return $surfaces;
	}

	private static function widget_sidebar( $sidebars, $widget_id ) {
		foreach ( (array) $sidebars as $sidebar => $ids ) {
			if ( is_array( $ids ) && in_array( $widget_id, $ids, true ) ) {
				return $sidebar;
			}
		}
		return '';
	}

	private static function walk_strings( $value, $path, $callback, $depth = 0 ) {
		if ( $depth > 8 ) {
			return;
		}
		if ( is_string( $value ) ) {
			if ( false !== strpos( $value, '[bs_' ) ) {
				$callback( $path, $value );
			}
			return;
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( (array) $value as $k => $v ) {
				self::walk_strings( $v, '' === $path ? (string) $k : $path . '.' . $k, $callback, $depth + 1 );
			}
		}
	}

	private static function scan_theme_files() {
		$dirs  = array_unique( array( get_stylesheet_directory(), get_template_directory() ) );
		$items = array();
		$seen  = 0;
		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$iterator = new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), function ( $current ) {
				return ! in_array( $current->getFilename(), array( 'node_modules', 'vendor', '.git' ), true );
			} ) );
			foreach ( $iterator as $file ) {
				if ( 'php' !== strtolower( $file->getExtension() ) || ++$seen > 2000 ) {
					continue;
				}
				$code = @file_get_contents( $file->getPathname(), false, null, 0, 512 * 1024 );
				if ( false === $code ) {
					continue;
				}
				$hits = array();
				if ( false !== strpos( $code, 'do_shortcode' ) ) {
					$hits[] = 'do_shortcode()';
				}
				if ( false !== strpos( $code, '[bs_' ) ) {
					$hits[] = '[bs_ literal';
				}
				if ( $hits ) {
					$items[] = array( 'file' => str_replace( trailingslashit( dirname( $dir ) ), '', $file->getPathname() ), 'hits' => $hits );
				}
			}
		}
		return array( 'count' => count( $items ), 'items' => array_slice( $items, 0, self::MAX_ITEMS ), 'themes' => array_map( 'basename', $dirs ) );
	}

	/**
	 * What a text scan cannot see. Shown on the report so "no matches" is never
	 * read as "safe to remove".
	 */
	public static function not_scanned() {
		return array(
			'Plugin and mu-plugin PHP that calls do_shortcode() or echoes [bs_ markup (only the active theme\'s files are checked).',
			'Content stored outside this database: page builders or forms that keep their data on their own servers, external feeds, and other sites in a multisite network.',
			'Comments, user profile fields and nav menu descriptions (WordPress does not run shortcodes there by default).',
			'Bootstrap classes written directly as HTML (btn, row, col-md-6 ...): they depend on the Bootstrap CSS this plugin loads, not on its shortcodes, and a text search for [bs_ does not find them.',
			'Anything rendered from the Bootstrap JS by the theme itself (navbars, carousels, modals) when "Load Twitter Bootstrap javascript file" is on.',
		);
	}

	private static function environment() {
		$options = get_option( 'bs_options' );
		$options = is_array( $options ) ? $options : array();
		return array(
			'wordpress' => implode( '.', array_slice( explode( '.', get_bloginfo( 'version' ) ), 0, 2 ) ),
			'php'       => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'multisite' => is_multisite(),
			'settings'  => array(
				'css'         => ! empty( $options['chk_default_options_css'] ),
				'js'          => ! empty( $options['chk_default_options_js'] ),
				'conditional' => ! empty( $options['chk_conditional_assets'] ),
			),
		);
	}

	/**
	 * The optional report for the plugin author: aggregate counts and coarse
	 * environment only. No ids, titles, names, URLs, keys, file paths or dates
	 * finer than a month. Every key here is listed in docs/privacy.md.
	 */
	public static function share_payload() {
		$results = self::results();
		if ( ! $results || empty( $results['finished'] ) ) {
			return null;
		}
		$components = array();
		foreach ( $results['components'] as $tag => $c ) {
			$components[ $tag ] = array( 'occurrences' => (int) $c['occurrences'], 'posts' => (int) $c['posts'], 'published_posts' => (int) $c['published_posts'] );
		}
		$s = $results['surfaces'];
		return array(
			'schema'         => 1,
			'generated'      => gmdate( 'Y-m' ),
			'plugin_version' => BS_SHORTCODES_VERSION,
			'environment'    => array(
				'wordpress' => $results['environment']['wordpress'],
				'php'       => $results['environment']['php'],
				'multisite' => (bool) $results['environment']['multisite'],
			),
			'settings'       => $results['environment']['settings'],
			'totals'         => array(
				'posts_affected'     => (int) $results['totals']['posts_affected'],
				'published_affected' => (int) $results['totals']['published_affected'],
				'occurrences'        => (int) $results['totals']['occurrences'],
				'revisions'          => (int) $results['revisions'],
			),
			'components'     => $components,
			'flags'          => array_map( 'intval', $results['flags'] ),
			'surfaces'       => array(
				'widgets'     => isset( $s['widgets']['count'] ) ? (int) $s['widgets']['count'] : 0,
				'theme_mods'  => isset( $s['theme_mods']['count'] ) ? (int) $s['theme_mods']['count'] : 0,
				'options'     => isset( $s['options']['count'] ) ? (int) $s['options']['count'] : 0,
				'postmeta'    => isset( $s['postmeta']['count'] ) ? (int) $s['postmeta']['count'] : 0,
				'terms'       => isset( $s['terms']['count'] ) ? (int) $s['terms']['count'] : 0,
				'theme_files' => isset( $s['theme_files']['count'] ) ? (int) $s['theme_files']['count'] : 0,
			),
		);
	}
}
