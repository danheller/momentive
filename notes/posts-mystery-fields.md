# Blog Post Migration — Mystery Fields

These field groups appear in the post editor metaboxes but are **not referenced by any Elementor shortcode** in the post content. The strong hypothesis is that they render nothing on the published page — either the shortcode was never added to these posts, or the feature was set up and abandoned.

**Verification method:** open the post on the legacy site, scroll through the full page (desktop), and confirm whether any visible block corresponds to the field data. The cleanest verification posts are those with NO Elementor shortcodes in their content — anything visible must come from a template hook reading these fields, not from a shortcode.

---

## Group 1 — `resource_cta_*` (48 posts)

**Field keys:**

| Key | Notes |
|---|---|
| `resource_cta_enable_cta` | `true`/`false` toggle |
| `resource_cta_ttitle` | Title text (typo in key — double t). Can contain HTML `<a>` tags. |
| `resource_cta_button_text` | Button label |
| `resource_cta_button_url` | Button URL |
| `resource_cta_button_link_outbound` | `true`/`false` — open in new tab |
| `resource_cta_image` | Attachment ID (rare — a few posts only) |
| `resource_cta_description` / `resource_cta_copy` | Additional text (rare) |

**Sample data:**
```
resource_cta_enable_cta: true
resource_cta_ttitle: Ready to make 2026 your year? Discover how accounting software can help.
resource_cta_button_text: Get Started
resource_cta_button_url: /solutions/accounting-software/
resource_cta_button_link_outbound: true
```

**Posts to verify** (no Elementor shortcodes — cleanest signal):

| Post ID | Slug | CTA title preview |
|---|---|---|
| 949 | `delivering-more-value-to-mission-driven-organizations` | "Learn more about Momentive's Blue Sky eLearn acquisition." |
| 1181 | `nonprofit-financial-audit-best-practices` | "Read the Comprehensive Go-to Guide for Nonprofit Accounting" |
| 2615 | `best-ams` | "Find your ideal AMS fit." |
| 2932 | `protect-learner-data-multi-factor-authentication` | "Connect with our team today!" |
| 4039 | `5-strategies-to-account-for-diverse-funding-sources` | "Watch this webinar to learn more future-proof strategies" |

**What to check:** Does a styled CTA card / button appear anywhere in these posts on the legacy site? If not, this field group is dead and can be ignored in migration.

**If it does render:** note where (top of post, after first section, bottom, sidebar) and what it looks like, and we'll add a migration block for it.

---

## Group 2 — `cta_-_*` (7 posts)

Structurally similar to `sc_cta_-_*` but different field key prefix and supports a second button.

**Field keys:**

| Key | Notes |
|---|---|
| `cta_-_enable_cta_section` | `true`/`false` toggle |
| `cta_-_title` | Heading text |
| `cta_-_description` | Body text (can contain HTML) |
| `cta_-_button_1_text` | First button label |
| `cta_-_button_1_link` | First button URL |
| `cta_-_button_1_-_open_in_new_tab` | `true`/`false` |
| `cta_-_button_2_text` | Second button label (optional — some posts only) |
| `cta_-_button_2_link` | Second button URL |

**Sample data (best-event-apps-2):**
```
cta_-_title: Event App: Take the Next Steps with Momentive Software
cta_-_description: Momentive Software's event management platform...
cta_-_button_1_text: Schedule a Demo
cta_-_button_1_link: https://momentivesoftware.com/request-a-demo/
cta_-_button_2_text: Explore Features
cta_-_button_2_link: https://momentivesoftware.com/solutions/
```

**Posts to verify** (no Elementor shortcodes in content — cleanest signal):

| Post ID | Slug | CTA title |
|---|---|---|
| 8462 | `nonprofit-payment-processing` | _(no title field set)_ |
| 8861 | `best-event-apps-2` | "Event App: Take the Next Steps with Momentive Software" |
| 9089 | `membership-engagement-ideas` | "Take the Next Step" |

**What to check:** same as above — does a styled CTA block appear anywhere on the page?

---

## Group 3 — `extra_feat_-_*` (5 posts)

**Decision: ignore.** All 5 posts have literally `"test"` / `"test"` / `"test"` in every field. This was clearly a feature under development that was never deployed. Drop these fields during migration.

Affected posts: `nonprofit-financial-reporting`, `large-scale-events-vs-small`, `increase-event-registration`, `manage-a-better-booth-floor-plan`, `event-registration-process`.

---

## Group 4 — `custom_sidebar_cta_*` (1 post)

**One post only:** `modernizing-association-budget-process` (ID 6532).

| Key | Value |
|---|---|
| `enable_custom_sidebar_cta` | true |
| `custom_sidebar_cta_post` | 6507 (post ID — probably a related post) |
| `custom_sidebar_cta_image` | attachment ID |
| `custom_sidebar_cta_title` | (title text) |
| `custom_sidebar_cta_button_text` | (button label) |
| `custom_sidebar_cta_button_url` | (URL) |

**What to check:** does a custom sidebar CTA appear on this post? If yes, note its appearance — might be worth a one-off manual edit rather than migration logic for a single post.

---

## Group 5 — `post_hero_button_cta_*` (1 post)

**One post only:** `innovation-blog-series-q1-26` (ID 8858).

```
post_hero_button_cta_text: See what MomentiveIQ can do for you
post_hero_button_cta_url: (not visible in WXR — key may be different)
```

**What to check:** does a hero-area CTA button appear on this post? If it does nothing, ignore. If it renders, handle as a one-off manual edit.

---

## Migration decision tree

After verification:

- **Fields render nothing** → ignore in migration script; do not emit any block
- **Fields render a visible block** → add a migration branch using the same `highlight-cta` group block pattern, positioned wherever it appears on the page
- **Only 1 post** → handle with a one-off manual edit regardless

---

## Fields confirmed dead (no verification needed)

These were checked and either have zero posts with data or are confirmed no-ops:

| Field group | Posts | Status |
|---|---|---|
| Popup form embed | 0 | Never used |
| Footer CTA | 0 | Never used |
| Testimonials (meta) | 0 | Never used |
| Statistics section (meta) | 0 | Never used |
| Disable sidebar TOC | 0 | Never used |
| `extra_feat_-_*` | 5 | All test data — drop |
| `blog_pillar_*` | — | Only rank_math_pillar_content, already handled by RankMath |
