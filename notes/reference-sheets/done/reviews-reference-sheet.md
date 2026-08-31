# Reviews Rebuild — Reference Sheet (86 posts)

Decoded content and architecture notes for the legacy `reviews` CPT.

**Source export:**
- `migrations/momentivesoftware.reviews.current.2026-07-20.xml` — 86 `reviews` posts (85 published + 1 draft explicitly titled "(DUPLICATE)").

**What this is:** each post is a single third-party review quote — reviewer name, star rating, source platform, and a link to the review on that platform — syndicated from Capterra/G2/Software Advice/TrustRadius onto the site. Structurally this is the simplest CPT in the pending-migration list: no gating, no rich body sections, no form. It's much closer to `testimonial` than to any of the gated-content CPTs (whitepaper/infographic/toolkit/product-overview).

---

## Field → destination map

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| post title | Post title | Reviewer-written headline, e.g. "Cloud based functionality makes Abila an attractive option." Kept verbatim — these read as real review titles, not filler. |
| `post_content` | Post content (review body) | **Already clean native Gutenberg paragraph blocks** (`<!-- wp:paragraph -->`) in every post checked — this CPT was apparently entered directly into the block editor rather than Elementor/Classic, so it needs no HTML-to-block conversion and no Word-artifact cleanup. The one CPT in this project where that's true. |
| `review_name` | Reviewer attribution | Already in the project's name-shortening convention (first name + last initial, e.g. "Kanul D.", "Peter A.") — no transformation needed, unlike testimonials' legacy full-name-to-shortened-name migration step. |
| `review_date` (Unix timestamp) | Post date | Spans 2015–2025 (see distribution below) — these are the *original review* dates, not migration/import dates. Preserve via the same `$wpdb->update()` post-date pattern used for Case Studies/Webinars/Whitepapers (since `wp_insert_post` would otherwise force "now"). |
| `review_rating` | Star rating display | **Every single one of the 86 reviews is a 5.** This is a curated set (only 5-star reviews were ever added to this CPT) — not a representative sample of all reviews received. Confirm this is intentional editorial curation before assuming a rating field is even worth building as a variable display; a static "★★★★★" per card may be simpler and equally accurate than a dynamic 1–5 renderer that will never render anything but 5. |
| `review_rating_source` | Source platform label/badge | 4 distinct values: `Capterra` (46), `Software Advice` (18), `TrustRadius` (15), `G2` (7). Worth a small icon/logo per source if the rebuilt card design wants one — no source appears misspelled or inconsistently cased. |
| `review_rating_source_link` | "Read the full review" outbound link | Points at the specific review's anchor on the source platform (e.g. `https://www.capterra.com/p/116591/MIP-Accounting/#SoftwareAdvice___193057`). The URL path also reveals which **product** the review is about (see below) — this is the only field that ties a review to a product, and only indirectly. |
| category terms | Native category panel | Every post has exactly one Solution-scoped category (Accounting, Fundraising, Event Management, Learning Management, Volunteer Management, Career Centers, Association Management, Data Analytics). Same shared taxonomy as testimonials/products/resources. |

**Fields NOT migrated (plugin cruft, same convention as every other CPT):**
- `rank_math_seo_score`, `rank_math_internal_links_processed` — Rank Math.
- `wp_last_modified_info`, `_wplmi_last_modified` — Post Meta Auto Update plugin.
- `_lmt_disableupdate`, `_wp_old_date` — housekeeping, not content.
- `reviews_headline`, `detailed_review` — present on only 4/86 posts, both empty in every case checked. Dead fields; don't build for them without finding a populated example first.

---

## Product attribution lives only inside the source URL — no direct field

Unlike testimonials (`related_case_study`) or product overviews (`linked_product`), reviews have **no post-object or taxonomy field pointing at a specific Product post.** The only signal is a Capterra product slug embedded in `review_rating_source_link`, and only when the source is Capterra:

| Capterra URL slug | Review count |
|---|---|
| _(not a Capterra link, or no slug pattern matched)_ | 40 |
| Event-Tech-Suite | 9 |
| VolunteerMatters | 8 |
| YM-Careers | 8 |
| GiveSmart | 8 |
| MIP-Accounting | 7 |
| Path-LMS | 2 |
| Freestone | 2 |
| Nimble-AMS | 1 |
| YourMembership | 1 |

If the rebuilt site wants to filter/group reviews by Product (e.g. show only MIP reviews on the MIP overview page), that relationship would need to be **added** at migration time — either a new Post Object field resolved from this URL pattern (with the same "5/9 mismatches expect manual review" caution the Product Overview migration already hit when matching legacy text slugs to real Product posts), or simply rely on the existing category taxonomy, which is coarser (solution-family, not product-specific) but already reliable on all 86 posts.

