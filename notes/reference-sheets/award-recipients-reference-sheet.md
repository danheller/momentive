# Award Recipients Rebuild — Reference Sheet (all 4 posts)

Confirms and details the plan already noted in `CLAUDE.md`/`notes/todo.txt`: "4 posts built for one page (`/bring-on-better-awards/`) — fold into a block pattern, retire the CPT." This reference sheet exists to give that plan the actual content, not to change the recommendation.

**Source export:**
- `migrations/momentivesoftware.award-recipients.current.2026-07-27.xml` — 9 items: 4 `award-recipients` posts (all published) + 5 attachments.

**What this is:** the 2025 "Bring on Better Awards" cohort — one post per award category, each naming a winning organization (or, in one case, an individual) with a short write-up and a link to the announcement blog post. All 4 posts share `year_awarded: 2025` — this is a single year's cohort, not an ongoing archive with history across years (at least not yet).

---

## Field → destination map

| Legacy field | Notes |
|---|---|
| `award_received` | The award category/title: "Association of the Year," "Leader of the Year," "Nonprofit of the Year," "Innovator of the Year" — one of each, no duplicates. |
| `year_awarded` | `2025` on all 4. |
| `organization_name` | Recipient organization. Also duplicated into the post title on 3/4 posts (post title = org name); the 4th (Angel Baltimore) has both the individual's name and org name in the title, with `organization_name` holding just the org. |
| `organization_logo` | Attachment ID, present on all 4. |
| `individual_recipient` (bool) + `individual_name` + `individual_photo` | **Only 1/4 posts** ("Leader of the Year" — Angel Baltimore, National Apartment Association) names an individual rather than an organization. The other 3 awards are organization-level; this field distinguishes which card layout a given award needs (photo+name+title vs. just an org logo). |
| `award_recipient_description` (HTML) | A short paragraph, generally including one outbound link to the recipient's own site. Clean HTML in all 4 samples — no Word-artifact cruft to strip here. |
| `related_blog_post` (post ID) | **3/4 posts link to a blog post** (presumably each award's own announcement post) — Military Women's Memorial ("Nonprofit of the Year") has none. Resolve as a Post Object reference into `post`, not a raw ID column, if rebuilding as real fields; if folding straight into a hand-built pattern, this can just become a hardcoded "Read the announcement" link per card. |

---

## Recommendation (unchanged from CLAUDE.md/todo.txt — confirmed by this data)

Four posts, one page, no ongoing archive need visible in this data (all one year, no taxonomy/relationship suggesting future cohorts query dynamically). **Fold into a block pattern for `/bring-on-better-awards/`** — four cards, each with logo/photo, name, award title, description, and an optional "read more" link to the related blog post — and retire the CPT. No migration script needed; this is small enough, and final enough, to hand-build directly into the pattern.

**If a 2026 cohort is expected**, worth a one-line confirmation with Daniel/Greg before retiring the CPT outright — four static cards works fine for a single year, but a second year would raise the same "does this need to be queryable/dynamic" question this reference sheet doesn't have data to answer on its own.

---

## Open questions before building

- **Will there be a 2026 (or later) awards cohort?** If yes, decide once whether future years get their own hand-built page/pattern (matching this year's approach) or whether it's worth a light data structure (even just page sections per year) instead of fully static markup — before building the 2025 page in a way that would need reworking for year two.
- **Resolve the 3 `related_blog_post` IDs** (8846, 8088, 8244) to their actual rebuilt Blog posts (if those posts have been migrated/rebuilt) before finalizing each card's "read more" link.
