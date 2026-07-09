<?php
/**
 * Admin: metabox for curating translated comment highlights.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types the metabox may appear on.
 *
 * @return string[]
 */
function mvhc_supported_post_types() {
	/** Filter the post types eligible for the highlights metabox. */
	return (array) apply_filters( 'mvhc_supported_post_types', array( 'post', 'page' ) );
}

/**
 * Register the metabox on eligible edit screens.
 *
 * @param string  $post_type Current post type.
 * @param WP_Post $post      Current post.
 * @return void
 */
function mvhc_register_metabox( $post_type, $post ) {
	if ( ! in_array( $post_type, mvhc_supported_post_types(), true ) ) {
		return;
	}

	// Only where curation could be relevant: non-French posts under Polylang.
	// When Polylang is inactive we still show the box so manual highlights can
	// be authored, but we skip French posts when the language is known.
	if ( mvhc_polylang_active() && ! mvhc_language_allows_module( $post->ID ) ) {
		return;
	}

	add_meta_box(
		'mvhc_highlights',
		__( 'Translated comment highlights', 'mavo-highlight-comments' ),
		'mvhc_render_metabox',
		$post_type,
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'mvhc_register_metabox', 10, 2 );

/**
 * Enqueue admin assets only on the post edit screen where the box appears.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function mvhc_enqueue_admin_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, mvhc_supported_post_types(), true ) ) {
		return;
	}

	wp_enqueue_style(
		'mvhc-admin',
		MVHC_URL . 'assets/css/admin.css',
		array(),
		MVHC_VERSION
	);

	wp_enqueue_script(
		'mvhc-admin',
		MVHC_URL . 'assets/js/admin.js',
		array(),
		MVHC_VERSION,
		true
	);

	wp_localize_script(
		'mvhc-admin',
		'mvhcAdmin',
		array(
			'i18n' => array(
				'confirmRemove' => __( 'Remove this highlight?', 'mavo-highlight-comments' ),
				'copied'        => __( 'Copied to a new highlight below.', 'mavo-highlight-comments' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'mvhc_enqueue_admin_assets' );

/**
 * Render one selected-highlight editor row.
 *
 * @param int   $index     Row index (integer, or the literal __INDEX__ template token).
 * @param array $highlight Highlight data.
 * @return void
 */
function mvhc_render_highlight_row( $index, $highlight ) {
	$defaults  = mvhc_normalize_stored_highlight( array() );
	$highlight = wp_parse_args( $highlight, $defaults );
	$name      = 'mvhc_highlights[' . $index . ']';
	$id_suffix = is_int( $index ) ? (string) $index : 'tpl';
	?>
	<div class="mvhc-row" data-mvhc-row>
		<div class="mvhc-row__head">
			<span class="mvhc-row__handle" aria-hidden="true">⋮⋮</span>
			<strong class="mvhc-row__title">
				<?php esc_html_e( 'Highlight', 'mavo-highlight-comments' ); ?>
				<span class="mvhc-row__num"></span>
			</strong>
			<button type="button" class="button-link mvhc-row__remove" data-mvhc-remove>
				<?php esc_html_e( 'Remove', 'mavo-highlight-comments' ); ?>
			</button>
		</div>

		<div class="mvhc-row__body">
			<p class="mvhc-field">
				<label for="mvhc-text-<?php echo esc_attr( $id_suffix ); ?>">
					<?php esc_html_e( 'Translated text', 'mavo-highlight-comments' ); ?>
				</label>
				<textarea
					id="mvhc-text-<?php echo esc_attr( $id_suffix ); ?>"
					name="<?php echo esc_attr( $name ); ?>[translated_text]"
					rows="4"
					class="large-text mvhc-field__text"
				><?php echo esc_textarea( $highlight['translated_text'] ); ?></textarea>
			</p>

			<div class="mvhc-field-grid">
				<p class="mvhc-field">
					<label for="mvhc-author-<?php echo esc_attr( $id_suffix ); ?>">
						<?php esc_html_e( 'Display author', 'mavo-highlight-comments' ); ?>
					</label>
					<input
						type="text"
						id="mvhc-author-<?php echo esc_attr( $id_suffix ); ?>"
						name="<?php echo esc_attr( $name ); ?>[display_author]"
						value="<?php echo esc_attr( $highlight['display_author'] ); ?>"
						class="regular-text"
					/>
				</p>

				<p class="mvhc-field">
					<label for="mvhc-mode-<?php echo esc_attr( $id_suffix ); ?>">
						<?php esc_html_e( 'Author display', 'mavo-highlight-comments' ); ?>
					</label>
					<select
						id="mvhc-mode-<?php echo esc_attr( $id_suffix ); ?>"
						name="<?php echo esc_attr( $name ); ?>[author_mode]"
					>
						<option value="first_name" <?php selected( $highlight['author_mode'], 'first_name' ); ?>>
							<?php esc_html_e( 'First name', 'mavo-highlight-comments' ); ?>
						</option>
						<option value="custom" <?php selected( $highlight['author_mode'], 'custom' ); ?>>
							<?php esc_html_e( 'Custom', 'mavo-highlight-comments' ); ?>
						</option>
						<option value="anonymous" <?php selected( $highlight['author_mode'], 'anonymous' ); ?>>
							<?php esc_html_e( 'Anonymous', 'mavo-highlight-comments' ); ?>
						</option>
					</select>
				</p>

				<p class="mvhc-field">
					<label for="mvhc-status-<?php echo esc_attr( $id_suffix ); ?>">
						<?php esc_html_e( 'Status', 'mavo-highlight-comments' ); ?>
					</label>
					<select
						id="mvhc-status-<?php echo esc_attr( $id_suffix ); ?>"
						name="<?php echo esc_attr( $name ); ?>[status]"
					>
						<option value="draft" <?php selected( $highlight['status'], 'draft' ); ?>>
							<?php esc_html_e( 'Draft', 'mavo-highlight-comments' ); ?>
						</option>
						<option value="published" <?php selected( $highlight['status'], 'published' ); ?>>
							<?php esc_html_e( 'Published', 'mavo-highlight-comments' ); ?>
						</option>
					</select>
				</p>
			</div>

			<p class="mvhc-field">
				<label for="mvhc-note-<?php echo esc_attr( $id_suffix ); ?>">
					<?php esc_html_e( 'Editor note (private)', 'mavo-highlight-comments' ); ?>
				</label>
				<input
					type="text"
					id="mvhc-note-<?php echo esc_attr( $id_suffix ); ?>"
					name="<?php echo esc_attr( $name ); ?>[editor_note]"
					value="<?php echo esc_attr( $highlight['editor_note'] ); ?>"
					class="large-text"
				/>
			</p>

			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[source_comment_id]" value="<?php echo esc_attr( $highlight['source_comment_id'] ); ?>" data-mvhc-field="source_comment_id" />
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[source_post_id]" value="<?php echo esc_attr( $highlight['source_post_id'] ); ?>" data-mvhc-field="source_post_id" />
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[source_lang]" value="<?php echo esc_attr( $highlight['source_lang'] ); ?>" data-mvhc-field="source_lang" />
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[target_lang]" value="<?php echo esc_attr( $highlight['target_lang'] ); ?>" data-mvhc-field="target_lang" />
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[order]" value="<?php echo esc_attr( $highlight['order'] ); ?>" data-mvhc-field="order" />
		</div>
	</div>
	<?php
}

/**
 * Render the metabox.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function mvhc_render_metabox( $post ) {
	wp_nonce_field( 'mvhc_save_highlights', 'mvhc_nonce' );

	$fr_post_id = mvhc_get_french_source_post_id( $post->ID );
	$highlights = mvhc_get_highlights( $post->ID, false );
	$disabled   = (bool) get_post_meta( $post->ID, MVHC_META_DISABLED, true );
	$lang       = mvhc_get_post_language( $post->ID );

	echo '<div class="mvhc-metabox">';

	// --- 1. Source information -------------------------------------------
	echo '<div class="mvhc-section mvhc-source">';
	echo '<h3>' . esc_html__( 'Source information', 'mavo-highlight-comments' ) . '</h3>';

	if ( ! mvhc_polylang_active() ) {
		echo '<p class="mvhc-notice">' . esc_html__( 'Polylang is not active. You can still author highlights manually, but automatic candidate suggestions are unavailable.', 'mavo-highlight-comments' ) . '</p>';
	} elseif ( ! $fr_post_id ) {
		echo '<p class="mvhc-notice">' . esc_html__( 'No linked French original found for this translated post.', 'mavo-highlight-comments' ) . '</p>';
	} else {
		$fr_title = get_the_title( $fr_post_id );
		$edit_url = get_edit_post_link( $fr_post_id );
		$view_url = get_permalink( $fr_post_id );

		echo '<ul class="mvhc-source__list">';
		echo '<li>' . esc_html__( 'French source post:', 'mavo-highlight-comments' ) . ' <strong>' . esc_html( $fr_title ) . '</strong> ';
		if ( $edit_url ) {
			echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'mavo-highlight-comments' ) . '</a> ';
		}
		if ( $view_url ) {
			echo '<a href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'mavo-highlight-comments' ) . '</a>';
		}
		echo '</li>';
		echo '<li>' . esc_html__( 'Current language:', 'mavo-highlight-comments' ) . ' <strong>' . esc_html( $lang ? $lang : '—' ) . '</strong></li>';
		echo '<li>' . esc_html__( 'Selected highlights:', 'mavo-highlight-comments' ) . ' <strong>' . esc_html( (string) count( $highlights ) ) . '</strong></li>';
		echo '</ul>';
	}
	echo '</div>';

	// --- 2. Display settings ---------------------------------------------
	echo '<div class="mvhc-section mvhc-display">';
	echo '<h3>' . esc_html__( 'Display settings', 'mavo-highlight-comments' ) . '</h3>';
	echo '<p><label><input type="checkbox" name="mvhc_disabled" value="1" ' . checked( $disabled, true, false ) . ' /> ' . esc_html__( 'Disable translated comment highlights on this post', 'mavo-highlight-comments' ) . '</label></p>';

	$title_override = (string) get_post_meta( $post->ID, MVHC_META_TITLE_OVERRIDE, true );
	$intro_override = (string) get_post_meta( $post->ID, MVHC_META_INTRO_OVERRIDE, true );

	echo '<p class="mvhc-field"><label for="mvhc-title-override">' . esc_html__( 'Module title override (optional)', 'mavo-highlight-comments' ) . '</label>';
	echo '<input type="text" id="mvhc-title-override" name="mvhc_title_override" value="' . esc_attr( $title_override ) . '" class="large-text" /></p>';

	echo '<p class="mvhc-field"><label for="mvhc-intro-override">' . esc_html__( 'Intro text override (optional)', 'mavo-highlight-comments' ) . '</label>';
	echo '<textarea id="mvhc-intro-override" name="mvhc_intro_override" rows="2" class="large-text">' . esc_textarea( $intro_override ) . '</textarea></p>';
	echo '</div>';

	// --- 3. Selected highlights ------------------------------------------
	echo '<div class="mvhc-section mvhc-selected">';
	echo '<h3>' . esc_html__( 'Selected highlights', 'mavo-highlight-comments' ) . '</h3>';
	echo '<p class="description">' . esc_html__( 'Add up to a handful of curated highlights. Only “Published” highlights with translated text appear on the frontend. Translated text is stored as plain text.', 'mavo-highlight-comments' ) . '</p>';

	echo '<div class="mvhc-rows" data-mvhc-rows>';
	if ( ! empty( $highlights ) ) {
		foreach ( $highlights as $i => $highlight ) {
			mvhc_render_highlight_row( (int) $i, $highlight );
		}
	}
	echo '</div>';

	echo '<p><button type="button" class="button" data-mvhc-add>' . esc_html__( '+ Add highlight', 'mavo-highlight-comments' ) . '</button></p>';

	// Hidden template row for JS cloning.
	echo '<script type="text/html" id="mvhc-row-template">';
	mvhc_render_highlight_row( '__INDEX__', mvhc_normalize_stored_highlight( array() ) );
	echo '</script>';
	echo '</div>';

	// --- 4. Suggested candidates -----------------------------------------
	echo '<div class="mvhc-section mvhc-candidates">';
	echo '<h3>' . esc_html__( 'Suggested candidates from French comments', 'mavo-highlight-comments' ) . '</h3>';
	mvhc_render_candidates_section( $post->ID, $fr_post_id );
	echo '</div>';

	echo '</div>'; // .mvhc-metabox
}

/**
 * Render the scored candidate shortlist.
 *
 * @param int $post_id    Translated post ID.
 * @param int $fr_post_id French source post ID (0 when none).
 * @return void
 */
function mvhc_render_candidates_section( $post_id, $fr_post_id ) {
	if ( ! mvhc_polylang_active() || ! $fr_post_id ) {
		echo '<p class="mvhc-notice">' . esc_html__( 'Candidate suggestions require a linked French original.', 'mavo-highlight-comments' ) . '</p>';
		return;
	}

	$data = mvhc_get_candidates( $post_id );

	if ( 0 === $data['approved_count'] ) {
		echo '<p class="mvhc-notice">' . esc_html__( 'The French original has no approved comments available for highlights.', 'mavo-highlight-comments' ) . '</p>';
		return;
	}

	echo '<p class="description">';
	printf(
		/* translators: 1: number of candidates, 2: number of approved comments scanned. */
		esc_html__( 'Showing %1$d candidate(s) out of %2$d approved comment(s). Use “Add as highlight” to copy one into a new highlight row above, then translate and review it.', 'mavo-highlight-comments' ),
		count( $data['candidates'] ),
		(int) $data['approved_count']
	);
	echo '</p>';

	if ( empty( $data['candidates'] ) ) {
		echo '<p class="mvhc-notice">' . esc_html__( 'No comments scored high enough to suggest automatically. You can still add highlights manually.', 'mavo-highlight-comments' ) . '</p>';
		return;
	}

	echo '<ul class="mvhc-candidate-list">';
	foreach ( $data['candidates'] as $candidate ) {
		$comment = $candidate['comment'];
		$author  = (string) $comment->comment_author;
		$text    = trim( wp_strip_all_tags( (string) $comment->comment_content ) );
		$date    = mysql2date( get_option( 'date_format' ), $comment->comment_date );

		$classes = 'mvhc-candidate' . ( $candidate['high'] ? ' mvhc-candidate--high' : '' );

		echo '<li class="' . esc_attr( $classes ) . '">';
		echo '<div class="mvhc-candidate__meta">';
		echo '<span class="mvhc-candidate__score">' . esc_html__( 'Score:', 'mavo-highlight-comments' ) . ' ' . esc_html( (string) $candidate['score'] ) . '</span> ';
		echo '<span class="mvhc-candidate__author">' . esc_html( $author ) . '</span> ';
		echo '<span class="mvhc-candidate__date">' . esc_html( $date ) . '</span>';
		echo '</div>';

		if ( ! empty( $candidate['reasons'] ) ) {
			echo '<div class="mvhc-candidate__reasons">' . esc_html__( 'Reasons:', 'mavo-highlight-comments' ) . ' ' . esc_html( implode( ', ', $candidate['reasons'] ) ) . '</div>';
		}
		if ( ! empty( $candidate['warnings'] ) ) {
			echo '<div class="mvhc-candidate__warnings">' . esc_html__( 'Warnings:', 'mavo-highlight-comments' ) . ' ' . esc_html( implode( ', ', $candidate['warnings'] ) ) . '</div>';
		}

		echo '<blockquote class="mvhc-candidate__text">' . esc_html( $text ) . '</blockquote>';

		printf(
			'<button type="button" class="button mvhc-candidate__add" data-mvhc-add-candidate data-author="%1$s" data-comment-id="%2$d" data-source-post="%3$d" data-text="%4$s">%5$s</button>',
			esc_attr( mvhc_first_name( $author ) ),
			(int) $comment->comment_ID,
			(int) $fr_post_id,
			esc_attr( $text ),
			esc_html__( 'Add as highlight', 'mavo-highlight-comments' )
		);

		echo '</li>';
	}
	echo '</ul>';
}

/**
 * Extract a display first name from a comment author name.
 *
 * @param string $author Full author name.
 * @return string
 */
function mvhc_first_name( $author ) {
	$author = trim( (string) $author );
	if ( '' === $author ) {
		return '';
	}
	$parts = preg_split( '/\s+/', $author );
	return $parts ? $parts[0] : $author;
}

/**
 * Save handler.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function mvhc_save_highlights( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['mvhc_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mvhc_nonce'] ) ), 'mvhc_save_highlights' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Highlights.
	$raw_highlights = isset( $_POST['mvhc_highlights'] ) ? wp_unslash( $_POST['mvhc_highlights'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per-field in mvhc_sanitize_highlights().
	$highlights     = mvhc_sanitize_highlights( $raw_highlights, $post_id );

	if ( ! empty( $highlights ) ) {
		update_post_meta( $post_id, MVHC_META_HIGHLIGHTS, $highlights );
	} else {
		delete_post_meta( $post_id, MVHC_META_HIGHLIGHTS );
	}

	// Disabled flag.
	if ( ! empty( $_POST['mvhc_disabled'] ) ) {
		update_post_meta( $post_id, MVHC_META_DISABLED, 1 );
	} else {
		delete_post_meta( $post_id, MVHC_META_DISABLED );
	}

	// Overrides.
	$title_override = isset( $_POST['mvhc_title_override'] ) ? sanitize_text_field( wp_unslash( $_POST['mvhc_title_override'] ) ) : '';
	if ( '' !== $title_override ) {
		update_post_meta( $post_id, MVHC_META_TITLE_OVERRIDE, $title_override );
	} else {
		delete_post_meta( $post_id, MVHC_META_TITLE_OVERRIDE );
	}

	$intro_override = isset( $_POST['mvhc_intro_override'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mvhc_intro_override'] ) ) : '';
	if ( '' !== $intro_override ) {
		update_post_meta( $post_id, MVHC_META_INTRO_OVERRIDE, $intro_override );
	} else {
		delete_post_meta( $post_id, MVHC_META_INTRO_OVERRIDE );
	}
}
add_action( 'save_post', 'mvhc_save_highlights' );
