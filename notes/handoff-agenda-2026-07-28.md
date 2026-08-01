# Handoff meeting with Greg — July 28, 2026

One hour, before parental leave starts. Goal: Greg leaves able to keep the project moving without me — not just informed, but having actually driven one migration build himself.

---

## 1. Where things stand — and where Greg might focus (15 min)

- `PROJECT-SUMMARY.md` (repo root) — readable overview, what's done, what's next. Point Greg here first if he forgets everything else.
- `CLAUDE.md` (repo root) — the real reference. Every convention, gotcha, and past decision is written down here specifically so neither of us has to be in the room for the other to work correctly. When in doubt, it's the source of truth, not memory of a conversation.
- `notes/todo.txt` — the working scratch list. Slightly messier than the other two, but has the open questions for Colleen/PM and the full list of not-yet-decided CPTs.

**Rather than assign Greg a specific task, walk through the "What's next" roadmap from
`momentive-progress-report-2026-07-28.pdf` (full rationale in
`notes/reference-sheets/recommended-sequence.md`) and let him pick where he wants to spend
his attention. The phase order below is a recommended default sequence, not a requirement —
any of these is a legitimate place to start, and it's his call which he's most comfortable
owning solo:**

| Phase | What it is | Why it might appeal to Greg |
|---|---|---|
| **0 — Decisions first** | Fold Reviews + Video Testimonials into `testimonial`? Confirm the Videos CPT approach. Pick a Toolkits card-grid model. Kick off the marketing ask for Landing Pages' active-campaign list (it has its own turnaround time — worth starting regardless of what else he picks). Check `parts/megamenu-who-we-serve.html`'s links (confirmed stale — see below). | Cheap, unblocks everything else, mostly judgment calls rather than build work — good if he wants to get oriented before committing to a bigger build. |
| **1 — Quick wins** | Integrations, Award Recipients, Donation Examples, Videos, Reviews, Video Testimonials — small, uniform CPTs, sheets are ready, no blockers once Phase 0 lands. | Parallelizable, doesn't need me in the room, good for building confidence with the migration-script pattern beyond just Toolkits. |
| **2 — Gated-content family** | Toolkits (today's live-build subject), then the Product Overview CPT + its own archive-page request right after. | Natural follow-on from what we build together today — same gated shell, still fresh. |
| **3 — Bespoke hand-built pages** | One shared pattern library (hero, stats, accordion, features, testimonials, FAQ, CTA, carousel), reused across Pages, Who We Serve, Events, Interactive Tools. Who We Serve's Nonprofit page just got a concrete Figma spec and jumped the queue — see `who-we-serve-reference-sheet.md`. | More design/layout-driven than script-driven — a different kind of work if he wants a change of pace from writing migration PHP. |
| **4 — Landing Pages** | Biggest item (166 posts, 143 collapse into one template) — deliberately last, once marketing's active-campaign list is in hand. | Only worth picking once Phase 0's marketing ask is answered and the Phase 3 pattern library exists to draw from. |
| **5 — Pipeline feature additions** | Net-new Asana requests with no legacy equivalent: announcement bar CMS/scheduling, author bio pages + Heather Noll byline fix, Product Overview archive page, Who We Serve parent-page updates — plus webinar/series template updates and megamenu updates still queued. See `notes/pipeline-features/`. | Genuinely new feature work rather than migration — appealing if he'd rather build something net-new than move legacy content. |
| **6 — Anthropic API improvements** | See `notes/anthropic-api-roadmap.docx` for the full writeup. | Different again — worth a look if he's interested in the AI-assisted tagging side of things (there's already a working example in `inc/resource-relevance.php`). |

None of these needs me in the room the way today's Toolkits walkthrough does — that's the point of spending time on this now.

## 2. Live build: Toolkits migration (25 min) — the main event