**G2/TrustRadius/Software Advice links don't follow this slug pattern at all** — the product-matching approach above only recovers attribution for the 38/86 posts sourced from Capterra with a matching URL, leaving the rest identifiable only by category.

---

## Duplicate and near-duplicate content

**One explicit duplicate, already flagged by the legacy editor:** post 10054, "Strong product, good value (DUPLICATE)" (draft), is a near-verbatim copy of post 9925, "Strong product, good value" (published) — same reviewer name (Reid S.), same review text. **Don't migrate the draft** — it's marked for exactly this reason.

**Five further title-identical or near-identical pairs, not flagged, worth a manual check before migrating both:**

| Pair | Note |
|---|---|
| "Cloud based functionality makes Abila an attractive option" (8369, 9933) | Same title. Check body text for exact duplication vs. two distinct reviewers who happened to write the same headline. |
| "Will Meet All Your Non-Profit ACCOUNTING Needs" (9927, 9935) | Same title. |
| "GiveSmart helped us to Pivot!" (9970, 9987) | Same title. |
| "Best Investment Ever" (9968, 9991) | Same title. |
| "MIP Reporting is second to none" / "MIP Reporting is second to none." (9707, 9929) | Differ only by trailing period — almost certainly the same review entered twice. |

None of these carry a "(DUPLICATE)" marker the way 10054 does, so a migration script shouldn't silently drop them — flag for the same kind of manual dedup pass the Case Study migration did for testimonials, rather than an automatic rule.

---

## Rating and date distribution (context, not a migration concern)

- **Ratings:** 86/86 are 5 stars. No 1–4 star review exists in this CPT.
- **Dates:** 2015 (5), 2016 (12), 2017 (5), 2018 (5), 2019 (6), 2020 (13), 2021 (11), 2022 (8), 2023 (7), 2024 (10), 2025 (4). Roughly even spread, no single-year concentration worth special-casing.
- **Categories:** Fundraising (20) and the rest of the Solution families cluster around 6–10 each — no category is unrepresented.

---

## Confirmed: several rebuilt `testimonial` posts are already edited duplicates of these reviews

Checked directly against the export data (2026-08-17), prompted by Daniel's hunch that
Reviews and Testimonials were built independently, by different people, without anyone
checking for overlap. **Confirmed — and worse than a coincidence, it's provably the same
underlying content, hand-copied and lightly rewritten with the source metadata stripped.**

**Four testimonials say so directly, in their own title:** `Anthony C., Volunteer Manager
(G2 Review)`, `Patricia N., CEO (G2 Review)`, `Reid S., Donor Relations Manager (G2
Review)`, and `Ernie Y., Development Manager (G2 Review)` — someone labeled these
"(G2 Review)" right in the post title, and three of the four (`Anthony C.`, `Patricia N.`,
`Reid S.`) are **verbatim or near-verbatim copies** of reviews already sitting in the
`reviews` export under those exact same names. The fourth, `Ernie Y.`, has no matching post
anywhere in the reviews export — meaning at least one G2 review was hand-copied straight
into a testimonial and never got its own `reviews` CPT entry at all. **That means the
`reviews` CPT is not even a complete inventory of "reviews that became testimonials"** —
some went straight to testimonial and skipped the CPT entirely.

**Five more are unlabeled but just as clearly the same content, reworded:**

| Review (source) | Testimonial | What changed |
|---|---|---|
| Amber Berkey (TrustRadius), "GiveSmart- Just Do It" | `Amber B.`, Fundraising and Development Officer, Nonprofit | Same sentence structure and phrase ("cohesive fundraising strategy, better donor communication, and a better giving experience") almost word-for-word; job title added, rating/source dropped. |
| Bunny Rosenberg (TrustRadius), "Easy to use for galas and auctions!" | `Bunny R.`, Director of Marketing & Communications, Nonprofit | Same story (registration/sponsorship/auction for an annual gala) condensed and punched up into marketing copy ("It's AWESOME."). |
| Becca, "Slick Functionality, Fabulous Support Staff" (Path LMS review) | `Neurocritical Care Society` | Near-verbatim, re-attributed from the reviewer's name to the customer organization's name instead. |
| Stacy, "The only way to go!" (review is literally about **BlueSky eLEARN**, not Path LMS) | `Minnesota County Attorneys Association` | Same sentence structure, but **the product name was changed** from BlueSky eLEARN to Path LMS when it was turned into a testimonial — worth flagging to whoever owns product naming, since this could be a legitimate rebrand/successor-product situation or could be a real misattribution. |
| Laura M., "Very happy with this software" (Path LMS review) | `Laura, CME Director, Education` | Near-verbatim, first name kept, last name dropped and a job title added. |

