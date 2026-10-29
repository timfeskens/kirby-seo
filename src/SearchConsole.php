<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use Kirby\Cms\App;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Http\Response;
use RuntimeException;

/** Independent OAuth implementation using Google's read-only Search Console API. */
final class SearchConsole
{
    public static function configured(): bool
    {
        $credentials = self::credentials();
        return !empty($credentials['client_id']) && !empty($credentials['client_secret']) && is_string(Seo::option('searchConsole.tokenPath')) && Seo::option('searchConsole.tokenPath') !== '';
    }

    private static function requireConfiguration(): void
    {
        if (!self::configured()) throw new RuntimeException('Configure Google credentials and a private tokenPath.');
    }

    private static function credentials(): array
    {
        $credentials = Seo::option('searchConsole.credentials', []);
        return is_array($credentials) ? ($credentials['web'] ?? $credentials) : [];
    }

    private static function path(): string
    {
        $path = Seo::option('searchConsole.tokenPath');
        // Require explicit storage outside the document root (also outside Git).
        $parent = is_string($path) ? realpath(dirname($path)) : false;
        $root = realpath(App::instance()->root('index'));
        if (!$parent || !$root || $parent === $root || str_starts_with($parent . '/', $root . '/') || is_link($path)) {
            throw new RuntimeException('Configure a private Search Console tokenPath outside the document root.');
        }
        return $parent . '/' . basename($path);
    }

