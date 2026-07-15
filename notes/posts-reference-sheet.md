# Blog Post Migration — Reference Sheet

Coverage posts chosen to exercise every SC template type, both enabled and disabled states, and all structural variants. Build the blocks for each post in order; by post #5 every template type will be defined and the migration script output format fully specified.

**Source export:** `momentivesoftware.posts.current.2026-07-06.xml` — 1,441 posts (1,087 have no SC shortcodes; 354 do)

---

## SC Template → Block mapping

All 12 Elementor template IDs used in post content come from the legacy `[SCM]` (Shortcode Module) system. Each shortcode renders per-post meta fields through an Elementor layout shell. In the rebuilt theme, each becomes inline native block markup.

### Template ID → legacy field group

| Template ID | Elementor title | SC field prefix | # posts |
|---|---|---|---|
| 1458 | [SCM] CTA Section | `sc_cta_-_` | 285 |
| 1526 | [SCM] CTA Section 2 | `sc_cta_-_` + `_2` suffix | 52 |
| 1464 | [SCM] CTA with Image | `sc_cta_with_image_-_` | 75 |
| 1527 | [SCM] CTA with Image 2 | `sc_cta_with_image_-_` + `_2` suffix | 14 |
| 1467 | [SCM] Tip 1 Section | `sc_tip_1*` | 125 |
| 1468 | [SCM] Tip 2 Section | `sc_tip_2*` | 75 |
| 1471 | [SCM] Tip 3 Section | `sc_tip_3*` | 41 |
| 1473 | [SCM] Tip 4 Section | `sc_tip_4*` | 20 |
| 1474 | [SCM] Tip 5 Section | `sc_tip_5*` | 13 |
| 1475 | [SCM] Tip 6 Section | `sc_tip_6*` | 4 |
| 8984 | [SCM] Checklist 1 | `enable_checklist_1`, `checklist_1` | 2 |
| 1974 | [Section] Tabbed Content | _(no post meta — global Elementor template)_ | 1 |

### Field → block destination

**CTA Section (1458 / 1526)**

| Legacy meta key | Value type | Becomes |
|---|---|---|
| `sc_cta_-_enable_cta_section` / `_2` | `true`/`false` | If false: remove shortcode entirely |
| `sc_cta_-_header` / `_2` | text (often empty) | `core/heading` level 3 — omit if empty |
| `sc_cta_-_description` / `_2` | text (often empty) | `core/paragraph` — omit if empty |
| `sc_cta_-_button_text` / `_2` | text | `core/button` label |
| `sc_cta_-_button_url` / `_2` | URL | `core/button` href |
| `sc_cta_-_button_open_in_new_tab` / `_2` | `true`/`false` | `target="_blank"` on button |

Block shell: `core/group` with className `highlight-cta` + `is-style-bg-light`.

**CTA with Image (1464 / 1527)**

| Legacy meta key | Value type | Becomes |
|---|---|---|
| `sc_cta_with_image_-_enable_cta_with_image_section` / `_2` | `true`/`false` | If false: remove shortcode entirely |
| `sc_cta_with_image_-_image` / `_2` | legacy attachment ID | Sideload; `core/image` in left column |
| `sc_cta_with_image_-_header` / `_2` | text (often empty) | `core/heading` level 3 — omit if empty |
| `sc_cta_with_image_-_description` / `_2` | text | `core/paragraph` |
| `sc_cta_with_image_-_button_text` / `_2` | text | `core/button` label |
| `sc_cta_with_image_-_button_url` / `_2` | URL | `core/button` href |
| `sc_cta_with_image_-_button_open_in_new_tab` / `_2` | `true`/`false` | `target="_blank"` |

Block shell: `core/group` with className `highlight-cta highlight-cta--with-image`, containing `core/columns`.

**Tip Section (1467–1475)**

Two variants based on whether `sc_tip_N_icon` and `sc_tip_N_title` are present:

