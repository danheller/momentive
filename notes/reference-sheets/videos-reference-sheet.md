# Videos Rebuild — Reference Sheet (all 3 posts)

Decoded content and architecture notes for the legacy `videos` CPT — only 3 posts exist, all covered here.

**Source export:**
- `migrations/momentivesoftware.videos.current.2026-07-01.xml` — 6 items: 3 published `videos` posts + 3 attachments (card images). No drafts.

**What this is:** a minimal "watch this video" landing page — a Wistia embed plus a short description and checklist, no HubSpot form, no presenters, no date. All 3 existing posts are MIP Accounting product videos. `notes/todo.txt` already flagged this as "no form, just a watch now button" and floated folding into webinars or a minimal standalone CPT — this reference sheet confirms that read and adds detail to decide between the two.

---

## Field → destination map

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `wistia_video_id` | Video embed (Wistia player) | Present on all 3 — this is the real, current mechanism. `video_embed_code` (a `<script>` embed) is only populated on 1/3 (see below); `wistia_video_id` alone is reliable across all posts. |
| `resource_details` (HTML) | Intro paragraphs | Word-artifact cleanup applies — same patterns as every other resource CPT. |
| `resource_checklist_title` + `resource_checklist` (serialized) | Checklist ("You'll learn" / "Why MIP is the right choice") | Present on all 3, 2–4 items each. Items may contain inline `<b>`/`<strong>` — preserve as rich text. |
| `details_cta` | Closing CTA sentence, e.g. "Watch the video now for a quick overview..." | Present on all 3. This is the *only* call-to-action on the page — there's no form and no download link, just "watch." |
| `_thumbnail_id` | Featured image | Archive card image, present on all 3. |
| category terms | Native category panel | All 3 are `Accounting` (all three are MIP Accounting product videos — the entire legacy corpus happens to be single-product). |
| post title, excerpt, date | Post title, excerpt, date | |

**Fields NOT migrated (dead defaults, confirmed empty/false — same convention as every other resource CPT):**
- `enable_gated_content` is `true` on all 3, but `hubspot_form_code` and `form_heading` are **empty on all 3** — the "gated" flag is a leftover default from the shared field group, never actually wired to a form. Do not build a form section for this CPT.
- `hero_video_source` (hardcoded `wistia`), `hero_library_video`, `hero_link_video` — dead, same as every other CPT that carries this field group.
- `enable_cae_credits_module`, `cae_credits_module`, `cae_credits_text`
- `enable_insights_section`, `content_tab`, `content_title`, `content_description`, `insights_list`
- `resource_enable_quote_box`, `resource_quote*`
- `enable_related_resources`, `related_resources_*`, `manual_post_list`
- `enable_cta_box`, `resource_cta_*`
- `resource_hero_image` — present as a *field* but empty on all 3; the featured image (`_thumbnail_id`) is the only image these posts actually use.
- `resource_link`, `resource_link_text`, `resource_link_open_in_new_tab` — empty; no download link exists alongside the video.

---

## One post (Meet MIP Dashboards, #1428) carries dead leftover fields the other two don't

Post 1428 is the only one of the 3 built in the Elementor canvas (`_elementor_data`, `_elementor_edit_mode: builder` present) and the only one carrying a full parallel **webinar** field set that is **not used**:

