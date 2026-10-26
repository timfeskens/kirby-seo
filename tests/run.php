<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/index.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Form\Field;
use TimFeskens\Seo\Endpoints;
use TimFeskens\Seo\Integrations;
use TimFeskens\Seo\Preview;
use TimFeskens\Seo\Seo;
use TimFeskens\Seo\Analytics;
use TimFeskens\Seo\SearchConsole;

$count = 0;
function check(bool $condition, string $message): void
{
    global $count;
    $count++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fixture(array $options = [], array $siteContent = [], array $request = []): array
{
    $root = sys_get_temp_dir() . '/kirby-seo-tests-' . getmypid();
    $kirby = new App([
        'request' => $request,
        'roots' => ['index' => $root, 'cache' => $root . '/cache'],
        'urls' => ['index' => 'https://preview.example.test'],
        'options' => array_replace_recursive([
            'timfeskens.seo.canonicalBase' => 'https://example.test',
            'timfeskens.seo.locale' => 'nl_NL',
        ], $options),
        'site' => [
            'content' => array_replace(['title' => 'Example', 'metaDescription' => 'Site description'], $siteContent),
            'children' => [
                ['slug' => 'home', 'template' => 'home', 'content' => ['title' => 'Home']],
                ['slug' => 'error', 'template' => 'error', 'content' => ['title' => 'Error']],
                ['slug' => 'recipes', 'num' => 1, 'content' => ['title' => 'Recipes', 'metaInherit' => 'metaDescription', 'metaDescription' => 'Parent description'], 'children' => [
                    ['slug' => 'cake', 'num' => 1, 'content' => ['title' => 'Cake <script>alert(1)</script>']],
                ]],
                ['slug' => 'hidden', 'content' => ['title' => 'Hidden']],
                ['slug' => 'listed', 'num' => 2, 'content' => ['title' => 'Listed', 'robotsIndex' => 'false']],
                 ['slug' => 'duplicate', 'num' => 3, 'content' => ['title' => 'Duplicate', 'canonicalUrl' => 'https://original.example.test/article']],
                ['slug' => 'invalid-canonical', 'num' => 4, 'content' => ['title' => 'Invalid override', 'canonicalUrl' => 'not a URL']],
            ],
        ],
    ]);
    return [$kirby, $kirby->page('recipes/cake')];
}

[$kirby, $page] = fixture();
check($page instanceof Page, 'Fixture page exists');
$meta = Seo::metadata($page);
check($meta['canonical'] === 'https://example.test/recipes/cake', 'Canonical origin');
check(Seo::canonical($kirby->site()->homePage()) === 'https://example.test', 'Home canonical');
check($meta['tags']['description'] === 'Parent description', 'Explicit parent inheritance');
check($meta['tags']['og:locale'] === 'nl_NL', 'Locale configurable');
check($meta['tags']['robots'] === 'index, follow', 'Listed pages indexable');
check(!Seo::indexable($kirby->page('hidden')), 'Unlisted pages not indexable by default');
check(Seo::allowed($kirby->page('hidden'), 'follow'), 'Unlisted links still followed');
check(!Seo::indexable($kirby->page('error')), 'Error excluded');
check(!Seo::indexable($kirby->page('listed')), 'Explicit noindex respected');
$override = $page->clone(['content' => ['title' => 'Own title', 'metaDescription' => 'Own description']]);
check(Seo::metadata($override)['tags']['description'] === 'Own description', 'Page override');
check(Seo::metadata($override)['title'] === 'Own title - Example', 'Title template');
$html = Seo::render($page);
check(substr_count($html, '<title>') === 1, 'Single title');
check(!str_contains($html, '<script>alert'), 'HTML stripped from title');
check(str_contains(Seo::json(['text' => '</script><script>']), '\u003C'), 'JSON script delimiter escaped');
check(str_contains(Seo::escape('"<>'), '&quot;&lt;&gt;'), 'Attribute escaping');
$doc = new DOMDocument();
check($doc->loadXML(Endpoints::sitemap()->body()), 'Valid sitemap XML');
$xml = Endpoints::sitemap()->body();
check(!str_contains($xml, '/hidden'), 'Unlisted excluded from sitemap');
check(!str_contains($xml, '/listed'), 'Noindex excluded from sitemap');
check(!str_contains($xml, '/duplicate'), 'External canonical excluded from sitemap');
check(str_contains($xml, '/recipes/cake'), 'Child included in sitemap');
check(str_contains($xml, '/invalid-canonical'), 'Invalid override does not remove valid sitemap URL');
$xpath = new DOMXPath($doc);
$xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
check($xpath->query('//sm:loc[text()="https://example.test"]')->length === 1, 'Homepage included once');
check($xpath->evaluate('string(//sm:url[sm:loc="https://example.test"]/sm:priority)') === '1.0', 'Homepage priority is 1.0');
check($xpath->evaluate('string(//sm:url[sm:loc="https://example.test/recipes"]/sm:priority)') === '0.8', 'Top-level priority is 0.8');
check($xpath->evaluate('string(//sm:url[sm:loc="https://example.test/recipes/cake"]/sm:priority)') === '0.6', 'Second-level priority is 0.6');
check($xpath->query('//sm:priority')->length === $xpath->query('//sm:url')->length, 'Every sitemap entry has a priority');
check(!str_contains($xml, '/error'), 'Error page absent from sitemap');
check(str_contains($xml, "\n  <url>\n    <loc>"), 'Sitemap XML is indented');
check(str_contains($xml, 'href="https://preview.example.test/sitemap.xsl"'), 'Stylesheet uses serving origin rather than canonical origin');
$stylesheet = new DOMDocument();
check($stylesheet->loadXML(Endpoints::sitemapStylesheet()->body()), 'Stylesheet is valid XML');
check($stylesheet->documentElement->namespaceURI === 'http://www.w3.org/1999/XSL/Transform', 'Stylesheet is XSLT');
$lastmods = $xpath->query('//sm:lastmod');
check($lastmods->length > 0, 'Date regression has actual modification dates');
foreach (['date', 'intl'] as $handler) {
    [$dateKirby, $datePage] = fixture(['date.handler' => $handler]);
    $dates = new DOMDocument();
    $dates->loadXML(Endpoints::sitemap()->body());
    $dateXpath = new DOMXPath($dates);
    $dateXpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    foreach ($dateXpath->query('//sm:lastmod') as $date) {
        $parsed = DateTimeImmutable::createFromFormat(DATE_ATOM, $date->textContent);
        check($parsed !== false && $parsed->format(DATE_ATOM) === $date->textContent, $handler . ' emits valid ISO 8601 lastmod');
    }
    $expected = date(DATE_ATOM, $datePage->modified());
    check($dateXpath->evaluate('string(//sm:url[sm:loc="https://example.test/recipes/cake"]/sm:lastmod)') === $expected, $handler . ' preserves the actual page modification timestamp');
}
[$kirby, $page] = fixture();
$subdirectory = new App([
    'roots' => ['index' => sys_get_temp_dir() . '/kirby-seo-subdir-' . getmypid()],
    'urls' => ['index' => 'https://preview.example.test/subdirectory'],
    'site' => ['children' => [['slug' => 'home', 'template' => 'home', 'content' => ['title' => 'Home']]]],
]);
check(str_contains(Endpoints::sitemap()->body(), 'href="https://preview.example.test/subdirectory/sitemap.xsl"'), 'Stylesheet supports installation subdirectories');
$tree = [];
for ($level = 6; $level >= 1; $level--) {
    $tree = [['slug' => 'level-' . $level, 'num' => 1, 'content' => ['title' => 'Level ' . $level], 'children' => $tree]];
}
$depthKirby = new App([
    'roots' => ['index' => sys_get_temp_dir() . '/kirby-seo-depth-' . getmypid()],
    'urls' => ['index' => 'https://preview.example.test/install'],
    'options' => ['timfeskens.seo.canonicalBase' => 'https://example.test/install'],
    'site' => ['children' => array_merge([['slug' => 'home', 'template' => 'home', 'content' => ['title' => 'Home']]], $tree)],
]);
$path = '';
foreach (['0.8', '0.6', '0.4', '0.2', '0.1', '0.1'] as $index => $priority) {
    $path .= ($path === '' ? '' : '/') . 'level-' . ($index + 1);
    check(Endpoints::sitemapPriority($depthKirby->page($path)) === $priority, 'URL level ' . ($index + 1) . ' priority excludes installation path and stays at least 0.1');
}
[$kirby, $page] = fixture();
check(str_contains(Endpoints::robots()->body(), 'https://example.test/sitemap.xml'), 'Canonical sitemap in robots');
check(Endpoints::llms()->code() === 404, 'LLM disabled by default');
check(Endpoints::markdown('recipes/cake')->code() === 404, 'Markdown disabled by default');
check(!Integrations::indexNowEnabled(), 'IndexNow disabled by default');
try {
    Integrations::suggest($page);
    check(false, 'AI disabled by default');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'AI assistance is not configured.', 'AI disabled by default');
}

