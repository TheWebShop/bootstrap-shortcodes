# Migrating off Bootstrap Shortcodes

The goal is a site that renders the same content with this plugin deactivated and
deleted. There are three steps, all on **Tools > Bootstrap Shortcodes**:

1. **Scan** to learn what depends on the plugin (see the report's "If you deactivated
   this plugin today" and "Not scanned" sections; a clean post scan is not proof).
2. **Migrate** post content: preview one post with a diff, migrate it, or select
   posts and migrate in batches. Originals are kept for rollback.
3. **Decide about Bootstrap itself**: either nothing else needs it (self-contained
   styling, theme has its own), or install the legacy-assets companion.

Then deactivate the plugin, check the pages the report listed, and delete it.

## What the migration does and does not touch

- Rewrites **post content** only (every post type and status the scan covers).
  Widgets, options, custom fields, term descriptions and theme files are reported,
  not rewritten: edit those by hand using the same mappings below.
- Reads each post fresh at migration time, so a post edited after the scan is
  converted from its current content. A single-post preview is refused if the post
  changed after the preview was made.
- Stores the original content in the custom field `_bs_migration_original` (first
  migration only; later migrations of the same post never overwrite it), and
  `wp_update_post()` records a revision as usual. **Roll back** restores the stored
  original and removes the custom field. Rollback does not depend on revisions.
- Leaves in place, and lists: occurrences whose opening and closing tags do not match
  (an unclosed `[bs_well]`), escaped `[[bs_x]]` examples, `bs_` tags the plugin
  never registered (typos, other plugins), and block-level components (row, column,
  well, alert, lead, collapse, tabs) written *inside* a paragraph, heading or list
  item of a block post, where a `<div>` would break the block. For a paragraph,
  put the shortcode in its own paragraph (or a Shortcode block) and migrate again;
  inside a list, move it out of the list block or convert that list by hand. Nothing in a post is dropped silently;
  every approximation is reported as a note in the preview and stored with the post.
- Never runs on activation, update or a schedule. Needs `manage_options`, a nonce
  and a button press (or `edit_post` for each post).

## Output options

| Option | Values | Meaning |
| --- | --- | --- |
| Output | auto / blocks / HTML | `blocks` writes core block markup (Columns, Buttons, Details, Paragraph, Group, Custom HTML, Classic). `HTML` writes plain markup with no block comments, for classic-editor sites. `auto` picks blocks for posts that already contain blocks, otherwise HTML. |
| Styling | self-contained / Bootstrap classes | `self-contained` adds inline styles (wells, alerts, labels, lead, tooltips) and inline SVG icons so nothing needs Bootstrap CSS. `Bootstrap classes` keeps only the original class names, for sites that keep Bootstrap 3 via the theme or the companion. |

Block output is checked two ways: it round-trips through core's PHP block
parser/serializer losslessly (`MigratorConvertTest::test_block_mode_output_is_what_the_editor_would_save`,
which proves the delimiters and attributes are well formed), and the Playwright
run opens migrated posts in the block editor and asserts that no block shows the
"unexpected or invalid content" warning (`specs/migration.spec.js`), which is the
editor's own JavaScript validation of each block's saved HTML.

## Component matrix

WP = minimum WordPress version for the block destination; the HTML destination works
everywhere. "Keeps" lists what is preserved; "Changes" what is visibly or
behaviourally different; "Needs" what the output still depends on.

| Shortcode | Blocks destination | HTML destination | WP | Keeps | Changes | Needs |
| --- | --- | --- | --- | --- | --- | --- |
| `bs_row` + `bs_col` | Columns > Column, width from the first `col-*-N` class (N/12 as %); a `bs_col` outside any `bs_row` gets its own Columns block (noted) | `<div class="row"><div class="col-sm-6">` unchanged (a custom `class` on `bs_row` replaces `row`, as the shortcode did) | 5.0 | content, nesting, column proportions, extra classes (as block className) | responsive breakpoints become the Columns block's own (stack on mobile); `offset`/`push`/`pull` dropped and noted; theme spacing | HTML output needs Bootstrap CSS |
| `bs_button` standalone | Buttons > Button with url/text/target (`rel="noopener noreferrer"` for `_blank`) | `<a class="btn btn-primary">` identical to today | 5.4 | text, link, target | theme button style instead of Bootstrap colours/sizes (noted when a `type` or `size` was set) | HTML output needs Bootstrap CSS |
| `bs_button` inline in a sentence | stays an inline `<a class="btn ...">` (noted) | same | – | everything | – | Bootstrap CSS |
| `bs_well` | self-contained: Custom HTML block with inline well styling; classes: Group block with `well` class | `<div class="well" style="...">` or `<div class="well">` | 5.0 | content (paragraphs baked in for the HTML block) | editable as HTML rather than blocks (self-contained); small sizing differences | classes variant needs Bootstrap CSS |
| `bs_notification` | same shape as wells with alert colours and `role="alert"` (the classes variant's Group block cannot store `role`, so it is dropped there, noted) | same | 5.0 | message, type colour | **dismiss (×) button removed** (needs Bootstrap JS), noted | classes variant needs Bootstrap CSS |
| `bs_lead` | Paragraph with `lead` class and the theme's `large` font size | `<p class="lead" style="font-size:1.3em;font-weight:300">` | 5.0 | text | exact size follows the theme | classes variant needs Bootstrap CSS |
| `bs_label` | inline `<span class="label label-x">` with inline colours | same | – | text, type colour | – | classes variant needs Bootstrap CSS |
| `bs_icon` | inline SVG built from the bundled Glyphicons font (`<svg class="bs-icon bs-icon-star">`, `currentColor`, 1em) | same | – | the glyph, size relative to text, extra classes | none visible; unknown glyph names keep `<i class="glyphicon ...">` and are noted; old `icon-*` sprite names keep their `<i>` and are noted | classes variant, or unknown glyphs, need the Glyphicons font (Bootstrap CSS); `icon-*` names need the plugin's `shortcodes.css` sprite, which the companion ships |
| `bs_tooltip` | `<span class="bs-tooltip" title="...">` (dotted underline when self-contained) | same | – | the text and the tooltip text (they were always identical) | native browser tooltip: no placement/trigger, none on touch devices; noted | – |
| `bs_collapse` + `bs_citem` | one Details block per item; items with a `parent` (what the editor button writes) share `name` = that parent so one opens at a time (the block's `name` needs WP 6.8+; on 6.3 to 6.7 it is left out and the lost grouping is noted; below 6.3 each item is a Custom HTML block holding a `<details>`), items without one stay independent, as Bootstrap's `data-parent` worked; `open` for `open="true"`, element `id` kept so `#anchor` links work | `<div class="bs-collapse"><details name><summary>` | 6.3 (Details block); HTML works everywhere | titles, content, initial open state, one-at-a-time behaviour, anchor ids | Bootstrap panel chrome gone (theme styles the Details block); keyboard: native `<summary>` | – |
| `bs_tabs` + `bs_thead` + `bs_tab` + `bs_dropdown` + `bs_tcontents` + `bs_tcontent` | one Details block per pane, titled by the matching tab (`href="#id"` ↔ `id`), active pane open; dropdown tabs flattened as "Dropdown: Tab" | `<div class="bs-tabs"><details>` per pane | 6.3 / any | every pane's content, titles, order, initial pane, anchor ids | **tabs become stacked collapsible sections** (semantic alternative, noted). On WordPress 7.1+ the editor's Tabs block can be built from them by hand if tabs are wanted back | – |

Attributes the plugin never used (anything not in the table above, such as a
`color=` on a well) are dropped with a note; they never rendered anything.

Why not the core Accordion (6.9) and Tabs (7.1) blocks directly? Both are
Interactivity-API blocks whose saved markup must match the editor's exactly; hand
written markup that drifts makes the editor flag every migrated post as invalid.
Details is a static block with a stable save format and a `name` attribute, so it is
the reliable automatic destination. Converting Details to Accordion or Tabs is a
manual, per-post editor action.

## Keeping Bootstrap 3 after removal: the companion

If the report shows "Still needs: Bootstrap 3 CSS" on migrated posts, or your theme or
hand-written HTML uses Bootstrap 3 classes, or you use `[bs_icon]` names that are not
in the bundled set, the site still needs Bootstrap 3 after this plugin is gone.

**Bootstrap 3 Legacy Assets** is a separate, minimal plugin (`companion/` in this
repository; the migration page builds its zip from this plugin's own files). It:

- loads the same Bootstrap 3.4.1 CSS (with this plugin's `shortcodes.css` and its
  `icon-*` sprite), JS, Glyphicons font and tooltip initialiser this plugin loads,
  with three on/off switches (Settings > Legacy Bootstrap);
- registers **no** shortcodes and has no other behaviour;
- stays idle while Bootstrap Shortcodes is active and loading its assets on every
  page, and skips its CSS or JS when the theme (or Bootstrap Shortcodes itself)
  registers a `bootstrap.css` / `bootstrap.js`, so nothing loads twice. With
  Bootstrap Shortcodes' conditional loading on, the companion covers the pages
  without shortcodes and Bootstrap Shortcodes covers the rest;
- is installed only from the migration page after ticking an explicit statement, or
  by uploading the downloaded zip yourself. Installed from the migration page, its
  settings start as copies of this plugin's CSS/JS settings; uploaded by hand, it
  starts with its own defaults (CSS, JS and tooltips on), so check Settings > Legacy
  Bootstrap.

Be honest with yourself about what that means: the site no longer depends on
Bootstrap Shortcodes, but it still depends on **end-of-life Bootstrap 3** (see
`docs/security.md`). Treat the companion as a bridge to be removed once the remaining
markup is migrated.

## Verifying before you delete

The repository's Playwright suite (`specs/migration.spec.js`) does this end to end
in a test site: scan, preview, migrate, roll back, migrate again in a batch, install
the companion, **deactivate Bootstrap Shortcodes**, and then check on the frontend
that columns, buttons, wells, labels, icons, lead text, collapse sections (open,
close, keyboard), tab sections and tooltips still render and work, on a desktop and
a phone-sized viewport. Do the same on your site with the pages the report listed.

Checklist:

- [ ] Scan shows no remaining post content, or only occurrences you have reviewed.
- [ ] "Other surfaces" and "Not scanned" items reviewed by hand.
- [ ] Decided about Bootstrap 3: theme-provided, not needed, or companion installed.
- [ ] Plugin deactivated; listed pages checked on desktop and mobile.
- [ ] Plugin deleted. The custom fields `_bs_migration_original` / `_bs_migration_result`
      stay with the posts; delete them when you no longer want rollback.

## Manual mappings for non-post content

For widgets, options, custom fields and theme files, use the HTML destinations above
(the plugin's output for each shortcode is in `inc/bs_*.php`). A theme calling
`do_shortcode()` on a custom field will render whatever that field holds; migrate
the field's text with the same rules, or replace the call with the markup directly.