    private static function tokens(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }
        return json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private static function save(array $tokens): void
    {
        $path = self::path();
        $temporary = tempnam(dirname($path), '.seo-oauth-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot store connection.');
        }
        try {
            if (!chmod($temporary, 0600) || file_put_contents($temporary, json_encode($tokens, JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $path)) {
                throw new RuntimeException('Cannot store connection.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function redirectUri(): string
    {
        return rtrim(App::instance()->url('index'), '/') . '/__seo/gsc/callback';
    }

    public static function connect(bool $traffic = false): string
    {
        Analytics::authorize(admin: true);
        self::requireConfiguration();
        self::path();
        $credentials = self::credentials();
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            throw new RuntimeException('Configure Google OAuth credentials.');
        }
        $state = bin2hex(random_bytes(32));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        App::instance()->session()->set('timfeskens.seo.oauth', [
            'state' => $state, 'verifier' => $verifier, 'expires' => time() + 600,
            'user' => App::instance()->user()->id(),
            'traffic' => $traffic,
        ]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $credentials['client_id'], 'redirect_uri' => self::redirectUri(),
            'response_type' => 'code', 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    public static function callback(): Response
    {
        try {
            Analytics::authorize(admin: true);
            self::requireConfiguration();
            $kirby = App::instance();
            $pending = $kirby->session()->get('timfeskens.seo.oauth', []);
            $kirby->session()->remove('timfeskens.seo.oauth'); // One use, including failed attempts.
            $state = (string)$kirby->request()->get('state', '');
            $code = (string)$kirby->request()->get('code', '');
            if (empty($pending['state']) || !hash_equals($pending['state'], $state) || ($pending['expires'] ?? 0) < time() || ($pending['user'] ?? '') !== $kirby->user()->id() || $code === '') {
                throw new RuntimeException('Invalid OAuth callback.');
            }
            $tokens = self::tokenRequest(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::redirectUri(), 'code_verifier' => $pending['verifier']]);
            if (empty($tokens['refresh_token'])) {
                throw new RuntimeException('Missing refresh token. Reconnect with consent.');
            }
            self::save(['access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'], 'expires_at' => time() + (int)$tokens['expires_in'], 'connection' => bin2hex(random_bytes(16))]);
            return Response::redirect(rtrim($kirby->url('panel'), '/') . (($pending['traffic'] ?? false) === true ? '/traffic' : '/seo'));
        } catch (\Throwable) {
            return new Response('Google Search Console connection failed. Return to the Panel and try connecting again.', 'text/plain', 400, ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
        }
    }

    private static function tokenRequest(array $data): array
    {
        $credentials = self::credentials();
        $response = Analytics::request('https://oauth2.googleapis.com/token', [
            'method' => 'POST', 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'data' => http_build_query($data + ['client_id' => $credentials['client_id'] ?? '', 'client_secret' => $credentials['client_secret'] ?? '']),
        ]);
        if (empty($response['access_token']) || empty($response['expires_in'])) {
            throw new RuntimeException('Invalid token response.');
        }
        return $response;
    }

    private static function accessToken(): string
    {
        $tokens = self::tokens();
        if (empty($tokens['refresh_token'])) {
            throw new RuntimeException('Not connected.');
        }
        if (($tokens['expires_at'] ?? 0) < time() + 300) {
            $response = self::tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']]);
            $tokens['access_token'] = $response['access_token'];
            $tokens['refresh_token'] = $response['refresh_token'] ?? $tokens['refresh_token'];
            $tokens['expires_at'] = time() + (int)$response['expires_in'];
            self::save($tokens);
        }
        return $tokens['access_token'];
    }

    private static function properties(): array
    {
        $tokens = self::tokens();
        $result = Analytics::cached('gsc-properties:' . ($tokens['connection'] ?? hash('sha256', $tokens['refresh_token'] ?? '')), static fn (): array => Analytics::request(
            'https://www.googleapis.com/webmasters/v3/sites',
            ['method' => 'GET', 'headers' => ['Authorization' => 'Bearer ' . self::accessToken()]],
        ), 5);
        return array_values(array_filter(array_column($result['siteEntry'] ?? [], 'siteUrl'), 'is_string'));
    }

    public static function selectProperty(string $property): void
    {
        Analytics::authorize(admin: true);
        self::requireConfiguration();
        if (!in_array($property, self::properties(), true)) {
            throw new RuntimeException('Select a property available to this Google account.');
        }
        $tokens = self::tokens();
        $tokens['property'] = $property;
        self::save($tokens);
    }

    public static function availableProperties(): array
    {
        Analytics::authorize(admin: true);
        self::requireConfiguration();
        return ['status' => 'property', 'admin' => true, 'properties' => self::properties()];
    }

    public static function disconnect(): void
    {
        Analytics::authorize(admin: true);
        self::requireConfiguration();
        $path = self::path();
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Cannot remove connection.');
        }
    }

    private static function connection(): array
    {
        $admin = App::instance()->user()?->isAdmin() === true;
        if (!self::configured()) {
            return ['status' => 'credentials', 'admin' => $admin];
        }
        $tokens = self::tokens();
        if (empty($tokens['refresh_token'])) {
            return ['status' => 'disconnected', 'admin' => $admin];
        }
        $property = Seo::option('searchConsole.property') ?: ($tokens['property'] ?? null);
        if (!$property) {
            return ['status' => 'property', 'admin' => $admin, 'properties' => $admin ? self::properties() : []];
        }
        return ['status' => 'connected', 'admin' => $admin, 'property' => $property];
    }

    public static function data(ModelWithContent $model): array
    {
        $connection = self::connection();
        if ($connection['status'] !== 'connected') return $connection;
        $property = $connection['property'];
        $tokens = self::tokens();
        $body = ['startDate' => gmdate('Y-m-d', strtotime('-30 days')), 'endDate' => gmdate('Y-m-d', strtotime('-3 days')), 'dimensions' => ['query'], 'rowLimit' => 25000];
        if ($model instanceof Page) {
            // Exact canonical URL only: path-contains fallbacks mix unrelated pages/domains.
            $body['dimensionFilterGroups'] = [['filters' => [['dimension' => 'page', 'operator' => 'equals', 'expression' => Seo::canonical($model)]]]];
        }
        $rows = Analytics::cached('gsc:' . ($tokens['connection'] ?? hash('sha256', $tokens['refresh_token'])) . ':' . $property . ':' . json_encode($body), static function () use ($property, $body): array {
            $result = Analytics::request('https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($property) . '/searchAnalytics/query', [
                'method' => 'POST', 'headers' => ['Authorization' => 'Bearer ' . self::accessToken(), 'Content-Type' => 'application/json'], 'data' => Seo::json($body),
            ]);
            return array_map(static fn ($row) => [
                'query' => (string)($row['keys'][0] ?? ''), 'clicks' => (float)($row['clicks'] ?? 0),
                'impressions' => (float)($row['impressions'] ?? 0), 'ctr' => (float)($row['ctr'] ?? 0), 'position' => (float)($row['position'] ?? 0),
            ], $result['rows'] ?? []);
        });
        return $connection + ['rows' => $rows, 'startDate' => $body['startDate'], 'endDate' => $body['endDate']];
    }

    /** Property totals are independent of privacy-filtered query breakdowns. */
    public static function report(int $days = 30): array
    {
        Analytics::authorize(admin: true);
        $base = ['days' => in_array($days, [7, 30, 90], true) ? $days : 30];
        try {
            $connection = self::connection();
            if ($connection['status'] !== 'connected') return $base + $connection;
            $property = $connection['property'];
            $tokens = self::tokens();
            $end = (new \DateTimeImmutable('today', new \DateTimeZone('America/Los_Angeles')))->modify('-3 days');
            $body = ['startDate' => $end->modify('-' . ($base['days'] - 1) . ' days')->format('Y-m-d'), 'endDate' => $end->format('Y-m-d'), 'type' => 'web', 'dataState' => 'final'];
            $data = Analytics::cached('gsc-dashboard:v1:' . ($tokens['connection'] ?? hash('sha256', $tokens['refresh_token'])) . ':' . $property . ':' . json_encode($body), static function () use ($body, $property): array {
                $query = static function (array $dimensions, int $limit) use ($body, $property): array {
                    $result = Analytics::request('https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($property) . '/searchAnalytics/query', [
                        'method' => 'POST', 'headers' => ['Authorization' => 'Bearer ' . self::accessToken(), 'Content-Type' => 'application/json'],
                        'data' => Seo::json($body + ['dimensions' => $dimensions, 'rowLimit' => $limit]),
                    ]);
                    return array_map(static fn (array $row): array => [
                        'label' => (string)($row['keys'][0] ?? ''), 'clicks' => (float)($row['clicks'] ?? 0), 'impressions' => (float)($row['impressions'] ?? 0), 'ctr' => (float)($row['ctr'] ?? 0), 'position' => (float)($row['position'] ?? 0),
                    ], $result['rows'] ?? []);
                };
                $totals = $query([], 1)[0] ?? ['clicks' => 0.0, 'impressions' => 0.0, 'ctr' => 0.0, 'position' => 0.0];
                unset($totals['label']);
                $daily = array_column($query(['date'], 90), null, 'label');
                $series = [];
                for ($day = new \DateTimeImmutable($body['startDate']); $day->format('Y-m-d') <= $body['endDate']; $day = $day->modify('+1 day')) {
                    $date = $day->format('Y-m-d');
                    $row = $daily[$date] ?? ['label' => $date, 'clicks' => 0.0, 'impressions' => 0.0, 'ctr' => 0.0, 'position' => null];
                    if ($row['impressions'] <= 0) $row['position'] = null;
                    $series[] = $row;
                }
                return ['totals' => $totals, 'daily' => $series, 'queries' => $query(['query'], 100), 'pages' => $query(['page'], 100), 'countries' => $query(['country'], 100), 'devices' => $query(['device'], 10)];
            }, 60);
            return $base + $connection + $body + $data;
        } catch (AnalyticsException $error) {
            return $base + ['status' => 'error', 'reason' => $error->reason];
        } catch (\Throwable) {
            return $base + ['status' => 'error'];
        }
    }

    public static function manage(string $action): array
    {
        Analytics::authorize(admin: true);
        try {
            if ($action === 'connect') return ['url' => self::connect(traffic: true)];
            if ($action === 'properties') return self::availableProperties();
            if ($action === 'property') {
                self::selectProperty((string)App::instance()->request()->get('property', ''));
                return ['ok' => true];
            }
            if ($action === 'disconnect') {
                self::disconnect();
                return ['ok' => true];
            }
        } catch (\Throwable) {
            // No credentials, token paths or upstream errors in API responses.
        }
        return ['status' => 'error'];
    }
}
