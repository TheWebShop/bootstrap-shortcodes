# Roadmap: a safe landing for Bootstrap Shortcodes

Status: living document. Last updated 2026-10-31.

This plugin is in **maintenance mode**. It is kept safe and working for the sites
that already depend on it; it is not being grown into a page builder or a Bootstrap
component suite. The three outcomes below are the whole plan.

1. **Stable, safe, backward-compatible operation today.**
2. **A local dependency scanner** that tells an administrator what depends on the
   plugin, with optional, consent-based sharing of an aggregate report.
3. **A usable migration path** off the plugin, component by component, including a
   way to keep legacy Bootstrap assets when a site needs them.

## Evidence this plan rests on (verified 2026-10-31)

| Fact | Source |
| --- | --- |
| 4,000+ active installs; versions in use: 3.4.x 75 %, 3.5 22 %, other 3 % | api.wordpress.org plugin info and stats endpoints |
| ~4,300 downloads over the last 29 days (includes automatic updates after 3.5.0) | api.wordpress.org downloads endpoint |
| Readme says `Requires at least: 3.9`, `Tested up to: 6.9`, no `Requires PHP` | `readme.txt` |
| Current WordPress is 7.1.4; 7.1 runs ~65 % of sites, 6.x ~24 %, below 5.0 ~3 % | api.wordpress.org core and stats endpoints |
| PHP below 7.4 is ~5.7 % of WordPress sites; 7.4 is ~15 %; 8.x is ~79 % | api.wordpress.org php stats |
| Bundled Bootstrap is 3.4.1 (last Bootstrap 3 release, EOL). Bundled jQuery is 1.11.1, used only inside the TinyMCE dialog iframes | `css/bootstrap.css`, `js/bootstrap.js`, `js/jquery.js`, `js/plugins/*.html` |
| Core Details block: WP 6.3. Accordion block: WP 6.9. Tabs block: WP 7.1. Columns/Buttons/Group/Custom HTML/Shortcode blocks: WP 5.x | wordpress.org blocks list and block articles |
| WordPress.org guideline 7: no contact with external servers without explicit, authorized consent; guideline 8: no third-party CDNs or external code | developer.wordpress.org detailed plugin guidelines |
| The one documented migration request (support forum, Jan 2017) came from an agency with ~100 sites and got no reply | wordpress.org support topic |
| GitHub issue #81 (June 2025) asked whether anyone maintains the plugin after a Patchstack advisory | github.com/TheWebShop/bootstrap-shortcodes/issues/81 |

Installs are a signal of installed copies, not of rendered components or of people
who would notice removal. Nothing here measures those.

## Component surface (from the code)

| Tag(s) | Attributes | Nesting | Needs Bootstrap JS |
| --- | --- | --- | --- |
| `bs_row` | `class` (default `row`) | contains `bs_col` | no |
| `bs_col` | `class` (default `col-sm-1`) | inside `bs_row` | no |
| `bs_tabs` > `bs_thead` > `bs_tab`, `bs_dropdown` > `bs_tab`; `bs_tcontents` > `bs_tcontent` | `bs_tab`: `href`, `title`, `class`; `bs_dropdown`: `title`, `id`, `class`; `bs_tcontent`: `id`, `class` | deep, id-linked | yes (tab, dropdown) |
| `bs_collapse` > `bs_citem` | `bs_collapse`: `id`; `bs_citem`: `id`, `title`, `parent`, `open` | id-linked | yes (collapse) |
| `bs_notification` | `type`, `dismissible` | content | dismiss only |
| `bs_well` | `size` (`sm`, `lg`) | content | no |
| `bs_button` | `size`, `type`, `value`, `href`, `target` | self-closing | no |
| `bs_label` | `type` | content | no |
| `bs_icon` | `name` (a class list) | self-closing | no (Glyphicons font) |
| `bs_lead` | none | content | no |
| `bs_tooltip` | `placement`, `trigger`, `href` (unused) | content | yes (tooltip + `init.js`) |

Editor integration: TinyMCE buttons (Classic Editor only), one per component group,
inserting the shortcodes above. The block editor gets no buttons; shortcodes still
render through the Shortcode or Classic block.

## Phase 1: stability and safety

Branch `fix/stability-baseline`. Acceptance criteria:

