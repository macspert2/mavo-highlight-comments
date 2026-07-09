<?php
/**
 * Candidate scoring heuristics (French comments).
 *
 * Scoring is deliberately conservative and additive: it produces a score plus
 * human-readable reason/warning labels so the editor keeps final control. No
 * comment is ever auto-published based on score alone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * French regex fragments used for both scoring and filtering.
 *
 * @return array<string,string>
 */
function mvhc_patterns() {
	return array(
		'family'     => '/enfant|enfants|b[ée]b[ée]|ados?|poussette|famille|filles?|gar[çc]ons?|petits?|grands?/iu',
		'practical'  => '/itin[ée]raire|test[ée]|suivi|ador[ée]|conseil|pratique|transport|train|voiture|h[ôo]tel|logement|restaurant|adresse|budget|parking|visite|balade|randonn[ée]e/iu',
		'validation' => '/nous avons suivi|nous avons test[ée]|on a test[ée]|on a suivi|gr[âa]ce [àa] vous|vos conseils|nous sommes all[ée]s|nous avons ador[ée]/iu',
		'outdated'   => '/\b\d+\s?€|francs|en 20\d\d|tarif|prix|horaires?|ferm[ée]|ouvert|navette|billet/iu',
		'thanks'     => '/^(merci|super article|tr[èe]s int[ée]ressant|bravo|g[ée]nial|superbe article)[\s!.…]*$/iu',
		'url'        => '#https?://|www\.#i',
		'email'      => '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
		'phone'      => '/(\+\d{1,3}[\s.-]?)?(\(?\d{2,4}\)?[\s.-]?){3,}\d/',
	);
}

/**
 * Commercial-looking author-name tokens.
 *
 * `voyage`/`tour`/`guide` appear in ordinary French, so these drive a negative
 * score rather than a hard exclusion (plan §9.5).
 *
 * @return string[]
 */
function mvhc_commercial_tokens() {
	return array(
		'voyage',
		'travel',
		'circuit',
		'agence',
		'hotel',
		'hôtel',
		'visa',
		'taxi',
		'excursion',
		'tour',
		'guide',
		'seo',
		'assurance',
		'location',
		'immobilier',
		'discount',
	);
}

/**
 * Whether an author name looks commercial.
 *
 * A single incidental token (e.g. "Marie en voyage") is not enough — we require
 * either two commercial tokens, or a token combined with a place/duration cue
 * such as "circuit vietnam cambodge 3 semaines".
 *
 * @param string $author Author display name.
 * @return bool
 */
function mvhc_comment_author_looks_commercial( $author ) {
	$author = mb_strtolower( (string) $author );

	if ( '' === $author ) {
		return false;
	}

	$hits = 0;
	foreach ( mvhc_commercial_tokens() as $token ) {
		if ( false !== mb_strpos( $author, $token ) ) {
			$hits++;
		}
	}

	if ( $hits >= 2 ) {
		return true;
	}

	// One commercial token plus a "3 semaines / 2 weeks" style duration, or a
	// long multi-word promotional name, reads as commercial.
	if ( $hits >= 1 && preg_match( '/\d+\s?(semaines?|jours?|weeks?|days?)/iu', $author ) ) {
		return true;
	}

	return false;
}

/**
 * Score a French comment as a highlight candidate.
 *
 * @param WP_Comment|object $comment Comment object.
 * @param array             $context Optional context (target_post_id, source_post_id).
 * @return array{score:int,reasons:array<int,string>,warnings:array<int,string>}
 */