| Legacy meta key | Value type | Becomes |
|---|---|---|
| `sc_tip_N` (the enable key) | `true`/`false` | If false: remove shortcode |
| `sc_tip_N_icon` | icon slug with `box-` prefix (often empty) | Strip `box-` → icon — omit if empty |
| `sc_tip_N_title` | text (often empty) | `core/heading` level 3 — omit if empty |
| `sc_tip_N_description` | raw HTML | Strip block-comment noise; wrap inline in `core/paragraph` |

Block shell: `core/group` with className `highlight-tip` + `is-style-bg-light`.

**Checklist (8984)**

| Legacy meta key | Value type | Becomes |
|---|---|---|
| `enable_checklist_1` | `true`/`false` | If false: remove shortcode |
| `checklist_1` | PHP serialized `a:N:{..."label":"..."}` | Parse items → `core/list` with `is-style-circle-checks` |

**Tabbed Content (1974)**

Global Elementor template — no post meta. Appears in 1 post only (`fundraising-ideas`). Remove the shortcode and flag that post for manual attention.

---

## Additional field groups (not shortcode-driven)

These appear in post meta but are injected by the Elementor single post template, not via shortcodes. Decide separately whether they become ACF fields or blocks in the FSE `single.html` template.

| Field prefix | # posts | Likely purpose |
|---|---|---|
| `resource_cta_*` | 368 | Bottom-of-post branded CTA (auto-injected by template) |
| `custom_sidebar_cta_*` | 343 | Sidebar CTA block |
| `blog_pillar_*` | 198 | Pillar page sidebar CTA |
| `post_hero_button_cta_*` | 90 | Hero CTA button |

---

## Coverage posts

---

## #1 — **CTA only (1458), header set, description empty.** Simplest case. Also shows that CTA+Image fields are always present even when 1464 shortcode is absent from content.

- **slug:** `large-scale-events-vs-small`
- **live URL:** https://momentivesoftware.com/large-scale-events-vs-small/
- **status:** publish  |  **date:** 2023-06-24
- **categories:** Event Management
- **excerpt:** When you’re planning an event, regardless of its size, having a plan and timeline is essential.
- **Shortcodes in content (in order):** CTA Section (1458)

### SC fields

**CTA Section (→ 1458) (→ 1458)**

| Field | Value |
|---|---|
| enable | `true` |
| header | 'Feel free to contact us today and request a demo to further explore our powerful suite of event planning tools.' |
| description | '' |
| button_text | 'Request demo' |
| button_url | `https://www.attendeeinteractive.com/demo-request/` |
| open_in_new_tab | `true` |

**CTA Section 2 (→ 1526) (→ 1526)**

| Field | Value |
|---|---|
| enable | `false` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA with Image (→ 1464) (→ 1464)**

| Field | Value |
|---|---|
| enable | `true` |
| image (legacy ID) | `1784` |
| header | 'In a world of shrinking grants and rising expectations, finance leaders are charting a bold path forward.' |
| description | 'Read the full 2025 Nonprofit Trends Report to explore how top organizations are adapting and how yours can, too.' |
| button_text | 'Read the report' |
| button_url | `https://momentivesoftware.com/research-study/2025-nonprofit-trends-study/` |
| open_in_new_tab | `false` |

**CTA with Image 2 (→ 1527) (→ 1527)**

| Field | Value |
|---|---|
| enable | `false` |
| image (legacy ID) | `` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

---

## #2 — **All 6 tip variants + CTA (1458).** Every tip has icon + title. Canonical tip-with-icon example.

- **slug:** `automate-association-tasks`
- **live URL:** https://momentivesoftware.com/automate-association-tasks/
- **status:** publish  |  **date:** 2020-03-17
- **categories:** Association Management
- **excerpt:** Discover how to automate association tasks to streamline workflows, reduce manual effort, and focus on initiatives that enhance member engagement and organizati
- **Shortcodes in content (in order):** Tip 1 (1467), Tip 2 (1468), Tip 3 (1471), Tip 4 (1473), Tip 5 (1474), Tip 6 (1475), CTA Section (1458)

### SC fields

**CTA Section (→ 1458) (→ 1458)**

