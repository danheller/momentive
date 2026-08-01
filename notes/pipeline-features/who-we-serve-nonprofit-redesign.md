# Pipeline feature: Who We Serve — Nonprofit page redesign

**Source:** Asana ticket, forwarded 2026-07-28.

**Full CPT/content architecture for the Who We Serve family lives in
`notes/reference-sheets/who-we-serve-reference-sheet.md` — this document covers only the
Nonprofit-page redesign ask.** Cross-linked from that sheet as well.

**New design template (Figma):** https://www.figma.com/design/wUpTERJGUIwB83yxf4Agr7/UI-Design?node-id=27949-23067&t=EoTDdLI5maQ9d0X… *(truncated in the source paste — pull the full link from Asana)*

**Content structure reference (for writers):** https://momentivesoftware-my.sharepoint.com/:w:/p/raymundalfred_oropeza/IQCHQdznyUr1SJ7czUqeFRDbAe87… *(truncated in the source paste — pull the full link from Asana)*

## The ask, verbatim

> **Marketing Business Justification:** The Who We Serve page template has been redesigned
> (Figma: Who We Serve-sub-v2) to bring a consistent structure and updated visual design
> across all industry pages. The live nonprofit page still runs the old design and needs to
> be rebuilt on the new template so it's visually and structurally aligned with the rest of
> the site before this template rolls out to other Who We Serve pages.
>
> **Request:** Rebuild the nonprofit Who We Serve page using the new sub-page template.
> Replace the current layout, components, and section structure with the new Figma design.
> Content will be updated separately by the content team to match the section structure
> defined in the Writer Guidelines, so build to accommodate that structure (including any
> sections in the new template that don't exist on the page today).
>
> 1. Page to update: Nonprofit Organization Software & Tools (`https://momentivesoftware.com/who-we-serve/nonprofit/`)
>
> **Acceptance Criteria:**
> 1. Page matches the Who We Serve-sub-v2 Figma spec (layout, components, spacing,
>    typography) on desktop and mobile.
> 2. Every section in the new template is built and functional, with existing copy carried
>    over or placeholder marked where new copy is pending.
> 3. No legacy layout, styles, or components from the old design remain on the page.
> 4. All CTAs and internal links function and point to the correct destinations.
> 5. Existing SEO metadata (title, description, URL, schema) is preserved unless a change is
>    separately requested.
> 6. Page reviewed against the Figma file and signed off by design before going live.

## How this lines up with the existing reference sheet

`notes/reference-sheets/who-we-serve-reference-sheet.md` already recommends hand-building
all 6 real Who We Serve pages (Foundation, Nonprofit, Education, Associations, Government,
Healthcare & Medical) from a shared pattern library — hero, stats via `momentive/impact-stat`,
accordion, features, testimonials, FAQ, resource list, carousel — deliberately not scripted,
same tier as the Solutions hub pages. **This ticket is the first concrete page in that queue,
not new or unplanned scope** — it assigns a specific new template (Figma "Who We Serve-sub-v2")
to the Nonprofit page specifically, explicitly ahead of the other five ("before this template
rolls out to other Who We Serve pages"). Good confirmation the reference sheet's plan matches
what marketing is actually asking for.

## Content vs. structure — read this against the reference sheet's field map

The ticket is explicit that content team will update copy separately to match the Writer
Guidelines' section structure, and that the build should "accommodate that structure
(including any sections in the new template that don't exist on the page today)." The
reference sheet's field → destination map was built from the **legacy** Nonprofit page's
~10 sections (hero, stats, accordion, features, testimonials, FAQ, resource list, carousel,
plus a Nonprofit-only embedded success-video section) — the new Figma template may not map
onto those 1:1. **Recommend diffing the Figma spec's section list against the reference
sheet's field map before starting the build**, so it's clear up front which sections carry
over existing legacy copy (via the reference sheet's map) and which are genuinely new and
need placeholder copy rather than a migration source.

## Megamenu is very likely part of this, not a separate task

The reference sheet already flagged `parts/megamenu-who-we-serve.html` as probably still
wired to placeholder content. Confirmed directly: as of this writing it links to
`/solutions/momentiveiq/` and `/products/aptify/` under a "Who We Serve" heading — no real
audience-page links at all. Since the Nonprofit page is about to become the first real page
in this family rebuilt on the new template, this is a natural moment to also point the
megamenu at its actual URL (and audit the rest while in there) — worth doing alongside this
ticket rather than filing it as separate, unrelated work.

## Acceptance-criteria note

Criterion 5 asks that existing SEO metadata be preserved unless a change is separately
requested. The legacy page's URL is `/who-we-serve/nonprofit/` — confirm the rebuilt page
keeps this exact slug so metadata and any existing backlinks/rankings stay attached to the
same URL, with no redirect needed.

## Open questions

- Pull the full, untruncated Figma and SharePoint links from the source Asana ticket before
  starting — both were cut off in the paste.
- Whether "sub-v2" implies a "Who We Serve-sub-v1" template is already live somewhere on
  the site (worth checking so a real v1 isn't mistaken for "the old design" and skipped),
  or whether "v2" just reflects the Figma file's own internal iteration history with no
  live v1 to compare against.
- This ticket may be requesting the Nonprofit rebuild sooner than
  `notes/reference-sheets/recommended-sequence.md`'s Phase 3 ("Bespoke hand-built pages,"
  sequenced after a shared pattern library exists from the Solutions hub-page work) assumed
  — flag the tension with whoever owns sequencing rather than silently reordering that
  document. If Nonprofit needs to move earlier, the pattern library it depends on
  (hero/stats/accordion/features/testimonials/FAQ/carousel) may need to be built earlier too.
