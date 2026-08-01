# Donation Examples Rebuild — Reference Sheet (32 posts)

Decoded content and architecture notes for the legacy `donation_examples` CPT.

**Source export:**
- `migrations/momentivesoftware.donation-examples.current.2026-07-27.xml` — 71 items: 39 attachments + 32 `donation_examples` posts (all published).

**What this is:** a filterable showcase gallery of **real GiveSmart customer campaigns** — each post is one card: an event name, a screenshot, a short description, the organization's name, and an outbound link to that org's actual live GiveSmart-hosted fundraising page. This is marketing-by-example — "see what other organizations have built with GiveSmart" — not editorial content, and structurally the simplest of the CPTs in this batch: no page-builder field group, no HubSpot form, no gating, just a card.

---

## Field → destination map

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| post title | Card title (event/campaign name) | E.g. "Hope Parade Event," "Bloomfest," "Rogers Golf Outing." |
| `de_summary` (HTML) | Card description | Short, 1–3 sentence description of the campaign. Clean HTML in the samples checked. |
| `de_example_image` (attachment ID) | Card screenshot | Present on all 32 — a screenshot of the actual live GiveSmart campaign page. |
| `de_campaign_link` | "See it live" outbound link | **Missing on 2/32 posts** — the card's whole point is this link, so decide the fallback now: hide the link/button entirely when empty (same "empty is not an error" convention as `hubspot-form`/`back-link`), don't leave a dead button. |
| `de_example_title` | Organization name | Despite the field name, this holds the customer org's name (e.g. "ICU Baby," "Easter Seals Oregon"), not a second "example title." Rename to something like `organization_name` if rebuilding as a real ACF field, to avoid perpetuating the confusing legacy name. |
| `de_example_summary` | Secondary blurb, if used | **5/32 posts have literal placeholder text here** — see below. Confirm real copy exists (or write it) before migrating this field's content verbatim. |
| `fundraising_features` (taxonomy, multi) | Filter facet — feature type | Donations (27), Ticketing (19), Auctions (17), Raffles (12), Peer-to-Peer (6), Instant Buys (6), Voting (1). Multi-select per post. |
| `organization_type` (taxonomy, single per post in samples checked) | Filter facet — org type | Human Services (13), K-12 Schools and Higher Ed (5), Arts and Culture (4), Youth Services (3), Animal and Conservation (3), Healthcare and Research (2), Civic and Social (1), Associations (1). |

**Fields NOT migrated:** `general_information_tab`/`general_information`, `de_alternate_title`, `de_example` — all empty across every post checked; leftover unused fields from the CPT's field group.

---

## Real content quality issues — flag, don't migrate verbatim

- **5/32 posts (16%) have literal "Lorem ipsum dolor sit amet..." placeholder text in `de_example_summary`.** This is unfinished content shipped to production, same category of issue as the "Bro, this is a test" accordion item found on the published AFP ICON `events` post — worth a combined content-QA pass across CPTs rather than fixing one-off.
- **2/32 posts have no `de_campaign_link`** — the card's core call-to-action is missing on these two. Confirm whether the link should be backfilled (the organization presumably still has a live campaign somewhere) or the card retired.

---

## Recommendation

This is a strong candidate for a **query-mode block reusing the existing filter infrastructure**, not a page-by-page rebuild. The two taxonomies map directly onto a filterable grid — the same shape `momentive/resource-filters` already provides for other archives (proximity-targeted Query Loop + AJAX filter bar). A small migration script is straightforward: 32 posts, one consistent field shape, no layout branching, no gated-content shell to worry about. Given the volume and uniformity, this is closer to Testimonials or FAQ in migration complexity than to any of the flexible-page-builder CPTs in this batch.

---

## Open questions before building

- **Fix or confirm the 5 lorem-ipsum placeholder summaries** before migrating — either real copy exists somewhere and wasn't entered, or these need to be written.
- **Backfill or retire the 2 posts missing `de_campaign_link`.**
- **Confirm `organization_type` is genuinely single-select per post** (true in the samples checked) before deciding whether it's a taxonomy or a simple select field in the rebuilt schema.
- **Should this live under GiveSmart specifically, or as a general "customer campaign showcase"?** Every example in this export is a GiveSmart campaign — confirm whether that's because Donation Examples is GiveSmart-specific by design, or just because GiveSmart happens to be the only product with this kind of gallery today.
