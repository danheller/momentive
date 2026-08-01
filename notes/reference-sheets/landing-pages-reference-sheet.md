# Landing Pages Rebuild — Reference Sheet (166 posts: 161 published + 5 drafts)

Decoded content and architecture notes for the legacy `lp` CPT — by far the largest of the pending-migration content types (more posts than Case Studies or Webinars). This is a strategic scoping sheet, not a per-post field map — 166 posts isn't a "cover every post" corpus like the smaller CPTs; the goal here is to show that the real variety is much smaller than 166, and to give a migration script enough to work from.

**Source export:**
- `migrations/momentivesoftware.lp.current.2026-07-27.xml` — 185 items: 166 `lp` posts (161 published, 5 draft) + 19 attachments.

**What this is:** paid-marketing/PPC landing pages — one per product, per use case, per funnel stage, or per competitor comparison. Every post is set `rank_math_robots: noindex` — these are ad-destination pages, never meant to be found organically, which matters for how much migration fidelity is worth investing per post.

---

## The field group is a kitchen-sink — but real pages use a small, consistent slice of it

Every one of the 166 posts carries **the same ~300 postmeta keys**, covering dozens of optional modules: hero, benefits, integrations/feature-row, checklist grid, icon grid, tile grid, small tiles, testimonials, partners gallery, case-study highlight, competitor comparison table, guide/whitepaper embed, webinar/presenters, research-study fields, two-column layouts, three different CTA-section variants. This is the same "legacy field group carries every module the page builder ever offered, most unused per post" pattern already documented for Solutions in CLAUDE.md — **don't build for every field that exists; build for the fields that are actually populated**, confirmed below.

**A real page (e.g. "GiveSmart - Silent Auction Software") populates roughly 25 fields out of ~300.** The four dedicated **"Sample" posts already in this export are literal reference templates** the legacy team built to show each shape's full palette — `5213` (BOF Sample), `5209` (MOF Sample), `5206` (TOF Sample), `5214` (Competitor Comparison Sample). Use these as the spec for "what's possible," and real posts of each type as the spec for "what's actually used" — the Sample posts have every optional field switched on for demonstration; real posts use a lean, consistent subset.

---

## `select_landing_page_type` is the real content-shape switch

| Type | Count | What it is |
|---|---|---|
| `BOF` (bottom of funnel) | 126 | "See it in action" / request-a-demo pages, one per product × use case. The dominant shape by far (76% of all posts). |
| `MOF` (mid funnel) | 20 | Narrower feature/use-case pages, e.g. "MIP - Accounts Payable / Purchasing," "YourMembership - Chapter / Committee Management." Same shell as BOF, narrower topic. |
| `Competitor Comparison` | 9 | "GiveSmart vs [Competitor]" pages with a feature-by-feature comparison table. |
| `TOF` (top of funnel) | 6 | Awareness-stage pages — a webinar recap, a referral-program page, a marketplace page, an interactive demo. The least uniform group; each is closer to a one-off than a template instance. |
| _(none set)_ | 5 | The 4 Sample posts + 1 test page (see below). |

## Product breakdown (the BOF/MOF majority)

| Product prefix | Posts | Typical page |
|---|---|---|
| GiveSmart | 32 | Auction/fundraising use-case pages (silent auction, mobile bidding, church giving, capital campaign, text-to-give…) + 6 competitor comparisons |
| YourMembership | 24 | AMS feature/use-case pages |
| MIP | 21 | Accounting feature/use-case pages |
| PathLMS | 19 | LMS feature/use-case pages |
| NimbleAMS | 16 | AMS feature/use-case pages |
| Netforum | 16 | AMS feature/use-case pages |
| Aptify | 15 | AMS feature/use-case pages |
| _(one-off campaign pages)_ | 23 | See below |

143 of 166 posts (86%) are one of these 7 products' BOF/MOF pages — this is the part worth scripting. The remaining 23 are one-off campaign pages (referral program, marketplace partner page, event recap, cross-sell test, competitor comparisons not tied to a single product use-case) that don't cluster into a repeatable shape and are better hand-rebuilt individually if they're still active.

---

