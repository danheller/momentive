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
| Custom fields | ACF Pro — field groups defined in `inc/acf-groups.php` via `acf_add_local_field_group()` |
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
│   │   ├── block.json       Block metadata + attribute schema
│   │   ├── block.php        Registration + render callback
│   │   ├── editor.js        Editor UI (plain JS, no build step)
│   │   ├── {name}.js        Front-end JS (conditionally enqueued)
│   │   └── {name}.css       Front-end styles (conditionally enqueued)
│   └── build/         Compiled output for JSX blocks (committed to git)
│
├── inc/               Modular PHP — all required from functions.php
│   ├── acf-groups.php          All ACF field group definitions
│   ├── solutions.php           Solutions CPT + accent color injection
│   ├── products.php            Products CPT + accent color injection
│   ├── testimonials.php        Testimonials CPT
│   ├── faq.php                 FAQ CPT
│   ├── newsroom.php            Press Articles CPT + related posts injection
│   ├── authors.php             Authors CPT
│   ├── icons.php               Icon system (discovery, sprite output, helpers)
│   ├── check-content-for-block.php  momentive_content_has_block() helper
│   ├── patterns.php            Pattern registration helpers
│   ├── header-footer-edit-buttons.php  Logged-in edit buttons
│   ├── rename-posts-to-blog.php
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

---

## JavaScript build process

Only `momentive/impact-stat` requires a build step. All other blocks use WordPress globals directly.

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
- **Products:** `page_accent_color` field (the tinted background color). Also injects `--page-icon-color` from `accent_color` field (the icon/brand color). Note: product accent is split into two fields — this may be worth renaming later.

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
- ACF fields: `page_accent_color` (hero tint), `accent_color` (icon color), `product_icon`, `product_order`, `summary`, `background_image`, `logos` (repeater), `product_logo_*` (endorsed/unendorsed, white/color variants)
- New post template: `patterns/product-content.php`
- Product Marquee excludes Orphan products via `momentive_product_marquee_query_args` filter

### `testimonial`

- ACF fields: `solution_family` (relationship to category term), `author_name`, `author_description`, `author_photo`, `testimonial_type`

### `faq`

- ACF fields: `solution_family`
- Used by the accordion block in query mode

### `press-article` (Newsroom)

- URL: `/newsroom/{slug}/`
- Archive: `/newsroom/`
- Shares `single.html` template via body class filter (`.single-article`) in `newsroom.php`
- ACF fields: `hero_image`
- Related posts injected below post content via `render_block` filter

### `post` (Blog)

- Renamed to "Blog" in admin via `inc/rename-posts-to-blog.php`
- ACF fields: `breadcrumb_title`, `cta_button` (link field), `post_author_ref` (Post Object → `authors` CPT)

### `authors` CPT

- Post title = display name; featured image = photo
- Linked to blog posts via `post_author_ref` ACF field

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

## Custom blocks

### No-build blocks (plain JS)

| Block | Notes |
|---|---|
| `momentive/accordion` | Static or query (FAQ CPT) mode. Three style variants: default, categorized, icon. `closeOthers` and `openFirst` options. `@starting-style` animation on panels. `core/details`, `core/accordion*`, `core/icon` are unregistered to avoid ambiguity. |
| `momentive/breadcrumbs` | Uses ACF `breadcrumb_title` override if set. Options for home link and separator. |
| `momentive/post-byline` | Author photo, name, last-updated date (only when >24h after publish), reading time (`ceil(words/220)`). Falls back to WP author if ACF field empty. |
| `momentive/post-cta-button` | Outputs nothing if ACF `cta_button` field is empty — safe to include unconditionally in templates. |
| `momentive/resource-filters` | AJAX filter + sort bar for archives. Proximity-targets adjacent Query Loop (no ID needed). Load More replaces pagination. REST endpoint map in `filters.js`. |
| `momentive/table-of-contents` | Parses H2/H3, sticky, scroll-spy. Collapses when list >50% viewport height. Expand/collapse state in `sessionStorage`. |
| `momentive/social-share` | Copy link (clipboard API + `execCommand` fallback), LinkedIn, X, Facebook. Popups use constrained window. |
| `momentive/icon-shuffle` | Animated icon grid. |
| `momentive/testimonial` | Renders a testimonial card. Integrates with Query Loop. |
| `momentive/product-marquee` | Two auto-scrolling rows (Splide AutoScroll). Row 1 scrolls left, row 2 right. Pauses on hover. Pulls from Products CPT; filtered to `active-product` type via `momentive_product_marquee_query_args` hook. Wordmark image fallback when no logo image is set. |
| `momentive/product-solution-tabs` | Tabbed grid of products grouped by Solution. Tabs derived automatically from `get_solutions_with_products()` — no manual curation per instance. Deep-linkable via URL hash. Mobile dropdown with "All" option. Enqueuing checks both `momentive_content_has_block()` and `is_post_type_archive('product')`. |
| `momentive/hubspot-form` | ACF block. Two modes: standard embed (paste embed code), and two-step (email capture inline → full form in modal). Modal appended to `document.body` to avoid stacking context issues with sticky nav. |
| `momentive/megamenu-panel` | InnerBlocks-based panel. Allowed children: `core/columns`, `core/group`. Paired with flat WordPress nav and separate FSE template parts per panel (`parts/megamenu-*.html`). |

