# Merging Reviews into `testimonial` (Video Testimonials folds into `videos` instead)

Architecture plan for folding `reviews` (86 posts) into the existing `testimonial` CPT.
Read `notes/reference-sheets/reviews-reference-sheet.md` first — this document is the "how
to build it" layer above that sheet's "what's in the legacy data" layer.

**2026-08-19 update:** `video-testimonials` was originally planned to fold in here too (see
the now-superseded section below), but that decision reversed once the sole video testimonial
turned out to have a real singular gated-content-shaped page — incompatible with the
`testimonial` CPT's deliberately non-public, no-rewrite registration in `inc/testimonials.php`.
It folds into the `videos` CPT instead. See `notes/reference-sheets/video-testimonials-reference-sheet.md`
for the full writeup and `notes/reference-sheets/videos-reference-sheet.md` for the updated
4-post scope. The `video_embed_code` field added to "Testimonial Settings" for this purpose
has since been removed — it's `review_source`/`review_source_link` that remain relevant to
this plan now.

**Why this is happening now, not just "eventually":** confirmed (see the reviews reference
sheet's "Confirmed" sections) that at least nine `testimonial` posts already are duplicates
of `reviews` posts — hand-copied and reworded at some point, with no record of who did it or
when, and no structural link between the two. This isn't a clean-slate modeling decision;
it's cleanup of a mess that already exists on the live site.

---

## What's already in place (no schema change needed for this part)

`inc/testimonials.php` already registers `testimonial_type` as a real WordPress taxonomy —
**not an ACF field**, which earlier notes in this project (including this reference sheet's
own first draft, and `notes/todo.txt`) assumed. That distinction matters:

- It's **non-hierarchical** (flat, tag-style), which means WordPress already renders its
  admin UI as a checklist/tag-input, not a locked single-select — a post can already carry
  more than one term. Daniel's question ("should `testimonial_type` allow checking multiple
  values?") is already answered by the taxonomy's own registration: yes, natively, with zero
  code change.
- It's currently seeded with two terms in actual use: `client` (133 posts) and `employee`
  (6 posts) — 136 of 275 rebuilt testimonials have neither term set.
- Adding `review` and `video` as new terms is a `wp_insert_term()` call, not a field
  redesign. The migration script below does this itself on first run.

**So the "architecture" work here is small and additive, not a rebuild:** two new taxonomy
terms, a handful of new ACF fields (below) for review/video-specific data that has nowhere
to live on the current `testimonial` field group, and a migration + dedup pass.

---

## New ACF fields on "Testimonial Settings" (`group_6a23a12ae0f19`) — added 2026-08-19

Current fields on this group: `testimonial_author_name`, `testimonial_author_description`,
`testimonial_author_photo`, `related_case_study`, `related_case_study_url`, plus two added
for this merge (a third, `video_embed_code`, was added at the same time but has since been
removed — see the note at the top of this document):

| New field | Key | Type | Notes |
|---|---|---|---|
| `review_source` | `field_8fe702e2a34a2` | Select (`Capterra` / `G2` / `TrustRadius` / `Software Advice`) | Only relevant for testimonials tagged `review` on `testimonial_type`. Left unconditional (no conditional logic tied to the taxonomy — ACF conditional logic doesn't cleanly target a plain WP taxonomy the way it does an ACF taxonomy-type field) and relies on "renders nothing when empty," same convention as `back-link`/`hubspot-form`. |
| `review_source_link` | `field_b5b41991ca3f5` | URL | The "Read the full review" outbound link. Has real conditional logic — only shows once `review_source` is set, since a link with no source label doesn't make sense. |

**Deliberately no `review_rating` field.** All 86 reviews in the export are 5 stars — per
the reviews reference sheet's own open question, this is very likely editorial curation
(only 5-star reviews were ever added), not a representative sample. A static "★★★★★" badge
next to `review_source` is simpler and exactly as accurate as a 1–5 field that will never
render anything but 5 — add the field later only if a non-5-star review ever surfaces.

**How these were added:** hand-edited directly into `acf-json/group_6a23a12ae0f19.json`
rather than through the ACF admin UI, since Claude has no live access to the WordPress admin
from this environment. This is a deliberate, supported ACF workflow (Local JSON is meant to
be the portable source of truth, and ACF's own "Sync available" mechanism exists precisely
to pull a JSON-side change into the database) rather than a workaround. Daniel confirmed
both fields synced successfully into the live field group. `migrations/migrate-reviews.php`'s
`FK_REV_SOURCE`/`FK_REV_SOURCE_LINK` constants already point at the real keys above, and the
reviews migration has since been run live with good results.

`video_embed_code` (`field_34c22e8ff76b6`) was added in the same batch for the planned
Video Testimonials fold-in, then removed once that decision reversed (see top of document).
Same JSON-edit-then-sync workflow; check the live field group after syncing to confirm the
field actually disappeared, since ACF's local-JSON sync doesn't always auto-delete a
DB-side field just because it dropped out of the JSON.

---

## Resolving the nine-plus confirmed duplicates

Per the reviews reference sheet, at least nine existing `testimonial` posts are edited
copies of `reviews` posts. These need to become **one merged record each**, not two:

| Confirmed pair | Merge direction |
|---|---|
| `Anthony C., Volunteer Manager (G2 Review)` / review "I Love VolunteerMatters" | Keep the existing testimonial post (it already has a job title the review lacks); add `review` term, populate `review_source` = G2. Do not create a second post from the review. |
| `Patricia N., CEO (G2 Review)` / review "So glad we chose Volunteer Matters!" | Same pattern — keep testimonial, tag as `review`, don't duplicate. |
| `Reid S., Donor Relations Manager (G2 Review)` / review "Strong product, good value" | Same pattern. (Note: the *review* side of this pair also has its own internal duplicate — the "(DUPLICATE)" draft post 10054 — already flagged in the reviews sheet; that one gets skipped regardless of this merge.) |
| `Ernie Y., Development Manager (G2 Review)` | No matching `reviews` post exists at all — nothing to merge, just tag the existing testimonial `review` and set `review_source` = G2 with no `review_source_link` (none is recoverable). |
| `Amber B.` / review "Amber Berkey" (TrustRadius) | Keep testimonial (has richer job title), tag `review`, `review_source` = TrustRadius, `review_source_link` from the review. |
| `Bunny R.` / review "Bunny Rosenberg" (TrustRadius) | Same pattern. |
| `Neurocritical Care Society` / review "Becca" | Keep testimonial (organization attribution reads better publicly than a bare first name), tag `review`, source = the review's platform. |
| `Minnesota County Attorneys Association` / review "Stacy" | Same pattern — **but resolve the BlueSky eLEARN vs. Path LMS product-name discrepancy first** (see reviews sheet's open question) before deciding what, if anything, to store as product attribution. |
| `Laura, CME Director, Education` / review "Laura M." | Keep testimonial, tag `review`, source from the review. |

**General rule for all nine:** the existing testimonial post wins (it's already live,
already has whatever added context — job title, org name — someone gave it), and the
matching `reviews` post's metadata (source platform, source link) gets folded into that
*same* post rather than creating a rival copy. The `reviews` post itself does not get
migrated as a separate `testimonial` post once matched — migrating it would recreate the
exact duplication this whole effort exists to remove.

**For the ~77 reviews with no matching testimonial:** migrate normally as new `testimonial`
posts, tagged `review`, per the field map in the reviews reference sheet.

**This dedup list is not guaranteed exhaustive** — per the reviews sheet, it came from a
name-collision-plus-word-overlap pass, not an exhaustive fuzzy match of all 86×275 pairs.
Budget a manual skim during the actual migration for anything this approach missed
(paraphrased duplicates with no shared name fragment at all would be invisible to this
method).

---

## Video Testimonials — superseded, folds into `videos` instead (see 2026-08-19 update at top)

Original plan (kept here for the record): fold the 1 `video-testimonials` post in as
`testimonial_type` = `video`, using a `video_embed_code` field plus the existing
`testimonial_content`/`testimonial_author_name` fields, with the richer body content
(`resource_details` intro, checklist, CTA) kept as plain block content in `post_content`
since it didn't fit the plain-quote `testimonial` rendering.

That last point turned out to be the tell: needing to keep most of the content as raw block
markup outside the CPT's own field structure was a sign this content didn't actually belong
on `testimonial` at all. Confirming the post has a real singular page — hero,
`resource_details`, checklist, CTA, "watch now" button — made it clear this is structurally
the `videos` CPT's own shape (already reference-sheeted, already planned to be public and
routable), not a testimonial with extra decoration. See
`notes/reference-sheets/video-testimonials-reference-sheet.md` for the full reversal
reasoning and `notes/reference-sheets/videos-reference-sheet.md` for the updated 4-post
scope and field map — no new fields needed on `videos` for this post; it uses the same
`resource_details`/checklist/CTA/`wistia_video_id` shape as the other 3.

---

## Migration script

`migrations/migrate-reviews.php` (see that file) handles the Reviews side: standard
WP-CLI-from-WXR pattern (dry-run default, positional `live`/`limit`/`only` args), builds an
index of existing testimonials the same way `migrate-case-studies.php` already does for its
own testimonial create-and-reference step, and applies the nine-pair merge map above as an
explicit lookup table (not fuzzy-matched at run time — these were confirmed by hand and
should be applied as a fixed map, the same "explicit override list" convention already used
elsewhere in this codebase, e.g. `MOMENTIVE_SOL_FORCE_PARENT`) rather than re-deriving them
mechanically on every run.

Video Testimonials no longer needs an entry point here at all — it migrates as part of
whatever script builds the `videos` CPT (not yet written), as a 4th post alongside the
existing 3. See `videos-reference-sheet.md`.

---

## Update (2026-08-19): why category coverage was so low, and a broader dedupe pass

Two more things surfaced while troubleshooting the missing Solution-tint field (see the
chat history around 2026-08-17/19) — both now fixed or scripted, not just diagnosed.

### The legacy site never used the `category` taxonomy for testimonials at all

Daniel provided a fresh full export, `migrations/exports/momentivesoftware.testimonials.current.2026-08-19.xml`
(157 posts). It shows the legacy site tracked Solution family via a plain ACF **text**
field, `solution_family` (short slugs: `assn-mgmt`, `event-mgmt`, `learn-mgmt`,
`accounting`, `fundraising`, `careers`, `vol-mgmt`, `crt-mgmt`, `data-analytics`) —
**0 of 157 legacy posts have a native `category` term at all.** 147/157 have
`solution_family` populated. That field was apparently never translated into a real
`category` assignment during the original testimonials migration, which is the main reason
coverage on the rebuilt site sits at 98/275 — not a one-time regression, a mapping step that
never happened.

`migrations/patch-testimonials-solution-category.php` (new) backfills this: maps each
`solution_family` slug to its rebuilt category slug, and sets it on the matching rebuilt
post — but **only when that post currently has no category at all**, so it can never clobber
something already correct. Matches by legacy post ID (preserved 1:1 on the rebuilt site,
same convention as Solutions/Case Studies). Dry-run by default.

**Not quite a clean 1:1 map on first pass** — a live dry run turned up two mismatches:
`event-mgmt`/`careers`'s rebuilt category slugs didn't match the pattern the other seven
families follow. Traced this through two rounds:

1. First assumed the rebuilt category slugs matched the Solution *page's* permalink slug
   instead (`event-management-software`/`career-centers-software`) — a plausible theory,
   since Daniel had set them deliberately at some point.
2. Checking again, that theory was also wrong: the live category slugs were actually
   `event-technology`/`career-services` — carried over as-is from the legacy site, which
   used inconsistent slugs for these two families specifically (not matching either the
   Solution permalink or the other seven families' pattern).

Confirmed via a full codebase search that the Solution↔category tint relationship
(`get_solution_color_for_term()`, `momentive_get_solution_term_map()` in `inc/solutions.php`)
is entirely slug-independent — it resolves through the category term's `related_solution`
ACF post-object field, never the slug string — so renaming a category slug can't break that
relationship. On that basis, Daniel renamed both live category terms to
`event-management`/`career-centers`, matching this map's existing pattern, with a redirect
left in place from the old `/blog/category/event-technology/`/`/blog/category/career-services/`
archive URLs as insurance against any external/bookmarked links. `MOMENTIVE_TSF_SLUG_MAP` now
reflects the current (renamed) slugs — see the comment above it in the script itself.

Note: 59 of the 157 posts in this fresh export don't exist on the rebuilt site yet at all —
logged by the patch script as "not found," but out of its scope. **Resolved 2026-08-19: see
"Gap-fill migration" below** — `migrations/migrate-testimonials.php` now imports exactly
those posts.

### Testimonials created via other CPTs' migrations never got a category either

Confirmed Daniel's other hunch: `migrate-case-studies.php`'s testimonial
create-and-reference step (`momentive_cs_create_testimonial()`) never set a category on the
testimonials it creates — it only had quote/name/description to work with, no host-post
context. Fixed: the function now accepts the host case study's own already-resolved
category term IDs and applies them to the new testimonial, on the reasoning that a quote
attached to a case study almost always belongs to that case study's own Solution family.
(`migrate-solutions.php`'s testimonial handling only *resolves* existing testimonials by ID
— it doesn't create new ones, so it needed no equivalent fix.)

That fix only covers testimonials created **after** it landed — everything the Case Study
migration created before 2026-08-19 (the large majority of its create-and-reference output,
since that migration ran well before this date) is still missing a category, with no
`solution_family` meta to backfill from (these were never part of the legacy `testimonials`
CPT at all, so `patch-testimonials-solution-category.php` can't see them). **Resolved: see
"Case-study-sourced testimonial category backfill" below** — a separate patch script for
this specific gap.

---

## Gap-fill migration: the 59 legacy testimonials never imported at all (2026-08-19)

`migrations/migrate-testimonials.php` (new). Imports every legacy testimonial from
`momentivesoftware.testimonials.current.2026-08-19.xml` whose post ID doesn't already
resolve to a `testimonials` post on the rebuilt site — i.e., exactly the ~59-post gap this
document flagged above, nothing else. Same WP-CLI-from-WXR conventions as every other
migration in this project (dry-run default, `live`/`limit=`/`only=` positional args).

Key points:

- **Writes the quote to real `post_content`, not `testimonial_content`.** This surfaced a
  real bug while building this script — see "Blank-quote bug" below. Reuses
  `MOMENTIVE_MT_SLUG_MAP` (a duplicate of `MOMENTIVE_TSF_SLUG_MAP`, kept in sync by hand,
  same "small enough to duplicate" call made elsewhere in this codebase) for
  `solution_family` → category, and maps the legacy `testimonial_type` postmeta
  (`client`/`employee`) to the real `testimonial_type` taxonomy (same value mapping as
  `backfill-testimonial-type-taxonomy.php`).
- **`import_id` preserves the legacy post ID** on every new post — the same convention
  `migrate-solutions.php` uses, and the one `patch-testimonials-solution-category.php`'s own
  gap-detection check depends on. Re-running `patch-testimonials-solution-category.php`
  after this migration should show 0 "not found" instead of ~59.
- **`related_case_study` resolution is genuinely harder than it looks**, because
  `migrate-case-studies.php` upserts Case Study posts **by slug**, not `import_id` — case
  study IDs are NOT preserved 1:1 from legacy the way Solutions/Testimonials are. The script
  builds its own legacy-case-study-ID → slug → rebuilt-post-ID map from the Case Study WXR
  export, rather than trusting the raw legacy ID. Unresolved cases are logged, not guessed
  at, and fall back to the (usually-empty) `related_case_study_url` text field.
- **Author photos mostly won't resolve.** Only 13 attachment `<item>`s exist in this
  targeted export against ~43 distinct photo IDs referenced across the 157 posts — this
  export was a CPT-only pull, not a full media export. Photos that don't resolve are logged
  and left empty rather than broken/guessed; add manually from the live legacy site if
  wanted for a specific post.
- **Duplicate guard:** before creating anything, the script indexes every existing published
  testimonial's normalized quote text (same normalization as `migrate-case-studies.php`'s
  `momentive_cs_norm_quote()`) and skips (with a warning, not a silent skip) any legacy post
  whose quote matches one already live — protecting against re-creating something the
  original early ad hoc import already brought in under a different post ID. Use
  `only=<legacy_id>` to force one through after manually confirming it's genuinely distinct.

---

## Case-study-sourced testimonial category backfill (2026-08-19)

`migrations/patch-testimonials-case-study-category.php` (new). Covers the gap described
above: testimonials created by `migrate-case-studies.php`'s create-and-reference step before
the 2026-08-19 category fix landed, which have no `solution_family` meta to backfill from
(they were never part of the legacy `testimonials` CPT) and no `related_case_study` backlink
either (that field is never set on this creation path — the relationship only exists in the
other direction, as a `momentive/testimonial` block comment inside the case study's own
`post_content`).

Approach: scan every published `case-study` post's content for
`<!-- wp:momentive/testimonial {"testimonialId":N,...} /-->` (same block-walk shape as the
existing read-only `report-testimonial-references.php`, just inverted — collecting the host
case study alongside each referenced testimonial ID instead of just listing references), then
for each referenced testimonial with no category of its own, backfill from **its referencing
case study's own category terms** — not a `solution_family` lookup, since these testimonials
have none. Same "never overwrite an existing assignment" rule as the other two category
patches. If a testimonial is referenced by case studies in more than one category (expected
to be rare — the same quote reused across two Solution families), it uses the first match and
logs a warning for manual review rather than guessing further.

**Run these three category-related scripts in this order**, since each covers a distinct,
non-overlapping source of the missing-category problem: `patch-testimonials-solution-category.php`
(legacy `solution_family` field — the largest group) → `migrate-testimonials.php` (brings in
the 59 gap posts, which also carry `solution_family`, so running the solution-category patch
first vs. after only matters in that it makes the gap-fill's own `solution_family` handling
redundant, not wrong — the gap-fill script sets category directly, it doesn't depend on the
patch having run) → `patch-testimonials-case-study-category.php` (the separate,
non-`solution_family`-backed case-study-sourced group).

---

## Blank-quote bug found while building the gap-fill migration (2026-08-19)

While building `migrate-testimonials.php`, checked what `blocks/testimonial/block.php`
actually reads to render the quote: **`$post->post_content` only — never a
`testimonial_content` field or meta value.** Both `migrate-reviews.php` and
`migrate-case-studies.php`'s `momentive_cs_create_testimonial()` wrote the quote via
`update_field( 'testimonial_content', $quote, $post_id )` **after** `wp_insert_post()`,
without ever setting `post_content` — so every testimonial either script created rendered a
genuinely blank `<blockquote>` on the live front end, with no error and no visible sign in
the block editor preview (the editor doesn't re-fetch `testimonial_content` either; it just
shows an empty paragraph block area, easy to miss on a quick glance).

This is exactly the bug the *existing* `migrate-testimonials.php` (now renamed to
`patch-testimonials-content-backfill.php`, since it turns out to be a standing safety net
rather than the one-time throwaway its old docblock called it) was already built to fix —
just not yet re-run since the reviews/case-study scripts most recently wrote more of this bad
data. **Both migration scripts are now fixed** (quote is set directly on `post_content` at
insert time). Since the fix is only forward-looking, **run
`patch-testimonials-content-backfill.php live` once to backfill every testimonial already
affected** — this covers the full history of `migrate-case-studies.php`'s create-and-reference
output (that bug predates this discovery entirely) plus whatever `migrate-reviews.php`
created before this fix. Safe to run anytime; it only touches posts with empty
`post_content` and a non-empty `testimonial_content` value.

Also updated `migrate-reviews.php`'s merge branch (the nine confirmed review↔testimonial
duplicates from the section above): when merging a review into an existing testimonial,
if that testimonial has no category yet, it now inherits the review's own category rather
than being left blank.

### A real, separate duplication problem: exact-duplicate testimonial posts

While comparing the fresh legacy export against the rebuilt corpus, a normalized-quote
comparison (same technique `migrate-case-studies.php` already uses for its own testimonial
matching) found **11 clusters of exact-duplicate testimonials already live on the rebuilt
site — 12 "extra" posts** beyond one canonical copy each. This is exactly what Daniel
suspected: the same content entered via more than one path (the dedicated testimonials
migration, plus Case Study/Solutions create-and-reference steps) that didn't recognize each
other as the same testimonial, most likely because the quote text itself differs slightly
between the two copies (a bracketed edit, light rewording) even though it's clearly the same
person and story — enough to defeat the substring-based fuzzy match those scripts use.

Canonical post per cluster was chosen by **ACF field completeness**, not just "keep the
older post" — in several clusters the *later* duplicate actually has a `related_case_study`
link the original never got, so blindly preferring the lower ID would have thrown away real
data:

| Duplicate | Canonical | Why |
|---|---|---|
| #2513 | #519 | #519 has a photo + related case study; #2513 has neither. |
| #3222, #11161 | #12150 | #12150 additionally has an author description. |
| #12119 | #5317 | #5317 has a related case study; #12119 doesn't. |
| #12120 | #6053 | Tied on fields; kept the older post. |
| #12121 | #7217 | Tied on fields; kept the older post. |
| #7218 | #12122 | #12122 additionally has a related case study. |
| #7220 | #12123 | #12123 additionally has a related case study. |
| #12148 | #10049 | Tied on fields; kept the older post. |
| #11151 | #10145 | #10145 has a photo, and already uses the project's shortened-name convention ("Steve D." vs. "Steve Davis"). |
| #11159 | #12149 | #12149 additionally has a description and related case study. |
| #11149 | #12124 | #12124 additionally has a related case study. |

`migrations/patch-testimonials-dedupe.php` (new) handles the cleanup: for each duplicate, it
sweeps every post's `post_content` site-wide for a hardcoded `momentive/testimonial` block
referencing that post (`"testimonialId":N`) and rewrites it to the canonical ID, then moves
the duplicate to **Trash** (not a permanent delete — easy to undo if a cluster call turns
out wrong on closer inspection). It does not need to touch Query Loop usages, since those
resolve dynamically by post type/taxonomy and will simply stop returning the trashed post.
Dry-run by default.

**This dedup pass covers only exact-normalized-quote duplicates** — the same caveat as the
review↔testimonial dedup above applies here too: a more thorough, closer read (especially of
the 59 not-yet-migrated legacy posts once they're brought over) would likely turn up a few
more near-duplicates that differ enough in wording to dodge this mechanical check.

---

## Open questions before running live

- ~~Get the new ACF fields created and update the field-key constants~~ **Done (2026-08-19)**
  — fields added to `acf-json/group_6a23a12ae0f19.json` and keys wired into
  `migrate-reviews.php`. Still needed: **sync them into the live database** via the ACF
  admin's "Sync available" notice on "Testimonial Settings" before running anything live —
  see the section above.
- **Confirm the Stacy/BlueSky eLEARN → Path LMS product-name question** before merging that
  specific pair.
- **Decide whether `review_source`/`review_source_link` should use ACF conditional logic**
  keyed to the `testimonial_type` taxonomy, or just rely on "empty renders nothing" — worth
  a quick check of whether ACF's conditional logic UI can target a taxonomy field on this
  version of ACF Pro before assuming it's available.
- ~~Confirm the true Video Testimonials post count~~ **Resolved — it's genuinely 1, and it's
  no longer part of this plan anyway (folds into `videos` instead, see top of document).**
- **Run the manual dedup skim** mentioned above during the actual migration, not just before
  it — new duplicates could plausibly be found once someone is looking at all 86 reviews and
  275 testimonials side by side rather than via a mechanical pass.
- ~~The 59 legacy testimonials with no rebuilt counterpart yet~~ **Resolved — see
  "Gap-fill migration" above, `migrate-testimonials.php`.**
- **Spot-check a few of the 11 dedupe clusters by eye before running `patch-testimonials-dedupe.php`
  live** — the canonical choice was made mechanically from field completeness, which is a
  good signal but not proof the two posts are word-for-word the same testimonial rather than,
  say, two different quotes from the same person.
- **Re-run `patch-testimonials-solution-category.php` after `patch-testimonials-dedupe.php`**,
  not before — trashing a duplicate could leave its canonical twin as the one now missing a
  category if they'd resolved differently, worth a quick re-check of coverage numbers once
  both have run.

---

## Missing pieces found after the reviews migration ran live (2026-08-19)

Two gaps surfaced once Daniel started spot-checking the already-migrated reviews:

### 1. Category field invisible on the testimonial edit screen

`inc/testimonials.php` hides WordPress's native category checklist panel in the block editor
(`removeEditorPanel('taxonomy-panel-category')`) — the category assignment was only ever
visible via the admin list-table column, never on the edit screen. A dormant filter already
existed for the intended replacement — `acf/fields/taxonomy/query/name=testimonial_solution`,
scoping a field named `testimonial_solution` to children of the "Solutions" category — but
the field itself had never actually been added to "Testimonial Settings." Added now
(`field_c19a4f5e6b201`, single-select taxonomy field on `category`, mirroring the identical
`faq_solution`/`product_category` fields on FAQ/Product Settings). Sync it in the ACF UI the
same way as the other recent field additions.

### 2. Review headline text was being discarded, not just mis-placed

The legacy `reviews` CPT used its own post title as the reviewer's real headline (e.g. "Best
Investment Ever") — `reviews-reference-sheet.md` said to keep this verbatim. But
`migrate-reviews.php` set the rebuilt post's title to the reviewer's attribution name instead
(e.g. "Amber B."), matching the rest of the testimonial CPT's title convention — sensible on
its own (especially once a review can be tagged onto an existing plain testimonial post,
where the title needs to stay attribution-shaped, not headline-shaped), but it meant the
headline text had nowhere to go and was silently dropped.

Fixed: added `review_headline` (`field_d2c7e0a19f402`, text, conditional on `review_source`
being set — same pattern as `review_source_link`) to "Testimonial Settings." Also fixed
`migrate-reviews.php` to populate it going forward, in both the CREATE and MERGE branches.

**A backfill was needed since the reviews migration already ran live**: the headline text
itself was never destroyed — it's still sitting in the same legacy WXR export
`migrate-reviews.php` already reads — so `migrations/patch-testimonials-review-headline.php`
(new) re-reads that export and backfills `review_headline` on both the newly-created posts
(matched via the `_momentive_source_review_id` meta stamped at creation) and the 9 confirmed
merge targets (matched via a local copy of `MOMENTIVE_REV_CONFIRMED_MERGES`). Never overwrites
an existing value; safe to re-run.

**Bonus finding while fixing this:** `migrate-reviews.php`'s CREATE branch had no existence
check before inserting, despite the header comment's long-standing claim that the script
"upserts by a stamped `_momentive_source_review_id` meta key... so re-running updates in
place." That claim was wrong — a second live run would have created a duplicate post for
every non-merged review. Fixed by adding the missing lookup before the `wp_insert_post()`
call. Since the migration has already been run live exactly once, this is very likely
harmless in practice (no known second live run has happened) — but worth being aware of if
anyone was ever tempted to re-run it "just to be safe" before this fix landed.
