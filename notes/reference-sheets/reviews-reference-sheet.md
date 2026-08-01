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

## Recommendation

This is a small, low-risk migration relative to the gated CPTs — no layout branching, no form handling, no Word-cleanup step, content already in blocks. The main pre-migration decision is scope: **keep vs. retire**, per `notes/todo.txt`'s open question list. Given the content is real (curated 5-star third-party reviews, not placeholder data) and reasonably fresh (10 reviews from 2024, 4 from 2025), retiring outright would lose real social-proof content; more likely this either migrates as-is into a rebuilt `review` CPT (singular, matching the project's singular-CPT-key convention already used for `webinar`/`whitepaper`/`case-study`), or gets folded into `testimonial` as a `testimonial_type` variant (that CPT already has a `testimonial_type` select field) rather than staying a fully separate CPT. Worth a quick architectural call before scripting, similar to the Video Testimonials question already on the list — these two "should this fold into `testimonial`?" questions are likely worth deciding together rather than separately.

---

## Open questions before building

- **Keep as its own CPT, or fold into `testimonial` via `testimonial_type`?** `testimonial` already has fields this content doesn't need (`author_photo`, `related_case_study`) but the shape (name + quote + attribution) is otherwise identical. Deciding this alongside the Video Testimonials question (`notes/todo.txt`) avoids two separate half-answers to the same underlying "is `testimonial` the universal home for short third-party endorsements?" question.
- **Is the all-5-star pattern curation (only good reviews were ever added) or a filtered export?** If curation, a static star display is simpler and just as accurate as a dynamic renderer for a value that never varies.
- **Worth adding real product attribution** (a Post Object field to `product`, resolved from the Capterra URL slug where possible) or is the existing category-level granularity sufficient? Only recoverable for 38/86 posts without a manual pass, per the slug table above.
- **Six duplicate/near-duplicate content pairs** (see table above) — resolve with a manual review pass before or during migration, same convention as testimonial dedup during the Case Study migration.
