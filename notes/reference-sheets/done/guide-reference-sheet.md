# Guides & Research Rebuild — Reference Sheet (10 coverage posts)

Decoded, Word-artifact-cleaned content for the ten posts that together exercise every field and permutation across all 25 legacy `guides` posts.

**Source export:**
- `momentivesoftware.guides.current.2026-07-16.xml` — 25 `guides` posts (22 published + 3 drafts)

**Headline finding — this CPT is two CPTs wearing one costume.** A `guide_type` field splits the corpus into two structurally different page types that share a post type and a category taxonomy but almost nothing else:

- **`guides` (17 posts)** — structurally the same two-column gated/ungated layout already built for whitepapers: description → checklist → HubSpot form (or a direct download link when ungated). Reuse that pattern almost unchanged.
- **`research-study` (8 posts)** — a materially richer, standalone layout: custom hero + overline + rich subheader, a "download the full report" form, up to two animated-stat "insight" sections, an optional webinar-promo CTA band, and an optional "Explore Previous Studies" card grid. **This is new template work, not a content-only migration** — nothing currently built (whitepaper, infographic, webinar) covers stat-with-accent-color callouts or a previous-studies card grid.

Because of that split, this reference sheet uses 10 posts instead of the usual 5 — 5 for `guides` (whitepaper-shaped) and 5 for `research-study` (new layout), since 5 posts alone can't responsibly cover both shapes.

