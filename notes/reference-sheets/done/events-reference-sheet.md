# Events Rebuild — Reference Sheet (2 posts)

Decoded content and architecture notes for the legacy `events` CPT. Resolves the "scope unclear" open question from `PROJECT-SUMMARY.md`/`notes/todo.txt`.

**Source export:**
- `migrations/momentivesoftware.events.current.2026-07-27.xml` — 58 items: 56 attachments + 2 `events` posts (1 draft, 1 published). Only 2 posts exist on the legacy site for this CPT.

**What this is — and why it's not one thing:** the two posts that exist are wildly different in scope, and that difference is the real finding here.

| | Momentive Roadmap Summit (draft, #6988) | Connect with us at AFP ICON! (published, #9464) |
|---|---|---|
| Format | 3-day virtual multi-session summit | In-person conference booth micro-page |
| Content volume | ~690KB of content/markup, Elementor-built, 10 major sections, a 3-day tabbed session schedule with 15+ individual sessions across sub-lists | A few short sections: hero, one overview block, a 3-item accordion, booth number, event dates, a short CTA |
| Sections used | Hero, Overview, Accordion, Feature (MomentiveIQ debut), Sessions Carousel (10 items), Social Proof Logos, Tabbed Schedule (3 day-tabs × up to 6 sessions each), Form CTA, Bottom Carousel | Hero, Overview (with a list of "event details" — booth number, come-play-games copy), Accordion (3 items — but see below), Bottom Carousel |
| Registration | Full HubSpot form embed for session registration | No form at all — just an outbound link to a third-party registration site (`register.gtrnow.com`) |

**One CPT, two completely different real-world shapes** — same conclusion the Solutions migration reached about hub-tier vs. child-tier pages, and the same conclusion already reached for `lp` (see `notes/landing-pages-reference-sheet.md`): a single flexible field group with dozens of optional sections, where any given post uses a small, different subset.

---

## The field group is a full landing-page builder, not an "event details" schema

Roughly 20 optional sections exist as fields (hero, overview, accordion, feature, sessions carousel, social proof logos, tabbed schedule, form CTA, bottom carousel, clickable box, outlined carousel, event-details block), each gated by its own `enable_*` boolean, exactly like the `momentive/solution-resources`/`event_sub_*` per-section pattern the Solutions migration already handles. **This is a distinct field group from `lp`'s** (different section names, different field prefixes) — the two CPTs independently arrived at the same "flexible content, one enable-flag per optional module" architecture rather than sharing one.

**Two event-specific fields that don't exist on `lp`:**
- `event_type` (`virtual` / `physical`) and `event_status` (`upcoming`) — the closest thing to a structured "what kind of event is this" field.
- `webinar_date`/`webinar_end_date`/`webinar_timezone` (borrowed field names from the Webinar Settings group) for virtual events, vs. `event_dates__config` (a JSON blob: `{"dates":[{"date":"2026-04-26",...,"end_date":"2026-04-28"}]}`) + `event_location` + `event_booth` for physical events. **These two date mechanisms are not unified** — a migration/rebuild needs to read whichever one is populated, not assume one schema.

---

## Real content quality issue on the one published post

The published AFP ICON post's `accordion_items` field contains **literal placeholder/test copy still live on the site**: item titled "Bro, this is a test" with description "Did you know that this test is basically a really dumb thing?" This is not a migration artifact — it's in the legacy WXR export as the actual current content of a `publish`-status post. Flag for Daniel/Greg to fix on the legacy site regardless of the rebuild timeline, and don't migrate this section's copy verbatim — it needs real content or the section disabled.

---

## Recommendation: build as pages with reusable patterns, not a CPT

Two posts, radically different shapes, one with placeholder content — this is a weaker case for a dedicated CPT than any other pending type in this project. The right-sized version of "native blocks first, custom blocks only when native can't do the job" here:

1. **Rebuild each event as a normal WordPress page**, same decision already made for Industries.
2. **Extract the section types that repeat across event pages into block patterns** — a hero, an overview band, an accordion section (reusing `momentive/accordion`, already unregistered from native and built for exactly this), a sessions/schedule grid, a social-proof logo strip, a closing CTA. These patterns then serve *any* future event page, virtual or physical, without forcing every event through one rigid field schema that has to grow a new optional section every time an event's format differs from the last one.
3. **The multi-day tabbed schedule (Roadmap Summit only) is the one piece worth a small custom block** if a future summit-scale event recurs — everything else here is well within native blocks (columns, group, buttons, accordion) plus the existing `acf/hubspot-form` block for registration.

This mirrors the Landing Pages recommendation in spirit (patterns over a monster field schema) but lands in a different place because of scale: `lp` has 166 posts clustering into ~4 real shapes (worth a migration script), while `events` has 2 posts in 2 unrelated shapes (not worth a script — just rebuild both by hand from the patterns above).

---

## Open questions before building

- **Fix the placeholder accordion copy on AFP ICON** before or during rebuild — it's live on the current site.
- **Unify or explicitly branch on the two date mechanisms** (`webinar_date`/`_end_date`/`_timezone` for virtual vs. `event_dates__config`/`event_location`/`event_booth` for physical) if any future event patterns need to read a date programmatically (e.g., to auto-hide past events) — not urgent with only 2 posts, but worth deciding before a third event is built by hand and a fourth pattern-shape emerges.
- **Confirm whether more `events` posts exist that weren't captured in this export** — the file is large (994KB) for containing only 2 posts, almost entirely attachments; worth a legacy admin-list count to be sure this is the full corpus, same caution raised for Video Testimonials.
