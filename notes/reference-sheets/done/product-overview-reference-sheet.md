# Product Overview Rebuild — Reference Sheet (all 9 legacy posts)

Decoded content and architecture notes for the legacy `product-overviews` CPT (9 published posts — small enough to cover every post, not just a representative sample).

**Source exports:**
- `momentivesoftware.WordPress.2026-07-27.xml` — a targeted fresh export containing only `product-overviews` posts (9) + their attachments (30). No dedicated `momentivesoftware.product-overviews.current.*.xml` export exists yet like the other gated CPTs have; this file is the working source until one is pulled.
- `momentivesoftware.assets.current.2026-07-27.xml` — full `assets` CPT export (177 posts across all asset types), used to confirm the recording hand-off behavior described in "Recording layer" below.

**What this is:** each post is a gated "see the product in action" landing page — a HubSpot form to request a demo, built for one specific Product. Structurally it's the same two-column gated shape as whitepapers/infographics (description + checklist on the left, form on the right), plus a `webinar_*` field set clearly copy-pasted from Webinar Settings (date, time, presenters, upcoming/on-demand) that turns out to be **inert** — see "Notes discovered" below. `inc/recordings.php` and `notes/todo.txt` already anticipated this CPT (as a future recording host and as a gated-content type, respectively), so this isn't a from-scratch design — it's confirming and refining an existing plan against the real legacy data.

---

## The permalink question (why this reference sheet exists)

You asked me to figure out whether `product-overviews` should stay a custom-permalink setup or become a CPT with a field-derived permalink. The legacy data confirms your read on all three points:

**1. Every post uses a fake nested URL, not its own CPT permalink.** All 9 `<link>` values look like `/solutions/{family}/{product-slug}/overview/` — e.g. `https://momentivesoftware.com/solutions/association-management-software/netforum/overview/`. That's the *old* Solutions-nested product URL shape, from before Products got their own `/products/` CPT and URL space (per CLAUDE.md's migration history). So yes — outdated, exactly as you suspected.

**2. There's already a field meant to link the post to its product — and it's a plain text field.** `linked_product` (ACF text, not Post Object) holds a hand-typed slug: `netforum`, `cobalt`, `ym-careers`, etc. Nothing enforces that the value matches a real Product post, and nothing updates it if the Product's slug changes.

**3. That text field is already stale for more than half the posts.** Cross-checking each `linked_product` value against the actual rebuilt Product post slugs (`momentive.products.rebuild.2026-07-25.xml`):

| # | Overview post | `linked_product` (legacy text) | Actual Product post slug | Resolves? |
|---|---|---|---|---|
| 1 | Powering Meaningful Member Engagement with NetForum | `netforum` | `netforumams` | ✗ mismatch |
| 2 | Careers Powered by Momentive (YM Careers) | `ym-careers` | `ym-careers` | ✓ match |
| 3 | Amplify Your Impact with Cobalt AMS | `cobalt` | `cobaltams` | ✗ mismatch |
| 4 | Streamline, Engage, and Grow with YourMembership | `yourmembership` | `yourmembership-ams` | ✗ mismatch |
| 5 | Smarter Association Management with Nimble AMS | `nimble-ams` | `nimbleams` | ✗ mismatch |
| 6 | Aptify in Action | `aptify` | `aptify` | ✓ match |
| 7 | More Than Events — GiveSmart | `givesmart` | `givesmart` | ✓ match |
| 8 | Powering Growth & Engagement with Path LMS | `path-lms` | `path-lms` | ✓ match |
| 9 | MIP Accounting in Action | `mip-accounting` | `accounting` | ✗ mismatch |

**5 of 9 don't resolve as a plain slug lookup.** This is direct evidence for your instinct: a hand-typed text value drifts out of sync the moment a product's slug changes (which already happened at least 5 times), silently breaking whatever depends on it. A Post Object field doesn't have this failure mode — it stores a stable post ID, so a later slug rename never breaks the relationship.

**Recommendation: keep `product-overviews` (singular `product-overview`) as its own CPT**, matching the gated-content family (whitepaper/infographic/guide), and convert `linked_product` from a text field to a Post Object field (target: `product`, single value, required). Have the *permalink* derive from that field — `/products/{linked product's slug}/overview/` — rather than a stored/typed URL. See "Proposed architecture" below for the mechanism. This also matches `notes/todo.txt`'s own note: *"Product overview pages are currently nested under the old solution-nested product locations. Should those move under /products/?"* — yes.

