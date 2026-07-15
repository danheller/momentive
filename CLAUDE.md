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
│   ├── swoop-heading-cleanup.php  Strips stray &nbsp; from is-style-has-swoop headings at save time
│   ├── custom-menu-order.php
│   └── disable-comments.php
│
├── patterns/          Block patterns (PHP) + announcement bar
├── parts/             FSE template parts (header, footer, megamenu panels)
├── templates/         FSE block templates
├── migrations/        One-off WP-CLI migration scripts
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

# One-time build
sass --no-source-map assets/sass/momentive.scss assets/css/momentive.css --style compressed
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
- ACF fields: `accent_color`, `solution_icon` (slug from icon system), `background_image`, `breadcrumb_title`, `solution_order`, `solution_card_label`
- New post template: `patterns/solution-content.php` (applied via CPT template at priority 30)
- Child solutions inherit `accent_color` from parent; the field is hidden on child posts in the editor

### `product` (flat/non-hierarchical)

- URL: `/products/{slug}/`
- Taxonomy: `product_type` (private; terms: `active-product`, `orphan-product`) + shared `category`
- Shared solution categories: children of the built-in "Solutions" category term. Restricted in the ACF category picker via `acf/fields/taxonomy/query/name=product_category`; default category panel removed from editor via JS
- ACF fields: `tint_color` (hero tint), `accent_color` (icon color), `product_icon`, `product_order`, `summary`, `background_image`, `logos` (repeater), `product_logo_*` (endorsed/unendorsed, white/color variants)
- New post template: `patterns/product-content.php`
- Product Marquee excludes Orphan products via `momentive_product_marquee_query_args` filter

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
| `momentive/resource-filters` | AJAX filter + sort bar for archives. Proximity-targets adjacent Query Loop (no ID needed). Load More replaces pagination. REST endpoint map in `filters.js`. |
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
| `acf/webinar-status` | ACF block. Renders the upcoming/on-demand status badge for a webinar post. Reads `webinar_type` field and resolves live status against `webinar_date`. |
| `acf/webinar-schedule` | ACF block. Renders formatted date, time range, and timezone for a webinar. Reads `webinar_date`, `webinar_end_date`, `webinar_time_start`, `webinar_time_end`, `webinar_timezone`. Renders nothing when date is empty. |
| `acf/webinar-form-heading` | ACF block. Renders an optional heading above the HubSpot form in the sidebar. Field: `heading_override` (text). Renders nothing when empty — safe to include unconditionally in the webinar template. |
| `momentive/webinar-presenters` | ACF block. Renders presenter cards (headshot + name + job_position) from the `presenters` ACF field (post_object → `people`). Block attributes: `layout` (grid or list), `show_headshots` (true/false). Falls back gracefully when `people` posts have no featured image. |

### JSX build block

| Block | Notes |
|---|---|
| `momentive/impact-stat` | Animated stat counter. `IntersectionObserver` fires count-up at 25% threshold. Respects `prefers-reduced-motion`. Attributes: `statPrefix`, `statNumber`, `statSuffix`, `statLabel`, `accentColor`, `animationDuration`. Source in `blocks/impact-stat/src/`, compiled to `blocks/build/impact-stat/`. |

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
| `core/heading` | `eyebrow` | Small-caps accent color label |
| `core/heading` | `has-swoop` | Animated SVG underline on `<strong>` child |
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

PHP files in `patterns/`. Registered via `inc/patterns.php`. Pattern categories: `momentive-page`, `momentive-pricing`.

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
| Product Settings | `post_type == product` | `solution_family`, `summary`, `breadcrumb_title`, `product_order`, `background_image`, `tint_color` (hex), `logos` (repeater), `product_icon` (select), `accent_color` (hex), `product_logo_*` (image — endorsed/unendorsed × color/white) |
| Solution Settings | `post_type == solutions` | `breadcrumb_title`, `accent_color` (hex), `solution_icon` (select), `background_image`, `solution_card_label`, `solution_order` |
| Case Study Settings | `post_type == case-study` (`group_6a421df4548b3`) | `linked_products` (post-level source of truth for the sidebar block), `breadcrumb_title`. Stats/features live in their blocks, not post fields. |
| Linked Products Block | `block == momentive/linked-products` (`group_6a429f79214af`) | `heading`, `show_heading`, `linked_products` (block-level override). Field keys: heading `field_6a429fb9316b6`, show_heading `field_6a42a00e316b7`, override products `field_6a42aac112ead`. The post-level `linked_products` (Case Study Settings) is `field_6a429f79316b5`. |
| Stat Columns Block | `block == momentive/stat-columns` | `stats` repeater (`stat_value` `field_6a42c6c8357d9`, `stat_description` `field_6a42c6ef357da`; repeater `field_6a42c667b17bc`) |
| Webinar Settings | `post_type == webinar` (`group_6a3a318255bf0`) | `webinar_type` `field_6a3a3182ba777`, `is_series` `field_6a3e1db41ee80`, `webinar_date` `field_6a3a31bcba778`, `webinar_end_date` `field_6a3a31dbba779`, `webinar_time_start` `field_6a3a31f9ba77a`, `webinar_time_end` `field_6a3a323bba77b`, `webinar_timezone` `field_6a3a3249ba77c`, `form_upcoming` `field_6a3a3321ba77f`, `form_ondemand` `field_6a3a3356ba780`, `video_embed_code` `field_6a3ef54a65cd6`, `presenters` `field_6a3edd7da2c1f`, `hero_image` `field_6a3eddd24103c` (return_format: array) |
| Back Link Block | `block == momentive/back-link` (`group_6a44a4078d0f6`) | `label` `field_6a44a408f79e0`, `url` `field_6a44a420f79e1` |
| Webinar Form Heading Block | `block == acf/webinar-form-heading` (`group_6a44a695407f9`) | `heading_override` `field_6a44a695e649d` |
| Webinar Presenters Block | `block == momentive/webinar-presenters` (`group_6a448a68cf996`) | `layout` `field_6a448a69ebb4b`, `show_headshots` `field_6a4542d50b10a` |
| Whitepaper Settings | `post_type == whitepaper` (`group_6a45de7a50be6`) | `hero_image` `field_6a45de7b50be7` (image, return_format: array) |


