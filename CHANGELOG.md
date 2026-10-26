# Changelog

## 0.1.15 — 2026-10-26

- Simplify the README into numbered basic SEO, Search Console and Ahrefs setup
  steps, with a short troubleshooting table explaining Google API activation.
- Move advanced configuration, migration compatibility and deployment details to
  `docs/configuration.md`. No runtime or stored-content changes.

## 0.1.14 — 2026-10-26

- Defer preview metadata until after the Panel form loads instead of computing it
  during field serialization and again on mount. Show accessible per-platform
  skeletons, respect reduced motion, and retain loaded previews during edits.
- Classify Google API-disabled, revoked-access, permission and quota failures
  without exposing provider responses or credentials. Link administrators directly
  to API activation; reconnecting cannot enable a disabled Google Cloud API.
- Cache accessible Search Console properties for five minutes and prevent duplicate
  in-flight report loads. Keep query reports cached for 24 hours.
- Add PHP and real Panel browser regressions for deferred metadata, usable settings
  while previews load, safe error codes and loading placeholders.

## 0.1.13 — 2026-10-26

- Default optional Ahrefs reports to free Domain Rating with domain-wide caching,
  attribution and free-key setup guidance. Paid Site Explorer requires explicit
  `ahrefs.mode: paid`; anonymous free requests are not supported by Ahrefs.
- Move canonical overrides to a `canonicalUrl` PHP string/callback, retaining
  migrated content overrides and excluding canonicalized duplicates from sitemaps.
- Add a reports heading and Google Search Console query summary below previews,
  with accessible report skeletons and honest query-summary scope labels.
- Filter preview requests to SEO edits, deduplicate unchanged values and coalesce
  concurrent edits into one trailing request, ignoring stale/disposed responses.
- Document direct private PHP configuration and Ansible Vault setup.

## 0.1.12 — 2026-10-26

- Subscribe directly to Kirby's reactive unsaved content store and throttle
  preview requests so updates continue during typing, before blur or save.
  Invalidate stale responses as soon as input changes.
- Preview site defaults in the global SEO tab without saved homepage overrides
  masking edits. Label the example and link to actual homepage SEO settings;
  preserve all stored overrides and the page/frontend metadata cascade.
- Add real Panel regression tests for uninterrupted typing in visual templates,
  descriptions and page titles, with existing homepage overrides and social images.

## 0.1.11 — 2026-10-26

- Fix social images disappearing from live previews: normalize Kirby file-picker
  objects to UUID/id reference strings before resolving unsaved metadata.
- Preserve existing stored references and image inheritance, and ignore
  client-supplied image URLs. Test native picker UUIDs, legacy references,
  clearing selections and loaded Facebook/Twitter images in the real Panel.

## 0.1.10 — 2026-10-26

- Use the same variable-chip template editor for social site names and social
  title patterns, preserving the `ogSiteName` key and existing string values.
- Add repository instructions requiring migration compatibility with
  tobimori/kirby-seo and regression tests for shared content formats.

## 0.1.9 — 2026-10-26

- Add an independent visual title-template field with insertable variable chips,
  code view, and unchanged `metaTemplate`/`ogTemplate` storage.
- Support compact template tokens, inherited `cropOgImage`, original image
  dimensions when cropping is disabled, and Bluesky/Mastodon profile keys.
- Add optional, disabled-by-default Google Search Console OAuth/read-only reports
  and Ahrefs API v3 metrics. Keep credentials server-side, cache results for 24
  hours, and exclude non-indexable pages from per-page requests.
- Document field compatibility, migration boundaries and private token setup.

## 0.1.8 — 2026-10-25

- Add automatic sitemap priorities based on content URL depth: homepage 1.0, then 0.8, 0.6, 0.4, 0.2 and a minimum of 0.1.
- Exclude installation subdirectories and language prefixes from depth calculations.
- Display priority alongside URL and modification date in the responsive sitemap table.
- Test XML coverage, deep URL floors, translated homepages, language prefixes, subdirectory installations and browser rendering.

## 0.1.7 — 2026-10-25

- Fix sitemap modification dates on sites using Kirby's `intl` date handler: always emit ISO 8601 using PHP's date handler.
- Add an original responsive, light/dark sitemap browser view with clickable URLs, entry count, dates, language alternatives and an empty state via `/sitemap.xsl`.
- Indent the underlying XML and keep the stylesheet on the serving origin, including subdirectory installations and sites with a different canonical domain.
- Preserve all existing Panel-driven eligibility and canonical/language exclusions; omit unavailable dates rather than inventing them.
- Add date-handler, stylesheet, responsive browser, empty-state and multilingual regression checks.

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