**What this means:** this isn't a hypothetical modeling question anymore — it's confirmed
that reviews have already been informally "merged" into testimonials by hand, at least nine
times, with no structural link between the two records and the review's source/rating
metadata thrown away in the process. A real merge doesn't just avoid *future* duplication;
it needs to resolve these nine (at least) existing duplicate pairs so the site doesn't
publish both the review and its rewritten testimonial twin as if they were two different
customers.

**Two further internal review/testimonial dedup categories, for completeness:**
- The 6 review-vs-review duplicate pairs already documented below (in "Duplicate and
  near-duplicate content") are a separate issue from this section — those are duplicates
  *within* the `reviews` export itself, not between `reviews` and `testimonial`.
- This name/content-matching pass was not exhaustive — it's what turned up from checking
  every first-name-plus-last-initial collision between the two corpora, plus a broader
  word-overlap sweep. A more thorough pass (fuzzy-matching every review against every
  testimonial, not just name-collisions) would very likely turn up a few more; budget time
  for a manual skim of the full 275-testimonial corpus during the actual migration, not just
  a mechanical name match.

---

## Confirmed: reviews only ever appear on `/reviews/` — nowhere else on the site

Checked directly against the live legacy site (2026-08-17), since Colleen's answer on this
didn't quite address what was asked. Two findings:

1. **`/reviews/` is a single filterable hub**, not a plain archive — it has dropdown
   filters for "Resource Categories," "Solution Features," and "Reviews Categories" all on
   one page, alongside review cards, testimonial quotes, and resource cards mixed together.
   This matches Colleen's answer ("needed category filtering functions") — that's almost
   certainly *why* this became its own dedicated page rather than a widget: reviews needed
   to be sliceable by category, and a single filterable page was the mechanism.
2. **Product pages don't embed reviews.** Checked the MIP Accounting product page — it has
   a "Customer Voices"-style quote section, but those entries are shaped like `testimonial`
   posts (name, title, organization, quote) with no star rating, no source badge, and no
   "Read Full Review" link back to G2/Capterra/TrustRadius/Software Advice. Reviews always
   carry that source attribution; these don't. So the quotes visible elsewhere on the site
   are testimonials, not reviews — the two are easy to mistake for each other at a glance
   but are structurally distinct, and only one of them (testimonials) is reused site-wide.

**This strengthens the fold-into-`testimonial` recommendation below** rather than
complicating it: since reviews only ever render inside one filtered grid, rebuilding that
grid as a `testimonial_type == review` filter on the existing `testimonial` Query Loop is
simpler than standing up a second CPT with its own archive just to combine it with
testimonials on the same page anyway.

## Recommendation

**Decided (2026-08-17): fold into `testimonial`.** This is a small, low-risk migration
relative to the gated CPTs — no layout branching, no form handling, no Word-cleanup step,
content already in blocks — and the duplicate evidence above settles what was previously an
open architectural question: `testimonial` is already the de facto home for this content,
just an unofficial and inconsistent one. One correction to earlier notes in this sheet and
in `notes/todo.txt`: **`testimonial_type` is a real, already-registered WordPress taxonomy**
(`inc/testimonials.php`, non-hierarchical, currently seeded with `client` and `employee`
terms on 139/275 posts) — **not an ACF select field** as earlier drafts of this sheet and
`recommended-sequence.md` assumed. That's good news for Daniel's multi-value question below:
non-hierarchical taxonomies already support assigning more than one term per post out of the
box (WordPress renders them as a checkbox/tag-style list, not a locked single-select) — no
field-architecture change is needed to let a post carry both `review` and, say, `client`.
Adding `review` and `video` as new terms is a one-line `wp_insert_term()` call, not a schema
change. Full merge plan (new ACF fields needed for review/video-specific data, dedup
handling, taxonomy terms): see `notes/reference-sheets/testimonial-merge-plan.md`.

---

## Open questions before building

- ~~Keep as its own CPT, or fold into `testimonial`?~~ **Decided — fold in, see above.**
- **Is the all-5-star pattern curation (only good reviews were ever added) or a filtered export?** If curation, a static star display is simpler and just as accurate as a dynamic renderer for a value that never varies.
- **Worth adding real product attribution** (a Post Object field to `product`, resolved from the Capterra URL slug where possible) or is the existing category-level granularity sufficient? Only recoverable for 38/86 posts without a manual pass, per the slug table above.
- **Six duplicate/near-duplicate content pairs** (see table above) — resolve with a manual review pass before or during migration, same convention as testimonial dedup during the Case Study migration. Separate from, and in addition to, the nine-plus review↔testimonial duplicates above.
- **The Stacy/BlueSky eLEARN → Minnesota County Attorneys Association/Path LMS product-name swap** (see above) — confirm with whoever owns product naming whether this is a legitimate rebrand reference or a genuine misattribution before migrating either record.
