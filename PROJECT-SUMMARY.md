# Momentive Website Rebuild — Project Summary

*A readable overview for the team. For full technical detail, see [CLAUDE.md](CLAUDE.md) in the theme repo.*

---

## What we're doing

Rebuilding momentivesoftware.com from a legacy Elementor + JetEngine + Crocoblock stack to a native WordPress Full Site Editing (FSE) block theme. The new theme lives at `wp-content/themes/momentive/` in the repo.

Goals: faster site, cleaner codebase, no legacy page-builder dependencies, content that's maintainable without deep plugin knowledge.

The governing principle: **native WordPress blocks first.** Custom blocks only when native blocks can't do the job.

---

## What's been completed

### Theme infrastructure
- FSE block templates + template parts for all main routes
- SCSS design system (typography, spacing, color tokens via `theme.json`)
- Icon system — auto-discovers SVGs in `assets/icons/`, no registration step
- Megamenu (5 panels, each an FSE template part)
- Announcement bar (cookie-dismissable, configurable via filter)
- Blocks: accordion, breadcrumbs, table of contents, social share, resource filters, product marquee, HubSpot form, person card + lightbox, animated stat counter, and more

### Custom post types built and populated

| CPT | Posts | Status |
|---|---|---|
| Solutions | ~20 | Done |
| Products | ~15 | Done |
| Case Studies | 156 | **Migrated** via script |
| Webinars | 149 | **Migrated** via script |
| Testimonials | ~130 | Created during Case Study migration |
| FAQs | ~50 | Done |
| Press Articles | ongoing | Done |
| Blog | ongoing | Done |
| People | ~60 | Done — unifies team, authors, and presenters |

### Webinar details
- Upcoming vs. on-demand state is automatic: the site reads `form_upcoming` or `form_ondemand` based on whether the webinar date has passed, so forms update on their own when a webinar goes live or ends.
- Presenter profiles are linked People posts (the same profiles used for blog bylines and the Our Team page).
- Video embeds (Wistia) are stored per-post and render on on-demand pages.

---

## How migration scripts work

All content migrations follow the same pattern:

**Tools needed:** WP-CLI access to the rebuilt site, a WXR export file from the legacy site.

**The script reads the export file** — it never touches the legacy database. The legacy site stays live and untouched until we're ready to cut over.

```bash
# Always dry-run first (safe default — no writes, just logs what would happen)
wp eval-file migrations/migrate-webinars.php --user=admin

# Live run when ready
wp eval-file migrations/migrate-webinars.php live --user=admin
```

- `--user=admin` is always required (media uploads need admin capability)
- Re-running is safe — scripts upsert by slug, so posts update in place rather than duplicating
- Every migrated post gets a `_momentive_migration_run` timestamp for rollback identification
- The run ends with a summary of anything unresolved (missing images, unmatched relationships, etc.)

### Scripts currently in `migrations/`

| Script | Purpose |
|---|---|
| `migrate-case-studies.php` | Migrates 156 case studies from legacy `case_studies` CPT |
| `migrate-webinars.php` | Migrates 149 webinars from legacy `webinars` CPT |
| `patch-webinar-images-excerpts.php` | Patches featured images + excerpts on already-migrated webinars (run once after initial migration) |
| `patch-case-study-dates.php` | Restores original post dates on already-migrated case studies |

### What happens during migration
For each post the script:
1. Strips MS Word formatting cruft from legacy WYSIWYG fields
2. Converts legacy HTML to proper blocks (paragraphs, headings, lists, tables, quotes)
3. Sideloads images into the rebuilt media library (deduplicated — re-running won't re-download)
4. Matches legacy relationships (products, people, categories) to rebuilt posts
5. Assembles the full block content scaffold
6. Preserves original publish and modified dates

---

## What's coming up

### Gated content CPTs (work like webinars)
These follow the same pattern as webinars: a registration form that swaps to a recording after the event. A shared migration approach should cover most of them.

- Whitepapers
- Guides & Research
- Toolkits
- Infographics

### Product Overviews
**Not a new CPT** — these will extend the existing `product` post type with a toggle that enables upcoming/on-demand fields (same pattern as webinars). On the legacy site they live under product URLs (e.g. `/solutions/career-centers-software/ym-careers/overview/`). Video embed comes from a corresponding `assets` post, same as webinars.

### Videos
Only 3 posts on the legacy site. No form — just a "watch now" button. Likely folds into webinars or a minimal standalone CPT.

### CPTs that don't need to migrate as CPTs
- **Industries** — these are pages, not a CPT. Migrate as standard WordPress pages.
- **Award Recipients** — 4 posts built for one page (`/bring-on-better-awards/`). Fold into a block pattern, retire the CPT.

### CPTs to retire (no migration)
- **Assets** — already folding into other types (`video_embed_code` now lives on Webinars and will on Product Overviews)
- **Clients** — already marked "To be removed" on the current site

### CPTs to evaluate (decisions needed)
| CPT | Question |
|---|---|
| Events | Needed? Scope? |
| Video Testimonials | Fold into `testimonial` CPT with a video type? |
| Interactive Tools | Keep as CPT or retire? |
| Landing Pages | CPT or just pages with a template? |
| Integrations | Keep? |
| Donation Examples | Keep? |
| Reviews | Keep? |

### Legacy dashboard items to evaluate
These still appear in the legacy admin and need a keep / replace / remove decision:

| Item | Notes |
|---|---|
| Rank Math SEO | Keep, or replace with Yoast? |
| Site Configuration | Review what this controls |
| Product Settings / Solution Settings | Review what these control |
| Crocoblock | Legacy page builder dependency — retire |
| SEO Sheets | Evaluate |
| Publish Press Future | Evaluate |
| Capabilities | Evaluate |
| Site Documentation | Migrate or retire |
| Maintenance Reports | Evaluate |
| Thermometer | Evaluate |
| 500 Designs Toolkit | Folding into new theme |

---

## Key things to know

**One People profile per human.** Team members, blog authors, and webinar presenters are all the same post type (`people`). A role taxonomy (`leader`, `author`, `presenter`) is non-exclusive — someone can be all three. This means presenter profiles on webinars are the same profiles as the Our Team page.

**Blog bylines don't use `post_author`.** The byline field (`post_author_ref`) points to a People post. This allows multiple developers to publish under a shared "Momentive Software" byline.

**The legacy site stays live.** Migration scripts read from export files, not the live database. Nothing about running a migration touches the current site.
