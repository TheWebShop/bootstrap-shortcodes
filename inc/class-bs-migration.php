<?php
/**
 * Applying conversions to posts: preview, apply with a recoverable original,
 * roll back, and bounded batches. Nothing here runs automatically; every write
 * comes from an explicit admin action with a nonce and a capability check.
 */
class BS_Migration {

	const META_ORIGINAL = '_bs_migration_original';
	const META_RESULT   = '_bs_migration_result';
	const MAX_BATCH     = 25;

	public static function options_from( $input ) {
		$mode  = isset( $input['mode'] ) && in_array( $input['mode'], array( 'auto', 'blocks', 'html' ), true ) ? $input['mode'] : 'auto';
		$style = isset( $input['style'] ) && in_array( $input['style'], array( 'inline', 'classes' ), true ) ? $input['style'] : 'inline';
		return array( 'mode' => $mode, 'style' => $style );
	}

	/**
	 * Convert without writing. Includes what the apply step will check against.
	 */
	public static function preview( $post_id, $options ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'bs_not_found', __( 'Post not found.', 'bsshortcodes' ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'bs_forbidden', __( 'You cannot edit this post.', 'bsshortcodes' ) );
		}
		$result = BS_Migrator::convert( $post->post_content, $options );
		return array(
			'post_id'      => $post->ID,
			'title'        => $post->post_title,
			'status'       => $post->post_status,
			'modified_gmt' => $post->post_modified_gmt,
			'original'     => $post->post_content,
			'converted'    => $result['content'],
			'result'       => $result,
			'already'      => (bool) get_post_meta( $post->ID, self::META_ORIGINAL, true ),
		);
	}

	/**
	 * Write the conversion. Refuses if the post changed since $expected_modified_gmt
	 * (when given), keeps the original in post meta on first migration, and lets
	 * wp_update_post() add a revision as usual.
	 */
	public static function apply( $post_id, $options, $expected_modified_gmt = null ) {
		$preview = self::preview( $post_id, $options );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		if ( null !== $expected_modified_gmt && $expected_modified_gmt !== $preview['modified_gmt'] ) {
			return new WP_Error( 'bs_stale', __( 'This post was edited after the preview was made. Preview it again before migrating.', 'bsshortcodes' ) );
		}
		$result = $preview['result'];
		if ( 0 === $result['converted'] ) {
			return new WP_Error( 'bs_nothing', $result['skipped'] ? __( 'Nothing could be converted automatically; see the skipped list.', 'bsshortcodes' ) : __( 'No shortcodes to convert in this post.', 'bsshortcodes' ) );
		}
		// Users without unfiltered_html have their content passed through kses on
		// save, which strips the inline SVG icons (and, before WordPress 6.9, the
		// <details name> grouping). The preview shows the unfiltered markup, so
		// refuse before writing anything rather than save a different post.
		if ( self::would_be_filtered( $preview['converted'] ) ) {
			return self::filtered_error();
		}
		$backup_created = false;
		if ( ! $preview['already'] ) {
			// add_post_meta() and wp_update_post() both wp_unslash() their input, so
			// slash first or every backslash in the content is lost.
			$kept = add_post_meta( $post_id, self::META_ORIGINAL, wp_slash( array(
				'content'      => $preview['original'],
				'modified_gmt' => $preview['modified_gmt'],
				'migrated_at'  => time(),
				'version'      => BS_Migrator::VERSION,
			) ), true );
			if ( ! $kept ) {
				return new WP_Error( 'bs_backup', __( 'Could not store the original content; nothing was changed.', 'bsshortcodes' ) );
			}
			$backup_created = true;
		}
		$updated = wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $preview['converted'] ) ), true );
		if ( is_wp_error( $updated ) ) {
			if ( $backup_created ) {
				delete_post_meta( $post_id, self::META_ORIGINAL );
			}
			return $updated;
		}
		// Backstop for any other content_save_pre filter: never leave a silently
		// different version behind. Put the original back, and keep the stored
		// copy unless the restore is verified exact.
		$saved = get_post_field( 'post_content', $post_id, 'raw' );
		if ( $saved !== $preview['converted'] ) {
			wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $preview['original'] ) ) );
			if ( get_post_field( 'post_content', $post_id, 'raw' ) !== $preview['original'] ) {
				// The stored original (created above, or from an earlier migration) stays.
				return new WP_Error( 'bs_restore_failed', __( 'A filter on this site changed the post on save and the original could not be written back exactly. The original is kept in the custom field _bs_migration_original; roll back from the Migrated posts list, or restore it from the revisions.', 'bsshortcodes' ) );
			}
			if ( $backup_created ) {
				delete_post_meta( $post_id, self::META_ORIGINAL );
			}
			return new WP_Error( 'bs_filtered', __( 'A filter on this site changed the converted markup on save, so the post would not match the preview. The original was put back.', 'bsshortcodes' ) );
		}
		update_post_meta( $post_id, self::META_RESULT, array(
			'converted'    => $result['converted'],
			'skipped'      => count( $result['skipped'] ),
			'notes'        => $result['notes'],
			'dependencies' => $result['dependencies'],
			'mode'         => $result['mode'],
			'style'        => $result['style'],
			'migrated_at'  => time(),
		) );
		return array(
			'post_id'      => $post_id,
			'converted'    => $result['converted'],
			'skipped'      => $result['skipped'],
			'notes'        => $result['notes'],
			'dependencies' => $result['dependencies'],
		);
	}

	/** Put the stored original back. The migrated version stays in revisions. */
	public static function rollback( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'bs_not_found', __( 'Post not found.', 'bsshortcodes' ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'bs_forbidden', __( 'You cannot edit this post.', 'bsshortcodes' ) );
		}
		$backup = get_post_meta( $post->ID, self::META_ORIGINAL, true );
		if ( ! is_array( $backup ) || ! isset( $backup['content'] ) ) {
			return new WP_Error( 'bs_no_backup', __( 'No stored original for this post.', 'bsshortcodes' ) );
		}
		// Restoring as a user whose HTML is filtered would strip whatever the
		// original held that kses disallows (an embed, a script): refuse instead.
		if ( self::would_be_filtered( $backup['content'] ) ) {
			return self::filtered_error();
		}
		$updated = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $backup['content'] ) ), true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		if ( get_post_field( 'post_content', $post->ID, 'raw' ) !== $backup['content'] ) {
			// Some other filter changed it: keep the stored original so nothing is lost.
			return new WP_Error( 'bs_restore_failed', __( 'A filter on this site changed the content on save, so the original was not restored exactly. The stored original is kept; restore it from the revisions or the custom field _bs_migration_original.', 'bsshortcodes' ) );
		}
		delete_post_meta( $post->ID, self::META_ORIGINAL );
		delete_post_meta( $post->ID, self::META_RESULT );
		return true;
	}

	/** Would saving $content as the current user change it? (kses runs for users without unfiltered_html.) */
	private static function would_be_filtered( $content ) {
		if ( false === has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
			return false;
		}
		return wp_unslash( wp_filter_post_kses( wp_slash( $content ) ) ) !== $content;
	}

	private static function filtered_error() {
		return new WP_Error( 'bs_filtered', __( 'Your account cannot save this markup unchanged: WordPress filters HTML for users without the "unfiltered_html" capability, so the post would not match the preview. Nothing was changed. Run this as a user who has that capability; if no account on this site has it, this post cannot be converted automatically.', 'bsshortcodes' ) );
	}

	/**
	 * Apply to several posts. Each post is read fresh and converted from its
	 * current content, so a post edited after the scan is converted as it is
	 * now, never from a stale copy. Bounded to MAX_BATCH per call.
	 */
	public static function batch( $post_ids, $options ) {
		$post_ids = array_slice( array_values( array_unique( array_map( 'intval', (array) $post_ids ) ) ), 0, self::MAX_BATCH );
		$out      = array();
		foreach ( $post_ids as $id ) {
			$r = self::apply( $id, $options );
			$out[ $id ] = is_wp_error( $r )
				? array( 'ok' => false, 'code' => $r->get_error_code(), 'message' => $r->get_error_message() )
				: array( 'ok' => true, 'converted' => $r['converted'], 'skipped' => count( $r['skipped'] ), 'dependencies' => $r['dependencies'] );
		}
		return $out;
	}

	/**
	 * Posts that hold a stored original, newest first (bounded). Same types and
	 * statuses as the scan: 'any' would leave out reusable blocks, templates
	 * and trashed posts, which could then be migrated but never rolled back.
	 */
	public static function migrated_posts( $limit = 500 ) {
		return get_posts( array(
			'post_type'      => BS_Scanner::post_types(),
			'post_status'    => BS_Scanner::post_statuses(),
			'posts_per_page' => $limit,
			'meta_key'       => self::META_ORIGINAL,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
	}

	public static function is_migrated( $post_id ) {
		return (bool) get_post_meta( $post_id, self::META_ORIGINAL, true );
	}
}
