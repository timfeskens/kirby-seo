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

Previews update while typing, without blurring a field or saving. The global SEO
tab previews **site defaults**, using the homepage title/URL as an example but
ignoring its SEO overrides. The homepage SEO link opens its actual page settings;
page previews and rendered metadata retain page-specific overrides. To apply
global defaults to an existing page, clear its title pattern/description
overrides and leave the title-pattern toggle enabled. Social title patterns and
descriptions are separate settings; an explicit social description does not
change when the search description changes.
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
advanced/inheritance section for child-page defaults. Canonical overrides are
configured in PHP, not edited in the Panel.
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

For content whose original lives elsewhere, set `canonicalUrl` inside
`timfeskens.seo`. A callback can return a URL per page/language, or `null` to keep
the normal canonical (including any migrated saved override):

```php
'canonicalUrl' => static function (\Kirby\Cms\Page $page, ?string $language): ?string {
    return $page->id() === 'syndicated-article' ? 'https://original.example/article' : null;
},
```

A fixed HTTP(S) string is also accepted, but applies to **every page**. Config
takes precedence over legacy saved `canonicalUrl` content; legacy values remain
supported without rewriting content. Invalid URLs fall back to the natural page
canonical. Pages canonicalized elsewhere are omitted from the sitemap.

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
URLs and authors) remain empty rather than inventing content.

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
shows search queries with clicks, impressions, CTR and average position. The
reports section sits **below the Google/Facebook/X previews** and is not a preview
of search results. Its summary covers returned queries, not guaranteed property
totals: Google may omit anonymized queries. Loading reports show accessible
skeleton placeholders. Initial previews also show per-platform skeletons, with
metadata loaded asynchronously so the settings form is usable immediately. This
avoids calculating the same metadata during form loading and again on mount;
live previews keep their existing content while updating. Skeleton animation
respects reduced-motion preferences.
Only SEO-relevant edits trigger preview requests; requests are throttled,
single-flight and coalesced to the latest values, with stale responses ignored.
The top
10 are shown first; sort by any metric or show all returned rows (up to Google's
25,000-row response limit; this is not an exhaustive export). Page queries filter
by **exact canonical URL**, while Site queries cover the selected property. Data
covers 28 days ending three days ago to avoid incomplete recent data and is cached
for 24 hours. Empty reports and provider failures do not affect public metadata.
Accessible property lists are cached for five minutes per Google connection.

The Panel distinguishes a disabled Google API, revoked/expired Google access,
property permission errors and quota limits using fixed, safe error codes. If it
asks you to enable the API, select the **same Google Cloud project that owns the
OAuth client** and enable **Google Search Console API**, wait for activation and
retry. A successful Google login does not enable this API; reconnecting alone
cannot resolve `accessNotConfigured` / `SERVICE_DISABLED`. Provider messages,
tokens and client credentials are never returned to the Panel or logged.