// Instantiate a real Kirby field and resolve blueprint extensions.
$blueprint = Kirby\Cms\Blueprint::load('tabs/seo-page');
check(!isset($blueprint['columns']['settings']['sections']['seoAdvanced']['fields']['canonicalUrl']), 'Canonical overrides are config-only, not Panel controls');
$field = new Field('timfeskens-seo-preview', ['name' => 'seoPreview', 'model' => $page]);
check($field->toArray()['preview']['canonical'] === $meta['canonical'], 'Preview field serializes');
$siteField = new Field('timfeskens-seo-preview', ['name' => 'seoPreview', 'model' => $kirby->site()]);
check(is_array($siteField->toArray()['preview']), 'Site preview field serializes');
$preview = Preview::metadata($page, ['metaTitle' => 'Unsaved title', 'metaDescription' => 'Unsaved description', 'ogImage' => []]);
check($preview['title'] === 'Unsaved title - Example', 'Unsaved page title preview');
check($preview['tags']['description'] === 'Unsaved description', 'Unsaved page description preview');
check(Seo::metadata($page)['tags']['description'] === 'Parent description', 'Preview never mutates page');
$preview = Preview::metadata($kirby->site(), ['title' => 'Unsaved site', 'metaDescription' => 'Unsaved site description']);
check($preview['title'] === 'Home - Unsaved site', 'Unsaved site title pattern');
check($preview['tags']['description'] === 'Unsaved site description', 'Unsaved site description preview');
check($kirby->site()->title()->value() === 'Example', 'Preview restores site context');
$overrideSite = $kirby->site()->clone([]);
$overrideHome = $overrideSite->homePage()->clone(['site' => $overrideSite, 'content' => [
    'title' => 'Home', 'metaTitle' => 'Homepage title override', 'metaTemplate' => '{{ site.title }} | Developer',
    'metaDescription' => 'Homepage description override', 'useTitleTemplate' => 'false',
    'ogTemplate' => 'Homepage social override', 'ogDescription' => 'Homepage social description override', 'useOgTemplate' => 'false',
]]);
$overrideSite->children()->set('home', $overrideHome);
$preview = Preview::metadata($overrideSite, ['metaTemplate' => '{{ title }} - {{ site.title }} test', 'metaDescription' => 'New global description', 'ogTemplate' => '{{ title }} social', 'ogDescription' => 'New global social description']);
check($preview['title'] === 'Home - Example test', 'Global preview is not masked by homepage title overrides or disabled title patterns');
check($preview['tags']['description'] === 'New global description', 'Global preview ignores saved homepage description overrides');
check($preview['tags']['og:title'] === 'Home social', 'Global preview uses global social pattern');
check($preview['tags']['twitter:description'] === 'New global social description', 'Global preview uses global social description');
check($preview['scope'] === 'site-defaults' && str_ends_with($preview['homePanelUrl'], '/pages/home?tab=seo'), 'Global preview labels its scope and links to homepage SEO');
check(Preview::metadata($overrideHome, [])['tags']['description'] === 'Homepage description override', 'Page preview retains explicit overrides');
check(Seo::metadata($overrideHome)['title'] === 'Homepage title override', 'Global preview never changes real homepage metadata');
check($overrideHome->metaTemplate()->value() === '{{ site.title }} | Developer', 'Global preview preserves migrated homepage fields');
foreach (['site', 'page'] as $type) {
    $model = $type === 'site' ? $kirby->site() : $page;
    $blueprint = new Kirby\Cms\Blueprint(['name' => 'seo-test-' . $type, 'model' => $model, 'tabs' => ['seo' => 'tabs/seo-' . $type]]);
    $fields = new Kirby\Form\Fields($blueprint->fields(), $model);
    check(count($fields->toArray()) > 5, 'SEO ' . $type . ' blueprint resolves all fields');
    check(isset($fields->toProps()['seopreview']['preview']), 'SEO ' . $type . ' Panel props resolve');
    $props = $fields->toProps();
    check($blueprint->tabs()[0]['label'] === ($type === 'site' ? 'Global SEO settings' : 'SEO'), $type . ' SEO tab label distinguishes scope');
    foreach (['searchheading', 'socialheading', 'robotsheading'] as $heading) {
        check(isset($props[$heading]), $type . ' grouped SEO heading: ' . $heading);
    }
    foreach (['robotsindex', 'robotsfollow', 'robotsimageindex', 'robotssnippet'] as $name) {
        $option = array_values(array_filter($props[$name]['options'], fn ($option) => $option['value'] === 'default'))[0];
        check($option['text'] === ($type === 'site' ? 'Automatic' : 'Inherit / automatic'), $type . ' robots default label: ' . $name);
        check(count($props[$name]['options']) === 3, $type . ' retains automatic/allow/disallow robots options');
    }
    check(!isset($props['robotsarchive']), $type . ' obsolete archive control absent');
    if ($type === 'page') {
        foreach (['metatitle', 'usetitletemplate', 'useogtemplate', 'twitterauthor', 'metainherit'] as $name) {
            check(isset($props[$name]), 'Page-specific field retained: ' . $name);
        }
        check(!isset($props['ogsitename']) && !isset($props['socialmediaaccounts']), 'Site-only fields absent on pages');
        check(str_starts_with($blueprint->fields()['ogImage']['query'], 'page.files'), 'Page image query retained');
    }
    check($props['seopreview']['type'] === 'timfeskens-seo-preview', 'Panel preview uses kebab-case registry key');
    foreach (['metatemplate' => '{{ title }} - {{ site.title }}', 'ogtemplate' => '{{ title }}'] as $key => $expected) {
        check($props[$key]['default'] === $expected, $type . ' literal pattern default');
        check($props[$key]['placeholder'] === $expected, $type . ' literal pattern placeholder');
        check($fields->toFormValues()[$key] === $expected, $type . ' empty existing pattern filled');
    }
    check($props['twittercardtype']['default'] === 'default', 'Automatic Twitter card default');
    check($fields->toFormValues()['twittercardtype'] === 'default', 'Automatic Twitter card filled for existing content');
    check($fields->toFormValues()['robotsindex'] === 'default', 'Automatic robots selector filled');
    if ($type === 'site') {
        check($fields->toFormValues()['ogsitename'] === '{{ site.title }}', 'Social site name default filled');
        check($props['ogsitename']['placeholder'] === '{{ site.title }}', 'Social site name literal placeholder');
        check($props['ogsitename']['type'] === 'timfeskens-seo-template', 'Social site name uses the same variable editor as title patterns');
        check(Preview::metadata($kirby->site(), ['ogSiteName' => '{{ site.title }} / {{ title }}'])['tags']['og:site_name'] === 'Example / Home', 'Social site name resolves the shared template variables');
        check(Preview::metadata($kirby->site(), ['ogSiteName' => 'Existing brand'])['tags']['og:site_name'] === 'Existing brand', 'Migrated plain social site names remain unchanged');
    }
}
$legacy = $page->clone(['content' => array_replace($page->content()->toArray(), ['robotsArchive' => 'disallow'])]);
check(str_contains(Seo::metadata($legacy)['tags']['robots'], 'noarchive'), 'Legacy archive directive still supported');
$pattern = new Field('timfeskens-seo-text', ['name' => 'metaTemplate', 'model' => $page, 'value' => '{{ title }} | Custom']);
check($pattern->toArray()['value'] === '{{ title }} | Custom', 'Existing custom patterns preserved');
$customSite = $kirby->site()->clone(['content' => ['title' => 'Example', 'metaTemplate' => '{{ title }} | Custom site']]);
$customPage = $page->clone(['site' => $customSite]);
$pattern = new Field('timfeskens-seo-text', ['name' => 'metaTemplate', 'model' => $customPage]);
check($pattern->toArray()['value'] === '{{ title }} | Custom site', 'Page default uses inherited site pattern');
check($pattern->toStoredValue() === '', 'Unchanged inherited pattern is not pinned on save');
$pattern->fill('{{ title }} | Page override');
check($pattern->toStoredValue() === '{{ title }} | Page override', 'Explicit page override is saved');
check(Seo::metadata($page->clone(['content' => ['title' => 'Automatic', 'twitterCardType' => 'default']]))['tags']['twitter:card'] === 'summary', 'Automatic Twitter selector resolves to a valid card');
check($kirby->call('sitemap.xml')->code() === 200, 'Sitemap registered as a working route');
$description = new Field('timfeskens-seo-textarea', ['name' => 'metaDescription', 'model' => $page]);
check($description->toArray()['value'] === 'Parent description', 'Description default uses configured inheritance');
check($description->toArray()['placeholder'] === 'Parent description', 'Description placeholder matches inherited default');
check($description->toStoredValue() === '', 'Unchanged inherited description is not pinned on save');
$toggle = new Field('timfeskens-seo-toggle', ['name' => 'useTitleTemplate', 'model' => $page, 'default' => true]);
check($toggle->toArray()['value'] === true, 'Missing pattern toggle filled with true');
$toggle->fill(false);
check($toggle->toArray()['value'] === false, 'Explicit disabled pattern toggle preserved');
$toggle->fill('true');
check($toggle->toArray()['value'] === true, 'Stored enabled toggle becomes a boolean');
$toggle->fill('false');
check($toggle->toArray()['value'] === false, 'Stored disabled toggle becomes a boolean');
check(Analytics::providers() === [], 'Both analytics integrations disabled by default');
check(Seo::metadata($page->clone(['content' => ['title' => 'Native', 'metaTemplate' => '{{title}} | {{site.title}}']]))['title'] === 'Native | Example', 'Compact legacy template tokens supported');
$template = new Field('timfeskens-seo-template', ['name' => 'metaTemplate', 'model' => $page]);
check($template->toStoredValue() === '', 'Chip editor does not pin inherited patterns');
check($template->toArray()['type'] === 'timfeskens-seo-template', 'Dedicated template field registered');
$cropParent = $page->parent()->clone(['content' => ['metaInherit' => 'cropOgImage', 'cropOgImage' => 'false']]);
check(Seo::field($page->clone(['parent' => $cropParent]), 'cropOgImage')->value() === 'false', 'Migrated cropOgImage field inherits unchanged');
$imageRoot = $page->root();
Kirby\Filesystem\Dir::make($imageRoot);
$imagePath = $imageRoot . '/social.png';
$image = imagecreatetruecolor(640, 480);
imagepng($image, $imagePath);
$imagePage = $page->clone(['files' => [['filename' => 'social.png', 'content' => ['uuid' => 'test-social-image']]], 'content' => array_replace($page->content()->toArray(), ['ogImage' => '- social.png', 'cropOgImage' => 'false'])]);
$imageMeta = Seo::metadata($imagePage);
check($imageMeta['tags']['og:image:width'] === '640' && $imageMeta['tags']['og:image:height'] === '480', 'Disabled cropping retains original image dimensions');
check(Preview::metadata($imagePage, ['cropOgImage' => 'true'])['tags']['og:image:width'] === '1200', 'Unsaved crop setting updates preview');
$picker = $imagePage->files()->first()->panel()->pickerData(['model' => $imagePage]);
$kirby->site()->index()->set($imagePage->id(), $imagePage);
check(Preview::metadata($imagePage, ['ogImage' => [$picker]])['tags']['og:image'] === $imageMeta['tags']['og:image'], 'Native Panel picker UUID resolves to the selected image');
$pickerWithoutUuid = $picker;
unset($pickerWithoutUuid['uuid']);
check(Preview::metadata($imagePage, ['ogImage' => [$pickerWithoutUuid]])['tags']['og:image'] === $imageMeta['tags']['og:image'], 'Panel picker object resolves by file id instead of encoding an object as a stored reference');
check(Preview::metadata($imagePage, ['ogImage' => ['social.png']])['tags']['og:image'] === $imageMeta['tags']['og:image'], 'String reference arrays still resolve');
check(Preview::metadata($imagePage, ['ogImage' => '- social.png'])['tags']['og:image'] === $imageMeta['tags']['og:image'], 'Migrated stored YAML image references still resolve');
check(Preview::metadata($imagePage, ['ogImage' => [['url' => 'https://untrusted.example/image.png']]])['tags']['og:image'] === null, 'Preview does not trust client-supplied image URLs');
check(Preview::metadata($imagePage, ['ogImage' => []])['tags']['og:image'] === null, 'Clearing image selection removes the page image when no fallback exists');
check(Seo::metadata($imagePage)['tags']['og:image'] === $imageMeta['tags']['og:image'], 'Image preview never changes saved content');
unlink($imagePath);

