# Press Article (Newsroom) Rebuild — Reference Sheet + Coverage List

64 `press-article` posts imported from the legacy site (63 published + 1 draft). **7 have now been hand-rebuilt**: the original 2 (9866 `inspiring-leaders-tawny-kotchko`, 10265 `momentive-software-appoints-new-ceo`) plus the 5 coverage posts suggested in the first pass of this sheet — 4030 (`donor-engagement-gamification`), 2125 (`inspiring-leaders-tirrah-switzer`), 4372 (`unlocking-major-gifts`), and legacy 793/11191 which were re-imported under new IDs 7810 (`asae-showcase`) and 18275 (`security-notice-third-party-vendor-incident`, still draft) — see the note on ID drift below. The other 57 still carry only their raw paragraph/heading content, with no page layout.

**Source exports:**
- `migrations/momentivesoftware.current.press-articles.2026-07-22.xml` — 64 legacy `press-article` posts (this is the source of truth for content/meta)
- `migrations/momentive.press-article.rebuild.2026-07-26.xml` — re-exported 2026-07-26 evening; now 55 `press-article` posts + 117 attachments, up from 54/114 in the first export

**A heads-up about `migrations/rebuild.csv`:** the automated rebuild-progress report (`report-rebuild-progress.php`, run 2026-07-20) currently shows press-article as **54/54 "rebuilt"** — that number is misleading. Its classifier (`momentive_rebuild_report_classify()`) only checks for the presence of any `<!-- wp: -->` block comment, and every legacy press-article post already arrived with its body wrapped in plain `wp:paragraph`/`wp:heading` blocks from the original import — so the classifier sees real block markup and calls it "rebuilt" even though none of the actual page scaffold (entry-header, breadcrumbs, byline, sidebar) is present. This is the same false-positive risk the Solutions migration ran into with stale Elementor meta, just from the opposite direction (real blocks, but not the *right* blocks). Real coverage is 7/64 as of this update, not 54/55 — the classifier would need a press-article-specific check (e.g. "does the content contain a `post-layout` columns block") to be trustworthy for this CPT.

---

## Field → destination map

### Identity / header

