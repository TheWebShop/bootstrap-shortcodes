# Follow-up: a receiver for shared usage summaries

Status: **decision recorded 2026-10-31, not built, not deployed.** Today the only
channel is the manual export on the Tools page (`docs/privacy.md`). This note is
the smallest design that would let a user send that same payload with one click,
so the deployment decision can be made in one sitting when it is wanted.

## What it must not change

- Still off by default, still one-off, still the same consent screen that shows
  the full payload first. The payload itself (`BS_Scanner::share_payload()`,
  `schema: 1`) does not change; the receiver accepts exactly that JSON.
- No identifiers added by the client. No cookies, no tokens, no site URL.
- Scanning and migration keep working with the receiver down or refused.

## Smallest viable receiver

One HTTPS endpoint, `POST /v1/summary`, accepting `application/json` bodies up to
8 KB with `schema === 1`, validating field names and types against an allowlist,
and appending the record to a store with a server-generated random id. Replies
`202` with nothing but the id, which the plugin shows so the user can quote it
when asking for deletion.

Candidates, cheapest first:

| Option | Cost | Logging you control | Notes |
| --- | --- | --- | --- |
| Cloudflare Worker + KV (or D1) | free tier | Worker code logs nothing by default; Cloudflare keeps edge request logs (IP) for its own operations | simplest; one file of code; static allowlist validation |
| Small PHP endpoint on existing hosting | already paid | your web server's access log (IP, UA) | needs the access log pruned or anonymised |
| GitHub issue created by the user (today's manual path) | free | GitHub's | identifies the GitHub account, by design |

Recommendation if built: the Worker. Disable Cloudflare's optional logging
products for the zone, do not store `CF-Connecting-IP`, and document that
Cloudflare's own request logs exist and are retained per their policy.

## Data handling to publish before switching it on

- **Stored:** the payload, the server-generated id, and the received date (day).
- **Not stored:** IP, user agent, headers, referrer.
- **Retention:** 24 months, then deleted; or sooner on request quoting the id.
- **Access:** the maintainer only; used to decide which components the migration
  tools must cover; aggregate counts may be published in the repository.
- **Deletion:** the id shown after sending is the only key; an email or GitHub
  issue quoting it gets the record deleted.
- **Re-identification:** unchanged from `docs/privacy.md`: not from the data,
  possibly from unusual counts to someone who already knows the site.

## Plugin-side change when the receiver exists

- A "Send this summary now" button on the same consent screen, next to
  "Download". It posts the exact JSON shown in the textarea with
  `wp_remote_post()`, 5-second timeout, no retries, and shows the id or the
  error. Still nothing on activation, update, cron or page load.
- `readme.txt` gains the destination and the data-handling text above; the
  privacy doc gains the retention and deletion lines.
- Tests: the send is wrapped so a test can assert the request body equals the
  payload and that a refused consent sends nothing.

Until then: nothing to deploy, nothing to maintain.
