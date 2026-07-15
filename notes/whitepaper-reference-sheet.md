# Whitepaper Rebuild — Reference Sheet (5 coverage posts)

Decoded, Word-artifact-cleaned content for the five posts that together exercise every field and permutation across all 68 published whitepapers. The structure is considerably simpler than webinars: a two-column layout with a description/checklist on the left and a gated HubSpot form on the right. The "gate" delivers a PDF hosted by HubSpot — the rebuilt CPT just needs to show the form; HubSpot handles delivery.

**Source export:**
- `momentivesoftware.whitepapers.current.2026-07-01.xml` — 69 `whitepapers` posts (68 published + 1 empty draft)

---

## Field → destination map (applies to all posts)

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `resource_hero_image` (attachment ID) | `hero_image` ACF field | Sideload from legacy. Always set. Usually **different** from `_thumbnail_id` — see note below. |
| `_thumbnail_id` (attachment ID) | Featured image (`_thumbnail_id`) | Archive card image. Often the same attachment as hero_image but stored separately. |
| `enable_gated_content` | Layout toggle | `true` on 67/68 posts → shows HubSpot form. `false` on 1 post → shows a direct download link/button instead. |
| `form_heading` | Form section heading | Plain text. Varies per post (e.g. "Download Now", "Get your free copy now"). |
| `hubspot_form_code` | HubSpot embed block | Full `<script>` embed; portalId is always `46621835`. Extract `formId` if needed for a clean block field. |
| `resource_details` (HTML) | Left column — intro paragraphs | Commonly has Word span contamination — apply the same strip as webinars. |
| `details_cta` | Left column — closing CTA sentence | Plain text; appears 32/68 posts. Renders after `resource_details` (before the checklist, based on page structure). |
| `resource_checklist_title` | Checklist section heading | Plain text. Present on 55/68 posts. |
| `resource_checklist` (PHP serialized) | "You'll learn" checklist | Serialized repeater; each item has a single `description` key. 0 items on 13 posts. |
| `resource_details_after_checklist` (HTML) | Left column — paragraphs after checklist | Present on 30/68 posts (much more common than in webinars). Word-artifact cleanup applies. |
| `enable_additional_resource_link` | Extra button/link | `true` on 7/68 posts. Adds a button in the left column. |
| `resource_link` | Button URL | Either `#form` (anchor scroll to the right-column form, 4 posts) or an external PDF/page URL (3 posts). |
| `resource_link_text` | Button label | Plain text. |
| `resource_link_open_in_new_tab` | Button target | `true` → `target="_blank"`. Only set `true` on external URLs. |
| `enable_insights_section` | Insights accordion/list | `true` on 2/68 posts. Replaces the checklist with a richer title+description list. |
| `content_title` | Insights section heading | Plain text. Used alongside `insights_list`. |
| `insights_list` (PHP serialized) | Insight items | Serialized repeater; each item has `insight_title` and `insight_description`. |
| `resource_checklist_type` | _(not migrated)_ | Always `checkmarks` — hardcode in the rebuilt block. |
| category terms | Native category panel | Same Solution categories as products/case studies/webinars. |
| post title | Post title | |
| post excerpt | _(not migrated)_ | Empty on all 68 posts. |

**Fields not migrated (always false/empty across all 68 posts — dead Elementor fields):**
- `resource_enable_quote_box`, `enable_cae_credits_module`, `enable_video_module`
- `enable_related_resources`, `enable_cta_box`, `enable_series_section`
- `cta_-_enable_cta_section`, `enable_popup_forms`, `right_side_cta`, `static_utm_content`

---

## Permutations covered by the 5 reference posts

| # | Post | Permutation |
|---|---|---|
| 1 | Build vs. Buy Your AI-Powered Platform | **Typical:** gated + checklist (no extras) — ~55 posts |
| 2 | Navigating Fundraising Hurdles — A Handbook | **Insights section** (replaces checklist) + `resource_details_after_checklist` — 2 posts |
| 3 | The Complete AI Readiness Guide for AMCs | **Additional link → `#form` anchor** + checklist + `after_checklist` — 4 posts |
| 4 | Driving Non-Dues Revenue with Online Stores | **Additional link → external URL** + `details_cta`, no checklist — 3 posts |
| 5 | How to monetize your event experience | **Not gated** — direct download button in place of the form — 1 post |

