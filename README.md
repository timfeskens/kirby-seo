# Kirby SEO

A reusable, privacy-conscious SEO toolkit for **Kirby 5.6+ and PHP 8.4+**.
Built independently by Tim Feskens. MIT licensed, no plugin license key, telemetry,
tracking, or external services enabled by default. Kirby itself still requires its
own license for public use.

## Features

- Site defaults, explicit parent inheritance, and page overrides
- Escaped titles, descriptions, canonical URLs, Open Graph and Twitter/X cards
- Dutch/English SEO tabs with separate live Google, Facebook and Twitter/X previews
- Automatic 1200 × 630 social image crops
- XML sitemap, `robots.txt`, global and per-page robots settings
- Language-aware URLs, translated slugs, `hreflang` and sitemap alternates
- WebSite/WebPage/breadcrumb JSON-LD and a site-specific schema extension callback
- Optional AI suggestions, queued IndexNow updates, and allowlisted LLM endpoints
- Visual title-template editor with variable chips and a plain-code view
- Optional Google Search Console queries and Ahrefs metrics in the Panel

Previews are guidance, not ranking scores or guarantees.
Google and social platforms can choose different titles, snippets and images.
SEO tabs use a two-column layout: approximately two-thirds settings and one-third
previews, with Google, Facebook and Twitter/X stacked vertically. When the Panel
content area is 55rem or narrower, previews move below the settings in one column.
Normal input character counters remain; the Google preview has no suggested-length
counter. The obsolete archive control is no longer shown, but existing
`robotsArchive` values remain supported without modifying stored content.
The site tab is **Global SEO settings** (Dutch: **Globale SEO-instellingen**),
while page tabs describe per-page overrides. Separate sections group search
engines, social sharing, robots and social profiles; pages instead have an
advanced/inheritance section for canonical URLs and child-page defaults.
Root robots selectors offer **Automatic / Allow / Disallow**, with no misleading
inheritance choice. Page selectors retain **Inherit / automatic**. Automatic at
the root uses the plugin's safe defaults, not a parent site's settings.

## Installation

Until the package is registered on Packagist, add the GitHub VCS repository once:

```sh
composer config repositories.timfeskens-seo vcs https://github.com/timfeskens/kirby-seo
composer require timfeskens/kirby-seo:^0.1
```

Commit `composer.json` and `composer.lock` in each consuming project. Update with
`composer update timfeskens/kirby-seo`; do not track a floating development branch.
Composer installs into `site/plugins/timfeskens-seo`. Alternatively, download a
tagged release there manually (including `index.js`, `index.css` and `src/`).

### Add SEO tabs

Site blueprint:

```yaml
tabs:
  content:
    # Your existing sections/fields
  seo: tabs/seo-site
```

Page blueprints:

```yaml
tabs:
  content:
    # Your existing sections/fields
  seo: tabs/seo-page
```

If a blueprint currently uses top-level fields/columns/sections, first move these
under a content tab; Kirby cannot combine both layouts at the same level.
Existing local `tabs/seo-site`, `tabs/seo-page`, or `fields/seo-robots` blueprints
override the plugin versions. Remove or adapt those local definitions if you want
the shared UI. The plugin does not write or migrate content automatically.

### Render metadata once

Inside `<head>`, replace existing SEO title/meta/canonical/schema output with:

```php
<?php snippet('timfeskens-seo/meta') ?>
```

Keep your charset, viewport, favicon, styles, and other application-specific head
elements. Remove competing sitemap/robots routes or disable this plugin's routes.

### Configure

`site/config/config.php`:

```php
return [
    'timfeskens.seo' => [
        'canonicalBase' => 'https://example.com',
        'locale' => 'nl_NL', // single-language sites; multilingual sites use Kirby's locale
        'description' => 'An optional default description.',
    ],
];
```

No domain is hardcoded in the plugin. Without `canonicalBase`, Kirby's page URL is
used; configure Kirby's `url` explicitly so canonical URLs never trust arbitrary
request hostnames. A canonical base may include an installation subdirectory. For
multilingual domains, supply a map, e.g. `['nl' => 'https://example.nl', 'en' =>
'https://example.com']`; Kirby still supplies language paths and translated slugs.

Manage indexing in the Panel's global and per-page robots settings. Global
**Indexing → Disallow** applies by default; an explicit page setting can override
it. The plugin does not add an environment-level indexing override or blanket
robots header. Robots directives are not security: protect confidential sites
with authentication.

## Metadata cascade and compatibility

Resolution: non-empty page value → explicitly inheriting ancestor → site default →
plugin fallback. A parent's `metaInherit` selects what it passes to descendants.
Empty, `default`, and `[]` values count as unset. Site-wide `metaTitle` is normally
left empty; page titles then come from the page itself.

