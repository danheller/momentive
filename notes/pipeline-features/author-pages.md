# Pipeline feature: author pages + Heather Noll byline fix

**Source:** Asana ticket, forwarded 2026-07-28. Gated on a separate Asana sub-task,
"✓ Author Page Information Needed For Dev"
(https://app.asana.com/1/19722394369285/project/1213541469931144/task/1213811883215591?focus=true)
— per the ticket, dev shouldn't start until that sub-task is resolved.

**Figma File:** https://www.figma.com/design/wUpTERJGUIwB83yxf4Agr7/UI-Design?node-id=26223-20409&t=uF1UMf0K61zIviW… *(truncated in the source paste — pull the full link from Asana)*

**Author Information (Google Sheet):** https://docs.google.com/spreadsheets/d/1PGvkIFv_k_lEFeuORyrIcxWSW3IMzTD8TszW5wr_RGc/edit?usp=sharing

## The ask, verbatim

> Momentive currently has no author pages. Every blog post is published without a linked,
> credible author profile — which is a gap for E-E-A-T, buyer trust, and organic search
> authority. This ticket covers the build-out of the individual author bio pages for all
> internal contributors and external SMEs.
>
> And for the newsroom, all of the "Momentive in Action" posts should have Heather Noll as
> the author instead of Momentive.

Two distinct pieces of work. Handled separately below.

---

## Part 1: individual author bio pages

### What's already built and reusable

The byline *architecture* is fully built — this isn't starting from nothing:

- **`people` CPT** (`inc/people.php`) — publicly routable at `/people/{slug}/`, real
  permalinks, SEO-visible.
- **`person_role` taxonomy** — non-exclusive `leader`/`author`/`presenter` terms, so one
  profile can be an author and also a presenter/leader.
- **`post_author_ref`** (ACF Post Object → `people`, on `post` and `press-article`) is the
  canonical byline field — not native `post_author`. Byline resolution (`msw_resolve_linked_person()`,
  the `load_value`/`save_post` prefill hooks) is documented in `CLAUDE.md`'s "Byline
  architecture" section and is unaffected by this ticket.
- **`acf/person-position`** and **`acf/person-linkedin`** field blocks already exist —
  found at `blocks/person-metadata/person-position/` and `blocks/person-metadata/person-linkedin/`,
  registered together via `blocks/person-metadata/block.php`. (Minor doc correction worth
  making: `CLAUDE.md` lists these as if each lived in its own top-level `blocks/{name}/`
  folder per the project's usual one-block-per-folder convention — they're actually grouped
  under a shared `blocks/person-metadata/` folder. Doesn't affect this ticket, just noting
  it so nobody goes looking for a folder that isn't there.)
- **`momentive/person` block** (`blocks/person/block.php`) already renders a full profile
  view — headshot, name, position, LinkedIn link, bio content — inside a `<dialog>`
  lightbox, deep-linkable via `#person-{slug}`. This is effectively the profile *content*
  layout already designed and built, just rendered as a modal rather than (also) as a
  standalone page. Its markup (`momentive-person__profile-*` classes, `assets/css/person.css`)
  is a strong starting point for the real page's body content.

### What's actually missing

**`templates/single-people.html` does not exist.** Confirmed by listing `templates/` in
full — it isn't among the 13 files there. `CLAUDE.md` documents this exact template as
already built, in detail: a hero (eyebrow + `post-title` + `acf/person-position` +
`acf/person-linkedin`) followed by a two-column `post-content`/`post-featured-image` body.
Read that description as the **design brief**, not confirmation of past work — it appears
`CLAUDE.md` got ahead of the actual build at some point. This is the concrete, template-level
truth behind the ticket's claim "Momentive currently has no author pages" — validated, not
just asserted.

### Recommendation

Build `templates/single-people.html` per `CLAUDE.md`'s existing description, using the
`momentive/person` block's lightbox markup as a reference for the body layout (adapted to a
full-page hero-framed context rather than a modal — the two are deliberately *not* meant to
share a renderer, per `CLAUDE.md`'s "Person block and profile page are NOT a shared
renderer" design note, since the page needs hero framing the modal shouldn't have).
Cross-check the Figma file (node `26223-20409`) against that existing lightbox layout before
building from scratch — much of the visual language may already be a close match.

### Open questions

- Whether leader/People profiles generally should be indexed is already an open
  `CLAUDE.md` item (SEO-team decision, architecture supports either). This ticket sharpens
  it: author bio pages are explicitly framed as an E-E-A-T/SEO play, which argues for
  indexing *authors* specifically even if the broader leader-indexing question stays open.
- Whether the Author Information Google Sheet introduces any fields beyond what the
  current Person Settings ACF group already has (`job_position`, `linkedin_url`,
  `first_name`, `last_name`) — diff the sheet against the field group before building the
  template, since the sheet is the source of truth for what a profile needs to show.
- Blocked on the linked Asana sub-task per the ticket's own note — confirm that's resolved
  before starting.

---

## Part 2: Heather Noll byline for "Momentive in Action" posts

### Current mechanism

"Momentive in Action" is one of the three category-scoped URL prefixes for the
`press-article` CPT (`inc/blog-and-newsroom.php`,
`MOMENTIVE_PRESS_ARTICLE_CATEGORY_PREFIXES` — the `momentive-in-action` category term maps
to the `/momentive-in-action/{slug}/` permalink prefix). Byline for `press-article` posts
uses the exact same `post_author_ref` mechanism as every other resource type — there's no
separate Newsroom-specific byline field or logic to work around.

### What needs to happen

1. Confirm whether a Heather Noll `people` post already exists (check the People list
   table's "Filter by role" dropdown first — she may already be a `leader` or `presenter`
   and just need the `author` role added) or whether a new profile needs to be created.
2. Bulk-update `post_author_ref` on every `press-article` post in the `momentive-in-action`
   category term to point at that profile — replacing whatever it's currently set to (most
   likely a shared "Momentive Software" profile, per `CLAUDE.md`'s note on the dominant
   multiple-developers-share-a-byline write pattern).

### Recommendation

A small one-off WP-CLI patch script, following this project's established
`migrations/patch-*.php` convention (dry-run by default, explicit `live` token to write) —
model it on `migrations/patch-webinar-images-excerpts.php` for the dry-run/live shape
(`--user` isn't needed here; no media sideload, no SVG capability gate to satisfy). No WXR
export is needed — this is a live-database field update via `update_field()`, not a
migration from legacy content.

### Open question

Confirm the Heather Noll profile's existing role status (see step 1 above) before writing
the patch script, so it either creates or reuses the profile correctly on the first pass.
