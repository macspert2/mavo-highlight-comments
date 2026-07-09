# plan-highlight-comments.md

# Maman Voyage — Translated Comment Highlights Plugin Plan

## 0. Purpose

Build a small local WordPress plugin that lets Maman Voyage show a curated selection of useful French reader comments on translated English/German versions of articles in-between the post and the comment section.

The module must **not** copy translated French comments into the native WordPress comment thread of the EN/DE posts. Instead, it should show them as a separate, clearly labelled editorial block, e.g.:

- English: `Reader comments from the French article`
- German: `Kommentare aus der französischen Version`

The goal is to preserve the warmth and social proof of old French posts while staying transparent, privacy-conscious, and editorially controlled.

Additional functionality to be prepared for and added later: show one special highlighted comment in-between the post and the comment section as an 'editor selected comment' even on the same language post (i.e. a French comment can be shown in this section as highlighted even directly on the French post.

---

## 1. Project context and constraints

Maman Voyage is a French family travel blog by Christine. The main content is French, with smaller English and German translated sections. WordPress is used with GeneratePress Lite, a child theme `mavo26-child`, and Polylang for multilingual linking.

Important project rules:

- Do not edit GeneratePress or third-party plugin files directly.
- Controlled code belongs in the child theme and/or `mavo-*` plugins.
- This feature should be implemented as a new controlled plugin, preferably:

```text
wp-content/plugins/mavo-highlight-comments/
```

- Keep data local to the WordPress site.
- Do not use an external SaaS or hosted comment/translation service.
- Do not automatically publish all French comments on translated pages.
- Do not create real WordPress comments on EN/DE posts from translated French comments.
- Treat translated comment highlights as editorial excerpts, not native comments.

---

## 2. Recommended plugin name

Directory:

```text
wp-content/plugins/mavo-highlight-comments/
```

Main file:

```text
wp-content/plugins/mavo-highlight-comments/mavo-highlight-comments.php
```

Plugin header:

```php
<?php
/**
 * Plugin Name: Mavo Highlight Comments
 * Description: Curate and display translated highlights from French comments on translated Maman Voyage articles.
 * Version: 0.1.0
 * Author: Maman Voyage
 * Text Domain: mavo-highlight-comments
 */
```

Use the prefix:

```text
mvhc_
```

or:

```text
mavo_highlight_comments_
```

Do not use generic function names.

---

## 3. What this plugin should do

For a translated post/page in English or German:

1. Find the linked French original via Polylang.
2. Pull approved comments from the French original.
3. Filter out poor candidates automatically.
4. Score remaining comments for usefulness.
5. Show an admin metabox with candidate comments.
6. Allow the editor to select, translate, edit, anonymize, reorder, and publish 0–4 highlights.
7. Render the selected highlights on the EN/DE frontend as a separate module.

The plugin should have three layers:

```text
Candidate discovery:
  Read approved French comments and score/filter them.

Editorial selection:
  Admin metabox where selected highlights are stored on the translated post.

Frontend rendering:
  Display curated translated highlights in a clearly labelled module.
```

---

## 4. What this plugin must not do

Do not:

- Bulk-translate every comment.
- Import translated comments into `wp_comments` as if they were native EN/DE comments.
- Preserve author email/IP/gravatar on translated pages.
- Display commenter website links.
- Display raw comment URLs unless manually approved.
- Display spammy or commercial author names.
- Show the module if no comments have been selected and approved.
- Add heavy assets globally.
- Depend on external translation APIs for the first version.

---

## 5. Data model

Store curated highlights as post meta on the translated post.

Meta key:

```text
_mvhc_highlights
```

Store as JSON-serializable array or plain serialized array via `update_post_meta()`. WordPress can serialize arrays automatically. Prefer a structured PHP array.

Example:

```php
[
    [
        'source_comment_id' => 12345,
        'source_post_id'    => 678,
        'source_lang'       => 'fr',
        'target_lang'       => 'en',
        'display_author'    => 'Marie',
        'author_mode'       => 'first_name', // first_name|anonymous|custom
        'translated_text'   => 'We followed this itinerary with our two children and loved the stop in Cassis.',
        'editor_note'       => '',
        'status'            => 'published', // draft|published
        'order'             => 1,
    ],
]
```

Additional post meta:

```text
_mvhc_disabled
```

Possible values:

```text
0 or empty = enabled if highlights exist
1 = never show module for this translated post
```

Optional later meta:

```text
_mvhc_admin_note
_mvhc_last_candidate_refresh
_mvhc_source_post_id_override
```

---

## 6. Plugin file structure

Recommended structure:

```text
mavo-highlight-comments/
  mavo-highlight-comments.php
  includes/
    admin.php
    candidates.php
    render.php
    polylang.php
    sanitization.php
    scoring.php
  assets/
    css/
      highlight-comments.css
    js/
      admin.js
  languages/
```

### File responsibilities

#### `mavo-highlight-comments.php`

- Plugin bootstrap.
- Define constants.
- Load includes.
- Register hooks.

#### `includes/polylang.php`

- Helpers for current language and French original lookup.
- Must gracefully degrade if Polylang is inactive.

#### `includes/candidates.php`

- Fetch approved comments from French source post.
- Exclude unsuitable comments.
- Return candidate objects/arrays with score and reason labels.

#### `includes/scoring.php`

- Score comments according to usefulness heuristics.
- Add positive/negative scoring signals.

#### `includes/admin.php`

- Add metabox to post edit screen.
- Render candidate list and selected highlights editor.
- Save selected highlights.
- Nonce and capability checks.

#### `includes/render.php`

- Render frontend block.
- Shortcode/block/filter integration.

#### `includes/sanitization.php`

- Sanitize and validate saved highlight data.

#### `assets/css/highlight-comments.css`

- Frontend styles for translated comment module.
- Use Mavo visual language: soft cards, subtle shadows, rounded corners.

#### `assets/js/admin.js`

- Optional admin convenience: reorder highlights, copy original text, expand/collapse candidate comments.
- Avoid dependency-heavy JS.

---

## 7. Polylang integration

Need a helper to find the French source post for an EN/DE translated post.

Pseudo-code:

```php
function mvhc_get_current_language($post_id = 0) {
    if (function_exists('pll_get_post_language')) {
        return pll_get_post_language($post_id ?: get_the_ID());
    }
    return '';
}

function mvhc_get_french_source_post_id($post_id) {
    if (!function_exists('pll_get_post')) {
        return 0;
    }

    $lang = pll_get_post_language($post_id);

    if ($lang === 'fr') {
        return 0;
    }

    $fr_id = pll_get_post($post_id, 'fr');
    return $fr_id ? (int) $fr_id : 0;
}
```

Rules:

- If current post is French: no module.
- If no linked French original exists: no module.
- If French original has fewer than a minimum number of approved comments: still allow manual highlights if they exist, but do not show candidates by default.

---

## 8. Candidate discovery

Fetch candidate comments from the French source post.

Base query:

```php
$comments = get_comments([
    'post_id' => $fr_original_id,
    'status'  => 'approve',
    'type'    => 'comment',
    'number'  => 100,
    'orderby' => 'comment_date_gmt',
    'order'   => 'DESC',
]);
```

Consider supporting a `number` filter:

```php
apply_filters('mvhc_candidate_comment_limit', 100, $fr_original_id, $target_post_id);
```

Do not include:

- spam comments;
- trash comments;
- unapproved comments;
- pingbacks/trackbacks;
- comments by obvious commercial authors;
- comments containing URLs, unless later manually allowed;
- comments with email addresses or phone numbers in the body;
- comments shorter than a configurable threshold;
- comments that are only generic thanks;
- comments with too much private information;
- comments that depend heavily on outdated details.

---

## 9. Candidate filtering rules

Implement a function:

```php
function mvhc_is_comment_candidate($comment, $context = []) {
    // returns true/false, possibly with reason codes
}
```

Suggested checks:

### 9.1 Minimum length

Default minimum:

```text
120 characters
```

But allow filter:

```php
$min_length = apply_filters('mvhc_min_candidate_length', 120, $comment);
```

### 9.2 Maximum length for candidate shortlist

Very long comments can be selected manually, but they should be downranked.

Default ideal range:

```text
200–900 characters
```

### 9.3 Exclude URLs in body

Regex:

```php
preg_match('/https?:\/\/|www\./i', $text)
```

### 9.4 Exclude personal contact info

Basic detection:

```php
$email_found = preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text);
$phone_found = preg_match('/(\+\d{1,3}[\s.-]?)?(\(?\d{2,4}\)?[\s.-]?){3,}/', $text);
```

Be conservative; if detected, exclude from automatic candidates.

### 9.5 Commercial author names

Function:

```php
function mvhc_comment_author_looks_commercial($author) {
    $bad_patterns = [
        'voyage', 'travel', 'circuit', 'agence', 'hotel', 'hôtel',
        'visa', 'taxi', 'excursion', 'tour', 'guide', 'seo',
        'assurance', 'location', 'immobilier', 'discount'
    ];
}
```

For Maman Voyage specifically, be careful because `voyage` appears in normal language. Use this as a negative score rather than absolute exclusion unless the name is clearly commercial, e.g. `voyage Vietnam`, `circuit vietnam cambodge 3 semaines`.

### 9.6 Generic thanks

Downrank or exclude comments matching patterns like:

```text
merci
merci pour cet article
super article
très intéressant
bravo
```

If under 150 characters and mostly thanks, exclude from shortlist.

### 9.7 Christine replies

Replies from Christine can be useful if they contain practical information. But for first version:

- Include reader comments only by default.
- Optionally show Christine replies in admin as context, not candidates.

Later enhancement: allow paired Q&A highlights.

---

## 10. Candidate scoring

Implement:

```php
function mvhc_score_comment_candidate($comment, $context = []) {
    return [
        'score' => 0,
        'reasons' => [],
        'warnings' => [],
    ];
}
```

### Positive signals

Suggested scoring:

```text
+30 mentions children/family context
+25 includes practical feedback or a tip
+25 says they followed/tested the itinerary
+20 mentions a specific destination/place from the post
+15 mentions transport, accommodation, route, budget, stroller, baby, teens
+10 has good length: 200–900 characters
+10 author name looks like a real personal name
+10 comment has replies/useful discussion context
```

French pattern examples:

```php
$family_patterns = '/enfant|enfants|bébé|ados|ado|poussette|famille|filles|garçons|petit|grands/i';
$practical_patterns = '/itinéraire|testé|suivi|adoré|conseil|pratique|transport|train|voiture|hôtel|logement|restaurant|adresse|budget|parking|visite|balade|randonnée/i';
$validation_patterns = '/nous avons suivi|nous avons testé|on a testé|on a suivi|grâce à vous|vos conseils|nous sommes allés|nous avons adoré/i';
```

### Negative signals

Suggested scoring:

```text
-20 contains URL
-30 commercial-looking author
-20 mostly generic thanks
-30 likely outdated practical detail
-50 contains personal contact info
-30 contains strong complaint about a named third party
-20 very short
-10 very long
```

Outdated-detail patterns:

```php
$outdated_patterns = '/\b\d+\s?€|francs|en 20\d\d|tarif|prix|horaires|fermé|ouvert|navette|billet/i';
```

Do not automatically exclude all outdated patterns; add warning and downrank. Some may still be useful after editorial review.

### Candidate thresholds

Default:

```text
score >= 25 = show in admin candidate shortlist
score >= 50 = high-quality candidate
```

Allow filters:

```php
apply_filters('mvhc_candidate_score_threshold', 25, $target_post_id, $fr_original_id);
```

---

## 11. Admin metabox

Add a metabox to post edit screens.

Register only for posts/pages where useful:

```php
add_action('add_meta_boxes', 'mvhc_register_metabox');
```

Suggested placement:

```text
side or normal area
```

For usability, `normal` area is better because candidate comments can be long.

Title:

```text
Commentaires traduits depuis la version française
```

or in English admin:

```text
Translated comment highlights
```

### 11.1 When to show metabox

Show if:

- post type is `post` or `page`;
- Polylang exists;
- current post language is not French;
- linked French original exists.

If no French original:

```text
No linked French original found for this translated post.
```

If French original exists but no comments:

```text
The French original has no approved comments available for highlights.
```

### 11.2 Metabox sections

Suggested admin UI:

```text
1. Source information
2. Display settings
3. Selected highlights
4. Suggested candidates from French comments
```

#### Source information

Show:

```text
French source post: [title] [Edit] [View]
Approved French comments: 36
Current language: English
Selected highlights: 3
```

#### Display settings

Fields:

```text
[ ] Disable translated comment highlights on this post
Module title override
Intro text override
```

For first version, title/intro override can be optional. Default strings based on language are enough.

#### Selected highlights

For each selected highlight:

```text
Source comment ID
Display author
Translated text textarea
Status: draft/published
Order
Remove button
```

Fields:

```php
mvhc_highlights[0][source_comment_id]
mvhc_highlights[0][display_author]
mvhc_highlights[0][author_mode]
mvhc_highlights[0][translated_text]
mvhc_highlights[0][status]
mvhc_highlights[0][order]
```

#### Suggested candidates

For each candidate:

```text
Score: 72
Reasons: children/family, practical tip, followed itinerary
Warnings: contains old price
Author: Marie
Date: 12 août 2016
Original French text
[Add as highlight]
```

For version 1, the `Add as highlight` button can simply reveal/copy into a selected highlight form row via JS, or the editor can copy manually. A manual workflow is acceptable for MVP.

---

## 12. Admin save handling

Use nonce:

```php
wp_nonce_field('mvhc_save_highlights', 'mvhc_nonce');
```

Save hook:

```php
add_action('save_post', 'mvhc_save_highlights');
```

Guard clauses:

```php
if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
if (!isset($_POST['mvhc_nonce']) || !wp_verify_nonce(...)) return;
if (!current_user_can('edit_post', $post_id)) return;
```

Sanitize:

- `source_comment_id`: absint
- `source_post_id`: absint
- `source_lang`: sanitize_key
- `target_lang`: sanitize_key
- `display_author`: sanitize_text_field
- `author_mode`: enum whitelist
- `translated_text`: wp_kses_post or stricter plain text
- `editor_note`: sanitize_textarea_field
- `status`: enum whitelist
- `order`: absint

For translated text, prefer plain text with allowed inline emphasis only. First version can strip all HTML:

```php
$translated_text = wp_strip_all_tags(wp_unslash($raw));
```

---

## 13. Frontend rendering

The module should render only when:

```text
- current post language is EN or DE;
- `_mvhc_disabled` is not true;
- `_mvhc_highlights` contains at least one item with status = published;
- translated text is non-empty.
```

### 13.1 Placement options

Support both:

1. Automatic insertion via content filter.
2. Shortcode for manual placement.

Shortcode:

```text
[mavo_translated_comment_highlights]
```

Automatic insertion can be enabled later. For MVP, shortcode/manual placement is safer.

If automatic insertion is implemented, hook into `the_content` only on singular EN/DE posts/pages:

```php
add_filter('the_content', 'mvhc_maybe_append_highlights');
```

Default placement:

```text
after article content, before native comments section
```

But this may depend on theme structure. Shortcode is safer first.

### 13.2 Default frontend copy

English:

```text
Reader comments from the French article
These comments were originally posted on the French version of this article and translated for English readers.
```

German:

```text
Kommentare aus der französischen Version
Diese Kommentare wurden ursprünglich unter dem französischen Artikel veröffentlicht und ins Deutsche übersetzt.
```

French should not show module, but if needed for admin preview:

```text
Commentaires issus de la version française
Ces commentaires ont été sélectionnés depuis l’article d’origine.
```

### 13.3 Frontend markup

```php
<section class="mvhc-translated-comments" aria-labelledby="mvhc-translated-comments-title">
  <div class="mvhc-translated-comments__inner">
    <h2 id="mvhc-translated-comments-title" class="mvhc-translated-comments__title">
      Reader comments from the French article
    </h2>
    <p class="mvhc-translated-comments__intro">
      These comments were originally posted on the French version of this article and translated for English readers.
    </p>

    <div class="mvhc-translated-comments__list">
      <article class="mvhc-translated-comment">
        <blockquote class="mvhc-translated-comment__quote">
          <p>We followed this itinerary with our two children...</p>
        </blockquote>
        <p class="mvhc-translated-comment__source">
          — Marie, reader comment on the French article
        </p>
      </article>
    </div>
  </div>
</section>
```

### 13.4 Author/source line

English:

```text
— Marie, reader comment on the French article
```

German:

```text
— Marie, Kommentar zur französischen Version
```

Anonymous fallback:

English:

```text
— A reader of the French article
```

German:

```text
— Eine Leserin/ein Leser der französischen Version
```

Prefer inclusive/generic German if desired:

```text
— Aus einem Kommentar zur französischen Version
```

This avoids gendered wording.

---

## 14. Frontend CSS

Use the existing Mavo visual language: soft white card, rounded corners, subtle shadow, warm editorial tone.

Create:

```text
assets/css/highlight-comments.css
```

Only enqueue when module is needed or shortcode is present.

Base CSS:

```css
.mvhc-translated-comments {
  margin: 2rem 0;
}

.mvhc-translated-comments__inner {
  background: var(--mv-color-surface, #fff);
  border: 0;
  border-radius: var(--mv-tile-radius, 14px);
  box-shadow: var(--mv-tile-shadow, 0 8px 22px rgba(58, 58, 58, 0.08));
  padding: clamp(1.25rem, 3vw, 2rem);
}

.mvhc-translated-comments__title {
  margin-top: 0;
  margin-bottom: .4rem;
  color: var(--mv-color-primary, #4e74a5);
}

.mvhc-translated-comments__intro {
  margin-top: 0;
  margin-bottom: 1.25rem;
  color: var(--mv-color-muted, #555555);
}

.mvhc-translated-comments__list {
  display: grid;
  gap: 1rem;
}

.mvhc-translated-comment {
  border-left: 4px solid var(--mv-color-highlight, #a92d87);
  background: var(--mv-color-bg-cream, #f7f4ef);
  border-radius: 12px;
  padding: 1rem 1.1rem;
}

.mvhc-translated-comment__quote {
  margin: 0;
}

.mvhc-translated-comment__quote p {
  margin-top: 0;
}

.mvhc-translated-comment__source {
  margin-bottom: 0;
  color: var(--mv-color-muted, #555555);
  font-size: .92rem;
}
```

Avoid large decorative quotation marks unless they are accessible and not noisy.

---

## 15. Asset loading

Do not load CSS globally if avoidable.

MVP options:

### Option A — enqueue on all singular EN/DE posts/pages

Simple and acceptable if CSS is tiny.

```php
if (is_singular() && mvhc_post_has_published_highlights(get_the_ID())) {
    wp_enqueue_style(...);
}
```

### Option B — shortcode-aware enqueue

If using shortcode only, enqueue during shortcode render.

```php
function mvhc_render_shortcode($atts) {
    wp_enqueue_style('mvhc-highlight-comments');
    return mvhc_render_highlights(get_the_ID());
}
```

Do not enqueue admin JS/CSS outside post edit screens where metabox appears.

---

## 16. Translation management

For MVP, translation is manual/editorial.

Do not call machine translation APIs.

Admin UI should show:

```text
Original French comment
Translated text textarea
```

The editor can paste a translation created elsewhere and edit it.

Possible later feature:

```text
Add “suggest translation” button using a local/admin-only workflow.
```

But do not implement external API dependency in MVP.

---

## 17. Privacy and trust rules

The module must make clear:

- these are comments from the French article;
- they were translated for the target-language reader;
- they are not native EN/DE comments.

Do not display:

- email addresses;
- IP addresses;
- avatars/gravatars;
- website links;
- comment author URLs;
- full names if unnecessary.

Default author display should use the existing public comment author display name, but allow anonymization.

If author is commercial-looking, default to anonymous or skip.

---

## 18. Security

Admin save:

- nonce check;
- capability check;
- autosave/revision guard;
- strict sanitization.

Frontend:

- escape all output;
- translated text should be plain text or very limited markup;
- no raw comment HTML from source comments;
- no author URL output.

Candidate discovery:

- never expose unapproved/spam/trash comments;
- do not expose email/IP metadata.

---

## 19. Accessibility

Frontend:

- Use `<section>` with `aria-labelledby`.
- Use real heading level appropriate to article context, probably `<h2>`.
- Use `<blockquote>` for comment text.
- Do not use icon-only controls.
- Ensure contrast meets site standards.
- Ensure no hidden/focusable content.

Admin:

- Labels for all fields.
- Textareas associated with source comment IDs.
- Keyboard-accessible reorder controls if reorder is implemented.

---

## 20. Hooks and filters for future flexibility

Add filters:

```php
apply_filters('mvhc_candidate_comment_limit', 100, $fr_original_id, $target_post_id);
apply_filters('mvhc_min_candidate_length', 120, $comment);
apply_filters('mvhc_candidate_score_threshold', 25, $target_post_id, $fr_original_id);
apply_filters('mvhc_score_comment_candidate', $score_data, $comment, $context);
apply_filters('mvhc_frontend_title', $title, $target_lang, $post_id);
apply_filters('mvhc_frontend_intro', $intro, $target_lang, $post_id);
apply_filters('mvhc_should_render_highlights', $should_render, $post_id);
```

Add actions:

```php
do_action('mvhc_before_render_highlights', $post_id, $highlights);
do_action('mvhc_after_render_highlights', $post_id, $highlights);
```

---

## 21. MVP scope

Implement in this order.

### Phase 1 — plugin skeleton and frontend shortcode

- Create plugin structure.
- Register shortcode `[mavo_translated_comment_highlights]`.
- Read `_mvhc_highlights` from current post.
- Render published highlights.
- Add frontend CSS.
- No admin candidate UI yet.

Manual test: add `_mvhc_highlights` manually via code or custom field and confirm rendering.

### Phase 2 — admin metabox for selected highlights

- Add metabox on EN/DE posts/pages linked to FR original.
- Allow editor to add/edit/delete/reorder highlight rows.
- Save sanitized highlight data.
- Render module from saved data.

### Phase 3 — candidate discovery and scoring

- Fetch approved French comments.
- Filter unsuitable comments.
- Score candidates.
- Show suggested candidates in metabox with score/reasons/warnings.

### Phase 4 — admin convenience

- Add JS to copy candidate into selected highlight row.
- Add reorder controls.
- Add anonymize toggle.
- Add status draft/published.

### Phase 5 — rollout and refinement

- Apply to 5 translated posts.
- Review frontend quality.
- Adjust scoring rules.
- Expand cautiously.

---

## 22. Testing checklist

### 22.1 Source/translation relationship

Test:

```text
FR post with comments → EN translated post
FR post with comments → DE translated post
EN/DE post without FR source
FR post itself
```

Expected:

```text
FR post: module does not render.
EN/DE with source and selected highlights: module renders.
EN/DE without source: no metabox candidates, no frontend module.
EN/DE with no selected highlights: no frontend module.
```

### 22.2 Candidate filtering

Create or find comments with:

```text
short generic thanks
URL in body
commercial author name
family/practical useful detail
old price information
email address
phone number
```

Expected:

```text
Useful practical comments appear high in candidates.
Spammy/commercial comments are excluded or heavily downranked.
Personal contact info comments are excluded.
Old price comments are warned/downranked.
```

### 22.3 Admin save

Test:

```text
save post
autosave
revision
user without edit capability
nonce missing
```

Expected:

```text
Only valid authorized save updates meta.
Autosave/revisions do not overwrite highlights.
```

### 22.4 Frontend output

Check:

```text
HTML escapes correctly.
No author URLs are output.
No emails/IPs/gravatars appear.
No broken styling.
No module if disabled.
No module if all highlights are draft.
```

### 22.5 Multilingual copy

Check English and German strings.

Expected:

```text
EN page: English title/intro/source line.
DE page: German title/intro/source line.
```

### 22.6 Accessibility

Check:

```text
Heading is meaningful.
Blockquote structure is sane.
Keyboard focus does not enter hidden controls.
Admin fields have labels.
```

---

## 23. Rollout plan

Start with a small pilot.

Recommended first rollout:

```text
5 translated posts with high-value French comments
max 3 highlights per post
manual translations reviewed by Christine/editor
```

For each pilot post:

1. Open EN/DE post edit screen.
2. Review candidate list.
3. Select 2–3 useful comments.
4. Translate and edit for clarity.
5. Anonymize questionable author names.
6. Publish module.
7. Check frontend on desktop/mobile.

After pilot:

- Review whether module adds trust/usefulness.
- Remove/adjust if it feels noisy.
- Tune scoring.
- Decide whether to expand to more translated posts.

---

## 24. Styling integration with article UI system

The translated-comment module should follow the same visual language as other new article utility modules:

- TOC card;
- `Infos pratiques` card;
- related-region card;
- comment highlight card.

Use shared tokens if present:

```css
--mv-color-primary: #4e74a5;
--mv-color-warm: #886353;
--mv-color-highlight: #a92d87;
--mv-color-bg-cream: #f7f4ef;
--mv-color-bg-soft: #f0eee9;
--mv-tile-radius: 14px;
--mv-tile-shadow: 0 8px 22px rgba(58, 58, 58, 0.08);
```

Do not create a completely separate visual style.

---

## 25. SEO notes

These highlights should be normal visible content if selected editorially and useful.

However:

- do not output long translated comment archives;
- keep limit low: 2–4 comments;
- avoid low-quality/spammy comment text;
- do not output author URLs;
- if any link from a comment is ever allowed, add `rel="ugc nofollow"`.

Do not add structured data for translated comments in MVP.

---

## 26. Performance notes

Frontend should read one post meta array and render it. No comment queries should run on the public frontend unless no cached highlights exist and a deliberate preview mode is active.

Admin candidate discovery can query comments because it runs only on edit screens.

If source posts have hundreds of comments, limit candidate query to 100–200 and paginate/refresh later if needed.

---

## 27. Possible future enhancements

Do not implement these in MVP unless specifically requested:

- Bulk admin screen listing translated posts with candidate counts.
- Machine translation suggestion workflow.
- Pair reader question + Christine answer highlights.
- Per-post analytics for module clicks/engagement.
- Automatic insertion into specific article templates.
- Admin preview modal.
- REST endpoint for candidate refresh.
- Export/import selected highlights.

---

## 28. Grep commands for coding agent

Find existing comment templates and article hooks:

```bash
grep -RInE "comments_template|wp_list_comments|comment_form|the_content|single.php|content-single|entry-content" \
  wp-content/themes/mavo26-child wp-content/plugins/mavo-*
```

Find Polylang usage:

```bash
grep -RInE "pll_get_post|pll_get_post_language|pll_current_language|Polylang" \
  wp-content/themes/mavo26-child wp-content/plugins/mavo-*
```

Find existing utility card styles:

```bash
grep -RInE "mv-toc|mv-tile|mv-card|mv-.*utility|mv-color-highlight|mv-tile-shadow" \
  wp-content/themes/mavo26-child wp-content/plugins/mavo-*
```

---

## 29. Acceptance criteria

The feature is complete when:

```text
- A new controlled plugin `mavo-highlight-comments` exists and can be activated/deactivated safely.
- EN/DE translated posts linked to a French original show an admin metabox.
- The metabox shows candidate French comments scored and filtered.
- Editors can select, translate, anonymize, reorder, draft/publish, and save comment highlights.
- Frontend renders only selected published highlights.
- Highlights are clearly labelled as translated comments from the French article.
- No translated highlights are inserted into native WordPress comment threads.
- No email, IP, gravatar, or author website URL is displayed.
- Styling matches Maman Voyage's modern soft-card system.
- The module does not render on French originals or posts without selected highlights.
- Plugin deactivation removes the module without breaking posts.
```

---

## 30. Summary recommendation

Build this as a careful editorial tool, not as an automation engine.

The plugin should help Christine find and reuse the most helpful old French reader comments, but every public highlight should be explicitly selected and reviewed before appearing on English/German posts.

