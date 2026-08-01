# Pages Migration — Reference Sheet

Source: `migrations/exports/momentivesoftware.pages.current.2026-07-27.xml` (refreshed export; supersedes the original `momentivesoftware.current.pages.2026-07-22.xml` this sheet was first built from — same 67 `page` items: 57 published, 10 draft, same post IDs/slugs throughout. The export's other 380 items are attachments, out of scope here).

**Status: Clusters A (Legal/policy) and B (MIP legacy support/utility) are done.** Everything else in this sheet is still open, now with post IDs, slugs, and live legacy URLs filled in per cluster so each one can be pulled up directly on the legacy site.

**One-off styling note:** several of these legacy Elementor pages carry a genuinely bespoke visual treatment (flip-boxes, a one-off gradient, a hover effect used nowhere else) that only makes sense on that one page. Rather than folding those into `momentive.scss` — where they'd ship to every page and post on the site for the benefit of exactly one page — each gets its own SCSS file under `assets/sass/pages/{slug}.scss`, compiled to `assets/css/pages/{slug}.css` and auto-enqueued only on that page by `inc/page-styles.php` (no registration step). See "Per-page one-off styles" in CLAUDE.md's SCSS compilation section for the convention. If the same treatment turns up on two or more pages, promote it out of `pages/` into a real conditionally-loaded stylesheet instead of letting the per-page folder keep growing.

**Headline finding: `page` is not one CPT with a repeatable shape — it's ~67 individual Elementor builds.** Unlike Posts, Case Studies, Webinars, or Solutions (each ~90–450 postmeta keys, but the *same* keys on every post), Pages has no single dominant schema. Each page's real content lives almost entirely in one giant `_elementor_data` JSON blob (raw widget tree) or, for a handful, in native `content:encoded` blocks. **This means a single `migrate-pages.php` WP-CLI script in the style of the other migrations isn't realistic** — there's no consistent field → block mapping to automate across all 67. This will be a page-by-page hand-rebuild.

That said, three things *are* patterned and worth knowing before you start:

1. A library of ~10 reusable "section" meta-box field groups (CTA, Statistics, Features, Approach, Testimonials, FAQs, Request-a-Demo) is toggled on/off across many pages — real, populated, reusable content you can pull with a script even though the surrounding page can't be.
2. Two genuine template families exist (an "audience/vertical hub" template and a "MIP/accounting SEO pillar" template) where 3–5 pages each share near-identical structure with swapped copy — worth building once as a pattern, not five times by hand.
3. The dynamic grids on these pages (Solutions/Products/Testimonials/Blog listings via JetEngine) already have native equivalents in the rebuilt theme (`product-solution-tabs`, `solution-resources`, Query Loop, `resource-filters`) — so the "hard-looking" dynamic parts of these pages are actually the *cheap* parts to rebuild; the hand-written prose/hero copy is what takes time.

---

## 1. The reusable section field groups

These are Meta Box–style postmeta groups registered on the Page post type, each with an `enable_*_section` boolean toggle, independent of whatever's in the Elementor tree. Where enabled, they carry real, migratable content (title, description, buttons, images, sometimes a serialized repeater). Confirm on the live site that each still renders where you'd expect before treating it as gospel — the Posts migration found similarly-named field groups that turned out to be dead (see `posts-mystery-fields.md`), so don't assume without a visual check.

| Section | Enable flag | Pages with it `true` | Fields | Suggested destination |
|---|---|---|---|---|
| **CTA** | `cta_-_enable_cta_section` | 29 (spot check shows the same closing-banner look — "Propel your mission and vision forward" — repeats verbatim across many of them) | `title`, `description`, `button_1_text/link`, `button_2_text/link`, `desktop_background_image`, `mobile_background_image_833`, `enable_boxed` | No block for this exists yet in the theme. Worth building a `momentive/cta-band` pattern/block (title + description + 2 buttons + bg image) — it's the single most-reused section on the site. |
| **Statistics** | `statistics_-_enable_statistics_section` | 13 | `kicker_text`, `title`, `description`, `button_text/link`, `stats` (serialized repeater: `number_prefix`, `number`, `number_suffix`, `description`, `accent_color` per item) | Maps almost exactly to `momentive/impact-stat` (statPrefix/statNumber/statSuffix/statLabel/accentColor) — one block per stat item. |
| **Stats** (a second, near-duplicate group) | `statistics_-_enable_stats_section` | 2 (Finance for Fundraisers, Reviews) | Same shape as Statistics | Same destination — looks like an earlier/alternate version of the same component. |
| **Features** | `features_-_enable_features_section` | 12 | `kicker_text`, `title`, `description`, `button_text/link` (items likely live in a paired repeater not yet isolated — check `features_items`-style keys per page) | `momentive/icon-list` or a features-grid pattern, depending on layout. |
| **Approach** | `approach_-_enable_approach_section` | 6 — all in the MIP/accounting pillar-page cluster (see §2) | `kicker_text`, `title`, `description`, `button_label/url`, plus `enable_products_section` variant | Bespoke per pillar page; likely a text+accordion combo (`AccordionItems`, below, fires alongside this on the same pages). |
| **Testimonials** (meta) | `testimonials_-_enable_testimonials_section` | 2 (Home, Home v2 2026) | `title`, `description`, `button_text/link` | `momentive/testimonial` + Query Loop already covers this on the rebuilt theme. |
| **FAQs** (meta) | `faqs_-_enable_faqs_section` | 3 (Jobs, Request a Demo (new), Products) | `kicker_text`, `title`, `description` | `momentive/accordion` in FAQ CPT query mode already covers this — likely just needs the FAQ CPT posts tagged for the relevant page. |
| **Request a Demo** | `request_a_demo_-_enable_request_a_demo_section` | 1 (Driving Non-Dues Revenue Success) | `title`, `description`, `image`, `hubspot_form` | `acf/hubspot-form` block. |
| **Request a Demo — hero variant** | `request_a_demo_hero_enable_request_a_demo_hero_section` | 2 (Request a Demo (new), Connect with Your CSM) | `kicker_text`, `title`, `subheading`, `description`, `button_label/url`, `image`, `hubspot_form_script` | Same — `acf/hubspot-form`, in a hero layout. |
| **Accordion items** | non-empty `accordion_items` | 7 (matches the `jet-accordion` widget count exactly) | Serialized repeater: `icon`, `title`, body per item | `momentive/accordion`, static mode. |
| **Tabs** | non-empty `tab_titles` | 5 (matches `jet-tabs` widget count exactly) | Serialized repeater: `tab_icon`, `tab_title`, `content` per item | No direct native-block equivalent yet — likely hand-built with a `core/group` + JS, or worth a small custom block if this pattern repeats in future rebuilds. |
| **Post List 1–6 / `how_to_use_819` / `popup_information_1` / `our_approach_impact`** | — | **0 populated** across all 67 pages, despite the scaffolding existing on 63 of them | — | Dead field groups, same pattern as the Posts migration's mystery fields — registered globally, never actually used. Ignore. |

**Cross-reference:** the `cta_-_*` field group also appears (7 posts) in the Blog Posts export, flagged in `posts-mystery-fields.md` as "Group 2." It's the same component reused across both post types — further evidence it's worth building as a real, permanent block rather than a per-migration one-off.

**Known gap:** at least 6 more pages (About Us, Jobs, Solutions, Conference hubs, etc.) reuse an Elementor **global template** (`template` widget, ID 537, referenced on 19 pages total; a second one, ID 543, on 4 pages) instead of the meta-box CTA fields — almost certainly the same visual CTA banner, implemented the older way. Elementor Library templates aren't included in this pages export, so their content is invisible here. If you want to confirm what's inside template 537/543, you'd need a separate `elementor_library` post-type export — worth pulling before you start on the ~19 pages that depend on it.

---

## 2. The two real template families

### "Audience/vertical hub" template (3 pages, could be more)

`healthcare-event-success-hub`, `education-event-success-hub`, `science-event-success-hub` — verified by diffing their heading sequences: all three follow the identical section order (intro stat → 2-item webinar section → multi-item "insights" resource list with repeating "Watch/Read more" links → case-study/success-stories close), just with different verticals' copy and links swapped in. All three also fire `CTA`, `Stats`, and `Features` meta sections. Build this once as a pattern with content slots, then fill three times — don't hand-build three bespoke pages.

### "MIP/accounting SEO pillar" template (5–6 pages)

`best-accounting-software-for-growing-organizations` (draft), `best-accounting-software`, `mip-vs-sage`, `year-end-accounting-success-hub`, `finance-for-fundraisers-guide` — all fire `CTA` + `Approach` + `AccordionItems` (the year-end one also fires `Tabs`). These are long-form SEO/pillar pages (30–50KB of Elementor data each) targeting different accounting-software keywords with the same skeleton: hero → approach/value-prop block with an accordion of sub-points → CTA close. One shared pattern, five keyword variants.

---

## 3. Page inventory, grouped by rebuild cluster

Legend: **elen** = size of `_elementor_data` in bytes (rough proxy for how much bespoke content is packed in — not a literal cost estimate, but a useful sort). Sections = which meta field groups fire `true`/non-empty.

### A. Legal/policy — near-zero effort (7 pages) — ✅ DONE

Already native WordPress blocks (`<!-- wp:paragraph -->` etc.), not Elementor. Rebuilt.

| ID | Slug | Title | Legacy URL |
|---|---|---|---|
| 3 | `privacy-policy` | Privacy Policy | https://momentivesoftware.com/privacy-policy/ |
| 1240 | `core-apps` | Core-apps Privacy Policy | https://momentivesoftware.com/core-apps/ |
| 1244 | `expo-logic` | Expo Logic Privacy Policy | https://momentivesoftware.com/expo-logic/ |
| 1292 | `tripbuilder-media` | Tripbuilder Media Inc. Privacy Policy | https://momentivesoftware.com/tripbuilder-media/ |
| 30 | `terms-of-service` | Terms of Service | https://momentivesoftware.com/terms-of-service/ |
| 139 | `cookie-policy` | Cookie Policy | https://momentivesoftware.com/cookie-policy/ |
| 4017 | `accessibility-statement` | Accessibility Statement | https://momentivesoftware.com/accessibility-statement/ |

### B. MIP legacy support/utility cluster — low effort (4 pages) — ✅ DONE

Short, plain text-and-headings pages (1–10KB Elementor data each), no dynamic grids, no shared sections firing. `mip-abila` is a "this domain moved here" notice — confirm it's still needed or should be a redirect instead of a rebuilt page.

| ID | Slug | Title | Legacy URL |
|---|---|---|---|
| 7042 | `mip-system-requirements` | MIP - System Requirements | https://momentivesoftware.com/mip-system-requirements/ |
| 7059 | `mip-status` | MIP - Status | https://momentivesoftware.com/mip-status/ |
| 7060 | `mip-abila` | MIP - Abila | https://momentivesoftware.com/mip-abila/ |
| 7061 | `mip-disaster-recovery-hosting` | MIP - Disaster Recovery Hosting | https://momentivesoftware.com/mip-disaster-recovery-hosting/ |

### C. Transactional / demo-request cluster — low effort but needs a decision (13 pages)

All short (form + thank-you copy, mostly HubSpot/Wistia embeds via `html` widgets).

| ID | Slug | Title | Legacy URL |
|---|---|---|---|
| 237 | `contact-us` | Speak With Us | https://momentivesoftware.com/contact-us/ |
| 785 | `speak-with-us-success` | Speak With Us - Success | https://momentivesoftware.com/contact-us/speak-with-us-success/ |
| 604 | `request-a-demo` | Talk to sales | https://momentivesoftware.com/request-a-demo/ |
| 786 | `request-a-demo-success` | Request a demo - Success | https://momentivesoftware.com/request-a-demo/request-a-demo-success/ |
| 1685 | `registration-confirmation` | Registration Confirmation | https://momentivesoftware.com/registration-confirmation/ |
| 1692 | `demo-confirmation` | Demo Confirmation | https://momentivesoftware.com/demo-confirmation/ |
| 9382 | `stay-connected-momentive` | Stay Connected | https://momentivesoftware.com/stay-connected-momentive/ |
| 11040 | `employee-sales-lead-referral` | Employee Referral Program | https://momentivesoftware.com/employee-sales-lead-referral/ |
| 11041 | `partner-referral` | Partner Referral | https://momentivesoftware.com/partner-referral/ |
| 6825 | `demo` | Request a Demo (new) | https://momentivesoftware.com/demo/ |
| 5394 | `request-a-demo-uk` | Request a Demo UK | https://momentivesoftware.com/request-a-demo-uk/ |
| 8473 | `request-a-demo-cp-test` | Talk to sales (Chili Piper Test | https://momentivesoftware.com/request-a-demo-cp-test/ |
| 9108 | `support-mip-csm` | Connect with Your Customer Success Manager | https://momentivesoftware.com/support-mip-csm/ (fires the `ReqDemoHero` section, §1 — same shape as ID 6825, for the CSM ask rather than a general demo) |

**Flag for a decision:** there are 4 separate live "book a demo" page variants — IDs 604 (`/request-a-demo/`), 6825 (`/demo/`, "Request a Demo (new)"), 5394 (`/request-a-demo-uk/`), and 8473 (`/request-a-demo-cp-test/`, title literally says "Chili Piper Test"). These look like sequential replacements/A-B tests that were never cleaned up rather than 4 intentionally distinct pages. Worth confirming with whoever owns demo-request routing which one is canonical before rebuilding all four. (8473 also reappears in Cluster G — it's a live page, but named/shaped like a throwaway test.)

### D. "Audience/vertical hub" + related CTA/Stats/Features pages — medium effort (10 pages)

The 3 event-hub pages from §2, plus other pages sharing the same `CTA+Stats+Features` section combo. These lean on native dynamic grids (`jet-listing-grid`) for Products/Solutions/Testimonials content, which the rebuilt theme already has native blocks for — the manual work is the hero copy, the CTA/Stats/Features section content (already extractable per §1), and any custom carousel/tab content.

| ID | Slug | Title | Legacy URL |
|---|---|---|---|
| 1585 | `healthcare-event-success-hub` | Conference and event success hub: healthcare organizations | https://momentivesoftware.com/healthcare-event-success-hub/ |
| 1751 | `education-event-success-hub` | Conference and event success hub: education | https://momentivesoftware.com/education-event-success-hub/ |
| 2038 | `science-event-success-hub` | Conference and event success hub: science | https://momentivesoftware.com/science-event-success-hub/ |
| 232 | `about-us` | About Us | https://momentivesoftware.com/about-us/ |
| 233 | `our-team` | Our Team | https://momentivesoftware.com/our-team/ (CTA section only — no Stats/Features; likely already largely solved by the existing `momentive/person` block, per CLAUDE.md, which was purpose-built for this page) |
| 234 | `jobs` | Jobs | https://momentivesoftware.com/jobs/ |
| 560 | `solutions` | Solutions | https://momentivesoftware.com/solutions/ |
| 1699 | `nonprofit-trends-study` | Nonprofit Trends Study (Nucleus) | https://momentivesoftware.com/resources/nonprofit-trends-study/ |
| 5000 | `association-trends-study-dashboard` | Association Trends Study (Nucleus) | https://momentivesoftware.com/resources/association-trends-study-dashboard/ |
| 5511 | _(none — slug never set)_ | Mission Driven | https://momentivesoftware.com/?page_id=5511 (draft) |

### E. "MIP/accounting pillar" cluster — medium-high effort (5 pages, 1 draft)

Long-form, hand-written SEO prose is the bulk of the effort here (30–50KB of Elementor data each); the accordion/CTA sections are structured and extractable.

| ID | Slug | Title | Legacy URL |
|---|---|---|---|
| 5296 | `best-accounting-software-for-growing-organizations` | MIP The Right Fit for Growing Nonprofit Organizations Pillar | https://momentivesoftware.com/?page_id=5296 (draft — slug not live) |
| 5387 | `best-accounting-software` | MIP-best accounting-Pillar | https://momentivesoftware.com/best-accounting-software/ |
| 5440 | `mip-vs-sage` | Mission-driven Accounting Software | https://momentivesoftware.com/mip-vs-sage/ |
| 5597 | `year-end-accounting-success-hub` | Ultimate Resource Hub to Fuel Year-End Success | https://momentivesoftware.com/year-end-accounting-success-hub/ |
| 5738 | `finance-for-fundraisers-guide` | Nonprofit Accounting Series: Finance for Fundraisers | https://momentivesoftware.com/finance-for-fundraisers-guide/ |

### F. Flagship one-off pages — highest effort, no shortcuts (20 pages)

Each of these is a genuinely bespoke build with its own layout — carousels, tabbed content, badge galleries, integration filters, flip-boxes, etc. No shared schema to lean on; budget time per page individually.

| ID | Slug | Title | Legacy URL | elen (bytes) | What's actually in it |
|---|---|---|---|---|---|
| 9024 | `home-v2-2026` | Home (v2 2026) | https://momentivesoftware.com/ | 97,934 | **Currently the live homepage** (published) — hero, image carousels ×15, 4 dynamic grids, video |
| 196 | `home` | Home | https://momentivesoftware.com/?page_id=196 (draft — slug not live) | 73,992 | Appears to be the previous/superseded homepage. Confirm before ignoring. |
| 7570 | `reviews-2` | Reviews | https://momentivesoftware.com/reviews/ | 121,010 | Largest Elementor payload in the export; Stats + Stats2 sections both fire |
| 1309 | `resources` | Resources | https://momentivesoftware.com/resources/ | 36,862 | Likely superseded by the new `/momentive/v1/resources` REST + `resource-filters` block architecture — check whether this page needs to be a bespoke build at all vs. wiring into the already-built Resources plumbing (see CLAUDE.md's "no dedicated All Resources archive template has been built yet") |
| 181 | `blog` | Blog | https://momentivesoftware.com/blog/ | 24,451 | Blog archive/listing page |
| 10819 | `products` | Products | https://momentivesoftware.com/products/ | 38,939 | Hero + 3 dynamic grids + FAQ section; heavy overlap with the already-built `product-solution-tabs` block |
| 1150 | `support` | Support | https://momentivesoftware.com/support/ | 12,474 | JetSmartFilters + listing grid — overlaps with the already-built `resource-filters` block pattern |
| 8967 | `who-we-serve` | Who We Serve | https://momentivesoftware.com/who-we-serve/ | 13,287 | Icon-list + 2 dynamic grids |
| 8297 | `ai-resource-hub` | AI Resource Hub | https://momentivesoftware.com/ai-resource-hub/ | 59,933 | Tabs, accordion, 6 dynamic-field widgets |
| 2778 | `giving-tuesday` | Momentive Software's #GivingTuesday Success Toolkit | https://momentivesoftware.com/giving-tuesday/ | 45,062 | CTA+Stats+Approach+Accordion+Tabs — largest `content:encoded` in the whole export (1MB+), likely lots of embedded media/downloads |
| 4091 | `fundraising-ideas` | Fundraising Ideas | https://momentivesoftware.com/solutions/fundraising-software/ideas/ | 40,683 | Icon-list + JetSmartFilters (searchable idea gallery) |
| 2320 | `best-job-board-for-associations` | Careers Powered by Momentive: The Best Job Board for Associations | https://momentivesoftware.com/best-job-board-for-associations/ | 56,436 | Tabs + accordion, long-form product-marketing page |
| 1951 | `optimizing-nonprofit-efficiency` | Optimizing Nonprofit Operations: Increasing Efficiency | https://momentivesoftware.com/optimizing-nonprofit-efficiency/ | 46,337 | Tabs + accordion, long-form guide |
| 3812 | `driving-lp` | Driving Non-Dues Revenue Success | https://momentivesoftware.com/driving-non-dues-revenue-success/ | 31,760 | Request-a-Demo section + 2 dynamic-field/grid widgets — a gated-content landing page |
| 3563 | `path-lms-integrations` | Path LMS Integrations | https://momentivesoftware.com/path-lms-integrations/ | 37,292 | JetSmartFilters + listing grid — an integrations directory |
| 6659 | `bring-on-better-awards` | Bring on Better™ Awards | https://momentivesoftware.com/bring-on-better-awards/ | 27,464 | 5 listing grids; CLAUDE.md already flags this CPT for retirement into a pattern — this page is the pattern's home |
| 10845 | `membership-website-templates` | Membership Website Templates | https://momentivesoftware.com/solutions/association-management-software/membership-website-templates/ | 20,212 | Icon-list ×3 + image gallery — template showcase |
| 2977 | `expo-logic-badge-gallery` | Event Badge Gallery | https://momentivesoftware.com/solutions/event-management-software/check-in-and-badging/event-badge-gallery/ | 35,974 | 7 flip-boxes, an actual image gallery of badge designs |
| 5303 | `financial-storytelling-for-nonprofits` | Telling your Organization's Story | https://momentivesoftware.com/financial-storytelling-for-nonprofits/ | 47,605 | CTA+Stats+Features |
| 3114 | `partner` | Partner | https://momentivesoftware.com/partner/ | 23,042 | CTA section, partner-program copy |

### G. Test/draft/scratch — exclude from migration scope (10 pages)

Confirmed via empty content, "Test" in the title, or duplicate/superseded slugs. Recommend explicitly excluding these rather than silently skipping, so nothing is assumed lost:

| ID | Slug | Title | Legacy URL | Notes |
|---|---|---|---|---|
| 199 | `styleguide` | Styleguide | https://momentivesoftware.com/?page_id=199 (draft) | |
| 2773 | `solutions2` | Solutions (Test) | https://momentivesoftware.com/solutions2/ (published) | Live A/B variant of `/solutions/`, same CTA+Features sections firing; confirm it isn't still linked from anywhere before dropping |
| 1669 | `test-page` | Test Page | https://momentivesoftware.com/?page_id=1669 (draft) | |
| 1872 | `extra-modules` | Extra Modules | https://momentivesoftware.com/?page_id=1872 (draft) | Empty content |
| 2705 | `featured-resources-test` | Featured Resources Test | https://momentivesoftware.com/?page_id=2705 (draft) | Empty content |
| 3163 | _(none)_ | Post List Test | https://momentivesoftware.com/?page_id=3163 (draft) | |
| 8473 | `request-a-demo-cp-test` | Talk to sales (Chili Piper Test | https://momentivesoftware.com/request-a-demo-cp-test/ (published) | See Cluster C note on demo-page duplication — it's live, but named/shaped like a throwaway test |
| 5456 | _(none)_ | Mission-driven Accounting Software | https://momentivesoftware.com/?page_id=5456 (draft) | Completely empty — a duplicate of the published `mip-vs-sage` (ID 5440), which shares the same title |
| 5511 | _(none)_ | Mission Driven | https://momentivesoftware.com/?page_id=5511 (draft) | Near-empty; distinct from the pillar-page cluster despite the similar name (also listed in Cluster D, since it does fire CTA/Stats/Features) |
| 6004 | `momentive-software-support` | Momentive Software Support | https://momentivesoftware.com/?page_id=6004 (draft) | A from-scratch redraft of the live `/support/` page (ID 1150); worth a quick look to see if it's a newer direction before treating `/support/` as final |

---

## 4. Recommended approach

Given there's no repeatable schema to script against, treat this as prioritized hand-rebuilding rather than a migration script:

1. ~~**Cluster A (legal) first**~~ — ✅ done.
2. ~~**Cluster B (MIP utility)**~~ — ✅ done. **Cluster C (transactional/demo-request)** is still open — short, low-risk, a good next warm-up before the bigger pages. Resolve the demo-page-duplication (IDs 604, 6825, 5394, 8473) first so you don't rebuild pages that should simply redirect.
3. **Build the two template families (§2) as real patterns**, then fill in D/E's members — this converts 15 pages into "2 patterns + copy," not 15 bespoke builds.
4. **Cluster F (20 flagship pages)** is where the real time goes — no shortcuts available. Worth sequencing by business priority (the live homepage and Products/Resources/Support, which overlap with plumbing you've already built, are probably worth doing before one-off campaign pages like the Badge Gallery or GivingTuesday toolkit).
5. **Skip cluster G** outright, after confirming none of the "test" pages are secretly still linked from live navigation or inbound campaign links.
6. If you want template 537/543's actual content (the shared Elementor CTA banner used on 19+4 pages), pull a separate `elementor_library` WXR export — it wasn't included in this pages export.

Total realistic rebuild scope: **55 published pages** (57 published − 2 actually-live pages in Cluster G, `solutions2` and `request-a-demo-cp-test` — the other 8 excluded items were already drafts, not live pages, so they don't reduce the published count further), of which 11 are done (Clusters A+B), 13 remaining low effort (C), 15 collapse into 2 template patterns (D+E), and 20 need individual bespoke builds (F). Every one of the 67 legacy page IDs is accounted for exactly once above (or twice, for the 2 pages that sit in both a working cluster and the exclude list — 8473 in C/G, 5511 in D/G).
