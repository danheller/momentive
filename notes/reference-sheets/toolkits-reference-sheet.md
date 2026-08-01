# Toolkit Rebuild — Reference Sheet (all 6 published posts)

Decoded content and architecture notes for the legacy `toolkits` CPT — small enough to cover every published post, not just a representative sample.

**Source export:**
- `migrations/momentivesoftware.toolkits.current.2026-07-16.xml` — 16 items: 6 published `toolkits` posts + 10 attachments. No drafts.

**What this is:** a "toolkit" is a gated resource-collection page — one HubSpot form, but instead of gating a single PDF/video like whitepapers or infographics, it gates access to a *bundle*: several webinars/templates, or a long buyer's-guide with accordion sections. Structurally it reuses the same `resource_*`/`enable_gated_content`/`hubspot_form_code` field family every other gated CPT in this project uses (whitepapers, infographics, product overviews) — the novelty is entirely in what sits *below* that shared shell.

---

## The two layout variants (`toolkit_type` field)

| `toolkit_type` | Posts | What it adds on top of the shared gated shell |
|---|---|---|
| `buyers-guide` | 2 | Long-form accordion sections (`toolkit_sections` + `accordion_1_items` … `accordion_8_items`), a dark CTA box, an optional CTA band, a "See how Momentive replaces all your tools" section |
| `standard` | 4 | A `webinar_tools` repeater — a simple card grid linking out to the bundled webinars/templates (image + name + type label), no accordions |

These are **not** two CPTs pretending to be one — they're one CPT with a field that switches which optional section renders, the same "layout variant baked into one migration, not two scripts" pattern already established for whitepapers (gated vs. not-gated) and infographics (gated vs. ungated).

---