Supported existing field names include `metaTitle`, `metaTemplate`,
`metaDescription`, `ogTemplate`, `ogDescription`, `ogSiteName`, `ogImage`,
`useTitleTemplate`, `useOgTemplate`, `twitterCardType`, `twitterAuthor`,
`socialMediaAccounts`, `cropOgImage`, `metaInherit`, and `robots*`. This permits migrating existing
content without renaming fields. This is not a drop-in implementation of another
plugin's PHP API, custom fields, or commercial features.

Title patterns support **only** `{{ title }}`, `{{ page.title }}` and
`{{ site.title }}`; they are not arbitrary query/template execution.
Title patterns and the social site name use the same visual editor with labeled
variable chips; separators and other text remain
editable. **Code** switches to the original plain string, so existing content stays
portable. Tokens without surrounding whitespace (e.g. `{{title}}`) also resolve.
The Panel fills empty title patterns with `{{ title }} - {{ site.title }}` (Google)
and `{{ title }}` (social), and the social site name with `{{ site.title }}`.
Placeholders show the same literal patterns, not prematurely resolved titles.
Description fields display their configured/inherited fallbacks; robots and card
selectors start at automatic and both page pattern toggles start enabled.
Existing overrides are preserved. Unchanged inherited page text is not pinned as
a page override when saving. Fields without a meaningful default (images, profile
URLs, canonical overrides and authors) remain empty rather than inventing content.

### Migrating from tobimori/kirby-seo

Shared content keys are deliberately identical, including `metaTitle`,
`metaTemplate`, `useTitleTemplate`, `metaDescription`, `ogTemplate`, `useOgTemplate`,
`ogDescription`, `ogSiteName`, `ogImage`, `cropOgImage`, `metaInherit`, and all
`robots*` settings. Kirby treats field names case-insensitively. Social profiles
retain the `socialMediaAccounts` object and its `twitter`, `facebook`, `instagram`,
`youtube`, `linkedin`, `bluesky`, and `mastodon` keys. Cropping defaults to enabled;
an existing `cropOgImage: false` preserves the original image and its dimensions.

Back up content and config, remove the old plugin, then replace its blueprint tabs
and head snippet with the ones above. Keep existing content files and file UUIDs.
Settings under `tobimori.seo` must be deliberately translated to `timfeskens.seo`;
configuration namespaces and PHP APIs are **not** drop-in compatible. Our extra
`canonicalUrl`, `twitterCardType`, and `twitterAuthor` keys remain supported; they
do not have equivalent stock fields in the current upstream blueprints. Custom KQL
title expressions are intentionally not executed. OAuth connections are not
automatically imported: reconnect with the private token storage described below.
No content migration or bulk rewrite runs automatically.

## Optional Google Search Console

Disabled by default. When enabled, the SEO sidebar on both Site and page views
shows search queries with clicks, impressions, CTR and average position. The top
10 are shown first; sort by any metric or show all returned rows (up to Google's
25,000-row response limit; this is not an exhaustive export). Page queries filter
by **exact canonical URL**, while Site queries cover the selected property. Data
covers 28 days ending three days ago to avoid incomplete recent data and is cached
for 24 hours. Empty reports and provider failures do not affect public metadata.

1. Enable the **Google Search Console API** in your Google Cloud project.
2. Configure the OAuth consent screen and a **Web application** OAuth client.
   Register `https://example.com/__seo/gsc/callback` as an exact redirect URI
   (including any installation subdirectory). Local development needs its own URI.
3. Configure credentials through your environment and a writable token file path
   in an **existing private directory outside the document root and Git**:

```php
'searchConsole' => [
    'enabled' => true,
    'credentials' => ['web' => [
        'client_id' => getenv('GOOGLE_SEARCH_CONSOLE_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET'),
    ]],
    'tokenPath' => getenv('GOOGLE_SEARCH_CONSOLE_TOKEN_PATH'),
],
```

Downloaded Google `web` credentials arrays are also accepted. Do not commit the
downloaded JSON. Configure Kirby's trusted `url`; the callback is based on the
current site URL, not `canonicalBase`. For Docker, persist/mount private token
storage outside the web root; the plugin does not create deployment volumes.

4. As a Panel administrator, click **Connect Google**, grant read-only access,
   then choose an accessible Search Console property. Other editors with update
   permission can read reports, but cannot change the shared connection. Change
   property, reconnect, or disconnect in the sidebar. Optional server config
   `searchConsole.property` pins a property and takes precedence over Panel selection.

OAuth uses one-time, user-bound, expiring state and PKCE. Refresh tokens stay on the
server in an atomically written `0600` file. Credentials/tokens are never Panel
props or content fields. Disconnect deletes the local connection; to revoke the
Google grant itself, remove it in your Google account's third-party access settings.
Google OAuth apps in external **Testing** mode can have short-lived refresh tokens;
configure the consent application's publishing status appropriately.

