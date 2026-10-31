# Security notes

Two different things get reported against this plugin: bugs in the shortcode
renderers (code in `inc/`), and advisories against the libraries it bundles. They
are assessed separately here.

## Shortcode renderers

Every attribute the shortcodes accept is written into a quoted HTML attribute through
`esc_attr()`, or through `esc_url()` for `href` values. `tests/phpunit/ShortcodeEscapingTest.php`
feeds quote-breaking values, inline event handlers and `javascript:` URLs through every
attribute of every handler and checks the parsed DOM for injected attributes, `style`
attributes, script URLs and `<script>` elements.

Shortcode *content* (the text between the opening and closing tag) is output as-is,
like any other shortcode in WordPress. In **post content** that is safe by
construction: the text was already filtered by `wp_kses` for users without
`unfiltered_html`, so the plugin grants nobody more than they could write directly
into a post. The same handlers also run wherever a site calls `do_shortcode()`
itself (widgets, theme templates, custom fields, page builders). Those callers
choose what text they feed in, and the plugin applies no filtering of its own to
it: content from those sources must be trusted or sanitised by whoever renders it,
exactly as for any other shortcode.

History:

| Version | Issue | Fix |
| --- | --- | --- |
| 3.4.1 | Stored XSS via unescaped shortcode attributes | `esc_attr()` on attributes |
| 3.4.2 | Tooltip title not escaped | `esc_attr()` on the title |
| 3.4.6 | `bs_tcontent` `id` attribute was unquoted, so `esc_attr()` did not help (CVE-2026-112111) | Quoted; DOM-based regression test |

Reports: open a GitHub issue, or, for anything you would rather not post publicly,
contact the maintainer through the plugin's WordPress.org listing.

## Bundled dependencies

| Library | Version | Where it runs |
| --- | --- | --- |
| Bootstrap CSS + JS | 3.4.1 | Frontend, when the "Load Twitter Bootstrap css/javascript" settings are on (the default) |
| Glyphicons Halflings | from Bootstrap 3.4.1 | Frontend, with the CSS |
| jQuery | 3.7.1 | **Only** inside the TinyMCE dialog iframes in the Classic Editor (`js/plugins/*.html`). The frontend uses WordPress's own jQuery |

### Bootstrap 3.4.1

3.4.1 (February 2019) is the final Bootstrap 3 release; the Bootstrap project no longer
supports version 3. The fixed advisories are all included:

- CVE-2018-14040, CVE-2018-14041, CVE-2018-14042 (collapse, scrollspy and tooltip
  data attributes): fixed in 3.4.0.
- CVE-2019-8331 (tooltip/popover `data-template`): fixed in 3.4.1.

Advisories published after 3.4.1 that name Bootstrap 3:

- **CVE-2024-6484** (carousel `data-slide`/`data-slide-to` via `href`). Disputed: the
  NVD record was rejected with "This was not a security issue in Bootstrap", and the
  Bootstrap maintainers' position is that the JavaScript does not sanitise deliberately
  dangerous markup. This plugin emits no carousel markup. A site author would have to
  write the hostile carousel markup themselves, which needs `unfiltered_html`.
- **CVE-2024-6485** (button plugin `data-loading-text`). Same shape: the plugin emits no
  `data-loading-text`, and writing the markup needs `unfiltered_html`.

Both are listed by commercial "never-ending support" vendors who sell patched
builds. Neither is fixable upstream, and neither is reachable through this plugin's
shortcodes. The honest statement is: **Bootstrap 3 is end-of-life, this plugin ships
the last release of it, and a site that keeps Bootstrap 3 after leaving this plugin
keeps that status too.** See `docs/migration.md` for the asset handoff.

### jQuery (dialogs only)

Updated from 1.11.1 to 3.7.1 (unreleased, after 3.5.0). It is loaded only inside the editor dialog
iframes, where the only inputs are the current user's own form fields; the old
version's advisories (CVE-2015-9251, CVE-2019-11358, CVE-2020-11022, CVE-2020-11023)
were not reachable with site-visitor input, but there was no reason to keep shipping
them.

## Admin surfaces

- Settings: WordPress Settings API, `manage_options`, nonce handled by `options.php`,
  values sanitised to the known checkbox keys (`sanitize_options()`).
- Editor dialogs: static HTML inside the plugin directory, no PHP, no user data stored.
- No external requests are made by the plugin. Nothing is sent anywhere unless you
  use the explicit "share report" flow described in `docs/privacy.md` (phase 2).