| Legacy field | Status |
|---|---|
| `webinar_details`, `webinar_checklist_title`, `webinar_checklist` | Duplicate content of `resource_details`/`resource_checklist_title`/`resource_checklist` on the same post — same underlying copy, just present under both the webinar-template field names and the resource-template field names. Use the `resource_*` versions (matches the other 2 posts); ignore `webinar_*`. |
| `webinar_video_link` | `https://www.youtube.com/watch?v=CJO0u_HrWE8` — a **YouTube link**, distinct from the post's actual Wistia embed (`wistia_video_id: yx0i5ugqxy`). Likely an earlier draft's video source before the post was switched to Wistia, or a leftover from copying a webinar post as the starting template. **Do not migrate** — the live page uses the Wistia embed; verify this YouTube link isn't the intended source before discarding, but treat `wistia_video_id` as authoritative. |
| `webinar_tools`, `webinar_enable_tools_section: true` | Points at attachment IDs 1431/1433/1436 with no `asset_name`/`asset_type` labels (unlike the Toolkits CPT's fully-populated version of this same field) — effectively empty/unusable. Not migrated. |
| `webinar_presenter` | Two items, both with placeholder values (`presenter_name: "Full Name"`, `presenter_description: "Job Title,  Company Name"`) — **template defaults, never filled in with a real person.** Not real data; do not create People posts from this. |

This is strong evidence that post 1428 started life as a copy of a webinar post (or the webinar template) and was then adapted into a "video" page, leaving the webinar-shaped fields inert behind the surface content. Posts 5282 and 5285 (built later, no `_elementor_data` at all) don't carry any of this cruft — they went straight in with only the `resource_*` + `wistia_video_id` fields, which is the clean shape to migrate from for all 3.

---

## Per-post summary (all 3)

| Post | `wistia_video_id` | Checklist items | `details_cta` |
|---|---|---|---|
| Meet MIP Dashboards: Your Financial Command Center | `yx0i5ugqxy` | 2 | "Ready to See Your Data in a New Light? Watch the video now..." |
| Meet MIP Accounting: The Gold Standard for Mission-Driven Organizations | `agf6ha1b0m` | 4 | "See MIP in Action. Watch the video below..." |
| Reporting with MIP: Your Financial Story, Simplified | `psf46jxg0f` | 3 | "Tell a Smarter Financial Story..." |

---

## Recommendation: fold into a minimal standalone CPT, not into `webinar`

Three options exist; recommending the third:

1. **Fold into `webinar` CPT with a `video` type.** Rejected — webinars carry a whole apparatus (upcoming/on-demand lifecycle, `webinar_date`/`_time`/`_timezone`, presenters, `momentive_resolve_webinar_form()`) that these posts have zero use for. Forcing them through that CPT means either leaving a dozen fields permanently empty or building special-casing into `webinar_type_tax` and every webinar-aware block (`acf/webinar-status`, `momentive/webinar-presenters`) to skip a "video" type that behaves nothing like a webinar.
2. **Fold into `product-overview`.** Doesn't fit either — product overviews are gated (HubSpot form to request a demo); these videos are explicitly *not* gated (see above), and aren't tied to a single product landing page the way overviews are meant to be.
3. **Minimal standalone `video` CPT, reusing the shared "Recording" field group** (`video_embed_code`/`recording_url`, the same group already used by webinars and planned for product overviews per `inc/recordings.php`) **plus** the resource-details/checklist/CTA fields every gated CPT already shares. No form fields, no date fields, no presenters. Register `'video'` in `momentive_recording_host_types()` so `/recordings/{slug}` works here too, consistent with the architecture `inc/recordings.php` already documents for "hosts without the upcoming/on-demand notion" — the exact same phrase that ended up describing Product Overviews also describes Videos.

**Given there are only 3 posts today**, this is a small build: one CPT registration, one pattern (a single-column hero + description + checklist + embedded player, no sidebar form), and a 3-post migration script modeled directly on the whitepaper/infographic scripts minus the HubSpot-form branch entirely.

---

## Open questions before building

- **Confirm `wistia_video_id` (not `webinar_video_link`) is authoritative** for post 1428 — spot-check the live page's actual embedded player against `yx0i5ugqxy` before discarding the YouTube link.
- **Whether future videos will ever be gated** — if marketing ever wants a gated video (form before watching), decide now whether that's a `video` CPT variant (matching the toolkit `toolkit_type` switch pattern) or a job for a different existing CPT. Not needed for today's 3 posts, but worth a one-line decision so the CPT registration doesn't need revisiting later.
- **Single-product concentration** (all 3 are MIP Accounting) is incidental to this corpus, not an architectural constraint — the CPT itself is product-agnostic; category taxonomy handles solution-scoping the same as every other resource type.