| Field | Value |
|---|---|
| enable | `true` |
| header | '' |
| description | 'Discover how Nimble AMS can help your organization bring AI and process automation to life for your association.' |
| button_text | 'Schedule a demo.' |
| button_url | `https://momentivesoftware.com/request-a-demo/` |
| open_in_new_tab | `true` |

**CTA Section 2 (→ 1526) (→ 1526)**

| Field | Value |
|---|---|
| enable | `false` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA with Image (→ 1464) (→ 1464)**

| Field | Value |
|---|---|
| enable | `false` |
| image (legacy ID) | `` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA with Image 2 (→ 1527) (→ 1527)**

| Field | Value |
|---|---|
| enable | `false` |
| image (legacy ID) | `` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**Tip 1 (→ 1467)**

| Field | Value |
|---|---|
| enable (`sc_tip_1`) | `true` |
| icon | `box-bx-bulb` |
| title | 'TECH TIP:' |

description (HTML, cleaned):

```html
<p><a href="https://www.nimbleams.com/nimble-create/" target="_blank" rel="noreferrer noopener">Nimble Create</a> is a visual template builder included with Nimble AMS, allowing you to easily build templates (no coding required) for branded, personalized, and information-rich content. Use it to automatically pull member data and other details directly from your Nimble AMS system into your email content, providing a more personalized member experience.</p>
```

**Tip 2 (→ 1468)**

| Field | Value |
|---|---|
| enable (`sc_tip_2`) | `true` |
| icon | `box-bx-bulb` |
| title | 'TECH TIP:' |

description (HTML, cleaned):

```html
<p>Nimble AMS allows you to set up this option, with corresponding automated emails that notify members when their membership has been renewed.</p>
```

**Tip 3 (→ 1471)**

| Field | Value |
|---|---|
| enable (`sc_tip_3`) | `true` |
| icon | `box-bx-bulb` |
| title | 'TECH TIP:' |

description (HTML, cleaned):

```html
<p>Nimble AMS uses <a href="https://www.nimbleams.com/blog/einstein-artificial-intelligence-for-associations/" target="_blank" rel="noreferrer noopener">Salesforce Einstein artificial intelligence (AI) technology</a>, enabling point-and-click AI. Using this technology, you can easily make predictions and provide automated, intelligent guidance to members.</p>
```

**Tip 4 (→ 1473)**

| Field | Value |
|---|---|
| enable (`sc_tip_4`) | `true` |
| icon | `box-bx-bulb` |
| title | 'TECH TIP:' |

description (HTML, cleaned):

```html
<p>Using Nimble AMS, you can easily set up AI-based chatbots with no coding required.</p>
```

**Tip 5 (→ 1474)**

| Field | Value |
|---|---|
| enable (`sc_tip_5`) | `true` |
| icon | `box-bx-bulb` |
| title | 'TECH TIP:' |

description (HTML, cleaned):

```html
<p>Nimble AMS leverages <a href="https://trailhead.salesforce.com/en/content/learn/modules/business_process_automation/process_builder" target="_blank" rel="noreferrer noopener">Salesforce Process Builder</a> to allow your staff to save valuable time by automating complex business processes. Without code, your staff can specify process criteria to automate tasks such as automatically updating or creating new records, emails, and tasks, or submitting approval requests – all in a few simple steps.</p>
```

**Tip 6 (→ 1475)**

| Field | Value |
|---|---|
| enable (`sc_tip_6`) | `true` |
| icon | `box-bx-bulb` |
| title | 'TECH TIP:' |

description (HTML, cleaned):

```html
<p>Nimble AMS supports <a href="https://www.nimbleams.com/blog/nimble-ams-complex-tax-processing/" target="_blank" rel="noreferrer noopener">automated</a>, complex tax processing, enabling associations to manage taxes both nationally and internationally easily.</p>
```

---

## #3 — **CTA + Tips + both CTA+Image variants.** Tests 1458 + 1467 + 1468 + 1464 + 1527 in one post. Image IDs need sideloading.