## Optional Ahrefs

Disabled independently by default. Enable with an Ahrefs API v3 key whose account
has access and quota for the Site Explorer metrics endpoint:

```php
'ahrefs' => [
    'enabled' => true,
    'apiKey' => getenv('AHREFS_API_KEY'),
],
```

The Panel shows organic keywords, top-three keywords, estimated monthly organic
traffic and paid keywords. Ahrefs estimates are **not** measured visitor analytics.
Page scope is the exact canonical URL; Site scope is its canonical homepage prefix
(preserving subdirectory installations). Results use yesterday's data and are
cached for 24 hours to avoid repeated API-unit charges. API availability and cost
depend on your Ahrefs subscription; enabling this integration authorizes these
requests. Keys remain server-side; no Ahrefs JavaScript/tracking is injected.

Both integrations make no requests and show no UI when disabled. They only run on
authenticated Panel requests, never during public page rendering. Non-indexable
pages are excluded from per-page requests. Site-level reports still cover the
selected external property/site. No credentials are bundled and neither integration
is automatically connected by installation.

## Automatic XML sitemap

The plugin serves **`/sitemap.xml` automatically**, enabled by default. `/sitemap`
redirects permanently to it, and `robots.txt` advertises its canonical URL. No
static file, cron job or manual regeneration is required: published changes are
reflected on the next request.

The sitemap includes the homepage and eligible published pages, canonical URLs,
available content modification dates, and existing language versions with
`xhtml:link` alternates. Drafts, error pages, noindex pages, custom `include`
exclusions and URLs canonicalized elsewhere are omitted; unlisted pages require
explicit indexing permission. Alternates follow those same exclusion rules.
Global **Indexing → Disallow** produces an empty sitemap unless individual pages
explicitly allow indexing. The sitemap follows the same Panel settings as metadata.
The XML is indented and references a same-origin `/sitemap.xsl` stylesheet for a
readable browser table with clickable URLs, entry count, modification dates and
language alternatives. Browsers without XSLT support still receive readable XML;
search engines consume the unchanged sitemap data.
Some browsers show an XSLT deprecation warning; this browser-owned notice cannot
be removed by the plugin. The stylesheet is a convenience, not a dependency for
indexing or sitemap validity.
Modification dates use ISO 8601 regardless of the site's configured date handler;
unavailable dates are omitted. Each entry includes a URL-depth-based `priority`:
homepage **1.0**, top-level **0.8**, then **0.6**, **0.4**, **0.2**, with **0.1**
for deeper URLs. Installation subdirectories and language prefixes do not count
as content levels. Priority is also shown in the browser table. Google ignores
this optional hint; it is not a ranking score. `changefreq` is not generated.
Disable these routes with `'sitemap' => false` if another sitemap owns the URL.

Listed pages and home are indexable by default; unlisted pages require explicit
permission. Drafts and error pages are always excluded. Unlisted is not a privacy
boundary: do not explicitly enable indexing on confidential content. Links on
unlisted pages remain followable unless configured otherwise. Legacy `noarchive`
is retained for field compatibility; major engines may ignore it.

To exclude application-specific private pages from indexing, sitemap, IndexNow
and LLM exports, set an `include` callback:

```php
'include' => fn (\Kirby\Cms\Page $page, ?string $language) =>
    !in_array($page->intendedTemplate()->name(), ['account', 'internal'], true),
```

The plugin cannot infer authentication or privacy rules in arbitrary templates.
Audit this callback before enabling discovery integrations. Alternate canonical
URLs are omitted from the sitemap. Only existing translations get alternates.
Large sites with more than 50,000 URLs need a separate sitemap index solution;
the initial release provides one sitemap and does not cache rendered metadata.

## Structured data

Extend the graph with **real published facts**, not inferred claims:

```php
'schema' => function ($page, array $graph, array $meta): array {
    if ($page->intendedTemplate()->name() === 'recipe') {
        $recipe = myValidatedRecipeSchema($page); // site owns its recipe rules
        if ($recipe !== null) {
            unset($recipe['@context']);
            $graph[] = $recipe;
        }
    }
    return $graph;
},
```

Do not output the same schema twice. Existing site-specific JSON-LD can also stay
in its own script if it is not duplicated in this callback. Rich-result eligibility
depends on content and search-engine rules, not merely valid JSON-LD. Incomplete
recipes, invented nutrition/ratings, and misleading imagery must not be promoted.

Programmatic access: `$page->seo()`, `Seo::metadata($page, $language)`,
`Seo::canonical($page, $language)` and `Seo::render($page)` in the
`TimFeskens\Seo` namespace.

## Optional AI assistance

