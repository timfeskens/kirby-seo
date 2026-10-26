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
            return ['status' => 'credentials'];
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
        return ['status' => 'connected', 'metrics' => $metrics, 'date' => $date, 'target' => $target];
    }
}
