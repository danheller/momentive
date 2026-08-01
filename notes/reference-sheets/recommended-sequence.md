# Recommended sequence for the remaining content types

Every remaining content type now has a reference sheet in this folder (or, for Pages, its own internal ordering already worked out — see the note at the end). This document is the layer above those sheets: what order to actually work through them in, and why. It doesn't repeat their content — read the sheet itself when you get to that item.

**Not trying to schedule the handoff meeting with this** — that's `../greg-handoff-agenda-2026-07-28.md`, and it's deliberately scoped to just Toolkits. This is for after: what Daniel and Greg (or whoever's picking things up) should reach for next, in what order, and why that order rather than another.

---

## Phase 0 — Decisions first (cheap, unblocks everything below)

None of these require writing code. All of them block a build phase further down, so resolve them early rather than discovering the blocker mid-script.

1. **Reviews + Video Testimonials — fold into `testimonial`, or keep separate?** Both reference sheets independently landed on the same recommendation (add a `testimonial_type` of `review`/`video`, reuse the existing CPT). Decide once, since it's really one question asked twice.
2. **Videos — confirm the minimal standalone CPT approach** (reusing the shared Recording field group, not folding into `webinar`). Reference sheet has the reasoning; this is a five-minute confirmation, not new analysis.
3. **Toolkits — card-grid relationship model.** Hand-typed `asset_name`/`asset_type` labels (faithful to legacy) vs. real Post Object references into `webinar` (labels can't drift). Worth deciding before or during the Toolkits build itself, since it's the one place that CPT introduces a genuinely new modeling choice.
4. **Landing Pages — get the active-campaign list from marketing/ads.** This is the one item on this whole list with an external dependency and its own calendar time, so start it now rather than when you're ready to actually build — by the time Phase 4 comes around, you want the answer already in hand, not a fresh ask.
5. **Who We Serve — check what `parts/megamenu-who-we-serve.html` currently links to.** There's a real chance this megamenu panel already expects the URLs this content needs to fill in. A five-minute check now avoids planning this as new work when it might be closing a loop that's already half-built.
6. **Route the content-quality issues found along the way to whoever owns legacy content, not to the migration backlog:** the published AFP ICON event page has literal placeholder accordion copy; 5 of 32 Donation Examples have lorem-ipsum summaries; two Toolkit RFP posts share verbatim card copy that should probably differ. None of these block a migration script, but none should be migrated verbatim either.

---

## Phase 1 — Quick wins (small, uniform, no remaining blockers once Phase 0 lands)

These don't need to happen in this exact order relative to each other, and don't need Greg specifically — anyone comfortable with a short WP-CLI script (or, for Integrations, possibly no script at all) can pick these off in parallel with everything else on this list.

| Item | Why it's quick |
|---|---|
| **Integrations** (62 posts) | Title + logo + two taxonomy terms, nothing else. No layout branching, no gating. |
| **Award Recipients** (4 posts) | Already scoped to fold into one hand-built pattern, not a script. |
| **Donation Examples** (32 posts) | One consistent card shape, two filter taxonomies, no gating. |
| **Videos** (3 posts) | Trivial once Phase 0 #2 is confirmed. |
| **Reviews** (86 posts) | Trivial once Phase 0 #1 is confirmed — mostly a name/date/rating/source mapping, content's already in clean blocks. |
| **Video Testimonials** | Same decision as Reviews; confirm the real post count first (this export only had 1). |

---

## Phase 2 — Gated-content family (do right after the Toolkits meeting, while the shell is fresh)

- **Toolkits** — the meeting's own live-build subject. Whatever state it's in after the session, this is naturally first.
- **Product Overview** — the most fully-specified reference sheet of anything not yet built (permalink architecture, recording hand-off, and the field-key gotcha are all already worked out). It reuses the exact same gated shell Toolkits will just have exercised, plus the `inc/recordings.php` groundwork already documented in CLAUDE.md. Doing this second, not fifth, means the gated-shell code is still fresh instead of needing to be re-learned later.

---

## Phase 3 — Bespoke hand-built pages (build the pattern library once, reuse it across all of these)

Everything in this phase shares the same real constraint: no migration script is worth writing, because each post/page is genuinely bespoke. What *is* worth building once is a small pattern library — hero, stats band (via `momentive/impact-stat`), accordion, feature-challenges row, testimonials, FAQ, closing CTA, carousel — since every item below draws from some subset of the same eight patterns.

1. **Pages** — already has its own internal ordering worked out in `pages-reference-sheet.md` (clusters A through F, roughly increasing effort). Don't re-derive that here; just start at cluster A. Cluster D's "audience/vertical hub" pages (event-success templates) and cluster F's flagship pages are where most of the pattern-library payoff shows up.
2. **Who We Serve** — pair with the `/who-we-serve/` index page from Pages cluster F (same section, two different post types making it up). 6 real posts, each hand-built from the pattern library above.
3. **Events** — only 2 posts, 2 unrelated shapes; quick once the pattern library exists.
4. **Interactive Tools** — the quiz landing+tunnel pair folds into whichever Landing Page patterns exist by Phase 4 (they share a field-group family); the Fundraising Thermometer is blocked on exporting its linked Elementor template (post 5559) — do that export before this one.

**Two more page/CPT pairings worth knowing about, surfaced while cross-checking `pages-reference-sheet.md` against this batch:** the `reviews-2` page and the `path-lms-integrations` page are very likely the actual display pages for the Reviews and Integrations CPTs respectively (a filterable grid page hosting the CPT content) — build the CPT and its hosting page together, not as unrelated tickets.

---

## Phase 4 — Landing Pages (last, on purpose)

166 posts, the largest single item on this list — but not the hardest, because 143 of them collapse into one real template (BOF/MOF) once you exclude samples/duplicates. Doing this last means:
- Phase 0 #4's marketing input has had time to come back.
- The pattern library from Phase 3 already exists, and several LP sections (hero, CTA band, testimonials) overlap with it.
- Everything smaller and more decision-blocked is already cleared, so this phase can get uninterrupted focus rather than competing with quicker items.

Within this phase, its own reference sheet already recommends: script the 143 BOF/MOF product pages, script the 9 Competitor Comparison posts as one more branch of the same script, hand-build the handful of true one-offs (TOF pages, referral/marketplace pages).

---

## Not covered by this document (tracked elsewhere already)

- **Solutions' second migration pass** (topping up sibling grids for posts processed early in the first run) — already logged in CLAUDE.md's Known Limitations, not a new-content-type item.
- **Press Article** — `migrate-press-articles.php` and its reference sheet both look complete as of this writing; confirm and move the reference sheet into `done/` if so, rather than treating it as open work in this sequence.
