# CLAUDE.md — Momentive WordPress Theme

This file captures the architecture, conventions, and decisions baked into the Momentive FSE theme. Read it before touching anything. Source: https://github.com/danheller/momentive/

---

## Stack overview

Custom Full Site Editing (FSE) theme built on the [Frost](https://frostwp.com/) base. Migrated from an Elementor/JetEngine/Crocoblock stack. The governing philosophy: **native WordPress blocks first; custom blocks only when native blocks can't do the job.** ACF Pro is used for per-post metadata, not as a page-builder replacement.

| Layer | Technology |
|---|---|
| Theme engine | WordPress FSE (block templates + template parts) |
| Styling | SCSS compiled via `sass --watch`, no PostCSS |
| Custom blocks | Plain JS using `wp.*` globals (no build step), except `impact-stat` |
| JSX blocks | `@wordpress/scripts` (webpack + Babel) |
| Custom fields | ACF Pro — field groups defined in the ACF UI, version-controlled via `acf-json/` local JSON |
| Sliders | Splide (bundled into `/assets/`) |
| Local dev | Local by Flywheel |
| Hosting | WP Engine |
| Version control | Build output (`blocks/build/`) is committed; `node_modules/` is excluded via `.wpengineignore` |

---

## Directory structure

```
momentive/
├── assets/
│   ├── css/           Compiled CSS — do not edit directly
│   ├── js/            Compiled/hand-written JS
│   ├── sass/          Source SCSS — single entry point: momentive.scss
│   ├── fonts/         Figtree variable font (woff2)
│   ├── images/        SVG backgrounds, sample images, logo
│   └── icons/         SVG icon files — auto-discovered by icon system
│
├── blocks/            Custom blocks — each self-contained
│   ├── {block-name}/
│   │   ├── block.json       Block metadata + attribute schema (apiVersion: 3)
│   │   ├── block.php        Main include: registration + render callback (ACF renderTemplate target)
│   │   ├── editor.js        Editor UI for JS-registered blocks (plain JS, no build step)
│   │   ├── render.php       Front-end render for JS-registered blocks with save()=>null (e.g. icon-list)
│   │   ├── {name}.js        Front-end JS (conditionally enqueued)
│   │   └── {name}.css       Front-end styles (conditionally enqueued)
│   └── build/         Compiled output for JSX blocks (committed to git)
│
├── acf-json/          ACF local JSON — field group definitions, auto-synced by ACF UI
│
├── inc/               Modular PHP — all required from functions.php
│   ├── solutions.php           Solutions CPT + accent color injection
│   ├── products.php            Products CPT + accent color injection
│   ├── testimonials.php        Testimonials CPT
│   ├── faq.php                 FAQ CPT
│   ├── webinars.php            Webinars CPT + form resolution helper
│   ├── whitepapers.php         Whitepapers CPT
│   ├── people.php              People CPT + person_role taxonomy + byline architecture (replaces authors.php)
│   ├── icons.php               Icon system (discovery, sprite output, helpers)
│   ├── check-content-for-block.php  momentive_content_has_block() helper
│   ├── patterns.php            Pattern registration helpers
│   ├── header-footer-edit-buttons.php  Logged-in edit buttons
│   ├── blog-and-newsroom.php   Press Articles CPT + related posts injection + hero_image swap filter+ Blog post label renaming + Blog Settings ACF options sub-page
│   ├── resources.php           Cross-CPT "Resources" query layer (momentive_get_resource_post_types(), momentive_query_resources_for_solution()) + /momentive/v1/resources REST route
│   ├── resource-relevance.php  AI-assisted per-child-Solution relevance tagging (Anthropic API) — feeds resources.php's direct-match tier
│   ├── swoop-heading-cleanup.php  Surgical &nbsp; strip inside is-style-has-swoop headings at save time (regex-scoped, not a full parse_blocks()/serialize_blocks() round-trip)
│   ├── custom-menu-order.php
│   └── disable-comments.php
│
├── patterns/          Block patterns (PHP) + announcement bar
├── parts/             FSE template parts (header, footer, megamenu panels)
├── templates/         FSE block templates
├── migrations/        One-off WP-CLI migration scripts
│   └── exports/       Legacy WXR/CSV exports the scripts read from (moved out of the folder root 2026-07-27 for tidiness; every script's default path was updated to match — see the Migrations section below for the run-command convention, which is unchanged)
├── functions.php      Theme setup, asset enqueuing, block registration
├── theme.json         Design tokens: palette, type scale, spacing
└── style.css          Theme header only
```

---

## SCSS compilation

Entry point: `assets/sass/momentive.scss` — has a full TOC at the top.

```bash
# Watch (development)
sass --no-source-map --watch assets/sass:assets/css --style compressed

# One-time build — directory-wide, same as watch, just without --watch.
# Deliberately not scoped to momentive.scss alone: assets/sass/ also holds
# other independently-compiled top-level files (solutions.scss, gate.scss,
# webinar.scss, the per-page files under pages/, etc.), and a single-file
# command here would silently skip all of them on a manual production build.
sass --no-source-map assets/sass:assets/css --style compressed
```

Do not edit files in `assets/css/` directly. Separate stylesheets:

| File | Purpose | When loaded |
|---|---|---|
| `momentive.css` | Everything | Always |
| `editor-blocks.css` | Editor-only overrides | Block editor only |
| `splide.css` | Slider library | Always (global) |
| `solutions.css` | Solution slide cards | Conditional via `momentive_content_has_block()` |
| `testimonial.css` | Testimonial cards | Conditional |
| `gate.css` | Gated whitepaper layout | Conditional |
| `solutions-dark.css` | Dark-mode Solution color-token overrides | Conditional via `dark_mode` ACF field — see the Solutions CPT section |
| `pages/{slug}.css` | One-off styling for a single rebuilt Page | Conditional, per-page — see below |

**Per-page one-off styles (`assets/sass/pages/{slug}.scss` → `assets/css/pages/{slug}.css`).** Some rebuilt Pages carry a genuinely bespoke visual treatment inherited from the legacy Elementor build (flip-boxes, a one-off gradient, a hover effect used nowhere else). Rather than adding that to `momentive.scss` — where it would ship to every page and post on the site forever for the benefit of exactly one page — each one gets its own SCSS file under `assets/sass/pages/`, named after the page's slug. It compiles automatically via the directory-wide sass commands above (no build config changes needed for a new file), and `inc/page-styles.php` auto-discovers and conditionally enqueues the compiled CSS by checking whether `assets/css/pages/{current page's slug}.css` exists — no registration step, and nothing to clean up beyond deleting the file when the page goes away. Start the file with `@use '../config' as *;` to reach the shared breakpoint/mixin partials, same as every other top-level SCSS file.

If the same one-off treatment turns up on two or more pages, that's the signal to promote it out of `pages/` into a real conditionally-loaded stylesheet keyed to a shared class/block (the same pattern as `solutions.css`/`testimonial.css`/`gate.css`) rather than letting the per-page folder keep growing indefinitely with near-duplicate files.

---

## JavaScript build process

Only `momentive/impact-stat` requires a build step. All other blocks use WordPress globals directly (no build step is the default — see Block structure conventions under Custom blocks).

**Always set `"apiVersion": 3` in `block.json`.** Older API versions trigger editor console warnings and deprecation issues (editor-canvas iframe behavior, asset handling). All current blocks are apiVersion 3.

```bash
npm install          # first-time setup
npm run start        # runs sass --watch + wp-scripts start concurrently
npm run build        # production
```

**Adding a new JSX block:** add entries to `webpack.config.js`, reference compiled paths in `block.json` using `file:../build/{block-name}/` prefix.

Build output (`blocks/build/`) is committed to version control so collaborators don't need to run a build.

---

## Asset enqueuing strategy

- **Global assets:** `momentive.css`, `splide.css`, `site-utils.js`, `momentive.js` — always enqueued via `wp_enqueue_scripts`.
- **Sliders JS (`sliders.js`):** registered but not enqueued. A `render_block` filter checks for CSS classes (`autoslider`, `solutions-slider`, `testimonials-slider`, `news-slider`) and enqueues on demand.
- **Block assets:** each custom block registers its CSS/JS inside `block.php` using `wp_register_*`, then enqueues conditionally via `enqueue_block_assets` hook + `momentive_content_has_block()`.
- **Per-page CSS (`inc/page-styles.php`):** on `wp_enqueue_scripts`, for any `is_page()`, checks whether `assets/css/pages/{slug}.css` exists for the current page and enqueues it if so — no per-page registration needed. See "SCSS compilation" above for the convention this exists to support.
- **Dark-mode Solutions (`inc/solutions.php`):** on `wp_enqueue_scripts`, for any `is_singular('solutions')`, enqueues `solutions-dark.css` (deps: `momentive`) when `dark_mode` resolves true via `momentive_solution_inherited_field()`. Same function also drives the `solution-dark-mode` body class via a `body_class` filter. See the Solutions CPT section for the field and inheritance rule.
- **Reading progress bar:** only on `is_singular('post')`.

### `momentive_content_has_block()`

In `inc/check-content-for-block.php`. Recursively checks a post's content including inside synced patterns (`core/block` refs). Returns `false` on non-singular contexts by default — for archive templates that use a block (e.g. `product-solution-tabs` on the Products archive), check `is_post_type_archive()` explicitly alongside it:

```php
$on_singular = momentive_content_has_block( 'acf/my-block' );
$on_archive  = is_post_type_archive( 'product' );
if ( ! $on_singular && ! $on_archive ) return;
```

---

## CSS custom property architecture

### `--page-accent-color`

Injected on `<body>` via `wp_head` for singular Solutions and Product pages. Sourced from ACF fields:

- **Solutions:** `accent_color` field. Child solutions inherit from their parent (walks up with `wp_get_post_parent_id()`). The ACF field is hidden on child solutions in the editor.
- **Products:** `tint_color` field (the tinted background color). Also injects `--page-icon-color` from `accent_color` field (the icon/brand color). Note: product accent is split into two fields.

Any block on the page can consume `--page-accent-color` in CSS without needing inline styles.

### `--solution`

Injected as an inline style on `<a>` tags rendered by `core/post-terms` blocks showing the `category` taxonomy. Applied via a `render_block` filter in `solutions.php`. Sourced by walking: category term → ACF `related_solution` field → Solution post → `accent_color`. Cached per-request with a static array.

### Global brand tokens

Defined in `:root` inside `momentive.scss` and mirrored in `theme.json`:

```
--accent-color            Primary brand blue (#0078FF)
--light-accent-color      Light blue (#C1E6F7)
--extralight-accent-color
--superlight-accent-color
--button-background       Orange (#f26522)
--alert-background        Dark navy (used in announcement bar, dark cards)
```

`theme.json` tokens are referenced in SCSS via `var(--wp--preset--color--{slug})`.

---

## Post types and taxonomies

### `solutions` (hierarchical)

- URL: `/solutions/{slug}/`
- Supports: title, editor, excerpt, thumbnail, page-attributes (parent/child, order), revisions
- Taxonomy: `solution_tag` (flat, tag-like)
- ACF fields: `accent_color`, `dark_mode` (true/false — see below), `solution_icon` (slug from icon system), `background_image`, `breadcrumb_title`, `solution_order`, `solution_card_label`
- New post template: `patterns/solution-content.php` (applied via CPT template at priority 30)
- Child solutions inherit `accent_color` **and `dark_mode`** from parent for front-end rendering (`momentive_solution_inherited_field()` in `inc/solutions.php`, shared by both fields); both are hidden on child posts in the single-post editor (`momentive_hide_inherited_solution_field()` server-side + `assets/js/solutions-editor.js` for the live no-reload case). The Solutions list table's "Accent Color" column, however, deliberately does **not** walk up to the parent — it shows a value only when the field is actually set on that exact row, so the admin list reflects real per-post data rather than the resolved/inherited display color

**Dark-mode Solutions.** A handful of newer Solution posts (the MomentiveIQ family, e.g. `/solutions/momentiveiq/experience/`) use a reversed color scheme built from the same `patterns/solution-content.php` scaffold rather than a parallel template. The `dark_mode` toggle adds a `solution-dark-mode` body class (`body_class` filter, `inc/solutions.php`) and conditionally enqueues `assets/css/solutions-dark.css` (compiled from `assets/sass/solutions-dark.scss`). That file only reassigns the color-token custom properties already used throughout `momentive.scss` — background, text, icon fill, outline-button colors, accent-tinted backgrounds — scoped under `.solution-dark-mode`; it doesn't restate any layout, so it can't drift out of sync with the light version as the base styles evolve. Same "promote once it's reused" logic as the per-page Pages CSS below: this earned a real shared stylesheet rather than a per-post file because it's a known, reusable variant, not a one-off page quirk. The starter token values in that file haven't been diffed pixel-for-pixel against a live dark-mode page yet — verify and adjust before relying on them.

**"Glow Lights" hero background (`is-style-bg-glow-lights`, 2026-07-28).** The legacy MomentiveIQ hero used a large raster background image (`MIQ-Hero-Lights.webp`, `background-size:100% auto`) to show two soft magenta glow blobs along the top-left/top-right corners. Replaced with a pure-CSS `core/group` block style (registered on `core/group` in `functions.php`; styled in `solutions-dark.scss`, scoped under `.solution-dark-mode` since no light-mode version of this treatment exists yet) — two `radial-gradient()` circles blurred via `filter:blur()`, positioned with `:before`/`:after`, sized/colored via `--glow-color`/`--glow-size`/`--glow-blur`/`--glow-max` custom properties on the element (easy to art-direct without re-exporting an image asset). Expected to be combined with `.hero-background` (already provides `overflow:hidden` + the stacking context the glow's `z-index:0` needs). A small scroll-linked parallax (`Utils.initGlowParallax`, `assets/js/site-utils.js`) nudges each blob vertically via a `--glow-parallax` custom property the SCSS already reads (defaults to `0px`, so the glow is static without JS); skipped entirely under `prefers-reduced-motion`.

**Gradient text-clip headline (`is-style-gradient-heading`, 2026-07-28).** Same dark-mode-only pattern as Glow Lights above — registered on `core/heading` in `functions.php`, styled in `solutions-dark.scss` under `.solution-dark-mode`. The gradient's blue endpoint (`#3393FF`) doesn't match any existing brand token (`--accent-color` is `#0078FF`, `--light-accent-color` is `#C1E6F7`), so it's a one-off scoped as `--gradient-heading-color` on `body.solution-dark-mode` — alongside the file's other color-token overrides — rather than promoted to a global `:root` token in `momentive.scss`, since it's specific to this one text treatment, not a general brand color. The legacy site's version also carried `-webkit-linear-gradient()`/`-moz-linear-gradient()` duplicate declarations ahead of the standard `linear-gradient()` — dropped as dead weight, not a real fallback: those old vendor-prefixed gradient functions predate (and never supported) the `to bottom right` directional syntax, so they'd have been no-ops in any browser old enough to need them. `-webkit-background-clip`/`-webkit-text-fill-color`, on the other hand, are still genuinely required — `background-clip: text` remains prefix-only in most engines today — so both stay; the unprefixed `background-clip: text` is included alongside as forward-compat for the (currently few) engines that support it unprefixed.

### `product` (flat/non-hierarchical)

- URL: `/products/{slug}/`
- Taxonomy: `product_type` (private; terms: `active-product`, `orphan-product`) + shared `category`
- Shared solution categories: children of the built-in "Solutions" category term. Restricted in the ACF category picker via `acf/fields/taxonomy/query/name=product_category`; default category panel removed from editor via JS
- ACF fields: `tint_color` (hero tint), `accent_color` (icon color), `product_icon`, `product_order`, `summary`, `background_image`, `logos` (repeater), `product_logo_*` (endorsed/unendorsed, white/color variants)
- New post template: `patterns/product-content.php`
- Product Marquee excludes Orphan products via `momentive_product_marquee_query_args` filter
- **Redirect-to-Solution alias (`inc/products.php`):** some legacy products exist only to populate product lists/archives (their ACF fields — summary, logo, icon, colors — are what drives the Marquee/tabs/sidebar cards) but their real content lives on a Solution page; a visitor should never land on the product's own singular page. `redirect_to_solution` (Product Settings, Post Object → `solutions`) is the flag — its mere presence means "alias to that Solution," no separate toggle. `momentive_get_product_link( $product_id )` is the single resolver (used by `product-marquee`, `product-solution-tabs`, and `linked-products` instead of calling `get_permalink()` directly) so on-site clicks go straight to the Solution with no redirect hop; a `template_redirect` hook 301s any direct hit on the product's own URL (stale external links, indexed search results, bookmarks) to the Solution — a permanent redirect since this is a deliberate, lasting architecture decision, not a temporary state. An `edit_form_after_title` admin notice reminds editors when a product they're editing is alias-only. Deliberately **not** paired with the `orphan-product` term — the legacy site includes some of these aliased products in the Marquee and other lists, so whether an alias still appears there stays an independent per-product call.

### `testimonial`

- ACF fields: `solution_family` (relationship to category term), `author_name`, `author_description`, `author_photo`, `testimonial_type`
- Note: the rebuilt CPT stores content/author in `testimonial_content`, `testimonial_author_name`, `testimonial_author_description` (the keys the case-study migration reads/writes when creating-and-referencing testimonials). CPT registration name is `testimonials` (plural) in the DB.

### `faq`

- ACF fields: `solution_family`
- Used by the accordion block in query mode

### `case-study`

- URL: `/case-studies/{slug}/`, archive preserved at `/case-studies/` for inbound links. CPT slug is hyphenated (`case-study`) per convention; legacy type was `case_studies`.
- Migrated from the legacy site (151 published + 5 drafts) via `migrations/migrate-case-studies.php` — see Migrations.
- **Architectural principle:** structured legacy data stays structured, rendered by a block (stats → `stat-columns`, features → `icon-list`, products → post-level `linked_products` + `linked-products` block). Only genuinely prose sections (intro, challenge/solution, results, about) become free-form paragraph/heading/list blocks. Spacing lives in SCSS via theme.json presets carried by the pattern, never inline per-post.
- **Categories:** solution categories via the native category panel (multi-select), scoped to children of the "Solutions" parent — ~4 posts have multiple (ECS has 4). Not a single-select ACF field.
- ACF fields (Case Study Settings, `group_6a421df4548b3`): `linked_products` (post-level source of truth), `breadcrumb_title`. Sidebar features/stats live in their blocks, not post fields.
- New post template: `patterns/case-study-content.php` (full scaffold: breadcrumb, hero with logo/title/featured-image/download button, two-column body, sticky sidebar with linked-products + Key Features + CTA).
- Page chrome that varies per post: hero **logo image** (`small-logo`, from legacy `case_study_logo` attachment), **download PDF button** (from legacy `case_study_file`), both sideloaded during migration; omitted when absent.

### `webinar` (`inc/webinars.php`)

- URL: `/webinars/{slug}/`
- Legacy CPT slug: `webinars` (plural); rebuilt: `webinar` (singular)
- Taxonomy: `webinar_type_tax` (private; terms: `upcoming`, `on-demand`)
- ACF fields (Webinar Settings, `group_6a3a318255bf0`): `webinar_type` (select: upcoming|on-demand), `is_series` (true/false), `webinar_date` (date_picker, Ymd format), `webinar_end_date`, `webinar_time_start` (time_picker, H:i:s), `webinar_time_end`, `webinar_timezone` (text), `form_upcoming` (textarea — HubSpot embed), `form_ondemand` (textarea — HubSpot embed), `video_embed_code` (textarea — Wistia embed), `presenters` (post_object → `people`, multiple), `hero_image` (image, **return_format: array** — optional singular-view override)
- New post template: `patterns/webinar-content.php`
- Migrated from the legacy site (149 posts) via `migrations/migrate-webinars.php` — see Migrations

**`momentive_resolve_webinar_form( $post_id )`** (in `inc/webinars.php`): returns the correct HubSpot embed code based on live status — reads `form_upcoming` when the webinar date is in the future, `form_ondemand` otherwise. No cross-field fallback. Used by the `acf/hubspot-form` block render template when no block-level embed override is set.

**Featured image vs. `hero_image`:** the featured image (`_thumbnail_id`) is the archive card image. `hero_image` is an optional ACF override for the singular-view hero — when set, a `render_block` filter in `blog-and-newsroom.php` swaps the `core/post-featured-image` block output on singular `webinar` (and `post`, `press-article`) pages. Leave `hero_image` empty to use the featured image in both places. The migration sets them independently: `_thumbnail_id` from the legacy `_thumbnail_id` field, `hero_image` from `resource_hero_image` only when they differ.

### `whitepaper` (`inc/whitepapers.php`)

- URL: `/whitepapers/{slug}/`
- Legacy CPT slug: `whitepapers` (plural); rebuilt: `whitepaper` (singular)
- ACF fields (Whitepaper Settings, `group_6a45de7a50be6`): `hero_image` (`field_6a45de7b50be7`, image, return_format: array — optional singular-view hero override, same pattern as webinars)
- New post template: `patterns/whitepaper-content.php`
- Migrated from the legacy site (69 posts) via `migrations/migrate-whitepapers.php` — see Migrations
- CSS: `assets/css/gate.css` (conditionally enqueued via `momentive_content_has_block`)

**Gated vs. not-gated layout:** determined by whether the legacy post had a `hubspot_form_code` value. Gated posts (majority) get a two-column layout: left column has title, description, and checklist; right column has featured image and HubSpot form. Not-gated posts get a different right column: featured image, checklist, and download button. The layout variant is determined per-post during migration and baked into the block content.

**HubSpot embed stored inline in block data**, not in post-level ACF fields (unlike webinars). The `acf/hubspot-form` block's `data` object carries the embed code directly. See the `wp_slash()` gotcha in the Migrations section — this is why the embed code must survive the `wp_update_post` pipeline intact.

### `press-article` (Newsroom)

- URL: `/newsroom/{slug}/`
- Archive: `/newsroom/`
- Shares `single.html` template via body class filter (`.single-article`) in `blog-and-newsroom.php`
- ACF fields: `hero_image`
- Related posts injected below post content via `render_block` filter

### `post` (Blog)

- Renamed to "Blog" in admin via `inc/rename-posts-to-blog.php`
- ACF fields: `breadcrumb_title`, `cta_button` (link field), `post_author_ref` (Post Object → `people` CPT, restricted to the `author` role)

### `people` CPT (`inc/people.php`)

Unified profile type for leadership, blog authors, and webinar presenters — replaces the former separate `team` CPT, `authors` CPT, and the webinar `presenter` repeater field. One human = one People post, even when they hold several roles (a leader who also authors and presents is a single profile).

- URL: `/people/{slug}/` — `public => true`, so every profile has a real permalink (SEO-visible, shareable). `has_archive => false` (no native listing; the Our Team page is hand-built from blocks).
- Supports: title, editor, excerpt, thumbnail, revisions
- Post title = display name; featured image = headshot; `post_content` = bio (migrated leaders also have a "Did You Know" group block appended)
- Taxonomy: `person_role` (flat, **non-exclusive** — templates must not assume one role per person)
- ACF fields (Person Settings group): `job_position`, `linkedin_url`, `first_name`, `last_name`, `linked_user` (see byline architecture below). No `display_order` field — team ordering is handled by hand-picking/ordering Person blocks in the editor, not a meta field.

**`person_role` is a fixed, locked vocabulary.** Three seeded terms (`leader`, `author`, `presenter`), inserted once via `momentive_seed_person_roles()` (priority 20, after taxonomy registration). The taxonomy's `manage_terms`/`edit_terms`/`delete_terms` caps are set to `do_not_allow` via a `register_taxonomy_args` filter, which turns the editor meta box into a fixed checklist and hides the Roles admin submenu. **Adding a fourth role is a one-line code change** in the `$roles` array — intentionally not an editor task. (Note: `do_not_allow` also hides the screen from admins; switch to `manage_options` if admins should manage terms.)

### `team` CPT and `authors` CPT (retired)

Both consolidated into `people`. Migrations preserved in `migrations/` (see below). The `team` CPT registration and the `authors` CPT registration should be removed once the migration is confirmed on production.

> **Operational note:** `/people/{slug}/` returns 404 until rewrite rules are flushed (the CPT's rewrite is registered but WP only compiles rules on flush). After any change to the `people` rewrite slug, re-save **Settings → Permalinks** once, or rely on the version-stamped one-time `flush_rewrite_rules()` in `people.php` (bump the stamp to re-trigger). Don't flush on every `init` — it's expensive.

### Solution ↔ category term relationship

Products, testimonials, and FAQs are organized via built-in `category` taxonomy terms that are children of a "Solutions" parent category. Each category term has an ACF `related_solution` field (post_object → solutions CPT). Helper functions in `solutions.php`:

```php
momentive_get_solution_term_map()  // returns array<term_id, solution_post_id>
get_solution_color_for_term( $term_id )  // walks term → solution → accent_color
get_terms_for_solution( $solution_id )   // reverse lookup: solution → term IDs
get_solutions_with_products()            // solution IDs that have linked terms
```

All cached statically per-request.

### Resources (cross-CPT query layer)

The legacy site merges several content types into one "Resources" collection with its own archive: Blogs, Case Studies, Events, Guides & Research, Infographics, Interactive Tools, Product Overviews, Testimonials, Toolkits, Videos, Webinars, and Whitepapers. Not all of those are migrated yet (see "Pending CPT migrations" below), but `post`, `case-study`, `webinar`, `whitepaper`, and `infographic` already are, and all five already share the `category` taxonomy — the same relationship the Solution ⇄ category mapping above already establishes. No new taxonomy or field was needed to query them by Solution.

`inc/resources.php`:

```php
momentive_get_resource_post_types()                          // string[] — single source of truth for which post types count as a "resource"; filterable via `momentive_resource_post_types`
momentive_resolve_resource_term_ids_for_solution( $solution_id ) // int[] — get_terms_for_solution(), falling back to the parent Solution's term(s) if the given post has none of its own
momentive_query_resources_for_solution( $solution_id, $args ) // WP_Query — two-tier resolver, see below
momentive_get_relevant_resource_ids( $solution_id )           // int[] — Tier 1: AI-tagged direct matches (see Resource Relevance below)
momentive_get_category_fallback_resource_ids( $solution_id, $exclude_ids ) // int[] — Tier 2: category-term fallback, tops up remaining slots
momentive_filter_resources_by_freshness( $ids )               // int[] — drops stale non-evergreen items past momentive_resource_freshness_cutoff_months()
```

Child solution pages are the primary place `momentive/solution-resources` is used, and child solutions typically have no category term linked directly — only the top-level parent Solution does. `momentive_resolve_resource_term_ids_for_solution()` walks up one level via `wp_get_post_parent_id()` when the given Solution resolves to no term, the same single-level fallback `accent_color` already uses. This lives as its own function rather than being folded into `get_terms_for_solution()` itself, so other callers of that function (`product-solution-tabs`, etc.) keep their existing behavior — parent inheritance is opt-in per consumer, not a global change.

`momentive_query_resources_for_solution()` is a direct PHP call (no REST round trip) used by the `momentive/solution-resources` block for a single server-rendered grid on Solution pages — the same "query once, server-side" shape as `product-solution-tabs`. **Two-tier resolution** (curate-with-fallback, same shape as `linked-products`): Tier 1 pulls resources the AI relevance tagger matched directly to *this specific* child Solution (`momentive_get_relevant_resource_ids()`); Tier 2 tops up any remaining slots from the broader category-term match (`momentive_get_category_fallback_resource_ids()`, the original category-only behavior, now the fallback rather than the only mechanism). Both tiers pass through `momentive_filter_resources_by_freshness()`, which drops non-`evergreen` items older than `momentive_resource_freshness_cutoff_months()` (filterable) so a Solution page doesn't surface a 3-year-old blog post as "relevant." If filtering leaves nothing, the block renders nothing — same "empty is not an error" convention as `hubspot-form`/`back-link`.

#### Resource Relevance (AI-assisted per-Solution tagging, `inc/resource-relevance.php`)

Replaces the legacy site's manual "Featured Resources" admin screen (a category-level, hand-curated picker editors found unusable) with automatic, per-child-Solution relevance tagging — finer-grained than the category fallback, and zero ongoing editor effort.

- **Trigger:** `momentive_maybe_schedule_relevance_tagging( $post_id, $force = false )` hooks `save_post` for every resource post type, computes `momentive_resource_content_hash()` (title + content + excerpt), and only schedules a tag pass (`wp_schedule_single_event`, off the synchronous save path) when the hash changed since the last tag — so routine saves that don't touch substance don't re-spend an API call.
- **Tagging call:** `momentive_tag_resource_relevance_now( $post_id )` builds the candidate list via `momentive_get_taggable_child_solutions()` (all published child Solutions — the same tier the migration's sibling-grid logic operates on) and asks the LLM via `momentive_ask_llm_for_relevant_solutions( $post, $candidates )`, a raw `wp_remote_post` call to the Anthropic Messages API (no SDK/Composer dependency). The prompt requires a JSON-array-only response; returned IDs are hard-filtered through `array_intersect()` against the real candidate list as a hallucination guard before being written to the `relevant_solutions` ACF field.
- **Config:** API key from a `wp-config.php` constant (or filter — never hardcoded/committed); model from `momentive_relevance_model()` (constant-overridable).
- **Manual override:** editors can hand-edit `relevant_solutions` (post_object, multiple) or flip `evergreen` (true/false, exempts a resource from the freshness cutoff) on any resource post — the AI tag is a starting point, not a lock.
- **Bulk action:** `momentive_retag_relevance` registered on every resource post type's list table, for re-tagging after a prompt/model change without waiting for individual saves.
- **ACF field group:** "Resource Relevance" (`acf-json/group_6a95a10cf001.json`) — `relevant_solutions` (`field_6a95a10cf002`) and `evergreen` (`field_6a95a10cf003`); location rules OR across `post|case-study|webinar|whitepaper|infographic|guide`.
- **Backfill script (`migrations/backfill-resource-relevance.php`):** the save_post trigger and the bulk action only cover posts touched *after* this feature existed — this WP-CLI script covers everything published before it, across all 6 resource post types. Calls `momentive_tag_resource_relevance_now()` directly rather than scheduling it (the `wp_schedule_single_event` deferral only exists to keep a slow API call off an editor's save, which doesn't apply to a CLI loop), reimplements the same manual-override/unchanged-hash gating inline, and sleeps 1s between live API calls. Same dry-run-by-default convention as every other script in this folder; `live` mode refuses to run at all without `MOMENTIVE_ANTHROPIC_API_KEY` configured (rather than silently no-opping post-by-post), but dry-run exercises every other part of the script (querying, gating, logging) without needing a key.

**`GET /momentive/v1/resources`** (registered in `inc/resources.php`) is the multi-CPT REST route `resource-filters/filters.js` used to flag as a planned-but-missing feature (see its former "NOTE" comment on true multi-type querying). It runs one `WP_Query` across several post types so merge, sort, and pagination stay correct, and returns a flat item shape (`id`, `type`, `type_label`, `title`, `excerpt`, `link`, `date`, `featured_image`, `categories`) rather than mirroring any single CPT's REST shape. `categories` entries match the `{ name, link, tag_color }` shape `assets/js/site-utils.js`'s `renderCategoryLink()` already expects, so the client reuses that helper unchanged. `filters.js` calls this route instead of a core `/wp/v2/{type}` endpoint whenever more than one `post_type` checkbox is active in the filter bar; a single selected type still hits core's endpoint directly. (This REST route is unrelated to the relevance tagger — it's a plain multi-CPT listing, not solution-scoped.)

**Deliberately excluded:** testimonials, FAQs, products, and press-article (Newsroom). Press-article/Newsroom was never part of the legacy Resources collection. Testimonials appear in the legacy list, but the CPT's solution association is currently broken (see Known limitations) and a quote card doesn't fit the title/excerpt/link shape every other resource type shares — folding it in would need a card-shape decision beyond this task's scope.

---

## Byline architecture (People ↔ Users)

The blog byline is **not** `post_author`. On this site `post_author` is frequently a developer who imported or added the post on someone else's behalf, so it's treated purely as provenance ("who touched the row in WP"). The canonical byline is the `post_author_ref` ACF field (Post Object → `people`, restricted to the `author` role) on each post.

**Link direction: `linked_person` on the user, not `linked_user` on the person.** A "User Settings" ACF field group (`location: user_form == all`) gives each WP user an optional `linked_person` Post Object field pointing at a People profile. This direction is deliberate and was reversed from an earlier `linked_user`-on-person design:

- The dominant write path is a small team of ~4 developers publishing under a shared "Momentive Software" byline. Many users → one person is exactly what's needed, and the user-side field models it natively (each user points to one person; multiple users may point to the same person).
- The reverse (`linked_user` on the person) made that shared-byline case impossible without a multi-value field, which reintroduced an ambiguous-lookup problem. The user-side field has no such collision.
- Set the field's return format to **Post Object** (single value), which enforces one-person-per-user at the field level. `msw_resolve_linked_person()` normalizes ID / object / array shapes defensively regardless.

**Seeding `post_author_ref`** (both in `inc/people.php`):

- `acf/load_value/name=post_author_ref` — **prefill on new posts.** When a linked user opens a new post (status `auto-draft`), the byline field is pre-populated with their linked person so the default is *visible* in the editor. Gated to empty values + auto-draft status only, so it never overrides a deliberately-set or deliberately-cleared byline on existing posts.
- `acf/save_post` (priority 20) — **save-time backstop.** If a post is saved with an empty byline, default it to the current user's linked person. Catches contexts where `load_value` didn't run (programmatic creation, etc.).
- A user with no `linked_person` gets no default (empty byline they fill manually) — the intended behavior for unlinked accounts.

**Admin columns** (both in `inc/people.php`):

- People list table → "Linked Accounts" column: lists every user whose `linked_person` points at that profile (one query primed per screen, grouped in PHP — a serialized relationship value won't match a bare-ID `meta_value` query, so don't per-row query it).
- Users list table → "Linked Person" column: the inverse view; flags a stale link (person deleted / not a `people` post) in red.

**Role filter:** the People list table has a "Filter by role" dropdown (`restrict_manage_posts` + `parse_query`), filtering by `person_role` slug.

---

## Custom blocks

### Block structure conventions (read before adding a block)

Every custom block is a self-contained folder under `blocks/{block-name}/`. The house conventions, established across the People work and the Case Study build:

- **No build step by default.** Blocks are plain JS using `wp.*` globals (or pure PHP render for ACF blocks). A build step (`@wordpress/scripts`, `src/` → `build/`) is used **only when JSX is genuinely required** — currently just `momentive/impact-stat`. Reach for a build only when the editor UI can't be expressed reasonably without JSX (e.g. a rich custom inspector); otherwise stay no-build.
- **`block.json` is the manifest, `apiVersion: 3`.** Always set `"apiVersion": 3` — older API versions emit editor console warnings and hit deprecations (iframe/editor-canvas behavior, asset handling). `"category": "momentive"`. Declare `supports` explicitly (`anchor`, `html: false`, etc.). For ACF render blocks, include the ACF hook: `"acf": { "mode": "preview", "renderTemplate": "block.php" }`.
- **`block.php` is the main include file** for every block — it's the file the theme loads. For ACF blocks it does double duty: registration (`register_block_type( __DIR__ )` on `init`), conditional asset registration/enqueue, AND the render template body (ACF's `renderTemplate` target). For JS-registered blocks it handles registration + enqueue and points `editor_script`/`render_callback` at the right files.
- **Conditional asset enqueue.** Register CSS/JS with `wp_register_*` inside `block.php`, then enqueue on `enqueue_block_assets` guarded by `is_admin()` + `momentive_content_has_block( 'momentive/{name}' )`. Don't enqueue globally.
- **`{name}.css`** uses theme.json preset variables for spacing/typography/color (`var(--wp--preset--spacing--small)`, `var(--wp--preset--color--secondary)`) — no hardcoded dimensions where a preset exists. Include an editor `.is-placeholder` style.
- **ACF field groups** are defined in the ACF UI and version-controlled via `acf-json/` local JSON (ACF auto-writes the JSON on save). Location rule: `block == momentive/{name}`. Render reads fields with `get_field()`; renders nothing on the front end when empty; shows a `.is-placeholder` div in the editor (`$is_preview`).
- **Typed signatures.** PHP uses typed returns (`: void`, `: array`, `: int`) and `??`/`?:` — valid on this stack even if a linter flags them.

**ACF block render gotcha (learned the hard way on linked-products):** an ACF block needs its field keys present in the inline `data` object in the block comment, or ACF can't bind the block's fields and the block renders **blank on the front end while still working in the editor preview**. When emitting ACF blocks programmatically (migrations, patterns), include the `{"name":...,"data":{"field_key":"value",...},"mode":"preview"}` scaffold. Also: inside an FSE template, blocks render **outside the main query loop**, so `get_the_ID()` is unreliable for resolving the host post — use the `$post_id` ACF passes into the render template instead.

### No-build blocks (plain JS)

| Block | Notes |
|---|---|
| `momentive/accordion` | Static or query (FAQ CPT) mode. Three style variants: default, categorized, icon. `closeOthers` and `openFirst` options. `@starting-style` animation on panels. `core/details`, `core/accordion*`, `core/icon` are unregistered to avoid ambiguity. |
| `momentive/breadcrumbs` | Uses ACF `breadcrumb_title` override if set. Options for home link and separator. |
| `momentive/post-byline` | Author photo, name, last-updated date (only when >24h after publish), reading time (`ceil(words/220)`). Falls back to WP author if ACF field empty. |
| `momentive/post-cta-button` | Renders the CTA button in the blog post hero. Resolution order: (1) per-post `cta_button` ACF link field override; (2) `blog_hero_button` Link field on the post's solution category term; (3) `site_wide_blog_hero_button` Link field on the Blog Settings options page. Outputs nothing if all three are empty — safe to include unconditionally in templates. Logic lives in `momentive_resolve_post_cta_button()` in `block.php`. |
| `momentive/resource-filters` | AJAX filter + sort bar for archives. Proximity-targets adjacent Query Loop (no ID needed). Load More replaces pagination. REST endpoint map in `filters.js`. When more than one `post_type` checkbox is active, `filters.js` calls the `/momentive/v1/resources` route (`inc/resources.php`) instead of a core `/wp/v2/{type}` endpoint, so results from several post types merge, sort, and paginate correctly — a single selected type still hits core's endpoint directly. |
| `momentive/table-of-contents` | Parses H2/H3, sticky, scroll-spy. Collapses when list >50% viewport height. Expand/collapse state in `sessionStorage`. |
| `momentive/social-share` | Copy link (clipboard API + `execCommand` fallback), LinkedIn, X, Facebook. Popups use constrained window. |
| `momentive/icon-shuffle` | Animated icon grid. |
| `momentive/testimonial` | Renders a testimonial card. Integrates with Query Loop. |
| `momentive/product-marquee` | Two auto-scrolling rows (Splide AutoScroll). Row 1 scrolls left, row 2 right. Pauses on hover. Pulls from Products CPT; filtered to `active-product` type via `momentive_product_marquee_query_args` hook. Wordmark image fallback when no logo image is set. |
| `momentive/product-solution-tabs` | Tabbed grid of products grouped by Solution. Tabs derived automatically from `get_solutions_with_products()` — no manual curation per instance. Deep-linkable via URL hash. Mobile dropdown with "All" option. Enqueuing checks both `momentive_content_has_block()` and `is_post_type_archive('product')`. |
| `momentive/hubspot-form` | ACF block. Two modes: standard embed (paste embed code), and two-step (email capture inline → full form in modal). Modal appended to `document.body` to avoid stacking context issues with sticky nav. **Form resolution order:** (1) block-level `hubspot_embed_code` field if set; (2) post-level form fields via `momentive_resolve_webinar_form()` — so the correct form surfaces automatically when a webinar transitions from upcoming to on-demand. The legacy `form_source` select field was removed; the block now auto-detects. |
| `momentive/back-link` | ACF block. A back-navigation link with configurable label and URL (fields: `label`, `url`). Used in webinar post content as the first block in the left column. Renders nothing when both fields are empty. |
| `momentive/megamenu-panel` | InnerBlocks-based panel. Allowed children: `core/columns`, `core/group`. Paired with flat WordPress nav and separate FSE template parts per panel (`parts/megamenu-*.html`). |
| `momentive/person` | ACF block. Single-person card (headshot + name + position) for the Our Team page; native blocks (columns/grid) handle the layout and ordering of multiple instances. Person chosen via an ACF Post Object field (`person`, restricted to `people`, intentionally **not** role-restricted so it's reusable for presenters/bylines later). The card is a real `<a>` to the person's permalink; `view.js` intercepts the click to open the profile in a native `<dialog>` lightbox (progressive enhancement — no JS just navigates to the profile page). Deep-linkable: `/our-team/#person-{slug}` auto-opens that profile, and opening a profile writes the hash via `replaceState`. Backdrop tinted from `--wp--preset--color--superlight-accent` via `color-mix` to match the site. |
| `momentive/person-position` | ACF block. Renders the current queried person's `job_position`. Used in the `single-people` template hero (fills the `.person-position` slot). Resolves the person via `get_the_ID()`; placeholder shown on the editor canvas. |
| `momentive/person-linkedin` | ACF block. Renders a LinkedIn icon link for the current queried person (`linkedin_url`). Used in the `single-people` template hero. Same `get_the_ID()` resolution pattern as `person-position`. |
| `momentive/icon-list` | **JS-registered** block (not ACF) for the Case Study "Key Features" sidebar. A repeater of `{ iconSlug, text }` rows edited in the inspector, each row using the shared **visual icon picker** (`window.momentive.IconPicker` — same control as `icon-block`/`icon-link`, so you see/click the glyph rather than picking a slug from a text dropdown). `save() => null`; front end rendered by PHP `render.php` via the sprite `<use href="#icon-{slug}">`. Icon treatment (no shape, no background, secondary color) lives in `style.css`. `showHeading` attribute (the Case Study sidebar supplies its own "Key Features" `<h2>`, so the migrated blocks set `showHeading:false`). This block was made JS-registered (vs. the no-build ACF default) specifically for the visual picker — with 130+ icons and a teammate maintaining during leave, the click-the-glyph UX justified the deviation. Trade-off: migration emits it as serialized block markup (`<!-- wp:momentive/icon-list {"items":[...]} /-->`) rather than a field-to-field ACF write. |

### ACF blocks (PHP render template)

| Block | Notes |
|---|---|
| `acf/solution-slide` | Single solution card for use inside a Query Loop / Splide slider. |
| `acf/hubspot-form` | See above. |
| `acf/product-solution-tabs` | See above. |
| `acf/person` | See `momentive/person` above. |
| `acf/person-position`, `acf/person-linkedin` | Field blocks for the `single-people` template hero. See above. |
| `momentive/linked-products` | ACF block. Renders related products as logos linking to product pages, pulling the unendorsed logo + permalink from each Product post (so logos stay in sync — change a product's logo once, every instance updates). Heading is a block attribute. Product selection: a **post-level** `linked_products` field is the source of truth (set on the Case Study), with an optional block-level override; render prefers the block field, falls back to the post-level field resolved via the ACF-provided `$post_id` (NOT `get_the_ID()` — see the FSE gotcha above). Named generically (not "case-study-products") for reuse on solution pages etc. |
| `momentive/stat-columns` | ACF block. Repeater (`stats`: `stat_value` + `stat_description`); renders each value **verbatim as a string** — no number parsing, no count-up animation (39% of legacy case-study stat values can't be parsed: ">1 million", "~50%", "24-fold", "#1", typos). Handles 0–4 count gracefully (hidden at 0, centered at 1). Deliberately separate from `momentive/impact-stat`, which does animated count-up with prefix/number/suffix and is wrong for this free-form data. |
| `acf/webinar-status` | ACF block. Renders the upcoming/on-demand status badge for a webinar post. Reads `webinar_type` field and resolves live status against `webinar_date`. The renderTemplate file (`blocks/webinar-status/webinar-status.php`) drives itself off `get_the_ID()` rather than the ACF-provided `$post_id`, which is what lets `patterns/story-card.php` reuse it directly via `get_template_part()` for its webinar top-label case (see "Block patterns" below) — it works correctly inside any secondary loop that's already called `the_post()`, not just a webinar's own singular page. |
| `acf/webinar-schedule` | ACF block. Renders formatted date, time range, and timezone for a webinar. Reads `webinar_date`, `webinar_end_date`, `webinar_time_start`, `webinar_time_end`, `webinar_timezone`. Renders nothing when date is empty. |
| `acf/webinar-form-heading` | ACF block. Renders an optional heading above the HubSpot form in the sidebar. Field: `heading_override` (text). Renders nothing when empty — safe to include unconditionally in the webinar template. |
| `momentive/webinar-presenters` | ACF block. Renders presenter cards (headshot + name + job_position) from the `presenters` ACF field (post_object → `people`). Block attributes: `layout` (grid or list), `show_headshots` (true/false). Falls back gracefully when `people` posts have no featured image. |
| `momentive/solution-resources` | ACF block. Automatic grid of "resources" (blog posts, case studies, webinars, whitepapers, infographics, guides — see `momentive_get_resource_post_types()`, `inc/resources.php`) for the current Solution, via `momentive_query_resources_for_solution()`. **Two-tier**, replacing the original category-only version: Tier 1 is AI-tagged direct matches to this specific child Solution (see Resource Relevance above); Tier 2 tops up remaining slots from the broader category-term match (the original mechanism, falling back to the parent Solution's term(s) when the current child has none of its own — see `momentive_resolve_resource_term_ids_for_solution()`). Both tiers are freshness-filtered (non-`evergreen` items past the cutoff are dropped). No manual curation field, unlike `linked-products`' curate-with-fallback pattern — a resource's relevance is decided on the resource itself (AI tag + its own category panel), not from the Solution page. Fields: `heading` (text, default "Resources"), `count` (number, default 6). Reuses `patterns/story-card.php` for each card with no override — its default per-post-type top label (webinar status badge / press-article category / post type label otherwise) is already correct for a mixed-type grid like this one. Resolves the host Solution via the ACF-provided `$post_id`, not `get_the_ID()` (see the FSE gotcha above). |

### JSX build block

| Block | Notes |
|---|---|
| `momentive/impact-stat` | Animated stat counter. `IntersectionObserver` fires count-up at 25% threshold. Respects `prefers-reduced-motion`. Attributes: `statPrefix`, `statNumber`, `statSuffix`, `statLabel`, `accentColor`, `animationDuration`, `animate` (the "Count up" toggle, default `true`). Source in `blocks/impact-stat/src/`, compiled to `blocks/build/impact-stat/`. **Deprecation gotcha:** `save.js` serializes `animate` into a `data-animate` HTML attribute; any instance published before that attribute existed has HTML frozen in `post_content` without it. `deprecated.js`'s `v1` entry has no `migrate()` function, so opening one of those in the editor silently "recovers" it (attribute falls back to the schema default, `true`) but writes nothing back to the DB — the post keeps re-triggering the same silent recovery on every open until it's explicitly resaved once. `migrations/report-impact-stat-usage.php` is a read-only WP-CLI report that finds every instance across all post types and flags exactly which ones are missing `data-animate` in their stored HTML (i.e. still need that one resave) — don't trust the block comment's JSON `animate` key alone to tell you the live state, since Gutenberg omits any attribute matching its schema default from the comment JSON, so a correctly-working default-`true` block legitimately shows no `animate` key at all. |

---

## Icon system

Defined in `inc/icons.php`. SVG files live in `assets/icons/*.svg` — adding a file registers it automatically.

Key functions:

```php
momentive_get_available_icons()          // returns [ slug => 'Label' ] for all files in assets/icons/
momentive_get_icon_path( $slug )         // absolute path to SVG file
momentive_parse_svg_file( $slug )        // returns [ 'viewBox' => ..., 'inner' => ... ]
momentive_output_svg_symbols( $slugs )  // echoes hidden <svg><symbol> sprite markup
momentive_use_icon( $slug )             // selective registration (enqueues only what's needed)
momentive_render_icon( $slug )          // outputs <svg><use href="#icon-{slug}"></svg>
```

Icon pickers on ACF fields (`solution_icon`, `product_icon`) are populated via `acf/load_field/name=*` filters and show a live preview of the selected SVG via `acf/render_field/name=*`.

**Sourcing missing BoxIcons.** Most icon slugs referenced by migrated legacy content follow the [BoxIcons](https://boxicons.com/) naming convention (`bx-*` regular, `bxs-*` solid) that this project's `assets/icons/` files already match. When a migration's unresolved-icon log names a slug with no corresponding SVG file, check whether it's a genuine BoxIcon first — `npm install boxicons --no-save` pulls the full set locally, and the matching file is a plain copy from `node_modules/boxicons/svg/regular/{slug}.svg` or `.../solid/{slug}.svg` into `assets/icons/`. Not every referenced slug is a real BoxIcon, though: some are non-BoxIcon glyphs the design bespoke authored for a specific solution-family visual (recognizable by a `sol-` prefix, not `bx-`/`bxs-`) that need sourcing elsewhere (legacy media exports, hand-drawn), and a few real-looking BoxIcons simply don't exist in the library (e.g. `bxs-power-bi` — no Power BI icon ships in BoxIcons at all; substitute a generic chart icon like `bx-pie-chart`/`bx-bar-chart`, or source a Power BI mark from a brand-icon set like Simple Icons instead — already done for this project, see below).

---

## Block styles

Registered in `functions.php` via `register_block_style()`. All styled in `momentive.scss` using `.is-style-{name}`.

| Block | Style name | Effect |
|---|---|---|
| `core/group` | `bg-dark` | Dark navy background, white text |
| `core/group` | `bg-light` | Superlight accent background |
| `core/group` | `bg-gradient` | Blue-to-transparent gradient |
| `core/group` | `bg-dots` | Dot pattern SVG background |
| `core/group` | `bg-rings` | Rings + shapes SVG background |
| `core/group` | `bg-ellipse` | Ellipse gradient (page heroes) |
| `core/group` | `purple-seafoam-wash` | Purple/seafoam gradient wash |
| `core/group` | `cloudy-sunset` | Warm sunset gradient |
| `core/group` | `bg-glow-lights` | Two blurred magenta glow blobs, dark-mode Solutions only (see Dark-mode Solutions section) |
| `core/heading` | `eyebrow` | Small-caps accent color label |
| `core/heading` | `has-swoop` | Animated SVG underline on `<strong>` child |
| `core/heading` | `gradient-heading` | White-to-blue gradient text clip, dark-mode Solutions only (see Dark-mode Solutions section) |
| `core/paragraph` | `eyebrow` | Same as heading eyebrow |
| `core/paragraph` | `uppercase` | Uppercase label, no accent color |
| `core/columns` | `outline` | Bordered card columns |
| `core/columns` | `columns-reverse` | Reverses column order on mobile |
| `core/list` | `no-disc`, `column-checks`, `circle-checks` | List style variants |
| `core/image` | `shadow`, `round`, `rounder` | Image style variants |
| `core/button` | `superlight` | Blue pill button |
| `core/navigation-link` | `button` | Orange pill CTA (used for "Get Your Demo" in header nav) |
| `core/quote` | `shadow-light`, `shadow-solid`, `quote` | Quote card variants |

WP's built-in "rounded" image style is unregistered via `wp.blocks.unregisterBlockStyle()` on `wp.domReady` (with a 2-second timeout to allow WP to register it first).

---

## Block patterns

PHP files in `patterns/`. **No manual registration is needed to make a new pattern file show up in the editor's pattern inserter.** WordPress core auto-discovers any `patterns/*.php` file via its file-header comment block (`Title:`, `Slug:`, `Categories:`, optional `Post Types:` to scope it) — dropping a correctly-headered file in `patterns/` is sufficient on its own. `inc/patterns.php` only registers the two *pattern categories* themselves (`momentive-page`, `momentive-pricing`) so they exist as groupings in the inserter UI — it is not a per-pattern registry, and forgetting to add a new pattern there is never the reason a pattern fails to appear. If a newly-added pattern isn't showing: check the file header is well-formed (a missing/malformed `Slug:` or `Title:` silently drops the whole file), check `Post Types:` isn't scoping it out of the post type you're editing, and note the editor does cache the pattern list — a hard refresh or new editor load after adding the file is sometimes needed.

Key patterns:

| Pattern | Notes |
|---|---|
| `announcement-bar.php` | Rendered on `wp_body_open` (priority 5). Cookie-based dismissal (sitewide `/` path). Configure via `momentive_announcement_bar_args` filter or disable by commenting out the `add_action`. |
| `product-content.php` | Default template for new Product posts (28KB — the most complex pattern). |
| `solution-content.php` | Default template for new Solution posts. |
| `blog-article-content.php` | Blog post body layout. |
| `press-article-content.php` | Press article body layout. |
| `case-study-content.php` | Default template for new Case Study posts. Full scaffold: breadcrumb bar, hero (logo image slot, post-title, post-featured-image, download button), two-column `post-layout`, sticky sidebar (`linked-products` + "Key Features" `icon-list` + CTA). Applied via CPT template (same `init` hook pattern as webinar/product). The migration emits this same structure with per-post data filled in. |
| `related-posts.php` | "Recommended for you" section, injected by `blog-and-newsroom.php`. |
| `story-card.php` | Reusable post card (title, excerpt, date, featured image) used by `related-posts.php`, `momentive/solution-resources`, and `resource-filters` (as the template client-side JS matches). Top label default, per post type: `webinar` → live status badge (same markup `acf/webinar-status` renders, via `get_template_part( 'blocks/webinar-status/webinar-status' )`); `press-article` → first category name, unlinked; everything else → the post type's own singular label (`post`'s is rewritten to "Blog" by `inc/rename-posts-to-blog.php`, so no special case is needed for it). Accepts optional `$card_top_label` (literal override, `''` suppresses the label) and `$card_heading_level` (default `3`) via `get_template_part()`'s `$args` parameter. |

---

## FSE templates and parts

| File | Route |
|---|---|
| `templates/index.html` | Fallback |
| `templates/home.html` | Homepage |
| `templates/page.html` | Pages |
| `templates/single.html` | Blog post singles |
| `templates/single-people.html` | Person profile pages (`/people/{slug}/`). Hero with eyebrow + `post-title` + `acf/person-position` + `acf/person-linkedin`, then two-column `post-content` / `post-featured-image`. The same profile content also appears in the Person block lightbox, but the page and lightbox deliberately differ in structure (the page has hero framing the modal shouldn't), so they are **not** rendered from a shared function. |
| `templates/404.html` | 404 |
| `parts/header.html` | Sitewide header (sticky; offset by `--announcement-bar-height`) |
| `parts/footer.html` | Sitewide footer |
| `parts/megamenu-products.html` | Products megamenu panel |
| `parts/megamenu-solutions.html` | Solutions megamenu panel |
| `parts/megamenu-who-we-serve.html` | Who We Serve megamenu panel |
| `parts/megamenu-why-momentive.html` | Why Momentive megamenu panel |
| `parts/megamenu-resources.html` | Resources megamenu panel |

---

## Megamenu architecture

Flat WordPress Navigation block in the header + separate FSE template parts (`parts/megamenu-*.html`) per panel. Navigation items trigger panel swaps via JS. Key behaviors:

- Panel opens immediately when nav is closed; 120ms delay when switching between open panels (avoids flicker)
- Height animates via JS (not CSS height: auto, which can't be transitioned)
- `@starting-style` used for CSS entry transitions
- CSS grid with a hard-stop `linear-gradient` for the shaded right column

---

## Blocked/unregistered native blocks

To avoid ambiguity with custom equivalents, these native blocks are unregistered on every page:

- `core/details`
- `core/accordion`, `core/accordion-item`, `core/accordion-heading`, `core/accordion-panel`
- `core/icon`

Three-pronged removal approach (required because standard methods aren't reliable for these):
1. `allowed_block_types_all` filter
2. `block_editor_settings_all` filter on `__unstableBlockDefinitions`
3. `wp.blocks.unregisterBlockType()` in `wp.domReady` (polls until block types are registered, then unregisters)

---

## Query filters

```php
// Blank post_excerpt → return empty string (no fallback to full content on cards)
add_filter( 'get_the_excerpt', ... );

// Query Loop with class `has-featured-images-only` → meta_query filter
add_filter( 'query_loop_block_query_vars', ... );
```

The `solutions-sibling-slider` variant of this same filter (used on Solution pages to exclude the current post from its own "sibling solutions" Query Loop) resolves the current post via `get_queried_object_id()`, not `get_the_ID()` — more reliable inside a `query_loop_block_query_vars` filter callback, which can run outside the main loop context where `get_the_ID()` is unreliable (the same FSE gotcha documented elsewhere in this file for ACF render templates).

**Gotcha when targeting a Query Loop with a CSS class for a `render_block`/`query_loop_block_query_vars` filter:** the class must be applied to the **inner template block nested inside the Query block** (e.g. via the block's Advanced panel on the actual `core/post-template` or wrapping group inside the loop), not to the outer `core/query` block wrapper itself — applying it to the outer Query block silently no-ops the filter with no error. This cost real debugging time once; check which block the class landed on first if a Query Loop filter "isn't working."

---

## ACF options pages

Options pages are registered in PHP (not through the ACF UI) so that `parent_slug` can be set to any WordPress menu slug. The field group that populates the page is still created in the ACF UI as normal.

**Registration pattern** (in an `inc/` file, on the `init` hook):

```php
add_action( 'init', function () {
    if ( ! function_exists( 'acf_add_options_sub_page' ) ) return;
    acf_add_options_sub_page( [
        'page_title'  => 'Blog Settings',
        'menu_title'  => 'Blog Settings',
        'menu_slug'   => 'momentive-blog-settings',
        'parent_slug' => 'edit.php',   // nests under "Blog" in the dashboard sidebar
        'capability'  => 'manage_options',
    ] );
} );
```

**Hook:** use `init`, not `acf/init`. The `acf/init` hook is unreliable for options page registration in ACF Pro versions before 6.3.

**Field value access:** `get_field( 'field_name', 'option' )` — pass the string `'option'` as the second argument.

**Nesting under existing menus:** set `parent_slug` to the WordPress menu slug of any existing admin menu item (`edit.php` for Blog/Posts, `edit.php?post_type=product` for Products, etc.). To create a standalone top-level entry use `acf_add_options_page()` instead.

**Registered options pages:**

| Slug | Parent menu | Purpose |
|---|---|---|
| `momentive-blog-settings` | Blog (`edit.php`) | Site-wide blog hero CTA fallback button |

---

## ACF field groups

Field groups are created and edited in the ACF UI. ACF automatically writes a JSON file to `acf-json/` on every save — that directory is committed and serves as the version-controlled source of truth. To add or change a field group, edit it in the ACF UI; the JSON updates itself.

| Group | Location | Key fields |
|---|---|---|
| Category Settings | `taxonomy == category` | `related_solution` (post_object → solutions), `blog_hero_button` (link — button shown in blog post hero for posts in this category; read by `momentive_resolve_post_cta_button()`) |
| Blog Settings | `options_page == momentive-blog-settings` | `site_wide_blog_hero_button` (link — site-wide fallback hero button shown on all blog posts with no category-specific button set) |
| HubSpot Form | `block == acf/hubspot-form` | `hubspot_embed_code` (textarea — block-level override; leave empty to use post-level form fields), `two_step` (true/false). The former `form_source` select was removed; form origin is now auto-detected. |
| Post Settings | `post_type == post` | `breadcrumb_title`, `cta_button` (link), `post_author_ref` (post_object → `people`, restricted to `author` role), `hero_image` |
| Person Settings | `post_type == people` | `job_position`, `linkedin_url`, `first_name`, `last_name`, `linked_user` (legacy/unused after byline reversal — confirm before removing) |
| User Settings | `user_form == all` | `linked_person` (post_object → `people`, restricted to `author` role; **return format: Post Object**) |
| Testimonial Settings | `post_type == testimonial` | `solution_family` (taxonomy), `author_name`, `author_description`, `author_photo`, `testimonial_type` (select), `related_case_study` |
| FAQ Settings | `post_type == faq` | `solution_family` |
| Product Settings | `post_type == product` | `solution_family`, `summary`, `breadcrumb_title`, `product_order`, `background_image`, `tint_color` (hex), `logos` (repeater), `product_icon` (select), `accent_color` (hex), `product_logo_*` (image — endorsed/unendorsed × color/white), `redirect_to_solution` (`field_6a9f1e2d3001`, post_object → `solutions` — see Redirect-to-Solution alias above) |
| Solution Settings | `post_type == solutions` | `breadcrumb_title`, `accent_color` (hex), `solution_icon` (select), `background_image`, `solution_card_label`, `solution_order` |
| Case Study Settings | `post_type == case-study` (`group_6a421df4548b3`) | `linked_products` (post-level source of truth for the sidebar block), `breadcrumb_title`. Stats/features live in their blocks, not post fields. |
| Linked Products Block | `block == momentive/linked-products` (`group_6a429f79214af`) | `heading`, `show_heading`, `linked_products` (block-level override). Field keys: heading `field_6a429fb9316b6`, show_heading `field_6a42a00e316b7`, override products `field_6a42aac112ead`. The post-level `linked_products` (Case Study Settings) is `field_6a429f79316b5`. |
| Stat Columns Block | `block == momentive/stat-columns` | `stats` repeater (`stat_value` `field_6a42c6c8357d9`, `stat_description` `field_6a42c6ef357da`; repeater `field_6a42c667b17bc`) |
| Webinar Settings | `post_type == webinar` (`group_6a3a318255bf0`) | `webinar_type` `field_6a3a3182ba777`, `is_series` `field_6a3e1db41ee80`, `webinar_date` `field_6a3a31bcba778`, `webinar_end_date` `field_6a3a31dbba779`, `webinar_time_start` `field_6a3a31f9ba77a`, `webinar_time_end` `field_6a3a323bba77b`, `webinar_timezone` `field_6a3a3249ba77c`, `form_upcoming` `field_6a3a3321ba77f`, `form_ondemand` `field_6a3a3356ba780`, `video_embed_code` `field_6a3ef54a65cd6`, `presenters` `field_6a3edd7da2c1f`, `hero_image` `field_6a3eddd24103c` (return_format: array) |
| Back Link Block | `block == momentive/back-link` (`group_6a44a4078d0f6`) | `label` `field_6a44a408f79e0`, `url` `field_6a44a420f79e1` |
| Webinar Form Heading Block | `block == acf/webinar-form-heading` (`group_6a44a695407f9`) | `heading_override` `field_6a44a695e649d` |
| Webinar Presenters Block | `block == momentive/webinar-presenters` (`group_6a448a68cf996`) | `layout` `field_6a448a69ebb4b`, `show_headshots` `field_6a4542d50b10a` |
| Whitepaper Settings | `post_type == whitepaper` (`group_6a45de7a50be6`) | `hero_image` `field_6a45de7b50be7` (image, return_format: array) |
| Solution Resources Block | `block == momentive/solution-resources` (`group_6a7c1e2f4a001`) | `heading` `field_6a7c1e304a002` (text, default "Resources"), `count` `field_6a7c1e324a003` (number, default 6) |
| Resource Relevance | `post_type == post\|case-study\|webinar\|whitepaper\|infographic\|guide` (OR, `group_6a95a10cf001`) | `relevant_solutions` `field_6a95a10cf002` (post_object, multiple, targets `solutions`, return_format id — AI-tagged, hand-editable), `evergreen` `field_6a95a10cf003` (true_false — exempts from freshness cutoff). See `inc/resource-relevance.php`. |


---

## Developer experience

- **Header/Footer edit buttons:** visible to logged-in editors on hover. Links to template part in Site Editor. (`inc/header-footer-edit-buttons.php`)
- **"Blog" label:** "Posts" renamed throughout admin. (`inc/rename-posts-to-blog.php`)
- **Custom menu order:** dashboard sidebar menu reordered. (`inc/custom-menu-order.php`)
- **Comments disabled:** all comment UI, menus, and dashboard widgets removed. (`inc/disable-comments.php`)

---

## Design decisions and rationale

**"Resources" reuses the existing Solution ⇄ category relationship instead of a new taxonomy or field.** Every candidate resource type (`post`, `case-study`, `webinar`, `whitepaper`, `infographic`) already supports the `category` taxonomy, and a Solution is already linked to a category term via that term's `related_solution` field — the exact mechanism `product-solution-tabs` uses for Products. Introducing a parallel "solution" field/taxonomy for resources would have duplicated that relationship and created two sources of truth that could drift; `momentive_query_resources_for_solution()` and the `/momentive/v1/resources` REST route both call the same `get_terms_for_solution()` lookup instead.

**Solution-page resource grid queries automatically; no manual curation field.** Unlike `linked-products` (curated field with automatic fallback), `momentive/solution-resources` has no picker. Whether a resource "belongs" to a Solution is already decided on the resource itself, via its own category panel — adding a second, Solution-side override would let the two disagree with no clear tiebreaker. This mirrors `product-solution-tabs`, which is equally automatic for Products.

**Resources deliberately excludes testimonials, FAQs, products, and press-article/Newsroom.** The legacy "Resources" collection nominally includes testimonials, but that CPT's solution association is currently broken (see Known limitations), and a testimonial's natural display is a quote card, not the title/excerpt/link shape every other resource type shares. FAQs and products have their own dedicated solution-scoped fields and displays already (`product-solution-tabs`, the FAQ accordion) and aren't "browsable resource cards." Press-article/Newsroom was never part of the legacy Resources collection.

**Native blocks first.** Custom blocks exist only where native blocks genuinely can't do the job (animated counters, complex AJAX filters, product marquees, megamenu panels). Resisting the urge to build custom blocks for layout keeps the editor accessible to non-developers.

**When to use blocks and when to use fields** Fields-based editing is a lossy compression of the block editor. You take a rich, flexible editing model and reduce it to a fixed set of parameters someone predicted in advance. That works fine when the prediction is accurate and complete — but every variation that wasn't anticipated either can't be done, requires a new field (developer time), or requires a workaround. The block editor, by contrast, is composable — you combine primitives to get complexity, rather than pre-enumerating all possible complex states. To use an analogy, a form with fields is a fixed menu. The block editor is a kitchen. A fixed menu works great for a simple diner order. But a complex meal requires either an enormous menu or a kitchen.

**Using WordPress theme.json settings and utility classes, not a framework.** Many of the available block settings, like font size, dimensions, etc., are set in the theme.json file. In WordPress this is a good way to do a lot of what CSS frameworks and utility classes would do otherwise. WordPress treats theme.json settings as more of a "first-class citizen," since it provides sidebar panels, sliders, shading within the block editor, and other improved interface features. So far this has seemed like a WordPress-native approach to putting together a custom framework rather than using an out-of-the-box one, and that's why it's been used so far in palce of Tailwind, Bootstrap, etc. Theme.json settings are added as :root CSS variables, so we've been using those in the global CSS instead of redefining them, where possible.

**Build output committed.** `blocks/build/` is in version control so content editors and collaborators don't need Node.js. Only developers who modify JSX blocks need to run the build.

**ACF field groups in local JSON, not PHP.** Field groups are defined in the ACF UI and version-controlled via `acf-json/` — ACF writes the JSON automatically on every save, so definitions stay in git without a manual export step or verbose PHP. The `inc/acf-groups.php` approach (PHP `acf_add_local_field_group()`) was retired in favour of this.

**`--page-accent-color` on `body`.** Injecting the accent color at the body level (rather than per-block) means any block anywhere on a product/solution page can reference it with `var(--page-accent-color)` in CSS, with no PHP coordination needed per block.

**Splide globally enqueued.** Sliders appear on multiple page types (homepage, newsroom, product pages). The performance cost of always loading Splide is lower than the complexity of conditionally loading it across many templates.

**HubSpot modal appended to `document.body`.** The modal needs `z-index` above the sticky nav, which creates a stacking context. Appending to body breaks out of any stacking context in the page content.

**`core/accordion` triple-unregistration.** WordPress's native accordion blocks can't be removed via the standard `allowed_block_types_all` filter alone — they re-register themselves via `__unstableBlockDefinitions`. A three-pronged approach (`allowed_block_types_all` + `block_editor_settings_all` filter + JS `unregisterBlockType` on `wp.domReady`) is required.

**Swoop underline needs a local stacking context, unconditionally.** The `.is-style-has-swoop` SVG is drawn with `z-index: -1` inside a `<strong>` that only sets `position: relative` (no `z-index` of its own), so that `-1` resolves against whatever ancestor establishes the nearest stacking context. That context used to exist only as a side effect of the `is-style-bg-dots` / `is-style-bg-rings` / `is-style-ellipse-*` style rule (`> * { position: relative; z-index: 1; }`) applied to `.hero-background`'s child. A hero built with a plain custom background (the block's native Background color/gradient support, no `is-style-bg-*` class) had no such context, so the swoop SVG fell back to the page root and rendered *behind* the hero's own opaque background — invisible, even though the DOM/JS side (`is-ready`/`is-visible` classes, correct `<path>`) was working fine. Fixed by giving `.hero-background > *` `position: relative; z-index: 1` unconditionally in `momentive.scss`, independent of background style.

**Swoop headings strip stray `&nbsp;` at save time — via a scoped regex, not a full block reserialization.** Content pasted in from the legacy site often carries invisible non-breaking spaces (`&nbsp;` entity or the raw U+00A0 character) that neither the visual nor code editor surfaces — only the browser inspector shows them. Inside a `.is-style-has-swoop` heading, an nbsp glues adjacent words (and the swooped `<strong>`, which is `white-space: nowrap`) into one unbreakable run for the line-breaking algorithm; on a large/long heading with no valid space left to break at, the browser falls back to breaking mid-word (e.g. "Every member" rendering as "Every mem" / "ber" across two lines). `inc/swoop-heading-cleanup.php` hooks `wp_insert_post_data` and fixes this with a `preg_replace_callback` matching `<(h[1-6])([^>]*\bis-style-has-swoop\b[^>]*)>(.*?)<\/\1>`, normalizing both nbsp forms to a plain space only inside the matched inner content — scoped narrowly so intentional nbsp elsewhere (e.g. hero paragraphs) is left untouched. **This is a corrected implementation** — an earlier version ran the whole post through `parse_blocks()` / `serialize_blocks()` to do the same replacement, which caused a live "Block contains unexpected or invalid content" validation error on save for *any* post containing a swoop heading, because a full parse/serialize round-trip doesn't perfectly preserve every block's original markup byte-for-byte. The regex approach touches only the swoop heading's own HTML and leaves the rest of `post_content` completely untouched, eliminating that risk category entirely. If a similar "normalize some text inside one block type, save-time" need comes up again, prefer this scoped-regex pattern over a `parse_blocks()`/`serialize_blocks()` round-trip on the full document.

**Unified People CPT over separate team/author/presenter types.** One human can hold multiple roles (leader who authors, author who presents). Separate types guaranteed duplicate records and divergent data for the same person. A single `people` CPT with a non-exclusive `person_role` taxonomy models reality; presenters/leaders who are external (and shouldn't have WP accounts) are handled as profiles without a `linked_person`, which a users-table-based approach couldn't represent cleanly.

**Byline link lives on the user (`linked_person`), not the person.** Driven by the actual write pattern: a few developers publishing under a shared "Momentive Software" byline (many users → one person). See "Byline architecture" above for the full rationale. The earlier `linked_user`-on-person design was reversed because it couldn't represent the shared byline without reintroducing an ambiguous lookup.

**Profile permalink + lightbox, not lightbox-only.** The old site's team profiles existed only inside a JS modal — no permalink, no anchor, not crawlable. Because `people` is a public CPT, every profile already has a real server-rendered page; the Person block links to it and progressively enhances to a lightbox. Fixes SEO and deep-linking while keeping the lightbox UX. Whether leader profiles should be indexed is still an open editorial/SEO-team question, but the architecture supports either answer (a one-line `noindex` later if not).

**Person block and profile page are NOT a shared renderer.** They legitimately differ in structure (the page has hero framing — eyebrow, ellipse background, display title — that the modal shouldn't). Forcing both through one function would add branching that defeats the purpose. Instead, the page is native blocks + two tiny field blocks (`person-position`, `person-linkedin`); the lightbox keeps its own self-contained markup in the Person block.

---

## Migrations (`migrations/`)

One-off WP-CLI scripts (`wp eval-file`). The People consolidation ran in three passes; **order matters** (presenters before leaders, so the shared-name merges resolve correctly):

1. **Authors → People** (`role: author`). In-place `set_post_type()` on the already-imported `authors` posts (preserves IDs, thumbnails, byline relationships).
2. **Presenters → People** (`role: presenter`). Parsed from the webinar `webinar_presenter` serialized repeater; deduped by name; description (`job_position`) resolved to the most-recently-published webinar's value; name+credential pairs merged keeping the credential (e.g. "Tirrah Switzer, CAE"). Photos sideloaded from the live site, deduped by `_msw_source_url` attachment meta.
3. **Team → People** (`role: leader`). Bio → `post_content`; "Did You Know" field appended as a `superlight-accent` group block (Word-paste `<span>` wrappers stripped). Merges (e.g. Dustin Radtke, already author + presenter) **overwrite** content with the richer team bio; fill ACF fields only if empty.

Scripts are idempotent; merges are append-only on roles; photos dedupe by source URL across passes. A name-matching guard (`msw_clean()`) strips stray CDATA so re-runs don't create duplicates.

**Still pending:** webinar → presenter *relationship* field — see Webinar migration below and Known limitations.

### Case Study migration (`migrate-case-studies.php`)

Migrates the legacy `case_studies` CPT (151 published + 5 drafts) to the rebuilt `case-study` CPT. **Runs on the REBUILT site**, reading legacy content from the **WXR export file**, not the database (the legacy posts don't exist in the rebuilt DB). Per post it: strips Word `<span>` cruft from prose; maps products (CCT-ID → name → rebuilt Product post); copies stats verbatim into `stat-columns`; normalizes feature icons into an `icon-list` block; runs testimonial create-and-reference; sideloads logo/hero/PDF media; assembles the full page scaffold; preserves original post dates.

**Run modes (important — `wp eval-file` quirks):**
- `wp eval-file` does **not** accept `--flags`; they error as "unknown parameter." Flags are **positional**: `wp eval-file migrate-case-studies.php live limit=6`. Positional args arrive as a script-scope `$args` variable (NOT `$GLOBALS['args']`), captured at file scope and passed into the parser.
- **Dry-run is the default**; writing requires an explicit `live` token (or `MOMENTIVE_LIVE=1`). This is deliberate — a mis-parsed/forgotten flag once caused an accidental full live run, so the safe default now prevents that.
- **Must run as an admin user: `--user=<login-or-id>`.** Safe SVG gates SVG handling on user capability; WP-CLI has no user by default, so SVG logo sideloads fail with "you are not allowed to upload SVG files" without `--user`.
- Overridable constants: `MOMENTIVE_LEGACY_WXR`, `MOMENTIVE_UPLOADS_BASE`, `MOMENTIVE_PRODUCT_CSV`, `MOMENTIVE_ICON_DIR`.

**Key migration behaviors and findings:**
- **Word cruft:** legacy WYSIWYG fields carry many MS-Word span variants — `data-contrast`, `data-ccp-props`, `data-ccp-charstyle`, and class-only spans (`NormalTextRun`, `TextRun`, `EOP`, `SCXW…`, `BCX…`, spelling/comment spans). The stripper removes any span with a Word fingerprint (data-attr OR class) plus styleless spans, keeping inner text and hyperlinks. (~3,677 spans removed across the corpus, all 221 links preserved.) Leftover spans cause "Invalid content" errors in the editor.
- **Prose → blocks:** prose fields are converted to the right block per element — `<p>`→paragraph, `<h2-6>`→heading, `<ul>/<ol>`→list, `<blockquote>`→quote, `<table>`→table (97 lists + 23 h3s in the corpus would be silently dropped by a paragraph-only extractor). Prose is verbatim from legacy after Word-stripping — not rewritten.
- **Icons:** legacy `feature_icon` values have a `box-` prefix, stripped mechanically (`box-bxs-user-badge` → `bxs-user-badge`). All 132 distinct icons resolve against the sprite manifest; unresolved slugs are written as-is and logged. The migration does NOT do `bxs-→bx-` fallback — a few coverage posts were hand-corrected and the migration writes legacy faithfully (those are a known by-hand re-fix list).
- **Products:** CCT-ID → name (from Product Settings CSV) → rebuilt Product post by title. Matching is exact title first, then **normalized** (lowercase, non-alphanumerics stripped) to absorb the company's inconsistent spacing ("Crowd Wisdom" vs "CrowdWisdom", "Path LMS" vs "Path"), then a unique-candidate containment fallback. Unresolved names roll up into an end-of-run summary. Products write to the **post-level** `linked_products` field; the sidebar `linked-products` block is emitted **with its ACF data scaffold** (field keys present) or it renders blank on the front end.
- **Testimonials:** create-and-reference with fuzzy dedup. Match an existing `testimonial` CPT post by **normalized quote text** (reliable key; author names are abbreviated with collisions). ~50 matched existing, ~80–86 created new. New posts apply the **name-shortening convention**: full first name (incl. multi-word, e.g. "Mary Jo S.") + last initial; drop titles (Dr.) and post-comma credentials (CFO, DPA); drop middle initials ("Kevin R. Callahan" → "Kevin C."); group attributions kept verbatim; empty author → CPT post with blank author. Failure mode is "harmless duplicate," never silent wrong content. Name shortening was reviewed against a generated CSV before the run.
- **Media:** attachment ID → URL map built from the WXR itself (`_wp_attached_file` + uploads base), so no separate 14MB media export needed. Logo + hero (set as featured image) + PDF sideloaded, deduped by `_momentive_source_url` meta. Failures don't block the post write: the slot is left empty (logged) or, for PDFs, the original external URL is kept in the button. Two logos (CAALA, Berkeley Rep) aren't in the export → logged unresolved. Many TripBuilder-hosted PDFs fail to sideload (remote host's outdated TLS / `cURL error 35`) → kept as external links and summarized.
- **Dates:** original `post_date`/`post_date_gmt` set via the post shell; `post_modified`/`post_modified_gmt` set via direct `$wpdb->update()` *after* all writes (because `wp_insert/update_post` always force modified to "now"). `patch-case-study-dates.php` restores dates on already-migrated posts without re-running the full migration (slug-matched, dry-run default).
- **Breadcrumb title:** migrates `organization_name` into `breadcrumb_title` (the legacy site shows the org name in the breadcrumb), falling back to legacy `short_title` then post title.
- **Idempotency:** upserts by slug, so re-running updates in place rather than duplicating. Created posts/testimonials are stamped with `_momentive_migration_run` (a run timestamp) for safe rollback identification. In practice, restoring from a pre-migration backup is the clean reset.

**Coverage validation:** 6 representative posts (ECS, Plimoth/MIP, Ewald/YM, United Way/GiveSmart, CAALA/Events, VECCS/YM Careers) exercise every field and edge case; generated block markup was diffed byte-for-byte against the hand-built rebuilt versions. Remaining diffs were all known hand-edits (an icon `bxs-`→`bx-` swap, a `4,000+`→`4,000` stat, a deliberately-omitted logo) — the migration writes legacy faithfully and those stay a short by-hand list.

This migration establishes the **WP-CLI-from-WXR** pattern as the standard for moving content (dramatically faster than manual rebuilds): parse the legacy export → transform → write to the rebuilt DB, dry-run by default, per-item logging, end-of-run summaries of anything unresolved.

### Webinar migration (`migrate-webinars.php`)

Migrates the legacy `webinars` CPT (149 posts) to the rebuilt `webinar` CPT. Same WP-CLI-from-WXR pattern as the Case Study migration. Two export files are required next to the script:

| File | Contents |
|---|---|
| `momentivesoftware.webinars.current.2026-07-01.xml` | `webinars` posts (149) + attachments (355) — source for posts, presenter data, media |
| `momentivesoftware.assets.current.2026-07-01.xml` | `assets` posts (168) — source for `video_embed_code` (Wistia embeds) |

**Run modes:** same positional-flag pattern as Case Study — dry-run by default, `live` token required to write. Must run with `--user=<admin>` (Safe SVG capability gate). Overridable via `MOMENTIVE_WM_LEGACY_WXR`, `MOMENTIVE_WM_ASSETS_WXR`, `MOMENTIVE_WM_UPLOADS_BASE`.

**Key migration behaviors:**
- **Images:** legacy `_thumbnail_id` → featured image (archive card); legacy `resource_hero_image` → `hero_image` ACF field only when it differs from `_thumbnail_id` (when they're the same, featured image handles both and `hero_image` is left empty). Both sideloaded, deduped by `_momentive_source_url`.
- **HubSpot form:** single legacy `hubspot_form_code` field → `form_upcoming` or `form_ondemand` based on `webinar_type`. Upcoming webinars that later transition to on-demand continue to work without a manual update (the render template reads the correct field via `momentive_resolve_webinar_form()`).
- **Video embed code:** read from the assets WXR by slug. Exact slug match first; normalized containment fallback (handles cases where asset slug has a `webinar-`/`video-` prefix or the webinar slug has extra words). ~126 exact matches, ~8 containment matches, ~15 unmatched (logged).
- **Presenters:** legacy `webinar_presenter` serialized repeater → `people` CPT post IDs via name matching. Unmatched names create new People posts with `presenter` role. Deduped by normalized name within and across sessions.
- **Insights / checklist / quote blocks:** legacy structured fields assembled into a superlight-accent group block. Social-share placement: inside the insights group when no presenter section follows; outside (after presenters) when presenters are present.
- **Dates and excerpts:** original `post_date`/`post_modified` preserved (same `$wpdb->update()` pattern as Case Study). `excerpt:encoded` from WXR written as `post_excerpt`.
- **Idempotency:** upserts by slug; posts stamped with `_momentive_migration_run` for rollback identification.

**`patch-webinar-images-excerpts.php`:** targeted patch for already-migrated posts without requiring a full re-run. Fixes two issues from the initial run: (1) sideloads the correct `_thumbnail_id` attachment as featured image for posts where thumbnail ≠ hero image; clears the redundant `hero_image` override for posts where they were the same; (2) writes `post_excerpt` from the WXR where currently empty. Requires `--user=<admin>`; dry-run by default.

### Whitepaper migration (`migrate-whitepapers.php`)

Migrates the legacy `whitepapers` CPT (69 posts) to the rebuilt `whitepaper` CPT. Same WP-CLI-from-WXR pattern as webinars. One export file required next to the script:

| File | Contents |
|---|---|
| `momentivesoftware.whitepapers.current.2026-07-01.xml` | `whitepapers` posts (69) + attachments — source for posts and media |

**Run modes:** same positional-flag pattern — dry-run by default, `live` token required to write. Must run with `--user=<admin>`.

**Key migration behaviors:**
- **Gated vs. not-gated layout:** determined by whether `hubspot_form_code` is present in the legacy post. Gated posts get a two-column layout with the HubSpot form in the right column. Not-gated posts get the featured image, checklist, and download button in the right column instead.
- **HubSpot embed inline in block data:** unlike webinars (which store form code in `form_upcoming`/`form_ondemand` post-level ACF fields), whitepapers store the embed code directly inside the `acf/hubspot-form` block comment's `data` object. Field-key-direct format is used (`"field_6a2873ba3bf87": "<embed code>"`) — this is the format the block editor writes and ACF expects. See the `wp_slash()` gotcha below.
- **Images:** `_thumbnail_id` → featured image (archive card); `resource_hero_image` → `hero_image` ACF field only when it differs from `_thumbnail_id`. Same pattern as webinars.
- **Insights / checklist blocks:** legacy structured fields assembled into a superlight-accent group block. Social-share always inside the insights group.
- **Dates and excerpts:** original `post_date`/`post_modified` preserved via `$wpdb->update()`. `excerpt:encoded` from WXR written as `post_excerpt`.
- **Idempotency:** upserts by slug; posts stamped with `_momentive_migration_run`.

**`patch-whitepaper-excerpts.php`:** writes `post_excerpt` on already-migrated posts where it was initially left empty (63/69 posts have excerpt text in the WXR). Dry-run by default.

**`patch-whitepaper-hubspot-forms.php`:** fixes malformed HubSpot form blocks from the initial migration run. Two bugs caused broken blocks: (1) wrong data key format (`hubspot_embed_code` field-name format instead of `field_6a2873ba3bf87` field-key-direct format); (2) `wp_slash()` missing — see gotcha below. The patch re-reads embed codes from the WXR and rebuilds the block comments correctly. Dry-run by default; skips posts already in the correct format.

**`wp_slash()` / `wp_update_post` gotcha (critical — applies to any migration that stores block markup with JSON escape sequences in `post_content`):** `wp_update_post` calls `wp_unslash()` internally on all post data before writing to the DB. Without `wp_slash()` wrapping, every backslash in the block comment JSON is stripped: `\"` (escaped quote) becomes `"` (unescaped, breaking the JSON), and `\r\n` (JSON line endings) becomes `rn`. The fix is always to wrap the `post_content` value:

```php
wp_update_post( wp_slash( [ 'ID' => $new_id, 'post_content' => $post_content ] ), true );
```

This matters any time block content contains a JSON string with special characters — specifically ACF blocks that store embed codes (HubSpot, Wistia, etc.) inline in block data. Blocks with only simple alphanumeric values (like `back-link` or `post-title`) are unaffected because they have no backslashes to lose. Webinar migrations were unaffected because their embed codes were stored in post-level ACF fields via `update_field()`, not inline in block data.

### Solutions migration (`migrate-solutions.php`)

Migrates the legacy hierarchical `solutions` CPT (Elementor/Crocoblock) to the rebuilt `solutions` CPT (native blocks, `patterns/solution-content.php` family). The largest and most iteratively-hardened migration script in this codebase — built and refined against repeated fresh WXR re-exports as pages were hand-rebuilt in parallel.

**Why this script looks the way it does:** the legacy site is Elementor + Crocoblock, so `content:encoded`/`_elementor_data` in the WXR are shared template shells, not per-post content — a child post's rendered HTML shows its *parent's* hero, not its own. Neither is used as a content source. The real per-post content lives in ~220 consistently-named postmeta keys shared across all ~87 true child posts (`event_sub_hero_-_*`, `approach_-_*` + `accordion_items`, `event_sub_features_-_*`, `event_sub_benefits_-_*`, `request_a_demo_-_*`, plus ~10 further sections each gated by its own boolean "enable" flag). See `migrations/solutions-migration-coverage.xlsx` for the full field → block mapping this script was built from.

**Scope:** full `post_content` generation for the ~87 child pages (including legacy post 4428, "Accounting Software Implementation" — structurally `parent=0` in the legacy tree but shaped like a true child, force-parented via `MOMENTIVE_SOL_FORCE_PARENT`). The 21 hub-tier posts (12 top-level families + 9 "(Split Test B)" variants, `MOMENTIVE_SOL_HUB_IDS`) get **ACF field backfill only** — `accent_color`, `solution_icon`, `solution_order`, category taxonomy — never `post_content`; hub content is bespoke and hand-built, same decision already made for Products (`migrate-products.php`). 6 legacy "(OLD)"/"(DUPLICATE)" drafts (`MOMENTIVE_SOL_EXCLUDE_IDS`) are skipped entirely.

**Run modes** (same positional-flag convention as the other `*-wxr` migrations — dry-run by default, must run with `--user=<admin>` for the Safe SVG capability gate):

```bash
wp eval-file migrate-solutions.php               # dry run (default)
wp eval-file migrate-solutions.php live          # writes
wp eval-file migrate-solutions.php live only=4406    # single legacy ID
wp eval-file migrate-solutions.php live hubs-only    # hub-tier field backfill only
wp eval-file migrate-solutions.php live children-only # child content build only
wp eval-file migrate-solutions.php live force        # override the already-rebuilt guard (see below)
```

**Key migration behaviors and findings:**
- **Slug integrity:** legacy `post_name` values are frequently stale relative to the real legacy permalink. `momentive_sol_slug_from_link()` derives the correct slug from the legacy `<link>` and is used everywhere a slug is needed, with a run-time log of how many were corrected.
- **Duplicate-post prevention:** `momentive_sol_find_or_create_post()` matches an existing rebuilt post by **legacy ID first** (`get_post( $legacy_id )` + post type check), correcting a stale `post_name` in place if found; only falls back to a slug lookup if no post exists at that ID; uses `'import_id' => $legacy_id` on new inserts to preserve the legacy numeric ID — the same ID-preservation convention already established for Solutions/Testimonials/other CPTs via bulk WP Import. (A slug-only lookup would have silently created duplicate posts once slug-correction started changing `post_name` out from under it — caught and fixed before it caused a problem.)
- **Elementor cleanup:** legacy Elementor postmeta (`MOMENTIVE_SOL_ELEMENTOR_META_KEYS`) is cleared via `momentive_sol_clear_elementor_meta()` immediately after each child's content write, so a rebuilt post has no stale page-builder data lingering behind the native blocks.
- **"Already rebuilt" guard, and why it checks run-meta:** `momentive_sol_post_already_rebuilt( $post_id )` skips a child write unless `force` or `only=<that id>` is passed, protecting hand-built pages from being silently overwritten by a batch run. Critically, it does **not** protect posts the script itself wrote on an earlier pass (detected via the `_momentive_migration_run` postmeta stamp, `MOMENTIVE_SOL_RUN_META`) — those stay eligible for a refresh. This matters because `momentive_sol_related_solutions_block()` builds each child's "Related Solutions" sibling grid by querying *currently published* siblings under the same rebuilt parent at the moment that child is processed — in a single pass through all ~87 posts, a child processed early can't see siblings created later in the same run, so its grid comes out incomplete. **Plan on running the full batch twice**: once to create everything, once more immediately after so every child's sibling grid tops up now that all its siblings exist. Genuinely hand-built pages (no run-meta stamp, real content) stay protected either way.
- **Testimonial resolution:** tries a **direct legacy-ID match** first (`get_post( $legacy_id )` + testimonial-type check, since testimonial post IDs are preserved from the legacy CPT the same way Solutions IDs are) before falling back to the existing normalized-quote-text fuzzy match against the rebuilt `testimonials` CPT (reused from `migrate-case-studies.php`). An earlier version of `momentive_sol_testimonial_block()` bailed out and logged "skipped" before ever attempting resolution whenever no legacy testimonials WXR was loaded — removed once direct-ID resolution made a legacy WXR unnecessary for the common case.
- **Icons:** legacy `box-`-prefixed icon slugs normalized via `momentive_sol_normalize_icon()`; unresolved slugs (not in `momentive_sol_icon_manifest()`) are logged by name and by which post/section referenced them, both for hub-tier `solution_icon` and every per-child icon-bearing section, rather than silently written as-is.
- **Two mutually-exclusive layout variants of one section:** `event_sub_list_-_*` (a plain list, `momentive_sol_list_block()`) and `event_sub_benefits_-_*` (an icon-grid, `momentive_sol_features_overview_grid_block()`) are two rendering variants of the *same* "Features Overview" section — never both enabled on one post — not two independent sections. The icon-grid variant hardcodes a gray/neutral background on its wrapping group; legacy data has no field distinguishing a white/plain variant (a hand-rebuilt page may reasonably choose one), so that stays a per-post visual call to eyeball after migration, not something the script can decide.
- **Media collage retraction:** `benefits_-_enable_benefits_media_section` is flagged `true` on 17/87 legacy posts, but the section's actual field values (`benefits_-_title`, `-_description`, both floating images) are byte-identical stale boilerplate across all 17 — confirmed against the live legacy pages, none of which actually show this section. `momentive_sol_benefits_media_block()` is disabled (early `return ''`) rather than built; the original logic is left in place, commented, in case a specific post is later confirmed to genuinely need it. (First-pass finding that this needed a pattern to be *built* was wrong — corrected once live pages were checked directly.)
- **`solution_order`:** only has a real data source (the CCT export) for the Accounting family's 7 children; every other child's order has no legacy source and must be set by hand after migration.
- **Known gaps with no source data to resolve them** (not bugs): `connected_products` from the CCT CSV is unusable (Crocoblock exported every row as the literal string `"Array"`, not real IDs); 3 very-low-frequency one-off sections (`event_sub_list_w_heading`, `event_sub_accordion`, `sol_sub_features_accordion` — confirmed one post each across the full corpus) aren't scripted, just logged by name for hand-building.
- **Idempotency:** upserts by legacy ID (not slug — see above); posts stamped with `_momentive_migration_run` for rollback identification, and now also as the "safe to refresh" signal for the already-rebuilt guard.
- **Post-live-run QA pass (2026-07-22):** after the first live run, Daniel spot-checked one migrated post (2443, Event Apps) against a hand-rebuilt reference of the same page and found several real bugs, all fixed directly in the script:
  - **Hero background class was wrong for every child page.** `momentive_sol_hero_block()` emitted `"className":"is-style-bg-dots hero-background"`, matching the *default* `patterns/solution-content.php` scaffold — but every actual rebuilt child page uses `"className":"hero-background","gradient":"vertical"` (`has-vertical-gradient-background`) instead, confirmed across all hand-built children. Fixed.
  - **Duplicated hero eyebrow.** The hero kicker field (`solutions_sub_hero_title_kicker_text`) was rendered as its own `<p class="is-style-eyebrow">`, *and* the H1 separately used `$post_title` — two different source fields both visible, so the page showed "Event App" then "Event Apps" back to back. There is only ever one heading; it now uses the kicker text (falling back to `$post_title` on the 7/88 posts where the kicker is empty). Fixed.
  - **Media-text image-position attrs/markup mismatch.** `momentive_sol_features_block()` added the `has-media-on-the-right` class to the raw HTML for right-positioned rows but never set the matching `"mediaPosition":"right"` block attribute — an attrs/markup mismatch that makes Gutenberg flag the block as invalid content on open (front-end output was unaffected, since static blocks render straight from stored HTML, but editing was broken). Fixed.
  - **HubSpot form silently blank on the front end.** `momentive_sol_demo_form_block()`'s `acf/hubspot-form` block used the field-*name* + underscore-prefixed-key data format instead of field-*key*-direct format — the exact bug already fixed once for whitepapers (`patch-whitepaper-hubspot-forms.php`), reintroduced here. This meant every migrated child page's Request a Demo form was rendering blank on the front end while still looking fine in the editor preview (see the ACF block render gotcha under Custom blocks). Fixed.
  - **Resources section was a permanent TODO placeholder.** `momentive_sol_resources_placeholder_block()` predated `momentive/solution-resources` and always emitted a heading + a literal "TODO" paragraph instead of the real block. Since that block needs no per-post metadata to function (see Resources above), there was no reason to keep excluding it — renamed to `momentive_sol_resources_block()` and now emits the real `<!-- wp:momentive/solution-resources /-->` block. Values default to "Featured resources" / 3 (not the block's own field defaults, "Resources" / 6) to match the hand-rebuilt convention. Uses field-*name* + underscore-prefixed-key format (`"heading":"...","_heading":"field_..."`) rather than the field-key-direct format used elsewhere in this migration (e.g. the HubSpot fix above) — both are valid ACF block data shapes; Daniel confirmed this is the shape actually being produced/expected for this specific block, so the migration matches it rather than standardizing on one shape across every ACF block it emits.
  - **"Explore more {family} solutions" sibling slider wasn't built at all.** Confirmed via the hand-rebuilt reference that every child page gets this section regardless of legacy data (unlike `momentive_sol_related_solutions_block()`'s legacy-flag-gated "Related Solutions" grid, a separate section only 28/87 posts have). New `momentive_sol_explore_more_block()` builds it from the existing `siblings`-class Query Loop convention (functions.php) + `acf/solution-slide`, with a heading generated from `MOMENTIVE_SOL_FAMILY_TO_CAT_SLUG` (e.g. `event-management` → "Explore more event management solutions") rather than any legacy field. Added to the assemble sequence as the last section, matching the reference page.
  - **Minor:** the demo-form section's image block was missing its `"id"` attribute (class said `wp-image-{id}` but the JSON attrs didn't), same attrs/markup mismatch pattern as above on a smaller scale. Fixed.
  - Already-migrated posts (like 2443) all carry the `_momentive_migration_run` stamp, so the already-rebuilt guard does **not** block a refresh — a normal re-run (no `force` needed) overwrites them with the corrected output. Re-run after any future script change to pick up fixes on already-migrated pages.
  - Also fixed in passing: the `siblings`-class `query_loop_block_query_vars` filter in `functions.php` used `get_the_ID()` instead of `get_queried_object_id()` — CLAUDE.md had documented this fix as already applied to the `solutions-sibling-slider` filter, but it was never actually committed to the file. Corrected now that the migration script emits and depends on this exact filter.
- **Second QA round (2026-07-22, same day):** three more markup adjustments requested after reviewing the fixed output, all editorial/convention decisions rather than bugs:
  - **Media-text className switched from `is-style-stacked` to `no-shadow`**, matching every hand-rebuilt page. Each row also gets `padding-top`/`padding-bottom: var(--wp--preset--spacing--medium)` unconditionally, and the sideloaded image's class gains ` size-full`.
  - **Every media-text row is now wrapped in its own `"to-edge"` group**, even when it has no background color — "better to have it and not need it than to need it and not have it" (Daniel). The script alternates a `"backgroundColor":"neutral"` tint onto right-positioned rows (matching the left-plain/right-tinted pattern already established on the Event Apps hand-rebuild); freely overridable per post by hand.
  - **The prefooter CTA (`cta_-_*`, `momentive_sol_cta_block()`) restyled** to `"prefooter is-style-bg-rings"` (was `"alignfull is-style-bg-gradient"`), heading gets `fontSize: xl` plus top/bottom padding, and the buttons block drops its `flex`/`justifyContent` layout attrs. **Moved to the very end of the assemble sequence** — it now always closes out the page regardless of where `cta_-_*` happens to fall among the other enabled sections on a given legacy post.

**Hub-level HubSpot demo-form override, and the "product-as-solution" legacy oddity (2026-07-23/24).** After the QA passes above, Daniel hand-built all the hub-tier Solution pages and, while doing so, set each hub's "Request a Demo" section up as a real WordPress **synced pattern** (a `wp:block {"ref":ID}` reusable block) — 9 of them: accounting (18006), association-management (18004), career-centers (17976), certification-management (17902), donor-management (17859), event-management (17752), fundraising (17866), learning-management (17937), volunteer-management (17824). Data & Analytics (legacy hub 268) has none, and needs none — it has zero children in the legacy corpus. The intent was to reuse one canonical HubSpot form embed per family instead of trusting each child's own (occasionally stale) legacy `request_a_demo_-_hubspot_form_script` field.

A literal `wp:block {"ref":ID}` reference can't be used for this, though: **synced patterns are atomic** — referencing one renders its *entire* saved content, with no way to pull out just the form and leave the rest (title/description/kicker/image) dynamic per child. And per-child copy genuinely differs and must be kept — e.g. every Association Management child names a different AMS product ("See Aptify AMS in Action", "Discover Nimble AMS built on Salesforce", etc.), not interchangeable boilerplate. Daniel confirmed the choice here as "split and keep copy": `momentive_sol_demo_form_block()` keeps building kicker/title/description/image per child exactly as before, straight from that child's own legacy fields — only the HubSpot embed portion is swapped for a family-wide canonical value.

Implementation: a new `MOMENTIVE_SOL_DEMO_FORM_OVERRIDE` constant (keyed by legacy hub ID, one entry per family with a pattern) holds each family's canonical embed script + `two_step` value, extracted from the legacy WXR's own majority per-child value for that family (not re-typed from the pattern) and cross-checked byte-for-byte against the actual pasted pattern markup for Volunteer Management (portalId/formId/region/sfdcCampaignId matched exactly, modulo whitespace/`&nbsp;` cruft that's cleaned up in the constant). `momentive_sol_demo_form_block()` now takes an added `$legacy_parent` parameter; when an override exists for that hub it's used, otherwise the function falls back to the child's own legacy field unchanged (this is also the permanent behavior for Data & Analytics, or any future family with no hand-built pattern yet).

Two data anomalies were found and intentionally normalized by this override rather than silently left alone: accounting's post 4428 ("Accounting Software Implementation," the force-parented non-standard child — see `MOMENTIVE_SOL_FORCE_PARENT`) had used a different HubSpot form than the other 9 accounting children; and a learning-management post ("Association LMS ") had been using accounting's form ID, not learning-management's — almost certainly a legacy copy-paste error. Both now get their family's correct canonical form via the override, which is the intended behavior, not a special case that needed excluding.

**Distinguishing "product-as-solution" posts.** 14 Association Management children are actually individual AMS product pages (Cobalt, Nimble, Aptify, YourMembership, NetForum, etc.) built as `solutions` CPT posts back before a real `product` CPT existed — the legacy site never migrated them off, and continues to link to some of them. Daniel has since tagged these `product` on the rebuilt site by hand; they were not expected to need any migration-script-level special case. The reliable legacy-data marker for this set, confirmed 100% against the full ~88-post corpus: **the legacy post title ends in the literal word "Page"** (e.g. "Aptify AMS Page") — this suffix appears on exactly those 14 Association Management posts and nowhere else across any other family. The variance the hub-pattern work surfaced in the *other* flagged families (certification-management, event-management, learning-management) is unrelated, ordinary per-child content variety, not a second hidden batch of product-as-solution pages.

**`only=<child-id>` couldn't resolve its own hub parent (found 2026-07-27, testing `only=6369` live).** `$legacy_all` is narrowed to just the requested post(s) *before* both passes run, for every `only=`/`limit=` invocation. That's fine for Pass 2 itself, but the parent-resolution fallback used to search that same already-narrowed `$legacy_all` for the parent's own legacy record (to look it up by slug) — when targeting a single child, its hub parent's legacy record has already been filtered out, so the search always failed, and Pass 1 (which populates `$rebuilt_parent_map`) never even iterates the hub in an `only=<child id>` run since the hub isn't in the filtered set either. Net effect: any `only=<child id>` run failed with "can't resolve rebuilt parent" even when the hub post already existed live, exactly as reported testing post 6369 (AMS - Professional Services Page, parent hub 6000/Association Management Software).

Fixed with two changes: (1) an unfiltered `$legacy_all_full` copy is kept alongside the `only=`/`limit=`-narrowed `$legacy_all`, and the slug-based fallback now searches that full copy instead; (2) a direct-ID lookup (`get_post( $legacy_parent )`, checked for `post_type === solutions`) is tried *before* the slug fallback — since hub post IDs are preserved 1:1 from legacy via `import_id` (the same convention `momentive_sol_find_or_create_post()` already relies on for children), the hub almost always already lives at exactly that ID regardless of whether this run's Pass 1 touched it. This also means **no fresh "rebuilt site" WXR export is ever needed for this** — `migrate-solutions.php` always resolves already-existing rebuilt posts via live `get_post()`/`get_page_by_path()` calls against the database it's running against, never from a WXR snapshot of the rebuilt site (those exports are only consumed by `report-rebuild-progress.php`, a separate read-only script).

**Third markup round (2026-07-27), from reviewing post 6369's hand rebuild:** two more section builders adjusted to match the hand-rebuilt convention, both editorial/styling calls rather than bugs:
- **`momentive_sol_list_block()` ("Features Overview," plain-list variant)** — the wrapping group now gets a neutral background tint (`"backgroundColor":"neutral"`) plus top/bottom `spacing|medium` padding (was a plain unstyled group), and the list itself switches from a plain bulleted `wp:list` to `"is-style-column-checks two-columns"` (checkmark bullets laid out two-across). **Superseded same day** (see below) after Daniel looked more carefully at both the code he'd pasted and the legacy site itself.
- **`momentive_sol_testimonial_block()` (single-testimonial case — the only case this function currently handles)** — dropped the two-column wrapper (a 66%/33% split with an empty spacer column used to fake a narrower testimonial width) in favor of a `"className":"large"` attribute directly on the `momentive/testimonial` block, which controls its own width natively. The outer `alignfull no-margin` gradient group keeps its `spacing|large` top/bottom padding (now as a `style.spacing.padding` attribute rather than nested inside the removed `wp:columns` block).

**Fourth markup round (2026-07-27, same day) — `momentive_sol_list_block()` revised again, plus a genuinely missing field.** Daniel flagged that the third-round version above didn't hold up against closer comparison with the legacy site. The corrected shape:
- The outer neutral-tinted group is now `"alignfull"` (spans full width) rather than the previous constrained-width group.
- The eyebrow/heading/description intro copy is wrapped in its own inner `"narrow"` group (`layout: constrained`) so the copy stays narrow and centered (`textAlign: center` on all three), while the list below it isn't width-constrained by that inner wrapper. The heading's bottom margin tightened to `spacing|xx-small` (was `spacing|small`).
- **A genuinely missing field, not just a style tweak:** the legacy meta key `solutions_sub_list_description` — an intro paragraph that belongs directly under the headline — was never read by this function at all; it's now pulled in as `$description` and rendered inside the narrow intro group when present. Note the odd naming: every other field in this section is prefixed `event_sub_list_-_*`, but this one is `solutions_sub_list_description` with no matching prefix — confirmed against the legacy WXR directly (not a typo to "fix" to the other prefix).
- The list itself keeps `is-style-column-checks two-columns` from the third round, plus new top/bottom `spacing|small` margin so it doesn't sit flush against the intro copy above.
- Same caveat as the third round still applies: confirmed correct for post 6369, not yet verified as universal across all 18 posts using this section.

**`momentive_sol_explore_more_block()` excludes product-as-solution posts from the sibling slider (2026-07-27).** The "Explore more {family} solutions" Query Loop queries all published `solutions` posts under the rebuilt parent — which previously included the "product-as-solution" posts (see the writeup above: legacy AMS product pages built as `solutions` CPT posts before a real `product` CPT existed, e.g. the 14 Association Management children). Daniel hand-tagged those with a `solution_tag` term ("products") on the rebuilt site; the Query Loop block's `query.taxQuery.exclude.solution_tag` attribute now excludes that term automatically. The term is resolved by slug at run time (`get_term_by( 'slug', 'products', 'solution_tag' )`, falling back to `product` singular) rather than a hardcoded term ID — IDs can differ between environments — and a missing term logs a single `WP_CLI::warning()` (not once per post) rather than silently producing an unfiltered slider. Scoped to the migration script only; already-migrated posts aren't touched by this change and need the same query adjustment applied by hand if wanted.

**Two "block recovery" bugs found via before/after export comparison (2026-07-28).** Daniel noticed several migrated pages show blocks as corrupted in the editor (fine on the live front end — static HTML renders regardless of the editor's own validation) with an "Attempt Block Recovery" prompt. He exported a page's `post_content` before and after clicking recovery on every flagged block, which pinpointed two distinct root causes, both now fixed:
- **`momentive_sol_features_block()` — right-positioned media-text rows.** Gutenberg validates a block by re-running its own `save()` against the stored attributes and diffing the result against the stored HTML. `core/media-text`'s actual save() doesn't just add a class for `mediaPosition:"right"` — it swaps the DOM order (content div first, then the figure; figure-then-content is only correct for the default "left" position) and sorts `has-media-on-the-right` *before* `is-stacked-on-mobile` in the class list. This function emitted figure-then-content unconditionally with `has-media-on-the-right` appended last, which matched neither. Fixed by branching the child markup order (and class string) on `$is_left`, matching real save() output exactly for both positions.
- **`momentive_sol_stats_block()` — `momentive/impact-stat`.** Unlike the ACF PHP-rendered blocks elsewhere in this script, `momentive/impact-stat` is a JSX-built block (`blocks/impact-stat/src/`) whose `save.js` serializes real markup into `post_content` — it has no server-side render callback. This function emitted it as a self-closing `<!-- wp:momentive/impact-stat {...} /-->` with no inner HTML at all, which can never match what `save()` would regenerate from those attributes. New helper `momentive_sol_impact_stat_html()` mirrors `save.js` line for line — including the `block.json` defaults this migration never overrides (`accentColor` `#E8611A`, `animationDuration` 1800, `animate` true) — so the full rendered markup (border div, content div, prefix/number/suffix spans with the `data-final` thousands-formatted value, label) is written out just as a real editor save would produce it.

Both were previously undetectable from a single hand-rebuilt reference page comparison (the QA passes on 2026-07-22 and 2026-07-27) because neither bug affects front-end rendering — only the block editor's own content validation, which only surfaces when a post is actually opened for editing. Confirmed against `solution-before-recovery.html` / `solution-after-recovery.html` (Budget Software / Accounting family), which capture a real migrated post's `post_content` before and after manually triggering recovery on each flagged block — used as the ground truth for both fixes rather than reasoning about `save.js` output in the abstract.

**`migrations/patch-solutions-block-recovery.php`** applies both of the above fixes to already-migrated posts (`migrate-solutions.php` fixing the generator only helps posts migrated after 2026-07-28). Same WP-CLI conventions as every other patch script in this folder — dry-run by default, `live`/`only=<slug>`/`limit=<n>` — but uses **scoped regex replacement directly on `post_content`**, not a `parse_blocks()`/`serialize_blocks()` round-trip, deliberately mirroring `inc/swoop-heading-cleanup.php`'s approach (a full parse/serialize pass on the whole document risks "unexpected or invalid content" errors on unrelated blocks elsewhere in the same post — the exact failure mode that regex approach was already adopted to avoid once before). Both regexes are self-limiting/idempotent: the media-text pattern only matches the specific broken class-order/child-order shape (already-fixed posts have a different shape and stop matching), and the impact-stat pattern only matches the self-closing `/-->` form (already-expanded instances use an open/close comment pair instead). Verified byte-for-byte against `solution-before-recovery.html`/`solution-after-recovery.html` before writing anything live — the rendered `<div>` markup this script generates matches the real "Attempt Block Recovery" output exactly, aside from a harmless cosmetic difference (recovered instances sometimes decode a `&#039;` entity to a literal `'`, and may drop schema-default attributes like `statPrefix:""` from the block comment on resave — neither affects validation or rendering, so the patch doesn't try to replicate them).

### Migration report scripts (read-only, no writes)

- **`report-rebuild-progress.php`** — classifies every Solutions post as rebuilt/not-rebuilt from live post data. `momentive_rebuild_report_classify()` checks for real `<!-- wp: -->` block content *before* checking legacy Elementor meta (an earlier ordering produced false negatives), and strips a lone default empty-paragraph block (`MOMENTIVE_REBUILD_TRIVIAL_EMPTY_BLOCK` — the block the editor silently adds to any blank post on first open/save) before checking for real content, so a post that was opened once but never actually rebuilt isn't miscounted as "rebuilt" (a real false-positive caught mid-review). `migrate-solutions.php` duplicates this same trivial-empty-block check (`MOMENTIVE_SOL_TRIVIAL_EMPTY_BLOCK`) for its own already-rebuilt guard, since the two are independent `wp eval-file` scripts.
- **`report-impact-stat-usage.php`** — see the `momentive/impact-stat` deprecation gotcha under Custom blocks above.

---

## Known limitations / to-do

- Featured blog post ordering: archive "Featured" section queries by `featured` tag; manual ordering not yet implemented
- Resource filters: multi-CPT "All Resources" querying now works via `/momentive/v1/resources` (`inc/resources.php`) — no dedicated "All Resources" archive template/filter-bar instance has been built yet to actually expose it to editors; the REST plumbing and `filters.js` client support are in place, wiring it into an archive page is still open
- Reading progress bar: currently `is_singular('post')` only; extend to `press-article` in `functions.php` if needed
- `swoop-double` SVG path uses two `M` commands in one `d` string — verify cross-browser
- Webinar: ~15 posts have no matching `video_embed_code` in the assets WXR — check the migration's "[video] no embed code found" log lines and add manually where needed
- People: `linked_user` field on Person Settings is legacy after the byline-link reversal to `linked_person` on users — confirm nothing reads it, then remove
- People: decide whether the shared "Momentive Software" byline should render or show no byline at all (editorial; architecture supports either)
- People: decide whether leader/People profiles should be indexed (`noindex` on the CPT if not) — SEO-team question
- Person block: deep-link hash (`/our-team/#person-{slug}`) only works on pages that include that person's block; the canonical share URL is the permalink. Possible enhancement: make the permalink itself open the lightbox when arriving via internal link
- Case Study: 12 not-yet-created Product posts mean some `linked_products` won't resolve until those products exist — check the migration's end-of-run "unresolved products" summary after creating them, then re-run (idempotent by slug)
- Case Study: TripBuilder-hosted PDFs can't sideload (remote TLS); they remain external links — re-host manually from the run's "PDFs that did not sideload" summary if local copies are wanted
- Case Study: two logos (CAALA, Berkeley Rep) aren't in the WXR export — add by hand
- Case Study: a few coverage posts were hand-corrected (icon `bxs-`/`bx-`, a stat value); the migration writes legacy faithfully, so re-apply those edits by hand after any re-run
- Case Study: `migrate-case-studies.php` must run with `--user=<admin>` (Safe SVG capability gate) and the `live` token (dry-run is the default)
- Solutions: 11 bespoke `sol-*` hub-family icons (not real BoxIcons) are still unresolved — likely need sourcing from legacy media exports rather than an icon library
- Solutions: `migrate-solutions.php` needs its planned second/topping-up run after the first full batch, so `momentive_sol_related_solutions_block()`'s sibling grids are complete for children processed early in the first pass (see the migration's own writeup above)
- Solutions: hub 6383 (volunteer-management-software) still has at least one `momentive/impact-stat` block needing a manual resave to durably persist its "Count up" setting (see the `report-impact-stat-usage.php` gotcha above)
- Solutions: 6 not-yet-rebuilt posts reference a "720"-style multi-testimonial mechanism the current single-testimonial resolution doesn't model — needs a decision once those pages are actually being built
- Solutions: per-post padding/margin tweaks on alternating media-text blocks beyond the new default (e.g. removing spacing so images align flush with an adjacent section) have no legacy field backing them — expect to hand-adjust these per page occasionally, same as the features-overview-grid background color call
- Solutions: the new "Explore more {family} solutions" section's heading is generated from `MOMENTIVE_SOL_FAMILY_TO_CAT_SLUG` and confirmed correct for one family (event-management, via the Event Apps reference rebuild) — rebuilding one child per remaining parent family would confirm the heading pattern holds everywhere before trusting it across the full batch
- Solutions: legacy testimonial data can be stale relative to what the legacy site actually renders. Post 6369 (AMS - Professional Services Page) has `testimonials_-_enable_testimonials_section_83 = true` and a populated `testimonials` field (legacy ID 6368) — exactly what `momentive_sol_testimonial_block()` reads — but the live legacy page has no testimonial section at all (confirmed by fetching it directly). The likely cause is a separate `_fso_section_order`/`_fso_section_visibility` postmeta layer (hashed section IDs) that appears to independently gate which sections the legacy theme actually renders per post, on top of each section's own `_enable_section` flag; the WXR export has no legend mapping those hashes to section names, so this can't be fully confirmed or generalized from the data alone. The migration script has no visibility into this layer at all — it only checks `_enable_section` + content presence — so other posts using the testimonials section could in principle carry the same kind of stale reference. Daniel's call (2026-07-27): leave the script as-is rather than excluding 6369 or auditing the ~24 testimonial-using posts up front; catch any other instances by hand during normal post-migration QA.

### Pending CPT migrations

**Gated content (registration form — no upcoming/on-demand lifecycle)**
Whitepapers are done. Remaining gated types follow the same pattern (form inline in block data, gated vs. not-gated layout variant) and can be built from the whitepaper migration as a template:
- `guide` (Guides & Research)
- `toolkit`
- `infographic`
- `product-overview` — see below; same gated-content family, not a special case

**Product Overviews**
**Is its own CPT** (`product-overview`), part of the gated-content family above — not a toggle on `product`. (An earlier draft of this plan floated "just a toggle on `product`"; that's superseded now that the legacy data confirms these behave like a standalone gated post, same as whitepapers/infographics, and `inc/recordings.php` already assumes a separate host post.) Full field-by-field analysis, the permalink design, and the recording-asset findings are in `notes/reference-sheets/product-overview-reference-sheet.md` — key points:
- A Post Object field (`linked_product`, replacing the legacy text field of the same name) points at the `product` post and **drives the permalink**: `/products/{linked product's slug}/overview/`, via the same `post_type_link` filter + custom rewrite rule technique `inc/guides.php` uses for its dual `/guides/`/`/research-study/` prefixes.
- Legacy URLs are stale on two counts: they're nested under products' old pre-`/products/` Solution-nested location (e.g. `/solutions/career-centers-software/ym-careers/overview/`), and the `linked_product` text value no longer matches the real product slug on 5 of 9 posts.
- The legacy `assets` CPT posts these forms redirect to after submission (e.g. `/assets/product-overview/2026-06-givesmart-overview/`) don't share a slug with their product-overview post, and use an inconsistent, rotating monthly naming scheme (a product can have several, one per refresh) — so the `/webinars/{slug}` → `/recordings/{slug}` prefix-swap webinars use won't work here. Recommendation: don't try to route through the legacy asset slugs at all — pull each product's *latest* asset's `video_embed_code` onto the rebuilt post's own Recording fields (same shared field group as webinars) and let `/recordings/{product-overview's own slug}` (via `inc/recordings.php`, one line added to `momentive_recording_host_types`) be the one current, permanent recording URL. Redirect the old `/assets/product-overview/*` URLs to it as a static list (Redirection plugin), not a dynamic PHP catch-all.

**Events** ✓ Complete

CPT key `event`, URL `/events/{slug}/`, archive at `/events/`. Supports: title, editor, excerpt, thumbnail, revisions. Shares `category` taxonomy for solution-scoped filtering. Included in `momentive_get_resource_post_types()` (Resources archive filter + Solution-page grids) and Resource Relevance ACF field group. No ACF fields defined yet — the first event was hand-built as bespoke block content. Add ACF fields via the UI once content patterns are clear from multiple events. Registered in `inc/events.php`.

**Videos** ✓ Complete
Consolidated with Video Testimonials into the `video` CPT (`/videos/`). 4 posts total (3 videos + 1 video testimonial). `video_type` taxonomy (locked vocabulary, seeded term: `testimonial`) distinguishes video testimonials for filtering. PHP redirect in `inc/videos.php` covers `/testimonials/{slug}/` → `/videos/{slug}/`. `momentive/wistia-popover` block handles the player; `view.js` wires `.js-video-play` buttons to trigger the nearest player's popover. Added to `momentive_get_resource_post_types()` and Resource Relevance ACF location rules.

**Video Testimonials** ✓ Complete
Folded into the `video` CPT — see Videos above.

**To be determined (decisions needed before building)**
- `events` ✓ Complete — see Events CPT below
- Interactive Tools, Landing Pages, Integrations, Donation Examples
- Reviews ✓ Complete

**Migrate as pages, not CPTs**
- Industries — these are effectively pages; rebuild as standard `page` posts

**Fold into patterns, retire the CPT**
- Award Recipients — 4 posts built for one page (`/bring-on-better-awards/`); content goes into a block pattern

### Legacy CPTs to retire (no migration needed)
- **Assets** — already folding `video_embed_code` into Webinars; Product Overviews will do the same, taking only each product's latest recording (the legacy CPT has several rotating monthly assets per product) — see `notes/reference-sheets/product-overview-reference-sheet.md`
- **Clients** — already marked "To be removed" on the current site

### Dashboard / plugin items to evaluate
Still present in legacy admin; each needs a keep / replace / remove decision:
- Rank Math SEO — keep, or replace with Yoast?
- Site Configuration, Product Settings, Solution Settings — review what these control
- Crocoblock — legacy page-builder dependency; retire once migration is complete
- SEO Sheets, Publish Press Future, Capabilities, Site Documentation, Maintenance Reports, Thermometer — evaluate
- 500 Designs Toolkit — folding into the new theme