---

## Word artifact cleanup

`resource_details` and `resource_details_after_checklist` carry MS-Word span contamination in roughly 60–70% of posts. Strip:
- Spans with Word class names: `NormalTextRun`, `TextRun`, `SCXW*`, `BCX*`, `EOP`, etc.
- Attributes: `data-contrast`, `data-ccp-props`, `data-ccp-charstyle`, `xml:lang`
- Empty `<p>` tags and `&nbsp;`-only paragraphs

Same stripping logic as the case study and webinar migrations.

---

## #1 — Build vs. Buy Your AI-Powered Platform

> **Typical:** gated + 4-item checklist, no optional sections. The most common pattern (~55 posts). `_thumbnail_id` and `resource_hero_image` are different attachment IDs — thumbnail is the archive card, hero is the page image.

- **slug:** `build-vs-buy-ai-platform`
- **live URL:** https://momentivesoftware.com/resource-center/whitepapers/build-vs-buy-ai-platform/
- **date:** 2026-06-23
- **categories:** Association Management
- **_thumbnail_id (legacy):** 11213
- **resource_hero_image (legacy):** 11212

**enable_gated_content:** true  
**form_heading:** `Download Now`  
**HubSpot formId:** `711e0455-3f40-464f-9de8-ff13325f28c6` (portalId `46621835`)

**resource_details (cleaned):**

```html
<p>Association and nonprofit leaders should read this guide to decide if your organization needs to build or buy your AI-powered platform.</p>
<p>Artificial intelligence is no longer a future-forward concept for associations and nonprofits. Organizations that are not already adopting and implementing AI risk being left behind.</p>
```

**resource_checklist_title:** `Read our guide to learn these helpful tips and decide if you should build an AI-powered platform:`

**Checklist:**
- What is the cost of not adopting AI?
- What does building an AI platform entail?
- What challenges can occur when building a platform?
- And more.

_(No `details_cta`, no `resource_details_after_checklist`, no `enable_additional_resource_link`.)_

---

## #2 — Navigating Fundraising Hurdles — A Handbook

> **Insights section.** `enable_insights_section: true` replaces the checklist with a richer `content_title` + `insights_list` (title + description pairs). Also the primary example of `resource_details_after_checklist`. No checklist items. Covers both "insights" and "gated, no checklist" cases.

- **slug:** `fundraising-challenges`
- **live URL:** https://momentivesoftware.com/resource-center/whitepapers/fundraising-challenges/
- **date:** 2026-05-20
- **categories:** Fundraising
- **_thumbnail_id (legacy):** 10540
- **resource_hero_image (legacy):** 10541

**enable_gated_content:** true  
**form_heading:** `Read the handbook`  
**HubSpot formId:** `d0658387-6524-400a-aa69-555093644d9c` (portalId `46621835`)

**resource_details (cleaned):**

```html
<p>Nonprofit organizations always need funding, and supporters rely on ongoing assistance, regardless of changes in the landscape or challenges that arise. This guide provides leaders with clear frameworks, actionable data strategies, and reliable tools to help raise more—without overwhelming their teams.</p>
```

**enable_insights_section:** true  
**content_title:** `What's Inside`

**insights_list (4 items):**
| insight_title | insight_description |
|---|---|
| Challenges every nonprofit leader faces: | From stretched resources to proving program impact, this guide breaks down the most common obstacles and shows you exactly how to address them with data, strategy, and the right technology. |
| Limited resources: | How to build a data-driven framework for allocating staff, budget, and time, eliminating guesswork. |
| Creating more giving opportunities: | Explore donor engagement strategies and diversified revenue streams, from peer-to-peer campaigns to fundraising events. |
| Evaluating program effectiveness: | Learn to build a budget plan that measures what's working, guides your strategic plan, and tells a compelling story to stakeholders. |

**resource_details_after_checklist (cleaned):**

```html
<p>Funding your mission shouldn't mean working around the clock.</p>
<p>When nonprofits pair the right technology with smart strategy, they achieve stronger donor retention, reduce errors from fragmented data, and free up more time to focus on what truly matters. This handbook guides you through the most common fundraising hurdles and shows you how to overcome each one with clarity and confidence.</p>
```

_(No `details_cta`, no `resource_checklist`, no `enable_additional_resource_link`.)_

---

## #3 — The Complete AI Readiness Guide for AMCs