(Reconciles with two stale planning notes, for the record: `notes/todo.txt` and `PROJECT-SUMMARY.md` both floated an earlier idea — "not a new CPT, just a toggle on `product`" — before `inc/recordings.php` was built. That file's own comments already assume Product Overviews will be a **separate post** that registers as a recording host alongside webinars, which only makes sense as a real CPT. This reference sheet supersedes the toggle idea; recommend updating those two docs once this is built.)

---

## Field → destination map

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `linked_product` (text, e.g. `netforum`) | **Post Object field** → `product` (required, single) | Convert type. Becomes the permalink source — see below. 5/9 legacy values need manual re-matching to the correct Product post (see table above), not a mechanical rename. |
| `solution_family` (text, e.g. `association-management-software`) | _(not migrated — use the native `category` taxonomy instead)_ | **Stale on at least 1 post:** the YM Careers overview has `solution_family: accounting-software`, but its actual `category` term is correctly "Career Centers." The native taxonomy assignment is trustworthy; this text field is not — don't use it as a data source. |
| `category` (native taxonomy, 1 per post) | Native category panel | Shared Solution-scoped category, same as whitepapers/case studies/webinars. All 9 posts have exactly one. |
| `resource_hero_image` (attachment ID) | `hero_image` ACF field | Present on all 9. Different attachment from `_thumbnail_id` on every post. |
| `_thumbnail_id` (attachment ID) | Featured image | Archive card image. |
| `resource_details` (HTML) | Left column — intro paragraphs | Same Word-artifact cleanup as whitepapers/infographics/webinars. |
| `details_cta` | Left column — closing CTA sentence | Present on all but 1 (ym-careers — wait, actually present on netforum/cobalt/yourmembership/nimble-ams/aptify/givesmart/path-lms; absent on ym-careers and mip-accounting). Plain text. |
| `resource_checklist_title` + `resource_checklist` (serialized) | "What You'll Learn" checklist | Present on all 9, 3–5 items each. Items may contain inline `<b>` tags — preserve as rich text, not plain strings (same as the whitepaper/infographic checklist handling). |
| `resource_details_after_checklist` | Left column — paragraphs after checklist | Only 1/9 posts (MIP Accounting — mentions MomentiveIQ). Rare but real; don't skip building it. |
| `enable_gated_content` / `hubspot_form_code` | HubSpot form embed | **All 9 are gated** (`true`) — unlike infographics' mixed gated/ungated split. Embed code inline in the block's `data` (whitepaper pattern), not a post-level field — there's only ever one form per post, no upcoming/on-demand split to justify two fields. |
| `form_heading` | Form section heading | Plain text, varies per post (e.g. "Fill out the form to save your seat"). |
| `enable_cta_box` + `resource_cta_title` / `resource_cta_button_text` / `resource_cta_button_url` / `resource_cta_new_tab` | Closing CTA band | **Only 1/9 posts uses this (NetForum)** — a title + button pointing at `/solutions/association-management-software/` (itself a stale legacy URL — recheck/update the target when migrating). Real content, not dead cruft like it is on whitepapers/infographics — needs a small block/pattern section, just a rarely-used one. |
| `enable_related_resources` + `related_resources_heading` + `related_resources_list` (serialized post IDs) | Related resources grid | 2/9 posts (NetForum, MIP Accounting). Hand-curated list of specific post IDs (case studies/testimonials/blog posts), not an automatic query — closer to `linked-products`' curated-field pattern than `solution-resources`' automatic one. |
| post title, post date | Post title, post date | |

### Carried over from Webinar Settings but functionally dead

| Legacy field | Recommendation |
|---|---|
| `webinar_type` (always `on-demand`, all 9) | **Do not migrate as a lifecycle field.** `inc/recordings.php` already documents the intended behavior: product overviews are "hosts without [the upcoming/on-demand] notion" — available as soon as a video embed exists, no date gating. The legacy `webinar_date` values are inconsistent with a real "on-demand since X" story anyway — 5 of 9 are already in the past relative to today, 1 is 4 days out, 1 is 3+ weeks out, with no visible pattern — confirming this is copy-pasted field-group cruft, not real scheduling data. |
| `webinar_date`, `webinar_end_date`, `webinar_time_start`, `webinar_time_end`, `webinar_timezone` | Not migrated. |
| `webinar_presenters`, `webinar_enable_presenters_section` (empty/false on all 9), `single_column_presenters`, `use_people_post_type`, `presenters` | Not migrated — no post uses these. |
| `upcoming_form_code` / `on-demand_form_code` | Not migrated. Only 1 post (MIP Accounting) has a value in `upcoming_form_code` at all, and it's a duplicate of the same `hubspot_form_code` embed — leftover cruft from the copy-pasted field group, not a second real form. |
| `hero_video_source` (`wistia` on all 9), `hero_library_video` | Not migrated — same dead-default pattern already seen on infographics. |
| `hero_video` / `hero_embed_video` | Dead on 8/9. **1 exception:** YM Careers has `hero_video: true` with a real Wistia hero-background embed. This is a decorative hero video, unrelated to the Recording layer below — don't conflate the two. Flag for a manual per-post override if the rebuilt hero pattern doesn't already support a video background. |
| `rank_math_*`, `_wp_old_date`, `_lmt_disableupdate`, `_expiration-date*`, `_pp_future_metadata_hash`, `ppma_authors_name` | Plugin cruft (Rank Math, Publish Press Future, Post Meta Auto Update, Publish Press Authors) — not migrated, same as every other CPT migration in this project. |

### Recording layer — confirmed against the legacy `assets` CPT (2026-07-27 spot-check)

None of the 9 `product-overviews` posts have anything in their own `video_embed_code` — but that's because the recording doesn't live on the post itself. Daniel filled out the GiveSmart overview's HubSpot form live and it redirected to `https://momentivesoftware.com/assets/product-overview/2026-06-givesmart-overview/` — a separate `assets` CPT post. A fresh `assets` export (`migrations/momentivesoftware.assets.current.2026-07-27.xml`) confirms the shape:

- **The video is real and does exist** — that `assets` post's `video_embed_code` field holds a genuine Wistia embed. The form-fill flow works; it just hands off to a second post type rather than resolving on the `product-overviews` post itself.
- **The asset slug doesn't match its product-overview post, and there's no reliable pattern to derive one from the other.** GiveSmart alone has *five* of these, one per month as the demo gets refreshed — `2026-02-givesmart-overview`, `-03-`, `-04-`, `-05-`, `-06-givesmart-overview` — all still `publish` status, none superseded/trashed. Across all 9 products there are 17 of these `assets` posts total, and the naming is inconsistent even beyond the date prefix: `2026-02-aptify-product-overview` vs. `2026-02-givesmart-overview` vs. `2026-06-mip-overview` (not `mip-accounting-overview`), and one post (`2025-01-yourmembership-product-overview`) has a `2025` slug year against a `2026-01` actual post date — a straight typo. **This is exactly why the webinar trick — swap `/webinars/{slug}` for `/recordings/{slug}` because the slugs match — won't work here.** They don't match, and there's no clean transform that would fix that reliably across all 9.
- **There's no need to preserve every monthly asset as its own page going forward.** The pattern reads as "the same one demo video gets refreshed periodically," not "each month's version is meant to stay individually addressable" — nothing links to April's GiveSmart recording once June's exists; HubSpot's own form-submit redirect setting is presumably what gets updated each time a new one is built, not anything WordPress-side.

**Recommendation (matches what you're happy to do — change the URL and redirect the old one):**
1. Give the rebuilt `product-overview` CPT the same shared Recording field group webinars use (`video_embed_code`, `recording_url`). Populate it at migration time with just the *latest* `assets` post's embed for that product (June's, for GiveSmart) — the older monthly ones are migration inputs, not separate posts to recreate.
2. Add `'product-overview'` to `momentive_recording_host_types()` in `inc/recordings.php` (the one-line addition that file's comments already describe). The permanent, current recording URL becomes `/recordings/{the product-overview post's own stable slug}` — e.g. `/recordings/givesmart-overview` — reusing the existing flat namespace, no prefix-swap trick needed since this doesn't key off the legacy asset's slug at all.
3. Redirect the legacy `/assets/product-overview/*` URLs (17 of them, all captured in the fresh export) to their product's new `/recordings/{slug}` URL as a **static list via the Redirection plugin**, not a dynamic PHP catch-all — `inc/recordings.php`'s existing `/assets/*` catch-all works by treating the trailing path segment as the host's own slug, which only works because webinars keep matching slugs; it would silently fail to resolve any of these (the trailing segment is a dated asset slug, not the product-overview post's slug), so it's not a fit here without a hand-built mapping anyway. A static list is simpler and just as correct given there are only 17.
4. Separately, whoever manages the HubSpot forms needs to update each form's post-submit redirect target from the old `/assets/product-overview/...` URL to the new `/recordings/{slug}` URL — a HubSpot-side config change, not something the WordPress migration can do on its own.

---

## Proposed architecture

**CPT key:** `product-overview` (singular, hyphenated — matching `case-study`/`press-article`, not `whitepaper`/`guide`, since this is a genuine two-word compound). Registered the same way as `whitepaper`/`infographic`: `public`, own admin menu entry, `category` taxonomy shared with the other resource types, `template` populated from a `momentive/product-overview-content` pattern once built.

**Default rewrite (fallback only):** give it a real default slug, `product-overviews`, matching the legacy CPT key — this is what a draft with no product linked yet, or an admin preview, falls back to. It should almost never be the live public URL.

**Derived permalink — the actual public URL:** `/products/{linked product's slug}/overview/`. Same two-piece mechanism `inc/guides.php` already uses for its dual `/guides/` vs `/research-study/` prefixes, just parameterized by the linked product instead of a static second prefix:

1. `post_type_link` filter — when a `product-overview` post has `linked_product` set, replace the generated permalink's default `/product-overviews/{slug}/` with `/products/{product's post_name}/overview/`.
2. A hand-added `add_rewrite_rule( '^products/([^/]+)/overview/?$', ... )` + a `parse_query` step (same shape as `momentive_recording_resolve()` in `recordings.php`) that looks up the product by slug, then finds the `product-overview` post whose `linked_product` points at it, and hands WordPress a normal singular query for that post — including the `is_singular`/`is_archive`/`queried_object` correction block `recordings.php` already has to do, since this also arrives via a rewrite WordPress will otherwise misclassify.
3. A `redirect_canonical` bypass for this view, same reason as `recordings.php`'s — otherwise WordPress's canonical redirect tries to bounce back to the CPT's own default `/product-overviews/{slug}/` permalink.

**One-overview-per-product guard:** unlike `recordings.php`'s flat-namespace slug-collision guard (which auto-suffixes), two `product-overview` posts pointing at the same product would silently collide on the same derived URL, with no natural tiebreak. Recommend a hard stop via `acf/validate_value` on `linked_product` — reject the save with an error message if another published (non-trash) `product-overview` post already has this product selected. This is a real routing collision, not just an editorial inconsistency, so a hard validation error is the right strength here (stronger than the soft admin-notice convention used for the `redirect_to_solution` alias case in `inc/products.php`, which doesn't affect routing).

**Legacy URL redirects — two separate redirect problems, confirmed against the fresh 2026-07-27 exports, don't conflate them:**
1. The page itself: the 9 legacy `/solutions/{old-family}/{product-slug}/overview/` URLs (the `product-overviews` post's own permalink) need a 301 to the new `/products/{slug}/overview/`. Build this as an explicit map from each post's actual legacy `<link>` at migration time (same idea as the Case Study migration's slug-based upserts) — the old family segment can't be mechanically reconstructed from current data.
2. The recording: the 17 legacy `/assets/product-overview/{dated-slug}/` URLs (a *different* post type, reached only via the HubSpot form's post-submit redirect, never linked from the page itself) need a 301 to the new `/recordings/{product-overview's own slug}`. See "Recording layer" above — this is a static list, not something `recordings.php`'s dynamic `/assets/*` catch-all can resolve, since the dated asset slugs don't match the product-overview post's slug.

**Recording host registration:** once built, add `'product-overview'` to the `momentive_recording_host_types` filter in `recordings.php` — literally the one-line change that file's own comments describe ("adding products later is a one-line filter callback, not an edit here").

**Resources collection:** CLAUDE.md's original "Resources" description explicitly names Product Overviews as one of the legacy collection's content types. Once this CPT exists, add it to `momentive_get_resource_post_types()` (`inc/resources.php`) — one line, filterable, no other change needed, matching how `post`/`case-study`/`webinar`/`whitepaper`/`infographic` were each added.

**ACF field group ("Product Overview Settings"):**

| Field | Type | Notes |
|---|---|---|
| `linked_product` | Post Object → `product`, single, required | Source of truth for the permalink. Replaces the legacy text field of the same name. |
| `hero_image` | Image, return format array | Same optional-override pattern as webinar/whitepaper `hero_image`. |
| `video_embed_code`, `recording_url` | (shared "Recording" field group, reused from webinars) | Enables `inc/recordings.php` host support with no new fields. |

Everything else (form heading, checklist, CTA box, related resources) lives in the post body as blocks/patterns, same "fields for structured data PHP needs to read, blocks for editor-owned content" split already established for whitepapers/infographics/guides.

---

## Per-post summary

| Post | `linked_product` → actual slug | Category | Gated | CTA box | Related resources | `resource_details_after_checklist` |
|---|---|---|---|---|---|---|
| NetForum | `netforum` → `netforumams` ✗ | Association Management | ✓ | ✓ | ✓ | — |
| YM Careers | `ym-careers` → `ym-careers` ✓ | Career Centers | ✓ | — | — | — |
| Cobalt AMS | `cobalt` → `cobaltams` ✗ | Association Management | ✓ | — | — | — |
| YourMembership | `yourmembership` → `yourmembership-ams` ✗ | Association Management | ✓ | — | — | — |
| Nimble AMS | `nimble-ams` → `nimbleams` ✗ | Association Management | ✓ | — | — | — |
| Aptify | `aptify` → `aptify` ✓ | Association Management | ✓ | — | — | — |
| GiveSmart | `givesmart` → `givesmart` ✓ | Fundraising | ✓ | — | — | — |
| Path LMS | `path-lms` → `path-lms` ✓ | Learning Management | ✓ | — | — | — |
| MIP Accounting | `mip-accounting` → `accounting` ✗ | Accounting | ✓ | — | ✓ | ✓ |

---

## Pipeline feature: archive page request

An Asana ticket (forwarded 2026-07-28, not from the legacy site) asks for a dedicated
`/product-overviews/` archive page, "same behavior as `/webinars`." This depends directly
on the CPT decision above — `has_archive` is a CPT registration argument, so the archive
can't be built (or even meaningfully scoped) until `product-overview` exists as its own
CPT per the recommendation in this sheet, rather than the stale "extends existing CPT"
tracker note in `migrations/progress.md`. Full writeup, including a concrete build recipe
based on how `inc/webinars.php` actually implements the `/webinars/` archive today (a
generic `templates/archive.html` fallback, no dedicated template file, plus a sort/grouping
question that doesn't map cleanly onto Product Overview's field data): see
`notes/pipeline-features/product-overview-archive.md`.

---

## Open questions before building

- **Migration matching for the 5 mismatched posts** (NetForum, Cobalt, YourMembership, Nimble AMS, MIP Accounting): a normalized-slug fallback (strip `-ams`/`ams` suffixes, etc.) would probably resolve most of these automatically, same technique the Case Study migration used for product names — but should end with a manual confirmation pass, not a silent auto-match, given the stakes of an incorrect permalink.
- **Picking the "latest" recording per product:** confirmed real via a live spot-check (see "Recording layer" above) — 17 `assets/product-overview/*` posts exist across the 9 products, several products (GiveSmart: 5, MIP: 3, NetForum/YM Careers: 2 each) have more than one, dated by month. The migration should take the max-dated one per product; the older ones are inputs to that comparison, not posts to recreate.
- **CTA box and Related Resources blocks:** both are real (if rare) content on this CPT and don't have an existing block to reuse as-is — small net-new pieces, not large builds.
- **NetForum's CTA button URL** (`/solutions/association-management-software/`) is itself a legacy URL — verify what it should point to post-rebuild before migrating it verbatim.
- **HubSpot form redirect targets:** each product's HubSpot form currently redirects to its old `/assets/product-overview/...` URL after submission — that's a setting inside HubSpot, not WordPress. Needs updating to the new `/recordings/{slug}` URL as part of cutover, separately from anything the migration script can do.
- **`2025-01-yourmembership-product-overview`:** slug says `2025-01`, actual post date is `2026-01-14` — a legacy typo, not a real dating discrepancy. Harmless for the "pick the latest" logic (it's not the latest for that product either way) but worth knowing about if anyone goes looking for a 2025 asset that doesn't otherwise fit the timeline.