function mvhc_score_comment_candidate( $comment, $context = array() ) {
	$text     = isset( $comment->comment_content ) ? (string) $comment->comment_content : '';
	$author   = isset( $comment->comment_author ) ? (string) $comment->comment_author : '';
	$length   = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	$patterns = mvhc_patterns();

	$score    = 0;
	$reasons  = array();
	$warnings = array();

	// --- Positive signals -------------------------------------------------
	if ( preg_match( $patterns['family'], $text ) ) {
		$score += 30;
		$reasons[] = __( 'children/family', 'mavo-highlight-comments' );
	}

	if ( preg_match( $patterns['validation'], $text ) ) {
		$score += 25;
		$reasons[] = __( 'followed/tested the itinerary', 'mavo-highlight-comments' );
	}

	if ( preg_match( $patterns['practical'], $text ) ) {
		$score += 25;
		$reasons[] = __( 'practical feedback or tip', 'mavo-highlight-comments' );
	}

	if ( $length >= 200 && $length <= 900 ) {
		$score += 10;
		$reasons[] = __( 'good length', 'mavo-highlight-comments' );
	}

	if ( mvhc_author_name_looks_personal( $author ) ) {
		$score += 10;
		$reasons[] = __( 'real personal name', 'mavo-highlight-comments' );
	}

	// --- Negative signals -------------------------------------------------
	if ( preg_match( $patterns['url'], $text ) ) {
		$score -= 20;
		$warnings[] = __( 'contains a URL', 'mavo-highlight-comments' );
	}

	if ( mvhc_comment_author_looks_commercial( $author ) ) {
		$score -= 30;
		$warnings[] = __( 'commercial-looking author', 'mavo-highlight-comments' );
	}

	if ( mvhc_comment_is_generic_thanks( $text ) ) {
		$score -= 20;
		$warnings[] = __( 'mostly generic thanks', 'mavo-highlight-comments' );
	}

	if ( preg_match( $patterns['outdated'], $text ) ) {
		$score -= 30;
		$warnings[] = __( 'may contain outdated detail (price/time)', 'mavo-highlight-comments' );
	}

	if ( preg_match( $patterns['email'], $text ) || preg_match( $patterns['phone'], $text ) ) {
		$score -= 50;
		$warnings[] = __( 'contains personal contact info', 'mavo-highlight-comments' );
	}

	if ( $length < 120 ) {
		$score -= 20;
		$warnings[] = __( 'very short', 'mavo-highlight-comments' );
	} elseif ( $length > 1200 ) {
		$score -= 10;
		$warnings[] = __( 'very long', 'mavo-highlight-comments' );
	}

	$score_data = array(
		'score'    => $score,
		'reasons'  => $reasons,
		'warnings' => $warnings,
	);

	/**
	 * Filter the computed score data for a candidate comment.
	 *
	 * @param array             $score_data Score/reasons/warnings.
	 * @param WP_Comment|object $comment    Comment.
	 * @param array             $context    Context.
	 */
	return apply_filters( 'mvhc_score_comment_candidate', $score_data, $comment, $context );
}

/**
 * Heuristic: does the author name look like a real personal name?
 *
 * @param string $author Author name.
 * @return bool
 */
function mvhc_author_name_looks_personal( $author ) {
	$author = trim( (string) $author );

	if ( '' === $author || mvhc_comment_author_looks_commercial( $author ) ) {
		return false;
	}

	// One or two words, mostly letters, no digits or URLs.
	if ( preg_match( '/\d|https?:|www\./i', $author ) ) {
		return false;
	}

	$words = preg_split( '/\s+/', $author );

	return count( $words ) >= 1 && count( $words ) <= 3 && mb_strlen( $author ) <= 40;
}

/**
 * Whether a comment is short and mostly a generic thank-you.
 *
 * @param string $text Comment text.
 * @return bool
 */
function mvhc_comment_is_generic_thanks( $text ) {
	$trimmed = trim( wp_strip_all_tags( (string) $text ) );
	$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $trimmed ) : strlen( $trimmed );

	if ( $length >= 150 ) {
		return false;
	}

	$patterns = mvhc_patterns();

	if ( preg_match( $patterns['thanks'], $trimmed ) ) {
		return true;
	}

	// "merci pour cet article" and close variants under the length threshold.
	if ( preg_match( '/^merci\b/iu', $trimmed ) && $length < 100 ) {
		return true;
	}

	return false;
}
