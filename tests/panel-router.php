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
$kirby = new Kirby\Cms\App([
    'roots' => ['index' => $root],
    'urls' => ['index' => 'http://' . $_SERVER['HTTP_HOST']],
    'options' => ['debug' => true, 'panel.install' => false, 'api.allowImpersonation' => true, 'timfeskens.seo.canonicalBase' => 'https://example.test', 'timfeskens.seo.noindex' => ($_COOKIE['seo_test_blocked'] ?? '') === '1'],
    'blueprints' => [
        'site' => ['title' => 'Test', 'tabs' => ['seo' => 'tabs/seo-site']],
        'pages/default' => ['title' => 'Test', 'tabs' => ['seo' => 'tabs/seo-page']],
    ],
    'users' => [['id' => 'seo-test', 'email' => 'seo@example.test', 'role' => 'admin', 'language' => 'nl']],
    'site' => ['content' => ['title' => 'Example'], 'children' => [
        ['slug' => 'home', 'content' => ['title' => 'Home']],
        ['slug' => 'error', 'content' => ['title' => 'Error']],
        ['slug' => 'test', 'num' => 1, 'content' => ['title' => 'Test page']],
    ]],
]);
$kirby->impersonate('seo-test');
echo $kirby->render();
