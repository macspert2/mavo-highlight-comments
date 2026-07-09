<?php
/**
 * Uninstall cleanup: remove all post meta this plugin created.
 *
 * Deactivation leaves everything intact; deletion removes the curated data.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$meta_keys = array(
	'_mvhc_highlights',
	'_mvhc_disabled',
	'_mvhc_title_override',
	'_mvhc_intro_override',
	'_mvhc_admin_note',
	'_mvhc_last_candidate_refresh',
	'_mvhc_source_post_id_override',
);

foreach ( $meta_keys as $meta_key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
