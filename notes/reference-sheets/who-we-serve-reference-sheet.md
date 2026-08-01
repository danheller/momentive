# Who We Serve Rebuild — Reference Sheet (6 published + 2 draft)

Decoded content and architecture notes for the legacy `who-we-serve` CPT — **not previously identified in `CLAUDE.md`, `PROJECT-SUMMARY.md`, or `notes/todo.txt`.** This reference sheet exists partly to answer "what is this," which turns out to matter: it's very likely the actual content behind an already-rebuilt piece of the new site.

**Source export:**
- `migrations/momentivesoftware.who-we-serve.current.2026-07-27.xml` — 42 items: 34 attachments + 6 published `who-we-serve` posts + 2 drafts.

---

## What this is: audience/industry-segment marketing pages — and it's the same thing as "Industries"

| Post | Status | URL |
|---|---|---|
| Foundation | publish | `/who-we-serve/foundation/` |
| Nonprofit | publish | `/who-we-serve/nonprofit/` |
| Community | **draft, only 8 fields populated** | — |
| Education / K-12 / Higher Ed | publish | `/who-we-serve/educational-institutions/` |
| Tribal / First Nations | **draft, only 8 fields populated** | — |
| Associations | publish | `/who-we-serve/associations/` |
| Government Software | publish | `/who-we-serve/government/` |
| Healthcare & Medical | publish | `/who-we-serve/healthcare-medical/` |

**This is what `notes/todo.txt` and `PROJECT-SUMMARY.md` call "Industries"** — "these are effectively pages; rebuild as standard page posts" — just under the legacy site's own name for the section (`who-we-serve`) rather than the generic "Industries" label the planning docs used. Confirming this matters because **the rebuilt theme already has `parts/megamenu-who-we-serve.html`** (per `CLAUDE.md`'s FSE templates list) — a megamenu panel almost certainly built to link out to exactly these pages. Before building anything from this reference sheet, check what that panel currently links to; there's a real chance it's already pointing at placeholder or dead URLs waiting for this content to exist. **Don't plan this as a brand-new "Industries" build and separately as a "fix the megamenu" task — they're the same task.**

---

## Content shape: bespoke marketing pages, same tier as Solutions hub pages — not a scriptable migration

Each published post is a full, hand-crafted landing page with 10+ sections: hero, statistics, a challenges/features section, an accordion (deep product-fit copy — one example runs 3,492 characters), testimonials (curated lists of specific testimonial post IDs), a resource list (curated post IDs), FAQs, a solutions/sessions carousel, and in one case an embedded customer success video. The copy is genuinely different per audience — Nonprofit talks about "40+ years serving purpose-driven organizations" and MIP; Associations talks about "500-member professional society or 500,000-member trade federation." **This is not a template with swapped-in variables** the way the Toolkits or Product Overview corpora are — it's closer to the Solutions hub-tier pages, which CLAUDE.md already documents as deliberately hand-built and explicitly excluded from the Solutions migration script ("hub content is bespoke and hand-built, same decision already made for Products").

**Recommend the same treatment here: hand-build each page from patterns, don't write a migration script.** With only 6 real posts (2 are empty drafts), the effort of writing a generalized extractor for a ~50-field, heavily-nested schema this bespoke would likely cost more than just rebuilding six pages by hand using the same section-pattern library the Solutions hub pages and (per its own reference sheet) `events`/`lp` are already recommended to draw from — hero, statistics, accordion, feature-challenges, testimonials, FAQ, resource list, carousel. Several of these patterns will already exist once the Solutions hub-page and Landing Page pattern work happens; this becomes largely a matter of reusing them with audience-specific copy.

---

## Field → destination map (high-level — not a full field dump, given the "hand-build, don't script" recommendation)