No provider, key, or external dependency is bundled. Configure an explicit source
and a callable provider; credentials belong in environment variables, never Git:

```php
'ai' => [
    'enabled' => true,
    'source' => fn ($page) => $page->description()->value(),
    'provider' => function (array $input): array {
        // Your server-side adapter; enforce timeouts and use your environment key.
        // $input: title, text (max 12,000 chars), language, instruction
        return myAiService($input); // ['title' => '...', 'description' => '...']
    },
],
```

Only authenticated editors with update permission can request suggestions, via
Kirby's protected field API. They must confirm before sending saved content.
Suggestions are escaped, shown separately, and **never automatically saved**;
review and copy them into the fields. Site-level suggestions are not supported.
Configure provider-side rate limits/budgets. Text is plain content, not trusted
instructions; provider adapters should also protect against prompt injection.

## Optional IndexNow

```php
'indexnow' => [
    'enabled' => true,
    'key' => getenv('INDEXNOW_KEY'), // 8–128 letters, digits or hyphens
],
```

The key verification file is routed automatically. Eligible updates/status/slug
changes and impending deletions queue canonical URLs in the plugin's Kirby cache.
Use persistent cache storage in container deployments. Draft/private content is
not queued. The verification key is public by
design; it is not an authentication secret.

Flush from a **serialized, scheduled CLI job** after bootstrapping your site:

```php
require __DIR__ . '/kirby/bootstrap.php';
$kirby = new Kirby();
$count = \TimFeskens\Seo\Integrations::flush();
```

Only the fixed HTTPS `api.indexnow.org` endpoint is contacted. Failed batches are
retained, accepted batches cleared. v0.1 uses Kirby's simple cache queue, not a
transactional delivery system: concurrent writers may race, so it is best-effort
and never a substitute for a sitemap. Queue hooks must not be your only deletion
notification mechanism. Search-engine participation varies; Google does not use
IndexNow. No background worker or cron is installed automatically.

## Optional `llms.txt` and Markdown

```php
'llms' => [
    'enabled' => true,
    'include' => fn ($page) => $page->intendedTemplate()->name() === 'article',
    'markdown' => fn ($page) => '# ' . $page->title() . "\n\n" . $page->text(),
],
```

Both callbacks are required. Only opted-in, indexable public pages are listed in
`/llms.txt` and exported at `/seo/markdown/{encoded-page-id}.md`. The callback must
return intentional, accurate Markdown; the plugin never dumps arbitrary content
fields, renders authenticated templates, or exports accounts. Use a restricted
field allowlist in your renderer. Markdown endpoints send `X-Robots-Tag: noindex`
to avoid competing with the HTML page. These endpoints are experimental discovery
conventions, not a ranking guarantee, crawler access control, or training opt-out.

## Options overview

All options below live under `timfeskens.seo`:

| Option | Default | Purpose |
| --- | --- | --- |
| `canonicalBase` | `null` | Trusted origin/base or language-code map |
| `locale` | `en_US` | Single-language locale |
| `description` | `''` | Fallback description |
| `include` | `null` | Optional public-page eligibility callback |
| `sitemap` / `robots` | `true` | Enable discovery routes |
| `schema` | `null` | Graph extension callback |
| `ai.enabled` | `false` | Manual suggestion UI/API |
| `ai.source` / `ai.provider` | `null` | Explicit content source/provider adapters |
| `indexnow.enabled` | `false` | Queue eligible change notifications |
| `indexnow.key` | `null` | Verification key |
| `llms.enabled` | `false` | Enable explicit public exports |
| `llms.include` / `llms.markdown` | `null` | Allowlist and Markdown renderer |
| `searchConsole.enabled` | `false` | Read-only search performance in Panel |
| `searchConsole.credentials` | `null` | Server-side Google OAuth credentials |
| `searchConsole.tokenPath` | `null` | Explicit private persistent token file |
| `searchConsole.property` | `null` | Optional property pin instead of Panel selection |
| `ahrefs.enabled` | `false` | Ahrefs Site Explorer metrics in Panel |
| `ahrefs.apiKey` | `null` | Server-side Ahrefs API v3 key |

## Development

```sh
composer install
composer validate --strict
composer lint
composer test
node --check index.js
npm ci
npx playwright install chromium
npm test
```

Tests use virtual Kirby content and isolated temporary caches, not either site's
production content/accounts. Integrations use mocked transports: CI never sends
IndexNow requests or AI content. The Panel uses native Kirby components with no
JavaScript build step. See [CHANGELOG.md](CHANGELOG.md) for release history.

## License and attribution

[MIT](LICENSE). Original implementation; no commercial Kirby SEO source or assets
are included. Inspired by common technical SEO practices and the Kirby ecosystem,
including [tobimori/kirby-seo](https://github.com/tobimori/kirby-seo).
