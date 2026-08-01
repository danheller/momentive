# Pipeline feature: announcement banner rebuild

**Source:** Asana ticket, forwarded 2026-07-28. No Figma/Sheet links included with this one.

## The ask, verbatim

> Current banner solution is manual and not closeable. Previous closeable version failed
> (close button state did not persist).
>
> We need a scalable, permanent solution that:
> 1. Lives within the site (not HubSpot-injected)
> 2. Is closeable
> 3. Persists closed state (localStorage or cookie)
> 4. Can be scheduled (start/end date)
> 5. Maintains layout integrity
>
> **Scope:** Build reusable banner component in header. Add: close button, cookie/localStorage
> persistence, optional scheduling fields. Ensure no nav shift or CLS impact. QA across templates.
>
> **Acceptance Criteria:** Banner can be toggled on/off in CMS. Close button works and persists.
> No nav disappearance. No layout shift. No HubSpot dependency.

## What's actually already built

`patterns/announcement-bar.php` exists today and substantially satisfies several of these
requirements already:

- **Lives in the site, not HubSpot** — it's a PHP pattern rendered on `wp_body_open`
  (priority 5), configured via the `momentive_announcement_bar_args` filter.
- **Closeable, with persistence** — a close button (`#momentium-announcement-close`) sets a
  sitewide cookie (`momentive_announcement_dismissed` by default, `path=/`, `SameSite=Lax`,
  configurable expiry via `cookie_days`, default 30) on click, and the server-side render
  checks `$_COOKIE` and returns early if already dismissed. This is a cookie, not
  localStorage, but the ticket's own requirement #3 accepts either ("localStorage or
  cookie") — a cookie is arguably the better choice here anyway, since it's readable
  server-side and lets the PHP render skip the bar entirely rather than flashing it and
  removing it client-side.
- **No layout shift on close** — the bar's height is synced to a `--announcement-bar-height`
  CSS custom property via a `ResizeObserver`, which the sticky header offsets against; the
  close animation removes the property so the header collapses back up cleanly.

So requirements 2, 3, and 5, and half of requirement 1, are done. **This ticket's framing
("current banner ... not closeable") doesn't match what's in the codebase** — worth
confirming with whoever filed it whether production is actually still running something
else (a real HubSpot-injected banner, or an older, different mechanism) rather than this
pattern. If a second, HubSpot-injected banner mechanism genuinely is still live somewhere,
finding and removing it is part of "no HubSpot dependency" and should be called out
explicitly during QA — don't assume this pattern is the only banner mechanism in play
without checking.

## What's actually missing

1. **No CMS toggle.** Today, turning the bar on/off, or changing its text/link, means
   editing the `momentive_announcement_bar_args` filter or commenting out the `add_action`
   call in PHP — a code deploy, not a CMS action. Acceptance criterion #1 ("Banner can be
   toggled on/off in CMS") is not met.
2. **No scheduling.** There's no start/end date concept at all — the bar is either always
   showing (subject to the dismissal cookie) or code-disabled. Requirement #4 is not met.

## Recommendation

Don't rebuild the mechanism — extend it. Add an ACF Options Page ("Announcement Bar
Settings"), following the exact pattern already established for Blog Settings in
`inc/blog-and-newsroom.php` (`acf_add_options_sub_page()` on the `init` hook, not
`acf/init` — see `CLAUDE.md`'s "ACF options pages" section for why). Fields:

| Field | Type | Notes |
|---|---|---|
| `enabled` | true/false | The CMS on/off toggle acceptance criterion #1 asks for. |
| `message` | text or WYSIWYG | Replaces the hardcoded `text` arg. |
| `link_url` / `link_label` | link fields | Replace the hardcoded `link_url`/`link_label` args. |
| `start_date` / `end_date` | date picker, optional | When both are empty, show whenever `enabled` is true (today's behavior). When set, only render inside that window — the scheduling requirement #4. |
| `cookie_days` | number | Replaces the hardcoded `cookie_days` arg. |

`patterns/announcement-bar.php` then reads these via `get_field( $name, 'option' )` instead
of (or as new defaults feeding) `momentive_announcement_bar_args`, adds a date-range check
alongside the existing cookie check, and keeps every other line of the existing
closeable/cookie/`ResizeObserver` JS exactly as-is. This is additive, not a rewrite — most
of "build reusable banner component," "close button," "cookie persistence," and "no nav
shift" line items in the ticket's own scope list are already done and shouldn't be redone
from scratch.

## Open questions

- Confirm whether a HubSpot-injected banner is genuinely still live in production
  somewhere, separate from this pattern — if so, that's a "find and remove" task bundled
  into this work, not something this pattern's existence already resolves.
- Scheduling model: the ticket asks for start/end date only (no mention of recurring or
  multiple queued banners) — keep the options page to a single active banner unless told
  otherwise.
- Whether `enabled` should be a simple checkbox or should defer entirely to the date range
  when one is set (i.e., does `enabled = true` with no dates mean "always on," and is that
  still wanted as a mode, or should every banner require a date range going forward?).
