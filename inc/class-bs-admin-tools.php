<?php
/**
 * Tools > Bootstrap Shortcodes: run the dependency scan, read the report,
 * export it, and (phase 3) migrate.
 *
 * Everything here needs manage_options. Scanning writes only its own two
 * options; it never touches content.
 */
class BS_Admin_Tools {

	const PAGE   = 'bs-shortcodes-tools';
	const ACTION = 'bs_scan_step';
	const BATCH  = 100;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_form_step' ) );
		add_action( 'admin_post_bs_scan_reset', array( $this, 'handle_reset' ) );
		add_action( 'admin_post_bs_scan_export', array( $this, 'handle_export' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_ajax_step' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_page() {
		add_management_page(
			__( 'Bootstrap Shortcodes', 'bsshortcodes' ),
			__( 'Bootstrap Shortcodes', 'bsshortcodes' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'tools.php' ) );
	}

	public function enqueue( $hook ) {
		if ( 'tools_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_script( 'bs_tools', plugins_url( 'js/admin-tools.js', BS_SHORTCODES_FILE ), array(), BS_SHORTCODES_VERSION, true );
		wp_localize_script( 'bs_tools', 'bsTools', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => self::ACTION,
			'nonce'   => wp_create_nonce( self::ACTION ),
			'batch'   => self::BATCH,
			'reload'  => self::url(),
		) );
	}

	/* ---------------------------------------------------------------- */
	/* Handlers                                                          */
	/* ---------------------------------------------------------------- */

	private function authorise( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'bsshortcodes' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	/** No-JS path: one batch per request, then back to the page. */
	public function handle_form_step() {
		$this->authorise( self::ACTION );
		if ( ! empty( $_POST['start'] ) ) {
			BS_Scanner::start();
		}
		BS_Scanner::run_step( self::BATCH );
		wp_safe_redirect( self::url() );
		exit;
	}

	public function handle_reset() {
		$this->authorise( 'bs_scan_reset' );
		BS_Scanner::reset();
		wp_safe_redirect( self::url() );
		exit;
	}

	/** JS path: the page loops this until finished. */
	public function handle_ajax_step() {
		list( $status, $data ) = $this->ajax_step_response();
		if ( 200 === $status ) {
			wp_send_json_success( $data );
		}
		wp_send_json_error( $data, $status );
	}

	/**
	 * The AJAX step without the request-ending send, so it can be unit tested.
	 *
	 * @return array [ HTTP status, payload ]
	 */
	public function ajax_step_response() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 403, array( 'message' => 'forbidden' ) );
		}
		if ( ! check_ajax_referer( self::ACTION, false, false ) ) {
			return array( 403, array( 'message' => 'bad nonce' ) );
		}
		if ( ! empty( $_POST['start'] ) ) {
			BS_Scanner::start();
		}
		$state = BS_Scanner::run_step( self::BATCH );
		return array( 200, array(
			'finished'      => ! empty( $state['finished'] ),
			'posts_scanned' => (int) $state['posts_scanned'],
			'total'         => (int) $state['total'],
		) );
	}

	/**
	 * Download the report. "full" is the local report (post ids and titles
	 * included, for the site's own use); "share" is the aggregate payload.
	 */
	public function handle_export() {
		$this->authorise( 'bs_scan_export' );
		$kind = isset( $_GET['kind'] ) && 'share' === $_GET['kind'] ? 'share' : 'full';
		$data = self::export_data( $kind );
		if ( ! $data ) {
			wp_die( esc_html__( 'Run a scan first.', 'bsshortcodes' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bootstrap-shortcodes-' . $kind . '-report.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	public static function export_data( $kind ) {
		return 'share' === $kind ? BS_Scanner::share_payload() : BS_Scanner::results();
	}

	/* ---------------------------------------------------------------- */
	/* Page                                                              */
	/* ---------------------------------------------------------------- */

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state   = BS_Scanner::state();
		$results = BS_Scanner::results();
		$done    = $results && ! empty( $results['finished'] );
		?>
		<div class="wrap bs-tools">
			<h1><?php esc_html_e( 'Bootstrap Shortcodes: dependency report', 'bsshortcodes' ); ?></h1>
			<p><?php esc_html_e( 'What on this site depends on this plugin, and what would happen if you removed it. The scan reads content; it changes nothing and sends nothing anywhere.', 'bsshortcodes' ); ?></p>

			<?php $this->render_controls( $state, $done ); ?>

			<?php if ( $done ) : ?>
				<?php $this->render_impact( $results ); ?>
				<?php $this->render_components( $results ); ?>
				<?php $this->render_posts( $results ); ?>
				<?php $this->render_surfaces( $results ); ?>
				<?php $this->render_not_scanned( $results ); ?>
				<?php $this->render_share( $results ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_controls( $state, $done ) {
		$running = $state && empty( $state['finished'] );
		?>
		<div class="bs-scan-controls" data-running="<?php echo $running ? '1' : '0'; ?>">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bs-scan-form" style="display:inline-block">
				<?php wp_nonce_field( self::ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php if ( $running ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: posts scanned, 2: posts to scan */
							esc_html__( 'Scan in progress: %1$d of %2$d candidate posts checked.', 'bsshortcodes' ),
							(int) $state['posts_scanned'],
							(int) $state['total']
						);
						?>
						<span class="bs-scan-progress"></span>
					</p>
					<button class="button" name="continue" value="1"><?php esc_html_e( 'Scan next batch', 'bsshortcodes' ); ?></button>
					<span class="description bs-scan-nojs"><?php esc_html_e( '(with JavaScript on, batches continue automatically)', 'bsshortcodes' ); ?></span>
				<?php else : ?>
					<button class="button button-primary" name="start" value="1"><?php echo $done ? esc_html__( 'Scan again', 'bsshortcodes' ) : esc_html__( 'Scan this site', 'bsshortcodes' ); ?></button>
					<?php if ( $done ) : ?>
						<span class="description">
							<?php
							printf(
								/* translators: %s: date/time */
								esc_html__( 'Last scan finished %s.', 'bsshortcodes' ),
								esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $state['finished'] ) )
							);
							?>
						</span>
					<?php endif; ?>
				<?php endif; ?>
			</form>
			<?php if ( $state ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:1em">
					<?php wp_nonce_field( 'bs_scan_reset' ); ?>
					<input type="hidden" name="action" value="bs_scan_reset" />
					<button class="button-link"><?php esc_html_e( 'Discard results', 'bsshortcodes' ); ?></button>
				</form>
			<?php endif; ?>
			<p class="description">
				<?php
				printf(
					/* translators: %d: batch size */
					esc_html__( 'Posts, pages and every other post type are read %d at a time, so this is safe on large sites. Only posts whose content contains "[bs_" are examined.', 'bsshortcodes' ),
					(int) self::BATCH
				);
				?>
			</p>
		</div>
		<?php
	}

	private function render_impact( $results ) {
		$t   = $results['totals'];
		$env = $results['environment'];
		$s   = $results['surfaces'];
		$other = ( isset( $s['widgets']['count'] ) ? $s['widgets']['count'] : 0 )
			+ ( isset( $s['options']['count'] ) ? $s['options']['count'] : 0 )
			+ ( isset( $s['postmeta']['count'] ) ? $s['postmeta']['count'] : 0 )
			+ ( isset( $s['terms']['count'] ) ? $s['terms']['count'] : 0 )
			+ ( isset( $s['theme_mods']['count'] ) ? $s['theme_mods']['count'] : 0 )
			+ ( isset( $s['theme_files']['count'] ) ? $s['theme_files']['count'] : 0 );
		?>
		<h2><?php esc_html_e( 'If you deactivated this plugin today', 'bsshortcodes' ); ?></h2>
		<ul class="bs-impact">
			<li>
				<?php
				if ( $t['posts_affected'] ) {
					printf(
						/* translators: 1: occurrences, 2: published posts, 3: all posts */
						esc_html__( '%1$d shortcodes in %2$d published posts (%3$d including drafts, private and trashed) would show as literal "[bs_...]" text instead of components.', 'bsshortcodes' ),
						(int) $t['occurrences'],
						(int) $t['published_affected'],
						(int) $t['posts_affected']
					);
				} else {
					esc_html_e( 'No post content uses the shortcodes.', 'bsshortcodes' );
				}
				?>
			</li>
			<li>
				<?php
				if ( $env['settings']['css'] || $env['settings']['js'] ) {
					esc_html_e( 'Bootstrap 3 CSS and/or JavaScript would stop loading on every page. Anything in the theme or in hand-written HTML that relies on Bootstrap classes or plugins (navbars, grids, modals, carousels) would lose its styling or behaviour, whether or not it uses these shortcodes.', 'bsshortcodes' );
				} else {
					esc_html_e( 'The plugin is not loading Bootstrap (both asset settings are off), so removing it changes no stylesheets or scripts.', 'bsshortcodes' );
				}
				?>
			</li>
			<li>
				<?php
				if ( $other ) {
					printf(
						/* translators: %d: count */
						esc_html__( '%d other places outside post content reference the shortcodes (see "Other surfaces").', 'bsshortcodes' ),
						(int) $other
					);
				} else {
					esc_html_e( 'No widgets, options, custom fields, term descriptions or theme files reference the shortcodes.', 'bsshortcodes' );
				}
				?>
			</li>
			<li><?php esc_html_e( 'Some surfaces cannot be inspected by a text scan; see "Not scanned" before treating this as complete.', 'bsshortcodes' ); ?></li>
		</ul>
		<?php
	}

	private function render_components( $results ) {
		?>
		<h2><?php esc_html_e( 'Components in use', 'bsshortcodes' ); ?></h2>
		<?php if ( ! $results['components'] ) : ?>
			<p><?php esc_html_e( 'None found in post content.', 'bsshortcodes' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="widefat striped bs-components">
			<thead><tr>
				<th><?php esc_html_e( 'Shortcode', 'bsshortcodes' ); ?></th>
				<th><?php esc_html_e( 'Occurrences', 'bsshortcodes' ); ?></th>
				<th><?php esc_html_e( 'Posts', 'bsshortcodes' ); ?></th>
				<th><?php esc_html_e( 'Published posts', 'bsshortcodes' ); ?></th>
				<th><?php esc_html_e( 'Needs Bootstrap JS', 'bsshortcodes' ); ?></th>
			</tr></thead>
			<tbody>
			<?php
			$needs_js = array( 'bs_tab', 'bs_tabs', 'bs_thead', 'bs_dropdown', 'bs_tcontents', 'bs_tcontent', 'bs_collapse', 'bs_citem', 'bs_tooltip' );
			$components = $results['components'];
			ksort( $components );
			foreach ( $components as $tag => $c ) :
				?>
				<tr>
					<td><code>[<?php echo esc_html( $tag ); ?>]</code></td>
					<td><?php echo (int) $c['occurrences']; ?></td>
					<td><?php echo (int) $c['posts']; ?></td>
					<td><?php echo (int) $c['published_posts']; ?></td>
					<td><?php echo in_array( $tag, $needs_js, true ) ? esc_html__( 'yes', 'bsshortcodes' ) : ( 'bs_notification' === $tag ? esc_html__( 'dismiss button only', 'bsshortcodes' ) : esc_html__( 'no', 'bsshortcodes' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $results['revisions'] ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %d: count */
					esc_html__( 'Also present in %d post revisions (not counted above; revisions are restored only on request).', 'bsshortcodes' ),
					(int) $results['revisions']
				);
				?>
			</p>
		<?php endif; ?>
		<?php if ( $results['totals']['escaped'] ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %d: count */
					esc_html__( '%d escaped shortcodes ([[bs_...]]) were found; they display as text and do not depend on the plugin.', 'bsshortcodes' ),
					(int) $results['totals']['escaped']
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	public static function flag_labels() {
		return array(
			'nesting'           => __( 'nested inside another component', 'bsshortcodes' ),
			'custom_class'      => __( 'custom CSS classes', 'bsshortcodes' ),
			'unknown_attribute' => __( 'unknown attributes', 'bsshortcodes' ),
			'malformed'         => __( 'unbalanced open/close tags', 'bsshortcodes' ),
			'unknown_tag'       => __( 'unrecognised bs_ tag', 'bsshortcodes' ),
			'duplicate_id'      => __( 'duplicate ids', 'bsshortcodes' ),
			'missing_id'        => __( 'missing id', 'bsshortcodes' ),
		);
	}

	private function render_posts( $results ) {
		if ( ! $results['posts'] ) {
			return;
		}
		$labels = self::flag_labels();
		?>
		<h2><?php esc_html_e( 'Affected content', 'bsshortcodes' ); ?></h2>
		<?php if ( $results['flags'] ) : ?>
			<p>
				<?php esc_html_e( 'Migration complications found:', 'bsshortcodes' ); ?>
				<?php
				$parts = array();
				foreach ( $results['flags'] as $flag => $n ) {
					$parts[] = sprintf( '%s (%d)', isset( $labels[ $flag ] ) ? $labels[ $flag ] : $flag, (int) $n );
				}
				echo esc_html( implode( ', ', $parts ) );
				?>
			</p>
		<?php endif; ?>
		<table class="widefat striped bs-posts">
			<thead><tr>
				<th><?php esc_html_e( 'Title', 'bsshortcodes' ); ?></th>
				<th><?php esc_html_e( 'Type', 'bsshortcodes' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bsshortcodes' ); ?></th>
				<th><?php esc_html_e( 'Components', 'bsshortcodes' ); ?></th>
				<th><?php esc_html_e( 'Complications', 'bsshortcodes' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $results['posts'] as $p ) : ?>
				<tr>
					<td>
						<?php $edit = get_edit_post_link( $p['id'] ); ?>
						<?php if ( $edit ) : ?>
							<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( '' !== $p['title'] ? $p['title'] : sprintf( '#%d', $p['id'] ) ); ?></a>
						<?php else : ?>
							<?php echo esc_html( '' !== $p['title'] ? $p['title'] : sprintf( '#%d', $p['id'] ) ); ?>
						<?php endif; ?>
						<?php if ( 'publish' === $p['status'] ) : ?>
							<a href="<?php echo esc_url( get_permalink( $p['id'] ) ); ?>" class="bs-view"><?php esc_html_e( 'view', 'bsshortcodes' ); ?></a>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $p['type'] ); ?></td>
					<td><?php echo esc_html( $p['status'] ); ?></td>
					<td>
						<?php
						$parts = array();
						foreach ( $p['counts'] as $tag => $n ) {
							$parts[] = $tag . ' ×' . (int) $n;
						}
						echo esc_html( implode( ', ', $parts ) );
						?>
					</td>
					<td>
						<?php
						$parts = array();
						foreach ( $p['flags'] as $flag ) {
							$parts[] = isset( $labels[ $flag ] ) ? $labels[ $flag ] : $flag;
						}
						if ( $p['unknown_tags'] ) {
							$parts[] = implode( ', ', $p['unknown_tags'] );
						}
						echo esc_html( implode( '; ', $parts ) );
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $results['posts_overflow'] ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %d: count */
					esc_html__( '%d more affected posts are not listed here (counts above include them). Use the WP-CLI command for the full list.', 'bsshortcodes' ),
					(int) $results['posts_overflow']
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	private function render_surfaces( $results ) {
		$s = $results['surfaces'];
		?>
		<h2><?php esc_html_e( 'Other surfaces', 'bsshortcodes' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Found by text search; values are not shown. These need manual review: the migration tool only rewrites post content.', 'bsshortcodes' ); ?></p>
		<ul class="bs-surfaces">
			<li>
				<strong><?php esc_html_e( 'Widgets', 'bsshortcodes' ); ?>:</strong>
				<?php echo (int) $s['widgets']['count']; ?>
				<?php if ( $s['widgets']['items'] ) : ?>
					(<?php
					$parts = array();
					foreach ( $s['widgets']['items'] as $w ) {
						$parts[] = $w['id'] . ( $w['sidebar'] ? ' in ' . $w['sidebar'] : ' (inactive)' );
					}
					echo esc_html( implode( ', ', $parts ) );
					?>)
					<a href="<?php echo esc_url( admin_url( 'widgets.php' ) ); ?>"><?php esc_html_e( 'open widgets', 'bsshortcodes' ); ?></a>
				<?php endif; ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Customizer settings', 'bsshortcodes' ); ?>:</strong>
				<?php echo (int) $s['theme_mods']['count']; ?>
				<?php if ( $s['theme_mods']['items'] ) : ?>(<?php echo esc_html( implode( ', ', wp_list_pluck( $s['theme_mods']['items'], 'name' ) ) ); ?>)<?php endif; ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Options (other plugins / theme settings)', 'bsshortcodes' ); ?>:</strong>
				<?php echo (int) $s['options']['count']; ?>
				<?php if ( $s['options']['items'] ) : ?>(<?php echo esc_html( implode( ', ', wp_list_pluck( $s['options']['items'], 'name' ) ) ); ?>)<?php endif; ?>
				<?php if ( ! empty( $s['options']['truncated'] ) ) : ?><em><?php esc_html_e( 'list truncated', 'bsshortcodes' ); ?></em><?php endif; ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Custom fields / builder data', 'bsshortcodes' ); ?>:</strong>
				<?php echo (int) $s['postmeta']['count']; ?>
				<?php if ( $s['postmeta']['items'] ) : ?>
					(<?php
					$parts = array();
					foreach ( $s['postmeta']['items'] as $m ) {
						$parts[] = sprintf( '%s: %d posts', $m['key'], (int) $m['posts'] );
					}
					echo esc_html( implode( ', ', $parts ) );
					?>)
				<?php endif; ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Term descriptions', 'bsshortcodes' ); ?>:</strong>
				<?php echo (int) $s['terms']['count']; ?>
				<?php if ( $s['terms']['items'] ) : ?>
					(<?php
					$parts = array();
					foreach ( $s['terms']['items'] as $t ) {
						$link    = get_edit_term_link( $t['term_id'], $t['taxonomy'] );
						$parts[] = $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $t['name'] ) . '</a>' : esc_html( $t['name'] );
					}
					echo wp_kses( implode( ', ', $parts ), array( 'a' => array( 'href' => array() ) ) );
					?>)
				<?php endif; ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Active theme PHP files', 'bsshortcodes' ); ?>
					(<?php echo esc_html( implode( ', ', $s['theme_files']['themes'] ) ); ?>):</strong>
				<?php echo (int) $s['theme_files']['count']; ?>
				<?php if ( $s['theme_files']['items'] ) : ?>
					(<?php
					$parts = array();
					foreach ( $s['theme_files']['items'] as $f ) {
						$parts[] = $f['file'] . ' [' . implode( ', ', $f['hits'] ) . ']';
					}
					echo esc_html( implode( ', ', $parts ) );
					?>)
					<span class="description"><?php esc_html_e( 'A do_shortcode() call may render shortcodes from custom fields or options; check what it is fed.', 'bsshortcodes' ); ?></span>
				<?php endif; ?>
			</li>
		</ul>
		<?php
	}

	private function render_not_scanned( $results ) {
		?>
		<h2><?php esc_html_e( 'Not scanned', 'bsshortcodes' ); ?></h2>
		<ul class="bs-not-scanned">
			<?php foreach ( $results['not_scanned'] as $line ) : ?>
				<li><?php echo esc_html( $line ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private function render_share( $results ) {
		$payload = BS_Scanner::share_payload();
		$export  = wp_nonce_url( admin_url( 'admin-post.php?action=bs_scan_export&kind=full' ), 'bs_scan_export' );
		$share   = wp_nonce_url( admin_url( 'admin-post.php?action=bs_scan_export&kind=share' ), 'bs_scan_export' );
		?>
		<h2><?php esc_html_e( 'Export and sharing', 'bsshortcodes' ); ?></h2>
		<p>
			<a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Download full report (JSON)', 'bsshortcodes' ); ?></a>
			<span class="description"><?php esc_html_e( 'For your own records or your developer. Includes post ids and titles; keep it private.', 'bsshortcodes' ); ?></span>
		</p>

		<details class="bs-share">
			<summary><?php esc_html_e( 'Share an anonymous-as-possible summary with the plugin author (optional)', 'bsshortcodes' ); ?></summary>
			<p><?php esc_html_e( 'The author maintains this plugin for the sites that still need it, and wants to know which components people actually use so the migration tools cover them. This is entirely optional. Nothing is sent by this page: you download or copy the summary below and send it yourself, so you can read exactly what it contains first.', 'bsshortcodes' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Destination', 'bsshortcodes' ); ?>:</strong> <?php esc_html_e( 'a GitHub issue on TheWebShop/bootstrap-shortcodes (public, tied to your GitHub account), or an email to the author (see the plugin page on WordPress.org).', 'bsshortcodes' ); ?></li>
				<li><strong><?php esc_html_e( 'What it contains', 'bsshortcodes' ); ?>:</strong> <?php esc_html_e( 'counts per shortcode, totals, the complication counts, counts of other surfaces, your WordPress and PHP major.minor versions, whether this is a multisite, the three asset settings, the plugin version and the month.', 'bsshortcodes' ); ?></li>
				<li><strong><?php esc_html_e( 'What it never contains', 'bsshortcodes' ); ?>:</strong> <?php esc_html_e( 'site name or URL, post titles, ids, content, option or meta names, file paths, email addresses, user names.', 'bsshortcodes' ); ?></li>
				<li><strong><?php esc_html_e( 'How often', 'bsshortcodes' ); ?>:</strong> <?php esc_html_e( 'once, when you choose to. There is no recurring reporting and no setting to enable one.', 'bsshortcodes' ); ?></li>
				<li><strong><?php esc_html_e( 'Can it identify you?', 'bsshortcodes' ); ?>:</strong> <?php esc_html_e( 'Not from the data itself. The channel you send it through can (GitHub shows your account; email shows your address). The combination of counts could be unusual enough to recognise a site the author already knows, which is why nothing finer than month-level dates is included.', 'bsshortcodes' ); ?></li>
			</ul>
			<p>
				<a class="button" href="<?php echo esc_url( $share ); ?>"><?php esc_html_e( 'Download summary (JSON)', 'bsshortcodes' ); ?></a>
				<a class="button" href="https://github.com/TheWebShop/bootstrap-shortcodes/issues/new?title=Usage+summary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open a GitHub issue to paste it into', 'bsshortcodes' ); ?></a>
			</p>
			<textarea readonly rows="14" style="width:100%;font-family:monospace" aria-label="<?php esc_attr_e( 'Summary payload', 'bsshortcodes' ); ?>"><?php echo esc_textarea( wp_json_encode( $payload, JSON_PRETTY_PRINT ) ); ?></textarea>
		</details>
		<?php
	}
}
