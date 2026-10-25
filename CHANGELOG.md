# Changelog

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
