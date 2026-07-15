# momentive/product-marquee

Two auto-scrolling rows of product cards drawn from the `product` CPT.
Row 1 scrolls left, row 2 scrolls right. Both rows pause on hover (or touch).

## Dependencies

- **Splide** core, **AutoScroll** extension, and **Intersection** extension must
  be loaded before `view.js`. These are already bundled in `sliders.bundle.js`.
- **ACF Pro** — the render callback reads these fields per product post:
  - `product_logo_unendorsed` (image — preferred; the full horizontal wordmark)
  - `product_icon` (select → icon slug from the theme icon system — fallback only)
  - `accent_color` (hex color string)
- **Theme icon system** (`icons.php`) — `momentive_use_icon()` must be
  available so the footer sprite includes the right symbols (fallback path only).

## Card rendering: logo vs. fallback

Each card prefers the `product_logo_unendorsed` image field — this matches the
live site, where marquee cards are full wordmark logos, not icon+text pairs.

If a product hasn't had a logo uploaded yet, the card falls back to the
icon + title-text treatment (the `product_icon` slug + post title). This keeps
in-progress products usable in the marquee during content migration rather than
rendering nothing. Once all products have logos uploaded, every card should
render via the image path and the fallback becomes dead code in practice —
safe to leave in for resilience, but don't expect to see it once migration
is complete.

## Files

| File | Purpose |
|---|---|
| `block.json` | Block metadata; no configurable attributes |
| `block.php` | `register_block_type()` + `render_callback` |
| `view.js` | Splide init (frontend only) |
| `style.css` | Card styles + `--product` color-mix() derivatives |
| `editor.js` | Minimal placeholder for the block editor |
| `editor.css` | Placeholder styles |

## Registration

Call `require_once` (or `include`) on `block.php` from your plugin's main file,
or add it to the block-loader loop if you have one. No build step required.

## Adding the Product CPT

The render callback queries `post_type => 'product'`. Make sure the CPT is
registered before `init` priority 10 so the query finds posts. Duplicate the
Solutions CPT registration and rename accordingly, adding:

- `product_logo_unendorsed` — Image field, returning the array format (`url`, `alt`)
- `product_icon` — Select field, choices populated by `acf/load_field/name=product_icon`
  (same pattern as `solution_icon` in `icons.php`) — used only as a fallback
- `accent_color` — Color picker field (can share the same field name as Solutions
  since they're on different post types)

## Shuffle behaviour

Products are shuffled server-side on each page render (`shuffle()` in PHP).
Row 1 gets the first ceil(n/2) products, row 2 gets the rest. With 24 products
that's 12 per row. The distribution changes on every page load, so there's no
fixed ordering to maintain.

## Hover pause coordination

`view.js` coordinates hover-pause across both rows: entering either row pauses
both Splide instances via `Components.AutoScroll.pause()` / `.play()`. This
prevents the jarring effect of one row continuing to move while the other is
stopped.

## Styling notes

Each card carries `--product` as an inline custom property (the raw accent hex).
The following derivatives are computed in `style.css` via `color-mix()`:

- `--product-dark`    — 20% darkened (for text/borders at contrast)
- `--product-bg`      — 85% lightened (icon background)
- `--product-bg-soft` — 94% lightened (card hover background)

Adjust the mix percentages in `style.css` to match the visual weight of the
solution card derivatives.
