# Solutions rebuild review — 2026-07-21 export

Review of `migrations/momentive.solutions.rebuild.2026-07-21.xml` against
`migrations/solutions-migration-coverage.xlsx` and the legacy export. Covers:
current rebuild progress, the two flagged missing blocks, a slug-integrity
check, and spot-checks on the title/excerpt/icon/parent mapping decisions.

## 1. Progress snapshot (corrected)

`migrations/rebuild.csv` (generated 2026-07-20 19:41, from `report-rebuild-progress.php`)
reported only 6 Solutions posts as "rebuilt." That undercounts real progress —
**4 genuinely-rebuilt posts were misclassified as `import_remnant`**
(financial-management-operations/4385, text-to-donate/6885,
fundraising-online-giving-and-payments/6887, ticketing/6888) because they
still carry leftover `_elementor_data`/`_elementor_edit_mode` postmeta from
the original WP Import, even though their `post_content` is now real
Gutenberg blocks. The script checked for stale Elementor meta *before*
checking for real block content — fixed by reordering the check in
`migrations/report-rebuild-progress.php`'s `momentive_rebuild_report_classify()`
(block content now wins regardless of what legacy meta lingers). Re-run the
report to get a fresh, accurate `rebuild.csv`.

**Corrected count: 10 of 87 in-scope child solutions are actually rebuilt.**

By family:

- **Accounting** — rebuilt: Financial Management Operations (4385). import_remnant: Audit-ready Security and Compliance (4406), Budgeting and Forecasting Software (4375), Payroll and People Management (4421), Reporting/Mission Intelligence & Insights (4397), Workflows/Automation/Team Efficiency (4412). Not started: Nonprofit Accounting Software (11216), Nonprofit Payroll Software (10183), School Accounting Software (10198).
- **Association Management** — rebuilt: Events (6295), Member Management (6308). Empty shells: AI & Automation (6255), Accounting/Financial (6363), CRM (6357), Member Engagement (6348), Member Portal & E-Commerce (6339), Professional Services (6369), Reporting & Analytics (6298), Aptify (6221), Cobalt (6073), NetForum (6228), Nimble (6215), YourMembership (6223).
- **Career Center** — import_remnant: all 7 (Career Development Tools 6588, Events and Networking 6586, Implementation & Services 6568, Job Board 6569, Job Board Integrations 6590, Non-Dues Revenue 6556, Recruitment Marketing 6589). Not started: Association Job Board Software (10374).
- **Certification Management** — import_remnant: AI and Automation (5345), Application and Eligibility Management (5415), CRM/Microsoft Dynamics (5419), Integrations (5418), Renewal Management (5416). Empty: Compliance/Verification/Reporting (5428), Credits and Certificates (3411).
- **Donor Management** — import_remnant: all 6 (Data and Segmentation 7611, Engagement and Stewardship 7610, Management and Retention 7607, Gift Tracking 7608, Reporting and Analytics 7609, GiveSmart CRM Integrations 7612).
- **Event Management** — rebuilt: Abstract Management (2488). import_remnant: Lead Capture (2484). Empty: Agenda Builder & Speaker Management (2486), Attendee Tracking (2483), CE Credit Claiming (2485), Check-in and Badging (2428), Event Apps (2443), Event Registration (2397), Session Room & Vendor Management (2487). Not started: Association Event Software (10688), Conference Management Software (10477), Nonprofit Event Software (10173), Trade Show Management Software (10730).
- **Fundraising** — rebuilt: Online Giving and Payments (6887), Text to Donate (6885), Ticketing and Guest Management (6888). import_remnant: Auctions & Mobile Bidding (6886), Campaign and Event Management (6892), Custom Forms (6890), GiveSmart Integrations (6889), Peer-to-Peer (7241), Raffles/Voting/Item Sales (6891). Not started: Compliance Software (11266).
- **Learning Management** — rebuilt: Course Creation (3390), Learner Engagement (3273). Empty: AI-Powered Learning (3245), E-Commerce (3432), Extended Membership (3454), Integrations (3553), Learning & Development (3791), Live Events (3400), Quizzes and Assessments (3443), Reporting and Analytics (3421), Virtual Events & Production (3480). Not started: Association LMS (10267), Healthcare (10345), Nonprofit LMS (10123).
- **Volunteer Management** — rebuilt: Volunteer Tracking and Reporting (6469). Empty: Database & Registration (6452), Onboarding/Background Checks/Compliance (6490), Recruitment and Communications (6492), Shift Sign-Up and Scheduling (6463), Volunteer Engagement and Project Management (6493).

"Not started" (12 posts, no matching post ID at all in the rebuild export —
these still need a post created) vs. "empty" (35 posts — a post shell
already exists, `post_content` is blank) is a useful distinction for
sequencing remaining work.

## 2. The two flagged missing blocks

Checked every one of the 10 currently-rebuilt posts against the legacy
`benefits_-_enable_benefits_media_section` (media collage, 17/87 legacy
children) and `image__text_2_cols_-_enable_section` (image+text 2-col,
18/87) flags. Only 3 of the 10 rebuilt posts are supposed to have either
section — **all 3 are missing what they should have**:

| Post | Should have | Has |
|---|---|---|
| Ticketing and Guest Management (6888) | Media collage + Image+Text 2-col | Neither |
| Text to Donate (6885) | Image+Text 2-col | Missing |
| Online Giving and Payments (6887) | Media collage + Image+Text 2-col | Neither |

No other currently-rebuilt post is affected (none of the other 7 are
flagged for either section in the legacy data), so there's nothing else to
go check right now — but revisit this once more Fundraising-family posts
get rebuilt, since the pattern above suggests whoever built these three just
didn't have a section to drop in for either block.

**That's very likely the actual cause**: neither section had an individual
pattern in `patterns/` (only the always-on Features media-text rows,
checklist/icon-grid Features Overview, demo form, hero, and accordion
patterns existed). I've now added the two missing ones:

- `patterns/solution-media-collage.php` — matches the "content-collage"
  markup already used on the rebuilt Fundraising hub page and generated by
  `migrate-solutions.php`'s `momentive_sol_benefits_media_block()`.
- `patterns/solution-image-text-2col.php` — matches
  `momentive_sol_image_text_2cols_block()`'s two-column shape.

Both are `templateLock:contentOnly` so the structure (collage image
classes, column split) can't drift from what the migration script generates,
while every heading/paragraph/image stays editable. You can now insert
these on 6888/6885/6887 by hand, or leave them for the eventual script run.

## 3. Bigger finding: legacy `post_name` doesn't match the real URL on 51/87 posts

Cross-checked every in-scope legacy child's `wp:post_name` against the last
path segment of its own `<link>` (the actual served URL at export time).
**51 of 87 (59%) don't match.** Examples:

- 6888 "Ticketing and Guest Management": `post_name` =
  `fundraising-ticketing-and-guest-management`, actual URL segment =
  `ticketing`.
- 6885 "Text to Donate": `post_name` is a completely unrelated stale slug,
  `career-center-implementation-services-duplicate` — actual URL segment is
  `text-to-donate`.
- Dozens more where `post_name` is the old long-form auto-slug and the real
  URL is a cleaned-up short form (`solutions-career-centers-software-development`
  vs. `development`, `data-and-segmentation-tools` vs. `segmentation`, etc.)
- A few live at a different top-level path entirely — 5419 and 5428 (both
  Certification Management) serve from `/certification-management/...`, not
  `/solutions/...`.

`migrate-solutions.php` was using `wp:post_name` verbatim for the rebuilt
slug, with no correction table (only `post_parent` had a hardcoded
override, `MOMENTIVE_SOL_FORCE_PARENT`, for one post). Since you've been
manually deriving the correct slug from the real permalink for every post
you've hand-rebuilt so far, running the script as-is on the remaining ~77
would very likely have produced wrong URLs for most of them.

**Fixed**: added `momentive_sol_slug_from_link()`, which derives the slug
from `<link>`'s path (falling back to `post_name` if the link is empty or
doesn't look slug-shaped), and wired it into `momentive_sol_load_legacy_posts()`.
The script now also logs how many posts got a corrected slug on each run.
`post_parent` resolution was already ID-based (via `wp:post_parent` →
`$rebuilt_parent_map`), not slug-derived, so hierarchy itself should already
be reliable — this fix is specifically about the slug string.

## 4. Title / excerpt / icon / parent decisions — spot-check results

Checked all 10 rebuilt posts against their legacy source.

**Title** (from `solutions_sub_card_title`): correct. Where legacy
`solutions_sub_card_title` was empty, the rebuild kept the original
(already-clean) post title rather than inventing something — correct
behavior, no bug.

**Excerpt** (from `event_sub_-_card_description`): matches on 8/10, but two
issues:
- **Financial Management Operations (4385)**: excerpt is empty even though
  legacy `event_sub_-_card_description` has real content ("Everything you
  need to streamline and master your day-to-day..."). Needs to be set by
  hand.
- **Member Management (6308)**: excerpt has a run of literal tab
  characters before the text (`\t\t\t\t...Attract, engage, and retain
  members...`) — looks like a paste artifact. Worth trimming.

**Icon** (`event_sub_card_icon` → `solution_icon`, box- prefix stripped):
correct on 9/10. One deviation:
- **Financial Management Operations (4385)**: legacy is `box-bx-money`
  (→ `bx-money`, which exists and is what every other post's mapping would
  produce), but the rebuilt post's `solution_icon` is set to
  `sol-productaccounting` — **not a file that exists in
  `assets/icons/`** (checked against `notes/icon-filenames.txt`'s 303
  entries). This will silently fail to render. Worth confirming whether this
  was a deliberate override (if so, the SVG needs to be added to
  `assets/icons/`) or should just revert to `bx-money`.

Separately, across all 87 in-scope legacy children (not just the 10
rebuilt), three `event_sub_card_icon` values have no matching file in
`assets/icons/` at all and will need either a new SVG or a substitute icon
chosen once those posts get rebuilt:
- `bxs-flag-checkered` — Nonprofit Event Software (10173)
- `bxs-school` — School Accounting Software (10198)
- `copilot` — AI and Automation (5345)

**Parent relationships**: `post_parent` resolution in the migration script
is ID-based, not slug/text-based, so it should already be robust — no
issues found in the 10 rebuilt posts checked (all resolved to the expected
hub-family parent).

## Files touched in this pass

- `migrations/report-rebuild-progress.php` — classification order fixed
  (block content checked before Elementor meta).
- `migrations/migrate-solutions.php` — added `momentive_sol_slug_from_link()`,
  slug now derived from `<link>` with `post_name` fallback, correction count
  logged at run time.
- `patterns/solution-media-collage.php` — new.
- `patterns/solution-image-text-2col.php` — new.

## Still to fix by hand (can't be done from a WXR/theme-files review — needs
the live editor)

