# Advanced configuration and migration

Start with the [setup guide](../README.md). All plugin options below belong inside
`timfeskens.seo` in your Kirby config array.

## Sidebar areas and existing site content

Installation automatically adds admin-only **SEO** (`/panel/seo`) and **Verkeer**
(`/panel/traffic`) sidebar areas. The global editor uses the same field names,
stored formats and site content as `tabs/seo-site`, including template variables,
social profiles, robots selections and file references. Remove the optional
`seo: tabs/seo-site` entry from the site's blueprint to avoid duplicate editors;
no content migration is needed. Page SEO tabs are unchanged. The global editor
validates and serializes with Kirby fields, allows only SEO keys, preserves
unrelated site fields, respects site update permission and existing edit locks,
and saves on explicit **Save settings** in the sticky header, not automatically
on input. Settings and previews/search performance use responsive columns.

Search Console configuration consists of `searchConsole.credentials` (either
the client ID/secret directly or Google's `web` wrapper), `searchConsole.tokenPath`
and an optional pinned `searchConsole.property`. Credentials and tokenPath make
the integration available automatically; there is no separate enable option.
Keep secrets server-side, outside Git and the document root. Connecting from
Verkeer returns to `/panel/traffic`; connecting from the SEO editor returns to
`/panel/seo`. Both share the existing `/__seo/gsc/callback` and read-only OAuth
connection. The sidebar dashboard has a one-hour cache and 7/30/90-day periods;
page-level query reports retain their existing cache and period. The SEO plugin
does not depend on the legacy Traffic plugin or collect visitor analytics.

## URLs and canonical overrides

Configure Kirby’s trusted `url` so generated URLs do not rely on arbitrary request
hostnames. `canonicalBase` can include an installation subdirectory. Multilingual
sites can use a language-code map:

```php
'canonicalBase' => ['nl' => 'https://example.nl', 'en' => 'https://example.com'],
```

Kirby supplies language paths, translated slugs and locales. Without
`canonicalBase`, the plugin uses Kirby’s page URL.

For content whose original lives elsewhere, use `canonicalUrl`:

```php
'canonicalUrl' => static function (\Kirby\Cms\Page $page, ?string $language): ?string {
    return $page->id() === 'syndicated-article'
        ? 'https://original.example/article'
        : null;
},
```

A fixed HTTP(S) string is also accepted, but applies to **every page**. Config takes
precedence over migrated `canonicalUrl` content. Returning `null` keeps the normal
canonical, including any saved override. Invalid URLs fall back to the natural
page canonical. Pages canonicalized elsewhere are omitted from the sitemap.
Canonical overrides are configured in PHP, not edited in the Panel.

## Inheritance and stored fields

Values resolve in this order: non-empty page value → explicitly inheriting ancestor
→ site default → plugin fallback. A parent’s `metaInherit` selects what it passes
to descendants. Empty, `default` and `[]` count as unset. Usually leave site-wide
`metaTitle` empty so each page uses its own title.

Default patterns are `{{ title }} - {{ site.title }}` for search, `{{ title }}` for
social titles and `{{ site.title }}` for the social site name. Compact tokens such
as `{{title}}` also work. Arbitrary queries/template expressions are not executed.
Unchanged inherited page text is not pinned as a page override on save.

### Migrating from tobimori/kirby-seo

1. Back up content and configuration.
2. Remove the old plugin and replace its blueprint tabs and head snippet using the
   [setup guide](../README.md#set-up-basic-seo).
3. Keep content files and file UUIDs unchanged.
4. Translate `tobimori.seo` configuration to `timfeskens.seo` deliberately; PHP APIs
   and configuration are **not drop-in compatible**.
5. Reconnect external services. OAuth connections are not automatically imported.

Shared content keys retain their names and stored formats: `metaTitle`,
`metaTemplate`, `useTitleTemplate`, `metaDescription`, `ogTemplate`, `useOgTemplate`,
`ogDescription`, `ogSiteName`, `ogImage`, `cropOgImage`, `metaInherit` and `robots*`.
Field names are case-insensitive in Kirby. Explicit inheritance choices and image
UUID references are preserved; no bulk rewrite runs automatically.

`socialMediaAccounts` retains the `twitter`, `facebook`, `instagram`, `youtube`,
`linkedin`, `bluesky` and `mastodon` object keys. Cropping defaults to enabled;
`cropOgImage: false` keeps the original image and dimensions. Legacy `robotsArchive`
values remain supported, though the control is hidden and engines may ignore it.

Our `canonicalUrl`, `twitterCardType` and `twitterAuthor` extensions remain supported
but do not have equivalent stock fields in the upstream blueprints. Custom KQL
title expressions and other plugins’ custom fields/commercial features are not
implemented. This is content compatibility, not API compatibility.

## Sitemap and public-page eligibility

`/sitemap.xml` is automatic; `/sitemap` redirects to it. `/robots.txt` advertises the
sitemap. Set `'sitemap' => false` or `'robots' => false` if another implementation
owns those routes.

Home and listed pages are indexable by default. Unlisted pages need explicit
indexing permission; drafts and error pages are always excluded. Global/page
robots settings, canonical overrides, language availability and the `include`
callback determine which pages appear. Only existing translations get alternates.
Unlisted pages are not private, and links remain followable unless configured
otherwise. Use authentication for confidential content.

To exclude application-specific pages from indexing, sitemap, IndexNow and LLM
exports:

```php
'include' => fn (\Kirby\Cms\Page $page, ?string $language) =>
    !in_array($page->intendedTemplate()->name(), ['account', 'internal'], true),
```

The plugin cannot infer authentication rules from templates. Audit exclusions
before enabling discovery integrations.

Sitemap dates use ISO 8601; unavailable dates are omitted. Priorities follow URL
depth: 1.0 for home, then 0.8, 0.6, 0.4, 0.2 and a 0.1 floor. Installation
subdirectories/language prefixes do not count. Google ignores this optional hint.
Sites exceeding 50,000 URLs need a separate sitemap-index solution.

`/sitemap.xsl` adds a browser table. Browsers retiring XSLT may show raw XML or a
deprecation warning; indexing and the sitemap data are unaffected.

## Structured data and PHP access

The plugin outputs WebSite, WebPage and breadcrumb data. Add site-specific schema
through a callback, using only validated, published facts:

```php
'schema' => function ($page, array $graph, array $meta): array {
    if ($page->intendedTemplate()->name() === 'recipe') {
        $recipe = myValidatedRecipeSchema($page);
        if ($recipe !== null) {
            unset($recipe['@context']);
            $graph[] = $recipe;
        }
    }
    return $graph;
},
```

Do not duplicate existing JSON-LD or invent ratings, nutrition or other claims.
Valid schema alone does not guarantee rich results.

PHP access: `$page->seo()`, `Seo::metadata($page, $language)`,
`Seo::canonical($page, $language)` and `Seo::render($page)` in the
`TimFeskens\Seo` namespace. Rendered metadata is not cached by the plugin.

## Private configuration and deployment

Options are direct PHP values; environment variables are not required. Use an
ignored domain-specific config locally. In production, generate protected config
from your secret manager, or use Ansible Vault. The website owns this deployment;
the plugin does not provision volumes, credentials or workers.

For Ansible, keep secrets in encrypted Vault variables and render the PHP config
with mode `0600`, owned by the PHP application user. Use `no_log: true` and
`diff: false` on secret-handling tasks. A JSON nowdoc avoids PHP interpolation:

```jinja2
<?php
return json_decode(<<<'KIRBY_CONFIG_JSON'
{{ seo_runtime_config | to_nice_json }}
KIRBY_CONFIG_JSON, true, 512, JSON_THROW_ON_ERROR);
```

Mount config read-only in containers and give PHP a writable, persistent Google
token directory outside the document root and Git. Each site/environment needs
its own token file. **Never template, overwrite, copy between sites or commit OAuth
tokens.** Keep Vault passwords in a secret manager, not Git.

### Analytics details

- Disabled integrations make no requests and show no UI. Enabled reports only run
  on authenticated Panel requests, not public page renders. Non-indexable pages
  are excluded from per-page reports.
- Google credentials accept either a client ID/secret array or Google’s downloaded
  `web` credentials array. `searchConsole.property` optionally pins a property and
  takes precedence over Panel selection. Accessible properties cache for five minutes.
- Google uses read-only OAuth with one-use, user-bound, expiring state and PKCE.
  Tokens are written atomically with `0600` permissions. Administrators manage the
  connection; editors with update permission can read reports.
- Google returns up to 25,000 query rows, not an exhaustive export. The Panel shows
  the top 10 initially, with sorting and an option to show all returned rows.
- Disconnect removes the local token file. Revoke the grant itself in
  [Google account connections](https://myaccount.google.com/connections).
- Ahrefs free mode shares a domain-level 24-hour cache between site/page reports
  and includes [required attribution](https://ahrefs.com/legal/domain-rating-license).
  Paid mode uses yesterday’s keyword/estimated traffic data: exact canonical URL
  for pages, canonical homepage prefix for the site. It can consume paid units.
- Provider failures do not affect public metadata. Credentials, tokens and raw
  provider errors are not sent to the Panel or logged.

## Optional AI suggestions

Provide your own server-side adapter, private credentials and explicit content
source:

```php
'ai' => [
    'enabled' => true,
    'source' => fn ($page) => $page->description()->value(),
    'provider' => function (array $input): array {
        // Input: title, text (max 12,000 chars), language, instruction.
        return myAiService($input); // ['title' => '...', 'description' => '...']
    },
],
```

Editors must confirm before sending saved page content. Suggestions are shown
separately and never saved automatically. Site-level suggestions are unsupported.
Your adapter must enforce timeouts, rate limits/budgets and prompt-injection
defenses. No provider or API key is bundled.

## Optional IndexNow

```php
'indexnow' => [
    'enabled' => true,
    'key' => 'your-verification-key', // 8–128 letters, digits or hyphens
],
```

The public verification file is served automatically. Eligible updates, status/slug
changes and impending deletions queue canonical URLs in Kirby’s cache. Use
persistent cache storage and flush through a **serialized, scheduled CLI job**:

```php
require __DIR__ . '/kirby/bootstrap.php';
$kirby = new Kirby();
$count = \TimFeskens\Seo\Integrations::flush();
```

Only `https://api.indexnow.org` is contacted. Failed batches stay queued. This is a
best-effort cache queue: concurrent writers may race, so it must not be your only
deletion notification mechanism or a substitute for the sitemap. Google does not
use IndexNow. No cron job is installed automatically.

## Optional LLM exports

```php
'llms' => [
    'enabled' => true,
    'include' => fn ($page) => $page->intendedTemplate()->name() === 'article',
    'markdown' => fn ($page) => '# ' . $page->title() . "\n\n" . $page->text(),
],
```

Both callbacks are required. Only opted-in, indexable public pages are listed in
`/llms.txt` and exported at `/seo/markdown/{encoded-page-id}.md`. Use an explicit
field allowlist; do not export private content. Markdown responses send
`X-Robots-Tag: noindex` to avoid competing with the HTML page. These experimental
conventions are not access controls, training opt-outs or ranking guarantees.