## Field → destination map (the BOF/MOF shell — confirmed against real pages, not just the Sample templates)

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `landing_page_hero_-_title` / `_description` | Hero heading + intro | E.g. "See GiveSmart in action" / "Get a guided walkthrough focused on silent auction software." |
| `landing_page_hero_list_heading` + `landing_page_hero_list_items` (serialized) | Hero benefit list ("How GiveSmart helps") | 3 short bullet items, typical. |
| `landing_page_hero_hubspot_form_script` | Inline HubSpot form embedded directly in the hero (not a separate section) | This is the primary conversion point — the form is *in* the hero, not below the fold. Portal ID always `46621835`. |
| `product_logo` (serialized, image URL) | Product wordmark shown in hero | |
| `integrations_-_title` + `integrations_-_description` + `integration_item` (serialized, icon+title+description rows) | A 2–3 column icon-feature row | Misleadingly named — this is a generic "what you get" feature-icon row, not necessarily about third-party integrations (e.g. "A walkthrough tailored to your organization and goals" is one item's text). |
| `partners_gallery_-_title` + `type_of_gallery` | Logo strip section | `type_of_gallery` picks which stock set of customer logos renders (`fundraising`, etc. — a taxonomy-like enum, not per-post logos). |
| `cta_enable_cta_lp_section` + `cta_lp_title`/`_description`/`_button_1_text`/`_button_1_link` | Closing CTA band | Button link is almost always `#form` (anchor back to the hero form) rather than a separate page. |
| `solution_family`, `guide_type` | Category/solution scoping | Present on every post but low-signal — same value (`fundraising`, `guides`) recurring across many posts in a product family; likely set once and left unchanged rather than curated per page. |
| `rank_math_robots: noindex` | **Do not index** | Confirmed on every post — carry this forward on the rebuilt pages; these were never meant to rank. |

## Additional fields — Competitor Comparison variant only (9 posts)

| Legacy field | Notes |
|---|---|
| `competitor_section_title` / `_description` | E.g. "Compare GiveSmart vs BiddingOwl." |
| `momentive_product_logo` / `competitor_product_logo` (attachment IDs) | Two logos side by side. |
| `add_comparison_info` (serialized, ~14 rows: `product_feature_cell`, `momentive_product_check`, `competitor_product_check`) | The actual comparison table — a feature name plus two booleans (has it / doesn't have it). Straightforward to render as a two-column checkmark table. |
| `add_competitor_info` | A second, differently-shaped comparison field present alongside `add_comparison_info` on some posts — confirm which one is actually rendered on the live page before assuming both are used; the BOF Sample template has both populated for demonstration, but that doesn't confirm real usage. |

---

## Test, duplicate, and sample posts — exclude these from any migration count

| Post | Reason |
|---|---|
| 3595 — `[TEST] Cross-Sell/Upsell Page` | Test |
| 4631 — `[TEST] Prism Form` | Test |
| 5179 — `Test All sections` | Test |
| 5206 / 5209 / 5213 / 5214 — `TOF/MOF/BOF/Competitor Comparison Sample` | Internal templates, not real campaign pages — use as spec, don't migrate as content |
| 6820 — `Test All sections (DUPLICATE)` | Explicit duplicate |
| 7780 — `GiveSmart - Requirements / Security / Compliance (DUPLICATE)` (draft) | Explicit duplicate of published 7779 |
| 8209 — `YourMembership - Phrase- Brand Core (DUPLICATE)` (draft) | Explicit duplicate |
| 8210 — `YourMembership - Legacy Brand (YM / YourMembership) (DUPLICATE)` (draft) | Explicit duplicate |
| 2748 — `Momentive Software + Cobalt: Stronger Together for the Road Ahead` (draft) | Never published — confirm still wanted before migrating |
| 7791 — `GiveSmart - Golf Outing Fundraising` (draft) | Never published — confirm still wanted |

That's 11 posts (4 Sample templates + 4 explicit duplicates/tests + 2 more drafts, minus the 1 test that's also a draft counted once) to exclude from the "real content" count out of 166 — leaving **~155 real candidate pages**, of which the 143 product BOF/MOF pages are the scriptable majority.

---

## Recommendation

**This is the opposite conclusion from `events`** (2 posts, 2 unrelated shapes → hand-rebuild from patterns) despite both CPTs sharing the same "one flexible field group, many optional modules" architecture. Here, 143 of 166 posts collapse into one real shape (BOF/MOF hero-with-inline-form + feature row + logo strip + CTA) varying mainly by product and use-case copy — that's exactly the kind of volume-with-a-pattern that has justified a migration script everywhere else in this project (Case Studies, Webinars, Whitepapers, Solutions). Recommend:

1. **A migration script for the BOF/MOF majority** (143 posts), modeled on the whitepaper/infographic scripts' "extract shared shell, branch on one type field" shape — except the branch here barely matters, since BOF and MOF use the identical field set at different topic granularity.
2. **A second, smaller script pass (or the same script, one more branch) for the 9 Competitor Comparison posts** — same shell plus the comparison table.
3. **Hand-rebuild the ~6 TOF posts and the non-duplicate one-off campaign pages individually** — too few and too varied to script, same reasoning as `events`.
4. **An editorial pass before migrating anything**, not after: these are ad-destination pages, and PPC campaigns rotate. Worth asking marketing which of the 143 product pages correspond to *currently running* ad campaigns before spending migration effort on all of them — a stale "GiveSmart - Text-to-Give (Church)" page for a campaign that ended last year doesn't need the same priority as one still receiving paid traffic. This is the one CPT in this project where "is this still needed" is as important a filter as "how do we migrate it," because unlike a blog post or case study, an LP's value is entirely tied to whether an ad still points at it.

---

## Open questions before building

- **Get the currently-active-campaign list from marketing/ads before prioritizing which of the 143 product pages to migrate first** — see recommendation above.
- **`add_comparison_info` vs. `add_competitor_info`** on Competitor Comparison posts — confirm only one is actually rendered before building support for both.
- **The 2 un-published drafts** (2748, 7791) — confirm intent before deciding whether they're in scope at all.
- **Whether `solution_family`/`guide_type` are curated or copy-pasted defaults** — low apparent variance suggests the latter; worth a quick spot-check across a few more posts per product before trusting them as real categorization data.
