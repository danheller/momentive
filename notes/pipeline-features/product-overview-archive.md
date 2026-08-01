# Pipeline feature: archive page for Product Overview

**Source:** Asana ticket, forwarded 2026-07-28.

**Full architecture for the `product-overview` CPT itself lives in
`notes/reference-sheets/product-overview-reference-sheet.md` — this document covers only
the archive-page-specific ask.** Cross-linked from that sheet as well.

## The ask, verbatim

> **Marketing Justification:** We want to create a dedicated Product Overview page to give
> visitors a clear, centralized destination for this type of resource. This will improve
> navigation, strengthen internal linking, and help guide users more efficiently toward
> product education and conversion-focused content.
>
> **Request:** Create an archive page for product overviews, using the URL slug:
> `/product-overviews/`. The behavior would be the same with `/webinars`.
>
> Affected URL / Planned URL(s): `/product-overviews/`

## Dependency: this needs the `product-overview` CPT to exist first

`has_archive` is a CPT registration argument — it can't exist independently of the CPT
itself. Per `migrations/progress.md`, Product Overviews is currently tracked as "Extends
existing CPT... mirrors Webinar Settings," but the dedicated reference sheet's own findings
supersede that: it recommends a **standalone `product-overview` CPT**, not a toggle on
`product`, with a Post Object field driving a derived permalink
(`/products/{linked product's slug}/overview/`). That tracker note is stale (flagged in the
reference sheet and in the progress-report PDF's "Flagged for review" section) and should be
resolved as part of building this CPT, not separately.

**Sequence this archive-page ticket right after the `product-overview` CPT is built, not in
parallel with it or ahead of it.**

One nuance worth flagging early: the reference sheet's proposed derived permalink for
*individual* Product Overview posts is `/products/{slug}/overview/` — a different path
prefix than the `/product-overviews/` slug this archive-page ticket asks for. That's fine;
WordPress doesn't require a CPT's archive to live under the same path prefix as its
singular posts' (derived or otherwise) permalinks. `webinar` happens to keep both under
`/webinars/`, which may be why the ticket assumes the two always match — worth double-checking
during build that nothing downstream (breadcrumbs, canonical-URL logic, the `redirect_canonical`
bypass the reference sheet's permalink design already needs) quietly assumes they do.

## Concrete build recipe, based on how `/webinars/` actually works today

Read directly from `inc/webinars.php`'s registration and query hooks:

1. **Registration** — give `product-overview` its own `has_archive => 'product-overviews'`
   and `rewrite => ['slug' => 'product-overviews', 'with_front' => false]`, mirroring
   `inc/webinars.php`'s `webinar` registration args line-for-line (`has_archive =>
   'webinars'`, same `rewrite` shape).
2. **No dedicated template file is needed.** `/webinars/` has no `archive-webinar.html` —
   it renders through the generic `templates/archive.html` fallback (header
   template-part, `wp:query-title`, `wp:pattern {"slug":"momentive/posts"}`, footer
   template-part). WordPress's own `archive-{posttype}.html` → `archive.html` resolution
   order means `/product-overviews/` will render the same way automatically the moment
   `has_archive` is registered — nothing else to build here.
3. **Decide on sort/grouping behavior.** `inc/webinars.php` layers a `pre_get_posts` filter
   (gated on `is_post_type_archive('webinar')` + `is_main_query()`) that sorts the archive
   query by `webinar_date` (soonest-first), plus a companion `the_posts` filter that
   partitions results into upcoming-first / on-demand-after groups. Read literally, "the
   behavior would be the same with `/webinars`" could mean either "an archive exists,
   rendered the same generic way" or "the same upcoming/on-demand-style partition." Per the
   reference sheet, Product Overview posts have **no lifecycle state to sort by** — the
   `webinar_type`/`webinar_date` fields carried over from Webinar Settings are confirmed
   dead on all 9 legacy posts. There's nothing to partition into two groups the way webinars
   are. Recommend defaulting to plain reverse-chronological order (no custom `pre_get_posts`
   hook needed at all) and confirming that reading with whoever filed the ticket before
   assuming — see open questions below.

## Open questions

- Confirm the "same behavior as `/webinars`" reading above with the requester
  (raymundalfred.oropeza@momentivesoftware.com per the ticket) — does it mean just "an
  archive page exists," or specifically the upcoming/on-demand sort logic that has no
  equivalent data on this CPT?
- Once built, consider reusing `patterns/story-card.php` for the archive's cards — the same
  reusable card `momentive/solution-resources` and `related-posts.php` already use — for a
  consistent look, even though the ticket doesn't ask for this explicitly.
- No mention of filtering/sorting UI (unlike `momentive/resource-filters` on other
  archives) — confirm whether this archive needs one or should stay a plain grid.