- **slug:** `association-trends`
- **live URL:** https://momentivesoftware.com/association-trends/
- **status:** publish  |  **date:** 2025-10-15
- **categories:** Association Management, Career Centers, Event Management
- **excerpt:** Tips on how your organization can plan and strategize to keep driving your mission forward in this period of uncertainty.
- **Shortcodes in content (in order):** CTA Section (1458), Tip 1 (1467), CTA with Image (1464), CTA with Image 2 (1527), Tip 2 (1468)

### SC fields

**CTA Section (→ 1458) (→ 1458)**

| Field | Value |
|---|---|
| enable | `true` |
| header | '' |
| description | 'Ready to strengthen your strategy for the year ahead? Explore how Association Management Software helps organizations streamline operations, engage members, and build long-term sustainability.' |
| button_text | 'Explore now' |
| button_url | `/solutions/association-management-software/` |
| open_in_new_tab | `false` |

**CTA Section 2 (→ 1526) (→ 1526)**

| Field | Value |
|---|---|
| enable | `false` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA with Image (→ 1464) (→ 1464)**

| Field | Value |
|---|---|
| enable | `true` |
| image (legacy ID) | `3780` |
| header | '' |
| description | 'Discover how the American College of Sports Medicine modernized their educational offerings, launched new revenue-generating courses, and created a strategic roadmap for their 50,000 learners.' |
| button_text | 'Read more' |
| button_url | `/case-studies/lms-american-college-of-sports-medicine/` |
| open_in_new_tab | `false` |

**CTA with Image 2 (→ 1527) (→ 1527)**

| Field | Value |
|---|---|
| enable | `true` |
| image (legacy ID) | `7179` |
| header | '' |
| description | 'Learn how St. Anthony Catholic School transformed donor engagement and realized revitalized success.' |
| button_text | 'Read the Case Study' |
| button_url | `https://www.givesmart.com/resource/st-anthony-catholic-schools-journey-back-to-better-fundraising/` |
| open_in_new_tab | `false` |

**Tip 1 (→ 1467)**

| Field | Value |
|---|---|
| enable (`sc_tip_1`) | `true` |
| icon | `` |
| title | '' |

description (HTML, cleaned):

```html
<p><a href="https://momentivesoftware.com/solutions/event-management-software/">Event management software</a> eases the administrative burden for your staff, while putting on a wow-worthy event for members.</p>
```

**Tip 2 (→ 1468)**

| Field | Value |
|---|---|
| enable (`sc_tip_2`) | `true` |
| icon | `` |
| title | '' |

description (HTML, cleaned):

```html
<p>If you’re looking for other things to add to or adapt for your fundraising calendar, read our <a href="https://www.givesmart.com/resource/nonprofit-revenue-sources-workbook/">30+ Revenue Sources Workbook</a> today to further unlock growth potential.</p>
```

---

## #4 — **Checklist (8984) + Tips without icon or title.** Bare HTML tip variant. CTA and CTA+Image all disabled.

- **slug:** `charity-sports-events`
- **live URL:** https://momentivesoftware.com/charity-sports-events/
- **status:** publish  |  **date:** 2026-03-03
- **categories:** Fundraising
- **excerpt:** Plan a successful charity sports event from start to finish. Explore event ideas, fundraising methods, sponsorship tips, and a day-of checklist to maximize dona
- **Shortcodes in content (in order):** Tip 1 (1467), Checklist (8984), Tip 2 (1468)

### SC fields

**CTA Section (→ 1458) (→ 1458)**

| Field | Value |
|---|---|
| enable | `false` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA Section 2 (→ 1526) (→ 1526)**

| Field | Value |
|---|---|
| enable | `false` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA with Image (→ 1464) (→ 1464)**

| Field | Value |
|---|---|
| enable | `false` |
| image (legacy ID) | `` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA with Image 2 (→ 1527) (→ 1527)**

| Field | Value |
|---|---|
| enable | `false` |
| image (legacy ID) | `` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**Tip 1 (→ 1467)**

| Field | Value |
|---|---|
| enable (`sc_tip_1`) | `true` |
| icon | `` |
| title | '' |

description (HTML, cleaned):