| Legacy field | Notes |
|---|---|
| `industry_icon` (SVG URL) | Small icon representing the audience — likely what the megamenu panel or an audience-picker grid would show. |
| `number_served` + `number_served_after` | A single stat used as a headline number, e.g. `2.4` + `K` = "2.4K" nonprofits served, `1.3` + `K` for associations. Worth wiring through `momentive/impact-stat` (already built, animated count-up) rather than static text. |
| `hero_-_title`/`_description`/`_button_1_*`/`_button_2_*`/`_image` | Standard hero. |
| `statistics_-_stats` (serialized, 4 items: `number_prefix`/`number`/`number_suffix`/`description`/`accent_color`) | A 4-stat band — another natural fit for `momentive/impact-stat`, which already supports prefix/number/suffix. |
| `accordion_items` (serialized) | Deep-dive product-fit content, 4–6 items — some carry heavy Word-artifact contamination (`TextRun`/`SCXW*` spans, confirmed on the Associations post) needing the standard cleanup. |
| `features_-_*` + `features` (serialized, 3 items) | "Challenges facing [audience]" — icon + title + description rows. |
| `testimonials` (serialized, list of post IDs) | Curated testimonial picks per audience — same "curated list of specific IDs" pattern as `linked-products`, not an automatic query. Resolve against the rebuilt `testimonials` CPT. |
| `resouces_list` (serialized, list of post IDs — **field name is misspelled in the legacy schema**, not a typo introduced here) | Curated resource picks, same curated-ID pattern. |
| `faq_item` (serialized) | FAQ accordion content, 5–6 Q&A pairs per post. |
| `sessions-carousel-items` (serialized, ~6–8 items, same rich per-item shape as the `events` CPT's carousel) | A "complete suite of solutions" carousel — another confirmed instance of the sessions-carousel field shape recurring across `events`, `lp`, and now this CPT. |
| `success_video_-_*` (Nonprofit post only) | An embedded Wistia customer-success video + quote — not present on every post; check per-page before assuming it's a standard section. |
| `solution_features` (serialized list of post IDs) | Yet another curated-ID list, this time of specific Solution/feature posts. |

**Recurring theme across this and every other flexible-page CPT in this batch:** curated lists of specific post IDs (testimonials, resources, solution features) rather than automatic Solution-scoped queries. Unlike `momentive/solution-resources`'s deliberately automatic design, every one of these audience pages hand-picks its supporting content — closer to `linked-products`' curated pattern. Worth deciding once whether audience pages should stay hand-curated (matches legacy, more editorial control) or move to an automatic query keyed off a `category`/audience taxonomy (less editor effort, matches the `solution-resources` philosophy) — the same category of decision already made once for Solutions resources and worth applying consistently rather than re-deciding per CPT.

---

## Recommendation

1. **Treat this as the answer to the "Industries" open question**, not a separate content type — same URL family (`/who-we-serve/{slug}/`), same job (audience-segment marketing pages), and it directly informs `parts/megamenu-who-we-serve.html`.
2. **Hand-build all 6 real pages from patterns** (hero, stats via `momentive/impact-stat`, accordion, features, testimonials, FAQ, resource list, carousel) — no migration script, same reasoning as Solutions hub pages.
3. **Skip the 2 drafts (Community, Tribal/First Nations) for now** — 8 populated fields each is essentially an empty shell; there's no real content yet to migrate.
4. **Audit `parts/megamenu-who-we-serve.html`'s current links** before or alongside this build — there's a good chance it's already wired for URLs this content needs to fill in.

---

## Pipeline feature: Nonprofit page redesign

An Asana ticket (forwarded 2026-07-28, not from the legacy site) asks for the Nonprofit
page specifically (`/who-we-serve/nonprofit/`) to be rebuilt on a new Figma template
("Who We Serve-sub-v2"), ahead of the same template rolling out to the other five pages in
this family. This is the first concrete page in the "hand-build all 6 from patterns"
recommendation above — not new or unplanned scope, just a specific template and sequence
assigned to it. It also confirms the megamenu suspicion below directly: `parts/megamenu-who-we-serve.html`
currently links to `/solutions/momentiveiq/` and `/products/aptify/`, not to any real
Who We Serve URL. Full writeup, including how the new template's section structure may not
map 1:1 onto this sheet's legacy-derived field map: see
`notes/pipeline-features/who-we-serve-nonprofit-redesign.md`.

---

## Open questions before building

- **What does the megamenu panel currently link to?** Check before assuming this is greenfield work.
- **Community and Tribal/First Nations** — confirm whether these are actively planned (worth building placeholder pages now) or abandoned drafts (skip entirely).
- **Curated vs. automatic supporting content** (testimonials/resources/solution-features lists) — decide once, consistently, per the note above.
- **Confirm the full 6-post corpus is really everything** — no count was previously logged anywhere for this CPT, same caution already raised for the other freshly-discovered exports in this batch.