### ACF blocks (PHP render template)

| Block | Notes |
|---|---|
| `acf/solution-slide` | Single solution card for use inside a Query Loop / Splide slider. |
| `acf/hubspot-form` | See above. |
| `acf/product-solution-tabs` | See above. |

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
| `related-posts.php` | "Recommended for you" section, injected by `newsroom.php`. |

---

## FSE templates and parts

| File | Route |
|---|---|
| `templates/index.html` | Fallback |
| `templates/home.html` | Homepage |
| `templates/page.html` | Pages |
| `templates/single.html` | Blog post singles |
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

## ACF field groups (defined in `inc/acf-groups.php`)

All field groups use `acf_add_local_field_group()` — no JSON export needed.

| Group | Location | Key fields |
|---|---|---|
| Category Settings | `taxonomy == category` | `related_solution` (post_object → solutions) |
| HubSpot Form | `block == acf/hubspot-form` | `hubspot_embed_code` (textarea), `two_step` (true/false) |
| Post Settings | `post_type == post` | `breadcrumb_title`, `cta_button` (link), `post_author_ref` (post_object → authors), `hero_image` |
| Testimonial Settings | `post_type == testimonial` | `solution_family` (taxonomy), `author_name`, `author_description`, `author_photo`, `testimonial_type` (select), `related_case_study` |
| FAQ Settings | `post_type == faq` | `solution_family` |
| Product Settings | `post_type == product` | `solution_family`, `summary`, `breadcrumb_title`, `product_order`, `background_image`, `page_accent_color` (hex), `logos` (repeater), `product_icon` (select), `accent_color` (hex), `product_logo_*` (image — endorsed/unendorsed × color/white) |
| Solution Settings | `post_type == solutions` | `breadcrumb_title`, `accent_color` (hex), `solution_icon` (select), `background_image`, `solution_card_label`, `solution_order` |

**ACF local JSON:** `/acf-json/` directory is not yet set up. To enable field group version control, create the directory and enable it in ACF settings.

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

**ACF fields in PHP, not JSON export.** `acf_add_local_field_group()` in `inc/acf-groups.php` keeps field definitions in version control without a separate JSON export step. The trade-off is verbose PHP; the benefit is a single source of truth.

**`--page-accent-color` on `body`.** Injecting the accent color at the body level (rather than per-block) means any block anywhere on a product/solution page can reference it with `var(--page-accent-color)` in CSS, with no PHP coordination needed per block.

**Splide globally enqueued.** Sliders appear on multiple page types (homepage, newsroom, product pages). The performance cost of always loading Splide is lower than the complexity of conditionally loading it across many templates.

**HubSpot modal appended to `document.body`.** The modal needs `z-index` above the sticky nav, which creates a stacking context. Appending to body breaks out of any stacking context in the page content.

**`core/accordion` triple-unregistration.** WordPress's native accordion blocks can't be removed via the standard `allowed_block_types_all` filter alone — they re-register themselves via `__unstableBlockDefinitions`. A three-pronged approach (`allowed_block_types_all` + `block_editor_settings_all` filter + JS `unregisterBlockType` on `wp.domReady`) is required.

---

## Known limitations / to-do

- `acf-json/` local JSON not yet set up — field groups are not version-controlled as JSON
- `icon-shuffle` block documentation incomplete
- `icons` CPT: slug not confirmed; update README Post Types section
- Featured blog post ordering: archive "Featured" section queries by `featured` tag; manual ordering not yet implemented (ACF options page approach is an option)
- Resource filters: "All Resources" across multiple CPTs requires a custom REST endpoint — not yet built
- Reading progress bar: currently `is_singular('post')` only; extend to `press-article` in `functions.php` if needed
- `swoop-double` SVG path uses two `M` commands in one `d` string — verify cross-browser
- Product accent color field naming is confusing (`page_accent_color` for the hero tint, `accent_color` for the icon; consider renaming)