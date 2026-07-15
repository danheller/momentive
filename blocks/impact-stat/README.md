# momentive/impact-stat

Atomic custom block for a single animated statistic with a colored left border.

## Attributes

| Attribute           | Type    | Default     | Notes                                              |
|---------------------|---------|-------------|----------------------------------------------------|
| `statPrefix`        | string  | `""`        | Static text before the number. e.g. `$`, `1 in `  |
| `statNumber`        | number  | `0`         | The value that animates. Supports decimals.        |
| `statSuffix`        | string  | `""`        | Static text after the number. e.g. `M+`, `K`, `s` |
| `statLabel`         | string  | `""`        | Descriptor line below the stat value.              |
| `accentColor`       | string  | `#E8611A`   | Hex color for the left border.                     |
| `animationDuration` | number  | `1800`      | Count-up duration in milliseconds.                 |

## Accent color presets (editor palette)

| Name   | Hex       |
|--------|-----------|
| Orange | `#E8611A` |
| Purple | `#7B61FF` |
| Teal   | `#00C4B4` |
| Blue   | `#3B82F6` |

Custom hex values are also accepted via the color picker.

## Number formatting

- **Integers** (e.g. `1000`) → formatted with thousands separators: `1,000`
- **Decimals** (e.g. `35.5`) → one decimal place preserved throughout animation: `35.5`
- **Prefix/suffix** are always static; only `statNumber` animates

## Animation

- Driven by `IntersectionObserver` + `requestAnimationFrame` in `view.js`
- Triggers once when the block is 25% visible in the viewport
- Uses an ease-out cubic curve for a natural deceleration
- Respects `prefers-reduced-motion`: skips animation and shows final value immediately

## Build

```bash
npm install
npm run build   # production build → build/
npm run start   # watch mode
```

Requires `@wordpress/scripts` ^27.

## File structure

```
impact-stat/
├── block.json
├── impact-stat.php          # require from functions.php
├── package.json
├── src/
│   ├── index.js             # block registration
│   ├── edit.js              # editor component
│   ├── save.js              # static save output
│   ├── view.js              # frontend animation
│   ├── style.scss           # shared (front + editor)
│   └── editor.scss          # editor-only
├── build/                   # compiled output (gitignored)
└── patterns/
    └── stats-centered-three-up.php
```

## Usage in functions.php

```php
require_once get_template_directory() . '/inc/blocks/impact-stat.php';
```

## Registering the pattern category

Add once to your theme's `functions.php` (or a dedicated patterns registration file):

```php
add_action( 'init', function () {
    register_block_pattern_category( 'momentive-stats', [
        'label' => __( 'Stats', 'momentive' ),
    ] );
} );
```

Then add to each pattern file's header comment:

```php
/**
 * Title: Stats — Centered Three Up
 * Slug: momentive/stats-centered-three-up
 * Categories: momentive-stats
 */
```