- [ ] Frontend assets enqueued on `wp_enqueue_scripts`, admin CSS on `admin_enqueue_scripts`; same handles and files as before.
- [ ] `init.js` loads and runs when "Load Bootstrap JS" is off and a theme supplies Bootstrap (regression test).
- [ ] Reactivating the plugin keeps existing settings (regression test).
- [ ] Settings are sanitised to the known keys on save.
- [ ] Optional conditional asset loading, **off by default**, with a render-time fallback and a filter, documented with its limits.
- [ ] Escaping audited per output context; every attribute quoted and escaped (tests cover the id/class/href/title paths).
- [ ] Accessibility fixes that do not change layout: alert close button label, collapse `aria-controls`/`aria-expanded`/`collapsed` state, tab roles, icon `aria-hidden`.
- [ ] Nested shortcodes render inside `bs_label` (was raw).
- [ ] Bundled jQuery for the editor dialogs updated from 1.11.1 to 3.7.1 (admin-only, Bootstrap 3.4.1 supports it); e2e dialogs still pass.
- [ ] Support policy decided and declared in `readme.txt` (see Decisions), with the tested matrix recorded in `docs/compatibility.md`.
- [ ] Bundled dependency advisories assessed separately from shortcode-renderer issues (`docs/security.md`).
- [ ] PHPUnit and Playwright green locally and in CI.

Risks: changing the enqueue hook could reorder scripts relative to a theme that also
enqueues on `init` (very rare); conditional loading can miss shortcodes rendered
outside the main query (mitigated by the render-time fallback and by being opt-in).

## Phase 2: dependency scanner and optional report

Branch `feat/dependency-scanner`. Acceptance criteria:

- [ ] Tools > Bootstrap Shortcodes page with a bounded, resumable scan over posts of all public and private post types (all statuses), reporting per component: occurrences, affected posts, published vs draft/other, with edit links.
- [ ] Flags per occurrence: nesting, custom classes, unknown attributes, malformed or unclosed syntax, duplicate ids.
- [ ] Also scans widgets (`text`, `custom_html`, `block`), the Customizer/theme mods and options that hold content (bounded list), post meta values that are strings, and reports the template/PHP surfaces it cannot inspect (theme files, `do_shortcode()` calls, page builders' own storage).
- [ ] Never writes content; never executes shortcodes during discovery (text scan only, documented as candidates, not proof).
- [ ] WP-CLI command for large sites.
- [ ] "Share this report with the author": off by default, shows destination, exact fields, one-time vs recurring, payload preview, no identifiers by default. v1 is an **export/copy** flow (no endpoint exists; see Decisions).

Risks: large sites (bounded batches, no full-table loads); serialized meta (read only,
string search, never unserialize-and-rewrite).

## Phase 3: migration and asset handoff

Branch `feat/migration`. Acceptance criteria:

- [ ] Component-by-component matrix in `docs/migration.md` (destination, WP version, preserved content/interaction, visual differences, retained dependency, fallback, limitations).
- [ ] Converter: shortcode text to block markup or reviewed static HTML, preserving unrelated markup and third-party shortcodes; unknown/malformed/ambiguous occurrences are left in place and listed for manual review.
- [ ] Preview, migrate selected, migrate in batches, review outstanding; explicit confirmation, nonces, capability checks.
- [ ] Originals stored in post meta (not only revisions) with a tested rollback; stale-content protection via `post_modified_gmt` check; bounded, resumable, safe to retry.
- [ ] Legacy assets handoff: a separate, minimal companion plugin ("Bootstrap 3 Legacy Assets") shipped in this repo, installable from the migration page only with explicit consent; works with this plugin deactivated and deleted; avoids double-loading with theme Bootstrap; documents what retaining Bootstrap 3 means.
- [ ] Verified by deactivating this plugin in wp-env after migration: desktop/mobile layouts, keyboard, tabs, collapse, icons, tooltips.

## Decisions

Decisions taken with a sensible default; each is reversible and is flagged for Kevin
in the PR description.

| Decision | Default applied | Why |
| --- | --- | --- |
| Support policy | Declare `Requires at least: 5.0`, `Requires PHP: 7.4`, `Tested up to: 7.1`; test on WP 6.4/PHP 7.4 and WP 7.1/PHP 8.3 | WordPress itself requires PHP 7.4+ since 6.6/7.x; sites below the floor keep 3.5.0 (WordPress will not offer them the update) |
| Reporting receiver | None yet. v1 = local export + copy; payload format designed for a future endpoint | No receiver exists; inventing one is an infrastructure/privacy decision |
| Companion plugin distribution | Source in this repo, zip built in-admin, installed through core's upgrader with consent; no WordPress.org listing yet | A listing is a directory decision |
| Plugin description | State maintenance mode and point to the migration page | Honest expectation setting; wording is Kevin's call |

## Out of scope

Bootstrap 4/5 conversion, new components, a block suite, Gutenberg buttons for
inserting shortcodes, monetisation, any analytics beyond the explicit report.
