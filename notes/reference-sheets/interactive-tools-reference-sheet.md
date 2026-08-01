# Interactive Tools Rebuild — Reference Sheet (all 4 posts)

Decoded content and architecture notes for the legacy `interactive-tools` CPT — small enough to cover every post.

**Source export:**
- `migrations/momentivesoftware.interactive-tools.current.2026-07-27.xml` — 7 items: 4 `interactive-tools` posts (all published) + 3 attachments.

**What this is — two unrelated sub-patterns sharing one CPT slug, not one thing:**

| Post | `tool-type` category | What it actually is |
|---|---|---|
| Find Your Ideal AMS Fit (2575) | Landing Page | A marketing landing page pitching "take our quiz" — products showcase + hero + CTA linking out to post #2. |
| Find Your Ideal AMS Fit 2 (2584) | Tunnel Page | The actual quiz — a HubSpot form embed framed as "6 simple questions, 1 perfect AMS fit." `noindex`/`nofollow`/`noarchive` — deliberately hidden from search, only reached by clicking through from #2575. |
| Find Your Ideal AMS Fit 3 (2586) | Tunnel Page | **Near-identical duplicate of #2584** — same copy, same layout, a different HubSpot `formId`. Almost certainly an A/B test variant of the same quiz, not a separate tool. |
| Fundraising Thermometer (5558) | _(none — plain `category: Fundraising`)_ | A genuinely different thing: an embedded Elementor-template widget (`[elementor-template id="5559"]` — a donation-tracker/thermometer generator tool) plus an FAQ section. No quiz, no funnel, no HubSpot form on the page itself. |

**This CPT is really "quiz funnels" (3 posts, 1 real quiz) plus one unrelated embedded widget page.** Don't design one schema to cover both — they don't share a real structure beyond both technically being `interactive-tools` posts.

---

## The quiz pages (2575, 2584, 2586) use the exact same field-group family as `lp`

`hero_image`, `underlined_title`/`title_before_underline`/`title_underlined`/`title_after_underline`, `type_of_interactive_section` (`none` / `embed-form`), `main_section_embed_code`, `cta_-_title`/`_button_1_*`/`_desktop_background_image` — these are the identical field names documented in `notes/landing-pages-reference-sheet.md` for the `lp` CPT's hero/CTA shell. **This strongly suggests the legacy site's landing-page builder was reused wholesale for the quiz funnel, under a different CPT slug rather than actually being `lp` posts.** Practically: whatever pattern-based rebuild approach is chosen for Landing Pages should also cover this quiz landing + tunnel pair — it's the same hero/CTA pattern, just chained across two pages instead of one, with a "products showcase" module (`products_-_*` + `product` repeater — 5 AMS products with logo/title/description) that doesn't appear in the `lp` corpus's confirmed-populated fields but exists as a possible module there too.

**Field → destination map (quiz pages):**

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `title_before_underline`/`title_underlined`/`title_after_underline` | Hero heading, with the middle segment styled/underlined | "Find your **ideal** AMS fit" |
| `_description` | Hero intro paragraph | |
| `hero_cta_button_text` + `hero_cta_link` | Hero CTA button, linking landing page → tunnel page | On #2575, points to `/interactive-tools/membership-management-software-quiz-2/` — i.e., directly to #2584's slug. |
| `products_-_title`/`_description` + `product` (serialized, 5 items: `logo`, `description`) | Product showcase grid | Same 5 AMS products (Nimble AMS, etc.) repeated verbatim across all 3 quiz posts — a shared component, not curated per page. |
| `type_of_interactive_section: embed-form` + `main_section_embed_code` | The actual quiz — a HubSpot form | Only on the tunnel pages (2584/2586), not the landing page (2575). Portal ID `46621835`; formId differs between 2584 and 2586. |
| `main_section_title_before_underline`/`_underlined` + `main_section_subheading` | Quiz section heading | Only on 2584 — "6 simple questions. 1 perfect **AMS fit.**" |
| `cta_-_title` + `cta_-_button_1_text`/`_link` | Closing "already know which AMS is right for you?" CTA | Present on all 3, links to `/request-a-demo/` (with UTM params on 2575's version specifically). |
| `rank_math_robots: noindex,nofollow,noarchive,nosnippet,noimageindex` | Do not index | Only on the 2 tunnel pages — the landing page (2575) is meant to be found; the tunnel pages are only meant to be clicked into. |

---

## The Fundraising Thermometer page (5558) is a different animal entirely

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `main_section_shortcode: [elementor-template id="5559"]` | The actual interactive widget | This references a **separate Elementor library template** (post 5559, not in this export) that presumably contains the real thermometer-building tool (likely a form + live preview, built in Elementor's own widget system). **The tool itself isn't capturable from this CPT's fields — it lives in a linked Elementor template that would need its own export/inspection before this page can be faithfully rebuilt.** Flag as a blocker: get the `elementor_library` post 5559 exported before treating this reference sheet as complete for this one post. |
| `faqs_-_title`/`_description` + `faq_item` (serialized, 12 Q&A pairs) | FAQ accordion | Maps directly onto `momentive/accordion` in static mode — no query, just 12 hardcoded items. Clean, no cleanup needed in the sample checked. |
| `faq_cta_title` + `faq_cta_button_text`/`_link` | Closing CTA ("Still have questions? Schedule a Demo") | |
| category | Fundraising | Only post in this CPT using the plain `category` taxonomy instead of `tool-type` — consistent with it not being part of the quiz-funnel pattern. |

---

## Recommendation

- **Fold the quiz landing + tunnel pages into whatever pattern-based approach is chosen for Landing Pages** (see `notes/landing-pages-reference-sheet.md`) — same hero/CTA shell, same underlying page-builder. Rebuild as 2 real pages (landing + quiz), and treat #2586 as a duplicate/A-B-test variant to retire rather than a third page to maintain, unless there's a live reason two quiz form IDs are both still active.
- **The Fundraising Thermometer needs the linked Elementor template (post 5559) exported before it can be scoped** — this is the one piece of "real interactive tool" functionality in the whole CPT, and it isn't visible in the export gathered so far.
- **Given only 4 posts and 2 unrelated shapes, this doesn't need its own CPT** — same reasoning as `events`. The quiz pair becomes ordinary pages (or folds into the LP pattern set); the Thermometer becomes one page with an embedded custom widget once its actual mechanism is understood.

---

## Open questions before building

- **Export the `elementor_library` template (post ID 5559)** referenced by the Fundraising Thermometer — without it, that page's core functionality is unknown.
- **Confirm whether quiz variant #2586 is still receiving traffic** (different HubSpot formId from #2584) before deciding whether to preserve both or retire one as a stale A/B test leftover.
- **Confirm there are no other `interactive-tools` posts beyond these 4** — small export, worth a quick admin-list count same as the other small-corpus CPTs already flagged.
