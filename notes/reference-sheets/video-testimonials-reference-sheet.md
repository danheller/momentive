# Video Testimonials Rebuild — Reference Sheet (1 post in this export)

Decoded content and architecture notes for the legacy `video-testimonials` CPT.

**Source export:**
- `migrations/momentivesoftware.video-testimonials.current.2026-07-27 (1).xml` — 2 items: 1 published `video-testimonials` post + 1 attachment.

**Caution — this export may not be the full corpus.** Every other targeted export in this project (Videos: 3 posts, Interactive Tools: 4 posts, Award Recipients: 4 posts) matches the post count `notes/todo.txt` already expected. This export contains exactly 1 post with no count previously logged anywhere for this CPT — worth confirming with a broader admin-list count on the legacy site before assuming this single post is everything, the same caution already flagged when the Product Overview reference sheet was built from a single-post-type targeted export.

---

## What this is

A customer-story video page — one Wistia-embedded video plus a short description, a benefits checklist, and a closing CTA sentence. No form, no gate, no download link. Structurally almost identical to the `videos` CPT already reference-sheeted, but the content itself reads as a **case-study-style customer testimonial with video**, not a product demo:

- **Title:** "Modernizing veterinary regulation with Nimble AMS"
- **Permalink:** `https://momentivesoftware.com/testimonials/ams-abvma/` — **shares the `/testimonials/` URL namespace with the regular `testimonials` CPT.** Either the legacy site registered both CPTs onto the same rewrite base, or this is one specific case where a video testimonial was manually slugged to sit alongside the text testimonials. Worth checking the legacy admin for how the CPT's rewrite is actually configured before assuming this is the general pattern — it changes whether "fold into `testimonial`" is a data-modeling nicety or something the legacy site already effectively did at the URL level.
- **Subject:** Alberta Veterinary Medical Association (ABVMA), a Nimble AMS customer — a real named-customer success story, same shape as a Case Study, just video-led instead of prose-led.

---

## Field → destination map

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `custom_header` | Display title override | Identical to the post title in this one example — likely an override field that's rarely different from the title, same low-signal pattern worth confirming across more posts before building a dedicated ACF field for it. |
| `wistia_video_id` | Video embed | `sbkdhn0omw`. Same reliable field as the `videos` CPT — use this over `video_embed_code`, which is empty here. |
| `resource_details` (HTML) | Intro paragraph | **Heavy Word-artifact contamination** — `TextRun`/`SCXW*`/`BCX*`/`NormalTextRun` spans throughout, same cleanup as every other resource CPT. |
| `resource_checklist_title` + `resource_checklist` (serialized, 3 items) | Benefits checklist | `resource_checklist_title` is empty on this post — checklist renders with no heading. Items: "Real-time reports and dynamic dashboards...", "Reliable and worry-free updates 3x/[year, truncated]...", plus a third not fully captured here. |
| `details_cta` | Closing CTA sentence | "See how ABVMA is using Nimble AMS to drive efficiency, elevate service, and lead the future of veterinary regulation in Alberta." |
| `_thumbnail_id` | Featured image | Present. |
| category terms | Native category panel | Association Management. |
| post excerpt | Post excerpt | Present, customer-story framing ("The Alberta Veterinary Medical Association (ABVMA) chose Nimble AMS to transform how it serves members..."). |

**Fields NOT migrated (dead defaults — identical pattern to every other CPT built on this field group):**
- `enable_gated_content: false`, `hubspot_form_code`/`form_heading` empty — confirmed ungated, same as `videos`.
- `resource_enable_quote_box` + `resource_quote*` — false/empty. Notable because a *quote box* would be a very natural fit for a testimonial-shaped post, and it exists as a field, but isn't used on this example. Check a second post (once found) before ruling it out as always-dead.
- `resource_hero_image`, `hero_video_source`/`hero_library_video`/`hero_link_video`, `video_module`/`enable_video_module` — same dead-default pattern as every resource CPT.
- `enable_cae_credits_module`, `enable_insights_section`, `enable_related_resources`, `enable_cta_box`, `cta_-_*`, `series_section*` — all false/empty.

---

## Recommendation

This single example reads as a case study, not a product demo — the customer is named, the story is about their organization's outcome, and the CTA is "see how [customer] is using [product]," not "watch this feature walkthrough" (the framing every `videos` CPT post uses instead). Combined with the permalink already sitting under `/testimonials/`, the strongest signal here is: **fold into `testimonial` with a `testimonial_type` of `video`** (that field already exists as a select on the `testimonial` CPT), reusing the shared Wistia-embed mechanism rather than building a fourth near-identical "resource_details + checklist + video" CPT after `videos`, `product-overview`, and now this. This is the same recommendation the Reviews reference sheet raised independently — worth deciding both at once, since they're really one question ("what's the single home for short third-party/customer endorsements, with or without video?") asked twice.

If a second video-testimonial post surfaces with a real `resource_quote` filled in, that further strengthens the case — testimonials are fundamentally quote-shaped, and this CPT already carries an unused quote field that would finally get used.

---

## Open questions before building

- **Confirm the true post count.** This export has 1 post; get an admin-list count from the legacy site before treating this reference sheet as exhaustive.
- **Fold into `testimonial` (recommended) vs. keep standalone** — decide alongside the same question raised in `notes/reviews-reference-sheet.md`.
- **Whether `custom_header` ever actually differs from the post title** — only testable once more posts are available; if it's always identical, don't build a redundant field for it.
- **Whether `/testimonials/{slug}/` was a deliberate shared namespace or a one-off manual slug** — check the legacy CPT's rewrite registration if possible.
