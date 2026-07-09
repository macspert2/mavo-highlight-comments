<?php
/**
 * Polylang integration helpers.
 *
 * All helpers degrade gracefully when Polylang is not active: language lookups
 * return an empty string and the French-source lookup returns 0, which the rest
 * of the plugin treats as "no module / no candidates".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether Polylang is available.
 *
 * @return bool
 */
function mvhc_polylang_active() {
	return function_exists( 'pll_get_post_language' ) && function_exists( 'pll_get_post' );
}

/**
 * Get the Polylang language slug for a post.
 *
 * @param int $post_id Post ID. Defaults to the current post in the loop.
 * @return string Language slug (e.g. 'fr', 'en', 'de') or '' when unknown.
 */
function mvhc_get_post_language( $post_id = 0 ) {
	if ( ! function_exists( 'pll_get_post_language' ) ) {
		return '';
	}

	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$lang = pll_get_post_language( $post_id );

	return $lang ? (string) $lang : '';
}

/**
 * Find the French source post for a translated (EN/DE) post.
 *
 * Returns 0 when Polylang is inactive, the post is itself French, or no linked
 * French translation exists.
 *
 * @param int $post_id Translated post ID.
 * @return int French source post ID, or 0.
 */
function mvhc_get_french_source_post_id( $post_id ) {
	$post_id = (int) $post_id;

	if ( ! $post_id ) {
		return 0;
	}

	/**
	 * Allow overriding the resolved French source post ID, e.g. via the
	 * `_mvhc_source_post_id_override` meta or custom relation logic.
	 *
	 * @param int|null $override Non-null value short-circuits the lookup.
	 * @param int      $post_id  Translated post ID.
	 */
	$override = apply_filters( 'mvhc_source_post_id_override', null, $post_id );
	if ( null !== $override ) {
		return (int) $override;
	}

	if ( ! mvhc_polylang_active() ) {
		return 0;
	}

	$lang = pll_get_post_language( $post_id );

	if ( 'fr' === $lang ) {
		return 0;
	}

	$fr_id = pll_get_post( $post_id, 'fr' );

	return $fr_id ? (int) $fr_id : 0;
}

/**
 * Whether the module may render / be curated for this post's language.
 *
 * French originals never show the translated-comments module (highlights are a
 * translation feature). A dedicated "editor selected comment" feature for
 * same-language posts is planned separately (see plan section 0).
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function mvhc_language_allows_module( $post_id ) {
	$lang = mvhc_get_post_language( $post_id );

	// When Polylang is inactive we cannot determine language; allow rendering of
	// any manually stored highlights so the feature is not hard-blocked.
	if ( '' === $lang ) {
		return true;
	}

	return 'fr' !== $lang;
}
