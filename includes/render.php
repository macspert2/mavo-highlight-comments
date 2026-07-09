<?php
/**
 * Frontend rendering of curated translated-comment highlights.
 *
 * The frontend reads a single post-meta array and renders it. No comment
 * queries run here (plan §26).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read stored highlights for a post.
 *
 * @param int  $post_id            Post ID.
 * @param bool $published_only     When true, only published highlights.
 * @return array[] Normalized highlight rows.
 */
function mvhc_get_highlights( $post_id, $published_only = false ) {
	$stored = get_post_meta( (int) $post_id, MVHC_META_HIGHLIGHTS, true );

	if ( ! is_array( $stored ) ) {
		return array();
	}

	$highlights = array();

	foreach ( $stored as $row ) {
		$row = mvhc_normalize_stored_highlight( $row );
		if ( null === $row ) {
			continue;
		}
		if ( $published_only && 'published' !== $row['status'] ) {
			continue;
		}
		if ( '' === $row['translated_text'] ) {
			continue;
		}
		$highlights[] = $row;
	}

	usort(
		$highlights,
		static function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		}
	);

	return $highlights;
}

/**
 * Whether a post has at least one renderable published highlight.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function mvhc_post_has_published_highlights( $post_id ) {
	return array() !== mvhc_get_highlights( $post_id, true );
}

/**
 * Whether the module should render for a post.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function mvhc_should_render_highlights( $post_id ) {
	$post_id = (int) $post_id;

	$should = $post_id
		&& ! get_post_meta( $post_id, MVHC_META_DISABLED, true )
		&& mvhc_language_allows_module( $post_id )
		&& mvhc_post_has_published_highlights( $post_id );

	/**
	 * Final say on whether the module renders.
	 *
	 * @param bool $should  Whether to render.
	 * @param int  $post_id Post ID.
	 */
	return (bool) apply_filters( 'mvhc_should_render_highlights', $should, $post_id );
}

/**
 * Resolve the target language for copy strings.
 *
 * @param int $post_id Post ID.
 * @return string 'en', 'de', 'fr' or '' — falls back to 'en'.
 */
function mvhc_resolve_lang( $post_id ) {
	$lang = mvhc_get_post_language( $post_id );
	$lang = $lang ? strtolower( substr( $lang, 0, 2 ) ) : '';

	if ( ! in_array( $lang, array( 'en', 'de', 'fr' ), true ) ) {
		$lang = 'en';
	}

	return $lang;
}

/**
 * Module title for a language.
 *
 * @param string $lang    Language slug.
 * @param int    $post_id Post ID.
 * @return string
 */
function mvhc_frontend_title( $lang, $post_id ) {
	$override = get_post_meta( $post_id, MVHC_META_TITLE_OVERRIDE, true );

	if ( is_string( $override ) && '' !== trim( $override ) ) {
		$title = $override;
	} else {
		switch ( $lang ) {
			case 'de':
				$title = __( 'Kommentare aus der französischen Version', 'mavo-highlight-comments' );
				break;
			case 'fr':
				$title = __( 'Commentaires issus de la version française', 'mavo-highlight-comments' );
				break;
			default:
				$title = __( 'Reader comments from the French article', 'mavo-highlight-comments' );
		}
	}

	/** Filter the module title. */
	return (string) apply_filters( 'mvhc_frontend_title', $title, $lang, $post_id );
}

/**
 * Module intro paragraph for a language.
 *
 * @param string $lang    Language slug.
 * @param int    $post_id Post ID.
 * @return string
 */
function mvhc_frontend_intro( $lang, $post_id ) {
	$override = get_post_meta( $post_id, MVHC_META_INTRO_OVERRIDE, true );

	if ( is_string( $override ) && '' !== trim( $override ) ) {
		$intro = $override;
	} else {
		switch ( $lang ) {
			case 'de':
				$intro = __( 'Diese Kommentare wurden ursprünglich unter dem französischen Artikel veröffentlicht und ins Deutsche übersetzt.', 'mavo-highlight-comments' );
				break;
			case 'fr':
				$intro = __( 'Ces commentaires ont été sélectionnés depuis l’article d’origine.', 'mavo-highlight-comments' );
				break;
			default:
				$intro = __( 'These comments were originally posted on the French version of this article and translated for English readers.', 'mavo-highlight-comments' );
		}
	}

	/** Filter the module intro. */
	return (string) apply_filters( 'mvhc_frontend_intro', $intro, $lang, $post_id );
}

/**
 * Build the source/attribution line for one highlight.
 *
 * @param array  $highlight Highlight row.
 * @param string $lang      Language slug.
 * @return string Plain text (caller escapes).
 */
function mvhc_source_line( $highlight, $lang ) {
	$anonymous = ( 'anonymous' === $highlight['author_mode'] ) || '' === trim( $highlight['display_author'] );
	$author    = trim( $highlight['display_author'] );

	if ( $anonymous ) {
		switch ( $lang ) {
			case 'de':
				return __( 'Aus einem Kommentar zur französischen Version', 'mavo-highlight-comments' );
			case 'fr':
				return __( 'Extrait d’un commentaire sur la version française', 'mavo-highlight-comments' );
			default:
				return __( 'A reader of the French article', 'mavo-highlight-comments' );
		}
	}

	switch ( $lang ) {
		case 'de':
			/* translators: %s: commenter display name. */
			return sprintf( __( '%s, Kommentar zur französischen Version', 'mavo-highlight-comments' ), $author );
		case 'fr':
			/* translators: %s: commenter display name. */
			return sprintf( __( '%s, commentaire sur la version française', 'mavo-highlight-comments' ), $author );
		default:
			/* translators: %s: commenter display name. */
			return sprintf( __( '%s, reader comment on the French article', 'mavo-highlight-comments' ), $author );
	}
}