## Field → destination map (shared shell — applies to all 6 posts)

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `_thumbnail_id` | Featured image | Archive card image. Always a different attachment from `resource_hero_image` across this corpus. |
| `resource_hero_image` | `hero_image` ACF field | Present on all 6. |
| `resource_details` (HTML) | Left column — intro paragraphs | Same Word-artifact cleanup as whitepapers/infographics/webinars — several posts carry `data-contrast`/`data-ccp-props` span cruft. |
| `resource_checklist_title` + `resource_checklist` (serialized) | Checklist section | Present on 3/6 posts (the two webinar-bundle posts + Workforce Retention). Items are plain-text descriptions, same shape as infographics — no HTML-link edge case like `silent-auction-tips` had. |
| `details_cta` | Closing CTA sentence (may include a literal `<br><br>`) | Present on 1/6 (AMS Buyer's Toolkit) — preserve the `<br>` as a paragraph break, not literal text. |
| `enable_gated_content` (always `true` on this CPT) / `hubspot_form_code` | HubSpot form embed | All 6 gated — unlike infographics, no ungated variant exists here. Portal ID always `46621835`. |
| `form_heading` | Form section heading | Plain text, varies: "Download now", "Watch now", "Fill out the form to access this toolkit". |
| category terms | Native category panel | Shared Solution-scoped category. Every post has at least one; the two buyer's-guide posts have 2–4 (span multiple solution families, since these are cross-solution buying guides). |
| post title, excerpt, date | Post title, excerpt, date | All 6 have excerpts. |

**Fields NOT migrated (dead defaults, confirmed empty/false across all 6 — same pattern as every other gated CPT):**
- `hero_video_source` (hardcoded `wistia`), `hero_library_video`, `hero_link_video`, `video_embed_code`, `video_module`/`enable_video_module`
- `enable_cae_credits_module`, `cae_credits_module`, `cae_credits_text`
- `enable_insights_section`, `content_tab`, `content_title`, `content_description`, `insights_list`
- `resource_enable_quote_box`, `resource_quote*`
- `enable_related_resources`, `related_resources_*`, `manual_post_list`
- `enable_cta_box` / `resource_cta_*` at the top level (distinct from the buyer's-guide-only `cta_-_*` and `dark_cta_box_*` fields below, which **are** real on 1 post)
- `series_section*`, `static_utm_content`
- `popup_form_*` (present as empty fields on the 2 "standard" RFP-template posts — `enable_popup_forms: false` on both, never populated)

---

## `buyers-guide` variant — additional fields (2 posts)

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `toolkit_sections` (serialized, 8–9 items) | Drives which accordion section renders, in order | Each item: `enable_section`, `section_type` (always `accordion` in this corpus), `section_bg_color` (alternates `#ffffff`/`#f7f7f7` — a banded-background pattern, migrate as alternating background on the wrapping group), `section_id` (anchor slug), `section_title`, `section_description` (HTML), `section_image` (usually empty), `section_cta_button_text`/`_url`/`_new_tab` (per-section CTA — sometimes `#form` to jump to the HubSpot form, sometimes an outbound link like `/solutions/association-management-software/`), `linked_accordion` (which `accordion_N_items` field supplies this section's actual accordion rows). |
| `accordion_1_items` … `accordion_8_items` (serialized, 3–5 rows each) | `momentive/accordion` block content, one per section | Each row: `accordion_item_title`, `accordion_item_description` (HTML, Word-cleanup needed — several rows carry `data-contrast`/`TextRun`/`SCXW*` spans), `accordion_item_icon_type` + `accordion_item_icon_solutions` (e.g. `sol-fundraising-management`) or `_icon_boxicons`/`_icon_custom`, `icon_color`. Also carries **dead per-row CTA/callout fields never populated in this corpus**: `accordion_item_enable_bl` (button link), `accordion_item_enable_cb` (callout box), `accordion_item_product_logo` — all `false`/empty on every row checked; don't build UI for these unless a future post actually uses them. |
| `cta_-_enable_cta_section` + `cta_-_title`/`_description`/`_button_1_*`/`_button_2_*` | A two-button CTA band ("See how Momentive replaces all your tools with one platform") | **Only 1/2 buyer's-guide posts** (Association Software Buyer's Guide) — Nonprofit Buyer's Guide has `enable_cta_section: false`. Not a shared/global toolkit section; per-post. |
| `enable_dark_cta_box` + `dark_cta_box_image`/`_title`/`_description`/`_button_text`/`_button_url` | Dark-background CTA box, button anchors to `#form` | **Both** buyer's-guide posts (`true`). Both reuse the exact same image URL (`Association-Buyers-Guide-CTA-Image.png`) even on the Nonprofit guide — a copy-paste-and-never-updated asset reference; flag for a possible fresh image before/at migration rather than carrying the association-branded image onto the nonprofit guide verbatim. |
| `toolkit_type: buyers-guide` | — | The switch itself. |

**Accordion count varies per post — not a fixed template.** Association Software Buyer's Guide populates `accordion_1` through `accordion_7` (7 sections); Nonprofit Software Buyer's Guide populates `accordion_1` through `accordion_7` as well but with different row counts per section (3–4 rows vs. 3–5). Build the accordion-block generator to read however many `accordion_N_items` fields are actually populated per post, not a hardcoded N.

---

## `standard` variant — additional fields (4 posts)

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `webinar_enable_tools_section` (`true` on all 4) + `title` (section heading, e.g. "Included on-demand webinars:") + `webinar_tools` (serialized, 2–5 items) | A card grid: `card_image` (attachment ID), `asset_name`, `asset_type` (`"Webinar"` or `"Template"`) | This is a **flat list of labeled cards, not linked posts** — `asset_name`/`asset_type` are typed by hand, not resolved from the referenced webinar/template's own title or a post relationship. If any bundled webinar has since been renamed, the toolkit card's label won't reflect that automatically. Consider whether the rebuilt version should keep hand-typed card copy (faithful migration) or upgrade to a real Post Object reference into the `webinar` CPT so labels can't drift — a real architectural choice, not just a migration detail. |
| `toolkit_type: standard` | — | The switch itself. |

**Two pairs of near-identical posts.** The two RFP-template toolkits (Union, Membership) share the exact same `webinar_tools` payload verbatim (`card_image` 10749/10750, "Union RFP Template"/"Member Management System Requirements Matrix") even though one is scoped to union management and the other to membership management broadly — almost certainly a copy-paste of one post to create the other, with the toolkit's actual downloadable RFP template context differing (per `resource_details`) but the *card labels describing what's included* left unedited. Worth flagging for a content review pass, not something the migration script should try to auto-correct.

---

## Per-post summary (all 6)

| # | Post | `toolkit_type` | Categories | Checklist | `details_cta` | Buyer's-guide extras |
|---|---|---|---|---|---|---|
| 1 | The AMS Buyer's Toolkit (4 webinars) | `standard` | Association Management | ✓ (3 items) | ✓ | — |
| 2 | Association Software Buyer's Guide | `buyers-guide` | Association Management, Buyer's Guide | — | — | CTA band ✓, dark CTA box ✓, 7 accordion sections |
| 3 | Nonprofit Software Buyer's Guide | `buyers-guide` | Accounting, Buyer's Guide, Fundraising, Volunteer Management | — | — | CTA band ✗, dark CTA box ✓, 7 accordion sections |
| 4 | Union RFP Template and Matrix | `standard` | Association Management | — | — | — |
| 5 | Membership RFP Template and Matrix | `standard` | Association Management | — | — | — (shares card copy verbatim with #4 — see above) |
| 6 | Workforce Retention Toolkit | `standard` | Association Management | ✓ (3 items) | — | — |

---

## Word artifact cleanup

`resource_details` and the buyer's-guide `accordion_item_description` fields carry the same MS-Word span contamination already handled for whitepapers/infographics/webinars/case studies — `data-contrast`, `data-ccp-props`, `xml:lang`, `TextRun`/`SCXW*`/`BCX*` class spans. Confirmed present in at least: Union Management RFP's `resource_details`, and several accordion rows across both buyer's-guide posts (e.g. Association Software Buyer's Guide's `accordion_3_items` item-0, Nonprofit Software Buyer's Guide's `accordion_3_items`/`accordion_4_items`). Reuse the existing stripper rather than writing a new one.

---

## Notes discovered during analysis

**This is the first gated CPT where the gate wraps a *bundle*, not a single asset.** Every other gated CPT (whitepaper, infographic, product overview) delivers one PDF/video/form. Toolkits deliver either a long buyer's-guide (itself effectively a mini pillar page) or a curated set of other resources. The rebuilt pattern needs its own two-column scaffold rather than reusing `patterns/whitepaper-content.php` wholesale — closer to a hybrid of the whitepaper gated layout and a stripped-down solution page.

**`toolkit_type` cleanly predicts every other structural difference.** No post mixes accordions and a webinar-tools card grid, and no post has neither. A migration script can branch once on this single field rather than checking several enable-flags to infer the layout, unlike whitepapers (which infers gated/ungated from whether `hubspot_form_code` exists at all).

**Card-grid entries are hand-typed labels, not relationships** (see `standard` variant table above) — an open question worth raising with Daniel/Greg before building: keep faithful-to-legacy hand-typed cards, or upgrade to Post Object references into `webinar`/a future `toolkit-asset` field.

**Two RFP-template posts share verbatim card copy** — see above. Flag as a content QA item, not a migration bug to silently fix.

**Both buyer's-guide posts reuse the same `dark_cta_box_image`** regardless of which guide it's on — likely an asset that should get its own nonprofit-specific version rather than being migrated as-is.

**No ungated toolkits exist in this corpus** (unlike infographics' near-even gated/ungated split) — `enable_gated_content` is `true` on all 6, and none has `enable_additional_resource_link` or a bare `resource_link` in place of the form. Simpler than infographics on this axis.

---

## Open questions before building

- **Card-grid relationship model:** hand-typed `asset_name`/`asset_type` labels (faithful migration, matches every other CPT's "write legacy faithfully" convention) vs. a real Post Object reference into `webinar` so labels can't drift when a bundled webinar is renamed. Worth a quick call before scripting — this is the one place this CPT introduces a genuinely new modeling decision instead of reusing an existing pattern.
- **Buyer's-guide accordion count is variable (7 populated slots of a possible 8)** — confirm the migration script reads however many `accordion_N_items` are actually non-empty per post rather than assuming a fixed count, since a third buyer's-guide post (if one is ever added) might use more or fewer.
- **Dark CTA box image reuse** across both buyer's-guide posts — confirm whether to migrate verbatim or request a nonprofit-specific asset before cutover.
- **RFP-template card copy duplication** (Union vs. Membership posts) — flag for Daniel/Greg to decide whether to differentiate the card labels during migration or leave as-is.