> **Additional link → `#form` anchor.** `enable_additional_resource_link: true` with `resource_link: #form` — a button in the left column that scrolls to the right-column form. Also has `resource_details_after_checklist`. The most common additional-link pattern (4 of 7 such posts use `#form`).

- **slug:** `ai-readiness-guide`
- **live URL:** https://momentivesoftware.com/resource-center/whitepapers/ai-readiness-guide/
- **date:** 2026-04-30
- **categories:** Association Management
- **_thumbnail_id (legacy):** 10078
- **resource_hero_image (legacy):** 10079

**enable_gated_content:** true  
**form_heading:** `Get your free copy now`  
**HubSpot formId:** `984ee1ac-15f2-453e-af0d-a871d44441e1` (portalId `46621835`)

**resource_details (cleaned):**

```html
<p><strong>Everything you need to govern, scale, and productize AI across your client portfolio</strong></p>
<p>Many Association Management Companies (AMCs) are experimenting with AI, but only a few know how to use it effectively across all their client portfolios.</p>
<p>This guide is for AMCs ready to go beyond small wins and random tool tests. It offers a clear, step-by-step path from getting started to delivering real strategic value, all while maintaining client trust, confidentiality, and compliance.</p>
```

**resource_checklist_title:** `You'll learn how to:`

**Checklist:**
- Govern AI across staff and clients to proactively manage risk.
- Build workflows that scale across your portfolio without sacrificing quality.
- Use data to make decisions and turn AI results into insights your clients can use in the boardroom.
- Turn AI work into services that clients find valuable and are willing to pay for.
- Position your AMC as a forward-thinking partner, not just operational support.

**resource_details_after_checklist (cleaned):**

```html
<p>No matter where you are in your AI journey, this guide walks you through three clear stages: Readiness, Value, and Foresight. It also gives you practical frameworks you can use right now.</p>
<p>Whether you're just getting started, already running pilots, or ready to monetize your AI capabilities, the Complete AI Readiness Guide for AMCs meets you where you are.</p>
```

**enable_additional_resource_link:** true  
**resource_link:** `#form`  
**resource_link_text:** `Get your free copy now`  
**resource_link_open_in_new_tab:** false

---

## #4 — Driving Non-Dues Revenue with Online Stores

> **Additional link → external URL** + `details_cta`, no checklist. The 3 posts with external-URL links (vs. `#form` anchor) point to HubSpot-hosted PDFs or external pages. Also demonstrates `details_cta` — a plain-text closing sentence that appears in the description area.

- **slug:** `non-dues-revenue-online-stores`
- **live URL:** https://momentivesoftware.com/resource-center/whitepapers/non-dues-revenue-online-stores/
- **date:** 2026-04-22
- **categories:** Association Management, Career Centers, Donor Management, Event Management, Learning Management
- **_thumbnail_id (legacy):** 9907
- **resource_hero_image (legacy):** 9906

**enable_gated_content:** true  
**form_heading:** `Download now`  
**HubSpot formId:** `8aba86b8-4e87-45d7-9b26-33c53cce878c` (portalId `46621835`)

**resource_details (cleaned):**

```html
<p>Non-dues revenue is an essential aspect of any association's financial health, and in today's economic climate, it's even more paramount. As your association goes through periods of flat or declining memberships, non-dues revenue helps bridge the gap, making up losses and ensuring your association doesn't have to scale back services.</p>
<p>A key driver of non-member revenue is your association's online store. A thriving e-commerce strategy can help your association grow sustainably, and the right AMS makes it seamless.</p>
```

**details_cta:** `Let's explore how an online store solves key challenges and creates new opportunities for your association.`

_(No `resource_checklist`, no `resource_details_after_checklist`.)_

**enable_additional_resource_link:** true  
**resource_link:** `https://go.momentivesoftware.com/hubfs/001%20Momentive%20Software/MS-Whitepaper/Whitepaper_YM_Driving-Non-Dues-Revenue-Online-Stores_2026.pdf`  
**resource_link_text:** `Download now`  
**resource_link_open_in_new_tab:** false

> **Note:** External-link posts have the form AND an external download link. The link may be a direct PDF download or a HubSpot landing page. The rebuilt block should render both; the form still captures the lead while the link offers ungated access. Verify this behavior against the live page.

---

## #5 — How to monetize your event experience