| Legacy field | Becomes | Notes |
|---|---|---|
| post `<title>` | Post title | Used directly (unlike Case Studies, no "Org: Product" pattern here) |
| `custom_breadcrumb_title` | ACF `breadcrumb_title` (already exists, `group_6a0dbbc36e898`) | 30/64 populated; direct rename, no transform needed |
| `news-category` | Native category taxonomy term | See "Category resolution" below — legacy never used real WP categories, only this meta field |
| `_thumbnail_id` | Featured image | 57/64 have one; 7 posts have none (needs graceful no-image handling) |
| `hero_image` (attachment ID) + `alternate_hero_image` (bool) | ACF `hero_image` field (already exists, `field_6a17d4fd884cd`) | Only sideload/set `hero_image` when `alternate_hero_image === 'true'` (24/64) — same convention already used for webinars/whitepapers/press-article's own `render_block` swap filter in `blog-and-newsroom.php`. When false, leave empty and let the featured image serve both roles |
| `display_featured_image_on_page` | **Confirmed dead — do not migrate** | `false` on 12/64 posts. Daniel confirmed the field is ignored (matches `asae-showcase`'s rebuilt output, which shows the image regardless). Migration script always emits `<!-- wp:post-featured-image /-->` unconditionally, same as every other CPT |
| `hero_subtitle` | Large-font paragraph directly under H1 in the header | Confirmed by 9866 and 2125: `<!-- wp:paragraph {"fontSize":"large"} --><p class="has-large-font-size">{hero_subtitle}</p>`. Only emit when non-empty |
| `hero_title` | **Confirmed vestigial — do not migrate as a separate block** | 5/64 posts have this field set (all "Inspiring Leaders" posts). Checked against 2125 (`inspiring-leaders-tirrah-switzer`), which has it set: the rendered H1 is still a plain `wp:post-title` block, and `hero_title`'s value is byte-identical to the post title itself. It never drives a separate override anywhere in the rebuilt output. Safe to skip entirely in the migration — the ordinary post title already covers it |
| `hero_overline` | Eyebrow paragraph above H1 | Confirmed by 2125: `<!-- wp:paragraph {"className":"is-style-eyebrow","fontSize":"regular"} --><p class="is-style-eyebrow has-regular-font-size">{hero_overline}</p>`, placed after `post-terms` and before `post-title`. Always `"Inspiring Leaders"` in this corpus (6/64 posts) — only emit when non-empty |
| `hero_image_alignment` | Conditional style on the `entry-header` group | Checked all 64 legacy posts: `center` on 63, `bottom` on exactly 1 (2125) — no other values exist in this corpus. Daniel confirmed the migration script **should** replicate this conditionally (reversing the initial guess that it was a one-off not worth scripting): when `hero_image_alignment === 'bottom'`, emit the `entry-header` group with `"style":{"spacing":{"padding":{"top":"var:preset|spacing|medium","bottom":"0"}}}` (inline style `padding-top:var(--wp--preset--spacing--medium);padding-bottom:0`); otherwise (the `center` default, 63/64 posts) emit the plain unstyled group. Since only 2125 has `bottom` in the current corpus, this rule will only ever fire for that one post in practice — but it's a trivial, deterministic conditional, so the script should implement it generically rather than special-casing 2125's ID directly |
| `ppma_authors_name` | `post_author_ref` (via name match to People CPT) | Present on all 64; reuse `momentive_pm_set_author()` from `migrate-posts.php` verbatim — same PublishPress-Authors-name → People-CPT-title matching already used for Blog. 9866 resolves to "Heather Noll" (person ID 10528); 10265 resolves to the shared "Momentive Software" byline, matching the existing byline architecture in CLAUDE.md |

### Category resolution (news-category → real taxonomy term)

Legacy press-articles were **never assigned real WP categories** — despite the CPT declaring `category` support, every one of the 64 posts has an empty native category panel, and instead carries a `news-category` postmeta value:

| `news-category` value | # posts | Rebuilt term (confirmed) |
|---|---|---|
| `press-releases` | 32 | "Press Release" (nicename `press-release`, **singular** — note the meta value is plural but the confirmed term nicename is not). Confirmed on 10265 and 7810 |
| `in-the-news` | 20 | "In the News" (nicename `in-the-news`, matches the meta value exactly). Confirmed on 4030 and 4372 |
| `momentive-in-action` | 12 | "Momentive in Action" (nicename `momentive-in-action`) — confirmed via 9866 and 18275 |

All 3 terms are now confirmed directly from rebuilt posts' `category` elements. These are **not** children of the "Solutions" parent category the way blog/case-study/webinar categories are — they're their own small, dedicated set of exactly 3 terms. The migration script should look up (or create once) these 3 terms and assign by `news-category` value, rather than reusing any Solution-mapping helper.

**One open item:** `inspiring-leaders-tirrah-switzer` (ID 2125) currently has **no category assigned at all** on the rebuilt site, despite `news-category = momentive-in-action` in its legacy data. Likely just missed during the hand-rebuild rather than intentional — worth double-checking before treating 2125 as a fully-confirmed reference for category assignment.

**Permalink implication:** these 3 terms now also drive the front-end URL — see "Category-scoped permalinks" below, implemented in `inc/blog-and-newsroom.php`.

### Body content

Unlike every other WYSIWYG-heavy CPT migrated so far (Case Studies, Webinars, Whitepapers, Blog), **press-article body content needs no Word-artifact stripping** — checked all 64 posts for `TextRun`/`data-contrast`/`NormalTextRun`/`SCXW` fingerprints and found zero. The content is already valid `wp:paragraph`/`wp:heading` block markup (a residue of an earlier bulk import pass), just missing the page scaffold around it. `&nbsp;` entities appear in 39/64 posts (cosmetic; optional cleanup, not required). Two posts (`collective-strength-symposium`, `2b-raised-2025`) have a stray `[elementor-template id="1458"]` shortcode string left in the content — that's the same CTA Section shortcode described below and should be replaced, not left as literal text.

### Shortcode-driven fields (shared system with Blog posts — see `notes/posts-reference-sheet.md`)

Press-articles carry the exact same SC-shortcode meta field families as Blog posts, but use almost none of them:

| Field family | # press-articles using it | Notes |
|---|---|---|
| `sc_cta_-_*` (CTA Section, template 1458) | 2 (`collective-strength-symposium`, `2b-raised-2025`) | Reuse `momentive_pm_cta_block()` from `migrate-posts.php` directly |
| `sc_cta_with_image_-_*` | 0 | Enable flags present but always `false` |
| `sc_tip_*` (Tip 1–6) | 0 | Enable flags present but always `false` — unlike Blog, no press-article ever uses the tip shortcodes |
| `enable_checklist_1` / `checklist_1` | 0 | Enable flag present on 23 posts but always `false`, and the checklist data itself is always empty |
| `resource_cta_*` (bottom-of-post branded CTA) | 1 (`path-lms-advanced-assessments`) | Reuse `momentive_pm_resource_cta_block()` from `migrate-posts.php` directly |
| `cta_-_*` / `cta_block_*` (a separate, older CTA system) | 0 functional | `enable_cta_block_section` / `cta_-_enable_cta_section` present as `false` on 25 posts; every actual title/description/button subfield is unconditionally empty across all 64 posts — this system is vestigial for this CPT, safe to ignore entirely |
| `custom_sidebar_cta_*` | 0 functional | Same story — `enable_custom_sidebar_cta` present (23 posts, always `false`) but every subfield (`_image`, `_title`, `_button_text`, `_button_url`) is empty on every post. Ignore |

**Practical implication:** a `migrate-press-articles.php` script barely needs the shortcode machinery at all — only 3 of 64 posts (2 CTA, 1 resource CTA) touch it. Copy `momentive_pm_cta_block()`, `momentive_pm_resource_cta_block()`, and `momentive_pm_tpl_block()`'s dispatch logic from `migrate-posts.php` rather than rebuilding them; everything else in that shortcode family can be skipped for this CPT.

### Original-publication attribution (`in-the-news` only — now confirmed)

20/64 posts — **exactly** the ones tagged `news-category = in-the-news`, a 1:1 correlation — carry a 3-field outlet-attribution group with no analog in any other migrated CPT:

| Field | Contents |
|---|---|
| `op_name` | Outlet name, e.g. "Nonprofit PRO", "Associations Now", "The NonProfit Times" |
| `op_link` | URL to the original external article |
| `op_logo` | Legacy attachment ID for the outlet's logo (18/20 have one; 2 don't — `momentive-channel-partner-program`, `staff-retention-donor-retention`, `vip-event-experiences` have `op_name`/`op_link` but no logo) |

