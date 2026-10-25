<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Remote;
use RuntimeException;

final class Integrations
{
    /** Suggestions are never persisted, and no provider is contacted implicitly. */
    public static function suggest(Page $page): array
    {
        $provider = Seo::option('ai.provider');
        $source = Seo::option('ai.source');
        if (Seo::option('ai.enabled', false) !== true || !is_callable($provider) || !is_callable($source)) {
            throw new RuntimeException('AI assistance is not configured.');
        }
        $user = App::instance()->user();
        if (!$user || !$page->permissions()->can('update')) {
            throw new RuntimeException('Not allowed.');
        }
        $result = $provider([
            'title' => Seo::text($page->title()),
            'text' => mb_substr(Seo::text($source($page)), 0, 12000),
            'language' => Seo::metadata($page)['language'],
            'instruction' => 'Suggest a concise SEO title and description using only the supplied facts. Return title and description. Never invent claims, ingredients, nutrition, ratings or achievements.',
        ]);
        if (!is_array($result) || !is_string($result['title'] ?? null) || !is_string($result['description'] ?? null)) {
            throw new RuntimeException('The provider returned an invalid suggestion.');
        }
        return ['title' => mb_substr(Seo::text($result['title']), 0, 200), 'description' => mb_substr(Seo::text($result['description']), 0, 500)];
    }

    public static function key(): ?string
    {
        $key = Seo::option('indexnow.key');
        return is_string($key) && preg_match('/^[a-zA-Z0-9-]{8,128}$/D', $key) ? $key : null;
    }

    public static function indexNowEnabled(): bool
    {
        return Seo::option('indexnow.enabled', false) === true && self::key() !== null;
    }

    /** Queue only; publishing never waits on a third-party network request. */
    public static function enqueue(Page $page): void
    {
        if (!self::indexNowEnabled() || !Seo::indexable($page)) {
            return;
        }
        $cache = App::instance()->cache('timfeskens.seo');
        $urls = $cache->get('indexnow', []);
        foreach (Seo::languages($page) as $language) {
            if (Seo::indexable($page, $language)) {
                $urls[] = Seo::canonical($page, $language);
            }
        }
        $cache->set('indexnow', array_slice(array_values(array_unique($urls)), -10000));
    }

    /** Run from a serialized scheduled job, never from a public route. */
    public static function flush(?callable $transport = null): int
    {
        if (!self::indexNowEnabled()) {
            return 0;
        }
        $cache = App::instance()->cache('timfeskens.seo');
        $urls = $cache->get('indexnow', []);
        if ($urls === []) {
            return 0;
        }
        $home = Seo::canonical(App::instance()->site()->homePage());
        $host = parse_url($home, PHP_URL_HOST);
        if (parse_url($home, PHP_URL_SCHEME) !== 'https' || !$host || !str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('IndexNow requires a public HTTPS canonical hostname.');
        }
        $urls = array_values(array_filter($urls, fn ($url) => parse_url($url, PHP_URL_HOST) === $host && parse_url($url, PHP_URL_SCHEME) === 'https'));
        $payload = ['host' => $host, 'key' => self::key(), 'keyLocation' => rtrim($home, '/') . '/' . self::key() . '.txt', 'urlList' => $urls];
        $transport ??= static function (array $payload): int {
            return Remote::request('https://api.indexnow.org/indexnow', [
                'method' => 'POST',
                'headers' => ['Content-Type' => 'application/json'],
                'data' => Seo::json($payload),
                'timeout' => 10,
            ])->code();
        };
        if ($urls !== []) {
            $status = $transport($payload);
            if (!in_array($status, [200, 202], true)) {
                throw new RuntimeException('IndexNow did not accept the batch; queued URLs retained.');
            }
        }
        $cache->remove('indexnow');
        return count($urls);
    }
}
