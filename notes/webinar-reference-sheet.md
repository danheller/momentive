# Webinar Rebuild — Reference Sheet (6 coverage posts)

Decoded, Word-artifact-cleaned content for the six posts that together exercise every field and edge case across all 141 published webinars. Build these by hand in order; by #6 you'll have defined where every field lands, and the migration script's output format will be fully specified.

**Source exports:**
- `momentivesoftware.current.webinars.2026-06-30.xml` — 141 published `webinars` posts
- `momentivesoftware.current.assets.2026-06-30.xml` — 167 published `assets` posts (recording pages)

**Slug-matching:** 123 of 141 webinars have a matching `assets` post by slug; 18 do not. The `assets` post provides `video_embed_code` (always Wistia) for on-demand webinars. There is no `hubspot_form_code` on the assets side — the form lives entirely on the webinar post.

---

## Field → destination map (applies to all posts)

| Legacy field | Source | Rebuilt field | Notes |
|---|---|---|---|
| `webinar_type` | webinar post | `webinar_type` | Values: `upcoming` \| `on-demand` — direct match |
| `webinar_date` | webinar post | `webinar_date` | Unix timestamp → `Ymd` format (e.g. `20260714`) |
| `webinar_end_date` | webinar post | `webinar_end_date` | Unix timestamp → `Ymd`; only set on 1 post |
| `webinar_time_start` | webinar post | `webinar_time_start` | `HH:MM` (24h) → `g:i a` (12h), e.g. `13:00` → `1:00 pm` |
| `webinar_time_end` | webinar post | `webinar_time_end` | Same conversion |
| `webinar_timezone` | webinar post | `webinar_timezone` | Direct; always `ET` in practice |
| `hubspot_form_code` | webinar post | `form_upcoming` if type=upcoming | Single legacy field splits into two |
| `hubspot_form_code` | webinar post | `form_ondemand` if type=on-demand | |
| asset `video_embed_code` | matching assets post | `video_embed_code` | Always Wistia; only from assets CPT, never from webinar post itself |
| `resource_hero_image` (attachment ID) | webinar post | `hero_image` | Sideload from legacy; all 141 posts have a value |
| `webinar_presenter` (PHP serialized repeater) | webinar post | `presenters` (post_object → people CPT) | Match by name; create People post if not found |
| `resource_details` (HTML) | webinar post | Body — intro paragraphs | Word artifacts to strip |
| `resource_checklist_title` | webinar post | Body — checklist heading | |
| `resource_checklist` (PHP serialized repeater) | webinar post | Body — "you'll learn" checklist | |
| `resource_details_after_checklist` (HTML) | webinar post | Body — additional paragraphs after checklist | 11/141 posts |
| `cae_credits_text` | webinar post | Body — CAE credits note | 2/141 posts |
| `resource_quote` / `_source_name` / `_source_description` | webinar post | Body — quote block | 1/141 posts |
| `series_section_header` / `_description` | webinar post | Body — "About the Research" section | 4/141 posts; `series_section` repeater is always empty |
| post title | webinar post | Post title | |
| post excerpt | webinar post | Post excerpt | 131/141 have an excerpt |
| category terms | webinar post | Native category panel | Same Solution categories as products/case studies |

**Fields not migrated (rendered by blocks or auto-derived in rebuilt site):**
- `form_heading` — replaced by auto-derived heading ("Save your spot" / "Watch this webinar") in rebuilt site
- `resource_checklist_type` — always `checkmarks`; irrelevant in rebuilt block
- `series_order` — editorial metadata, not an ACF field in rebuilt site
- `series_section_layout` — layout is handled by the block
- All Rank Math / PPMA / elementor fields

---

## Presenter resolution

