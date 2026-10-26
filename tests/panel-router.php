<?php

declare(strict_types=1);

// Isolated loopback-only fixture. Never used by either consuming site.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/index.php';

$root = getenv('SEO_PANEL_TEST_ROOT');
if (!$root || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/media/site/')) {
    $file = realpath($root . $path);
    if ($file && str_starts_with($file, realpath($root . '/media') . '/') && is_file($file)) {
        echo Kirby\Http\Response::file($file);
        return;
    }
}
if (str_starts_with($path, '/kirby/') || preg_match('~^/media/panel/[^/]+/(.*)$~', $path, $matches)) {
    $file = realpath(dirname(__DIR__) . (isset($matches[1]) ? '/kirby/panel/dist/' . $matches[1] : $path));
    $assets = realpath(dirname(__DIR__) . '/kirby/panel/dist') . '/';
    if ($file && str_starts_with($file, $assets) && is_file($file)) {
        header('Content-Type: ' . match (pathinfo($file, PATHINFO_EXTENSION)) {
            'js' => 'application/javascript', 'css' => 'text/css', 'woff2' => 'font/woff2', default => 'application/octet-stream',
        });
        readfile($file);
        return;
    }
    http_response_code(404);
    return;
}
Kirby\Filesystem\Dir::make($root . '/content');
if (!is_file($root . '/content/social.png')) {
    $image = imagecreatetruecolor(640, 480);
    imagefill($image, 0, 0, imagecolorallocate($image, 75, 120, 190));
    imagepng($image, $root . '/content/social.png');
}
$kirby = new Kirby\Cms\App([
    'roots' => ['index' => $root],
    'urls' => ['index' => 'http://' . $_SERVER['HTTP_HOST']],
    'options' => [
        'debug' => true, 'panel.install' => false, 'api.allowImpersonation' => true, 'timfeskens.seo.canonicalBase' => 'https://example.test',
        'timfeskens.seo.searchConsole.enabled' => getenv('SEO_PANEL_ANALYTICS') === '1',
        'timfeskens.seo.ahrefs.enabled' => getenv('SEO_PANEL_ANALYTICS') === '1',
        'timfeskens.seo.ahrefs.apiKey' => 'fixture-only',
        'timfeskens.seo.ahrefs.mode' => 'paid',
        'timfeskens.seo.analyticsTransport' => static fn () => ['metrics' => ['org_keywords' => 7, 'org_traffic' => 42]],
    ],
    'blueprints' => [
        'site' => ['title' => 'Test', 'tabs' => ['seo' => 'tabs/seo-site']],
        'pages/default' => ['title' => 'Test', 'tabs' => ['seo' => 'tabs/seo-page']],
    ],
    'users' => [['id' => 'seo-test', 'email' => 'seo@example.test', 'role' => 'admin', 'language' => 'nl']],
    'site' => ['content' => ['title' => 'Example', 'ogImage' => '- social.png'], 'files' => [['filename' => 'social.png']], 'children' => [
        ['slug' => 'home', 'content' => ['title' => 'Home', 'metaTemplate' => '{{ site.title }} | Developer', 'metaDescription' => 'Saved homepage description', 'ogTemplate' => 'Homepage social override', 'ogDescription' => 'Saved homepage social description', 'useTitleTemplate' => 'false', 'useOgTemplate' => 'false']],
        ['slug' => 'error', 'content' => ['title' => 'Error']],
        ['slug' => 'test', 'num' => 1, 'content' => ['title' => 'Test page']],
    ]],
]);
$kirby->impersonate('seo-test');
echo $kirby->render();