- Financial Management Operations (4385): set excerpt, resolve the
  `sol-productaccounting` icon question.
- Add media-collage + image+text-2-col sections to 6888, 6885, 6887 (or
  leave for the full script run once it's ready).
- Decide on the 3 missing icon files above before those posts get rebuilt.

## Correction — 2026-07-21 (after re-export + your pushback)

You re-exported after fixing the 6308 excerpt — confirmed clean in the new
file, no more stray tabs.

**Media collage was wrong — retracted.** You checked a few Career Center
legacy pages directly and didn't find the collage anywhere; that pushback
was right. I checked two live legacy pages myself (Career Center - Career
Development Tools, and Fundraising - Campaign and Event Management —
different families, both flagged "enabled" in postmeta) and neither shows a
media collage; both show the accordion-style Benefits/Approach section
instead. Checking the underlying data explains why: `benefits_-_title`,
`benefits_-_description`, `benefits_-_floating_image_1`, and
`benefits_-_floating_image_2` are **byte-identical across all 17 "flagged"
posts, regardless of family** — stale template boilerplate that was
apparently never customized per-post, not real content. This section
appears to be a hub-page-only design element, not something any child page
actually used. Retracted the "6888/6887 missing media collage" finding
entirely — they weren't missing anything. `momentive_sol_benefits_media_block()`
in `migrate-solutions.php` now unconditionally returns `''` for children
(left in place but disabled, with the evidence in its docblock, rather than
deleted outright). `solutions-migration-coverage.xlsx`'s "Section to Block
Mapping" sheet is patched to match. Image+text-2-col wasn't affected by
this — that one's confirmed missing on 6885 independently of this issue.

**4385 (Financial Management Operations) was miscast as "rebuilt" — a
classifier bug, not a data bug.** Its `post_content` is almost entirely raw
pasted legacy HTML (`<figure class='gallery-item'>`, single-quoted
attributes — clearly not Gutenberg output), with exactly one trivial
trailing `<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->` — the empty
block WordPress adds automatically the first time a post is opened in the
block editor, even with no real edits made. That was enough to trip the
"contains `<!-- wp:`" check. Fixed `report-rebuild-progress.php` (and the
classification logic here) to strip that specific trivial-empty-block
pattern before testing for real content. Corrected count: **9 of 87** (not
10) are actually rebuilt — 4385 needs a real rebuild whenever you're ready
for it.

**Elementor postmeta cleanup — added to the script**, per your request.
`migrate-solutions.php` now clears `_elementor_data`,
`_elementor_edit_mode`, `_elementor_template_type`, `_elementor_version`,
`_elementor_page_settings`, `_elementor_controls_usage`, and `_elementor_css`
on every child post it writes, via `momentive_sol_clear_elementor_meta()`,
called right after the `post_content` write.

**Related bug found while fixing the above, now also fixed:** the child
post lookup (`momentive_sol_find_or_create_post()`) matched existing posts
by slug. Once slugs are corrected from `<link>` (see the section above),
an already-imported post's *actual* `post_name` in the DB is still whatever
the original bulk import wrote — so looking it up by the newly-corrected
slug would silently fail to find it and create a duplicate post instead of
updating the real one. Changed the lookup to match by the legacy post ID
first (confirmed earlier: IDs are preserved across the import → rebuild
pipeline for the vast majority of posts), correcting `post_name` on match
if it's stale; slug lookup is now only a fallback for a post that was
somehow recreated under a new ID. Brand-new posts (the 12 not-started-yet
ones) are now created with `import_id` set to the legacy ID too, so they
stay consistent with everything else.

**Copilot icon**: the only legacy post using it is **5345, "AI and
Automation"** (Certification Management family) —
`event_sub_card_icon = box-copilot`, plus one `accordion_items` entry
("Built-in Microsoft Copilot") and one `event_sub_benefits_-_repeater` entry
("Microsoft Copilot"), both also using the `copilot` icon slug. (Other
posts mention "Copilot" in accordion/benefits *text*, e.g. Cobalt - AMS
Page 6073, but use different icons for those items — 5345 is the only one
that actually references the `copilot` icon file.)
