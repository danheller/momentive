# Momentive WordPress Theme

A custom Full Site Editing (FSE) theme built on the [Frost](https://frostwp.com/) base theme, replicating the design of [momentivesoftware.com](https://momentivesoftware.com) with native WordPress blocks, custom blocks, and minimal dependencies. Source repository: https://github.com/danheller/momentive/

---

## Table of Contents

- [Requirements](#requirements)
- [Getting Started](#getting-started)
- [Directory Structure](#directory-structure)
- [SCSS Compilation](#scss-compilation)
- [Block System](#block-system)
  - [Custom Blocks](#custom-blocks)
  - [Block Styles](#block-styles)
  - [Block Patterns](#block-patterns)
- [Post Types](#post-types)
- [ACF Fields](#acf-fields)
- [Templates](#templates)
- [Key Dependencies](#key-dependencies)
- [Developer Experience Features](#developer-experience-features)
- [Design Tokens](#design-tokens)
- [Known Limitations & To Do](#known-limitations--to-do)

---

## Requirements

- WordPress 6.5+
- PHP 8.1+
- [Advanced Custom Fields Pro](https://www.advancedcustomfields.com/) (ACF) — used for per-post fields on blog posts and author linking
- [Splide](https://splidejs.com/) — slider library, compiled into `/assets/css/splide.css` and `/assets/js/` (loaded globally)
- Node/npm not required — no build step for JavaScript; editor scripts use WordPress globals (`wp.blocks`, `wp.element`, etc.)

---

## Getting Started

1. Clone or copy the theme into `wp-content/themes/momentive/`.
2. Activate the theme in **Appearance → Themes**.
3. Flush permalinks: **Settings → Permalinks → Save Changes**. This is required whenever post types or archive slugs change.
4. Install and activate ACF Pro.
5. Import ACF field groups — see [ACF Fields](#acf-fields).
6. Start the SCSS watcher — see [SCSS Compilation](#scss-compilation).

---

## Directory Structure

```
momentive/
├── assets/
│   ├── css/                  Compiled CSS (do not edit directly)
│   │   ├── momentive.css     Main stylesheet
│   │   └── splide.css        Slider library styles
│   ├── images/               SVGs and theme images (logo, backgrounds, icons)
│   ├── js/
│   │   ├── momentive.js      Main JS (sliders, swoop animation, announcement bar)
│   │   └── reading-progress.js  Reading progress bar (single posts only)
│   └── sass/                 Source SCSS — see SCSS Compilation below
│       └── momentive.scss    Single entry point with full TOC at top of file
│
├── blocks/                   Custom blocks — each self-contained
│   ├── breadcrumbs/
│   ├── icon-shuffle/
│   ├── post-byline/
│   ├── post-cta-button/
│   ├── resource-filters/
│   ├── social-share/
│   └── table-of-contents/
│
├── inc/                      Modular PHP includes loaded from functions.php
│   ├── authors.php           Authors CPT registration
│   ├── disable-comments.php  Removes all comment UI and endpoints
│   ├── header-footer-edit-buttons.php  Edit buttons for logged-in users
│   ├── icons.php             Icons CPT registration
│   ├── newsroom.php          Press Articles CPT + related posts injection
│   ├── rename-posts-to-blog.php  Renames "Posts" to "Blog" in admin
│   ├── show-patterns-in-menu.php  Adds Patterns to dashboard sidebar
│   └── solutions.php         Solutions CPT registration
│
├── languages/                Translation files
│
├── parts/                    FSE template parts
│   ├── header.html
│   ├── footer.html
│   └── [others]
│
├── patterns/                 Block patterns and the announcement bar template
│   └── announcement-bar.php  Renders the dismissible top bar (cookie-based)
│
├── template-parts/           PHP template parts (used outside block templates)
│   ├── related-posts.php     "Recommended for you" section on single posts
│   └── story-card.php        Reusable post card for related posts + archives
│
├── templates/                FSE block templates
│   ├── index.html
│   ├── single.html           Blog post single template
│   ├── archive-press-article.html  Newsroom archive
│   └── [others]
│
├── functions.php             Theme setup, enqueuing, filters — well commented
├── theme.json                Design tokens: type scale, color palette, spacing
└── README.md                 This file
```

---

## SCSS Compilation

Source files live in `/assets/sass/`. The single entry point is `momentive.scss`, which has a full table of contents at the top.

**Watch command** (run from the theme root):

```bash
sass --no-source-map --watch assets/sass:assets/css --style compressed
```

**One-time compile:**

```bash
sass --no-source-map assets/sass/momentive.scss assets/css/momentive.css --style compressed
```

Do not edit files in `/assets/css/` directly — they are overwritten on the next compile.

### SCSS Structure

The file is organized into numbered sections matching the TOC:

| Section | Contents |
|---|---|
| 0.1–0.4 | Mixins, breakpoints, media query strings, CSS custom properties |
| 1.0–1.2 | Reset, base typography, forms |
| 2.0–2.4 | Utility classes, layout, visibility, shape utilities |
| 3.0–3.3 | Global block overrides (buttons, columns, navigation) |
| 4.0–4.5 | Text styles (eyebrow, headings, quote, read more, swoop underline) |
| 5.0–5.3 | Site structure (announcement bar, header, footer) |
| 6.0–6.9 | Homepage sections (hero, trust, highlights, solutions, etc.) |
| 7.0–7.3 | News/blog (story card, news slider, featured blogs grid) |
| 8.0–8.1 | Animations (parallax) |
| 9.0–9.1 | Asset placeholders (social icon SVGs) |

---

## Block System

### Custom Blocks

Each block lives in `/blocks/{block-name}/` and contains:

| File | Purpose |
|---|---|
| `block.json` | Block metadata and attribute definitions — authoritative source for attributes |
| `block.php` | Registers the block, editor script, and front-end assets; contains `render_callback` |
| `editor.js` | Block editor UI — uses WordPress globals (`wp.blocks`, `wp.element`, etc.), no build step required |
| `[name].js` | Front-end JavaScript (enqueued only on pages that use the block) |
| `[name].css` | Front-end styles (enqueued only on pages that use the block) |

#### `momentive/breadcrumbs`

Renders a breadcrumb trail for the current post or page. Falls back to the WordPress post author if ACF isn't active.

**ACF dependency:** `breadcrumb_title` (Text field on `post`) — optional short label that overrides the full post title in the breadcrumb. Leave blank to use the post title.

**Options:** Show/hide home link, customise home label, customise separator character.

---

#### `momentive/post-byline`

Renders the author photo, name, "Last updated" date, and estimated reading time below the post header. The "Last updated" date only appears when the modified date is more than 24 hours after the publish date.

**ACF dependency:** `post_author_ref` (Post Object field on `post`, points to the `authors` CPT). Falls back to the WordPress post author if the field is empty.

**Reading time formula:** `ceil( word_count / 220 )` words per minute, minimum 1 minute.

**Options:** Show/hide modified date, show/hide reading time.

---

#### `momentive/post-cta-button`

Renders an optional CTA button in the post header from an ACF link field. Outputs nothing if the field is empty, so it's safe to include in the single post template unconditionally.

**ACF dependency:** `cta_button` (Link field on `post`, array format with `url`, `title`, `target`).

**Options:** Filled or outline button style.

---

#### `momentive/resource-filters`

Filter and sort bar for archive pages. Works with any adjacent Query Loop block using proximity-based targeting (no query ID needed). Replaces server-rendered pagination with a progressive-enhancement Load More button.

**Options (set in block editor sidebar):**
- **Post type** — drives the REST API endpoint and restricts the category list to categories used by that CPT
- Show/hide category filter, resource type filter, search, sort
- Resource type list (one per line: `slug | Label`)

**Notes:**
- Category checkboxes use term IDs (not slugs) as values, matching the WP REST API `categories` parameter
- The `data-default-post-type` attribute on the wrapper div is read by JS to determine which REST endpoint to query
- REST endpoint map is in `filters.js` (`postTypeEndpoint()`) — extend this when adding new CPTs

---

#### `momentive/table-of-contents`

Parses the current post's headings (H2 and optionally H3) and renders a sticky in-page navigation with scroll-spy highlighting. Collapses automatically if the list height exceeds 50% of the viewport. Expand/collapse state is persisted in `sessionStorage` per post.

Falls back to the post title when no headings are found (used on "in the news" style posts with no document structure).

**Options:** Title text, maximum heading level (H2 only or H2+H3), expanded by default.

---

#### `momentive/social-share`

Share buttons for the current post: copy link (with clipboard API + checkmark confirmation), LinkedIn, X, Facebook. Social links open in a constrained popup window.

**Options:** Toggle each button individually, customise heading text.

---

#### `momentive/icon-shuffle`

[Add description — animated icon grid used in the product suite section.]

---

### Block Styles

Block styles add an `is-style-{name}` CSS class to a block and appear in the editor's Styles panel. They are registered in `functions.php` and styled in `momentive.scss`.

| Block | Style | Effect |
|---|---|---|
| `core/group` | `bg-dots` | Dot pattern background (accent-tinted SVG) |
| `core/group` | `bg-rings` | Rings + shapes SVG background |
| `core/group` | `bg-dark` | Dark navy background, flips text to white |
| `core/group` | `bg-light-blue` | Superlight accent background |
| `core/group` | `bg-blue-ellipse` | Blue ellipse gradient (used in page heroes) |
| `core/group` | `bg-gradient-blue` | Blue-to-transparent gradient |
| `core/columns` | `outline` | Bordered card columns with rounded corners |
| `core/columns` | `columns-reverse` | Reverses column order on mobile |
| `core/heading` | `eyebrow` | Small caps label style in accent color |
| `core/heading` | `has-swoop` | Animated SVG underline on the `<strong>` child |
| `core/paragraph` | `eyebrow` | Same as heading eyebrow |
| `core/paragraph` | `uppercase` | Uppercase label without accent color |
| `core/navigation-link` | `button` | Orange pill button style for nav CTAs |

### Block Patterns

Patterns are registered as PHP files in `/patterns/` or as Synced Patterns (stored as `wp_block` posts, accessible via **Patterns** in the dashboard sidebar).

Pattern categories registered: `momentive-page`, `momentive-pricing`.

---

## Post Types

### `post` (Blog)

Standard WordPress posts. Renamed to "Blog" in the admin via `inc/rename-posts-to-blog.php`.

**Single template:** `templates/single.html`
**Archive:** Standard WordPress blog page (set in **Settings → Reading**)

### `press-article` (Newsroom)

Press releases, news coverage, and company announcements.

**Single template:** Shares `single.html` via a body class filter in `inc/newsroom.php` (adds `.single-article` to both post types for shared styling).
**Archive template:** `templates/archive-press-article.html` — lives at `/newsroom/`.

### `solutions`

[Add description — used to populate the solutions slider on the homepage.]
Registered in `inc/solutions.php`.

### `authors`

Author profiles used to populate the `post-byline` block. Post title = author display name; featured image = author photo. Linked to blog posts via the ACF `post_author_ref` field.

Registered in `inc/authors.php`.

### `icons` (post type slug: [add slug])

[Add description — used to populate the icon shuffle block.]
Registered in `inc/icons.php`.

---

## ACF Fields

All ACF field groups should be exported and version-controlled as JSON in `/acf-json/` (ACF's local JSON feature). If this directory doesn't exist, create it and enable local JSON in ACF settings.

| Field group | Location | Fields |
|---|---|---|
| Post Options | Post type: `post` | `breadcrumb_title` (Text), `post_author_ref` (Post Object → `authors`), `cta_button` (Link) |
| [Featured Posts] | Options page | `featured_blog_1`–`featured_blog_4` (Post Object → `post`) — [confirm field names] |

---

## Templates

| Template file | Route | Notes |
|---|---|---|
| `templates/index.html` | Fallback | |
| `templates/single.html` | `/blog/{slug}/` | Two-column layout: content + sticky sidebar (TOC + share buttons). Related posts injected via `render_block` filter in `inc/newsroom.php`. |
| `templates/archive-press-article.html` | `/newsroom/` | Grid of press articles with pagination. |
| `parts/header.html` | All pages | Sticky header with logo and navigation. Offset by announcement bar height via `--announcement-bar-height` CSS custom property. |
| `parts/footer.html` | All pages | Multi-column footer with gradient SVG background. |

---

## Key Dependencies

| Dependency | How used | Loaded |
|---|---|---|
| ACF Pro | Per-post fields, author linking | Must be installed separately |
| Splide | Sliders (solutions, testimonials, news, trust logos) | Globally via `wp_enqueue_style/script` |
| WordPress REST API | Resource filter AJAX, Load More | Native — no extra setup |
| `sessionStorage` | TOC expand/collapse state | Native browser API |
| `navigator.clipboard` | Copy link button in social share | Native browser API, with `execCommand` fallback |

---

## Developer Experience Features

### Patterns in Dashboard Menu

A **Patterns** top-level menu item in the WordPress dashboard sidebar links to:
- **Synced Patterns** — the `wp_block` post type list view
- **Theme Patterns** — the Site Editor patterns view
- **Add New** — create a new synced pattern

Configured in `inc/show-patterns-in-menu.php`.

### Header/Footer Edit Buttons

When logged in, hovering over the site header or footer reveals an "Edit Header" / "Edit Footer" button that links directly to the relevant template part in the Site Editor. Configured in `inc/header-footer-edit-buttons.php`.

### Rename Posts to Blog

"Posts", "Post", and related labels throughout the WordPress admin are renamed to "Blog" and "Blog Post". Configured in `inc/rename-posts-to-blog.php`.

---

## Design Tokens

Design tokens are defined in two places:

**`theme.json`** — WordPress-native tokens consumed by the block editor:
- Color palette
- Font sizes (named scale: `small`, `medium`, `large`, `x-large`, `2x-large`, `display`, `display-large`)
- Spacing scale
- Shadow presets

**`:root` in `momentive.scss`** — CSS custom properties for use in SCSS:
- `--accent-color`, `--light-accent-color`, `--extralight-accent-color`, `--superlight-accent-color`
- `--button-background` (orange `#f26522`)
- `--alert-background` (dark navy, used in announcement bar and dark cards)
- `--shadow`, `--menu-border-color`, `--text-color`, `--light-text-color`

Where possible, `theme.json` tokens are referenced in SCSS via their generated custom property names (e.g. `var(--wp--preset--color--primary)`).

---

## Known Limitations & To Do

- **Featured blog posts** — the blog archive's "Featured" section queries by a `featured` tag. Manual ordering of featured posts is not yet implemented; see `functions.php` for notes on an ACF options page approach.
- **Resource Hub filters** — multiple post types cannot be queried simultaneously via the standard REST API. A custom endpoint is needed for "All Resources" queries that span CPTs. Currently the filter defaults to the configured post type.
- **ACF local JSON** — field group JSON export is not yet set up. Add `/acf-json/` directory and enable in ACF settings to version-control field definitions.
- **Megamenu** — the header navigation uses the native WordPress Navigation block, which has limited support for complex dropdown layouts. A custom navigation block may be needed for megamenu requirements.
- **`icon-shuffle` block** — documentation incomplete; add description and any ACF dependencies to this README.
- **`icons` CPT** — slug and description not confirmed; update Post Types section above.
- **Tailwind** — not yet implemented; adding a utility framework would create two parallel styling systems.
- **Reading progress bar** — currently loads on `is_singular('post')` only. Extend to `press-article` if needed by changing the conditional in `functions.php`.
- **`swoop-double` path** — the `swoop-double` shape in the swoop underline JS uses a single `d` string with two `M` commands rather than an array; verify this renders correctly across browsers.