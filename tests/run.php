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

$count = 0;
function check(bool $condition, string $message): void
{
    global $count;
    $count++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fixture(array $options = []): array
{
    $root = sys_get_temp_dir() . '/kirby-seo-tests-' . getmypid();
    $kirby = new App([
        'roots' => ['index' => $root, 'cache' => $root . '/cache'],
        'urls' => ['index' => 'https://preview.example.test'],
        'options' => array_replace_recursive([
            'timfeskens.seo.canonicalBase' => 'https://example.test',
            'timfeskens.seo.locale' => 'nl_NL',
        ], $options),
        'site' => [
            'content' => ['title' => 'Example', 'metaDescription' => 'Site description'],
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
check(!str_contains($xml, '/error'), 'Error page absent from sitemap');
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
check(isset($blueprint['fields']['canonicalUrl']), 'Page blueprint registered');
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
foreach (['site', 'page'] as $type) {
    $model = $type === 'site' ? $kirby->site() : $page;
    $blueprint = new Kirby\Cms\Blueprint(['name' => 'seo-test-' . $type, 'model' => $model, 'tabs' => ['seo' => 'tabs/seo-' . $type]]);
    $fields = new Kirby\Form\Fields($blueprint->fields(), $model);
    check(count($fields->toArray()) > 5, 'SEO ' . $type . ' blueprint resolves all fields');
    check(isset($fields->toProps()['seopreview']['preview']), 'SEO ' . $type . ' Panel props resolve');
    $props = $fields->toProps();
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
    }
}
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

[$kirby, $page] = fixture(['timfeskens.seo.noindex' => true, 'timfeskens.seo.llms.enabled' => true, 'timfeskens.seo.llms.include' => fn () => true, 'timfeskens.seo.indexnow.enabled' => true, 'timfeskens.seo.indexnow.key' => 'abcdefgh12345678']);
check(!Seo::indexable($page), 'Environment noindex takes precedence');
check(str_contains(Seo::metadata($page)['tags']['robots'], 'noindex'), 'Noindex meta');
check(str_contains(Endpoints::robots()->body(), 'Disallow: /'), 'Preview robots disallow');
check(!str_contains(Endpoints::sitemap()->body(), '<url>'), 'Preview sitemap empty');
check(Endpoints::llms()->code() === 404, 'Preview cannot export llms');
check(!Integrations::indexNowEnabled(), 'Preview cannot submit IndexNow');

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

[$kirby, $page] = fixture(['timfeskens.seo.noindex' => true]);
$kirby->call('robots.txt');
check($kirby->response()->header('X-Robots-Tag') === 'noindex, nofollow, noarchive', 'Global noindex response header');

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
check(Seo::metadata($page, 'en')['tags']['og:locale'] === 'en_GB', 'Language locale');
check(Seo::metadata($page, 'en')['title'] === 'About - Example', 'Translated metadata');
check(Seo::languages($kirby->page('alleen-nl')) === ['nl'], 'Missing translations omitted');
check(str_contains(Endpoints::sitemap()->body(), 'hreflang="en"'), 'Sitemap language alternates');
check(str_contains(Seo::render($page), 'hreflang="nl"'), 'HTML language alternates');
$xml = Endpoints::sitemap()->body();
check(!str_contains($xml, 'https://example.com/en/copy'), 'Canonical override excluded from sitemap entries and alternates');
check(str_contains($xml, 'https://example.nl/kopie'), 'Other language remains in sitemap');

echo "Passed {$count} assertions.\n";