Working session, not a demo — Greg drives, I navigate. Goal: get a real `migrate-toolkits.php` started (doesn't need to finish in the hour) so he's touched the pattern once with me there to catch mistakes.

**Setup:**
- Open `notes/reference-sheets/toolkits-reference-sheet.md` — full field map, both layout variants, per-post summary for all 6 posts. This is the spec; don't re-derive it from the XML live, we already did that work.
- Open `migrations/migrate-infographics.php` side by side — closest existing template, since infographics also branch on a boolean (gated vs. ungated) into two different section sets, same shape as toolkits' `toolkit_type` (`buyers-guide` vs. `standard`).

**Walk through, in order:**
1. Positional-flag arg parsing + dry-run-by-default convention (copy from `migrate-infographics.php` almost verbatim — every migration script in this repo uses the same `$args` scope-capture trick).
2. The shared gated shell: `resource_details`, `resource_checklist`, `hubspot_form_code`, `form_heading` — same extraction functions as every other gated CPT, no new code needed, just reuse.
3. The branch: `toolkit_type == 'buyers-guide'` → walk `toolkit_sections` → follow `linked_accordion` → pull the matching `accordion_N_items` → build `momentive/accordion` blocks. `toolkit_type == 'standard'` → build the `webinar_tools` card grid instead.
4. **Have Greg write the `wp_slash()` wrap on the final `wp_update_post()` call himself** — this is the single most common real bug in this codebase (bit whitepapers once already, see CLAUDE.md's "wp_slash gotcha" section) and the best way for it to stick is for him to write it once, not just read about it.
5. Point out the two open questions flagged in the reference sheet (card-grid relationship model; duplicate RFP-template card copy) — these are exactly the kind of judgment call that comes up mid-migration and needs a real decision, not a mechanical answer.

**If time allows:** run the dry-run against the real export (`migrations/exports/momentivesoftware.toolkits.current.2026-07-16.xml` — exports live in their own subfolder now, see item 5 below) and look at the log output together.

## 3. Migration gotchas worth repeating out loud (5 min)

Quick verbal pass — these are all in CLAUDE.md, but worth saying once so Greg knows they're not hypothetical:

- **`wp_slash()` before any `wp_update_post()` that writes block markup with JSON in it.** Whitepapers broke silently on the front end from missing this once already.
- **ACF block field keys must be in the block comment's `data` object**, or the block renders blank on the front end while still looking fine in the editor preview. Bit `linked-products` once.
- **`--user=<admin>` is required on every migration run** — Safe SVG's capability gate fails logo sideloads without it, silently-ish (the error is there, but easy to miss in a long log).
- **Dry-run is the default on every script, on purpose** — a forgotten flag caused a real accidental live run once. Never remove that default to save a keystroke.
- **Push the site to WP Engine right before running a migration** - this ensures there's a pre-migration backup. I've often pulled the WPE version back down so I could modify the migration script then re-run.
- **Inside FSE templates, use the ACF-provided `$post_id`, not `get_the_ID()`.** Blocks render outside the main query loop.

## 4. What's open while I'm out (10 min)

Walk through the current open-questions list so Greg knows what's a real decision point vs. what's just unfinished:

- **Reference sheets exist for every remaining post type now** — nothing is blocked on a missing export anymore:
  - **Fully migrated, sheet done, in `notes/reference-sheets/done/`:** guide, webinar, case-study, posts, whitepaper, infographic.
  - **Sheet ready, not yet built, in `notes/reference-sheets/`:** toolkits, videos, reviews, product-overview, press-article, pages, award-recipients, donation-examples, integrations, interactive-tools, landing-pages, who-we-serve, events, video-testimonials. That's 14 — the last 8 (award-recipients through video-testimonials) came together after I got exports for them from Daniel; there's no more "still need an export" list.
  - `notes/reference-sheets/recommended-sequence.md` ties all of these together into the phased order used in item 1 above.
- **Real architectural decisions sitting in the reference sheets, not yet made:**
  - Product Overview: convert `linked_product` from text to Post Object, derive the permalink from it — recommendation is written up, just needs a build. A Product Overview *archive page* request also just came in via Asana and rides on this same decision — see `notes/pipeline-features/product-overview-archive.md`.
  - Videos: fold into a minimal standalone CPT reusing the shared Recording field group (not into `webinar` — too much unused apparatus). Only 3 posts; small build.
  - Reviews: fold into `testimonial` via `testimonial_type`, or keep as its own CPT? Worth deciding alongside the Video Testimonials question — same underlying "is `testimonial` the universal home for short endorsements?" call.
  - Toolkits: card-grid entries are hand-typed labels today — keep faithful-to-legacy, or upgrade to real Post Object references into `webinar` so labels can't drift?
  - Who We Serve: confirmed the same content as the "Industries" line in `PROJECT-SUMMARY.md`/`notes/todo.txt` — hand-build from patterns, don't script it. `parts/megamenu-who-we-serve.html` is currently pointing at placeholder URLs (`/solutions/momentiveiq/`, `/products/aptify/`), not real Who We Serve pages — worth fixing alongside the page builds, not as a separate ticket.
  - Landing Pages: 143 of 166 collapse into one template, but the active-campaign list needs to come from marketing before scripting — that ask should go out soon regardless of what else gets picked up first.
  - **Also now on the table — four Asana tickets with no legacy equivalent at all:** announcement bar CMS/scheduling, author bio pages + a Heather Noll byline fix, the Product Overview archive page above, and a Who We Serve Nonprofit-page redesign. See `notes/pipeline-features/README.md` for all four — these aren't migration work, so they don't have "reference sheets" the way the CPTs above do, but they're real open items.
- **Solutions migration still needs its second pass** (`migrate-solutions.php`) so sibling grids top up for posts processed early in the first run — see CLAUDE.md's writeup, this is a known follow-up, not a bug to chase.

## 5. Where everything lives (5 min)

- `notes/reference-sheets/done/*.md` — finished reference sheets, safe to build straight from.
- `notes/reference-sheets/*.md` (not in `done/`) — reference sheets still in progress or ready to build from, not yet promoted; `recommended-sequence.md` here ties them into the phased order from item 1.
- `notes/pipeline-features/*.md` — new as of this week: net-new Asana feature requests with no legacy content behind them, distinct from the migration reference sheets above. Start at `README.md` in that folder.
- `migrations/*.php` — every migration/patch/report script.
- `migrations/exports/` — the WXR/CSV exports those scripts read from, moved into their own subfolder for tidiness. Check here first before requesting a fresh export — several are already sitting in this folder unused.
- `acf-json/` — field group definitions, auto-synced. Never hand-edit; change in the ACF UI and let it write itself.

## 6. While I'm out

- Feel free to reach out on email (daniel.heller@momentivesoftware.com or danielkheller@gmail.com) or phone/text (323-240-5543). I'd expect little questions and mysteries may come up that I could resolve quickly. Much of the knowledge is in CLAUDE.md and the previous migration sheets, but there's so much documentation that it might be quicker just to ask.
