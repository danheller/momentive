# Integrations Rebuild — Reference Sheet (62 posts)

Decoded content and architecture notes for the legacy `integrations` CPT — the simplest content type in this entire migration project.

**Source export:**
- `migrations/momentivesoftware.integrations.current.2026-07-27.xml` — 129 items: 67 attachments + 62 `integrations` posts (all published).

**What this is:** a third-party integration/partner directory — think "logos of everything Momentive products connect to" (ABA MCLE, ACCME PARS, Adobe Connect, Aptify, Authorize.Net, CE Broker, Credly, Drupal, and 54 more). Confirmed across all 62 posts: **no body content, no excerpt, nothing beyond a title, a logo, and two taxonomy terms.** This isn't a gap in the export — it's the whole CPT. Every post is, functionally, one directory card: name + logo + filter tags.

---

## Field → destination map

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| post title | Card label | The integration/partner's name. |
| `_thumbnail_id` | Card logo | Present on all 62. |
| `integrations-type` (taxonomy, one per post in every sample checked) | Filter facet — what kind of system | AMS/CRM (26), Webinar Platform (5), Payment Gateway (5), CE System (4), Proctoring/Exam System (4), Analytics (2), Badging System (2), Community (2), Protocols (2), and 11 more terms with 1 post each (IPaaS, CMS, Federated Search, Marketing Automation, Path API, Mobile, Event Management System, etc.). |
| `integrations-capabilities` (taxonomy, multi-select) | Filter facet — what it does | SSO (41), eCommerce (27), Activity Writebacks (25), Teams (11), Data (6), Virtual Events (5), Content (2), External Credit Sync (1). |

That's the entire schema. No description field, no "which Momentive product does this integrate with" relationship, no outbound link to the partner's own site — none of that exists anywhere in this CPT's data.

---

## Recommendation

Given there's zero unique content per post beyond name + logo + two taxonomies, this is a straightforward **filterable logo grid**, not a set of individual landing pages — nobody visits `/integrations/authorize-net/` expecting a page; the taxonomy-filtered directory view is the entire product. Two real implementation choices, both reasonable:

1. **A lightweight CPT** (title + featured image + the two taxonomies, nothing else) driving a filterable grid — matches the "one post per instance" convention every other content type in this project uses, and makes adding a new integration a normal "add new post" action for whoever maintains this list.
2. **A single repeater field on one static Integrations page** — avoids standing up a whole CPT/taxonomy pair for something with no unique per-item page value, but makes the list editable only by someone comfortable with a repeater field rather than the standard "new post" flow, and loses the two taxonomies' natural fit as registered `wp_terms` (useful if the same taxonomy vocabulary is ever reused elsewhere, e.g. tagging which integrations a given product page highlights).

**Leaning CPT** given the taxonomy-driven filtering is real and would need to be rebuilt some other way under option 2, but this is a low-stakes, easily-reversible call either way — worth deciding based on who maintains this list going forward more than on data modeling purity.

**No migration script needed regardless of which option is chosen** — 62 posts, 3 fields each (title/logo/taxonomies), trivially fast to migrate by hand or with a five-line script; not worth the same reference-sheet-driven scripting investment as the richer CPTs in this project.

---

## Open questions before building

- **CPT vs. repeater-on-a-page** — see above; low stakes, pick based on maintenance ownership.
- **Should each integration link to the partner's own site, or to a Momentive product page that highlights it?** Neither exists in the legacy data — worth deciding if the rebuilt grid should add either, or stay purely visual (logo + filter tags, no click-through at all, matching the legacy site's apparent behavior).
- **Is a "which Momentive product does this integrate with" relationship worth adding?** Not present in legacy data; if the rebuilt grid should filter by product family in addition to type/capability, that's new modeling, not a migration of existing structure.
