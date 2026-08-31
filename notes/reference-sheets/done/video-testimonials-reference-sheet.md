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

**Reversed (2026-08-19): fold into `videos`, not `testimonial`.** The 2026-08-17 decision
below assumed this content was quote-shaped like a testimonial. Checking the live legacy
page (confirmed the post count is really just 1, and confirmed what the permalink actually
renders) settled the open question this sheet had flagged about the shared `/testimonials/`
namespace: **it's a full singular page** — hero, intro copy, checklist, CTA, embedded video,
"watch now" in place of a form. That's the `videos` CPT's exact shape, not a quote fragment.

This matters architecturally, not just cosmetically: `inc/testimonials.php` registers the
rebuilt `testimonial` CPT with `'public' => false` and `'rewrite' => false` — on purpose,
since every testimonial is meant to be consumed only via the `momentive/testimonial` block
or a Query Loop, never visited directly. Making that CPT routable just for this one post
would mean either giving all 275+ quote-only testimonials real (unwanted) public URLs, or
building a second, inconsistent visibility rule into one CPT's registration. Neither is
worth it for one post. `videos` is already slated to be public and routable, already uses
the same Recording field group + resource-details/checklist/CTA shape this post needs, and
already needs its own template for exactly this content. Fold this post in there instead —
see `notes/reference-sheets/videos-reference-sheet.md`, now updated to 4 posts.

The only real difference from the other 3 `videos` posts is tone (this one is a named-customer
success story; the other 3 are product-demo copy) — a content/copy distinction, not a
structural one. No new field or CPT branching needed for it.

**Superseded:** the `testimonial_type` = `video` term and the `video_embed_code` field added
to "Testimonial Settings" on 2026-08-19 (see `testimonial-merge-plan.md`) are no longer
needed for this purpose. `video_embed_code` has been removed from the ACF group
(`acf-json/group_6a23a12ae0f19.json`, 2026-08-19) since it had no other use — sync in the
ACF UI, and confirm the field is actually gone from the live group afterward (JSON-removed
fields don't always auto-delete from the DB on sync; may need a manual delete in the field
editor). `review_source`/`review_source_link` stay — those still serve the (already-run)
Reviews fold-in.

**Correction (still valid):** earlier drafts of this sheet described `testimonial_type` as
"a select field" already on `testimonial`. It's actually a real WordPress taxonomy
(`inc/testimonials.php`, non-hierarchical) — noted here since the taxonomy itself is still a
useful precedent even though this specific CPT decision reversed.

---

## Open questions before building

- ~~Confirm the true post count~~ **Confirmed — just 1.**
- ~~Whether `/testimonials/{slug}/` was a deliberate shared namespace~~ **Resolved — it's not
  a shared namespace at all; the legacy `video-testimonials` CPT has its own real singular
  template, unrelated to the plain-quote `testimonials` CPT sharing a URL prefix by
  coincidence or a manual slug choice.**
- **Whether `custom_header` ever actually differs from the post title** — still open, but
  now moot for architecture purposes; revisit when building the `videos` CPT's field map if
  a second video-testimonial-shaped post ever surfaces.