1. Select or create a project in the [Google Cloud Console](https://console.cloud.google.com/projectselector2),
   then enable the **Google Search Console API** in the
   [API Library](https://console.cloud.google.com/apis/library/searchconsole.googleapis.com).
   See Google's [API setup guide](https://developers.google.com/webmaster-tools/v1/prereqs).
2. Configure the [OAuth consent screen](https://console.cloud.google.com/auth/overview)
   and create a **Web application** OAuth client under
   [Clients](https://console.cloud.google.com/auth/clients).
   Follow Google's [web-server OAuth guide](https://developers.google.com/identity/protocols/oauth2/web-server#creatingcred)
   for client creation and redirect URI rules.
   Register `https://example.com/__seo/gsc/callback` as an exact redirect URI
   (including any installation subdirectory). Local development needs its own URI.
3. Configure direct PHP values in a private Kirby config file (see
   [local and Ansible/Vault configuration](#private-configuration-localhost-and-ansible-vault)
   and Kirby's [configuration guide](https://getkirby.com/docs/guide/configuration)),
   with a writable token file path
   in an **existing private directory outside the document root and Git**:

```php
'searchConsole' => [
    'enabled' => true,
    'credentials' => [
        'client_id' => 'your-client-id.apps.googleusercontent.com',
        'client_secret' => 'your-client-secret',
    ],
    'tokenPath' => '/absolute/private/path/google-search-console.json',
],
```

Downloaded Google `web` credentials arrays are also accepted. Do not commit the
downloaded JSON. Configure Kirby's trusted `url`; the callback is based on the
current site URL, not `canonicalBase`. For Docker, use a persistent
[volume](https://docs.docker.com/engine/storage/volumes/) or
[bind mount](https://docs.docker.com/engine/storage/bind-mounts/) for private token
storage outside the web root; the plugin does not create deployment volumes.

4. As a Panel administrator, click **Connect Google**, grant read-only access,
   then choose an accessible property from [Google Search Console](https://search.google.com/search-console).
   If needed, [add and verify your property](https://support.google.com/webmasters/answer/34592)
   and check [property access permissions](https://support.google.com/webmasters/answer/7687615)
   first. Other editors with update
   permission can read reports, but cannot change the shared connection. Change
   property, reconnect, or disconnect in the sidebar. Optional server config
   `searchConsole.property` pins a property and takes precedence over Panel selection.

OAuth uses one-time, user-bound, expiring state and PKCE. Refresh tokens stay on the
server in an atomically written `0600` file. Credentials/tokens are never Panel
props or content fields. Disconnect deletes the local connection; to revoke the
Google grant itself, remove it in your
[Google account's third-party access settings](https://myaccount.google.com/connections).
Google OAuth apps in external **Testing** mode can have short-lived refresh tokens;
configure the consent application's publishing status appropriately; see Google's
[refresh-token expiration rules](https://developers.google.com/identity/protocols/oauth2#expiration).

## Optional Ahrefs

Disabled independently by default. Set it up as follows:

1. The default **free** mode uses [Domain Rating free](https://docs.ahrefs.com/en/api/reference/public/get-domain-rating-free).
   It requires a **free API v3 key**, but no paid subscription. Without a key the
   Panel shows setup guidance; Ahrefs does not support anonymous API requests.
2. Sign in to [Ahrefs](https://app.ahrefs.com/) and create a key in your workspace's
   API key settings. Follow the official
   [API key creation and management guide](https://docs.ahrefs.com/en/api/docs/api-keys-creation-and-management).
   Keep the key private; do not put it in content, browser code or Git.
3. Set the key directly in a private Kirby config file, or render it with Ansible
   from encrypted Vault variables (see
   [configuration examples](#private-configuration-localhost-and-ansible-vault)):

```php
'ahrefs' => [
    'enabled' => true,
    'apiKey' => 'your-ahrefs-api-key',
    'mode' => 'free', // default; explicitly opt into 'paid' for Site Explorer
],
```

4. Open your site's Panel **Global SEO settings** tab to view the reports, then
   compare the target site in [Ahrefs Site Explorer](https://app.ahrefs.com/site-explorer).
   There is no separate Connect button: the plugin authenticates using the server-side key.

Free mode shows **Domain Rating by Ahrefs**, a domain-wide backlink rating from
0–100, with the [required attribution](https://ahrefs.com/legal/domain-rating-license).
It is not a Google ranking score or page traffic. One domain-level cache is shared
between site and page reports for 24 hours.

Explicit `'mode' => 'paid'` shows organic keywords, top-three keywords, estimated
monthly organic traffic and paid keywords. Check [API access and limits](https://docs.ahrefs.com/en/api/docs/introduction)
and your subscription first: this mode can consume paid units and is never chosen
automatically. Ahrefs estimates are **not** measured visitor analytics.
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

### Private configuration: localhost and Ansible Vault

The plugin reads Kirby options directly; environment variables are **not required**.
Keep the snippets above inside `timfeskens.seo` in your config array. Locally, use
an ignored `site/config/config.localhost.php` (or the filename matching your local
hostname; see [domain-specific configuration](https://getkirby.com/docs/guide/configuration#domain-specific-config-files)):

```php
<?php
return [
    'timfeskens.seo' => [
        'searchConsole' => [
            'enabled' => true,
            'credentials' => [
                'client_id' => 'your-client-id.apps.googleusercontent.com',
                'client_secret' => 'your-client-secret',
            ],
            'tokenPath' => '/absolute/private/path/google-search-console.json',
        ],
        'ahrefs' => ['enabled' => false],
    ],
];
```

For deployment, put non-secret settings in normal Ansible variables and credentials
in [Ansible Vault](https://docs.ansible.com/ansible/latest/vault_guide/index.html):

```yaml
# Regular variables
seo_runtime_config:
  timfeskens.seo:
    searchConsole:
      enabled: true
      credentials:
        client_id: '{{ vault_seo_google_client_id }}'
        client_secret: '{{ vault_seo_google_client_secret }}'
      tokenPath: /run/seo-private/google-search-console.json
    ahrefs:
      enabled: false
      # To enable later, supply apiKey: '{{ vault_seo_ahrefs_api_key }}'.

# Inside encrypted vault.yml (never commit the plaintext form)
vault_seo_google_client_id: your-client-id.apps.googleusercontent.com
vault_seo_google_client_secret: your-client-secret
```

Use an [Ansible template](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/template_module.html)
to render a protected domain-specific PHP config file. JSON inside a PHP nowdoc
avoids unsafe interpolation of quotes, backslashes and dollar signs in secrets:

```jinja2
<?php
return json_decode(<<<'KIRBY_CONFIG_JSON'
{{ seo_runtime_config | to_nice_json }}
KIRBY_CONFIG_JSON, true, 512, JSON_THROW_ON_ERROR);
```

Give the generated file mode `0600` and the PHP application's owner; set the
template task to `no_log: true` and `diff: false`. Keep the Vault password in your
password manager, not Git. Mount the private config read-only into containers and
give PHP a writable persistent token directory outside the web root. Each site
and environment needs its own token file. The Google connection writes that file
at runtime: **do not template, overwrite or commit OAuth tokens**.

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