Legacy `webinar_presenter` is a PHP-serialized repeater. Each item has:
- `presenter_name` — always set; sometimes includes title/company appended (see #1 below)
- `presenter_description` — job title + org; empty when name contains the info
- `presenter_photo` — legacy attachment ID (needs sideload)
- `presenter_bio` — always empty in the corpus

**Migration approach:**
1. Parse name; strip trailing credentials from the name string if description is empty and name contains commas (e.g. `"Allyson Olaniel, Sr. Sales Solution Engineer, YourMembership"` → name=`Allyson Olaniel`, description=`Sr. Sales Solution Engineer, YourMembership`)
2. Look up People CPT by post title (normalized: lowercase, strip punctuation)
3. If found: use that post ID
4. If not found: create a new People post (`role: presenter`), sideload the photo

Presenters range from 0 to 9 per webinar (most have 1–2).

---

## Word artifact cleanup

`resource_details` and `resource_details_after_checklist` carry MS-Word/Online span cruft in roughly 60–70% of posts. Strip:
- Spans with Word class names: `NormalTextRun`, `TextRun`, `SCXW*`, `BCX*`, `EOP`, `SpellingError`, etc.
- Attributes: `data-contrast`, `data-ccp-props`, `data-ccp-charstyle`, `xml:lang`, `data-wpel-link`
- Empty `<p>` tags and `&nbsp;`-only paragraphs

The `resource_details_after_checklist` field was unaffected in the one sample checked above, but apply the same pass.

---

## #1 — Boardroom Ready: Building a Business Case for Tech Investment

> Standard on-demand, **no matching asset post** (form code → `form_ondemand`, no `video_embed_code`). Two presenters with clean name+description split. Covers: the "no recording yet" case, internal + external presenter, 3-item checklist.

- **slug:** `boardroom-ready-building-a-business-case-for-tech-investment`
- **live URL:** https://momentivesoftware.com/webinars/boardroom-ready-building-a-business-case-for-tech-investment/
- **webinar_type:** on-demand → `form_ondemand`
- **webinar_date:** `20250610` (Jun 10, 2025)
- **time:** 12:00 pm – 1:00 pm ET
- **categories:** Association Management
- **hero_image_id (legacy):** 1691
- **video_embed_code:** _(no assets post — field left empty)_

**Presenters:**
| name | description | legacy photo_id |
|---|---|---|
| Tirrah Switzer | Vice President of Product Marketing, Momentive Software | 1690 |
| Rebecca Achurch | Chief Executive Officer, Achurch Consulting, LLC | 1689 |

**form_heading (legacy, informational):** Watch now

**Checklist title:** In this session, you'll learn:

**Checklist:**
- How to present a clear, data-driven case for investment to your board
- Ways to align your technology needs with organizational goals
- What today's most forward-thinking associations are doing to future-proof their tech stack

**Excerpt:**
Discover how we empower association and nonprofit professionals with best practices, frameworks, and real-world examples to help you build leadership buy-in for future-ready tech decisions.

**resource_details (cleaned):**

```html
<p>Gaining board approval for new software can feel like an uphill battle in today's economic climate.</p>
<p>With 56% of association professionals focused on boosting staff productivity and 51% prioritizing tech upgrades and integrations in the year ahead, it's clear that investing in the right tools is key to long-term success.</p>
<p>Getting new technology approved is a powerful opportunity to strengthen your organization's foundation for the future. Watch now to discover how we empower association and nonprofit professionals with best practices, frameworks, and real-world examples to help you build leadership buy-in, ensure long-term stability, and make confident, future-ready tech decisions.</p>
```

---

## #2 — Grow Member Loyalty and Revenue with Smarter CE Delivery

> On-demand **with matching assets post** (Wistia embed). Two presenters where **name and title/company are concatenated in the name field** (description is empty) — the migration parser must detect and split these. Covers: video from assets CPT, presenter name-concat format, 5-item checklist.

- **slug:** `smarter-ce-delivery-yourmembership-path-lms`
- **live URL:** https://momentivesoftware.com/webinars/smarter-ce-delivery-yourmembership-path-lms/
- **webinar_type:** on-demand → `form_ondemand`
- **webinar_date:** `20250917` (Sep 17, 2025)
- **time:** 2:30 pm – 3:30 pm ET
- **categories:** Learning Management
- **hero_image_id (legacy):** 3036

**`video_embed_code` (from assets post — first 120 chars):**
```
<div class="wistia_responsive_padding" style="padding:56.25% 0 0 0;position:relative;"><div class="wistia_responsive_wra…
```
_(Pull full embed from the matching `assets` post with slug `smarter-ce-delivery-yourmembership-path-lms`)_

**Presenters (name-concat format — description field is empty, name contains title+org):**
| raw name (legacy) | parsed name | parsed description | legacy photo_id |
|---|---|---|---|
| Allyson Olaniel, Sr. Sales Solution Engineer, YourMembership | Allyson Olaniel | Sr. Sales Solution Engineer, YourMembership | 3100 |
| Sean Connelly, Director, Product Management, Path LMS | Sean Connelly | Director, Product Management, Path LMS | 3102 |

**Checklist title:** Discover how to:

**Checklist:**
- Deliver a seamless learning and membership experience with integrated tools
- Offer personalized access and pricing to grow non-dues revenue
- Improve member retention and acquisition by increasing the value of CE
- Save time with SSO, credit sync, order sync, and more
- Work with support teams that understand both your LMS and AMS

**Excerpt:**
Discover how YourMembership AMS & Path LMS help associations deliver smarter continuing education, boost member retention, and grow non-dues revenue.

**resource_details (cleaned):**

```html
<p>Delivering continuing education shouldn't mean juggling siloed systems or settling for a disconnected member experience. Watch now to learn how to streamline your CE delivery and drive meaningful growth—for your members and your organization.</p>
```

_(Note: this post's resource_details has heavy Word artifact contamination; the above is the cleaned result. The raw field starts with `<span class="TextRun SCXW82624099 BCX0" …>`.)_

---

## #3 — Beyond Membership Dues: Proven Strategies to Grow Revenue Year-Round

> **Upcoming** webinar — form code goes to `form_upcoming`. Also has a matching assets post (video from a prior run of this webinar). Covers: upcoming type, single presenter (clean format), 4-item checklist, `form_upcoming` field.

- **slug:** `small-staff-associations-non-dues-revenue`
- **live URL:** https://momentivesoftware.com/webinars/small-staff-associations-non-dues-revenue/
- **webinar_type:** upcoming → `form_upcoming`
- **webinar_date:** `20260714` (Jul 14, 2026)
- **time:** 1:00 pm – 1:30 pm ET
- **categories:** Association Management
- **hero_image_id (legacy):** 11221

**`video_embed_code` (from assets post — Wistia):**
_(Pull full embed from the matching `assets` post with slug `small-staff-associations-non-dues-revenue`)_

> **Note:** Upcoming webinars can still have a matching assets post (a previous run of the same webinar). Pull the `video_embed_code` when present; the `form_upcoming` is what's shown while the event is live.

**Presenters:**
| name | description | legacy photo_id |
|---|---|---|
| Allyson Olaniel | Membership Expert, YourMembership AMS | 3100 |

**Checklist title:** What you'll walk away with:

**Checklist:**
- A clear picture of the non-dues revenue opportunities you're most likely missing
- Practical ways to launch and manage new revenue streams directly from YourMembership
- Strategies your limited team can run with — and actually sustain year-round
- A framework for making new revenue a member value-add, not just a line item

**Excerpt:**
Join YourMembership on July 14th to learn simple strategies for small associations to grow & diversify non-dues revenue streams.

**resource_details (cleaned):**

```html
<p><b>Your Dues Aren't Enough. Here's What to Do About It.</b></p>
<p>Relying on membership dues alone can limit your association's growth. The most resilient organizations diversify—building revenue streams that deliver ongoing value to both their members and their bottom line.</p>
<p>In this free demo webinar, see exactly how YourMembership's all-in-one AMS helps small-staff associations create, launch, and manage new revenue opportunities—without adding complexity for your team.</p>
```

---

## #4 — Unlock a Decade of Association Trends

> On-demand + asset video + **CAE credits** + **0 presenters**. Covers: CAE credits text block, no-presenter layout, 7-item checklist.

- **slug:** `2025-association-trends-study-reveal-webinar`
- **live URL:** https://momentivesoftware.com/webinars/2025-association-trends-study-reveal-webinar/
- **webinar_type:** on-demand → `form_ondemand`
- **webinar_date:** `20250925` (Sep 25, 2025)
- **time:** 12:00 pm – 1:00 pm ET
- **categories:** Association Management
- **hero_image_id (legacy):** 3548

**`video_embed_code` (from assets post — Wistia script embed):**
```
<script src="https://fast.wistia.com/player.js" async></script><script src="https://fast.wistia.com/embed/hj1ccc6bfu.js"…
```
_(Pull full embed from the matching `assets` post with slug `2025-association-trends-study-reveal-webinar`)_

**Presenters:** _(none — 0 presenters; omit the presenter block entirely)_

**CAE credits text:** `Live attendees will earn 1 CAE credit`

**Checklist title:** What You Can Expect in This Webinar

**Checklist:**
- Loyalty metrics and member retention drivers in today's challenging environment
- Strategic insights on optimizing event revenue through regional and hybrid approaches
- Data-driven recommendations for expanding digital professional development offerings
- Technology adoption strategies that boost member satisfaction and loyalty
- Practical AI integration roadmap based on successful association implementations
- Member engagement tactics that resonate with younger demographics and remote preferences
- Q&A session with industry experts and survey researchers

**Excerpt:**
Join Momentive for the exclusive webinar, Unlock a Decade of Associations Trends on Thursday, September 25th at 12 PM EST, where we'll unveil findings from the 10th Annual Associations Survey Report.

**resource_details (cleaned):**

```html
<p>Exclusive Insights from the 10th Annual Associations Survey Report</p>
<p>Join us for an exclusive webinar revealing groundbreaking findings from our comprehensive survey of association professionals and members. Discover how loyalty metrics are soaring as members turn to their organizations for guidance in uncertain times and learn why successful events have become the #1 priority for association leaders.</p>
<p>This year's report uncovers critical shifts in member expectations, from the growing demand for regional events to the increase in professional development requirements. We'll explore how technology adoption is driving member loyalty and reveal surprising insights about AI integration in the association space.</p>
```

---

## #5 — Transform Your Association's Learning Programs into a Revenue-Generating Hub

> On-demand + asset video + **quote block** + 0 presenters. Covers: the one post with a `resource_quote` box; also a short 15-minute webinar (unusual time).

- **slug:** `create-a-non-dues-revenue-hub`
- **live URL:** https://momentivesoftware.com/webinars/create-a-non-dues-revenue-hub/
- **webinar_type:** on-demand → `form_ondemand`
- **webinar_date:** `20250723` (Jul 23, 2025)
- **time:** 1:00 pm – 1:15 pm ET
- **categories:** Learning Management
- **hero_image_id (legacy):** 2098

**`video_embed_code` (from assets post — Wistia script embed):**
```
<script src="https://fast.wistia.com/player.js" async></script><script src="https://fast.wistia.com/embed/fzc4x3yzaz.js"…
```
_(Pull full embed from the matching `assets` post with slug `create-a-non-dues-revenue-hub`)_

**Presenters:** _(none)_

**Quote:**
> "Path LMS has proven to be a critical tool to create new revenue, keep members informed and engaged, and streamline the delivery of effective education."
> — **Maggie Bayerl**, Education Coordinator, American Therapeutic Recreation Association (ATRA)

**Checklist title:** Key takeaways from the webinar:

**Checklist:**
- How to create non-dues revenue through educational content.
- The power of eCommerce sync and flexible pricing models.
- Expanding your reach with external access and team-based purchasing options.
- How to leverage analytics to optimize revenue and educational impact.

**Excerpt:**
Join our webinar to learn how Path LMS can help you create non-dues revenue streams with seamless eCommerce tools, powerful analytics, and strategic content development services.

**resource_details (cleaned):**

```html
<p>Associations are facing shrinking budgets and increasing pressure to diversify revenue streams. 73% of organizations say increasing non-dues revenue is critical. Start creating an additional revenue stream from educational content, breaking dependence on membership fees and supporting sustainable, long-term growth. Path LMS provides the technology and tools to turn your education programs into sustainable revenue drivers.</p>
<p>Join Path LMS by Momentive Software for a 15 minute webinar to see how you can easily deliver high-quality, on-demand learning while generating non-dues revenue.</p>
```

---

## #6 — The Shift: How Associations and Nonprofits Are Adapting to What's Next

> **Upcoming + multi-day + no asset + 9 presenters + 0 checklist + all 10 categories.** Covers: `webinar_end_date`, maximum-presenter count, all-category case, no-checklist layout, no-asset upcoming.

- **slug:** `associations-nonprofits-adaptive-strategies`
- **live URL:** https://momentivesoftware.com/webinars/associations-nonprofits-adaptive-strategies/
- **webinar_type:** upcoming → `form_upcoming`
- **webinar_date:** `20260721` (Jul 21, 2026)
- **webinar_end_date:** `20260723` (Jul 23, 2026)
- **time:** _(not set — no start/end times on this post)_
- **categories:** Accounting, Association Management, Career Centers, Certification Management, Data Analytics, Donor Management, Event Management, Fundraising, Learning Management, Volunteer Management
- **hero_image_id (legacy):** 11189
- **video_embed_code:** _(no assets post — field left empty)_

**Presenters (9 — mix of internal Momentive staff and external):**
| name | description | legacy photo_id |
|---|---|---|
| Mary Connor, CAE, AAiP | Chief Strategy Officer, Stringfellow Management Group | _(none)_ |
| Dustin Radtke | Chief AI Officer, Momentive Software | _(none)_ |
| Rich Vallaster, CEM, QAS, AAiP | Sr. Director of Industry Strategy, Momentive Software | _(none)_ |
| Patrick Gotham | Senior Vice President, CCS Fundraising | _(none)_ |
| Tirrah Switzer, CAE | Vice President, Product Marketing, Momentive Software | _(none)_ |
| Chris Capistran | Vice President, Industry Strategy, Momentive Software | _(none)_ |
| Chelsey Wilson | Senior Product Marketing Manager, Momentive Software | _(none)_ |
| Michelle Baughman | Manager, Exhibits and Sponsorship, America Society of Landscape Architects | _(none)_ |
| Daniel Martin | Managing Director, Development, American Society of Landscape Architects | _(none)_ |

> **Note:** This post's presenters have no `presenter_photo` attachment IDs. The migration should handle missing photos gracefully (create People post without a featured image).

**Checklist:** _(none — `resource_checklist` is empty; omit the "you'll learn" section)_

**Excerpt:**
This four-part series draws on original research from three separate studies of association leaders, nonprofit executives, and association members. Each session tackles similar pressure points across different themes: generational change, AI adoption, revenue sustainability, and donor trust.

**resource_details (cleaned):**

```html
<h2>Strategies for Organizations Navigating Generational and Technological Change</h2>
<p>Your membership pipeline looks different than it did three years ago. So does your donor base, your staff's relationship with technology, and the revenue model you've relied on for decades. You already know something fundamental is changing. The question is what to do about it.</p>
<p>The organizations moving forward have realized technology has already far surpassed where anyone predicted, and that industry leaders must adapt to change without getting left behind. These leaders are having honest conversations with their organizations about the gap between where they are and where they need to be—and closing it with intention.</p>
<p>This four-part series draws on original research from three separate studies of association leaders, nonprofit executives, and association members. Each session tackles similar pressure points across different themes: generational change, AI adoption, revenue sustainability, and donor trust. Together, they give you a grounded picture of what's shifting and a practical path forward.</p>
```

---

## Notes discovered during analysis

**Date format conversion required.** Legacy `webinar_date` and `webinar_end_date` are stored as Unix timestamps (e.g. `1749513600`). The rebuilt ACF date_picker uses `Ymd` format (e.g. `20250610`). Convert with `date('Ymd', $timestamp)`. Only 1 post has a non-empty `webinar_end_date`.

**Time format conversion required.** Legacy `webinar_time_start`/`webinar_time_end` are `HH:MM` 24-hour strings (e.g. `13:00`). The rebuilt ACF time_picker stores `g:i a` 12-hour (e.g. `1:00 pm`). 5 of the 141 posts have no time set at all — handle gracefully.

**Two Wistia embed formats in the wild.** Older assets posts use the `jsonp` + `E-v1.js` embed style; newer ones use the `player.js` + `embed/{id}.js` style. Both are valid; copy verbatim.

**`series_section` repeater is always empty.** Four posts have a `series_section_header` and `series_section_description` (rich HTML, Word-artifact-cleaned) but the `series_section` repeater itself contains no items. The "About the Research" block is just a header + description text, not a list of related posts. These four posts also have `series_order` (1–4) and `series_section_layout` (`two-columns`) but these are rendered layout hints, not block content. Decide whether to include a static "About the Research" group block in the migration or just include the header/description text in `resource_details` body content.

**`resource_details_after_checklist` present on 11 posts.** This is a second prose block that appears below the "you'll learn" checklist. It's usually 1–2 short paragraphs. Include it as additional paragraph blocks after the checklist block in the migrated body.

**CAE credits on 2 posts.** `cae_credits_text` is a plain-text string (e.g. `"Live attendees will earn 1 CAE credit"`). The rebuilt webinar pattern presumably has a block or group for this; confirm placement before writing the migration.

**Only 1 post has a quote box.** `resource_enable_quote_box == 'true'` + `resource_quote` set on `create-a-non-dues-revenue-hub` only. Three fields: `resource_quote`, `resource_quote_source_name`, `resource_quote_source_description`. Map to the appropriate quote block.

**18 on-demand webinars have no matching assets post.** These get `form_ondemand` set but `video_embed_code` left empty (no recording to pull). The `webinar_status()` helper will show the on-demand form correctly even without a video.

**3 posts have presenter name/company concatenated in the name field.** When `presenter_description` is empty and `presenter_name` contains a comma, treat everything after the first comma as the description. Example: `"Allyson Olaniel, Sr. Sales Solution Engineer, YourMembership"` → name=`Allyson Olaniel`, description=`Sr. Sales Solution Engineer, YourMembership`.

**`form_heading` is deprecated in the rebuilt site.** The rebuilt site auto-derives the form heading from status. Do not migrate this field; it's informational only in this sheet.

**Presenter photo attachment IDs are from the legacy site** and will need to be sideloaded. Many newer posts (including all 9 presenters in #6) have no photo. The People CPT allows a profile without a featured image; don't block post creation on a missing photo.

**The `assets` CPT is a recording-hub page, not a 1:1 webinar mirror.** Most fields on assets posts (`page_heading`, `toolkit_assets_*`, etc.) are not migrated — the rebuilt site does not have a separate recordings hub page per webinar. Only `video_embed_code` is pulled from the matched assets post.