```html
<p>Shop around when you’re sourcing equipment. Your local sports store may be happy to donate some or provide it at a reduced price. Buying and storing your equipment may make sense if you’ll need it again for future events. Create a detailed checklist and ensure you have everything you need well in advance.</p>
```

**Tip 2 (→ 1468)**

| Field | Value |
|---|---|
| enable (`sc_tip_2`) | `true` |
| icon | `` |
| title | '' |

description (HTML, cleaned):

```html
<p>Your budget is another important consideration, as some events are more expensive to put together.</p>
```

**Checklist (→ 8984)**

| Field | Value |
|---|---|
| enable | `true` |

Items:

- Set up, mark, and clean the field, court, or route.
- Install directional signs, sponsor banners, and safety signs.
- Test PA systems, microphones, and music.
- Set up registration tables, laptops, participant lists, bibs, and waiver forms.
- Set up water and first aid stations.
- Brief volunteers, assign roles (parking, registration, scorekeeping), and assign a central point of contact.
- Manage merchandise, raffles, and concessions, and ensure payment methods (cash/card) work.
- Thank volunteers, participants, and sponsors on-site. Ensure any promised signage or swag is clearly visible.

---

## #5 — **All shortcodes disabled, all fields empty.** Migration removes all shortcodes and emits nothing.

- **slug:** `career-insights`
- **live URL:** https://momentivesoftware.com/career-insights/
- **status:** publish  |  **date:** 2024-11-11
- **categories:** Career Centers
- **excerpt:** Learn how to give your association’s members the valuable career insights they need to make more informed career decisions.
- **Shortcodes in content (in order):** CTA with Image (1464), CTA Section (1458)

### SC fields

**CTA Section (→ 1458) (→ 1458)**

| Field | Value |
|---|---|
| enable | `false` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA Section 2 (→ 1526) (→ 1526)**

| Field | Value |
|---|---|
| enable | `false` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA with Image (→ 1464) (→ 1464)**

| Field | Value |
|---|---|
| enable | `false` |
| image (legacy ID) | `` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA with Image 2 (→ 1527) (→ 1527)**

| Field | Value |
|---|---|
| enable | `false` |
| image (legacy ID) | `` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

---

## #6 — **Tips (no icon/title) + CTA 2 (1526) + CTA + Tabbed Content (1974).** Only post with 1974 — remove it. CTA 2 enabled.

- **slug:** `fundraising-ideas`
- **live URL:** https://momentivesoftware.com/fundraising-ideas/
- **status:** publish  |  **date:** 2026-02-17
- **categories:** Fundraising
- **excerpt:** Discover proven fundraising ideas that boost nonprofit revenue—from galas and community events to virtual, corporate, and recurring campaigns.
- **Shortcodes in content (in order):** Tip 1 (1467), Tip 2 (1468), Tip 6 (1475), Tip 3 (1471), CTA Section 2 (1526), CTA Section (1458), Tabbed Content (1974)

### SC fields

**CTA Section (→ 1458) (→ 1458)**

| Field | Value |
|---|---|
| enable | `true` |
| header | '' |
| description | '30+ Revenue Generating Ideas to Fuel Your Mission. Open our step-by-step guide packed with checklists to help you identify current sources, uncover new growth opportunities, set strategic goals, avoid common pitfalls, and evaluate your progress.   Unlock sustainable, year-round revenue and drive your nonprofit’s goals.' |
| button_text | 'Download our free guide today!' |
| button_url | `https://www.givesmart.com/resource/nonprofit-revenue-sources-workbook/` |
| open_in_new_tab | `true` |

**CTA Section 2 (→ 1526) (→ 1526)**

| Field | Value |
|---|---|
| enable | `true` |
| header | '' |
| description | 'As you look to maximize your ROI and grow your events, check out GiveSmart’s Fundraising Event Success Hub with resources, webinars, guides, and more to help you efficiently amplify your impact at your signature fundraisers.' |
| button_text | 'Find out more' |
| button_url | `https://www.givesmart.com/fundraising-event-success-hub/?utm_content=284598079&amp;utm_medium=social&amp;utm_source=linkedin&amp;hss_channel=lcp-3006979` |
| open_in_new_tab | `true` |

