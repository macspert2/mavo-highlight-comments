=== Mavo Highlight Comments ===
Contributors: mamanvoyage
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Curate and display translated highlights from French reader comments on translated (EN/DE) Maman Voyage articles, as a clearly labelled editorial block.

== Description ==

For a translated post/page (English or German) linked via Polylang to a French
original, this plugin:

* finds the French source post,
* scores and filters its approved reader comments into a candidate shortlist,
* lets an editor select, translate, anonymise, reorder and publish 0–4 highlights
  in a post-edit metabox,
* renders the published highlights on the frontend as a separate, clearly
  labelled module — never mixed into the native WordPress comment thread.

No email, IP, gravatar, or author website URL is ever displayed. No machine
translation APIs are used; translation is manual/editorial.

== Rendering ==

Place the module with the shortcode:

    [mavo_translated_comment_highlights]

Automatic insertion after the article content is available but off by default:

    add_filter( 'mvhc_enable_auto_insert', '__return_true' );

== Key filters ==

* mvhc_candidate_comment_limit
* mvhc_min_candidate_length
* mvhc_candidate_score_threshold
* mvhc_score_comment_candidate
* mvhc_frontend_title
* mvhc_frontend_intro
* mvhc_should_render_highlights
* mvhc_enable_auto_insert
* mvhc_supported_post_types
* mvhc_source_post_id_override

== Actions ==

* mvhc_before_render_highlights
* mvhc_after_render_highlights

== Changelog ==

= 0.1.0 =
* Initial MVP: shortcode + optional auto-insert rendering, admin metabox with
  candidate discovery/scoring, manual translation workflow.
