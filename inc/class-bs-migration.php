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
		if ( ! $preview['already'] ) {
			$kept = add_post_meta( $post_id, self::META_ORIGINAL, array(
				'content'      => $preview['original'],
				'modified_gmt' => $preview['modified_gmt'],
				'migrated_at'  => time(),
				'version'      => BS_Migrator::VERSION,
			), true );
			if ( ! $kept ) {
				return new WP_Error( 'bs_backup', __( 'Could not store the original content; nothing was changed.', 'bsshortcodes' ) );
			}
		}
		$updated = wp_update_post( array( 'ID' => $post_id, 'post_content' => $preview['converted'] ), true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
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
		$updated = wp_update_post( array( 'ID' => $post->ID, 'post_content' => $backup['content'] ), true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		delete_post_meta( $post->ID, self::META_ORIGINAL );
		delete_post_meta( $post->ID, self::META_RESULT );
		return true;
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

	/** Posts that hold a stored original, newest first (bounded). */
	public static function migrated_posts( $limit = 500 ) {
		return get_posts( array(
			'post_type'      => 'any',
			'post_status'    => 'any',
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
