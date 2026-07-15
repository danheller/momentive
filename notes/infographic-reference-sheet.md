# Infographic Rebuild — Reference Sheet (5 coverage posts)

Decoded, Word-artifact-cleaned content for the five posts that together exercise every field and permutation across all 14 published infographics.

**Source export:**
- `momentivesoftware.infographics.current.2026-07-01.xml` — 15 `infographics` posts (14 published + 1 empty draft)

**Key difference from whitepapers:** infographics are mostly *ungated* — 8 of 14 posts deliver the asset via a direct download link rather than a HubSpot form. The gated/ungated split is the central structural decision per post.

---

## Field → destination map (applies to all posts)

| Legacy field | Rebuilt destination | Notes |
|---|---|---|
| `resource_hero_image` (attachment ID) | `hero_image` ACF field | Always set on all 14 posts. Usually different from `_thumbnail_id`. |
| `_thumbnail_id` (attachment ID) | Featured image (`_thumbnail_id`) | Archive card image. Sometimes the same attachment as `resource_hero_image` (see #2). |
| `enable_gated_content` | Layout toggle | `true` on 6/14 posts → shows HubSpot form. `false` on 8/14 posts → shows direct download link instead. |
| `hubspot_form_code` | HubSpot embed block (gated only) | Full `<script>` embed; portalId always `46621835`. |
| `form_heading` | Form section heading (gated only) | Plain text. Varies per post. |
| `resource_link` | Download/view button URL | **Primary delivery for all 8 ungated posts.** Also present on 2 gated posts alongside the form (via `enable_additional_resource_link`). Points to HubSpot-hosted PDFs or external infographic pages. |
| `resource_link_text` | Button label | Plain text. Varies: "Download Now", "Open the infographic", "View Infographic", etc. |
| `resource_link_open_in_new_tab` | Button target | `true` on all posts that have a link — always opens in a new tab. |
| `enable_additional_resource_link` | Extra button/link | `true` on 2/14 posts (both happen to also be present on specific gated/ungated posts — see #4 and #5). |
| `resource_details` (HTML) | Description paragraphs | 14/14 posts. Word artifact cleanup applies (same patterns as whitepapers/webinars). 2 posts use `<h4>` headings inside — convert to `core/heading` blocks. |
| `details_cta` | Closing CTA sentence | Plain text. Present on 2/14 posts. Appears after `resource_details`, before the checklist. |
| `resource_checklist_title` | Checklist section heading | Present on 10/14 posts. |
| `resource_checklist` (PHP serialized) | Checklist items | 10/14 posts have items. **1 post (`silent-auction-tips`) has HTML anchor tags inside items** — treat as raw HTML list items, not plain text. |
| `resource_details_after_checklist` (HTML) | Additional paragraphs after checklist | Present on 4/14 posts. |
| category terms | Native category panel | Solution categories. 1 post (`giving-tuesday-statistics`) has no category — that's valid. |
| post title | Post title | |
| post excerpt | Post excerpt | 13/14 have an excerpt. 1 post (`recurring-giving-stats`) has no excerpt. |

**Fields NOT migrated (dead Elementor defaults — always empty or false on all 14 posts):**
- `hero_video_source` / `hero_library_video` / `hero_link_video` — `hero_video_source` is hardcoded to `wistia` on all 14 posts but both video URL fields are empty. This is a leftover template default, not actual video content. **Ignore entirely.**
- `enable_cae_credits_module`, `enable_video_module`, `enable_related_resources`, `enable_cta_box`
- `enable_insights_section`, `insights_list`, `resource_quote`, `enable_popup_forms`
- `series_section_*`, `static_utm_content`, `enable_series_section`

---

## Permutations covered by the 5 reference posts

| # | Post | Permutation |
|---|---|---|
| 1 | 7 Ways to Prevent Association Staff Burnout | **Typical gated** — form + checklist, no extras (~4 posts) |
| 2 | 5 Stats for #GivingTuesday Growth | **Typical ungated** — direct link + checklist, no category, same thumbnail/hero ID |
| 3 | 6 Stats to Help Nonprofits Navigate Economic Instability | **Gated + `details_cta`** — closes the description with a summary sentence |
| 4 | 9 Stats that Prove Why You Should Implement Recurring Giving | **Ungated + `enable_additional_resource_link`** + `resource_details_after_checklist`, no excerpt |
| 5 | 4 Silent Auction Facts to Start a Bidding War | **Gated + `enable_additional_resource_link`** + HTML-link checklist items (novel!) |

---

## Word artifact cleanup

`resource_details` and `resource_details_after_checklist` carry MS-Word span contamination in roughly half of posts. Strip the same patterns as whitepapers and webinars:
- Spans with Word class names: `NormalTextRun`, `TextRun`, `SCXW*`, `BCX*`, `EOP`, etc.
- Attributes: `data-contrast`, `data-ccp-props`, `data-ccp-charstyle`, `xml:lang`
- Empty `<p>` tags and `&nbsp;`-only paragraphs

2 posts (`association-volunteer-engagement`, `build-a-strong-data-governance-policy`) use `<h4>` inside `resource_details`. Convert to `core/heading` blocks at level 4 (or promote to level 3 per the rebuilt design).

---

## #1 — 7 Ways to Prevent Association Staff Burnout

> **Typical gated.** HubSpot form on the right, description + 3-item checklist on the left. No optional sections. Most common pattern for the 6 gated infographics. `_thumbnail_id` (11016) and `resource_hero_image` (11015) are different attachment IDs.

- **slug:** `7-ways-to-prevent-association-staff-burnout`
- **live URL:** https://momentivesoftware.com/resource-center/infographics/7-ways-to-prevent-association-staff-burnout/
- **date:** 2026-06-09
- **categories:** Association Management
- **_thumbnail_id (legacy):** 11016
- **resource_hero_image (legacy):** 11015

**enable_gated_content:** true  
**form_heading:** `Download the infographic`  
**HubSpot formId:** `71c42d85-00b8-4445-b95d-f246f3c828b8` (portalId `46621835`)

**resource_details (cleaned):**

```html
<p>Most associations don't have a burnout problem. They have a systems problem.</p>
<p>Disconnected tools, repetitive tasks, and constant demands are draining your team and putting retention at risk.</p>
<p>It doesn't have to be this way.</p>
```

**resource_checklist_title:** `Inside, you'll find: `

**Checklist:**
- Where manual work is quietly draining your team's time and energy
- Which changes can you make now, before burnout becomes a resignation
- What you can change now, without rebuilding your systems from scratch

**Excerpt:**
`Download the infographic to learn 7 practical ways associations can reduce staff burnout, improve retention, and fix systems that slow teams down.`

_(No `details_cta`, no `resource_details_after_checklist`, no `enable_additional_resource_link`.)_

---

## #2 — 5 Stats for #GivingTuesday Growth

> **Typical ungated.** No form — instead a direct download link button to a HubSpot-hosted PDF. Also the only post with **no solution category** and the only post where `_thumbnail_id` and `resource_hero_image` are the same attachment ID (10643). Migration must handle both the no-category and same-ID cases without erroring.

- **slug:** `giving-tuesday-statistics`
- **live URL:** https://momentivesoftware.com/resource-center/infographics/giving-tuesday-statistics/
- **date:** 2026-05-26
- **categories:** _(none)_
- **_thumbnail_id (legacy):** 10643
- **resource_hero_image (legacy):** 10643 _(same attachment — thumbnail and hero are identical)_

**enable_gated_content:** false  
**resource_link:** `https://go.momentivesoftware.com/hubfs/018%20Givesmart/Infographics/LG-GS-CT-INF-2025-08-Giving_Tuesday_Infographic.pdf`  
**resource_link_text:** `Download Now`  
**resource_link_open_in_new_tab:** true

**resource_details (cleaned):**

```html
<p>Mark your calendars: #GivingTuesday 2025 falls on December 2. With an estimated $3.74 billion projected to be raised this year, organizations can leverage this global day of giving to experiment with fresh fundraising strategies—and attract new supporters in the process.</p>
<p>This infographic offers stats to influence your #GivingTuesday communication, strategy, and plans.</p>
```

**resource_checklist_title:** `Nonprofits will find insights on:`

**Checklist:**
- Diverse forms of #GivingTuesday support including volunteerism and goods and services
- Why recurring giving really pays off
- Ideal timing for sending #GivingTuesday e-mails and social posts
- And more!

**Excerpt:**
`Explore the latest Giving Tuesday statistics in this easy-to-share infographic. See donation trends, donor behavior, & key insights.`

_(No `details_cta`, no `resource_details_after_checklist`.)_

---

## #3 — 6 Stats to Help Nonprofits Navigate Economic Instability

> **Gated + `details_cta`.** The only gated infographic with a `details_cta` — a summary sentence that appears after `resource_details` and before the checklist. Demonstrates the full left-column structure: description → cta sentence → checklist. Heavy Word artifact contamination in `resource_details`.

- **slug:** `nonprofit-economic-resilience-strategies`
- **live URL:** https://momentivesoftware.com/resource-center/infographics/nonprofit-economic-resilience-strategies/
- **date:** 2026-05-19
- **categories:** Fundraising
- **_thumbnail_id (legacy):** 10507
- **resource_hero_image (legacy):** 10506

**enable_gated_content:** true  
**form_heading:** `Download the Free Infographic`  
**HubSpot formId:** `f4ab626c-42b0-44c2-be0a-be9954a741c8` (portalId `46621835`)

**resource_details (cleaned):**

```html
<p>Economic uncertainty doesn't pause for the work you do, and neither should your fundraising strategy.</p>
<p>Whether you're navigating shifting donor priorities, absorbing funding cuts, or trying to diversify revenue streams, this infographic gives you the data and the direction to move forward with confidence.</p>
```

**details_cta:** `As you consider these insights, remember: The nonprofits that thrive during hard times aren't the ones with the biggest budgets. They're the ones with the most adaptable strategies.`

**resource_checklist_title:** `Inside the infographic, you'll discover:`

**Checklist:**
- The #1 revenue stream nonprofits are prioritizing right now
- Why recurring donors are worth 5.4x more than one-time givers — and how to get more of them
- The fundraising platform features most nonprofits aren't using (but should be)
- What the AmazonSmile shutdown taught us about revenue diversification
- How Donor Advised Funds have grown 4x in a decade, and why 68% of nonprofits are still leaving them on the table

**Excerpt:**
`Explore proven economic resilience strategies designed to help nonprofits diversify revenue, strengthen donor relationships, and adapt to economic uncertainty.`

_(No `resource_details_after_checklist`, no `enable_additional_resource_link`.)_

---

## #4 — 9 Stats that Prove Why You Should Implement Recurring Giving

> **Ungated + `enable_additional_resource_link` + `resource_details_after_checklist`.** No form, no excerpt. `enable_additional_resource_link: true` on an ungated post means the download link doubles as both the main CTA and the explicit button — in practice both `resource_link` and the extra-link fields point to the same PDF URL. Also the only published post with an empty `post_excerpt`.

- **slug:** `recurring-giving-stats`
- **live URL:** https://momentivesoftware.com/resource-center/infographics/recurring-giving-stats/
- **date:** 2026-05-14
- **categories:** Fundraising
- **_thumbnail_id (legacy):** 10366
- **resource_hero_image (legacy):** 10365

**enable_gated_content:** false  
**enable_additional_resource_link:** true  
**resource_link:** `https://go.momentivesoftware.com/hubfs/018%20Givesmart/Infographics/Infographic_GS_GS-Recurring-Giving_2025.pdf`  
**resource_link_text:** `Open the infographic now!`  
**resource_link_open_in_new_tab:** true

**resource_details (cleaned):**

```html
<p>Recurring giving isn't just a trend — it's a proven strategy for building sustainable, long-term support. The powerful stats in this infographic highlight why nonprofits should prioritize recurring giving donations to drive more revenue with less effort.</p>
```

**resource_checklist_title:** `This infographic shares more on:`

**Checklist:**
- Tips for inspiring and stewarding new monthly donors
- The number of years recurring donors continue to give
- How GiveSmart monthly donors compare to overall giving averages
- And more!

**resource_details_after_checklist (cleaned):**

```html
<p>Implementing recurring giving is one of the smartest moves your nonprofit can make. It drives consistent revenue, deepens donor loyalty, and creates a reliable foundation for your mission — all without requiring a major overhaul of your fundraising program.</p>
```

**Excerpt:** _(empty — omit; no fallback needed)_

---

## #5 — 4 Silent Auction Facts to Start a Bidding War

> **Gated + `enable_additional_resource_link` + HTML checklist items.** The only gated post with an extra link alongside the form. More importantly, the only post where `resource_checklist` items contain full HTML anchor tags — the "checklist" is actually a related-resources navigation list, not plain-text bullet points. The migration must preserve the `<a href>` markup rather than escaping it. `_thumbnail_id` (10166) and `resource_hero_image` (10166) are the same attachment.

- **slug:** `silent-auction-tips`
- **live URL:** https://momentivesoftware.com/resource-center/infographics/silent-auction-tips/
- **date:** 2025-10-08
- **categories:** Fundraising
- **_thumbnail_id (legacy):** 10166
- **resource_hero_image (legacy):** 10166 _(same attachment — thumbnail and hero are identical)_

**enable_gated_content:** true  
**enable_additional_resource_link:** true  
**resource_link:** `https://go.momentivesoftware.com/hubfs/018%20Givesmart/LG-GS-INF-4-Auction-Items-Guaranteed-Start-Bidding-War_2025.pdf`  
**resource_link_text:** `Download now`  
**resource_link_open_in_new_tab:** true  
**form_heading:** `Download now`  
**HubSpot formId:** `979bbbd2-d3b6-4596-802c-f8940a7b1e61` (portalId `46621835`)

**resource_details (cleaned):**

```html
<p>Auctions are a lot of work. Are they still worth it? Knowing which auction items could start a bidding war can make all of the difference in your success!</p>
<p>We've got that info for you – and then some! GiveSmart analyzed over 521,600 lines of silent and live auction data from nearly 4,300 organizations. From this data, we've learned which items can make a big impact at your auction.</p>
```

**resource_checklist_title:** `Other Auction resources:`

**Checklist (HTML links — render as raw HTML in list block, NOT as escaped text):**
- `Blog | <a href="https://www.givesmart.com/blog/5-strategic-ways-to-utilize-silent-auction-data-in-successful-fundraising-strategy/" target="_blank">5 Ways to Use Silent Auction Data for Successful Fundraising</a>`
- `Blog | <a href="https://www.givesmart.com/blog/30-silent-auction-ideas-that-dont-cost-a-dime/" target="_blank">30 Best Silent Auction Ideas That Don't Cost A Dime</a>`
- `Guide | <a href="https://www.givesmart.com/resource/the-ultimate-data-driven-auction-fundraising-guide/" target="_blank">The Ultimate Data-Driven Auction Fundraising Guide</a>`
- `Webinar | <a href="https://www.givesmart.com/resource/let-the-bidding-war-begin/" target="_blank">Let the Bidding War Begin</a>`
- `Webinar | <a href="https://www.givesmart.com/resource/beyond-bids-best-practices-and-strategies-for-effective-fundraising-auctions/" target="_blank">Beyond Bids: Best Practices and Strategies for Effective Fundraising Auctions</a>`

**Excerpt:**
`Discover 4 silent auction facts from 521K+ data points that reveal which items drive bidding wars and boost fundraising revenue at your next event.`

_(No `resource_details_after_checklist`, no `details_cta`.)_

---

## Notes discovered during analysis

**`hero_video_source: wistia` is a dead field — do not migrate.** The legacy Elementor `infographics` template defaults `hero_video_source` to `wistia` on every post, but `hero_library_video` and `hero_link_video` are empty on all 14 published posts. No video is linked anywhere in the corpus. Ignore this field entirely; it should not create an ACF field or any block in the rebuilt posts.

**The gated/ungated split is roughly even — unlike whitepapers.** 6 of 14 posts are gated (form), 8 are ungated (direct link). The rebuilt infographic template/pattern needs to handle both layouts. `enable_gated_content: false` is the common case here (opposite of whitepapers).

**`resource_link` is the primary delivery for ungated posts.** On ungated infographics, `resource_link` always points to the infographic asset itself (a HubSpot-hosted PDF or external infographic page). The `resource_link_open_in_new_tab` field is `true` on all 9 posts that have a link. On gated posts, `resource_link` only appears when `enable_additional_resource_link: true` (2 of 6 gated posts).

**1 post has HTML anchor tags inside checklist items** (`silent-auction-tips`). The `resource_checklist` serialized PHP contains full `<a href="..." target="_blank">` markup. The migration must extract these values verbatim (not HTML-escaped) and render them as `core/list` item markup with actual links. The plain-text extractor used for other CPTs will break them — use the raw serialized values here.

**2 posts use `<h4>` headings in `resource_details`** (`association-volunteer-engagement`, `build-a-strong-data-governance-policy`). Convert to `core/heading` blocks (level 4, or remap to level 3 per the rebuilt design). The prose extractor must handle `<h4>` just as the case study migration handled `<h2>`/`<h3>`.

**1 post has no solution category** (`giving-tuesday-statistics`). Valid edge case — do not assign a default category; leave the category panel empty.

**1 post has no excerpt** (`recurring-giving-stats`). Leave `post_excerpt` empty; do not fall back to a truncated `resource_details`.

**`_thumbnail_id` and `resource_hero_image` are sometimes the same attachment** (posts #2, #5, and several others). The migration must sideload once and assign to both fields without erroring on the duplicate source URL.

**HubSpot portalId is always `46621835`.** Only the `formId` changes per post. Extract with `preg_match('/formId["\s:]+([0-9a-f-]{36})/', $embed_code, $m)` if a clean ACF field is preferred over the raw embed.

**All `resource_checklist_type` values are `checkmarks`.** No variation — hardcode in the rebuilt block.