**Confirmed rebuilt pattern** (from 4030 and 4372, both `in-the-news`): rendered as the **last thing in the post-content column**, after all body paragraphs and before the About block (when present) — not in the sidebar as originally guessed:

```html
<!-- wp:group {"className":"is-style-outline source","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-small","bottom":"var:preset|spacing|x-small","left":"var:preset|spacing|small","right":"var:preset|spacing|small"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-outline source" style="padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--small)">
	<!-- wp:image {"id":{new_attachment_id},"width":"auto","height":"24px","sizeSlug":"medium","linkDestination":"none"} -->
	<figure class="wp-block-image size-medium is-resized"><img src="{logo_url}" alt="{op_name}" class="wp-image-{id}" style="width:auto;height:24px"/></figure>
	<!-- /wp:image -->

	<!-- wp:paragraph -->
	<p>This article was originally published by <a href="{op_link}" target="_blank" rel="noreferrer noopener">{op_name}</a>.</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
```

When `op_logo` is empty (the 3 posts noted above), the migration should presumably drop the `wp:image` block and keep just the paragraph — untested directly, but a safe inference since the block degrades to exactly that shape with the image simply omitted.

One post (`donor-engagement-gamification`, ID 4030) also has `logo_optional` set to the same attachment ID as its `op_logo` — the only place this field is used anywhere in the corpus. Confirmed harmless: the rebuilt output only reads `op_logo`. Safe to ignore `logo_optional` entirely.

### "About" boilerplate (`additional_about_sections`)

A PHP-serialized repeater (`about_title` + `about_description`, always exactly 1 item in this corpus) on 6/64 posts: `789`, `1975`, `4372`, `8188`, `9992`, and `10242` (legacy source for rebuilt 10265).

