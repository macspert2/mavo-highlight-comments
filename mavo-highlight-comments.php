<?php
/**
 * Plugin Name: Mavo Highlight Comments
 * Description: Curate and display translated highlights from French reader comments on translated (EN/DE) Maman Voyage articles, as a clearly labelled editorial block — never mixed into native comment threads.
 * Version: 0.1.2
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Maman Voyage
 * Text Domain: mavo-highlight-comments
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MVHC_VERSION', '0.1.2' );
define( 'MVHC_FILE', __FILE__ );
define( 'MVHC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MVHC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Post meta keys.
 */
define( 'MVHC_META_HIGHLIGHTS', '_mvhc_highlights' );
define( 'MVHC_META_DISABLED', '_mvhc_disabled' );
define( 'MVHC_META_TITLE_OVERRIDE', '_mvhc_title_override' );
define( 'MVHC_META_INTRO_OVERRIDE', '_mvhc_intro_override' );

require_once MVHC_PATH . 'includes/polylang.php';
require_once MVHC_PATH . 'includes/sanitization.php';
require_once MVHC_PATH . 'includes/scoring.php';
require_once MVHC_PATH . 'includes/candidates.php';
require_once MVHC_PATH . 'includes/render.php';

if ( is_admin() ) {
	require_once MVHC_PATH . 'includes/admin.php';
}

/**
 * Load translations.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'mavo-highlight-comments', false, dirname( plugin_basename( MVHC_FILE ) ) . '/languages' );
	}
);
