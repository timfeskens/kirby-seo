# Changelog

## 0.1.6 — 2026-10-25

- Manage indexing exclusively through the global and per-page robots settings in the Panel; explicit page choices can override global defaults.
- Remove the configuration-level indexing override, its preview notice and blanket robots response header.
- Remove the matching hard block from discovery routes and optional integrations; IndexNow and LLM exports remain opt-in and respect page eligibility.
- Preserve draft/error exclusions, canonical sitemap exclusions and the Markdown export's own noindex header.
- Test global indexing inheritance, explicit page permission and the absence of a blanket robots header.

## 0.1.5 — 2026-10-25

- Distinguish global site SEO settings from page overrides with scope-specific labels and help text.
- Group search, social sharing, robots, social profiles (site) and advanced/inheritance settings (pages) into separate sections.
- Offer Automatic, Allow and Disallow at the root; retain Inherit / automatic on pages without changing stored values or indexing behavior.
- Preserve responsive 66/33 columns and test grouped layouts, root/page options and blocked sitemap output.

## 0.1.4 — 2026-10-25

- Arrange site/page SEO tabs into approximately 66% settings and 33% previews, with Google, Facebook and Twitter/X stacked in the right column.
- Switch to full-width settings followed by previews when the available Panel area is too narrow; preserve page-specific fields and inheritance.
- Remove the suggested title/description character counts from the Google preview while retaining standard input counters.
- Remove the obsolete archive control from the Panel; retain backend support for existing `robotsArchive` values.
- Test real Panel geometry from 375px through 1920px, long titles and legacy robots compatibility.

## 0.1.3 — 2026-10-25

- Use consistently kebab-case Panel field registration and exercise real Kirby Panel loading in Chromium.
- Fix field API closures to use the captured page/site model rather than the API route context.
- Fill existing empty pattern, description, automatic selector and toggle defaults; match placeholders without evaluating SEO tokens early. Preserve existing values and inherited page text on save.
- Add distinct live Google, Facebook and Twitter/X previews using each platform's metadata and card type.
- Apply canonical exclusions to sitemap alternates, ignore invalid canonical overrides and use language-specific modification dates.
- Document automatic sitemap routes and extend regression coverage.

## 0.1.2

Resolve unsaved site defaults and site-title patterns in live previews without
persisting content or leaving modified Kirby context. Add preview isolation tests.

## 0.1.1

Explicitly require Kirby's Composer installer so consuming projects install the
plugin into `site/plugins/timfeskens-seo`, not a non-discoverable vendor folder.

## 0.1.0

Initial release: shared metadata engine, Dutch/English Panel blueprints and live
previews, robots/sitemap routes, multilingual alternates, extensible JSON-LD,
explicit AI-provider callbacks, best-effort IndexNow queue, and allowlisted LLM
exports. Optional integrations are disabled by default. Targets Kirby 5.6+/PHP 8.4+.
