# Kirby SEO

A reusable, privacy-conscious SEO toolkit for **Kirby 5.6+ and PHP 8.4+**.
Built independently by Tim Feskens. MIT licensed, no plugin license key, telemetry,
tracking, or external services enabled by default. Kirby itself still requires its
own license for public use.

## Features

- Site defaults, explicit parent inheritance, and page overrides
- Escaped titles, descriptions, canonical URLs, Open Graph and Twitter/X cards
- Dutch/English SEO tabs with live search and social previews
- Automatic 1200 × 630 social image crops
- XML sitemap, `robots.txt`, per-page robots rules, global preview protection
- Language-aware URLs, translated slugs, `hreflang` and sitemap alternates
- WebSite/WebPage/breadcrumb JSON-LD and a site-specific schema extension callback
- Optional AI suggestions, queued IndexNow updates, and allowlisted LLM endpoints

Previews and character counts are guidance, not ranking scores or guarantees.
Google and social platforms can choose different titles, snippets and images.

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
        'noindex' => false,
    ],
];
```

No domain is hardcoded in the plugin. Without `canonicalBase`, Kirby's page URL is
used; configure Kirby's `url` explicitly so canonical URLs never trust arbitrary
request hostnames. A canonical base may include an installation subdirectory. For
multilingual domains, supply a map, e.g. `['nl' => 'https://example.nl', 'en' =>
'https://example.com']`; Kirby still supplies language paths and translated slugs.

**Preview/staging:** set `noindex => true` in environment-specific configuration.
It overrides editor choices, adds `X-Robots-Tag` to Kirby responses, emits noindex
metadata, returns an empty sitemap and blocks IndexNow/LLM exports. Existing server
headers remain valuable for static assets served without Kirby. `noindex` and
`robots.txt` are not security: protect confidential sites with authentication.
A robots disallow can prevent crawlers from seeing a noindex instruction; removing
already-indexed URLs may require temporarily allowing crawls or removal tools.

## Metadata cascade and compatibility

Resolution: non-empty page value → explicitly inheriting ancestor → site default →
plugin fallback. A parent's `metaInherit` selects what it passes to descendants.
Empty, `default`, and `[]` values count as unset. Site-wide `metaTitle` is normally
left empty; page titles then come from the page itself.

Supported existing field names include `metaTitle`, `metaTemplate`,
`metaDescription`, `ogTemplate`, `ogDescription`, `ogSiteName`, `ogImage`,
`useTitleTemplate`, `useOgTemplate`, `twitterCardType`, `twitterAuthor`,
`socialMediaAccounts`, `metaInherit`, and `robots*`. This permits migrating existing
content without renaming fields. This is not a drop-in implementation of another
plugin's PHP API, custom fields, or commercial features.

Title patterns support **only** `{{ title }}`, `{{ page.title }}` and
`{{ site.title }}`; they are not arbitrary query/template execution.

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
not queued, and environment noindex wins. The verification key is public by
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
| `noindex` | `false` | Environment-wide indexing/export block |
| `include` | `null` | Optional public-page eligibility callback |
| `sitemap` / `robots` | `true` | Enable discovery routes |
| `schema` | `null` | Graph extension callback |
| `ai.enabled` | `false` | Manual suggestion UI/API |
| `ai.source` / `ai.provider` | `null` | Explicit content source/provider adapters |
| `indexnow.enabled` | `false` | Queue eligible change notifications |
| `indexnow.key` | `null` | Verification key |
| `llms.enabled` | `false` | Enable explicit public exports |
| `llms.include` / `llms.markdown` | `null` | Allowlist and Markdown renderer |

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
