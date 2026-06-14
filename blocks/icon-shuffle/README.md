# Icon Shuffle Grid – Custom Gutenberg Block

A Gutenberg block that displays a grid of small icon thumbnails cycling through
a pool of images. One cell swaps its image every N milliseconds (default 350ms),
so with 14 cells each cell changes roughly every ~5 seconds on average.

---

## File structure

```
icon-shuffle/
├── block.json          # Block registration metadata
├── edit.js             # Editor (Gutenberg) component
├── editor.css          # Editor-only styles
├── style.css           # Frontend + editor shared styles
├── view.js             # Frontend animation script
├── render.php          # Server-side render callback + block registration
└── README.md
```

---

## Installation

No build step required. All files are plain PHP, CSS, and JavaScript.

1. Place the `icon-shuffle/` folder inside your plugin's `blocks/` directory,
   e.g. `/wp-content/plugins/my-plugin/blocks/icon-shuffle/`.

2. Require `render.php` from your plugin's main file — that's all:

   ```php
   require_once plugin_dir_path( __FILE__ ) . 'blocks/icon-shuffle/render.php';
   ```

   `render.php` handles block registration and enqueues all scripts and styles
   itself via `wp_register_script` / `wp_register_style`.

3. That's it. The block will appear in the inserter as **Icon Shuffle Grid**.

---

## Block attributes

| Attribute            | Type    | Default | Description                                              |
|----------------------|---------|---------|----------------------------------------------------------|
| `images`             | array   | `[]`    | Pool of icon objects `{ id, url, alt }`                  |
| `columns`            | integer | `5`     | Number of grid columns                                   |
| `cellCount`          | integer | `14`    | Visible cells (must be < pool size)                      |
| `cellSize`           | integer | `24`    | Cell width & height in px                                |
| `interval`           | integer | `350`   | Ms between each individual cell swap                     |
| `transitionDuration` | integer | `600`   | Ms for the crossfade (should be ≤ interval)              |

---

## How the animation works

- On load, JS reads the config from a `data-icon-shuffle-config` attribute.
- Images are split into **active** (displayed) and **offstage** (waiting).
- Every `interval` ms, one random active cell swaps with one random offstage image.
- A CSS opacity crossfade (two stacked `<img>` layers per cell) handles the transition.
- If the pool exactly equals the cell count, the JS falls back to swapping two
  active cells with each other so something always changes.

---

## Timing reference

| Interval | Swaps/sec | Avg. time per cell (14 cells) |
|----------|-----------|-------------------------------|
| 250ms    | 4.0       | ~3.5 sec                      |
| 350ms    | 2.9       | ~4.9 sec                      |
| 500ms    | 2.0       | ~7.0 sec                      |

The `transitionDuration` should comfortably fit within the `interval`. At 350ms
interval and 600ms transition the crossfade would overlap the next tick — reduce
`transitionDuration` to ~250ms if using the 350ms interval, or keep it at 600ms
and raise the interval to 750ms+.