**One more wrinkle:** `guide_type` is a content-team label, not a reliable layout switch. Two of the 8 `research-study` posts (#9, #10 below) have every research-only field empty and use the plain `guides` layout instead — the migration must branch on *which fields are populated*, not on `guide_type` alone.

---

## Field → destination map — `guides` subtype (17 posts, whitepaper-shaped)

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `resource_hero_image` | `hero_image` ACF field | Sideload. Sometimes identical to `_thumbnail_id` (e.g. #4, #6 below) — skip the redundant sideload, same rule as whitepapers. |
| `_thumbnail_id` | Featured image | Archive card image. |
| `resource_header_overline` | Not migrated for this subtype | **RESOLVED — dead field on `guides`-shape posts.** Populated on #5 (outdoor-fundraising-ideas) and on `mobile-fundraising-strategy` (not yet rebuilt), but confirmed against the live legacy site: the value never actually renders there either. Same category as the other confirmed-dead Elementor leftovers in this migration — ignore it whenever `guide_type` is `guides`. Still a real, rendered field on the `research-study` side (the "preview" posts' eyebrow) — this dead-field status is specific to the `guides` subtype, not the field generally. |
| `custom_header` | `hero_title` ACF field | Optional H1 override, confirmed on both subtypes — not `guides`-only despite the name. Matches the post title exactly on some posts (skip — no override needed) and differs on others (donor-segmentation → "Donor Segmentation", the two research-study "Report" posts → their shortened headlines). Set `hero_title` only when this value differs from the post title. |
| `enable_gated_content` | Layout toggle | `true` on the great majority; `false` → direct download link instead of a form (same as whitepaper #5). |
| `form_heading`, `hubspot_form_code` | Form heading + HubSpot form block | Same inline-embed pattern as whitepapers/infographics. Portal is always `46621835` except where noted in the research-study section. |
| `resource_details` | Left column description | Word-cruft cleanup applies, same patterns as whitepapers. |
| `details_cta` | Bold closing sentence | Same placement convention as whitepapers (after description, before checklist). |
| `resource_checklist_title` / `resource_checklist` | Checklist heading + list | Same repeater shape as whitepapers (`description` per row). |
| `resource_checklist_type` | Checklist icon style | `checkmarks` on all `guides` posts. (The `custom` icon variant only appears on one `research-study` post — see below.) |
| `resource_details_after_checklist` | Paragraphs after checklist | Present on several posts, same as whitepapers. |
| `enable_additional_resource_link` / `resource_link` / `resource_link_text` / `resource_link_open_in_new_tab` | Extra button | Same pattern as whitepapers — `#form` anchor or external URL. |
| `enable_insights_section` (singular) / `content_title` / `insights_list` | Insights list (replaces checklist) | The **old-style** single insights section, identical shape to the 2 whitepaper posts that use it. Only 1/17 `guides` post uses it (#2 below) — and on that post it's paired with a CTA block whitepapers never actually populated (see next row). |
| `cta_-_enable_cta_section` / `cta_-_title` / `cta_-_description` / `cta_-_button_1_*` / `cta_-_button_2_*` | Full-width 2-button "looking for more?" CTA band | **New — not built for whitepapers.** The field group exists on whitepapers too but is always empty there (logged as a dead field in that migration); here it's actually populated once. Needs a small new pattern: heading + description + two buttons. |
| category terms | Native category panel | 1/25 posts has no category (see notes). |
| post excerpt | Post excerpt | 24/25 have one; 1 empty (a `research-study` post, see below). |

**Fields confirmed dead across all 25 posts (do not migrate):** `connected_products`, `enable_product_logos`, `statistics_sections`, `general_research_study_info` (+ its `_tab` wrapper), `resource_details_tab` / `resource_details_tab_tab` (Elementor tab-wrapper artifacts, always empty), `series_settings`, `series_order` (non-empty on exactly one draft, value `10`, with no visible use — treat as dead), `exclude_from_listing` (present once, `false`).

---

## Field → destination map — `research-study` subtype (8 posts, new layout)

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `custom_header_overline` / `custom_header` | Eyebrow + large header, replacing post-title in the hero | Distinct from `resource_header_overline` (which is used in the simple layout). |
| `research_hero_image` | Hero image for the research layout | A **third** image slot alongside `_thumbnail_id` and `resource_hero_image` — some posts populate all three with different attachments (see #6). |
| `research_subheader` (HTML) | Rich-text subheading under the header | Usually one larger-font intro line + 1-2 supporting paragraphs. |
| `research_form_header` / `research_form_code` | "Download the full report" form heading + HubSpot embed | Distinct from `form_heading`/`hubspot_form_code`, which some research posts also populate — see the two-form note below. |
| `enable_custom_image_section` / `custom_image` / `custom_image_mobile` / `custom_image_header_text` / `custom_image_subheader_text` / `custom_image_rounded_corners(_mobile)` / `separate_image_mobile` | Optional full-width image band with its own heading/subheading and a separate mobile image | Only 1/8 posts uses it (#6). |
| `enable_cta_section` / `cta_header` / `cta_text` / `cta_image` / `cta_button_text` / `cta_button_link` / `open_in_new_tab` | Full-width webinar-promo CTA band (image + heading + text + button) | 4/8 posts. Distinct from the `cta_-_*` "looking for more?" band used on the `guides` side — **do not conflate the two CTA field groups**, they're different components. |
| `enable_previous_studies_section` / `previous_studies_heading` / `previous_resource_type` / `previous_studies_list` | "Explore Previous Studies" card grid | 3/8 posts. Repeater: `previous_resource_year`, `previous_resource_image`, `previous_resource_title`, `previous_resource_description`, `previous_resource_download_link` per card. **New component** — closest existing analog is `linked-products`, but the card shape (year + image + title + description + direct PDF link, no post relationship) doesn't match it; build fresh. |
| `enable_insights_section_1` / `enable_insights_section_2` (+ `insights_N_kicker_text`, `_title`, `_description`, `_key_takeaway`, `_button_text`, `_button_link`, `_statistics`) | Up to two "Insight" sections, each: kicker + title + description + key-takeaway + button (almost always `#form`) + a row of animated stat callouts | 4/8 posts, always both or neither. `insights_N_statistics` is a repeater of `{number_prefix, number, number_suffix, description, accent_color}` — **the per-stat hex `accent_color` is new**; neither `impact-stat` nor `stat-columns` currently supports a per-item custom color. |
| `resource_checklist_type: custom` + `resource_custom_checklist` | Icon + description checklist (replaces the plain checkmark list) | Only 1/8 posts (#6). Repeater: `custom_icon` (legacy `box-` prefix, strip mechanically like the case-study migration), `custom_description`. |
| `enable_additional_resource_link` / `resource_link` / `resource_link_text` | Same additional-link pattern as `guides` | 2/8 posts — both are the "preview report" posts that otherwise use the plain layout (#9, #10). |

**Two-form wrinkle (post #6 only):** one post populates *both* `hubspot_form_code` (a short-form "get the preview" embed, portal `46621937` — note this is a **different HubSpot portal ID** than the `46621835` used everywhere else in the entire site) and `research_form_code` (the standard portal `46621835` "get the full report" embed). Confirm with Daniel whether this is a deliberate two-step preview→full-report flow or a stray/mistaken second form before building it — it's the only place a non-`46621835` portal shows up anywhere in this export.

**Fields confirmed dead for this subtype:** `statistics_sections`, `general_research_study_info`, `connected_products`, `enable_product_logos` — same dead list as the `guides` side.

---

## Permutations covered by the 10 reference posts

| # | Post | guide_type | Permutation |
|---|---|---|---|
| 1 | Your Go-To Guide for Donor Segmentation | guides | **Typical gated** — description, `details_cta`, checklist, form. No extras. Most common `guides` shape (~11 of 17). |
| 2 | Campaign Planning Calendar | guides | **Old-style insights section** (replaces checklist) + the **new "looking for more?" 2-button CTA band** (`cta_-_*`) — the only post in the whole export using that CTA field group. |
| 3 | Nonprofit Goal Planning: Worksheets to Set Your Fundraising Goals | guides | **Additional resource link** (external PDF) + `details_cta`, gated. |
| 4 | 2024 Impact Report | guides | **Not gated** — direct external link (Turtl story) replaces the form. Has `resource_details_after_checklist`. `_thumbnail_id` and `resource_hero_image` identical (10086). |
| 5 | Alternatives to Golf Fundraising | guides | **`resource_header_overline`** eyebrow field in use, plus `details_cta` + checklist + gated form. |
| 6 | 2025 Momentive Nonprofit Research Study | research-study | **Richest research post** — custom image section, webinar CTA band, previous-studies grid (1 card), **custom icon checklist**, and the **two-form (two-portal) wrinkle**. |
| 7 | Bridging the Gap: Aligning Association Professionals and Members for Success | research-study | Both insight sections (with stat callouts) + previous-studies grid (3 cards) + webinar CTA band. No custom image section. |
| 8 | 2025 Small Staff Associations Report | research-study | Both insight sections + previous-studies grid (1 card), **no CTA band** — demonstrates the CTA band is independently optional. |
| 9 | 2026 Talent Retention Report for the Mission-Driven Workforce | research-study | Both insight sections + webinar CTA band, **no previous-studies grid** — demonstrates that section is independently optional too. |
| 10 | 2026 Nonprofit Trends Preview Report: Rebuilding the Trust Economy | research-study | **`guide_type` lies** — tagged research-study but every research-only field is empty; renders as a plain gated `guides`-style page with an additional external link. No excerpt (the only post in the export with none). |

---

## Word artifact cleanup

Same contamination patterns as whitepapers/case studies — Word spans (`data-contrast`, `data-ccp-props`, `data-ccp-charstyle`, `NormalTextRun`, `TextRun`, `SCXW*`, `BCX*`, `EOP`, spelling/comment spans) plus stray `&nbsp;`. Applies to `resource_details`, `resource_details_after_checklist`, and — new for this CPT — `research_subheader` and `insights_N_description` occasionally carry inline `style="font-size:..."` spans that should be normalized rather than stripped outright (they're used deliberately for a larger lead sentence — see post #7's `research_subheader`).

---

## #1 — Your Go-To Guide for Donor Segmentation

> **Typical gated `guides` post.** No optional sections — the baseline shape most of the corpus follows.

- **slug:** `donor-segmentation`
- **guide_type:** guides
- **date:** 2023-05-25
- **categories:** Donor Management
- **_thumbnail_id / resource_hero_image (legacy):** 10231 / 10231 (same attachment)

**enable_gated_content:** true
**form_heading:** `Download now`
**HubSpot formId:** `1c5b46ea-c3fd-4b71-b1b7-335063826ce7` (portalId `46621835`)

**resource_details (cleaned):**
```html
<h3>Better nurture and engage your donors</h3>
<p>Giving trends are a bit bleak, with a steady decline in the overall number of both new donors and retained donors over the past few years. As of 2022, nonprofits are only retaining 43 out of 100 donors year over year. Organizations of all sizes have to grapple with this downward trend. What is a nonprofit to do?</p>
<p>Access your donor data and segment your supporters. Donor segmentation enhances your visibility into your support network, giving you an understanding of people's motivations and behaviors.</p>
```

**details_cta:** `With the right donor segmentation plan in place, you're sure to deepen your donor relationships, stabilizing revenue and increasing growth as you refine your strategy.`

**resource_checklist_title:** `Open this guide to learn more about:`

**Checklist:**
- Using segmentation for smarter donor stewardship
- Managing successful donor segmentation at your nonprofit organization
- Smart donor segments to use to increase conversion and retention
- Generational giving habits and differences
- And more

**Excerpt:** `Learn how to segment donors effectively to boost engagement, retention & fundraising results.`

_(No `resource_header_overline`, no `resource_details_after_checklist`, no additional link, not gated=false.)_

---

## #2 — Campaign Planning Calendar

> **Old-style insights section + new "looking for more?" CTA band.** Same `enable_insights_section` shape as 2 whitepaper posts, but this is the only post anywhere in the export (whitepapers included) where the `cta_-_*` field group is actually populated — build the 2-button CTA band fresh.

- **slug:** `annual-fundraising-calendar`
- **guide_type:** guides
- **date:** 2026-03-20
- **categories:** Fundraising
- **_thumbnail_id (legacy):** 11043 — **resource_hero_image (legacy):** 11044 (different)

**enable_gated_content:** true
**form_heading:** `Download your FREE Planning Calendar. Fill out the form and start planning impactful campaigns today!`
**HubSpot formId:** `71af9272-3175-4039-a430-ae74280a935e` (portalId `46621835`)

**resource_details (cleaned):**
```html
<h5>Bring on Better</h5>
<p>Throughout the year, there are dozens of meaningful moments; there's no shortage of opportunities to connect with your members, donors, and community, if you know they're on the calendar.</p>
<p>The Momentive Software Campaign Planning Calendar maps out the major holidays, awareness months, days of giving, and even the fun, offbeat moments that give your campaigns a timely hook. Use it to plan events, time your fundraising appeals, recognize volunteers, and build campaigns that are relevant to your community.</p>
```

**enable_insights_section:** true
**content_title:** `Everything you need to plan July, August, and September`

**insights_list (3 items):**
| insight_title | insight_description |
|---|---|
| Month-to-Month: | At-a-glance views for July, August, and September 2026 with key dates already mapped out, so that nothing sneaks up on you. |
| Awareness Months and Weeks: | From Parks and Rec to Management Training Week and Blood Cancer Awareness Month, connect with the causes your community cares about and better plan your messaging. |
| Campaign Tips and Inspiration: | Practical ideas for connecting each moment to your fundraising, membership, volunteer, and stewardship efforts. |

**resource_details_after_checklist (cleaned — bold lead-in, this is actually the CTA-band setup text baked into the WYSIWYG field on the legacy page, redundant with the structured `cta_-_*` fields below; use the structured fields, not this paragraph, when rebuilding):**
```html
<p><strong>Looking for even more ideas, inspiration, and marketing templates?</strong></p>
<p>Don't miss our <a href="https://momentivesoftware.com/resources/" target="_blank" rel="noreferrer noopener">resources</a> and blog, which are regularly updated to help you reach your organization's goals!</p>
```

**cta_-_enable_cta_section:** true
**cta_-_title:** `Looking for even more ideas, inspiration, and marketing templates?`
**cta_-_description:** `Don't miss our resources and blog, which are regularly updated to help you reach your organization's goals!`
**cta_-_button_1_text / link:** `Explore our Resources` → `/resources/`
**cta_-_button_2_text / link:** `Read the Blog` → `/blog/`

**Excerpt:** `Download the free Q2 2026 fundraising calendar. Plan campaigns around key dates, awareness months, and days of giving for April, May, and June.`

_(No `details_cta`, no checklist — the insights section replaces it, same rule as whitepapers.)_

---

## #3 — Nonprofit Goal Planning: Worksheets to Set Your Fundraising Goals

> **Additional resource link**, gated, with `details_cta`. Same shape as the whitepaper "additional link → external URL" permutation.

- **slug:** `how-to-set-a-fundraising-goal`
- **guide_type:** guides
- **date:** 2026-05-19
- **categories:** Fundraising
- **_thumbnail_id (legacy):** 10496

**enable_gated_content:** true
**form_heading:** `Get the Guide`
**enable_additional_resource_link:** true
**resource_link:** `https://go.momentivesoftware.com/hubfs/018%20Givesmart/Whitepaper/GS_Goal_Planning_Worksheet_2022_WithFields.pdf`
**resource_link_text:** `Download guide`
**resource_link_open_in_new_tab:** true

**resource_checklist_title:** `Open These Worksheeets for:`
**details_cta:** `Ready to take your fundraising to the next level? Download your free Goal Planning Worksheets now and get started on reaching your ambitious goals today.`

**Excerpt:** `Learn how to set a fundraising goal that is realistic, data-driven, & built to rally your team & donors around a shared target.`

_(No `resource_details_after_checklist`, no `resource_header_overline`.)_

---

## #4 — 2024 Impact Report

> **Not gated** — direct external link (a Turtl-hosted interactive report) replaces the form entirely, same pattern as the one not-gated whitepaper. Also has `resource_details_after_checklist`. `_thumbnail_id` and `resource_hero_image` are the same attachment (10086) — migration must not double-sideload.

- **slug:** `2024-impact-report`
- **guide_type:** guides
- **date:** 2025-03-17
- **categories:** Fundraising
- **_thumbnail_id / resource_hero_image (legacy):** 10086 / 10086 (same attachment)

**enable_gated_content:** false
**resource_link:** `https://momentive.turtl.co/story/2024-impact-report/`
**resource_link_text:** `Click here to view the report`
**resource_link_open_in_new_tab:** true

**resource_details (cleaned, opening lines):**
```html
<p>It was another growth year at GiveSmart; our partner organizations raised a record-breaking amount in 2024, supporting the missions and communities they serve.</p>
<p>This report highlights our customers' fundraising achievements and offers insight into their success. Our experts also provide some...</p>
```

**resource_checklist_title:** `Some impressive GiveSmart customer insights include:`

**Checklist:**
- Organizations raised $1.6 billion
- $26.7 million was raised via recurring giving
- Professional Service Advisors average 9.5 years of experience in nonprofit fundraising or events
- Silent auction, instant buy, live auction, raffle, voting, and ticketing revenue all grew in 2024
- When given the option, 49% of donors choose to cover credit card fees

**resource_details_after_checklist:** opens with an `<h3>` — `About our 2024 Impact Report` — followed by paragraph(s) on data sourcing/methodology.

_(No `details_cta`, no `form_heading` (no form to head), no additional link on top of the primary link.)_

---

## #5 — Alternatives to Golf Fundraising

> **`resource_header_overline`** in active use — a short punchy eyebrow line rendered above the description, distinct from the post title. Standard gated shape otherwise.

- **slug:** `outdoor-fundraising-ideas`
- **guide_type:** guides
- **date:** 2026-05-19
- **categories:** Fundraising
- **_thumbnail_id (legacy):** 10493 — **resource_hero_image (legacy):** 10492 (different)

**resource_header_overline:** `Ditch the golf cart. Grow your donor base.`

**enable_gated_content:** true
**form_heading:** `Open the Guide Today`

**resource_details (cleaned, opening):**
```html
<p>Golf events are expensive, niche, and long. The alternatives in this guide have lower overhead and higher energy and can work for all organizations.</p>
<p>We've compiled a list of outdoor fundraising ideas that are not...</p>
```

**resource_checklist_title:** `Open the Guide today for fundraising ideas such as:`

**Checklist:**
- Lawn game tournaments: Cornhole, bocce, giant Jenga — sell league tickets and let sponsors own each station.
- Races and runs: Low entry cost, no attendee cap, and a swag bag that markets your cause long after the finish line.
- Sports competitions: Sell player and spectator tickets. Livestream for supporters who can't attend in person.
- Outrageous experiences: Exclusive, limited-seat events that create urgency and deepen donor relationships.

**details_cta:** `Your donors are eager to support your organization, but if you're doing the same thing every year, they may lose interest. Mixing it up and creating new experiences can help your organization stand out this year.`

**Excerpt:** `Find the best outdoor fundraising ideas for nonprofits looking to boost donor turnout & create memorable giving experiences.`

_(Note the other overline post, "Complete Mobile Strategy Guide" (#10488), is a near-identical permutation — either can serve as the second overline example if you want a spare.)_

---

## #6 — 2025 Momentive Nonprofit Research Study

> **The richest post in the corpus.** Custom image section, webinar-promo CTA band, previous-studies grid, a **custom icon checklist** (the only one in the export), and the two-form/two-portal wrinkle. Treat this as the "build everything once" reference for the research-study layout.

- **slug:** `nonprofit-trends`
- **guide_type:** research-study
- **date:** 2025-06-11
- **categories:** Accounting, Data Analytics, Fundraising
- **_thumbnail_id:** 1808 — **resource_hero_image:** 1712 — **research_hero_image:** 1809 _(three distinct images)_

**custom_header_overline:** `2025 Momentive Nonprofit Research Study`
**custom_header:** `Weathering Federal Uncertainty: Nonprofit Operational Trends`

**resource_details (cleaned):**
```html
<p>Nonprofits are facing tighter grant restrictions and greater operational pressure — but some continue to grow despite uncertainty. What's their secret?</p>
<p>Organizations are seeking stability, revenue growth, and ways to broaden their mission. The throughline that connects all of that? Technology. New research from Momentive Software shows a clear pattern: nonprofits that adopt technology early are more optimistic, more flexible, and more financially prepared than their late-adopting counterparts.</p>
```

**Two forms (confirm intent with Daniel before building — see wrinkle note above):**
- **"Preview" form** — `form_heading`: `Fill out the form to download the preview`; HubSpot formId `0e198dcb-0591-4e67-872e-45c1a5e9ee1c`, **portalId `46621937`** (⚠ non-standard portal)
- **"Full report" form** — `research_form_header`: `Download the Complete 2025 Research Study`; HubSpot formId `09b2ee8e-2e8a-41cd-9f1a-93b35b097b95`, portalId `46621835` (standard), plus `sfdcCampaignId: 701Ph00000sRp7yIAC`

**resource_checklist_title:** `Preview the trends report to learn:`
**resource_checklist_type:** `custom`

**Custom checklist (icon + description; icon slugs carry the legacy `box-` prefix — strip it exactly like the case-study migration does, e.g. `box-bx-chart` → `bx-chart`):**
| icon (cleaned) | description |
|---|---|
| bx-chart | What high-performing nonprofits are doing differently |
| bx-bullseye | Where tech investment is driving revenue growth |
| bx-trophy | How flexibility is shaping 2025 strategies |

**details_cta:** `Uncertainty doesn't have to mean instability. See how leading nonprofits are building resilience and how your organization can too.`

**Custom image section:**
- `custom_image`: 1856, `custom_image_mobile`: 1857 (separate mobile image — `separate_image_mobile: true`)
- `custom_image_subheader_text`: _"Professionals in the nonprofit sector continue to face extraordinary challenges and opportunities in this rapidly changing environment. As we navigate through political and economic uncertainties, technological shifts, and changing donor behaviors, the need for data-driven insights has never been more critical."_
- `custom_image_header_text`: empty (subheader-only variant)
- `custom_image_rounded_corners`: false / `custom_image_rounded_corners_mobile`: true

**CTA band (webinar promo):**
- `cta_header`: `How nonprofits are thriving amidst uncertainty: View the on-demand webinar`
- `cta_text`: `Watch Momentive Software experts as they discuss topline trends from the 2025 Momentive Nonprofit Research Study, including how top-performing organizations leverage technology, diversify revenue streams, and discover new ways to thrive in an uncertain climate.`
- `cta_image`: 1806
- `cta_button_text` / `cta_button_link`: `Watch the webinar` → `https://momentivesoftware.com/webinars/2025-nonprofit-trends-study/`

**Previous studies grid (1 card):**
- `previous_studies_heading`: `Explore Previous Studies`
- `previous_resource_type`: `Nonprofit Trends Study`
- Card: year `2024`, image `5593`, title "The State of Nonprofit Fundraising, Technology and Operations: Impact on Mission Sustainability", description "In a time of fundraising shortfalls, economic uncertainty, staffing shortages, and day-to-day operational challenges, nonprofit leaders are innovating to drive efficiency. It's a transformative time for nonprofits and organizations embracing opportunities.", download link → HubSpot-hosted PDF

**Excerpt:** `From investing in and leveraging technology to exploring the future of nonprofit operations—organizations are pursuing ways to grow revenue through optimism, flexibility, and futureproofing.`

_(No insights_1/insights_2 sections on this post — those appear on #7–#9 instead.)_

---

## #7 — Bridging the Gap: Aligning Association Professionals and Members for Success

> **Both insight sections + previous-studies grid (3 cards) + CTA band.** The fullest example of the "modern" research layout (no custom image section, no custom checklist — just hero/subheader/form + two insight blocks + CTA + previous studies).

- **slug:** `association-trends`
- **guide_type:** research-study
- **date:** 2025-10-16
- **categories:** Association Management, Data Analytics, Event Management
- **_thumbnail_id:** 5031 — **research_hero_image:** 5032 _(`resource_hero_image` empty — this post skips that slot entirely)_
- **study_type:** `Associations Trends Study` (the one post where this field is populated — appears otherwise unused/redundant with the card titles)

**custom_header_overline:** `2025 Momentive Association Research Study`
**custom_header:** `Bridging the Gap: Aligning Association Professionals and Members for Success`

**research_subheader (note the larger-font lead line):**
```html
<p><span style="font-size: 30px">A decade of insights to guide your organization into the future</span></p>
<p>Over the past decade, much has changed for associations. But what comes next for your organization?</p>
<p>Now more than ever, associations must close critical gaps with their members to recruit, retain, and boost revenue. Read the 10th annual Association Trends Study for actionable strategies to reimagine events, advance AI, evolve professional development, and tap into member loyalty trends—helping you secure your organization's future.</p>
```

**research_form_header:** `Download the Complete Research Study`
**research_form_code:** formId `26396277-8649-4743-968d-c7e886fc507e`, portalId `46621835`, `sfdcCampaignId: 701Ph00000mP5QfIAK`

**Insight 1:**
- kicker: `Insight 1` / title: `Advance your association with AI`
- description: "AI is a present-day priority; the shift from exploration to execution is well underway. Associations have an opportunity to lead with innovation, align with member expectations, and build smarter, more responsive operations."
- key_takeaway: "AI adoption is accelerating, and leadership is on board. Now's the time for associations to move from interest to action."
- button: `Download the full report` → `#form`
- stats: `40%` (accent `#6a4ed8`) "of organizations now use AI (with policies in place)"; `61%` (accent `#d73f5d`) "of boards support AI adoption"

**Insight 2:**
- kicker: `Insight 2` / title: `Association events a value-driver for members and leaders`
- description: "Events are strategic assets, playing a major role in your association's financial sustainability. Additionally, events go beyond your bottom line, delivering high-value experiences to engage members, showcase leadership, and reinforce your mission."
- key_takeaway: "Events are both a financial engine and leadership priority, making them a powerful driver for member engagement and organizational growth."
- button: `Get the full study` → `#form`
- stats: `#1` (accent `#f26522`) "priority for association leaders is events"; `2` + suffix `<sup>nd</sup>` (accent `#61c6d2`) "largest revenue stream for associations is events" — **note the `<sup>` in `number_suffix`, must survive as raw markup, not escaped text**

**CTA band:** header "Watch our on-demand webinar revealing groundbreaking findings from our comprehensive survey of association professionals and members", image 3548 (**⚠ this attachment ID is not present in the WXR's attachment list — flag as unresolved media, source manually**), button "Watch the webinar" → `https://momentivesoftware.com/webinars/2025-association-trends-study-reveal-webinar/`

**Previous studies grid (3 cards)** — 2024 (image 2638), 2023 (image 5477), 2022 (image 5478), each with title/description/HubSpot-hosted PDF link.

**Excerpt:** `Discover key 2026 association trends and insights from our 10th annual report. Learn how to bridge gaps in engagement, retention, and revenue growth.`

---

## #8 — 2025 Small Staff Associations Report

> **Both insight sections + previous-studies grid (1 card), no CTA band.** Confirms the CTA band is independently optional from insights/previous-studies.

- **slug:** `small-staff-associations`
- **guide_type:** research-study
- **date:** 2025-10-30
- **categories:** Association Management
- **_thumbnail_id:** 6444 — **research_hero_image:** 5489 _(`resource_hero_image` empty)_

**custom_header_overline:** `2025 Small Staff Associations Report`
**custom_header:** `Shifting Traditional Strategies to Meet the Unique Needs of Members`

**research_subheader:** larger-font lead "Essential data and strategies to grow membership, revenue, and engagement" + 2 supporting paragraphs on small-staff association challenges/opportunities.

**research_form_header:** `Download the Complete Research Study` — formId `506af6ec-d3e7-4bf3-b127-0d05e9dd436a`, portalId `46621835`, `sfdcCampaignId: 701Ph00000muDa4IAE`

**Insight 1:** "Maximize your AMS investment to build stronger member connections" — stats `83%` (accent `#d73f5d`) / `11%` (accent `#61c6d2`); button "Download Now" → `#form`

**Insight 2:** "Member engagement is the key to long-term retention" — stats `90%` (accent `#f26522`) / `44%` (accent `#6a4ed8`); button "Download the full report" → `#form`

**Previous studies (1 card):** 2024, image 5490, "Benchmark Report: Small-Staff Associations", download link → HubSpot-hosted PDF.

**Excerpt:** `Download the Small Associations Report for expert insights on member engagement, value gaps, tech adoption, and revenue strategies tailored to small associations.`

_(No `enable_cta_section`, no custom image section, no custom checklist.)_

---

## #9 — 2026 Talent Retention Report for the Mission-Driven Workforce

> **Both insight sections + CTA band, no previous-studies grid.** Confirms previous-studies is independently optional too — this is a first-in-series report with nothing to look back on yet.

- **slug:** `talent-retention-report`
- **guide_type:** research-study
- **date:** 2026-03-30
- **categories:** 10 categories (Accounting, Association Management, Career Centers, Certification Management, Data Analytics, Donor Management, Event Management, Fundraising, Learning Management, Volunteer Management) — **widest category spread in the corpus**
- **_thumbnail_id:** 9499 — **research_hero_image:** 9500

**custom_header_overline:** `Momentive Research Study` _(no `custom_header` — title-only variant)_

**research_subheader:** larger-font lead "New research on talent retention, career development, technology, and belonging across nonprofits and associations" + paragraph naming the research partner: "This report, conducted by Wakefield Research and commissioned by Momentive Software, examines the priorities, challenges, and experiences of nonprofit and association employees..."

**research_form_header:** `Get the Full Report: Retaining Mission-Driven Talent in 2026` — formId `c2f632ce-8585-463e-af28-22b825a5bd54`, portalId `46621835`, `sfdcCampaignId: 701Ph000014u0M1IAI`

**Insight 1 (4 stats — the most of any post):** "Why employees leave—and what gets them to stay" — `64%`, `65%`, `92%`, `66%`, each with its own accent color; button "Download the full report" → `#form`

**Insight 2 (4 stats):** "How disconnected tech is driving staff burnout" — `82%`, `63%`, `71%`, `73%`; button "Get the full study" → `#form`

**CTA band:** header "Join our upcoming webinar revealing what the data says about retaining staff", image 9204, text on the webinar series, button "View this Webinar Series" → `https://momentivesoftware.com/webinars/mission-driven-workforce-research-2026/`

**Excerpt:** `Download the 2026 talent retention report. Research on career development, tech burnout & what keeps staff engaged.`

_(No previous-studies section — draft #9027 "Sneak Peek into the 2026 Mission-Driven Workforce Report" is this report's abandoned preview-stage precursor; see notes below on whether to migrate it.)_

---

## #10 — 2026 Nonprofit Trends Preview Report: Rebuilding the Trust Economy

> **`guide_type: research-study` but renders as a plain `guides` page.** Every research-only field (`custom_header`, `research_hero_image`, `research_form_code`, insights, CTA band, previous-studies) is empty. Instead it uses the exact `guides`-shape fields: `resource_details`, `resource_header_overline`, `resource_hero_image`, `resource_link` + `enable_additional_resource_link`. Proves the migration script must branch on field presence, not the `guide_type` label. Also the only post in the export with an empty excerpt.

- **slug:** `nonprofit-trends-report-2026`
- **guide_type:** research-study _(misleading — see above)_
- **date:** 2026-07-13 (newest post in the corpus)
- **categories:** Accounting, Fundraising, Volunteer Management
- **_thumbnail_id:** 11537 — **resource_hero_image:** 11538

**resource_header_overline:** `2026 Nonprofit Trends Report`

**resource_details:**
```html
<h3>New research on donor trust, operational infrastructure, technology integration, and financial resilience across the nonprofit sector</h3>
<p>Mission-driven organizations are deeply committed to their communities, but traditional outreach tactics are no longer enough to secure long-term donor loyalty. This report, conducted by Wakefield Research and commissioned by Momentive Software, examines shifting donor expectations, technological bottlenecks, and the functional realities facing nonprofits to identify the critical factors between back-office data and external fundraising. This research shows how nonprofits can repair the trust economy.</p>
```

**enable_gated_content:** true
**form_heading:** `Sign up now to get the full research report when it releases!`
**HubSpot formId:** `88aac011-ea4d-467b-bc6d-9d89bf0aab48` (portalId `46621835`)

**enable_additional_resource_link:** true
**resource_link:** `https://momentive.turtl.co/story/2026-nonprofit-trends-preview-study/page/1`
**resource_link_text:** `Download the Preview Study`
**resource_link_open_in_new_tab:** true

**Excerpt:** _(empty — omit, no fallback needed, same rule as the whitepaper/infographic corpora)_

---

## Notes discovered during analysis

**This CPT needs new template work, not just content migration.** Unlike whitepapers → infographics (where the second migration was mostly "reuse the pattern, adjust for gated/ungated split"), the `research-study` subtype introduces real new components: animated stat callouts with a per-item custom accent color, a previous-studies card grid, a webinar-promo CTA band, and a full-width custom-image section. Recommend scoping the `research-study` build as its own small project rather than treating it as a drop-in extension of the whitepaper pattern.

**RESOLVED — research-study posts have a preview → full-launch lifecycle, confirmed against the live site.** Daniel checked the live `nonprofit-trends` page (#6) after rebuilding it: no sign of the second ("preview") HubSpot form anywhere on it. That, plus checking the actual relationship between #6 and #10, unifies three things this sheet originally flagged separately as open questions:

- **#10 (`nonprofit-trends-report-2026`) is not #6's counterpart — it's the *next annual edition's* preview.** #6 is "2025 Momentive Nonprofit Research Study" (published 2025-06-11); #10 is the preview teaser for the 2026 edition of that same series (published 2026-07-13, the newest post in the whole corpus). Same series, consecutive years, each year apparently going through its own preview → full cycle.
- **The 3 "abandoned duplicate" drafts are earlier preview stubs, not stray duplicates:** `2024-association-trends-study` (draft 2636) and `2025-association-trends-study` (draft 3950) both precede the published `association-trends` (#7); `talent-retention-report-preview` (draft 9027) precedes the published `talent-retention-report` (#9). Each pair is a preview/full pair for the same report, same shape as #6/#10.
- **Unlike webinars, this is NOT an automatic date-driven lifecycle.** Webinars flip upcoming→on-demand as a pure function of `webinar_date` vs. today, so a resolver function (`momentive_resolve_webinar_form()`) makes sense there. Research-study previews have no equivalent computable trigger — "the report is ready" is an editorial decision, not a date. The legacy practice confirms this: every preview→full transition in this corpus is two SEPARATE posts (different slug, sometimes the preview stays a draft and gets abandoned/retired rather than converted) — never one post whose rendering flips in place. **No PHP-level state-resolver is needed for this CPT.** The only thing that needs to happen when a report "graduates" is: publish the full post (new or reused ID, editor's call) and set `guide_type` to Research Study — see the Permalinks/CSS note below for why that one field is enough to drive everything else automatically.

Given this, the previously-open "confirm before migrating" question is resolved: the 3 drafts are real historical preview stubs, not junk duplicates. Still Daniel's call whether to migrate them as drafts (keeps history, no live purpose) or skip them (the pattern doesn't require them) — flagging the resolved context so that's now an informed choice rather than a data-quality concern.

**One attachment referenced by the export doesn't exist in the export's own attachment list.** `cta_image` on post #7 (Bridging the Gap) points to legacy attachment ID 3548, which has no matching `<item>` of type `attachment` anywhere in this WXR. Every other image referenced across all 10 reference posts resolves fine. When you rebuild #7, you'll need to source that CTA image manually — the migration script will log it as unresolved rather than guess.

**One post has no category** — "The State of Nonprofit Fundraising, Technology and Operations: Impact on Mission Sustainability" (a `guides`-type post, not one of the 10 numbered references above, since it's the *previous study* referenced inside #6's card grid). Valid edge case, same as the infographic corpus — leave the category panel empty.

**`resource_checklist_type` is almost always `checkmarks`.** Only post #6 uses `custom` (icon checklist). Hardcode `checkmarks` as the default rendering and special-case `custom` only when the icon repeater is actually populated.

**Two visually distinct "CTA" field groups exist — don't conflate them:**
- `cta_-_*` (with the literal hyphen in the meta key) → the 2-button "looking for more resources" band, used once, on a `guides` post (#2).
- `cta_header` / `cta_text` / `cta_image` / `cta_button_*` (no hyphen) → the single-button webinar-promo band, used 4 times, exclusively on `research-study` posts (#6, #7, #9, and draft #2636).

These are genuinely different Elementor field groups that happen to share the word "CTA" — build them as two separate blocks/patterns, not one configurable component.

**`previous_resource_type` appears redundant.** It's a short label ("Nonprofit Trends Study", "Associations Trends Study", "Small Staff Report") set alongside `previous_studies_heading` but every card in the grid already carries its own `previous_resource_title`. Unclear what (if anything) renders `previous_resource_type` on the legacy live site — worth checking the live pages before deciding whether to migrate it into a visible field or drop it.

**HubSpot portal is `46621835` everywhere except one field on one post.** Post #6's `hubspot_form_code` (the short "preview" form, not the `research_form_code` full-report form) uses portal `46621937` — one digit different, and the only non-standard portal anywhere in this export or any prior migration. **RESOLVED:** this is dead leftover data, same category as `hero_video_source`/`series_order` elsewhere in this migration — confirmed by checking the live #6 page, which shows no trace of a second form. It's very likely a remnant from when #6 itself was in its own preview stage (see the resolved lifecycle note above) before the field group got overwritten with the full study's content. Don't migrate `hubspot_form_code` on a post that also has `research_form_code` populated — the latter wins, the former is inert.

**`resource_header_overline` vs `custom_header_overline` — two different eyebrow fields for two different layouts.** `resource_header_overline` is a `guides`-shape field (used on #5 and #10); `custom_header_overline` is the `research-study`-shape field (used on #6–#9). Same visual role, different field, different layout context — don't merge them into one ACF field on the rebuilt CPT unless the rebuilt template genuinely unifies both layouts' heroes.

**Visual chrome (hero background, sidebar color) should be driven by `guide_type` in CSS, not per-post block Styles.** Rebuilding #6 required manually wrapping the layout in an extra `hero-background` group with a hand-set vertical gradient, plus a white (`backgroundColor: base`) sidebar column — neither of which the plain `guides` layout has (white page background, `superlight`-tinted sidebar instead). This is a real, recurring difference between the two subtypes, not a one-off. Since `guide_type` already exists as a real post-level field (added for the permalink split — see inc/guides.php), the same field can drive this chrome automatically via a body class (mirroring the site's existing `--page-accent-color`-on-`body` pattern for Solutions/Products), so switching a post from preview-shape to full research-study-shape only ever requires flipping one field, never re-tinkering the Styles panel per post. See the design-notes docblock in inc/guides.php once that's added.

**Confirmed deliberate (not bugs) after review of the first 10 rebuilt posts — recorded here so they aren't mistaken for inconsistencies later:**

- **Social-share sits inside the old-style insights group, not after it, on Campaign Planning Calendar (#2) — this is unconditional, not about what comes after.** Same convention already documented for whitepapers in CLAUDE.md: whenever `enable_insights_section` populates that group, `momentive/social-share` goes inside its container rather than as its own block after `hero-background` closes — inherited unchanged from the whitepaper migration's own hardcoded behavior. #2 also has the `cta_-_*` full-width "looking for more?" band, which appears *after* the insights group, but that doesn't move social-share out of it — the two components are independent, and the insights-group placement rule doesn't depend on which section ends up last on the page. Every other reference post has no insights section at all, so `social-share` sits at the top level there instead.
- **The `wp:query-title` top label is present in every guide post's content, including fully-launched research-study posts — it's hidden by CSS on those, not omitted from the markup.** The legacy site never showed the generic label once a study left preview, but every guide post uses the same `wp:query-title {"type":"post-type","showPrefix":false,"className":"top-label"}` block regardless of `guide_type` (consistent editor experience, one pattern, no per-post branching on which blocks to include). `body.is-research-study .top-label { display: none; }` in gate.scss suppresses it visually for the fully-launched state only — previews and plain guides still show it. The 4 posts rebuilt before this was settled (#6/nonprofit-trends, #7/association-trends, #8/small-staff-associations, #9/talent-retention-report) are each missing the block entirely and should have it added back for consistency, even though the end result looks the same once suppressed.
- **The on-page H1 can differ from the post title via the `hero_title` ACF field (Guide Settings group), not by swapping `wp:post-title` for a static Heading block.** The legacy site had exactly this kind of override (an SEO/permalink-oriented post title vs. a shorter on-page headline). 3 of the first 10 rebuilt posts (#1/donor-segmentation, #6/nonprofit-trends, #8/small-staff-associations) used a hardcoded `wp:heading` in place of `wp:post-title` to get this — which works today, but silently stops tracking the post title if it's ever changed via Quick Edit, since the H1 text is just typed into the block rather than reading the title field. Those 3 posts should be switched back to the ordinary `wp:post-title` block with the shortened headline moved into `hero_title` instead.