// Real OAuth state/PKCE, private token storage, refresh, queries and quota-safe caching.
$private = sys_get_temp_dir() . '/kirby-seo-oauth-' . getmypid();
mkdir($private, 0700, true);
$tokenPath = $private . '/tokens.json';
$requests = [];
$options = [
    'timfeskens.seo.searchConsole.enabled' => true,
    'timfeskens.seo.searchConsole.credentials' => ['web' => ['client_id' => 'test-client', 'client_secret' => 'test-secret']],
    'timfeskens.seo.searchConsole.tokenPath' => $tokenPath,
    'timfeskens.seo.ahrefs.enabled' => true,
    'timfeskens.seo.ahrefs.apiKey' => 'test-ahrefs-key',
    'timfeskens.seo.ahrefs.mode' => 'paid',
    'timfeskens.seo.analyticsTransport' => function ($url, $options) use (&$requests): array {
        $requests[] = [$url, $options];
        if (str_contains($url, '/token')) return ['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600];
        if (str_contains($url, '/searchAnalytics/query')) return ['rows' => [['keys' => ['cake <script>'], 'clicks' => 5, 'impressions' => 100, 'ctr' => .05, 'position' => 3.2]]];
        if (str_contains($url, 'api.ahrefs.com')) return ['metrics' => ['org_keywords' => 7, 'org_traffic' => 42, 'unused' => 'not exposed']];
        return ['siteEntry' => [['siteUrl' => 'sc-domain:example.test']]];
    },
];
[$kirby, $page] = fixture($options);
try { Analytics::data($page, 'ahrefs'); check(false, 'Anonymous analytics rejected'); }
catch (RuntimeException $e) { check($e->getMessage() === 'Not allowed.', 'Anonymous analytics rejected'); }
$kirby->impersonate('kirby');
check(Analytics::data($page, 'searchConsole')['status'] === 'disconnected', 'Enabled GSC starts disconnected');
$auth = SearchConsole::connect();
parse_str((string)parse_url($auth, PHP_URL_QUERY), $authQuery);
check($authQuery['scope'] === 'https://www.googleapis.com/auth/webmasters.readonly', 'OAuth uses only read-only scope');
check($authQuery['code_challenge_method'] === 'S256', 'OAuth uses PKCE');
$pending = $kirby->session()->get('timfeskens.seo.oauth');
check(strlen($pending['state']) === 64, 'OAuth state is random');
$kirby->session()->commit();
[$kirby, $page] = fixture($options, request: ['query' => ['state' => $pending['state'], 'code' => 'test-code']]);
$kirby->impersonate('kirby');
$kirby->session()->set('timfeskens.seo.oauth', $pending);
$invalid = $pending;
$invalid['state'] = 'different-state';
$kirby->session()->set('timfeskens.seo.oauth', $invalid);
$before = count($requests);
check(SearchConsole::callback()->code() === 400 && count($requests) === $before, 'Invalid OAuth state rejected before token exchange');
$kirby->session()->set('timfeskens.seo.oauth', $pending);
check(SearchConsole::callback()->code() === 302, 'Valid OAuth callback connects');
check((fileperms($tokenPath) & 0777) === 0600, 'Tokens stored with private permissions');
check($kirby->session()->get('timfeskens.seo.oauth') === null, 'OAuth state consumed');
check(SearchConsole::callback()->code() === 400, 'OAuth callback replay rejected');
check(Analytics::data($page, 'searchConsole')['status'] === 'property', 'Property selection required, never picks unrelated property');
try { SearchConsole::selectProperty('sc-domain:unrelated.test'); check(false, 'Unknown property rejected'); }
catch (RuntimeException) { check(true, 'Unknown property rejected'); }
SearchConsole::selectProperty('sc-domain:example.test');
$result = Analytics::data($page, 'searchConsole');
check($result['rows'][0]['clicks'] === 5.0, 'Search query metrics returned');
$queryRequest = array_values(array_filter($requests, fn ($r) => str_contains($r[0], '/searchAnalytics/query')))[0];
$body = json_decode($queryRequest[1]['data'], true);
check($body['dimensionFilterGroups'][0]['filters'][0]['expression'] === Seo::canonical($page), 'Page query uses canonical URL');
check($body['dimensionFilterGroups'][0]['filters'][0]['operator'] === 'equals', 'Page filter never matches unrelated paths');
$before = count($requests);
Analytics::data($page, 'searchConsole');
check(count($requests) === $before, 'Search Console queries cached');
check(!str_contains(json_encode($result), 'test-secret') && !str_contains(json_encode($result), 'test-access'), 'No OAuth secrets in Panel response');
$siteResult = Analytics::data($kirby->site(), 'searchConsole');
$siteQuery = json_decode(end($requests)[1]['data'], true);
check(!isset($siteQuery['dimensionFilterGroups']), 'Site query is property-wide');
$result = Analytics::data($page, 'ahrefs');
check($result['metrics'] === ['org_keywords' => 7, 'org_traffic' => 42], 'Ahrefs response allowlists metrics');
$ahrefsRequest = end($requests);
parse_str(parse_url($ahrefsRequest[0], PHP_URL_QUERY), $ahrefsQuery);
check($ahrefsQuery['mode'] === 'exact' && $ahrefsQuery['target'] === Seo::canonical($page), 'Ahrefs page scope is exact canonical URL');
$before = count($requests);
Analytics::data($page, 'ahrefs');
check(count($requests) === $before, 'Ahrefs results cached to avoid repeated quota charges');
check(Analytics::data($kirby->page('hidden'), 'ahrefs')['status'] === 'excluded', 'Private/unlisted page URLs not sent');
check(count($requests) === $before, 'Excluded pages do not contact provider');
$tokens = json_decode(file_get_contents($tokenPath), true);
$tokens['expires_at'] = 0;
file_put_contents($tokenPath, json_encode($tokens));
SearchConsole::selectProperty('sc-domain:example.test');
check(json_decode(file_get_contents($tokenPath), true)['expires_at'] > time(), 'Expired access token refreshed');
SearchConsole::disconnect();
check(!is_file($tokenPath), 'Disconnect deletes private tokens');
$kirby->session()->commit();
[$kirby, $page] = fixture(array_replace($options, ['timfeskens.seo.searchConsole.enabled' => false, 'timfeskens.seo.ahrefs.enabled' => false]));
$kirby->impersonate('kirby');
$before = count($requests);
try { Analytics::data($page, 'ahrefs'); check(false, 'Disabled provider inaccessible'); }
catch (RuntimeException) { check(true, 'Disabled provider inaccessible'); }
check(count($requests) === $before, 'Disabled integrations make no requests');
rmdir($private);

[$kirby, $page] = fixture([
    'timfeskens.seo.ahrefs.enabled' => true,
    'timfeskens.seo.ahrefs.apiKey' => 'free-test-key',
    'timfeskens.seo.analyticsTransport' => function ($url, $options) use (&$requests): array {
        $requests[] = [$url, $options];
        return ['domain_rating' => ['domain_rating' => 5, 'license' => 'http://ahrefs.com/legal/domain-rating-license']];
    },
]);
$kirby->impersonate('kirby');
$free = Analytics::data($kirby->site(), 'ahrefs');
check($free['mode'] === 'free' && $free['metrics'] === ['domain_rating' => 5.0], 'Ahrefs defaults to free Domain Rating, not paid Site Explorer');
$request = end($requests);
check(str_contains($request[0], '/v3/public/domain-rating-free?target=example.test') && $request[1]['headers']['Authorization'] === 'Bearer free-test-key', 'Free endpoint uses a free key and domain-only scope');
$before = count($requests);
check(Analytics::data($page, 'ahrefs')['metrics'] == $free['metrics'] && count($requests) === $before, 'Domain Rating cache is shared between site and page reports');
[$kirby, $page] = fixture(['timfeskens.seo.ahrefs.enabled' => true]);
$kirby->impersonate('kirby');
check(Analytics::data($page, 'ahrefs')['status'] === 'credentials', 'Missing key explains free-key requirement without anonymous or paid requests');
check(count($requests) === $before, 'No Ahrefs network request without a key');

// Apply a callback to every page to verify config precedence independently of IDs.
[$kirby, $page] = fixture(['timfeskens.seo.canonicalUrl' => static fn ($page, $language) => 'https://original.example.test/config']);
check(Seo::metadata($page)['canonical'] === 'https://original.example.test/config', 'Canonical URL callback is configurable outside the Panel');
check(!str_contains(Endpoints::sitemap()->body(), '<url>'), 'Config-canonicalized duplicates are excluded from sitemap');
[$kirby, $page] = fixture(['timfeskens.seo.canonicalUrl' => 'javascript:alert(1)']);
check(Seo::canonical($page) === Seo::canonicalDefault($page), 'Invalid config canonical cannot inject unsafe URL schemes');
check(Preview::metadata($page, ['canonicalUrl' => 'https://untrusted.example.test'])['canonical'] === Seo::canonical($page), 'Preview API cannot introduce unsaved canonical overrides');
[$kirby, $page] = fixture(['timfeskens.seo.canonicalUrl' => static fn ($page, $language) => null]);
check(Seo::canonical($kirby->page('duplicate')) === 'https://original.example.test/article', 'Null config callback preserves migrated canonical content');

[$kirby, $page] = fixture(siteContent: ['robotsIndex' => 'false']);
check(!Seo::indexable($page), 'Global Panel indexing setting applies to pages');
check(str_contains(Seo::metadata($page)['tags']['robots'], 'noindex'), 'Global Panel setting emits noindex');
check(!str_contains(Endpoints::sitemap()->body(), '<url>'), 'Global Panel noindex empties sitemap');
$override = $page->clone(['content' => array_replace($page->content()->toArray(), ['robotsIndex' => 'true'])]);
check(Seo::indexable($override), 'Page Panel setting overrides global noindex');
check(!str_contains(Seo::metadata($override)['tags']['robots'], 'noindex'), 'Explicit page permission emits indexable metadata');

[$kirby, $page] = fixture([
    'timfeskens.seo.llms.enabled' => true,
    'timfeskens.seo.llms.include' => fn (Page $page) => $page->id() === 'recipes/cake',
    'timfeskens.seo.llms.markdown' => fn (Page $page) => '# ' . Seo::text($page->title()),
]);
check(str_contains(Endpoints::llms()->body(), 'recipes%2Fcake.md'), 'Explicit LLM inclusion');
check(!str_contains(Endpoints::llms()->body(), 'Hidden'), 'LLM excludes non-included content');
check(Endpoints::markdown('recipes%2Fcake')->code() === 200, 'Markdown opt in');
check(Endpoints::markdown('hidden')->code() === 404, 'Markdown excludes unlisted');

[$kirby, $page] = fixture(['timfeskens.seo.schema' => function (Page $page, array $graph): array {
    $graph[] = ['@type' => 'Recipe', 'name' => $page->title()->value()];
    return $graph;
}]);
check(count(Seo::schema($page, Seo::metadata($page))['@graph']) === 3, 'Schema extension API');

[$kirby, $page] = fixture(['timfeskens.seo.include' => fn (Page $page) => $page->id() !== 'recipes/cake']);
check(!Seo::indexable($page), 'Site-specific private content exclusion');
check(!str_contains(Endpoints::sitemap()->body(), '/cake'), 'Excluded content absent from sitemap');

[$kirby, $page] = fixture(['timfeskens.seo.indexnow.enabled' => true, 'timfeskens.seo.indexnow.key' => 'abcdefgh12345678']);
Integrations::enqueue($page);
Integrations::enqueue($page);
$sent = [];
check(Integrations::flush(function (array $payload) use (&$sent): int { $sent = $payload; return 202; }) === 1, 'IndexNow deduplicated batch');
check($sent['host'] === 'example.test', 'IndexNow canonical host');
check($sent['urlList'] === ['https://example.test/recipes/cake'], 'IndexNow URL payload');
Integrations::enqueue($page);
try {
    Integrations::flush(fn () => 500);
    check(false, 'Failed IndexNow batch throws');
} catch (RuntimeException) {
    check(count($kirby->cache('timfeskens.seo')->get('indexnow')) === 1, 'Failed IndexNow batch retained');
}
Integrations::flush(fn () => 200);
check(Integrations::flush(fn () => 200) === 0, 'Acknowledged batch cleared');

$input = null;
[$kirby, $page] = fixture([
    'timfeskens.seo.ai.enabled' => true,
    'timfeskens.seo.ai.source' => fn () => '<p>Only these facts</p>',
    'timfeskens.seo.ai.provider' => function (array $data) use (&$input): array {
        $input = $data;
        return ['title' => '<b>Suggested</b>', 'description' => 'A suggestion'];
    },
]);
try {
    Integrations::suggest($page);
    check(false, 'Unauthenticated AI rejected');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Not allowed.', 'Unauthenticated AI rejected');
}
$kirby->impersonate('kirby');
$suggestion = Integrations::suggest($page);
check($suggestion['title'] === 'Suggested', 'AI response sanitized');
check($input['text'] === 'Only these facts', 'Only configured AI source sent');
check($page->metaTitle()->isEmpty(), 'AI never writes metadata');

[$kirby, $page] = fixture();
$kirby->call('robots.txt');
check($kirby->response()->header('X-Robots-Tag') === null, 'No configuration-level robots header overrides Panel settings');

$kirby = new App([
    'roots' => ['index' => sys_get_temp_dir() . '/kirby-seo-multilang-' . getmypid()],
    'urls' => ['index' => 'https://example.test'],
    'options' => ['languages' => true, 'timfeskens.seo.canonicalBase' => ['nl' => 'https://example.nl', 'en' => 'https://example.com']],
    'languages' => [
        ['code' => 'nl', 'default' => true, 'locale' => 'nl_NL', 'url' => '/'],
        ['code' => 'en', 'locale' => 'en_GB', 'url' => '/en'],
    ],
    'site' => [
        'translations' => [['code' => 'nl', 'content' => ['title' => 'Voorbeeld']], ['code' => 'en', 'content' => ['title' => 'Example']]],
        'children' => [
            ['slug' => 'home', 'template' => 'home', 'translations' => [['code' => 'nl', 'content' => ['title' => 'Welkom']], ['code' => 'en', 'content' => ['title' => 'Welcome']]]],
            ['slug' => 'over', 'num' => 1, 'translations' => [['code' => 'nl', 'content' => ['title' => 'Over']], ['code' => 'en', 'slug' => 'about', 'content' => ['title' => 'About']]]],
            ['slug' => 'alleen-nl', 'num' => 2, 'translations' => [['code' => 'nl', 'content' => ['title' => 'Nederlands']]]],
            ['slug' => 'kopie', 'num' => 3, 'translations' => [
                ['code' => 'nl', 'content' => ['title' => 'Kopie']],
                ['code' => 'en', 'slug' => 'copy', 'content' => ['title' => 'Copy', 'canonicalUrl' => 'https://original.example.test/copy']],
            ]],
        ],
    ],
]);
$page = $kirby->page('over');
check(Seo::languages($page) === ['en', 'nl'] || Seo::languages($page) === ['nl', 'en'], 'Translated languages');
check(Seo::canonical($page, 'nl') === 'https://example.nl/over', 'Dutch canonical');
check(Seo::canonical($page, 'en') === 'https://example.com/en/about', 'Translated slug and canonical domain');
check(Endpoints::sitemapPriority($page, 'en') === '0.8', 'Language prefix does not lower priority');
check(Endpoints::sitemapPriority($page, 'nl') === '0.8', 'Language versions have matching priority');
check(Endpoints::sitemapPriority($kirby->site()->homePage(), 'en') === '1.0', 'Translated homepage priority is 1.0');
check(Seo::metadata($page, 'en')['tags']['og:locale'] === 'en_GB', 'Language locale');
check(Seo::metadata($page, 'en')['title'] === 'About - Example', 'Translated metadata');
check(Seo::languages($kirby->page('alleen-nl')) === ['nl'], 'Missing translations omitted');
check(str_contains(Endpoints::sitemap()->body(), 'hreflang="en"'), 'Sitemap language alternates');
check(str_contains(Seo::render($page), 'hreflang="nl"'), 'HTML language alternates');
$xml = Endpoints::sitemap()->body();
check(!str_contains($xml, 'https://example.com/en/copy'), 'Canonical override excluded from sitemap entries and alternates');
check(str_contains($xml, 'https://example.nl/kopie'), 'Other language remains in sitemap');

echo "Passed {$count} assertions.\n";
