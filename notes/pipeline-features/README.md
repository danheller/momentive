# Pipeline Features — net-new requests, not on the legacy site

Everything else under `notes/reference-sheets/` documents **migrating** legacy content: a
post type existed on the old site and needs a home on the rebuilt one. The four documents
in this folder are a different category — features assigned via Asana that **never existed
on the legacy site at all**. There's no legacy field map to build, no WXR export to read;
these are net-new builds against the rebuilt theme's existing architecture.

Source: four Asana tickets forwarded by Daniel on 2026-07-28. Google Sheets/Figma/Asana
links are included below exactly as pasted; where the paste was truncated with "…" that's
noted so nobody mistakes it for the full URL — pull the untruncated link from Asana directly
before starting a build.

| Ticket | Priority | Summary | Doc |
|---|---|---|---|
| Archive page for Product Overview | Medium | New `/product-overviews/` archive, "same behavior as `/webinars`" | [`product-overview-archive.md`](./product-overview-archive.md) |
| Who We Serve — Nonprofit redesign | Not stated | Rebuild `/who-we-serve/nonprofit/` on the new Figma "Who We Serve-sub-v2" template | [`who-we-serve-nonprofit-redesign.md`](./who-we-serve-nonprofit-redesign.md) |
| Author pages + Heather Noll byline fix | Not stated (gated on an Asana sub-task) | Individual author bio pages; repoint "Momentive in Action" byline to Heather Noll | [`author-pages.md`](./author-pages.md) |
| Announcement banner rebuild | Not stated | Scalable in-site, closeable, schedulable announcement bar | [`announcement-banner.md`](./announcement-banner.md) |

Two of these four (Product Overview Archive, Who We Serve Nonprofit) are also cross-linked
directly from their related migration reference sheets, since they extend or depend on
architecture those sheets already describe:

- `notes/reference-sheets/product-overview-reference-sheet.md` — see its "Pipeline feature: archive page request" section.
- `notes/reference-sheets/who-we-serve-reference-sheet.md` — see its "Pipeline feature: Nonprofit page redesign" section.

## A theme worth flagging across all four

Investigating each of these against the actual codebase (rather than taking the ticket
descriptions at face value) turned up two real gaps between what `CLAUDE.md` documents and
what's actually built:

1. **`templates/single-people.html` does not exist.** `CLAUDE.md`'s FSE templates table
   describes it in detail (hero with eyebrow + post-title + `acf/person-position` +
   `acf/person-linkedin`, then two-column `post-content`/`post-featured-image`) as if it
   were already built. A directory listing of `templates/` confirms it isn't there — only
   `404.html`, `archive-faq.html`, `archive-press-article.html`, `archive.html`,
   `blank.html`, `home.html`, `index.html`, `no-title.html`, `page.html`, `recording.html`,
   `search.html`, `single-webinar.html`, `single.html`. Read `CLAUDE.md`'s description as
   the design brief for this template, not confirmation it exists. See `author-pages.md`.
2. **`patterns/announcement-bar.php` already does more than its ticket assumes.** The
   ticket for the announcement banner describes the "current" solution as manual and not
   closeable — but the pattern already in the codebase is closeable, persists via a
   sitewide cookie, and avoids layout shift via a `ResizeObserver`-driven CSS variable. The
   real gaps are CMS-editability and scheduling, not the close/persist mechanism itself. See
   `announcement-banner.md`.

Worth a quick correction pass on `CLAUDE.md` once these are resolved, so the architecture
doc stays trustworthy as the single source of truth it's meant to be.
