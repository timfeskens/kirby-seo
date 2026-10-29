<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/index.php';

$root = getenv('SEO_DASHBOARD_ROOT');
if (!$root || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/kirby/') || preg_match('~^/media/panel/[^/]+/(.*)$~', $path, $matches)) {
    $file = realpath(dirname(__DIR__) . (isset($matches[1]) ? '/kirby/panel/dist/' . $matches[1] : $path));
    if ($file && str_starts_with($file, realpath(dirname(__DIR__) . '/kirby/panel/dist') . '/') && is_file($file)) {
        header('Content-Type: ' . match (pathinfo($file, PATHINFO_EXTENSION)) {
            'js' => 'application/javascript', 'css' => 'text/css', 'woff2' => 'font/woff2', default => 'application/octet-stream',
        });
        readfile($file);
        return;
    }
    http_response_code(404);
    return;
}
foreach (['', '/home', '/error'] as $directory) {
    Kirby\Filesystem\Dir::make($root . '/content' . $directory);
}
if (!is_file($root . '/content/site.txt')) file_put_contents($root . '/content/site.txt', "Title: Fictional site\n\n----\n\nEmail: keep@example.test\n\n----\n\nMetaDescription: Existing global description");
foreach (['home', 'error'] as $slug) {
    if (!is_file($root . '/content/' . $slug . '/default.txt')) file_put_contents($root . '/content/' . $slug . '/default.txt', 'Title: ' . ucfirst($slug));
}
$kirby = new Kirby\Cms\App([
    'roots' => ['index' => $root], 'urls' => ['index' => 'http://' . $_SERVER['HTTP_HOST']],
    'options' => [
        'debug' => true, 'panel.install' => false, 'api.allowImpersonation' => true,
        'timfeskens.seo.searchConsole.credentials' => is_file($root . '/configured') ? ['client_id' => 'fixture', 'client_secret' => 'fixture-secret'] : null,
        'timfeskens.seo.searchConsole.tokenPath' => $root . '-google.json',
        'timfeskens.seo.analyticsTransport' => static function ($url, $options) use ($root): array {
            if (is_file($root . '/api-error')) throw \TimFeskens\Seo\AnalyticsException::response([], 429);
            if (!str_contains($url, 'searchAnalytics')) return ['siteEntry' => [['siteUrl' => 'sc-domain:example.test']]];
            $body = json_decode($options['data'], true);
            $dimension = $body['dimensions'][0] ?? '';
            if ($dimension === 'date') {
                $rows = [];
                for ($day = new DateTimeImmutable($body['startDate']); $day->format('Y-m-d') <= $body['endDate']; $day = $day->modify('+1 day')) {
                    $rows[] = ['keys' => [$day->format('Y-m-d')], 'clicks' => (int)$day->format('j'), 'impressions' => 100, 'ctr' => .05, 'position' => 3.2];
                }
                return ['rows' => $rows];
            }
            return ['rows' => [['keys' => [match ($dimension) {
                'query' => '<script>window.pwned=true</script>', 'page' => 'https://example.test/fictional', 'country' => 'nld', 'device' => 'MOBILE', default => '',
            }], 'clicks' => 35, 'impressions' => 700, 'ctr' => .05, 'position' => 3.2]]];
        },
    ],
    // No tabs/seo-site and no traffic plugin: both sidebar areas must work alone.
    'blueprints' => ['site' => ['title' => 'Site', 'fields' => ['email' => ['type' => 'email']]]],
    'users' => [['id' => 'seo-admin', 'email' => 'seo@example.test', 'role' => 'admin', 'language' => 'nl']],
]);
$kirby->impersonate('seo-admin');
if ($path === '/fixture/connect') {
    touch($root . '/configured');
    file_put_contents($root . '-google.json', json_encode(['refresh_token' => 'fictional-refresh', 'access_token' => 'fictional-access', 'expires_at' => time() + 3600, 'connection' => 'fictional', 'property' => 'sc-domain:example.test']));
    echo 'ok';
    return;
}
if ($path === '/fixture/error') {
    touch($root . '/api-error');
    $kirby->cache('timfeskens.seo')->flush();
    echo 'ok';
    return;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
echo $kirby->render();