**CTA with Image (→ 1464) (→ 1464)**

| Field | Value |
|---|---|
| enable | `false` |
| image (legacy ID) | `` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**CTA with Image 2 (→ 1527) (→ 1527)**

| Field | Value |
|---|---|
| enable | `false` |
| image (legacy ID) | `` |
| header | '' |
| description | '' |
| button_text | '' |
| button_url | `` |
| open_in_new_tab | `false` |

**Tip 1 (→ 1467)**

| Field | Value |
|---|---|
| enable (`sc_tip_1`) | `true` |
| icon | `` |
| title | '' |

description (HTML, cleaned):

```html
<p>Looking to partner with an auction expert? We’ve processed <strong>17+ million</strong> bids. <a href="https://www.givesmart.com/solutions/mobile-bidding-silent-auction/" target="_blank" rel="noreferrer noopener"><strong>Connect with GiveSmart today!</strong></a></p>
```

**Tip 2 (→ 1468)**

| Field | Value |
|---|---|
| enable (`sc_tip_2`) | `true` |
| icon | `` |
| title | '' |

description (HTML, cleaned):

```html
<p><strong>Golf Tournament Fundraiser Guide. </strong>Encourage supporters to tee up for your cause at your golf outing! Donors can live their PGA dreams on the green while raising vital funds for your meaningful mission. Check out our guide to see how you can drive friendly competitions and fun contests to raise funds at your hole-in-one charity golf tournament. <a href="https://www.givesmart.com/blog/golf-tournament-fundraising-guide/" target="_blank" rel="noreferrer noopener"><strong>Open the hole-in-one guide</strong></a><strong>!</strong></p>
```

**Tip 3 (→ 1471)**

| Field | Value |
|---|---|
| enable (`sc_tip_3`) | `true` |
| icon | `` |
| title | '' |

description (HTML, cleaned):

```html
<p>At GiveSmart, we’re grateful to power thousands of events annually. Peruse our <a href="https://www.givesmart.com/solutions/campaign-event-management/" target="_blank" rel="noreferrer noopener">event fundraising features</a> to learn how we can help you achieve your event fundraising goals.</p>
```

**Tip 5 (→ 1474)**

| Field | Value |
|---|---|
| enable (`sc_tip_5`) | `true` |
| icon | `` |
| title | '' |

description (HTML, cleaned):

```html
<p>Finding a corporation willing to match donations is a surefire way to hit your fundraising goals.</p>
```

**Tip 6 (→ 1475)**

| Field | Value |
|---|---|
| enable (`sc_tip_6`) | `true` |
| icon | `` |
| title | '' |

description (HTML, cleaned):

```html
<p>Anyone can be a model in your fashion show fundraising event. Consider including your constituents on the runway!</p>
```

---

## Migration decisions

- **Disabled shortcodes:** replace with empty string (remove from content). All meta fields for disabled instances are blank.
- **`highlight-cta` / `highlight-tip` classes:** add to SCSS and register as `core/group` block styles, or use as freeform classNames. `is-style-bg-light` already provides the background; these classes supply layout overrides.
- **Tip icons:** strip `box-` prefix from `sc_tip_N_icon` to get the sprite slug (e.g. `box-bx-bulb` → `bx-bulb`). Same pattern as case study feature icons.
- **CTA+Image image IDs:** legacy attachment IDs. Sideload using the legacy uploads base URL. If sideload fails, render text-only (omit the image column).
- **Tabbed Content (1974):** remove shortcode from `fundraising-ideas`; flag that post for manual attention.
- **`resource_cta_*` / `custom_sidebar_cta_*` fields:** not shortcode-driven. Decide separately — they may become ACF fields consumed by the FSE `single.html` template (sidebar or post-footer zone).
- **Empty header:** skip `core/heading` block entirely when `sc_cta_-_header` is empty. Don't emit a blank heading.
- **Tip description HTML:** strip all `<!-- wp:... -->` block comments before wrapping in `core/paragraph`. The block-comment noise is an Elementor/WP conflict artifact from some posts.