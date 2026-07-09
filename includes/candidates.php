<?php
/**
 * Candidate discovery: read approved French comments and filter/score them.
 *
 * This runs only on admin edit screens (plan §26); the public frontend never
 * queries comments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch approved reader comments from the French source post.
 *
 * @param int $fr_post_id     French source post ID.
 * @param int $target_post_id Translated post being edited.
 * @return WP_Comment[] Approved, top-level reader comments.
 */
function mvhc_get_source_comments( $fr_post_id, $target_post_id = 0 ) {
	$fr_post_id = (int) $fr_post_id;

	if ( ! $fr_post_id ) {
		return array();
	}

	/**
	 * Filter the maximum number of source comments to inspect.
	 *
	 * @param int $limit          Default 100.
	 * @param int $fr_post_id     French source post ID.
	 * @param int $target_post_id Translated post ID.
	 */
	$limit = (int) apply_filters( 'mvhc_candidate_comment_limit', 100, $fr_post_id, $target_post_id );

	$comments = get_comments(
		array(
			'post_id' => $fr_post_id,
			'status'  => 'approve',
			'type'    => 'comment',
			'number'  => $limit,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		)
	);

	return is_array( $comments ) ? $comments : array();
}

/**
 * Whether a comment is eligible to appear as an automatic candidate.
 *
 * Hard exclusions only (spam/trash are already filtered by the query). Softer
 * signals — commercial names, outdated details — are handled by scoring so the
 * editor can still choose them manually.
 *
 * @param WP_Comment|object $comment Comment.
 * @param array             $context Context.
 * @return array{ok:bool,reason:string}
 */
function mvhc_is_comment_candidate( $comment, $context = array() ) {
	$text   = isset( $comment->comment_content ) ? (string) $comment->comment_content : '';
	$text   = trim( wp_strip_all_tags( $text ) );
	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

	// Never surface Christine's own replies as candidates in v1 (plan §9.7).
	if ( mvhc_comment_is_author_reply( $comment, $context ) ) {
		return array(
			'ok'     => false,
			'reason' => 'author_reply',
		);
	}

	/**
	 * Filter the minimum candidate length in characters.
	 *
	 * @param int               $min     Default 120.
	 * @param WP_Comment|object $comment Comment.
	 */
	$min_length = (int) apply_filters( 'mvhc_min_candidate_length', 120, $comment );

	if ( $length < $min_length ) {
		return array(
			'ok'     => false,
			'reason' => 'too_short',
		);
	}

	if ( mvhc_comment_is_generic_thanks( $text ) ) {
		return array(
			'ok'     => false,
			'reason' => 'generic_thanks',
		);
	}

	$patterns = mvhc_patterns();

	// Contact info is a hard exclusion for automatic candidates (plan §9.4).
	if ( preg_match( $patterns['email'], $text ) || preg_match( $patterns['phone'], $text ) ) {
		return array(
			'ok'     => false,
			'reason' => 'contact_info',
		);
	}

	return array(
		'ok'     => true,
		'reason' => '',
	);
}

/**
 * Whether a comment was written by the post author / a site editor.
 *
 * @param WP_Comment|object $comment Comment.
 * @param array             $context Context (expects 'source_post_id').
 * @return bool
 */
function mvhc_comment_is_author_reply( $comment, $context = array() ) {
	$user_id = isset( $comment->user_id ) ? (int) $comment->user_id : 0;

	if ( $user_id && user_can( $user_id, 'edit_posts' ) ) {
		return true;
	}

	$source_post_id = isset( $context['source_post_id'] ) ? (int) $context['source_post_id'] : 0;
	if ( $source_post_id && $user_id ) {
		$post_author = (int) get_post_field( 'post_author', $source_post_id );
		if ( $post_author && $post_author === $user_id ) {
			return true;
		}
	}

	return false;
}

/**
 * Build the scored, filtered candidate shortlist for a translated post.
 *
 * @param int $target_post_id Translated post ID.
 * @return array{
 *     source_post_id:int,
 *     approved_count:int,
 *     candidates:array<int,array{comment:WP_Comment,score:int,reasons:array,warnings:array,high:bool}>
 * }
 */
function mvhc_get_candidates( $target_post_id ) {
	$target_post_id = (int) $target_post_id;
	$fr_post_id     = mvhc_get_french_source_post_id( $target_post_id );

	$result = array(
		'source_post_id' => $fr_post_id,
		'approved_count' => 0,
		'candidates'     => array(),
	);

	if ( ! $fr_post_id ) {
		return $result;
	}

	$comments = mvhc_get_source_comments( $fr_post_id, $target_post_id );

	$result['approved_count'] = count( $comments );

	$context = array(
		'target_post_id' => $target_post_id,
		'source_post_id' => $fr_post_id,
	);

	/**
	 * Filter the minimum score for a comment to enter the shortlist.
	 *
	 * @param int $threshold      Default 25.
	 * @param int $target_post_id Translated post ID.
	 * @param int $fr_post_id     French source post ID.
	 */
	$threshold = (int) apply_filters( 'mvhc_candidate_score_threshold', 25, $target_post_id, $fr_post_id );

	$candidates = array();

	foreach ( $comments as $comment ) {
		$eligible = mvhc_is_comment_candidate( $comment, $context );
		if ( ! $eligible['ok'] ) {
			continue;
		}

		$score_data = mvhc_score_comment_candidate( $comment, $context );

		if ( $score_data['score'] < $threshold ) {
			continue;
		}

		$candidates[] = array(
			'comment'  => $comment,
			'score'    => (int) $score_data['score'],
			'reasons'  => $score_data['reasons'],
			'warnings' => $score_data['warnings'],
			'high'     => $score_data['score'] >= 50,
		);
	}

	// Best candidates first.
	usort(
		$candidates,
		static function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		}
	);

	$result['candidates'] = $candidates;

	return $result;
}
