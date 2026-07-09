<?php
/**
 * Sanitization and validation for stored highlight data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed author display modes.
 *
 * @return string[]
 */
function mvhc_author_modes() {
	return array( 'first_name', 'anonymous', 'custom' );
}

/**
 * Allowed highlight statuses.
 *
 * @return string[]
 */
function mvhc_statuses() {
	return array( 'draft', 'published' );
}

/**
 * Sanitize a single highlight row coming from the admin form (raw $_POST).
 *
 * @param array $raw     Raw, slashed input for one highlight.
 * @param int   $post_id Target (translated) post ID.
 * @return array|null Clean highlight array, or null when the row is empty/invalid.
 */
function mvhc_sanitize_highlight( $raw, $post_id ) {
	if ( ! is_array( $raw ) ) {
		return null;
	}

	$get = static function ( $key ) use ( $raw ) {
		return isset( $raw[ $key ] ) ? wp_unslash( $raw[ $key ] ) : '';
	};

	// Translated text is stored as plain text — no source comment HTML ever
	// reaches the frontend. (Plan §12, §18.)
	$translated_text = trim( wp_strip_all_tags( (string) $get( 'translated_text' ) ) );

	$author_mode = sanitize_key( $get( 'author_mode' ) );
	if ( ! in_array( $author_mode, mvhc_author_modes(), true ) ) {
		$author_mode = 'first_name';
	}

	$status = sanitize_key( $get( 'status' ) );
	if ( ! in_array( $status, mvhc_statuses(), true ) ) {
		$status = 'draft';
	}

	$highlight = array(
		'source_comment_id' => absint( $get( 'source_comment_id' ) ),
		'source_post_id'    => absint( $get( 'source_post_id' ) ) ?: (int) mvhc_get_french_source_post_id( $post_id ),
		'source_lang'       => sanitize_key( $get( 'source_lang' ) ) ?: 'fr',
		'target_lang'       => sanitize_key( $get( 'target_lang' ) ) ?: mvhc_get_post_language( $post_id ),
		'display_author'    => sanitize_text_field( $get( 'display_author' ) ),
		'author_mode'       => $author_mode,
		'translated_text'   => $translated_text,
		'editor_note'       => sanitize_textarea_field( $get( 'editor_note' ) ),
		'status'            => $status,
		'order'             => absint( $get( 'order' ) ),
	);

	// Drop entirely empty rows (no text and no source comment reference).
	if ( '' === $highlight['translated_text'] && 0 === $highlight['source_comment_id'] ) {
		return null;
	}

	return $highlight;
}

/**
 * Sanitize the full list of highlights submitted from the metabox.
 *
 * @param mixed $raw_list Raw `mvhc_highlights` array from $_POST.
 * @param int   $post_id  Target post ID.
 * @return array[] Clean, re-ordered list of highlights.
 */
function mvhc_sanitize_highlights( $raw_list, $post_id ) {
	if ( ! is_array( $raw_list ) ) {
		return array();
	}

	$clean = array();

	foreach ( $raw_list as $raw ) {
		$highlight = mvhc_sanitize_highlight( $raw, $post_id );
		if ( null !== $highlight ) {
			$clean[] = $highlight;
		}
	}

	// Sort by editor-provided order, then normalize to a stable 1..n sequence.
	usort(
		$clean,
		static function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		}
	);

	$order = 1;
	foreach ( $clean as &$highlight ) {
		$highlight['order'] = $order++;
	}
	unset( $highlight );

	return $clean;
}

/**
 * Normalize a highlight array read back from post meta for safe rendering.
 *
 * Guards against legacy/hand-edited meta by re-applying defaults and stripping
 * unexpected keys.
 *
 * @param mixed $highlight Stored highlight.
 * @return array|null
 */
function mvhc_normalize_stored_highlight( $highlight ) {
	if ( ! is_array( $highlight ) ) {
		return null;
	}

	$defaults = array(
		'source_comment_id' => 0,
		'source_post_id'    => 0,
		'source_lang'       => 'fr',
		'target_lang'       => '',
		'display_author'    => '',
		'author_mode'       => 'first_name',
		'translated_text'   => '',
		'editor_note'       => '',
		'status'            => 'draft',
		'order'             => 0,
	);

	$highlight = array_merge( $defaults, array_intersect_key( $highlight, $defaults ) );

	$highlight['source_comment_id'] = absint( $highlight['source_comment_id'] );
	$highlight['source_post_id']    = absint( $highlight['source_post_id'] );
	$highlight['source_lang']       = sanitize_key( $highlight['source_lang'] );
	$highlight['target_lang']       = sanitize_key( $highlight['target_lang'] );
	$highlight['display_author']    = sanitize_text_field( $highlight['display_author'] );
	$highlight['author_mode']       = in_array( $highlight['author_mode'], mvhc_author_modes(), true ) ? $highlight['author_mode'] : 'first_name';
	$highlight['translated_text']   = trim( wp_strip_all_tags( (string) $highlight['translated_text'] ) );
	$highlight['editor_note']       = sanitize_textarea_field( $highlight['editor_note'] );
	$highlight['status']            = in_array( $highlight['status'], mvhc_statuses(), true ) ? $highlight['status'] : 'draft';
	$highlight['order']             = absint( $highlight['order'] );

	return $highlight;
}
