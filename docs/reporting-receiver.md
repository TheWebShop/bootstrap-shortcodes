# Follow-up: a receiver for shared usage summaries

Status: **approved to build 2026-10-31 as a trial; not built, not deployed.** Today
the only channel is the manual export on the Tools page (`docs/privacy.md`). This
note is the design for a one-click send to a Cloudflare Worker, written so the next
session can build it without re-deriving the constraints.

## Constraints from the maintainer (2026-10-31)

- Hosting the tool for the community is welcome, and paying for it is fine, but it
  must not be able to run up a bill: abuse by one party must cost nothing beyond a
  fixed, known amount. Prefer the **Workers Free plan**, where exceeding a limit
  fails requests instead of billing, over Workers Paid with usage billing.
- Heavy control and monitoring: a kill switch, per-IP and global rate limits,
  payload size and schema caps, and a view of volume per day that the maintainer
  checks, with alerts where Cloudflare offers them for free.
- This is a learning trial for future (possibly commercial) plugin services, so
  keep it small, readable and fully documented rather than clever.

## What it must not change

- Still off by default, still one-off, still the same consent screen that shows
  the full payload first. The payload itself (`BS_Scanner::share_payload()`,
  `schema: 1`) does not change; the receiver accepts exactly that JSON.
- No identifiers added by the client. No cookies, no tokens, no site URL.
- Scanning and migration keep working with the receiver down or refused.

## Smallest viable receiver

One HTTPS endpoint, `POST /v1/summary`, accepting `application/json` bodies up to
2 KB with `schema === 1`, validating field names and types against an allowlist,
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
- **Retention:** 12 months (KV TTL), then deleted; or sooner on request quoting the id.
  The derived keys hold counts only and expire too: `count:YYYY-MM-DD` after 2 days,
  `stats:YYYY-MM-DD` after 12 months (both set with a per-key TTL on write).
- **Access:** the maintainer only; used to decide which components the migration
  tools must cover; aggregate counts may be published in the repository.
- **Deletion:** the id shown after sending is the only key; an email or GitHub
  issue quoting it gets the record deleted.
- **Re-identification:** unchanged from `docs/privacy.md`: not from the data,
  possibly from unusual counts to someone who already knows the site.

## Abuse and cost controls (required before deploy)

| Control | Mechanism | Free-plan ceiling it relies on |
| --- | --- | --- |
| Account daily cap (shared) | Workers Free: 100,000 requests/day **per account**, then requests error; KV Free: 1,000 writes/day **per account**, then writes fail. Other Workers on the same account eat into these, so the receiver gets its own Cloudflare account (free) or the shared-quota risk is accepted knowingly | the cap is the bill: there is none |
| Per-IP rate limit | Workers Rate Limiting binding with a supported period (60 s; e.g. 3 requests / 60 s per IP). Counters are approximate and per Cloudflare location, so this is a brake, not a guarantee; one WAF rate-limiting rule (free plan allows one) backs it up at the edge | no cost |
| Kill switch | KV key `paused`; checked first; set from the dashboard or `wrangler kv` | one KV read per request (100,000/day free) |
| Payload cap | reject bodies > 2 KB (a full payload with every component is about 1 KB), non-JSON, `schema !== 1`, unknown keys, non-integer counts, counts > 10^7 | Worker CPU only |
| Ingestion cap | about 300 accepted records per UTC day, read from a KV counter `count:YYYY-MM-DD` (2-day TTL); beyond that reply `429` without storing. KV is eventually consistent and allows one write per key per second, so under a concurrent burst this cap is **approximate** (it can overshoot, and counter writes can fail). The hard ceiling is the account's 1,000 KV writes/day, which bounds storage whatever the counter says. If an exact cap is wanted, use a Durable Object counter (SQLite-backed Durable Objects are on the Free plan), decided when the receiver is built | KV Free writes 1,000/day |
| Method/path allowlist | `POST /v1/summary` (public) and `GET /v1/stats` (bearer secret, see Monitoring); everything else 404 with no body | – |
| No egress | the Worker calls nothing (no email, no third parties) | – |
| Storage growth | one KV value per record, TTL 12 months, key = random id; a daily cron summarises counts into one `stats:YYYY-MM-DD` key. Worst case, using the hard 1,000 writes/day rather than the soft 300: 1,000 × 2 KB × 365 ≈ 730 MB, inside the 1 GB KV Free limit | KV Free 1 GB |
| Monitoring | `GET /v1/stats` behind a bearer secret (wrangler secret) returning per-day counts; Cloudflare dashboard Workers metrics; `wrangler tail` for live logs; Cloudflare Notifications for Worker error rate | no cost |
| Upgrade path | if the trial outgrows Free, move to Workers Paid only after adding a hard spend ceiling in the dashboard and re-reviewing this table | – |

Explicitly rejected: Turnstile/CAPTCHA (the client is a server, not a browser), API
keys shipped in the plugin (public by definition), and storing IPs for abuse
analysis (the rate limiter needs them only in memory).

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