> **Not gated.** The only post with `enable_gated_content: false`. No HubSpot form on the right — instead, a direct download button pointing to an externally hosted PDF. Also demonstrates `details_cta` (different wording than #4). `_thumbnail_id` and `resource_hero_image` are the same attachment ID (2473) — a detail the migration should handle without erroring.

- **slug:** `how-to-monetize-your-event-experience`
- **live URL:** https://momentivesoftware.com/resource-center/whitepapers/how-to-monetize-your-event-experience/
- **date:** 2025-07-22
- **categories:** Event Management
- **_thumbnail_id (legacy):** 2473
- **resource_hero_image (legacy):** 2473 _(same attachment — thumbnail and hero are identical)_

**enable_gated_content:** false  
**form_heading:** _(empty — no form)_

**resource_details (cleaned):**

```html
<p>Event attendees today seek interactive learning, exclusive content, and networking, creating ripe opportunities for organizers to boost revenue beyond base ticket sales by offering tailored add-ons powered by event technology.</p>
```

**details_cta:** `By strategically integrating paid add-on offerings and leveraging purpose-built event technology, organizers can transform any event into a profit powerhouse while delivering elevated value and unforgettable experiences to attendees.`

**resource_checklist_title:** `After reading this Whitepaper, you will understand how to better:`

**Checklist:**
- Unlock new revenue streams and enhance attendee loyalty through personalized experiences.
- Leverage event tech to simplify upselling, improve engagement, and gather analytics.
- Drive revenue with VIP meet-and-greets, credit-earning workshops, off-site activities, and merchandise.
- And more!

**Direct download link (replaces form):**  
**resource_link:** `https://46621835.fs1.hubspotusercontent-na1.net/hubfs/46621835/007%20Expo%20Logic/EL-Whitepaper/WHP-EL-2404-04-Monetizing%20the%20Event%20Experience%20Maximizing%20Event%20Revenue.pdf`  
**resource_link_text:** `Download Now`  
**resource_link_open_in_new_tab:** true

_(No `resource_details_after_checklist`, no `enable_additional_resource_link`.)_

---

## Notes discovered during analysis

**`_thumbnail_id` and `resource_hero_image` are usually different attachments.** The featured image (`_thumbnail_id`) is used on archive cards; `resource_hero_image` is the page hero image. Both need to be sideloaded. On #5 they happen to be the same ID — the migration must not error when both point to the same source attachment.

**No post excerpts.** Unlike webinars (131/141 had excerpts), all 68 whitepaper posts have empty `post_excerpt`. Nothing to migrate.

**`resource_details_after_checklist` is common — 30/68 posts.** Much more prevalent than in webinars (11/141). Always include it as additional paragraph blocks after the checklist.

**`details_cta` is also common — 32/68 posts.** A plain-text closing sentence or short paragraph that appears in the description area (based on the legacy template, it renders below `resource_details` and above the checklist). No HTML — treat as a plain paragraph.

**`enable_additional_resource_link` is independent of gating.** It adds a standalone button/link in the left column regardless of whether the form is shown. The 4 posts with `#form` use it as an anchor scroll to the form; the 3 posts with external URLs point to PDFs or landing pages (the form is still present on those posts).

**Insights section is rare (2 posts) and replaces the checklist.** When `enable_insights_section: true`, the `resource_checklist` is always empty. The `insights_list` is a PHP serialized repeater with `insight_title` (bold heading) and `insight_description` (paragraph text) per item. No known HTML contamination in the insights fields.

**HubSpot portalId is always `46621835`.** Only the `formId` changes per post — extract it with `preg_match('/formId:\s*"([^"]+)"/', $embed_code, $m)` if needed for a clean ACF block field.

**All `resource_checklist_type` values are `checkmarks`.** No variation — hardcode the style in the rebuilt block rather than migrating this field.

**Unused Elementor-era features.** These fields are present on every post but always `false` or empty: quote box, CAE credits, video module, related resources, CTA box, series section, popup forms. Do not build blocks for them; they will not be migrated.

**`details_cta` placement note.** On the live legacy pages (Elementor-rendered), `details_cta` appears as a summary sentence at the bottom of the left-column text, before the checklist. Confirm the correct position when building the rebuilt template — it may sit between `resource_details` and the checklist heading, or after the checklist, depending on what the rebuilt design calls for.
