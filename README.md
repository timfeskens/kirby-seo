# Kirby SEO

SEO settings, live previews and search reports for **Kirby 5.6+ and PHP 8.4+**.
MIT licensed. No plugin license key, telemetry or visitor tracking. Kirby itself
still requires a license for public use.

## What’s included

- Global defaults in the **SEO** sidebar and page-specific SEO settings
- Live Google, Facebook and Twitter/X previews
- Titles, descriptions, canonical URLs, social images and structured data
- Automatic XML sitemap, `robots.txt` and multilingual alternates
- Google Search Console dashboard in the **Verkeer** sidebar, with charts and breakdowns
- Optional page-specific Google Search Console and Ahrefs reports

External integrations are inactive without configuration. Search Console becomes
available automatically when Google credentials and a private token path are
configured; other integrations retain their explicit opt-in. Basic SEO works
without external services. The separate legacy Traffic plugin is not required.

## Set up basic SEO

### 1. Install the plugin

```sh
composer config repositories.timfeskens-seo vcs https://github.com/timfeskens/kirby-seo
composer require timfeskens/kirby-seo:^0.1
```

Composer installs the plugin in `site/plugins/timfeskens-seo`. Commit your
`composer.json` and `composer.lock`.

For a manual installation, download a [tagged release](https://github.com/timfeskens/kirby-seo/releases)
and place its contents in `site/plugins/timfeskens-seo`.

### 2. Open the sidebar and add page tabs

The plugin automatically adds **SEO** (`/panel/seo`) and **Verkeer**
(`/panel/traffic`) to the sidebar for administrators. Global defaults are edited
in **SEO**; no change to `site/blueprints/site.yml` is needed. Existing sites can
remove `seo: tabs/seo-site` from that file: saved SEO values stay in the same site
content fields and are not moved or rewritten. The blueprint remains available
for backward compatibility, but keeping it shows a second editor for the same
values.

In each page blueprint that needs SEO settings, add the page SEO tab:

```yaml
tabs:
  content:
    # Your existing sections or fields
  seo: tabs/seo-page
```

If your blueprint currently has top-level `fields`, `columns` or `sections`, move
them under the `content` tab first. Existing local SEO blueprints can override the
plugin’s blueprints; remove or adapt those if you want to use the shared UI.

### 3. Add metadata to your templates

Inside your shared `<head>`, replace existing SEO title, meta, canonical and
duplicated structured-data output with:

```php
<?php snippet('timfeskens-seo/meta') ?>
```

Render this once per page. Keep your charset, viewport, favicon and styles.
Site-specific structured data can stay if it does not duplicate the plugin’s output.

### 4. Configure your website URL

Add these settings to `site/config/config.php`, alongside your existing options:

```php
return [
    'url' => 'https://example.com',
    'timfeskens.seo' => [
        'canonicalBase' => 'https://example.com',
        'locale' => 'nl_NL',
        'description' => 'Your default website description.',
    ],
];
```

Use your actual domain. For multiple languages or custom canonical URLs, see
[advanced configuration](docs/configuration.md#urls-and-canonical-overrides).

### 5. Set your defaults and check the output

1. Open **SEO** in the sidebar and set your title pattern,
   description, social image and social profiles, then choose **Save SEO settings**.
2. Open a page’s **SEO** tab to override those defaults where needed.
3. Check the page source for one title and canonical URL, then visit `/sitemap.xml`
   and `/robots.txt`. Remove competing sitemap/robots routes if necessary.

The sitemap updates automatically; no cron job or regeneration is needed.

### Editing tips

- Insert title variables with the visual editor, or switch to **Code**. Supported
  variables are `{{ title }}`, `{{ page.title }}` and `{{ site.title }}`.
- Global previews show site defaults using the homepage as an example. Open the
  homepage’s own SEO tab to see its overrides.
- To inherit defaults on an existing page, clear its overrides and leave its
  title-pattern toggle enabled. Search and social descriptions are separate.
- Previews load behind skeletons and update while typing. They are an indication,
  not a guarantee of what Google or social platforms will display.
- Manage indexing through the global/page robots settings. **Disallow** excludes
  pages from the sitemap unless a page explicitly allows indexing. Robots settings
  do not protect private content; use authentication for that.

## Set up Google Search Console (optional)

The **Verkeer** sidebar dashboard shows property-wide clicks, impressions, CTR
and average position, with four daily charts and tables for queries, pages,
countries, devices and daily metrics. It is part of **this SEO plugin**, not the
legacy Traffic plugin, and works without that plugin installed. Page SEO tabs
also show queries for the page's exact canonical URL below the previews.

### 1. Enable the Google API

In [Google Cloud](https://console.cloud.google.com/projectselector2), select the
project you will use for your OAuth client. Enable **Google Search Console API**
in the [API Library](https://console.cloud.google.com/apis/library/searchconsole.googleapis.com).

**This is required even if Google sign-in already works.**

### 2. Create OAuth credentials

1. Configure the [OAuth consent screen](https://console.cloud.google.com/auth/overview).
2. Create a **Web application** client under [Clients](https://console.cloud.google.com/auth/clients).
3. Add your exact redirect URI: `https://example.com/__seo/gsc/callback`.
   Localhost needs its own URI, including the port. The callback uses Kirby’s
   configured site URL, not `canonicalBase`.
4. Keep the client ID and client secret for the next step.

### 3. Add private server configuration

In an ignored, [domain-specific Kirby config file](https://getkirby.com/docs/guide/configuration#domain-specific-config-files),
add:

```php
<?php
return [
    'timfeskens.seo' => [
        'searchConsole' => [
            'credentials' => [
                'client_id' => 'your-client-id.apps.googleusercontent.com',
                'client_secret' => 'your-client-secret',
            ],
            'tokenPath' => '/absolute/private/path/google-search-console.json',
        ],
    ],
];
```

Create the token file’s parent directory **outside the document root and Git**, and
make it writable by PHP. The plugin creates the token file when you connect.
In Docker, use persistent storage. Each website/environment needs its own token
file. Never commit credentials or tokens.

No enable switch is needed: adding both client credentials and `tokenPath`
automatically makes Search Console available. Remove those credentials to stop
using the integration. The dashboard only contacts Google after configuration
and connection; simply installing the plugin does not send data to Google.

### 4. Connect in the Panel

As an administrator, open **Verkeer**, click **Koppelen met Google**, grant
read-only access and select the intended Search Console property. Your Google
account must have [access to that property](https://support.google.com/webmasters/answer/7687615).
Repeat this connection step for each website.

Dashboard periods are 7, 30 or 90 inclusive days, ending three days ago in Google's
Pacific timezone. Finalized organic web-search data is cached for one hour;
**Vernieuwen** reloads without bypassing that cache. Each uncached report makes
six API queries. Summary totals are queried independently from privacy-filtered
breakdowns; CTR and position come from Google, not averages of visible rows.
Tables show at most 100 top rows, not a complete export. Missing days have zero
clicks/impressions and no position. Google can omit anonymized queries.

Page reports retain their 28-day window and 24-hour cache and use the exact
canonical URL. Both views share the same account, property and token file;
disconnecting in one disconnects the other. These metrics describe search
results, not tracked page views, and will not necessarily match the legacy
traffic report. Update the legacy Traffic plugin to v0.1.1+ when using both:
its sidebar entry moves to **Verkeer (deprecated)** at `/panel/traffic-deprecated`.

### Troubleshooting

| Problem | What to do |
| --- | --- |
| `403 accessNotConfigured` / `SERVICE_DISABLED` | Enable **Google Search Console API in the project that owns your OAuth client**, wait for activation, then retry. **Reconnecting alone will not fix this.** |
| Access expired or revoked | Reconnect Google. OAuth apps in external **Testing** mode can have short-lived refresh tokens; check the consent app’s publishing status. |
| No access to the property | Check the connected account’s Search Console permissions or choose another property. |
| API quota reached | Wait and retry, or check the project’s quota in Google Cloud. |
| Connection cannot be stored | Check that the private token directory exists, is outside the web root and is writable by PHP. |

## Set up Ahrefs (optional)

1. Create a [free Ahrefs API key](https://app.ahrefs.com/account/api-keys).
2. Add the following inside `timfeskens.seo` in your private server config:

   ```php
   'ahrefs' => [
       'enabled' => true,
       'apiKey' => 'your-ahrefs-api-key',
       'mode' => 'free',
   ],
   ```

3. Open the SEO tab. No separate connection step is needed.

Free mode shows **Domain Rating by Ahrefs**: a domain-wide backlink rating from
0–100, not page traffic or a Google ranking score. A free key is required; no paid
subscription is needed. Results are cached for 24 hours.

Paid keyword and estimated traffic reports require an eligible subscription and
explicit `'mode' => 'paid'`. Check [API access and costs](https://docs.ahrefs.com/en/api/docs/introduction)
before enabling this mode.

## Update

```sh
composer update timfeskens/kirby-seo
```

Commit the updated lockfile and deploy through your website’s normal workflow.
Do not edit installed plugin files. Updates do not rewrite your content.

## More information

- [Advanced configuration and migration](docs/configuration.md): inheritance,
  canonical overrides, structured data, private deployment, AI, IndexNow and LLM exports
- [Changelog](CHANGELOG.md)
- [MIT license](LICENSE)

Built independently by Tim Feskens. Shared content fields support migration from
[tobimori/kirby-seo](https://github.com/tobimori/kirby-seo); PHP/config APIs are not
drop-in compatible. No proprietary upstream code or assets are included.

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

Tests use isolated fixtures and mocked external integrations, not production data.
Panel components require no JavaScript build step.