**Confirmed rebuilt pattern (from 10265, 4030, 7810):** the canonical "About TA" text is emitted as a **synced pattern reference** — `<!-- wp:block {"ref":10593} /-->` — not inline content. This mirrors the precedent already established for the Solutions hub HubSpot-form override (CLAUDE.md's "hub-level HubSpot demo-form override" section): reuse one canonical synced block instead of duplicating boilerplate per post.

**Confirmed: this is category-gated, not driven by any legacy field.** Checked all 7 rebuilt posts —

| Post | Category | Synced "About TA" block present? |
|---|---|---|
| 10265 (press release) | Press Release | Yes |
| 4030 (in-the-news) | In the News | Yes |
| 7810 (press release) | Press Release | Yes |
| 4372 (in-the-news) | In the News | Yes (plus its own separate per-post bio, see below) |
| 9866 (momentive-in-action) | Momentive in Action | No |
| 2125 (momentive-in-action) | Momentive in Action | No |
| 18275 (momentive-in-action) | Momentive in Action | No |

That's a clean 4-for-4 / 0-for-3 split by category — press-releases and in-the-news posts always get the synced "About TA" block appended; momentive-in-action posts never do (makes editorial sense: the private-equity-parent boilerplate belongs on official company/media news, not on internal culture pieces). The `show_default_about` meta field, which looked like a candidate driver, turns out to be `true` on **all 64 legacy posts with no exceptions** — it carries no signal at all and can be ignored. **Migration rule, confirmed:** for press-releases/in-the-news posts, append `<!-- wp:block {"ref":10593} /-->` as the last block inside the `post-content` column (i.e. after the body content, the outlet-attribution block when present, and any per-post about section) — exactly where it appears in 10265/4030/7810's rebuilt content. For momentive-in-action posts, omit it entirely. Category-driven, not meta-field-driven.

This also matters because the legacy "About TA" text has **drifted between posts** — `789`'s version says "560 companies... 150 investment professionals," while `10242`'s (the legacy source for 10265) says "560 companies... 160 investment professionals" with slightly different sector wording. The rebuilt posts all reference the same synced block regardless of which wording their own legacy `about_description` had. Recommendation: don't migrate each post's own `about_description` text verbatim for "About TA" — just reference the existing synced pattern. The two "About The Stevie® Awards" instances (`8188`, `9992`) show the same drift pattern and are a second candidate for their own synced pattern, if those posts get the same treatment. `4372`'s *other* about section (Lisa Zola Greer bio, empty `about_title`) is confirmed genuinely per-post — it renders as a plain inline `is-style-outline` group (no heading, just the bio paragraph) placed after the synced "About TA" block, not a synced pattern.

---

## Decoded reference posts (7 rebuilt so far)

### #1 — Tawny Kotchko (Inspiring Leaders / `momentive-in-action`, simple variant)

- **ID:** 9866 · **slug:** `inspiring-leaders-tawny-kotchko`
- **category:** Momentive in Action
- **hero_subtitle:** "SVP, Corporate Marketing" (no `hero_title`/`hero_overline` — this post uses the simpler of the two Inspiring Leaders variants)
- **hero_image:** legacy ID 9872, used via `alternate_hero_image=true` (differs from featured image, legacy ID 10274/rebuilt 10274)
- **byline:** `post_author_ref` = 10528 (Heather Noll)
- **breadcrumb_title:** "Inspiring Leaders: Tawny Kotchko"

Full rebuilt scaffold (identical shape to `patterns/press-article-content.php`, with the added hero_subtitle paragraph):

```html
<!-- wp:group {"align":"full","className":"entry-header","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull entry-header"><!-- wp:group {"className":"header-inner"} -->
<div class="wp-block-group header-inner"><!-- wp:group {"className":"header-media"} -->
<div class="wp-block-group header-media"><!-- wp:post-featured-image /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"header-content"} -->
<div class="wp-block-group header-content"><!-- wp:momentive/breadcrumbs {"lock":{"move":true,"remove":true}} /-->

<!-- wp:post-terms {"term":"category","separator":"","lock":{"move":true,"remove":true},"className":"taxonomy-category lower-label"} /-->

<!-- wp:post-title {"level":1,"lock":{"move":true,"remove":true}} /-->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">SVP, Corporate Marketing</p>
<!-- /wp:paragraph -->

<!-- wp:momentive/post-cta-button /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:columns {"isStackedOnMobile":false,"className":"post-layout"} -->
<div class="wp-block-columns is-not-stacked-on-mobile post-layout"><!-- wp:column {"className":"post-content"} -->
<div class="wp-block-column post-content"><!-- wp:momentive/post-byline /-->

... body paragraphs/headings, verbatim from legacy content, unchanged ...

</div>
<!-- /wp:column -->

<!-- wp:column {"className":"post-sidebar"} -->
<div class="wp-block-column post-sidebar"><!-- wp:group {"className":"sidebar-sticky"} -->
<div class="wp-block-group sidebar-sticky"><!-- wp:momentive/table-of-contents /-->

<!-- wp:momentive/social-share /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
```

Body content itself is the legacy content untouched — no Word cruft, no shortcodes, just paragraphs/headings.

### #2 — Ravi Venkatesan CEO appointment (`press-releases`)

- **ID:** 10265 · **slug:** `momentive-software-appoints-new-ceo`
- **category:** Press Release
- **Legacy source:** ID 10242, same slug — the post was re-imported under a new ID (10242 → 10265) at some point rather than upserted in place; the migration script should match by **slug**, not legacy ID, for this CPT (unlike Solutions, which matches by legacy ID)
- **hero_subtitle / hero_title / hero_overline:** none — plain press-release header, straight to `post-cta-button`
- **hero_image:** none (`alternate_hero_image=false`) — featured image only
- **byline:** `post_author_ref` resolves to the shared "Momentive Software" People profile (from `ppma_authors_name = "Momentive Software"`)
- **About section:** `<!-- wp:block {"ref":10593} /-->` appended after the body paragraphs — a synced pattern, not inline content (see above)

Scaffold is identical to 9866's minus the hero_subtitle paragraph; body is 4 paragraphs of press-release copy (quotes, announcement text) followed by the synced "About TA" block, then the same sidebar (TOC + social-share).

### #3 — Tirrah Switzer (Inspiring Leaders / `momentive-in-action`, full variant)

- **ID:** 2125 · **slug:** `inspiring-leaders-tirrah-switzer`
- **category:** none assigned on the rebuilt post (likely an oversight — see above)
- **hero_overline:** "Inspiring Leaders" (eyebrow paragraph, confirmed pattern above)
- **hero_title:** set, but identical to the post title and not separately rendered (confirmed vestigial, see field map)
- **hero_subtitle:** "VP, Product Marketing" (large paragraph, same as 9866)
- **hero_image_alignment:** `bottom` — drove a hand-copied, one-off padding style on the `entry-header` group that Daniel confirmed should **not** be replicated by the migration script
- No "About TA" block (momentive-in-action) — confirms the category-gated rule above
- Body includes two `wp:gallery` blocks (photo pairs) — just ordinary content, no new pattern needed

### #4 — Donor engagement gamification (`in-the-news`)

- **ID:** 4030 · **slug:** `donor-engagement-gamification`
- **category:** In the News
- Confirms the outlet-attribution block (op_name/op_link/op_logo) exactly as documented above
- Has the synced "About TA" block (in-the-news gets it)
- `logo_optional` present but unused in the rebuilt output — ignore

### #5 — Unlocking major gifts (`in-the-news`)

- **ID:** 4372 · **slug:** `unlocking-major-gifts`
- **category:** In the News
- Second outlet-attribution example — same block shape, different outlet/logo
- Has **both** the synced "About TA" block *and* its own per-post bio group (Lisa Zola Greer/Pooya Pourak/Cara Dickerson) — the per-post bio is plain inline content, placed after the synced block
- Body opens with a `<p class="h3">...</p>` styled lead line naming Cara Dickerson as co-author. This is **not driven by any legacy field** — it's an editorial styling choice Daniel made while hand-building, seen on 2 other rebuilt posts too (7810, 18275) with different text each time. Not something the migration script should try to auto-generate; leave it as a manual post-migration touch-up where editors want it

### #6 — ASAE showcase (`press-releases`, `display_featured_image_on_page = false`)

- **ID:** 7810 (legacy ID 793, re-imported under a new ID — same slug-drift pattern as 10242→10265) · **slug:** `asae-showcase`
- **category:** Press Release
- `display_featured_image_on_page = false`, yet `<!-- wp:post-featured-image /-->` is still present in the header unconditionally — the field appears **not to be honored**, see the open question in the field map above
- Has the synced "About TA" block
- Same `<p class="h3">` lead-line styling as 4372, different text — reinforces that this is a per-post editorial choice, not a field-driven pattern

### #7 — Security notice (`momentive-in-action`, non-Inspiring-Leaders variant, draft)

- **ID:** 18275 (legacy ID 11191, same re-import-under-new-ID pattern) · **slug:** `security-notice-third-party-vendor-incident` · **status:** draft
- **category:** Momentive in Action
- Confirms momentive-in-action is **not** 1:1 with the "Inspiring Leaders" sub-series — this is a plain company notice with no hero_overline/hero_subtitle, using the same bare scaffold as any press-release
- No "About TA" block (consistent with the category rule)
- Rebuilt postmeta is much sparser than the other posts (no `sc_cta_-_*`, no `show_default_about`, etc.) — consistent with this being an editor-authored rebuild rather than one that retained leftover legacy import meta; not a concern for the migration script since none of that meta is legacy content this post needs preserved

---

## Reusable code from existing migration scripts

`migrate-posts.php` (the Blog migration) is the closest analog — press-articles share its exact shortcode-meta-field vocabulary and use the near-identical page scaffold. Reuse rather than rewrite:

| Function (in `migrate-posts.php`) | Reuse for press-articles as-is? |
|---|---|
| `momentive_pm_blog_scaffold( $body )` | Yes, with one addition: inject the `hero_subtitle` paragraph (when set) right after `post-title`, before `post-cta-button` |
| `momentive_pm_cta_block(...)` | Yes — handles the 2 posts using `sc_cta_-_*` |
| `momentive_pm_resource_cta_block(...)` | Yes — handles the 1 post using `resource_cta_*` |
| `momentive_pm_set_author( $post_id, $author_name )` | Yes — identical `ppma_authors_name` → People-CPT-title matching |
| `momentive_pm_sideload(...)`, `momentive_pm_build_att_map(...)` | Yes — same WXR attachment-map-by-ID sideload pattern used everywhere else |
| `momentive_pm_strip_word(...)` | Not needed — press-article content has no Word cruft (see above), but harmless to run defensively |
| `momentive_pm_tip_block`, `momentive_pm_checklist_block`, `momentive_pm_cta_image_block`, `momentive_pm_prefooter_block` | Not needed — none of the corresponding fields are ever populated on a press-article |

**Net new code needed** (no existing analog in any migration script):
1. `news-category` meta → real category term lookup/assignment (3-term map, not a Solution-linked term) — also now drives the permalink prefix, see below
2. Original-publication attribution block for `in-the-news` posts (op_name/op_link/op_logo) — pattern confirmed, ready to script
3. Synced-pattern reference for the canonical "About TA" block, appended based on category (press-releases/in-the-news yes, momentive-in-action no) — confirmed rule, ready to script. Find the existing synced pattern (ref 10593) rather than re-creating it
4. `hero_overline`/`hero_subtitle` placement — confirmed, ready to script. `hero_title` needs no code at all (vestigial)

---

## Category-scoped permalinks (implemented separately, 2026-07-27)

Legacy press-articles use a category-prefixed permalink (`/press-releases/{slug}/`, `/in-the-news/{slug}/`, `/momentive-in-action/{slug}/`), with the wrong prefix — or the generic `/newsroom/`/`/press-articles/` prefixes — 301-redirecting to the correct one. This has been implemented in `inc/blog-and-newsroom.php` (added right after `momentive_newsroom_setup()`):

- `MOMENTIVE_PRESS_ARTICLE_CATEGORY_PREFIXES` — explicit term-slug → URL-prefix map (`press-release` → `press-releases`, `in-the-news` → `in-the-news`, `momentive-in-action` → `momentive-in-action`). Explicit rather than reading the term slug directly, because the "Press Release" term's actual slug is singular while the desired URL prefix is plural.
- `momentive_press_article_url_prefix( $post_id )` — resolves a post's prefix from its category terms, falling back to the CPT's own default (`press-releases`) when uncategorized.
- A rewrite rule routes `in-the-news`, `momentive-in-action`, `newsroom`, and `press-articles` prefixes back to the `press-article` query var (the default `press-releases` prefix already resolves via the CPT's own registered rewrite).
- A `post_type_link` filter rewrites the generated permalink to the post's actual category prefix.
- WordPress's own `redirect_canonical()` handles the actual 301 for wrong-prefix/generic-prefix requests automatically — no custom redirect code needed, same as the existing `/guides/` vs `/research-study/` dual-prefix precedent in `inc/guides.php`, which this mirrors.
- Version-stamped `flush_rewrite_rules()` so the new rewrite rule takes effect (stamp `2026-07-27.1`) — **this requires either a page load that fires WordPress's `init` hook, or Daniel manually re-saving Permalinks once, before the new URLs will resolve.**

This is unrelated to the content migration itself and doesn't block writing `migrate-press-articles.php` — but the migration script's generated permalinks (used for any internal cross-links it writes) will automatically pick up the correct category-based prefix once this code is live, with no extra work needed in the migration script itself.

---

## Readiness assessment

**Ready to write `migrate-press-articles.php`.** Every field this CPT actually uses now has a confirmed rebuilt pattern, cross-checked against 7 hand-built posts spanning all 3 categories, both "About TA" states, both outlet-attribution states, the vestigial-`hero_title` case, and a draft post. `display_featured_image_on_page` is confirmed dead (ignore it, always show the image) and `hero_image_alignment` is confirmed as a real, if narrow, conditional style rule (only 2125 triggers it in the current corpus, but the script should implement the condition generically rather than special-case that one ID).

Remaining open item: **2125's missing category** — likely just needs the "Momentive in Action" category assigned by hand; not a migration-script concern either way.

Everything else — category resolution and its permalink implications, the outlet-attribution block, the category-gated "About TA" synced block placement, hero_overline/hero_subtitle placement, the hero_image_alignment conditional, and the (thankfully small) shortcode surface area — is confirmed and ready to script, largely by adapting `migrate-posts.php` as described above.

---

## Coverage list — all 64 legacy press-article posts

`Rebuilt?` = YES for the 7 done so far (3 of them under a drifted ID — see the note after the table). Flag columns: **op** = has `op_name`/`op_link`/`op_logo` (in-the-news attribution); **hero_title** = has `hero_title` set, or `sub` = only `hero_subtitle` set; **about** = has `additional_about_sections`; **sc_cta** = uses the CTA Section shortcode; **rcta** = uses `resource_cta_*`.

| ID | Title | Slug | Category | Status | Rebuilt? | op | hero_title | about | sc_cta | rcta |
|---|---|---|---|---|---|---|---|---|---|---|
| 789 | Community Brands is now Momentive Software | `community-brands-rebrand` | press-releases | publish | | | | Y | | |
| 791 | Momentive strengthens leadership team | `leadership-team-strengthened` | press-releases | publish | | | | | | |
| 793 | Momentive software to showcase at ASAE | `asae-showcase` | press-releases | publish | **YES (as 7810)** | | | | | |
| 795 | Momentive software adds two new senior executives | `new-senior-executives` | press-releases | publish | | | | | | |
| 797 | Big "I" advances member experience with NimbleAMS | `nimbleams-member-experience` | press-releases | publish | | | | | | |
| 799 | Momentive software transforms business operations | `business-operations-transformation` | press-releases | publish | | | | | | |
| 801 | 2024 Association Trends Study | `association-trends-2024` | press-releases | publish | | | | | | |
| 803 | Momentive software announces leadership additions | `leadership-additions` | press-releases | publish | | | | | | |
| 929 | Momentive Software Expands Leadership Bench w/ new CMO | `new-chief-marketing-officer` | press-releases | publish | | | | | | |
| 933 | Momentive Software Acquires Blue Sky eLearn | `blue-sky-elearn-acquisition` | press-releases | publish | | | | | | |
| 1375 | Momentive Software Helps Mission-Driven Orgs (payments) | `financial-management-automated-payments` | press-releases | publish | | | | | | |
| 1503 | Momentive Software Enhances the Donor Experience | `donor-experience-payment-option` | press-releases | publish | | | | | | |
| 1696 | Momentive Software Expands Association Management (Cobalt) | `cobalt-acquisition` | press-releases | publish | | | | | | |
| 1854 | Tech-Savvy Nonprofits are More Optimistic | `nonprofit-trends-2025` | press-releases | publish | | | | | | |
| 1975 | Momentive Software Earns Multiple 2025 TrustRadius Awards | `trustradius-awards-2025` | press-releases | publish | | | | Y | | |
| 2125 | Tirrah Switzer's Journey in Purpose-Driven Leadership | `inspiring-leaders-tirrah-switzer` | momentive-in-action | publish | **YES** | | Y | | | |
| 2604 | Momentive Software Adds New Solution (VolunteerMatters) | `volunteermatters-acquisition` | press-releases | publish | | | | | | |
| 2607 | Insights from The Giving Institute's Summer Symposium | `collective-strength-symposium` | momentive-in-action | publish | | | | | Y | |
| 2693 | Momentive Software Launches Unified Event Management | `unified-event-management` | press-releases | publish | | | | | | |
| 2820 | How Rob Miller Leads with Mentorship and Purpose | `inspiring-leaders-rob-miller` | momentive-in-action | publish | | | Y | | | |
| 3834 | How Jay Greaves' Lifelong Volunteerism Shapes Leadership | `inspiring-leaders-jay-greaves` | momentive-in-action | publish | | | Y | | | |
| 4030 | Gamification: Turning Donor Engagement into a Competition | `donor-engagement-gamification` | in-the-news | publish | **YES** | Y | | | | |
| 4372 | Unlocking Major Gifts | `unlocking-major-gifts` | in-the-news | publish | **YES** | Y | | Y | | |
| 4557 | Momentive Software Appoints Dustin Radtke as Interim CEO | `dustin-radtke-interim-ceo` | press-releases | publish | | | | | | |
| 4655 | How Jody Longshore Blends Conservation and Mentorship | `inspiring-leaders-jody-longshore` | momentive-in-action | publish | | | Y | | | |
| 4755 | Report: Member Loyalty Remains Strong | `member-loyalty-report` | in-the-news | publish | | Y | | | | |
| 5008 | Members Who View Associations as Tech Leaders... | `associations-trends-report` | press-releases | publish | | | | | | |
| 5448 | Amanda Davis' Approach to Empathy and Community | `inspiring-leaders-amanda-davis` | momentive-in-action | publish | | | Y | | | |
| 5551 | Why Associations Need to Invest in Professional Development | `why-associations-need-to-invest-in-professional-development` | in-the-news | publish | | Y | | | | |
| 5657 | Momentive Software Announces Winners of Inaugural Award | `momentive-bring-on-better-award-winners` | press-releases | publish | | | | | | |
| 5757 | The Data Behind Winning Bids | `nonprofits-maximize-auction-revenue` | in-the-news | publish | | Y | | | | |
| 6257 | Momentive Software Surpasses $2 Billion Raised | `2b-raised-2025` | press-releases | publish | | | | | Y | |
| 6499 | Momentive Software Appoints Adam Trenkle as CRO | `momentive-appoints-adam-trenkle-chief-revenue-officer` | press-releases | publish | | | | | | |
| 6665 | How Jason Daiger Helps Revitalize His Community | `inspiring-leaders-jason-daiger` | momentive-in-action | publish | | | sub | | | |
| 7410 | WedgeHR and Momentive Software Partner (AB 503) | `wedgehr-momentive-ab-503-volunteer-compliance` | in-the-news | publish | | Y | | | | |
| 7520 | Momentive Software Unveils MomentiveIQ™ | `momentiveiq-launch` | press-releases | publish | | | | | | |
| 7550 | Momentive Software Accelerates Mission-Driven Growth (Personify) | `personify-acquisition` | press-releases | publish | | | | | | |
| 7662 | Momentive Software Strengthens Executive Bench (CFO/CHRO) | `momentive-software-appoints-cfo-chro` | press-releases | publish | | | | | | |
| 7665 | Momentive Software Acquires Personify | `momentive-acquires-personify-37000-client-platform` | in-the-news | publish | | Y | | | | |
| 7669 | How Cara Dickerson Strengthens Her Community | `inspiring-leaders-cara-dickerson` | momentive-in-action | publish | | | sub | | | |
| 7670 | Momentive Buys Personify, Forging AI-Powered... | `momentive-acquires-personify` | in-the-news | publish | | Y | | | | |
| 8188 | Momentive Software Wins 2026 Stevie® Award | `momentive-software-wins-2026-stevie-award` | press-releases | publish | | | | Y | | |
| 8356 | Momentive's Stevie Win: Service Excellence | `momentive-stevie-award-customer-service-excellence` | in-the-news | publish | | Y | | | | |
| 8570 | Building Belonging: How Technology Enhances... | `belonging-in-fundraising` | in-the-news | publish | | Y | | | | |
| 8854 | Smart, Safe, and Strategic: How Associations... | `smart-safe-strategic-ai-associations` | in-the-news | publish | | Y | | | | |
| 9442 | How Rich Vallaster Leads with Purpose | `inspiring-leaders-rich-vallaster` | momentive-in-action | publish | | | sub | | | |
| 9512 | Momentive Software Research Finds Career Clarity... | `mission-driven-workforce-report` | press-releases | publish | | | | | | |
| 9628 | Career Clarity Doubles Employee Retention | `career-clarity-drives-retention` | in-the-news | publish | | Y | | | | |
| 9631 | Report: Nonprofits Lag on Professional Development | `nonprofit-professional-development-report` | in-the-news | publish | | Y | | | | |
| 9684 | Clear Career Path and Tech Challenging Staff Retention | `nonprofit-career-path-technology-staff-retention` | in-the-news | publish | | Y | | | | |
| **9866** | **How Tawny Kotchko Builds Community** | `inspiring-leaders-tawny-kotchko` | momentive-in-action | publish | **YES** | | sub | | | |
| 9992 | Momentive Software Earns Two Stevie® Awards | `momentive-software-earns-two-stevie-awards` | press-releases | publish | | | | Y | | |
| 10242 | Momentive Software Appoints Ravi Venkatesan as CEO | `momentive-software-appoints-new-ceo` | press-releases | publish | | | | Y | | |
| 10917 | The Hidden Staff Retention Risk | `software-staff-retention-risk` | in-the-news | publish | | Y | | | | |
| 11077 | Momentive Software Deepens Channel Commitment | `new-partner-ecosystem` | press-releases | publish | | | | | | |
| 11191 | Security Notice: Third-Party Vendor Incident | `security-notice-third-party-vendor-incident` | momentive-in-action | **draft** | **YES (as 18275)** | | | | | |
| 11192 | AI Stand-Ins Are Reshaping Virtual Events Economics | `ai-stand-ins-reshaping-virtual-events-economics` | in-the-news | publish | | Y | | | | |
| 11203 | Momentive's New Playbook: Betting on People | `momentive-channel-partner-program` | in-the-news | publish | | Y* | | | | |
| 11229 | How Stephen Koury Built a Community | `inspiring-leaders-stephen-koury` | momentive-in-action | publish | | | sub | | | |
| 11528 | Momentive Software Brings High-Stakes Exam Delivery | `path-lms-advanced-assessments` | press-releases | publish | | | | | | Y |
| 11543 | The Tolerance Trap: Friction Can Block Mission | `nonprofit-tolerance-trap-retention` | in-the-news | publish | | Y | | | | |
| 11638 | Momentive Software Advances Portfolio Alignment | `momentive-careers-and-a2z-events-rebrand` | press-releases | publish | | | | | | |
| 11726 | From Staff Retention to Donor Retention | `staff-retention-donor-retention` | in-the-news | publish | | Y* | | | | |
| 11728 | Inside a VIP Experience | `vip-event-experiences` | in-the-news | publish | | Y* | | | | |

`Y*` = has `op_name`/`op_link` but **no** `op_logo` (3 posts: `momentive-channel-partner-program`, `staff-retention-donor-retention`, `vip-event-experiences`) — the attribution block must degrade gracefully without a logo.

**Note on legacy-ID drift:** three of the seven rebuilt posts now show this same pattern — the legacy post gets re-imported under a new ID on the rebuilt site, same slug: `10242` → `10265`, `793` → `7810`, `11191` → `18275`. Three-for-three is enough to call this a confirmed characteristic of however these posts were rebuilt/re-imported, not a one-off. **Match by slug, not legacy ID, when scripting this migration** (unlike Solutions, which matches by legacy ID because slugs there were unreliable).

**Also present in the legacy export but excluded from the 64-post count above:** none — all 64 `press-article` items in the WXR are accounted for (63 publish + 1 draft).
