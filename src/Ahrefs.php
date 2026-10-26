<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;

final class Ahrefs
{
    public static function data(ModelWithContent $model): array
    {
        if (Seo::option('ahrefs.enabled', false) !== true) {
            throw new \RuntimeException('Integration is disabled.');
        }
        $key = Seo::option('ahrefs.apiKey');
        if (!is_string($key) || $key === '') {
            return ['status' => 'credentials', 'mode' => 'free'];
        }
        if (Seo::option('ahrefs.mode', 'free') !== 'paid') {
            // The public endpoint is free but still requires a free APIv3 key.
            // Domain Rating is domain-wide, so cache once across page reports.
            $site = $model instanceof Page ? $model->site() : $model;
            $target = strtolower((string)parse_url(Seo::canonical($site->homePage()), PHP_URL_HOST));
            $metrics = Analytics::cached('ahrefs-free:' . hash('sha256', $key) . ':' . $target, static function () use ($target, $key): array {
                $response = Analytics::request('https://api.ahrefs.com/v3/public/domain-rating-free?' . http_build_query([
                    'target' => $target, 'output' => 'json',
                ]), ['method' => 'GET', 'headers' => ['Authorization' => 'Bearer ' . $key, 'Accept' => 'application/json']]);
                $rating = $response['domain_rating']['domain_rating'] ?? null;
                if (!is_numeric($rating) || $rating < 0 || $rating > 100) {
                    throw new \RuntimeException('Invalid Ahrefs response.');
                }
                return ['domain_rating' => (float)$rating];
            });
            return ['status' => 'connected', 'mode' => 'free', 'metrics' => $metrics, 'target' => $target];
        }
        $target = Seo::canonical($model instanceof Page ? $model : $model->homePage());
        $mode = $model instanceof Page ? 'exact' : 'prefix';
        $date = gmdate('Y-m-d', strtotime('-1 day'));
        $metrics = Analytics::cached('ahrefs:' . hash('sha256', $key) . ':' . $target . ':' . $mode . ':' . $date, static function () use ($target, $mode, $date, $key): array {
            $response = Analytics::request('https://api.ahrefs.com/v3/site-explorer/metrics?' . http_build_query([
                'target' => $target, 'mode' => $mode, 'date' => $date, 'output' => 'json',
            ]), ['method' => 'GET', 'headers' => ['Authorization' => 'Bearer ' . $key, 'Accept' => 'application/json']]);
            if (!isset($response['metrics']) || !is_array($response['metrics'])) {
                throw new \RuntimeException('Invalid Ahrefs response.');
            }
            return array_intersect_key($response['metrics'], array_flip(['org_keywords', 'org_keywords_1_3', 'org_traffic', 'paid_keywords']));
        });
        return ['status' => 'connected', 'mode' => 'paid', 'metrics' => $metrics, 'date' => $date, 'target' => $target];
    }
}
