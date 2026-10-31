# Privacy

## What the plugin does with data

Nothing leaves the site. The plugin makes no HTTP requests, loads no third-party
scripts or fonts, sets no cookies, and has no tracking of any kind on the frontend or
in the admin. This is a hard rule for the plugin, in line with WordPress.org plugin
guideline 7 ("plugins may not contact external servers without explicit and
authorized consent").

The dependency scan (Tools > Bootstrap Shortcodes) reads post content, widget
settings, options, post meta, term descriptions and the active theme's PHP files
*on your server*, and stores its results in two options in your own database
(`bs_scan_state`, `bs_scan_results`). "Discard results" deletes them; so does
uninstalling the plugin (`uninstall.php`). The results contain, and only contain,
these identifiers from your own site: post ids, titles, types and statuses; widget
ids and sidebar names; Customizer setting paths; option names; post meta keys; term
ids, names and taxonomies; the active and parent theme directory names and the
paths of theme PHP files that call `do_shortcode()` or contain `[bs_`. Never option,
meta, Customizer or term description values, and never post content.

## Sharing a summary with the author (optional, manual)

The report page offers a summary you can send to the plugin author so the migration
tools cover the components people actually use. It is built to be as close to
anonymous as a usage summary can be, and it is **never sent automatically**:

- **Off by default, always.** There is no setting that turns on reporting. The page
  shows the summary; you download or copy it and send it yourself.
- **Destination.** You choose: a GitHub issue on `TheWebShop/bootstrap-shortcodes`
  (public, linked to your GitHub account) or email to the author (address on the
  WordPress.org plugin page). The plugin opens no connection to either.
- **Frequency.** One-off, each time you decide to. No recurring reporting exists.
- **Exact contents** (`BS_Scanner::share_payload()`, pinned by
  `tests/phpunit/ScannerSiteTest.php`):

  | Field | Example | Why |
  | --- | --- | --- |
  | `schema` | `1` | so later payloads can be told apart |
  | `generated` | `2026-10` | month only; a precise timestamp could match server logs |
  | `plugin_version` | `3.6.0` | which scanner produced it |
  | `environment.wordpress`, `.php` | `7.1`, `8.3` | major.minor only; drives the support policy |
  | `environment.multisite` | `false` | multisite sites have extra surfaces |
  | `settings.css`, `.js`, `.conditional` | booleans | how many sites rely on the plugin to load Bootstrap |
  | `totals.*` | counts | size of the dependency |
  | `components.<tag>` | occurrences / posts / published posts | the core question: which components matter |
  | `flags.*` | counts | which migration complications are common |
  | `surfaces.*` | counts | how often content lives outside post_content |

- **Never included:** site name, URL or domain, admin email, user names, post
  titles or ids, content, option names, meta keys, file paths, theme or plugin
  names, IP address, locale, timezone, exact dates.

### Can the summary identify a site?

Not from its fields. Two things can:

1. **The channel.** GitHub shows the account that posted; email shows the sender.
   Pick the channel you are comfortable with.
2. **Unusual combinations.** A site with, say, 1,347 occurrences across 212 posts is
   recognisable to someone who already knows that site. That is a property of any
   usage count, which is why the summary carries no finer detail than it needs.

The author's side: the summary is read by a person, used to decide what the
migration tools must cover, and kept in the GitHub issue or mailbox it arrived in,
subject to GitHub's and the mail provider's own retention. There is no database of
summaries and no automated processing. To withdraw one, delete or edit the GitHub
issue (your own account can) or ask the author to delete the email.

### Why not automatic opt-in telemetry?

It would need a receiving service (hosting, logging, retention and deletion
procedures, a provider agreement), a consent screen that stays honest across
updates, and ongoing maintenance, to answer a question that a handful of manually
shared summaries answer well enough. If that changes, it will be a separate,
documented decision, with the same "off by default, inspect before sending" rules,
and never enabled by an update.

## Migration (planned, phase 3)

Not part of this release. When it ships, migrating content will rewrite your own
posts in your own database after explicit confirmation, keep the original in post
meta (`_bs_migration_original`) for rollback, and send nothing anywhere. Its
documentation (`docs/migration.md`) arrives with it.
