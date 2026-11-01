<?php
/**
 * The migration half of Tools > Bootstrap Shortcodes: options, per-post
 * preview with a diff, migrate one / selected / in batches, roll back, and
 * the legacy-assets companion hand-off. Every write needs manage_options, a
 * nonce, and a click; nothing runs on activation, update or cron.
 */
class BS_Admin_Migration {

	const ACTION_PREVIEW  = 'bs_migrate_preview';
	const ACTION_APPLY    = 'bs_migrate_apply';
	const ACTION_BATCH    = 'bs_migrate_batch';
	const ACTION_ROLLBACK = 'bs_migrate_rollback';
	const ACTION_COMPANION = 'bs_companion_install';
	const ACTION_COMPANION_ZIP = 'bs_companion_zip';
	const BATCH = 10;

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION_APPLY, array( $this, 'handle_apply' ) );
		add_action( 'admin_post_' . self::ACTION_BATCH, array( $this, 'handle_batch_form' ) );
		add_action( 'admin_post_' . self::ACTION_ROLLBACK, array( $this, 'handle_rollback' ) );
		add_action( 'admin_post_' . self::ACTION_COMPANION, array( $this, 'handle_companion' ) );
		add_action( 'admin_post_' . self::ACTION_COMPANION_ZIP, array( $this, 'handle_companion_zip' ) );
		add_action( 'wp_ajax_' . self::ACTION_BATCH, array( $this, 'handle_batch_ajax' ) );
	}

	private function authorise( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'bsshortcodes' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	private function back( $args = array() ) {
		wp_safe_redirect( BS_Admin_Tools::url( $args ) );
		exit;
	}

	private function options_from_request() {
		return BS_Migration::options_from( array(
			'mode'  => isset( $_REQUEST['mode'] ) ? sanitize_key( $_REQUEST['mode'] ) : 'auto',
			'style' => isset( $_REQUEST['style'] ) ? sanitize_key( $_REQUEST['style'] ) : 'inline',
		) );
	}

	/* ---------------------------------------------------------------- */
	/* Handlers                                                          */
	/* ---------------------------------------------------------------- */

	public function handle_apply() {
		$this->authorise( self::ACTION_APPLY );
		$post_id  = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$expected = isset( $_POST['modified_gmt'] ) ? sanitize_text_field( wp_unslash( $_POST['modified_gmt'] ) ) : null;
		$r        = BS_Migration::apply( $post_id, $this->options_from_request(), $expected );
		if ( is_wp_error( $r ) ) {
			$this->back( array( 'bs_msg' => 'error', 'bs_text' => rawurlencode( $r->get_error_message() ), 'bs_preview' => $post_id, 'mode' => $this->options_from_request()['mode'], 'style' => $this->options_from_request()['style'] ) );
		}
		$this->back( array( 'bs_msg' => 'migrated', 'bs_count' => 1, 'bs_post' => $post_id ) );
	}

	/** No-JS batch: up to MAX_BATCH posts in one request. */
	public function handle_batch_form() {
		$this->authorise( self::ACTION_BATCH );
		$ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', (array) $_POST['post_ids'] ) : array();
		$out = BS_Migration::batch( $ids, $this->options_from_request() );
		$ok  = count( array_filter( $out, function ( $r ) { return $r['ok']; } ) );
		$this->back( array( 'bs_msg' => 'migrated', 'bs_count' => $ok, 'bs_failed' => count( $out ) - $ok ) );
	}

	public function handle_batch_ajax() {
		list( $status, $data ) = $this->batch_response();
		if ( 200 === $status ) {
			wp_send_json_success( $data );
		}
		wp_send_json_error( $data, $status );
	}

	public function batch_response() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 403, array( 'message' => 'forbidden' ) );
		}
		if ( ! check_ajax_referer( self::ACTION_BATCH, false, false ) ) {
			return array( 403, array( 'message' => 'bad nonce' ) );
		}
		$ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', (array) $_POST['post_ids'] ) : array();
		$ids = array_slice( $ids, 0, self::BATCH );
		return array( 200, array( 'results' => BS_Migration::batch( $ids, $this->options_from_request() ) ) );
	}

	public function handle_rollback() {
		$this->authorise( self::ACTION_ROLLBACK );
		$ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', (array) $_POST['post_ids'] ) : array();
		if ( isset( $_POST['post_id'] ) ) {
			$ids[] = (int) $_POST['post_id'];
		}
		$ok = 0;
		foreach ( array_slice( array_unique( $ids ), 0, BS_Migration::MAX_BATCH ) as $id ) {
			if ( true === BS_Migration::rollback( $id ) ) {
				$ok++;
			}
		}
		$this->back( array( 'bs_msg' => 'rolled_back', 'bs_count' => $ok ) );
	}

	public function handle_companion() {
		$this->authorise( self::ACTION_COMPANION );
		if ( empty( $_POST['bs_companion_consent'] ) ) {
			$this->back( array( 'bs_msg' => 'error', 'bs_text' => rawurlencode( __( 'Tick the confirmation box to install the companion.', 'bsshortcodes' ) ) ) );
		}
		$r = BS_Companion::install_and_activate();
		if ( is_wp_error( $r ) ) {
			$this->back( array( 'bs_msg' => 'error', 'bs_text' => rawurlencode( $r->get_error_message() ) ) );
		}
		$this->back( array( 'bs_msg' => 'companion' ) );
	}

	public function handle_companion_zip() {
		$this->authorise( self::ACTION_COMPANION_ZIP );
		$zip = BS_Companion::build_zip();
		if ( is_wp_error( $zip ) ) {
			wp_die( esc_html( $zip->get_error_message() ) );
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . BS_Companion::SLUG . '.zip"' );
		header( 'Content-Length: ' . filesize( $zip ) );
		readfile( $zip );
		@unlink( $zip );
		exit;
	}

	/* ---------------------------------------------------------------- */
	/* Rendering (called from BS_Admin_Tools::render)                    */
	/* ---------------------------------------------------------------- */

	public function render_notices() {
		if ( empty( $_GET['bs_msg'] ) ) {
			return;
		}
		$msg   = sanitize_key( $_GET['bs_msg'] );
		$count = isset( $_GET['bs_count'] ) ? (int) $_GET['bs_count'] : 0;
		$class = 'notice-success';
		switch ( $msg ) {
			case 'migrated':
				$failed = isset( $_GET['bs_failed'] ) ? (int) $_GET['bs_failed'] : 0;
				/* translators: %d: count */
				$text = sprintf( _n( '%d post migrated.', '%d posts migrated.', $count, 'bsshortcodes' ), $count );
				if ( $failed ) {
					/* translators: %d: count */
					$text .= ' ' . sprintf( __( '%d could not be migrated (nothing convertible, or skipped occurrences); see the list.', 'bsshortcodes' ), $failed );
					$class = 'notice-warning';
				}
				$text .= ' ' . __( 'Re-run the scan to refresh the report.', 'bsshortcodes' );
				break;
			case 'rolled_back':
				/* translators: %d: count */
				$text = sprintf( _n( '%d post rolled back to its original content.', '%d posts rolled back to their original content.', $count, 'bsshortcodes' ), $count );
				break;
			case 'companion':
				$text = __( 'Bootstrap 3 Legacy Assets is installed and active. It stays idle until Bootstrap Shortcodes stops loading its own files.', 'bsshortcodes' );
				break;
			case 'error':
				$class = 'notice-error';
				$text  = isset( $_GET['bs_text'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['bs_text'] ) ) ) : __( 'Something went wrong.', 'bsshortcodes' );
				break;
			default:
				return;
		}
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}

	public function current_options() {
		return $this->options_from_request();
	}

	/** The per-post preview page (diff + migrate button). Returns true if it rendered. */
	public function render_preview() {
		if ( empty( $_GET['bs_preview'] ) ) {
			return false;
		}
		$post_id = (int) $_GET['bs_preview'];
		$options = $this->options_from_request();
		$preview = BS_Migration::preview( $post_id, $options );
		echo '<p><a href="' . esc_url( BS_Admin_Tools::url() ) . '">&larr; ' . esc_html__( 'Back to the report', 'bsshortcodes' ) . '</a></p>';
		if ( is_wp_error( $preview ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $preview->get_error_message() ) . '</p></div>';
			return true;
		}
		$r = $preview['result'];
		?>
		<h2><?php printf( esc_html__( 'Preview: %s', 'bsshortcodes' ), esc_html( $preview['title'] ? $preview['title'] : '#' . $post_id ) ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: mode, 2: style */
				esc_html__( 'Output: %1$s, styling: %2$s.', 'bsshortcodes' ),
				esc_html( 'blocks' === $r['mode'] ? __( 'block markup', 'bsshortcodes' ) : __( 'static HTML', 'bsshortcodes' ) ),
				esc_html( 'inline' === $r['style'] ? __( 'self-contained (inline styles, inline SVG icons)', 'bsshortcodes' ) : __( 'Bootstrap classes only', 'bsshortcodes' ) )
			);
			?>
			<?php echo $this->options_switcher( $options, array( 'bs_preview' => $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</p>
		<?php if ( $preview['already'] ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'This post was migrated before; its stored original is kept and will not be replaced by migrating again.', 'bsshortcodes' ); ?></p></div>
		<?php endif; ?>
		<?php $this->render_result_lists( $r ); ?>
		<?php if ( 0 === $r['converted'] ) : ?>
			<p><strong><?php esc_html_e( 'Nothing can be converted automatically in this post.', 'bsshortcodes' ); ?></strong></p>
		<?php else : ?>
			<?php
			$diff = wp_text_diff( $preview['original'], $preview['converted'], array( 'title_left' => __( 'Current content', 'bsshortcodes' ), 'title_right' => __( 'After migration', 'bsshortcodes' ) ) );
			echo $diff ? $diff : '<p>' . esc_html__( 'No visible difference.', 'bsshortcodes' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_text_diff escapes.
			?>
			<details>
				<summary><?php esc_html_e( 'Full converted source', 'bsshortcodes' ); ?></summary>
				<textarea readonly rows="16" style="width:100%;font-family:monospace"><?php echo esc_textarea( $preview['converted'] ); ?></textarea>
			</details>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bs-migrate-one">
				<?php wp_nonce_field( self::ACTION_APPLY ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_APPLY ); ?>" />
				<input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>" />
				<input type="hidden" name="modified_gmt" value="<?php echo esc_attr( $preview['modified_gmt'] ); ?>" />
				<input type="hidden" name="mode" value="<?php echo esc_attr( $options['mode'] ); ?>" />
				<input type="hidden" name="style" value="<?php echo esc_attr( $options['style'] ); ?>" />
				<p>
					<button class="button button-primary"><?php esc_html_e( 'Migrate this post', 'bsshortcodes' ); ?></button>
					<span class="description"><?php esc_html_e( 'Writes the converted content, keeps the original for rollback, and adds a revision. Refuses if the post changed since this preview.', 'bsshortcodes' ); ?></span>
				</p>
			</form>
		<?php endif; ?>
		<?php
		return true;
	}

	private function render_result_lists( $r ) {
		if ( $r['skipped'] ) :
			?>
			<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Left in place for manual review:', 'bsshortcodes' ); ?></strong>
			<?php
			$parts = array();
			foreach ( $r['skipped'] as $s ) {
				$reasons = array(
					'unbalanced'     => __( 'opening/closing tags do not match', 'bsshortcodes' ),
					'inline-context' => __( 'a block-level component written inside a paragraph or heading; move it onto its own line, then migrate again', 'bsshortcodes' ),
				);
				$parts[] = sprintf( '[%s] (%s)', $s['tag'], isset( $reasons[ $s['reason'] ] ) ? $reasons[ $s['reason'] ] : $s['reason'] );
			}
			echo esc_html( implode( ', ', $parts ) );
			?>
			</p></div>
		<?php endif; ?>
		<?php if ( $r['notes'] ) : ?>
			<ul class="bs-notes">
				<?php foreach ( $r['notes'] as $note ) : ?>
					<li><?php echo esc_html( $note ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $r['dependencies'] ) : ?>
			<p><strong><?php esc_html_e( 'Still needs:', 'bsshortcodes' ); ?></strong>
				<?php echo esc_html( implode( ', ', array_map( array( $this, 'dependency_label' ), $r['dependencies'] ) ) ); ?>
				<?php esc_html_e( '(keep the theme\'s Bootstrap 3, or install the legacy-assets companion below).', 'bsshortcodes' ); ?>
			</p>
		<?php endif;
	}

	public function dependency_label( $dep ) {
		$labels = array(
			'bootstrap-css' => __( 'Bootstrap 3 CSS', 'bsshortcodes' ),
			'glyphicons'    => __( 'Glyphicons font (part of Bootstrap 3 CSS)', 'bsshortcodes' ),
		);
		return isset( $labels[ $dep ] ) ? $labels[ $dep ] : $dep;
	}

	private function options_switcher( $options, $extra_args ) {
		$links = array();
		foreach ( array( 'auto' => __( 'auto', 'bsshortcodes' ), 'blocks' => __( 'blocks', 'bsshortcodes' ), 'html' => __( 'HTML', 'bsshortcodes' ) ) as $mode => $label ) {
			$links[] = $mode === $options['mode'] ? '<strong>' . esc_html( $label ) . '</strong>' : '<a href="' . esc_url( BS_Admin_Tools::url( array_merge( $extra_args, array( 'mode' => $mode, 'style' => $options['style'] ) ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		$out = esc_html__( 'Output:', 'bsshortcodes' ) . ' ' . implode( ' | ', $links );
		$links = array();
		foreach ( array( 'inline' => __( 'self-contained', 'bsshortcodes' ), 'classes' => __( 'Bootstrap classes', 'bsshortcodes' ) ) as $style => $label ) {
			$links[] = $style === $options['style'] ? '<strong>' . esc_html( $label ) . '</strong>' : '<a href="' . esc_url( BS_Admin_Tools::url( array_merge( $extra_args, array( 'mode' => $options['mode'], 'style' => $style ) ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		return '<span class="bs-switcher">' . $out . ' &nbsp; ' . esc_html__( 'Styling:', 'bsshortcodes' ) . ' ' . implode( ' | ', $links ) . '</span>';
	}

	/** Migration controls shown under the report's affected-content table. */
	public function render_migration_section( $results ) {
		$options  = $this->options_from_request();
		$migrated = BS_Migration::migrated_posts();
		$pending  = array();
		foreach ( $results['posts'] as $p ) {
			if ( ! in_array( 'malformed', $p['flags'], true ) || $p['counts'] ) {
				$pending[] = $p['id'];
			}
		}
		?>
		<h2 id="migration"><?php esc_html_e( 'Migrate', 'bsshortcodes' ); ?></h2>
		<p><?php esc_html_e( 'Rewrites the shortcodes in a post as blocks or plain HTML so the post no longer needs this plugin. Every migrated post keeps its original in a custom field and gets a revision; you can roll back any time from this page. Occurrences the converter is unsure about (mismatched open/close tags, unrecognised tags) are left exactly as they are and listed for you.', 'bsshortcodes' ); ?>
			<a href="https://github.com/TheWebShop/bootstrap-shortcodes/blob/master/docs/migration.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'What each component becomes', 'bsshortcodes' ); ?></a>
		</p>
		<p><?php echo $this->options_switcher( $options, array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<ul class="description" style="list-style:disc;margin-left:1.5em">
			<li><?php esc_html_e( 'Output "auto": block markup for posts that already use blocks, static HTML for classic posts.', 'bsshortcodes' ); ?></li>
			<li><?php esc_html_e( 'Styling "self-contained": wells, alerts, labels, lead text and tooltips carry inline styles and icons become inline SVG, so nothing needs Bootstrap CSS. "Bootstrap classes" keeps the original class names only, for sites that keep Bootstrap 3 (theme or companion).', 'bsshortcodes' ); ?></li>
			<li><?php esc_html_e( 'Grid rows become Columns blocks (or keep row/col classes in HTML output), standalone buttons become Buttons blocks, collapse items and tabs become collapsible Details sections. Tabs change interaction (sections instead of tabs); alerts lose the dismiss button; tooltips become native title tooltips.', 'bsshortcodes' ); ?></li>
		</ul>

		<?php if ( $pending ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bs-migrate-batch" data-nonce="<?php echo esc_attr( wp_create_nonce( self::ACTION_BATCH ) ); ?>" data-action="<?php echo esc_attr( self::ACTION_BATCH ); ?>" data-batch="<?php echo (int) self::BATCH; ?>">
				<?php wp_nonce_field( self::ACTION_BATCH ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_BATCH ); ?>" />
				<input type="hidden" name="mode" value="<?php echo esc_attr( $options['mode'] ); ?>" />
				<input type="hidden" name="style" value="<?php echo esc_attr( $options['style'] ); ?>" />
				<table class="widefat striped bs-migrate-table">
					<thead><tr>
						<td class="check-column"><input type="checkbox" class="bs-check-all" aria-label="<?php esc_attr_e( 'Select all', 'bsshortcodes' ); ?>" /></td>
						<th><?php esc_html_e( 'Post', 'bsshortcodes' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bsshortcodes' ); ?></th>
						<th><?php esc_html_e( 'Components', 'bsshortcodes' ); ?></th>
						<th><?php esc_html_e( 'Complications', 'bsshortcodes' ); ?></th>
						<th><?php esc_html_e( 'Migration', 'bsshortcodes' ); ?></th>
					</tr></thead>
					<tbody>
					<?php
					$labels = BS_Admin_Tools::flag_labels();
					foreach ( $results['posts'] as $p ) :
						$is_migrated = in_array( $p['id'], $migrated, true );
						$preview_url = BS_Admin_Tools::url( array( 'bs_preview' => $p['id'], 'mode' => $options['mode'], 'style' => $options['style'] ) );
						?>
						<tr data-post-id="<?php echo (int) $p['id']; ?>">
							<th scope="row" class="check-column"><input type="checkbox" name="post_ids[]" value="<?php echo (int) $p['id']; ?>" /></th>
							<td><a href="<?php echo esc_url( get_edit_post_link( $p['id'] ) ); ?>"><?php echo esc_html( '' !== $p['title'] ? $p['title'] : '#' . $p['id'] ); ?></a></td>
							<td><?php echo esc_html( $p['status'] ); ?></td>
							<td><?php
								$parts = array();
								foreach ( $p['counts'] as $tag => $n ) {
									$parts[] = $tag . ' ×' . (int) $n;
								}
								echo esc_html( implode( ', ', $parts ) );
							?></td>
							<td><?php
								$parts = array();
								foreach ( $p['flags'] as $flag ) {
									$parts[] = isset( $labels[ $flag ] ) ? $labels[ $flag ] : $flag;
								}
								echo esc_html( implode( '; ', $parts ) );
							?></td>
							<td class="bs-migrate-status">
								<a href="<?php echo esc_url( $preview_url ); ?>"><?php esc_html_e( 'Preview', 'bsshortcodes' ); ?></a>
								<?php if ( $is_migrated ) : ?>
									<em><?php esc_html_e( '(migrated before; shortcodes remain)', 'bsshortcodes' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button class="button button-primary bs-migrate-selected" disabled><?php esc_html_e( 'Migrate selected', 'bsshortcodes' ); ?></button>
					<span class="description"><?php printf( esc_html__( 'Each post is read fresh and converted from its current content, %d at a time. Posts with mismatched tags are converted where possible and the rest is left for you.', 'bsshortcodes' ), (int) self::BATCH ); ?></span>
					<span class="bs-migrate-progress"></span>
				</p>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'The last scan found no post content to migrate.', 'bsshortcodes' ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Migrated posts (roll back)', 'bsshortcodes' ); ?></h3>
		<?php if ( ! $migrated ) : ?>
			<p><?php esc_html_e( 'None yet.', 'bsshortcodes' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bs-rollback" onsubmit="return window.confirm('<?php echo esc_js( __( 'Restore the original shortcode content of the selected posts?', 'bsshortcodes' ) ); ?>');">
				<?php wp_nonce_field( self::ACTION_ROLLBACK ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_ROLLBACK ); ?>" />
				<table class="widefat striped bs-rollback-table">
					<thead><tr><td class="check-column"><input type="checkbox" class="bs-check-all" aria-label="<?php esc_attr_e( 'Select all', 'bsshortcodes' ); ?>" /></td><th><?php esc_html_e( 'Post', 'bsshortcodes' ); ?></th><th><?php esc_html_e( 'Migrated', 'bsshortcodes' ); ?></th><th><?php esc_html_e( 'Result', 'bsshortcodes' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $migrated as $id ) :
						$meta = get_post_meta( $id, BS_Migration::META_RESULT, true );
						$meta = is_array( $meta ) ? $meta : array();
						?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" name="post_ids[]" value="<?php echo (int) $id; ?>" /></th>
							<td><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ? get_the_title( $id ) : '#' . $id ); ?></a> <a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php esc_html_e( 'view', 'bsshortcodes' ); ?></a></td>
							<td><?php echo isset( $meta['migrated_at'] ) ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $meta['migrated_at'] ) ) : ''; ?></td>
							<td><?php
								if ( $meta ) {
									printf( esc_html__( '%1$d converted, %2$d left in place; %3$s', 'bsshortcodes' ), (int) $meta['converted'], (int) $meta['skipped'], esc_html( $meta['dependencies'] ? __( 'still needs: ', 'bsshortcodes' ) . implode( ', ', array_map( array( $this, 'dependency_label' ), $meta['dependencies'] ) ) : __( 'no legacy CSS needed', 'bsshortcodes' ) ) );
								}
							?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button class="button"><?php esc_html_e( 'Roll back selected', 'bsshortcodes' ); ?></button></p>
			</form>
		<?php endif; ?>

		<?php $this->render_companion_section( $results ); ?>
		<?php
	}

	private function render_companion_section( $results ) {
		$env       = $results['environment'];
		$installed = BS_Companion::is_installed();
		$active    = BS_Companion::is_active();
		?>
		<h3 id="legacy-assets"><?php esc_html_e( 'Keeping Bootstrap 3 after removing this plugin', 'bsshortcodes' ); ?></h3>
		<p><?php esc_html_e( 'Removing this plugin removes two things: the shortcodes, and (unless your theme provides Bootstrap) the Bootstrap 3 CSS/JavaScript it loads on every page. Migration handles the first. If anything else on the site still relies on Bootstrap 3 (theme markup, hand-written HTML with btn/row/col classes, posts migrated with "Bootstrap classes" styling, Glyphicons), you need the second too.', 'bsshortcodes' ); ?></p>
		<ul style="list-style:disc;margin-left:1.5em">
			<li><?php esc_html_e( 'Best: your theme loads its own Bootstrap, or you migrate everything with "self-contained" styling and need nothing.', 'bsshortcodes' ); ?></li>
			<li><?php esc_html_e( 'Otherwise: install the "Bootstrap 3 Legacy Assets" companion. It is a separate, tiny plugin built from this plugin\'s files that loads the same CSS/JS/font and nothing else. It registers no shortcodes, so the site is independent of Bootstrap Shortcodes but still carries a documented Bootstrap 3 dependency.', 'bsshortcodes' ); ?></li>
		</ul>
		<p><strong><?php esc_html_e( 'Bootstrap 3 is end-of-life (last release 3.4.1, 2019).', 'bsshortcodes' ); ?></strong> <?php esc_html_e( 'No upstream security fixes will come; what is known is documented in docs/security.md. Keeping it is a deliberate, temporary choice.', 'bsshortcodes' ); ?></p>
		<?php if ( $active ) : ?>
			<p class="notice notice-success inline" style="padding:8px"><?php esc_html_e( 'The companion is installed and active. It takes over the moment this plugin stops loading its files (deactivate this plugin, or turn off its asset settings).', 'bsshortcodes' ); ?> <a href="<?php echo esc_url( admin_url( 'options-general.php?page=bs-legacy-assets' ) ); ?>"><?php esc_html_e( 'Companion settings', 'bsshortcodes' ); ?></a></p>
		<?php elseif ( $installed ) : ?>
			<p class="notice notice-info inline" style="padding:8px"><?php esc_html_e( 'The companion is installed but not active.', 'bsshortcodes' ); ?> <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Plugins', 'bsshortcodes' ); ?></a></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bs-companion">
				<?php wp_nonce_field( self::ACTION_COMPANION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_COMPANION ); ?>" />
				<p><label><input type="checkbox" name="bs_companion_consent" value="1" /> <?php esc_html_e( 'I understand this installs and activates a separate plugin that keeps loading end-of-life Bootstrap 3.4.1 on my site, and that ownership of those assets moves from Bootstrap Shortcodes to it.', 'bsshortcodes' ); ?></label></p>
				<p>
					<button class="button"><?php esc_html_e( 'Install and activate the companion', 'bsshortcodes' ); ?></button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_COMPANION_ZIP ), self::ACTION_COMPANION_ZIP ) ); ?>"><?php esc_html_e( 'Download the companion zip instead', 'bsshortcodes' ); ?></a>
					<span class="description"><?php esc_html_e( 'Its settings start as copies of this plugin\'s CSS/JS settings.', 'bsshortcodes' ); ?></span>
				</p>
			</form>
		<?php endif; ?>
		<p class="description">
			<?php
			esc_html_e( 'Current settings: ', 'bsshortcodes' );
			echo esc_html( sprintf( 'CSS %s, JS %s.', $env['settings']['css'] ? __( 'on', 'bsshortcodes' ) : __( 'off', 'bsshortcodes' ), $env['settings']['js'] ? __( 'on', 'bsshortcodes' ) : __( 'off', 'bsshortcodes' ) ) );
			?>
		</p>
		<?php
	}
}
