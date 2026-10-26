<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use Kirby\Cms\App;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use RuntimeException;

/** Server-only analytics: no credentials or provider errors reach Panel props. */
final class Analytics
{
    public static function authorize(?ModelWithContent $model = null, bool $admin = false): void
    {
        $user = App::instance()->user();
        if (!$user || ($admin && !$user->isAdmin()) || ($model && !$model->permissions()->can('update'))) {
            throw new RuntimeException('Not allowed.');
        }
    }

    public static function providers(): array
    {
        return array_values(array_filter(['searchConsole', 'ahrefs'], static fn ($name) => Seo::option($name . '.enabled', false) === true));
    }

    /** Fixed endpoints only; redirects are forbidden so bearer tokens stay at their origin. */
    public static function request(string $url, array $options): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($host, ['oauth2.googleapis.com', 'www.googleapis.com', 'api.ahrefs.com'], true)) {
            throw new RuntimeException('Unsupported analytics endpoint.');
        }
        try {
            $transport = Seo::option('analyticsTransport'); // Optional server-side test adapter.
            if (is_callable($transport)) {
                return $transport($url, $options);
            }
            $curl = curl_init($url);
            $headers = [];
            foreach ($options['headers'] ?? [] as $key => $value) {
                $headers[] = $key . ': ' . str_replace(["\r", "\n"], '', $value);
            }
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CUSTOMREQUEST => $options['method'] ?? 'GET', CURLOPT_HTTPHEADER => $headers,
            ]);
            if (isset($options['data'])) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $options['data']);
            }
            $body = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($body === false || $status < 200 || $status >= 300) {
                throw new RuntimeException();
            }
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($data) || isset($data['error'])) {
                throw new RuntimeException();
            }
            return $data;
        } catch (\Throwable) {
            // Upstream errors can contain request credentials. Never propagate them.
            throw new RuntimeException('Analytics request failed. Check credentials, access and API quota.');
        }
    }

    public static function cached(string $key, callable $load): array
    {
        $cache = App::instance()->cache('timfeskens.seo');
        $key = 'analytics-' . hash('sha256', $key);
        $value = $cache->get($key);
        if (is_array($value)) {
            return $value;
        }
        $value = $load();
        $cache->set($key, $value, 1440);
        return $value;
    }

    public static function data(ModelWithContent $model, string $provider): array
    {
        self::authorize($model);
        if (!in_array($provider, self::providers(), true)) {
            throw new RuntimeException('Integration is disabled.');
        }
        // Do not send draft/private page URLs to external analytics providers.
        if ($model instanceof Page && !Seo::indexable($model)) {
            return ['status' => 'excluded'];
        }
        try {
            return $provider === 'searchConsole' ? SearchConsole::data($model) : Ahrefs::data($model);
        } catch (\Throwable) {
            return ['status' => 'error', 'admin' => App::instance()->user()?->isAdmin() === true];
        }
    }
}