---

## Developer experience

- **Header/Footer edit buttons:** visible to logged-in editors on hover. Links to template part in Site Editor. (`inc/header-footer-edit-buttons.php`)
- **"Blog" label:** "Posts" renamed throughout admin. (`inc/rename-posts-to-blog.php`)
- **Custom menu order:** dashboard sidebar menu reordered. (`inc/custom-menu-order.php`)
- **Comments disabled:** all comment UI, menus, and dashboard widgets removed. (`inc/disable-comments.php`)

---

## Design decisions and rationale

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

**Swoop headings strip stray `&nbsp;` at save time.** Content pasted in from the legacy site often carries invisible non-breaking spaces (`&nbsp;` entity or the raw U+00A0 character) that neither the visual nor code editor surfaces — only the browser inspector shows them. Inside a `.is-style-has-swoop` heading, an nbsp glues adjacent words (and the swooped `<strong>`, which is `white-space: nowrap`) into one unbreakable run for the line-breaking algorithm; on a large/long heading with no valid space left to break at, the browser falls back to breaking mid-word (e.g. "Every member" rendering as "Every mem" / "ber" across two lines). `inc/swoop-heading-cleanup.php` hooks `wp_insert_post_data`, walks the parsed block tree, and normalizes both nbsp forms back to a plain space inside any `core/heading` block carrying `is-style-has-swoop` — scoped narrowly so intentional nbsp elsewhere (e.g. hero paragraphs) is left untouched.

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

---

## Known limitations / to-do

- Featured blog post ordering: archive "Featured" section queries by `featured` tag; manual ordering not yet implemented
- Resource filters: "All Resources" across multiple CPTs requires a custom REST endpoint — not yet built
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

### Pending CPT migrations

**Gated content (registration form — no upcoming/on-demand lifecycle)**
Whitepapers are done. Remaining gated types follow the same pattern (form inline in block data, gated vs. not-gated layout variant) and can be built from the whitepaper migration as a template:
- `guide` (Guides & Research)
- `toolkit`
- `infographic`

**Product Overviews**
Not a new CPT. Extend the `product` post type with a toggle that enables upcoming/on-demand recording fields (mirrors Webinar Settings: `webinar_date`, `form_upcoming`, `form_ondemand`, `video_embed_code`). On the legacy site these live under product URLs (e.g. `/solutions/career-centers-software/ym-careers/overview/`). Video embed comes from a corresponding `assets` post, same pattern as webinars. Upcoming example: `https://momentivesoftware.com/solutions/career-centers-software/ym-careers/overview/` — On-demand: `https://momentivesoftware.com/solutions/accounting-software/mip-accounting/overview/`

**Videos**
Only 3 posts on the legacy site. No form — "watch now" button only. Likely folds into webinars (with a `video` type) or a minimal standalone CPT.

**To be determined (decisions needed before building)**
- `events` — scope unclear
- Video Testimonials — consider folding into `testimonial` CPT with a `video` type
- Interactive Tools, Landing Pages, Integrations, Donation Examples, Reviews

**Migrate as pages, not CPTs**
- Industries — these are effectively pages; rebuild as standard `page` posts

**Fold into patterns, retire the CPT**
- Award Recipients — 4 posts built for one page (`/bring-on-better-awards/`); content goes into a block pattern

### Legacy CPTs to retire (no migration needed)
- **Assets** — already folding `video_embed_code` into Webinars and (eventually) Product Overviews
- **Clients** — already marked "To be removed" on the current site

### Dashboard / plugin items to evaluate
Still present in legacy admin; each needs a keep / replace / remove decision:
- Rank Math SEO — keep, or replace with Yoast?
- Site Configuration, Product Settings, Solution Settings — review what these control
- Crocoblock — legacy page-builder dependency; retire once migration is complete
- SEO Sheets, Publish Press Future, Capabilities, Site Documentation, Maintenance Reports, Thermometer — evaluate
- 500 Designs Toolkit — folding into the new theme