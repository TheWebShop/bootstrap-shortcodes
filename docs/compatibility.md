# Compatibility policy

What the plugin promises, what is actually tested, and what happens to sites below
the floor. Updated 2026-10-31.

## Declared support (readme.txt)

| Header | Value | Reason |
| --- | --- | --- |
| Requires at least | 5.0 | First WordPress with the block editor; every admin API the plugin uses (Settings API with `sanitize_callback`, `pre_do_shortcode_tag`, `has_shortcode`) exists from 4.7 |
| Requires PHP | 7.4 | This plugin's own policy, aligned with WordPress 7.0+ (which requires PHP 7.4; WordPress 6.6 to 6.9 still accept PHP 7.2.24). PHP below 7.4 runs about 6 % of WordPress sites (api.wordpress.org/stats/php) and has been end-of-life since 2022 |
| Tested up to | 7.1 | Current release line, tested below |

A site below the floor does not break: WordPress will simply not offer it this
plugin's newer versions, and it keeps the version it has. That is the deliberate
trade-off: older stacks keep working on 3.5.0, newer fixes target stacks WordPress
itself still supports.

## Tested matrix

| WordPress | PHP | What ran | Result |
| --- | --- | --- | --- |
| 7.1.4 | 8.3 | PHPUnit (81 tests), Playwright (17 tests: Classic Editor dialogs, frontend rendering and interaction under plugin-/theme-/no-Bootstrap-JS setups, conditional loading, block-editor Shortcode block) | green, 2026-10-31 |
| 6.4.7 | 7.4 | PHPUnit (81 tests); every component rendered on the frontend with assets present; deactivate/reactivate keeps settings | green, 2026-10-31 |
| latest (wp-env default) | 8.2 | PHPUnit + Playwright in GitHub Actions on every PR | see CI |

Not tested: multisite, PHP 8.4 specifically (nothing in the plugin is version
sensitive beyond what 7.4 to 8.3 covers), WordPress 5.0 to 6.3 (nothing used by the
plugin changed in that range; the floor is declared, not exercised).

## Editors

- **Classic Editor**: TinyMCE toolbar buttons and dialogs. Covered by Playwright.
- **Block editor**: no toolbar buttons. Shortcodes render when placed in a Shortcode
  block, a Classic block, or a paragraph. Covered by the frontend Playwright spec.

## Bootstrap setups

| Setup | Settings | Behaviour |
| --- | --- | --- |
| Plugin-provided (default) | CSS on, JS on | Bootstrap 3.4.1 CSS + JS and `init.js` on every page |
| Theme-provided Bootstrap 3 | CSS off, JS off | Only `init.js` loads (depends on jQuery); it initialises tooltips if a tooltip plugin is present when the page is ready, otherwise retries on `load`, otherwise does nothing |
| Static only | CSS on, JS off | Grid, wells, buttons, labels, icons, lead, alerts (not dismissible) render; tabs, collapse, tooltips need the JS |
| Conditional loading | any + "Conditional loading" | Assets load only when a post in the main query uses a shortcode, or late in the page when one renders anyway |

Bootstrap 4 or 5 themes are **not** compatible with the markup these shortcodes emit
(class names changed). That is a migration case, not a configuration one.

## Upgrades and reactivation

Nothing runs on plugin update. Activation only seeds settings when none exist.
Settings are a single option, `bs_options`, and are never rewritten by the plugin
outside the settings form.

## Running the matrix locally

Use `@wordpress/env` with a config outside the repo:

```json
{
  "core": "WordPress/WordPress#6.4.7",
  "phpVersion": "7.4",
  "mappings": { "wp-content/plugins/bootstrap-shortcodes": "/path/to/checkout" },
  "port": 8892,
  "testsPort": 8893
}
```

Then `npx wp-env start` from that directory and run `vendor/bin/phpunit` inside
`tests-cli` with the plugin directory as the working directory.