/**
 * Render the highlights module for a post.
 *
 * @param int  $post_id Post ID.
 * @param bool $force   Skip the should-render gate (used for admin preview).
 * @return string HTML, or '' when nothing to render.
 */
function mvhc_render_highlights( $post_id, $force = false ) {
	$post_id = (int) $post_id;

	if ( ! $force && ! mvhc_should_render_highlights( $post_id ) ) {
		return '';
	}

	$highlights = mvhc_get_highlights( $post_id, true );

	if ( empty( $highlights ) ) {
		return '';
	}

	$lang  = mvhc_resolve_lang( $post_id );
	$title = mvhc_frontend_title( $lang, $post_id );
	$intro = mvhc_frontend_intro( $lang, $post_id );

	/**
	 * Fires before the module markup is built.
	 *
	 * @param int   $post_id    Post ID.
	 * @param array $highlights Highlights being rendered.
	 */
	do_action( 'mvhc_before_render_highlights', $post_id, $highlights );

	$title_id = 'mvhc-translated-comments-title-' . $post_id;

	ob_start();
	?>
	<section class="mvhc-translated-comments" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
		<div class="mvhc-translated-comments__inner">
			<h2 id="<?php echo esc_attr( $title_id ); ?>" class="mvhc-translated-comments__title">
				<?php echo esc_html( $title ); ?>
			</h2>
			<?php if ( '' !== trim( $intro ) ) : ?>
				<p class="mvhc-translated-comments__intro"><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>

			<div class="mvhc-translated-comments__list">
				<?php foreach ( $highlights as $highlight ) : ?>
					<article class="mvhc-translated-comment">
						<blockquote class="mvhc-translated-comment__quote">
							<?php echo wpautop( esc_html( $highlight['translated_text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped before wpautop. ?>
						</blockquote>
						<p class="mvhc-translated-comment__source">
							<span class="mvhc-translated-comment__dash" aria-hidden="true">—</span>
							<?php echo esc_html( mvhc_source_line( $highlight, $lang ) ); ?>
						</p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
	$html = ob_get_clean();

	/**
	 * Fires after the module markup is built.
	 *
	 * @param int   $post_id    Post ID.
	 * @param array $highlights Highlights being rendered.
	 */
	do_action( 'mvhc_after_render_highlights', $post_id, $highlights );

	return $html;
}

/**
 * Enqueue frontend styles (kept out of the global scope; only when needed).
 *
 * @return void
 */
function mvhc_enqueue_frontend_assets() {
	if ( wp_style_is( 'mvhc-highlight-comments', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_style(
		'mvhc-highlight-comments',
		MVHC_URL . 'assets/css/highlight-comments.css',
		array(),
		MVHC_VERSION
	);
}

/**
 * Shortcode: [mavo_translated_comment_highlights]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function mvhc_render_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'post_id' => 0,
		),
		$atts,
		'mavo_translated_comment_highlights'
	);

	$post_id = (int) $atts['post_id'] ?: (int) get_the_ID();

	$html = mvhc_render_highlights( $post_id );

	if ( '' !== $html ) {
		mvhc_enqueue_frontend_assets();
	}

	return $html;
}
add_shortcode( 'mavo_translated_comment_highlights', 'mvhc_render_shortcode' );

/**
 * Enqueue styles early on singular views that will render the module, so the
 * stylesheet lands in <head> even when the module is placed via the content
 * filter.
 *
 * @return void
 */
function mvhc_maybe_enqueue_on_singular() {
	if ( ! is_singular() ) {
		return;
	}

	$post_id = (int) get_queried_object_id();

	if ( $post_id && mvhc_should_render_highlights( $post_id ) ) {
		mvhc_enqueue_frontend_assets();
	}
}
add_action( 'wp_enqueue_scripts', 'mvhc_maybe_enqueue_on_singular' );

/**
 * Optional automatic insertion after the article content.
 *
 * Off by default (plan §13.1: shortcode is safer for MVP). Enable with:
 *   add_filter( 'mvhc_enable_auto_insert', '__return_true' );
 *
 * @param string $content Post content.
 * @return string
 */
function mvhc_maybe_append_highlights( $content ) {
	/** Enable automatic insertion into the_content. Default false. */
	if ( ! apply_filters( 'mvhc_enable_auto_insert', false ) ) {
		return $content;
	}

	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$post_id = (int) get_the_ID();

	if ( ! mvhc_should_render_highlights( $post_id ) ) {
		return $content;
	}

	// Avoid double output when the shortcode is already present in the content.
	if ( has_shortcode( $content, 'mavo_translated_comment_highlights' ) ) {
		return $content;
	}

	$html = mvhc_render_highlights( $post_id );

	if ( '' === $html ) {
		return $content;
	}

	mvhc_enqueue_frontend_assets();

	return $content . $html;
}
add_filter( 'the_content', 'mvhc_maybe_append_highlights', 20 );
